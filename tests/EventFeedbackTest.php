<?php
/**
 * Tests for inc/event-feedback.php — tapahtumakohtainen palautekysely (#666).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventFeedbackTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Registers a `rytkoset_event` fixture with a given date.
	 */
	private function event( int $id, string $date = '', string $title = 'Sukujuhla' ): void {
		rytkoset_test_register_post( $id, 'rytkoset_event', $title, 0 );

		if ( '' !== $date ) {
			update_post_meta( $id, rytkoset_theme_get_event_date_meta_key(), $date );
		}
	}

	private function feedback_meta(): array {
		return rytkoset_theme_get_event_feedback_meta_keys();
	}

	// --- mode getter ---------------------------------------------------

	public function test_mode_defaults_to_disabled(): void {
		$this->event( 10, '2020-01-01' );

		$this->assertSame( 'disabled', rytkoset_theme_get_event_feedback_mode( 10 ) );
	}

	public function test_mode_rejects_unknown_value(): void {
		$this->event( 10, '2020-01-01' );
		update_post_meta( 10, $this->feedback_meta()['mode'], 'bogus' );

		$this->assertSame( 'disabled', rytkoset_theme_get_event_feedback_mode( 10 ) );
	}

	// --- settings save ---------------------------------------------------

	private function save_settings( int $event_id, array $post ): void {
		$_POST = array_merge(
			array( 'rytkoset_event_feedback_nonce' => 'rytkoset_save_event_feedback' ),
			$post
		);
		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;

		rytkoset_theme_save_event_feedback_settings( $event_id );
	}

	public function test_save_persists_manual_mode_deadline_and_intro(): void {
		$this->event( 10, '2020-01-01' );

		$this->save_settings(
			10,
			array(
				'rytkoset_event_feedback_mode'     => 'manual',
				'rytkoset_event_feedback_deadline' => '2020-02-01',
				'rytkoset_event_feedback_intro'    => 'Kiitos osallistumisesta!',
			)
		);

		$this->assertSame( 'manual', rytkoset_theme_get_event_feedback_mode( 10 ) );
		$this->assertSame( '2020-02-01', rytkoset_theme_get_event_feedback_deadline_raw( 10 ) );
		$this->assertSame( 'Kiitos osallistumisesta!', rytkoset_theme_get_event_feedback_intro( 10 ) );
	}

	public function test_save_persists_automatic_mode_with_valid_post_event_send_at(): void {
		$this->event( 20, '2020-01-01' );

		$this->save_settings(
			20,
			array(
				'rytkoset_event_feedback_mode'    => 'automatic',
				'rytkoset_event_feedback_send_at' => '2020-01-05T10:00',
			)
		);

		$this->assertSame( 'automatic', rytkoset_theme_get_event_feedback_mode( 20 ) );
		$this->assertSame( '2020-01-05T10:00', rytkoset_theme_get_event_feedback_send_at_raw( 20 ) );
	}

	public function test_save_falls_back_to_manual_when_automatic_send_at_missing(): void {
		$this->event( 21, '2020-01-01' );

		$this->save_settings( 21, array( 'rytkoset_event_feedback_mode' => 'automatic' ) );

		$this->assertSame( 'manual', rytkoset_theme_get_event_feedback_mode( 21 ) );
	}

	public function test_save_falls_back_to_manual_when_automatic_send_at_is_before_event(): void {
		$this->event( 22, '2020-01-10' );

		$this->save_settings(
			22,
			array(
				'rytkoset_event_feedback_mode'    => 'automatic',
				'rytkoset_event_feedback_send_at' => '2020-01-01T10:00',
			)
		);

		$this->assertSame( 'manual', rytkoset_theme_get_event_feedback_mode( 22 ) );
	}

	public function test_save_never_touches_queued_at(): void {
		$this->event( 23, '2020-01-01' );
		update_post_meta( 23, $this->feedback_meta()['queued_at'], '2020-02-01 10:00:00' );

		$this->save_settings( 23, array( 'rytkoset_event_feedback_mode' => 'manual' ) );

		$this->assertSame( '2020-02-01 10:00:00', rytkoset_theme_get_event_feedback_queued_at( 23 ) );
	}

	public function test_save_requires_capability_and_nonce(): void {
		$this->event( 24, '2020-01-01' );

		// No cap: nothing saved.
		$_POST = array(
			'rytkoset_event_feedback_nonce' => 'rytkoset_save_event_feedback',
			'rytkoset_event_feedback_mode'  => 'manual',
		);
		rytkoset_theme_save_event_feedback_settings( 24 );
		$this->assertSame( 'disabled', rytkoset_theme_get_event_feedback_mode( 24 ) );

		// Wrong nonce: nothing saved even with capability.
		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;
		$_POST['rytkoset_event_feedback_nonce']     = 'wrong';
		rytkoset_theme_save_event_feedback_settings( 24 );
		$this->assertSame( 'disabled', rytkoset_theme_get_event_feedback_mode( 24 ) );
	}

	// --- send_at-after-event pure decision ------------------------------

	public function test_send_at_after_event_accepts_datetime_past_event_day(): void {
		$this->event( 30, '2020-01-01' );

		// The event day's cutoff is the start of the following day (2020-01-02
		// 00:00), matching rytkoset_theme_is_event_date_passed()'s own end-of-day
		// rule — so exactly midnight is not yet "after", only strictly later is.
		$this->assertTrue( rytkoset_theme_event_feedback_send_at_is_after_event( 30, '2020-01-02T00:01' ) );
		$this->assertFalse( rytkoset_theme_event_feedback_send_at_is_after_event( 30, '2020-01-02T00:00' ) );
		$this->assertFalse( rytkoset_theme_event_feedback_send_at_is_after_event( 30, '2020-01-01T23:59' ) );
		$this->assertFalse( rytkoset_theme_event_feedback_send_at_is_after_event( 30, '' ) );
	}

	// --- survey open state ------------------------------------------------

	public function test_survey_is_closed_when_disabled(): void {
		$this->event( 40, '2020-01-01' );

		$this->assertFalse( rytkoset_theme_event_feedback_survey_is_open( 40 ) );
	}

	public function test_survey_is_closed_before_event_date_even_when_enabled(): void {
		$this->event( 41, '2099-01-01' );
		update_post_meta( 41, $this->feedback_meta()['mode'], 'manual' );

		$this->assertFalse( rytkoset_theme_event_feedback_survey_is_open( 41 ) );
	}

	public function test_survey_is_open_after_event_date_without_deadline(): void {
		$this->event( 42, '2020-01-01' );
		update_post_meta( 42, $this->feedback_meta()['mode'], 'manual' );

		$this->assertTrue( rytkoset_theme_event_feedback_survey_is_open( 42 ) );
	}

	public function test_survey_respects_deadline(): void {
		$this->event( 43, '2020-01-01' );
		update_post_meta( 43, $this->feedback_meta()['mode'], 'manual' );
		update_post_meta( 43, $this->feedback_meta()['deadline'], '2099-01-01' );
		$this->assertTrue( rytkoset_theme_event_feedback_survey_is_open( 43 ) );

		update_post_meta( 43, $this->feedback_meta()['deadline'], '2020-01-02' );
		$this->assertFalse( rytkoset_theme_event_feedback_survey_is_open( 43 ) );
	}

	// --- auto-queue due decision -----------------------------------------

	public function test_auto_queue_due_requires_automatic_mode(): void {
		$this->event( 50, '2020-01-01' );
		update_post_meta( 50, $this->feedback_meta()['mode'], 'manual' );
		update_post_meta( 50, $this->feedback_meta()['send_at'], '2020-01-02T00:00' );

		$this->assertFalse( rytkoset_theme_event_feedback_auto_queue_is_due( 50, strtotime( '2020-06-01' ) ) );
	}

	public function test_auto_queue_due_is_false_before_send_at_and_true_after(): void {
		$this->event( 51, '2020-01-01' );
		update_post_meta( 51, $this->feedback_meta()['mode'], 'automatic' );
		update_post_meta( 51, $this->feedback_meta()['send_at'], '2020-01-05T10:00' );

		$send_at = rytkoset_theme_get_event_feedback_send_at_timestamp( 51 );

		$this->assertFalse( rytkoset_theme_event_feedback_auto_queue_is_due( 51, $send_at - 60 ) );
		$this->assertTrue( rytkoset_theme_event_feedback_auto_queue_is_due( 51, $send_at ) );
	}

	public function test_auto_queue_due_is_false_once_already_queued(): void {
		$this->event( 52, '2020-01-01' );
		update_post_meta( 52, $this->feedback_meta()['mode'], 'automatic' );
		update_post_meta( 52, $this->feedback_meta()['send_at'], '2020-01-05T10:00' );
		update_post_meta( 52, $this->feedback_meta()['queued_at'], '2020-01-06 00:00:00' );

		$this->assertFalse( rytkoset_theme_event_feedback_auto_queue_is_due( 52, strtotime( '2021-01-01' ) ) );
	}

	// --- idempotent processing --------------------------------------------

	public function test_process_auto_queue_enqueues_job_and_marks_queued(): void {
		$GLOBALS['rytkoset_test_now'] = '2020-01-06';
		$this->event( 60, '2020-01-01' );
		update_post_meta( 60, $this->feedback_meta()['mode'], 'automatic' );
		update_post_meta( 60, $this->feedback_meta()['send_at'], '2020-01-05T10:00' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		rytkoset_test_register_post( 601, 'event_registration', 'Osallistuja - Sukujuhla' );
		update_post_meta( 601, $meta_keys['event_id'], 60 );
		update_post_meta( 601, $meta_keys['name'], 'Maija Meikäläinen' );
		update_post_meta( 601, $meta_keys['email'], 'maija@example.test' );
		update_post_meta( 601, $meta_keys['status'], 'confirmed' );

		rytkoset_theme_process_event_feedback_auto_queue();

		$this->assertNotSame( '', rytkoset_theme_get_event_feedback_queued_at( 60 ) );
		$queue = rytkoset_theme_get_event_messaging_queue();
		$this->assertCount( 1, $queue );
		$this->assertSame( 60, $queue[0]['event_id'] );
		$this->assertSame( 1, $queue[0]['total_count'] );
	}

	public function test_process_auto_queue_does_not_double_queue_on_repeat_run(): void {
		$GLOBALS['rytkoset_test_now'] = '2020-01-06';
		$this->event( 61, '2020-01-01' );
		update_post_meta( 61, $this->feedback_meta()['mode'], 'automatic' );
		update_post_meta( 61, $this->feedback_meta()['send_at'], '2020-01-05T10:00' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		rytkoset_test_register_post( 611, 'event_registration', 'Osallistuja - Sukujuhla' );
		update_post_meta( 611, $meta_keys['event_id'], 61 );
		update_post_meta( 611, $meta_keys['name'], 'Maija Meikäläinen' );
		update_post_meta( 611, $meta_keys['email'], 'maija@example.test' );
		update_post_meta( 611, $meta_keys['status'], 'confirmed' );

		rytkoset_theme_process_event_feedback_auto_queue();
		// Simulates a second cron run reaching the same due event (e.g. a
		// re-run before the lock in the first pass expired, or a stale query
		// result) — the queued_at guard must still prevent a second job.
		rytkoset_theme_process_event_feedback_auto_queue();

		$this->assertCount( 1, rytkoset_theme_get_event_messaging_queue() );
	}

	public function test_process_auto_queue_marks_queued_without_recipients(): void {
		$GLOBALS['rytkoset_test_now'] = '2020-01-06';
		$this->event( 62, '2020-01-01' );
		update_post_meta( 62, $this->feedback_meta()['mode'], 'automatic' );
		update_post_meta( 62, $this->feedback_meta()['send_at'], '2020-01-05T10:00' );

		rytkoset_theme_process_event_feedback_auto_queue();

		$this->assertNotSame( '', rytkoset_theme_get_event_feedback_queued_at( 62 ) );
		$this->assertCount( 0, rytkoset_theme_get_event_messaging_queue() );
	}

	// --- recipient eligibility --------------------------------------------

	public function test_recipients_include_active_statuses_and_exclude_dead_ones(): void {
		$this->event( 70, '2020-01-01' );
		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		rytkoset_test_register_post( 701, 'event_registration', 'Confirmed - Sukujuhla' );
		update_post_meta( 701, $meta_keys['event_id'], 70 );
		update_post_meta( 701, $meta_keys['name'], 'Vahvistettu' );
		update_post_meta( 701, $meta_keys['email'], 'vahvistettu@example.test' );
		update_post_meta( 701, $meta_keys['status'], 'confirmed' );

		rytkoset_test_register_post( 702, 'event_registration', 'Pending - Sukujuhla' );
		update_post_meta( 702, $meta_keys['event_id'], 70 );
		update_post_meta( 702, $meta_keys['name'], 'Odottava' );
		update_post_meta( 702, $meta_keys['email'], 'odottava@example.test' );
		update_post_meta( 702, $meta_keys['status'], 'pending' );

		rytkoset_test_register_post( 703, 'event_registration', 'Cancelled - Sukujuhla' );
		update_post_meta( 703, $meta_keys['event_id'], 70 );
		update_post_meta( 703, $meta_keys['name'], 'Peruttu' );
		update_post_meta( 703, $meta_keys['email'], 'peruttu@example.test' );
		update_post_meta( 703, $meta_keys['status'], 'cancelled' );

		update_post_meta( 70, '_rytkoset_event_product_id', 900 );
		$GLOBALS['rytkoset_test_products'][900] = new WC_Product( array(), 900, 'publish', 'Osallistumismaksu' );

		foreach (
			array(
				801 => 'processing',
				802 => 'completed',
				803 => 'cancelled',
				804 => 'refunded',
				805 => 'failed',
			) as $order_id => $status
		) {
			$order                = new WC_Order();
			$order->id             = $order_id;
			$order->status         = $status;
			$order->billing_email  = 'tilaus' . $order_id . '@example.test';
			$order->billing_first_name = 'Tilaaja';
			$order->billing_last_name  = (string) $order_id;
			$order->items[]        = new Rytkoset_Test_Order_Item( $GLOBALS['rytkoset_test_products'][900] );
			$GLOBALS['rytkoset_test_orders'][ $order_id ] = $order;
		}

		$result = rytkoset_theme_get_event_feedback_recipients( 70 );

		$emails = array_keys( $result['recipients'] );
		sort( $emails );

		$this->assertSame(
			array(
				'odottava@example.test',
				'tilaus801@example.test',
				'tilaus802@example.test',
				'vahvistettu@example.test',
			),
			$emails
		);
		// 3 free rows + 5 paid rows fetched, active filter keeps 2 free + 2 paid = 4.
		$this->assertSame( 4, $result['participant_row_count'] );
		$this->assertSame( 0, $result['no_address_count'] );
	}

	public function test_recipients_dedupe_by_email(): void {
		$this->event( 71, '2020-01-01' );
		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		rytkoset_test_register_post( 711, 'event_registration', 'A - Sukujuhla' );
		update_post_meta( 711, $meta_keys['event_id'], 71 );
		update_post_meta( 711, $meta_keys['name'], 'Ensimmäinen' );
		update_post_meta( 711, $meta_keys['email'], 'sama@example.test' );
		update_post_meta( 711, $meta_keys['status'], 'confirmed' );

		rytkoset_test_register_post( 712, 'event_registration', 'B - Sukujuhla' );
		update_post_meta( 712, $meta_keys['event_id'], 71 );
		update_post_meta( 712, $meta_keys['name'], 'Toinen' );
		update_post_meta( 712, $meta_keys['email'], 'SAMA@example.test' );
		update_post_meta( 712, $meta_keys['status'], 'confirmed' );

		rytkoset_test_register_post( 713, 'event_registration', 'C - Sukujuhla' );
		update_post_meta( 713, $meta_keys['event_id'], 71 );
		update_post_meta( 713, $meta_keys['name'], 'Osoitteeton' );
		update_post_meta( 713, $meta_keys['status'], 'confirmed' );

		$result = rytkoset_theme_get_event_feedback_recipients( 71 );

		$this->assertCount( 1, $result['recipients'] );
		$this->assertSame( 3, $result['participant_row_count'] );
		$this->assertSame( 1, $result['no_address_count'] );
	}

	// --- {palautelinkki} placeholder --------------------------------------

	public function test_feedback_link_resolves_for_single_event(): void {
		$url = rytkoset_theme_get_event_feedback_public_url( 80 );

		$this->assertStringContainsString( '/palaute/80/', $url );

		$message = rytkoset_theme_personalize_event_message( 'Linkki: {palautelinkki}', 'Maija', 'Sukujuhla', $url );
		$this->assertSame( 'Linkki: ' . $url, $message );
	}

	public function test_feedback_link_is_empty_for_all_events_broadcast(): void {
		$this->assertSame( '', rytkoset_theme_get_event_feedback_public_url( 0 ) );

		$message = rytkoset_theme_personalize_event_message( 'Linkki: {palautelinkki}', 'Maija', 'Kaikki tapahtumat', '' );
		$this->assertSame( 'Linkki: ', $message );
	}

	// --- public submission handler -----------------------------------------

	private function submit_feedback( int $event_id, array $overrides = array() ): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		$_POST                  = array_merge(
			array(
				'rytkoset_event_feedback_submit'        => '1',
				'feedback_website'                       => '',
				'rytkoset_event_feedback_submit_nonce'  => rytkoset_theme_get_event_feedback_submit_nonce_action(),
				'feedback_rating'                        => '4',
				'feedback_well'                           => 'Kaikki sujui hyvin.',
				'feedback_improve'                         => '',
				'feedback_wishes'                          => '',
			),
			$overrides
		);

		rytkoset_theme_handle_event_feedback_submission( $event_id );
	}

	private function open_event( int $id ): void {
		$this->event( $id, '2020-01-01' );
		update_post_meta( $id, $this->feedback_meta()['mode'], 'manual' );
	}

	public function test_submission_succeeds_and_stores_no_identifying_meta(): void {
		$this->open_event( 90 );

		try {
			$this->submit_feedback( 90 );
			$this->fail( 'Expected a redirect after a successful submission.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute=kiitos', $redirect->location );
		}

		$response_id = 1000;
		$this->assertSame( 'event_feedback', get_post_type( $response_id ) );

		$meta_keys      = rytkoset_theme_get_event_feedback_response_meta_keys();
		$stored_meta    = $GLOBALS['rytkoset_test_post_meta'][ $response_id ];
		$identity_keys  = array( 'name', 'email', 'user', 'registration', 'order', 'ip' );

		$this->assertSame( 90, $stored_meta[ $meta_keys['event_id'] ] );
		$this->assertSame( 4, $stored_meta[ $meta_keys['rating'] ] );
		$this->assertSame( 'Kaikki sujui hyvin.', $stored_meta[ $meta_keys['well'] ] );

		foreach ( array_keys( $stored_meta ) as $meta_key ) {
			foreach ( $identity_keys as $identity_key ) {
				$this->assertStringNotContainsStringIgnoringCase( $identity_key, $meta_key );
			}
		}
	}

	public function test_submission_honeypot_blocks_silently(): void {
		$this->open_event( 91 );

		try {
			$this->submit_feedback( 91, array( 'feedback_website' => 'https://spam.example' ) );
			$this->fail( 'Expected a redirect.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringNotContainsString( 'palaute=kiitos', $redirect->location );
		}

		$this->assertArrayNotHasKey( 1000, $GLOBALS['rytkoset_test_posts'] );
	}

	public function test_submission_rejects_invalid_nonce(): void {
		$this->open_event( 92 );

		try {
			$this->submit_feedback( 92, array( 'rytkoset_event_feedback_submit_nonce' => 'wrong' ) );
			$this->fail( 'Expected a redirect.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute_virhe=nonce', $redirect->location );
		}

		$this->assertArrayNotHasKey( 1000, $GLOBALS['rytkoset_test_posts'] );
	}

	public function test_submission_rejected_when_survey_closed(): void {
		$this->event( 93, '2099-01-01' );
		update_post_meta( 93, $this->feedback_meta()['mode'], 'manual' );

		try {
			$this->submit_feedback( 93 );
			$this->fail( 'Expected a redirect.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute_virhe=suljettu', $redirect->location );
		}

		$this->assertArrayNotHasKey( 1000, $GLOBALS['rytkoset_test_posts'] );
	}

	public function test_submission_requires_valid_rating(): void {
		$this->open_event( 94 );

		try {
			$this->submit_feedback( 94, array( 'feedback_rating' => '0' ) );
			$this->fail( 'Expected a redirect.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute_virhe=arvio', $redirect->location );
		}

		$this->assertArrayNotHasKey( 1000, $GLOBALS['rytkoset_test_posts'] );

		try {
			$this->submit_feedback( 94, array( 'feedback_rating' => '6' ) );
			$this->fail( 'Expected a redirect.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute_virhe=arvio', $redirect->location );
		}
	}

	public function test_submission_caps_free_text_length(): void {
		$this->open_event( 95 );
		$long = str_repeat( 'a', 600 );

		try {
			$this->submit_feedback( 95, array( 'feedback_well' => $long ) );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute=kiitos', $redirect->location );
		}

		$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();
		$stored    = $GLOBALS['rytkoset_test_post_meta'][1000][ $meta_keys['well'] ];

		$this->assertSame( 500, mb_strlen( $stored ) );
	}

	public function test_submission_is_rate_limited_after_configured_attempts(): void {
		$this->open_event( 96 );

		add_filter(
			'rytkoset_theme_event_feedback_rate_limit',
			static function () {
				return 2;
			}
		);

		for ( $i = 0; $i < 2; $i++ ) {
			try {
				$this->submit_feedback( 96 );
			} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
				$this->assertStringContainsString( 'palaute=kiitos', $redirect->location );
			}
		}

		try {
			$this->submit_feedback( 96 );
			$this->fail( 'Expected the third submission to be rate limited.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute_virhe=raja', $redirect->location );
		}
	}

	// --- manual redaction ---------------------------------------------------

	public function test_update_response_text_edits_only_text_fields(): void {
		rytkoset_test_register_post( 1100, 'event_feedback', '' );
		$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();
		update_post_meta( 1100, $meta_keys['event_id'], 10 );
		update_post_meta( 1100, $meta_keys['rating'], 5 );
		update_post_meta( 1100, $meta_keys['well'], 'Alkuperäinen nimi Maija Meikäläinen mainittu vahingossa.' );

		$result = rytkoset_theme_update_event_feedback_response_text( 1100, 'Muokattu teksti.', 'Parannettava.', '' );

		$this->assertTrue( $result );
		$this->assertSame( 5, get_post_meta( 1100, $meta_keys['rating'], true ) );
		$this->assertSame( 'Muokattu teksti.', get_post_meta( 1100, $meta_keys['well'], true ) );
		$this->assertSame( 'Parannettava.', get_post_meta( 1100, $meta_keys['improve'], true ) );
		$this->assertSame( '', get_post_meta( 1100, $meta_keys['wishes'], true ) );
	}

	public function test_update_response_text_rejects_wrong_post_type(): void {
		rytkoset_test_register_post( 1101, 'rytkoset_event', 'Ei palaute' );

		$this->assertFalse( rytkoset_theme_update_event_feedback_response_text( 1101, 'x', 'y', 'z' ) );
	}

	// --- admin aggregate: average rating -----------------------------------

	public function test_average_rating_is_null_with_no_responses(): void {
		$this->assertNull( rytkoset_theme_get_event_feedback_average_rating( array() ) );
	}

	public function test_average_rating_rounds_to_one_decimal(): void {
		$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();

		rytkoset_test_register_post( 1200, 'event_feedback', '' );
		update_post_meta( 1200, $meta_keys['rating'], 5 );
		rytkoset_test_register_post( 1201, 'event_feedback', '' );
		update_post_meta( 1201, $meta_keys['rating'], 4 );
		rytkoset_test_register_post( 1202, 'event_feedback', '' );
		update_post_meta( 1202, $meta_keys['rating'], 4 );

		$responses = array(
			get_post( 1200 ),
			get_post( 1201 ),
			get_post( 1202 ),
		);

		$this->assertSame( 4.3, rytkoset_theme_get_event_feedback_average_rating( $responses ) );
	}

	// --- text sanitization ---------------------------------------------------

	public function test_sanitize_text_strips_tags_and_caps_length(): void {
		$this->assertSame( 'aleksi', rytkoset_theme_sanitize_event_feedback_text( '<b>aleksi</b>' ) );
		$this->assertSame( 500, mb_strlen( rytkoset_theme_sanitize_event_feedback_text( str_repeat( 'x', 1000 ) ) ) );
	}

	// --- organizer notification opt-in ----------------------------------------

	public function test_notify_organizers_defaults_to_false(): void {
		$this->event( 100, '2020-01-01' );

		$this->assertFalse( rytkoset_theme_event_feedback_notifies_organizers( 100 ) );
	}

	public function test_save_persists_notify_organizers_checkbox(): void {
		$this->event( 101, '2020-01-01' );

		$this->save_settings(
			101,
			array(
				'rytkoset_event_feedback_mode'                => 'manual',
				'rytkoset_event_feedback_notify_organizers'   => 'yes',
			)
		);
		$this->assertTrue( rytkoset_theme_event_feedback_notifies_organizers( 101 ) );

		// Unchecking (field simply absent from $_POST) turns it back off.
		$this->save_settings( 101, array( 'rytkoset_event_feedback_mode' => 'manual' ) );
		$this->assertFalse( rytkoset_theme_event_feedback_notifies_organizers( 101 ) );
	}

	public function test_organizer_notification_skipped_when_disabled(): void {
		$this->event( 110, '2020-01-01' );
		update_post_meta( 110, '_rytkoset_event_organizer_notification_recipients', 'jarjestaja@example.test' );

		$sent = rytkoset_theme_send_event_feedback_organizer_notification( 110, 5, 'Hyvä', '', '' );

		$this->assertFalse( $sent );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_organizer_notification_skipped_without_recipients(): void {
		$this->event( 111, '2020-01-01' );
		update_post_meta( 111, $this->feedback_meta()['notify_organizers'], 'yes' );

		$sent = rytkoset_theme_send_event_feedback_organizer_notification( 111, 5, '', '', '' );

		$this->assertFalse( $sent );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_organizer_notification_sent_with_rating_and_texts(): void {
		$this->event( 112, '2020-01-01', 'Sukujuhla' );
		update_post_meta( 112, $this->feedback_meta()['notify_organizers'], 'yes' );
		update_post_meta( 112, '_rytkoset_event_organizer_notification_recipients', "jarjestaja@example.test\ntoinen@example.test" );

		$sent = rytkoset_theme_send_event_feedback_organizer_notification( 112, 4, 'Hyvä tunnelma', '', 'Lisää kahvia' );

		$this->assertTrue( $sent );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );

		$mail = $GLOBALS['rytkoset_test_mails'][0];
		$this->assertSame( array( 'jarjestaja@example.test', 'toinen@example.test' ), $mail['to'] );
		$this->assertStringContainsString( 'Sukujuhla', $mail['subject'] );
		$this->assertStringContainsString( 'Kokonaisarvio: 4 / 5', $mail['message'] );
		$this->assertStringContainsString( 'Hyvä tunnelma', $mail['message'] );
		$this->assertStringContainsString( 'Lisää kahvia', $mail['message'] );
		// The optional "Mitä voisimme parantaa?" question was left empty and
		// must not appear as an empty section.
		$this->assertStringNotContainsString( 'Mitä voisimme parantaa?:', $mail['message'] );
	}

	public function test_submission_sends_organizer_notification_when_enabled(): void {
		$this->open_event( 113 );
		update_post_meta( 113, $this->feedback_meta()['notify_organizers'], 'yes' );
		update_post_meta( 113, '_rytkoset_event_organizer_notification_recipients', 'jarjestaja@example.test' );

		try {
			$this->submit_feedback( 113 );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute=kiitos', $redirect->location );
		}

		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_submission_sends_no_organizer_notification_when_disabled(): void {
		$this->open_event( 114 );
		update_post_meta( 114, '_rytkoset_event_organizer_notification_recipients', 'jarjestaja@example.test' );

		try {
			$this->submit_feedback( 114 );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'palaute=kiitos', $redirect->location );
		}

		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}
}
