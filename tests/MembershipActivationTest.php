<?php
/**
 * Tests for the manual membership activation tool (#525).
 */

declare(strict_types=1);

/**
 * @covers inc/user-membership-activation.php
 */
final class MembershipActivationTest extends Rytkoset_Theme_Test_Case {

	private function details( string $type = 'annual', string $expires = '2029-12-31', string $period = '2026-2029' ): array {
		return array(
			'type'    => $type,
			'period'  => $period,
			'expires' => $expires,
		);
	}

	private function activation_product(
		int $id = 101,
		string $type = 'annual_individual',
		string $expires = '2029-12-31',
		string $period = '2026-2029',
		string $status = 'publish',
		string $name = 'Jäsenmaksu 2026-2029'
	): WC_Product {
		return rytkoset_test_register_product(
			$id,
			$status,
			$name,
			array(
				'_rytkoset_membership_product'     => 'yes',
				'_rytkoset_membership_type'        => $type,
				'_rytkoset_membership_period'      => $period,
				'_rytkoset_membership_expiry_date' => $expires,
			)
		);
	}

	// -----------------------------------------------------------------
	// Email list parsing
	// -----------------------------------------------------------------

	public function test_parse_emails_normalizes_dedupes_and_flags_invalid(): void {
		$parsed = rytkoset_theme_membership_activation_parse_emails(
			"Matti@Example.com\nmatti@example.com\n\n ei-osoite \nliisa@example.com; matti@example.com"
		);

		$this->assertSame( array( 'matti@example.com', 'liisa@example.com' ), $parsed['emails'] );
		$this->assertSame( array( 'ei-osoite' ), $parsed['invalid'] );
	}

	public function test_parse_emails_empty_input(): void {
		$parsed = rytkoset_theme_membership_activation_parse_emails( "  \n\n" );

		$this->assertSame( array(), $parsed['emails'] );
		$this->assertSame( array(), $parsed['invalid'] );
	}

	// -----------------------------------------------------------------
	// Membership product selection
	// -----------------------------------------------------------------

	public function test_resolve_product_requires_published_membership_product(): void {
		$result = rytkoset_theme_membership_activation_resolve_product( 999 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_invalid_membership_product', $result->get_error_codes()[0] );
	}

	public function test_resolve_product_rejects_draft_product(): void {
		$this->activation_product( 101, 'annual_individual', '2029-12-31', '2026-2029', 'draft' );

		$result = rytkoset_theme_membership_activation_resolve_product( 101 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_invalid_membership_product', $result->get_error_codes()[0] );
	}

	public function test_resolve_product_requires_complete_time_bound_details(): void {
		$this->activation_product( 101, 'annual_individual', '', '' );

		$result = rytkoset_theme_membership_activation_resolve_product( 101 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_incomplete_membership_product', $result->get_error_codes()[0] );
	}

	public function test_resolve_product_returns_family_snapshot(): void {
		$this->activation_product( 101, 'annual_family' );

		$result = rytkoset_theme_membership_activation_resolve_product( 101 );

		$this->assertSame(
			array(
				'type'    => 'family',
				'period'  => '2026-2029',
				'expires' => '2029-12-31',
			),
			$result
		);
	}

	public function test_resolve_product_lifetime_ignores_period_and_expiry(): void {
		$this->activation_product( 101, 'lifetime' );

		$result = rytkoset_theme_membership_activation_resolve_product( 101 );

		$this->assertSame(
			array(
				'type'    => 'lifetime',
				'period'  => '',
				'expires' => '',
			),
			$result
		);
	}

	public function test_product_options_include_published_and_private_memberships_only(): void {
		$this->activation_product( 101, 'annual_individual', '2029-12-31', '2026-2029', 'publish', 'Ö-jäsenyys' );
		$this->activation_product( 102, 'lifetime', '', '', 'private', 'Ainaisjäsenyys' );
		$this->activation_product( 103, 'annual_family', '2029-12-31', '2026-2029', 'draft', 'Luonnos' );
		rytkoset_test_register_product( 104, 'publish', 'Tavallinen tuote' );

		$options = rytkoset_theme_get_membership_activation_products();

		$this->assertSame( array( 102, 101 ), array_keys( $options ) );
	}

	public function test_product_label_contains_period_and_expiry(): void {
		$product = $this->activation_product();
		$label   = rytkoset_theme_get_membership_activation_product_label( $product );

		$this->assertStringContainsString( 'Jäsenmaksu 2026-2029', $label );
		$this->assertStringContainsString( '2026-2029', $label );
		$this->assertStringContainsString( '31.12.2029', $label );
	}

	public function test_incomplete_product_label_names_missing_expiry(): void {
		$product = $this->activation_product( 101, 'annual_individual', '' );
		$label   = rytkoset_theme_get_membership_activation_product_label( $product );

		$this->assertStringContainsString( 'voimassaolopäivä puuttuu', $label );
	}

	// -----------------------------------------------------------------
	// Never-shorten decision
	// -----------------------------------------------------------------

	public function test_lifetime_update_always_applies(): void {
		$current = array(
			'type'    => 'annual',
			'period'  => '',
			'expires' => '2099-12-31',
		);

		$this->assertFalse(
			rytkoset_theme_membership_current_covers_manual_update( $current, true, $this->details( 'lifetime', '' ) )
		);
	}

	public function test_current_lifetime_blocks_time_bound_update(): void {
		$current = array(
			'type'    => 'lifetime',
			'period'  => '',
			'expires' => '',
		);

		$this->assertTrue(
			rytkoset_theme_membership_current_covers_manual_update( $current, true, $this->details() )
		);
	}

	public function test_active_longer_membership_blocks_shorter_update(): void {
		$current = array(
			'type'    => 'annual',
			'period'  => '',
			'expires' => '2030-06-30',
		);

		$this->assertTrue(
			rytkoset_theme_membership_current_covers_manual_update( $current, true, $this->details( 'annual', '2029-12-31' ) )
		);
	}

	public function test_expired_membership_never_blocks_update(): void {
		$current = array(
			'type'    => 'annual',
			'period'  => '',
			'expires' => '2030-06-30',
		);

		$this->assertFalse(
			rytkoset_theme_membership_current_covers_manual_update( $current, false, $this->details( 'annual', '2029-12-31' ) )
		);
	}

	// -----------------------------------------------------------------
	// Applying to an existing user
	// -----------------------------------------------------------------

	public function test_apply_manual_membership_updates_meta_and_sends_confirmation(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_test_register_user( 5, 'matti@example.com', 'Matti Meikäläinen', 'matti' );

		$result = rytkoset_theme_apply_manual_membership( 5, $this->details() );

		$this->assertSame( 'applied', $result );
		$this->assertSame( 'annual', get_user_meta( 5, 'rytkoset_membership_type', true ) );
		$this->assertSame( '2026-2029', get_user_meta( 5, 'rytkoset_membership_period', true ) );
		$this->assertSame( '2029-12-31', get_user_meta( 5, 'rytkoset_membership_expires', true ) );

		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( 'matti@example.com', $GLOBALS['rytkoset_test_mails'][0]['to'] );
		$this->assertStringContainsString( 'Jäsenyytesi', $GLOBALS['rytkoset_test_mails'][0]['subject'] );
	}

	public function test_apply_manual_membership_lifetime_clears_time_fields(): void {
		rytkoset_test_register_user( 5, 'matti@example.com' );
		update_user_meta( 5, 'rytkoset_membership_type', 'annual' );
		update_user_meta( 5, 'rytkoset_membership_period', '2023-2026' );
		update_user_meta( 5, 'rytkoset_membership_expires', '2020-01-01' );

		$result = rytkoset_theme_apply_manual_membership( 5, $this->details( 'lifetime', '', '' ) );

		$this->assertSame( 'applied', $result );
		$this->assertSame( 'lifetime', get_user_meta( 5, 'rytkoset_membership_type', true ) );
		$this->assertSame( '', get_user_meta( 5, 'rytkoset_membership_period', true ) );
		$this->assertSame( '', get_user_meta( 5, 'rytkoset_membership_expires', true ) );
	}

	public function test_apply_manual_membership_never_shortens_active_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_test_register_user( 5, 'matti@example.com' );
		update_user_meta( 5, 'rytkoset_membership_type', 'annual' );
		update_user_meta( 5, 'rytkoset_membership_expires', '2031-12-31' );

		$result = rytkoset_theme_apply_manual_membership( 5, $this->details() );

		$this->assertSame( 'skipped_covered', $result );
		$this->assertSame( '2031-12-31', get_user_meta( 5, 'rytkoset_membership_expires', true ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_apply_manual_membership_no_confirmation_when_already_active(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_test_register_user( 5, 'matti@example.com' );
		update_user_meta( 5, 'rytkoset_membership_type', 'annual' );
		update_user_meta( 5, 'rytkoset_membership_expires', '2027-12-31' );

		$result = rytkoset_theme_apply_manual_membership( 5, $this->details( 'annual', '2029-12-31' ) );

		$this->assertSame( 'applied', $result );
		$this->assertSame( '2029-12-31', get_user_meta( 5, 'rytkoset_membership_expires', true ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	// -----------------------------------------------------------------
	// Pending store and invite emails
	// -----------------------------------------------------------------

	public function test_unknown_email_is_stored_pending_and_invited(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';

		$result = rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), false, 3 );

		$this->assertSame( 'invited', $result );

		$pending = rytkoset_theme_get_pending_manual_memberships();
		$this->assertArrayHasKey( 'uusi@example.com', $pending );
		$this->assertSame( 'annual', $pending['uusi@example.com']['type'] );
		$this->assertSame( '2029-12-31', $pending['uusi@example.com']['expires'] );
		$this->assertSame( 3, $pending['uusi@example.com']['added_by'] );
		$this->assertNotSame( '', $pending['uusi@example.com']['invite_sent_at'] );

		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$mail = $GLOBALS['rytkoset_test_mails'][0];
		$this->assertSame( 'uusi@example.com', $mail['to'] );
		$this->assertStringContainsString( 'jäsenrekisteri', $mail['message'] );
		$this->assertStringContainsString( wp_registration_url(), $mail['message'] );
	}

	public function test_reprocessing_pending_email_does_not_resend_without_optin(): void {
		rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), false, 3 );
		$GLOBALS['rytkoset_test_mails'] = array();

		$result = rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details( 'annual', '2030-12-31' ), false, 3 );

		$this->assertSame( 'pending_updated', $result );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );

		$pending = rytkoset_theme_get_pending_manual_memberships();
		$this->assertSame( '2030-12-31', $pending['uusi@example.com']['expires'] );
	}

	public function test_reprocessing_pending_email_resends_with_explicit_optin(): void {
		rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), false, 3 );
		$GLOBALS['rytkoset_test_mails'] = array();

		$result = rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), true, 3 );

		$this->assertSame( 'invited', $result );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_remove_pending_manual_membership(): void {
		rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), false, 3 );

		$this->assertTrue( rytkoset_theme_remove_pending_manual_membership( 'Uusi@Example.com' ) );
		$this->assertSame( array(), rytkoset_theme_get_pending_manual_memberships() );
		$this->assertFalse( rytkoset_theme_remove_pending_manual_membership( 'uusi@example.com' ) );
	}

	// -----------------------------------------------------------------
	// Full submission processing
	// -----------------------------------------------------------------

	public function test_process_submission_rejects_invalid_product_before_sending(): void {
		$response = rytkoset_theme_membership_activation_process_submission(
			"uusi@example.com",
			999,
			false,
			3
		);

		$this->assertNotSame( '', $response['error'] );
		$this->assertSame( array(), $response['results'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( array(), rytkoset_theme_get_pending_manual_memberships() );
	}

	public function test_process_submission_handles_mixed_existing_and_unknown(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_test_register_user( 5, 'matti@example.com', 'Matti' );
		$this->activation_product();

		$response = rytkoset_theme_membership_activation_process_submission(
			"matti@example.com\nuusi@example.com\nroskaa",
			101,
			false,
			3
		);

		$this->assertSame( '', $response['error'] );
		$this->assertSame( array( 'roskaa' ), $response['invalid'] );
		$this->assertSame(
			array(
				array(
					'email'  => 'matti@example.com',
					'result' => 'applied',
				),
				array(
					'email'  => 'uusi@example.com',
					'result' => 'invited',
				),
			),
			$response['results']
		);

		// Confirmation to the existing member + invite to the unknown address.
		$this->assertCount( 2, $GLOBALS['rytkoset_test_mails'] );

		$log = rytkoset_theme_get_membership_activation_log();
		$this->assertCount( 2, $log );
		$this->assertSame( 3, $log[0]['admin_id'] );
	}

	public function test_process_email_for_existing_user_clears_stale_pending_entry(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_theme_membership_activation_process_email( 'matti@example.com', $this->details(), false, 3 );
		rytkoset_test_register_user( 5, 'matti@example.com' );
		$GLOBALS['rytkoset_test_mails'] = array();

		$result = rytkoset_theme_membership_activation_process_email( 'matti@example.com', $this->details(), false, 3 );

		$this->assertSame( 'applied', $result );
		$this->assertSame( array(), rytkoset_theme_get_pending_manual_memberships() );
	}

	// -----------------------------------------------------------------
	// Registration linking
	// -----------------------------------------------------------------

	public function test_pending_membership_applies_on_user_register(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), false, 3 );
		$GLOBALS['rytkoset_test_mails'] = array();

		rytkoset_test_register_user( 9, 'Uusi@Example.com', 'Uusi Jäsen' );
		rytkoset_theme_apply_pending_manual_membership_on_user_register( 9 );

		$this->assertSame( 'annual', get_user_meta( 9, 'rytkoset_membership_type', true ) );
		$this->assertSame( '2029-12-31', get_user_meta( 9, 'rytkoset_membership_expires', true ) );
		$this->assertSame( array(), rytkoset_theme_get_pending_manual_memberships() );

		// Confirmation email on activation.
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );

		$log = rytkoset_theme_get_membership_activation_log();
		$this->assertSame( 'applied_on_register', $log[0]['result'] );
		$this->assertSame( 0, $log[0]['admin_id'] );
	}

	public function test_user_register_without_pending_entry_is_a_noop(): void {
		rytkoset_test_register_user( 9, 'uusi@example.com' );

		rytkoset_theme_apply_pending_manual_membership_on_user_register( 9 );

		$this->assertSame( '', get_user_meta( 9, 'rytkoset_membership_type', true ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( array(), rytkoset_theme_get_membership_activation_log() );
	}

	public function test_pending_membership_on_register_never_shortens_existing_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_theme_membership_activation_process_email( 'uusi@example.com', $this->details(), false, 3 );
		$GLOBALS['rytkoset_test_mails'] = array();

		rytkoset_test_register_user( 9, 'uusi@example.com' );
		update_user_meta( 9, 'rytkoset_membership_type', 'lifetime' );

		rytkoset_theme_apply_pending_manual_membership_on_user_register( 9 );

		$this->assertSame( 'lifetime', get_user_meta( 9, 'rytkoset_membership_type', true ) );
		$this->assertSame( array(), rytkoset_theme_get_pending_manual_memberships() );

		$log = rytkoset_theme_get_membership_activation_log();
		$this->assertSame( 'skipped_covered', $log[0]['result'] );
	}

	// -----------------------------------------------------------------
	// Log behavior
	// -----------------------------------------------------------------

	public function test_log_is_capped_to_max_entries(): void {
		add_filter(
			'rytkoset_theme_membership_activation_log_max_entries',
			static function (): int {
				return 3;
			}
		);

		for ( $i = 1; $i <= 5; $i++ ) {
			rytkoset_theme_membership_activation_append_log( "jasen{$i}@example.com", 'invited', 3 );
		}

		$log = rytkoset_theme_get_membership_activation_log();
		$this->assertCount( 3, $log );
		$this->assertSame( 'jasen5@example.com', $log[0]['email'] );
	}

	public function test_result_labels_exist_for_all_codes(): void {
		foreach ( array( 'applied', 'applied_on_register', 'skipped_covered', 'invited', 'pending_updated', 'invite_failed', 'pending_removed' ) as $code ) {
			$this->assertNotSame( $code, rytkoset_theme_get_membership_activation_result_label( $code ) );
		}
	}

	public function test_pending_map_normalization_drops_corrupt_entries(): void {
		update_option(
			'rytkoset_pending_manual_memberships',
			array(
				'ok@example.com'   => array(
					'type'    => 'annual',
					'expires' => '2029-12-31',
				),
				'huono@example.com' => array( 'type' => 'ei-tyyppi' ),
				'ei-osoite'         => array( 'type' => 'annual' ),
			)
		);

		$pending = rytkoset_theme_get_pending_manual_memberships();

		$this->assertSame( array( 'ok@example.com' ), array_keys( $pending ) );
		$this->assertSame( 'annual', $pending['ok@example.com']['type'] );
	}
}
