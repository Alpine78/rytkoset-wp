<?php
/**
 * Tests for inc/event-registrations.php — title building, error mapping, rate limits and the
 * free-registration-form availability gate (deadline cutoff).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventRegistrationFormTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Registers a free event with no linked product and an optional registration deadline.
	 *
	 * @param int    $id       Event post ID.
	 * @param string $fee_type Fee type meta value (free|paid).
	 * @param string $deadline Registration deadline (YYYY-MM-DD) or '' for none.
	 * @return void
	 */
	private function event( int $id, string $fee_type = 'free', string $deadline = '' ): void {
		rytkoset_test_register_post( $id, 'rytkoset_event', 'Sukujuhla', 0 );

		$keys = rytkoset_theme_get_event_details_meta_keys();
		update_post_meta( $id, $keys['fee_type'], $fee_type );

		if ( '' !== $deadline ) {
			update_post_meta( $id, rytkoset_theme_get_event_registration_deadline_meta_key(), $deadline );
		}
	}

	/**
	 * Registers a free event registration with the given meta values.
	 *
	 * @param int                  $id        Registration post ID.
	 * @param int                  $event_id  Event post ID.
	 * @param array<string,string> $overrides Meta alias => value pairs.
	 * @return void
	 */
	private function registration( int $id, int $event_id, array $overrides = array() ): void {
		rytkoset_test_register_post( $id, 'event_registration', 'Maija Meikäläinen - Sukujuhla', 0 );

		$keys   = rytkoset_theme_get_event_registration_meta_keys();
		$values = array_merge(
			array(
				'event_id' => (string) $event_id,
				'name'     => 'Maija Meikäläinen',
				'email'    => 'maija@example.test',
				'status'   => 'pending',
			),
			$overrides
		);

		foreach ( $values as $alias => $value ) {
			update_post_meta( $id, $keys[ $alias ], $value );
		}
	}

	/**
	 * Sets the organizer notification recipients for an event.
	 *
	 * @param int    $event_id   Event post ID.
	 * @param string $recipients Raw recipient list value.
	 * @return void
	 */
	private function organizer_recipients( int $event_id, string $recipients ): void {
		update_post_meta(
			$event_id,
			rytkoset_theme_get_event_organizer_notification_recipients_meta_key(),
			$recipients
		);
	}

	// --- build_event_registration_title -------------------------------------

	public function test_title_combines_name_and_event(): void {
		$this->event( 10 );
		$this->assertSame( 'Matti Meikäläinen - Sukujuhla', rytkoset_theme_build_event_registration_title( 'Matti Meikäläinen', 10 ) );
	}

	public function test_title_uses_placeholder_for_empty_name(): void {
		$this->event( 10 );
		$this->assertSame( 'Nimetön osallistuja - Sukujuhla', rytkoset_theme_build_event_registration_title( '', 10 ) );
	}

	public function test_title_is_just_name_for_invalid_event(): void {
		$this->assertSame( 'Matti', rytkoset_theme_build_event_registration_title( 'Matti', 0 ) );
	}

	// --- get_event_registration_error_field ---------------------------------

	public function test_error_codes_map_to_form_fields(): void {
		$this->assertSame( 'name', rytkoset_theme_get_event_registration_error_field( 'missing_name' ) );
		$this->assertSame( 'email', rytkoset_theme_get_event_registration_error_field( 'invalid_email' ) );
		$this->assertSame( 'email', rytkoset_theme_get_event_registration_error_field( 'already_registered' ) );
		$this->assertSame( 'choice', rytkoset_theme_get_event_registration_error_field( 'invalid_choice' ) );
		$this->assertSame( 'gdpr', rytkoset_theme_get_event_registration_error_field( 'missing_consent' ) );
		$this->assertSame( '', rytkoset_theme_get_event_registration_error_field( 'unknown_code' ) );
	}

	// --- rate limit defaults ------------------------------------------------

	public function test_rate_limit_defaults(): void {
		$this->assertSame( 5, rytkoset_theme_get_event_registration_rate_limit() );
		$this->assertSame( 600, rytkoset_theme_get_event_registration_rate_limit_window() );
	}

	// --- registration privacy notice ---------------------------------------

	public function test_privacy_notice_lists_diet_when_field_is_enabled(): void {
		$this->event( 10 );

		$notice = rytkoset_theme_get_event_registration_privacy_notice( 10 );

		$this->assertStringContainsString( 'ruokarajoitteet', $notice );
	}

	public function test_privacy_notice_omits_diet_when_field_is_disabled(): void {
		$this->event( 10 );
		update_post_meta( 10, rytkoset_theme_get_event_collect_diet_meta_key(), 'no' );

		$notice = rytkoset_theme_get_event_registration_privacy_notice( 10 );

		$this->assertStringNotContainsString( 'ruokarajoitteet', $notice );
		$this->assertStringContainsString( 'nimi, sähköpostiosoite ja lisätiedot', $notice );
	}

	// --- event_can_show_free_registration_form ------------------------------

	public function test_form_shown_for_free_event_without_deadline(): void {
		$this->event( 10, 'free' );
		$this->assertTrue( rytkoset_theme_event_can_show_free_registration_form( 10 ) );
	}

	public function test_form_hidden_for_paid_event(): void {
		$this->event( 10, 'paid' );
		$this->assertFalse( rytkoset_theme_event_can_show_free_registration_form( 10 ) );
	}

	public function test_form_hidden_after_deadline_cutoff(): void {
		$this->event( 10, 'free', '2026-06-20' );
		$GLOBALS['rytkoset_test_now'] = '2026-06-22 09:00:00';

		$this->assertFalse( rytkoset_theme_event_can_show_free_registration_form( 10 ) );
	}

	public function test_form_shown_on_deadline_day_itself(): void {
		$this->event( 10, 'free', '2026-06-20' );
		$GLOBALS['rytkoset_test_now'] = '2026-06-20 23:00:00';

		$this->assertTrue( rytkoset_theme_event_can_show_free_registration_form( 10 ) );
	}

	public function test_form_hidden_for_non_event_post(): void {
		rytkoset_test_register_post( 10, 'page', 'Tavallinen sivu', 0 );
		$this->assertFalse( rytkoset_theme_event_can_show_free_registration_form( 10 ) );
	}

	public function test_submission_saves_choice_and_quantity_then_redirects_successfully(): void {
		$this->event( 10 );
		update_post_meta( 10, rytkoset_theme_get_event_choice_enabled_meta_key(), 'yes' );
		update_post_meta( 10, rytkoset_theme_get_event_choice_options_meta_key(), "Kuopio\nVarkaus" );
		update_post_meta( 10, rytkoset_theme_get_event_choice_field_label_meta_key(), 'Lähtöpaikka' );
		update_post_meta( 10, rytkoset_theme_get_event_collect_quantity_meta_key(), 'yes' );
		update_post_meta( 10, rytkoset_theme_get_event_quantity_field_label_meta_key(), 'Matkustajia' );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		$_POST                  = array(
			'event_id'                                  => '10',
			'website'                                   => '',
			'rytkoset_event_registration_submit_nonce' => 'rytkoset_submit_event_registration',
			'registration_name'                         => ' Maija Meikäläinen ',
			'registration_email'                        => 'maija@example.test',
			'registration_diet'                         => "Laktoositon\nGluteeniton",
			'registration_notes'                        => 'Tarvitsee apua bussiin noustessa.',
			'registration_gdpr_consent'                 => '1',
			'registration_choice'                       => 'kuopio',
			'registration_quantity'                     => '12',
		);

		try {
			rytkoset_theme_handle_event_registration_submission();
			$this->fail( 'Expected the submission handler to redirect after saving.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'registration_status=success', $redirect->location );
		}

		$registration_id = 1000;
		$meta_keys       = rytkoset_theme_get_event_registration_meta_keys();

		$this->assertSame( 'event_registration', get_post_type( $registration_id ) );
		$this->assertSame( 'Maija Meikäläinen - Sukujuhla', get_the_title( $registration_id ) );
		$this->assertSame( 10, get_post_meta( $registration_id, $meta_keys['event_id'], true ) );
		$this->assertSame( 'Maija Meikäläinen', get_post_meta( $registration_id, $meta_keys['name'], true ) );
		$this->assertSame( 'maija@example.test', get_post_meta( $registration_id, $meta_keys['email'], true ) );
		$this->assertSame( 'Kuopio', get_post_meta( $registration_id, $meta_keys['choice'], true ) );
		$this->assertSame( 10, get_post_meta( $registration_id, $meta_keys['quantity'], true ) );
		$this->assertSame( 'confirmed', get_post_meta( $registration_id, $meta_keys['status'], true ) );
		// Event 10 has no organizer notification recipients, so only the
		// participant receipt is sent. See the organizer notification tests
		// below for the two-mail case.
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Lähtöpaikka: Kuopio', $GLOBALS['rytkoset_test_mails'][0]['message'] );
		$this->assertStringContainsString( 'Matkustajia: 10', $GLOBALS['rytkoset_test_mails'][0]['message'] );
	}

	// --- quantity normalization ---------------------------------------------

	public function test_registration_quantity_normalizes_to_positive_default_range(): void {
		$this->assertSame( 1, rytkoset_theme_normalize_event_registration_quantity( 0 ) );
		$this->assertSame( 3, rytkoset_theme_normalize_event_registration_quantity( '3' ) );
		$this->assertSame( 10, rytkoset_theme_normalize_event_registration_quantity( 99 ) );
	}

	public function test_registration_quantity_respects_filtered_maximum(): void {
		$filter = static fn() => 2;
		add_filter( 'rytkoset_theme_event_registration_max_quantity', $filter );

		$this->assertSame( 2, rytkoset_theme_normalize_event_registration_quantity( 5 ) );

		remove_filter( 'rytkoset_theme_event_registration_max_quantity', $filter );
	}

	// --- receipt email -------------------------------------------------------

	public function test_receipt_email_includes_choice_and_quantity(): void {
		$this->event( 10 );
		update_post_meta( 10, rytkoset_theme_get_event_choice_field_label_meta_key(), 'Lähtöpaikka' );
		update_post_meta( 10, rytkoset_theme_get_event_quantity_field_label_meta_key(), 'Matkustajia' );

		$this->assertTrue(
			rytkoset_theme_send_event_registration_receipt_email(
				10,
				'Maija Meikäläinen',
				'maija@example.test',
				'Kuopio',
				2
			)
		);

		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Lähtöpaikka: Kuopio', $GLOBALS['rytkoset_test_mails'][0]['message'] );
		$this->assertStringContainsString( 'Matkustajia: 2', $GLOBALS['rytkoset_test_mails'][0]['message'] );
	}

	// --- organizer notification ----------------------------------------------

	public function test_organizer_notification_is_skipped_without_configured_recipients(): void {
		$this->event( 10 );
		$this->registration( 500, 10 );

		$this->assertFalse( rytkoset_theme_send_event_registration_organizer_notification( 500 ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_organizer_notification_includes_event_and_participant_details(): void {
		$this->event( 10 );
		$this->organizer_recipients( 10, "jarjestaja@example.test\ntoinen@example.test" );

		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2026-08-29' );
		update_post_meta( 10, rytkoset_theme_get_event_details_meta_keys()['location'], 'Tampere' );

		$this->registration( 500, 10 );

		$this->assertTrue( rytkoset_theme_send_event_registration_organizer_notification( 500 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );

		$mail = $GLOBALS['rytkoset_test_mails'][0];

		$this->assertSame( array( 'jarjestaja@example.test', 'toinen@example.test' ), $mail['to'] );
		$this->assertStringContainsString( 'Uusi ilmoittautuminen: Sukujuhla', $mail['subject'] );
		$this->assertStringContainsString( 'Tapahtuma: Sukujuhla', $mail['message'] );
		$this->assertStringContainsString( 'Paikka: Tampere', $mail['message'] );
		$this->assertStringContainsString( 'Nimi: Maija Meikäläinen', $mail['message'] );
		$this->assertStringContainsString( 'Sähköposti: maija@example.test', $mail['message'] );
		$this->assertContains( 'Reply-To: maija@example.test', $mail['headers'] );
	}

	public function test_organizer_notification_omits_participant_free_text_fields(): void {
		$this->event( 10 );
		$this->organizer_recipients( 10, 'jarjestaja@example.test' );
		update_post_meta( 10, rytkoset_theme_get_event_choice_field_label_meta_key(), 'Lähtöpaikka' );

		$this->registration(
			500,
			10,
			array(
				'diet'     => 'Laktoositon',
				'notes'    => 'Tarvitsee apua bussiin noustessa.',
				'choice'   => 'Kuopio',
				'quantity' => '2',
			)
		);

		$this->assertTrue( rytkoset_theme_send_event_registration_organizer_notification( 500 ) );

		$message = $GLOBALS['rytkoset_test_mails'][0]['message'];

		$this->assertStringNotContainsString( 'Laktoositon', $message );
		$this->assertStringNotContainsString( 'Tarvitsee apua bussiin noustessa.', $message );
		$this->assertStringNotContainsString( 'Kuopio', $message );
	}

	public function test_organizer_notification_includes_admin_links(): void {
		$this->event( 10 );
		$this->organizer_recipients( 10, 'jarjestaja@example.test' );
		$this->registration( 500, 10 );

		$this->assertTrue( rytkoset_theme_send_event_registration_organizer_notification( 500 ) );

		$message = $GLOBALS['rytkoset_test_mails'][0]['message'];

		$this->assertStringContainsString( 'post.php?post=500&action=edit', $message );
		$this->assertStringContainsString( 'page=rytkoset-event-participants', $message );
		$this->assertStringContainsString( 'event_id=10', $message );
	}

	public function test_organizer_notification_is_skipped_for_invalid_registration(): void {
		$this->assertFalse( rytkoset_theme_send_event_registration_organizer_notification( 0 ) );

		rytkoset_test_register_post( 20, 'page', 'Tavallinen sivu', 0 );
		$this->organizer_recipients( 20, 'jarjestaja@example.test' );
		$this->registration( 500, 20 );

		$this->assertFalse( rytkoset_theme_send_event_registration_organizer_notification( 500 ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_submission_sends_receipt_and_organizer_notification(): void {
		$this->event( 10 );
		$this->organizer_recipients( 10, 'jarjestaja@example.test' );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		$_POST                  = array(
			'event_id'                                 => '10',
			'website'                                  => '',
			'rytkoset_event_registration_submit_nonce' => 'rytkoset_submit_event_registration',
			'registration_name'                        => 'Maija Meikäläinen',
			'registration_email'                       => 'maija@example.test',
			'registration_gdpr_consent'                => '1',
		);

		try {
			rytkoset_theme_handle_event_registration_submission();
			$this->fail( 'Expected the submission handler to redirect after saving.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'registration_status=success', $redirect->location );
		}

		$this->assertCount( 2, $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( 'maija@example.test', $GLOBALS['rytkoset_test_mails'][0]['to'] );
		$this->assertSame( array( 'jarjestaja@example.test' ), $GLOBALS['rytkoset_test_mails'][1]['to'] );
		$this->assertStringContainsString( 'Uusi ilmoittautuminen', $GLOBALS['rytkoset_test_mails'][1]['subject'] );
	}

	// --- registration source and informing state --------------------------

	public function test_normalize_source_defaults_to_web_form_for_unknown_values(): void {
		$this->assertSame( 'web_form', rytkoset_theme_normalize_event_registration_source( '' ) );
		$this->assertSame( 'web_form', rytkoset_theme_normalize_event_registration_source( 'legacy' ) );
		$this->assertSame( 'web_form', rytkoset_theme_normalize_event_registration_source( 'web_form' ) );
		$this->assertSame( 'manual', rytkoset_theme_normalize_event_registration_source( 'manual' ) );
	}

	public function test_normalize_informed_status_defaults_to_not_informed(): void {
		$this->assertSame( 'not_informed', rytkoset_theme_normalize_event_registration_informed_status( '' ) );
		$this->assertSame( 'not_informed', rytkoset_theme_normalize_event_registration_informed_status( 'jotain' ) );
		$this->assertSame( 'informed', rytkoset_theme_normalize_event_registration_informed_status( 'informed' ) );
	}

	public function test_public_submission_stamps_web_form_source(): void {
		$this->event( 10 );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.30';
		$_POST                  = array(
			'event_id'                                 => '10',
			'website'                                  => '',
			'rytkoset_event_registration_submit_nonce' => 'rytkoset_submit_event_registration',
			'registration_name'                        => 'Maija Meikäläinen',
			'registration_email'                       => 'maija@example.test',
			'registration_gdpr_consent'                => '1',
		);

		try {
			rytkoset_theme_handle_event_registration_submission();
			$this->fail( 'Expected the submission handler to redirect after saving.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertStringContainsString( 'registration_status=success', $redirect->location );
		}

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		$this->assertSame( 'web_form', get_post_meta( 1000, $meta_keys['source'], true ) );
	}

	// --- duplicate guard --------------------------------------------------

	public function test_active_registration_check_can_exclude_the_edited_row(): void {
		$this->event( 10 );
		$this->registration( 201, 10, array( 'status' => 'confirmed' ) );

		$this->assertTrue(
			rytkoset_theme_event_has_active_registration_for_email( 10, 'maija@example.test' )
		);
		$this->assertFalse(
			rytkoset_theme_event_has_active_registration_for_email( 10, 'maija@example.test', 201 )
		);
	}

	public function test_cancelled_registration_does_not_block_a_new_one(): void {
		$this->event( 10 );
		$this->registration( 202, 10, array( 'status' => 'cancelled' ) );

		$this->assertFalse(
			rytkoset_theme_event_has_active_registration_for_email( 10, 'maija@example.test' )
		);
	}

	// --- cancel / restore status toggle ---------------------------------

	public function test_set_status_flips_between_cancelled_and_confirmed(): void {
		$this->event( 10 );
		$this->registration( 203, 10, array( 'status' => 'confirmed' ) );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		$this->assertTrue( rytkoset_theme_set_event_registration_status( 203, 'cancelled' ) );
		$this->assertSame( 'cancelled', get_post_meta( 203, $meta_keys['status'], true ) );

		$this->assertTrue( rytkoset_theme_set_event_registration_status( 203, 'confirmed' ) );
		$this->assertSame( 'confirmed', get_post_meta( 203, $meta_keys['status'], true ) );
	}

	public function test_set_status_rejects_unknown_status_and_non_registration(): void {
		$this->event( 10 );
		$this->registration( 204, 10 );

		$this->assertFalse( rytkoset_theme_set_event_registration_status( 204, 'deleted' ) );
		$this->assertFalse( rytkoset_theme_set_event_registration_status( 10, 'cancelled' ) );
	}
}
