<?php
/**
 * Tests for inc/user-membership.php — the membership source of truth (#417, #390).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembershipTest extends Rytkoset_Theme_Test_Case {

	// --- Type normalization / labels -----------------------------------------

	public function test_normalize_accepts_allowlisted_types(): void {
		$this->assertSame( 'annual', rytkoset_theme_normalize_user_membership_type( 'annual' ) );
		$this->assertSame( 'family', rytkoset_theme_normalize_user_membership_type( 'family' ) );
		$this->assertSame( 'lifetime', rytkoset_theme_normalize_user_membership_type( 'lifetime' ) );
	}

	public function test_normalize_rejects_unknown_and_empty(): void {
		$this->assertSame( '', rytkoset_theme_normalize_user_membership_type( '' ) );
		$this->assertSame( '', rytkoset_theme_normalize_user_membership_type( 'gold' ) );
		$this->assertSame( '', rytkoset_theme_normalize_user_membership_type( 'annual_family' ) );
	}

	public function test_normalize_sanitizes_case_and_whitespace(): void {
		$this->assertSame( 'annual', rytkoset_theme_normalize_user_membership_type( 'ANNUAL' ) );
		$this->assertSame( 'lifetime', rytkoset_theme_normalize_user_membership_type( ' lifetime ' ) );
	}

	public function test_type_is_time_bound(): void {
		$this->assertTrue( rytkoset_theme_user_membership_type_is_time_bound( 'annual' ) );
		$this->assertTrue( rytkoset_theme_user_membership_type_is_time_bound( 'family' ) );
		$this->assertFalse( rytkoset_theme_user_membership_type_is_time_bound( 'lifetime' ) );
		$this->assertFalse( rytkoset_theme_user_membership_type_is_time_bound( '' ) );
	}

	public function test_type_label(): void {
		$this->assertSame( 'Vuosijäsen', rytkoset_theme_get_user_membership_type_label( 'annual' ) );
		$this->assertSame( 'Perhejäsen', rytkoset_theme_get_user_membership_type_label( 'family' ) );
		$this->assertSame( 'Ainaisjäsen', rytkoset_theme_get_user_membership_type_label( 'lifetime' ) );
		$this->assertSame( 'Ei jäsen', rytkoset_theme_get_user_membership_type_label( '' ) );
		$this->assertSame( 'Ei jäsen', rytkoset_theme_get_user_membership_type_label( 'unknown' ) );
	}

	// --- Period sanitization -------------------------------------------------

	public function test_sanitize_period_accepts_valid_range(): void {
		$this->assertSame( '2026-2029', rytkoset_theme_sanitize_user_membership_period( '2026-2029' ) );
	}

	public function test_sanitize_period_rejects_bad_formats(): void {
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_period( '2026' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_period( '26-29' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_period( '2026-2029 extra' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_period( 'abcd-efgh' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_period( '' ) );
	}

	// --- Expiry sanitization (FI dd.mm.yyyy <-> ISO) -------------------------

	public function test_sanitize_expires_accepts_finnish_format(): void {
		$this->assertSame( '2029-12-31', rytkoset_theme_sanitize_user_membership_expires( '31.12.2029' ) );
		$this->assertSame( '2030-01-01', rytkoset_theme_sanitize_user_membership_expires( '1.1.2030' ) );
	}

	public function test_sanitize_expires_accepts_iso_for_round_trip(): void {
		$this->assertSame( '2029-12-31', rytkoset_theme_sanitize_user_membership_expires( '2029-12-31' ) );
		$this->assertSame( '2030-01-05', rytkoset_theme_sanitize_user_membership_expires( '2030-1-5' ) );
	}

	public function test_sanitize_expires_rejects_invalid_dates(): void {
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_expires( '' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_expires( '31.02.2029' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_expires( '32.12.2029' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_expires( '2029-13-01' ) );
		$this->assertSame( '', rytkoset_theme_sanitize_user_membership_expires( 'garbage' ) );
	}

	public function test_expires_display_formats_iso_to_finnish(): void {
		$this->assertSame( '31.12.2029', rytkoset_theme_get_user_membership_expires_display( '2029-12-31' ) );
		$this->assertSame( '05.01.2030', rytkoset_theme_get_user_membership_expires_display( '2030-01-05' ) );
	}

	public function test_expires_display_rejects_invalid(): void {
		$this->assertSame( '', rytkoset_theme_get_user_membership_expires_display( '' ) );
		$this->assertSame( '', rytkoset_theme_get_user_membership_expires_display( '2029-13-01' ) );
		$this->assertSame( '', rytkoset_theme_get_user_membership_expires_display( '31.12.2029' ) );
	}

	// --- get_user_membership -------------------------------------------------

	public function test_get_user_membership_empty_when_no_meta(): void {
		$membership = rytkoset_theme_get_user_membership( 1 );
		$this->assertSame( array(
			'type'    => '',
			'period'  => '',
			'expires' => '',
		), $membership );
	}

	public function test_get_user_membership_returns_stored_values(): void {
		update_user_meta( 7, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( 7, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( 7, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );

		$this->assertSame( array(
			'type'    => 'annual',
			'period'  => '2026-2029',
			'expires' => '2029-12-31',
		), rytkoset_theme_get_user_membership( 7 ) );
	}

	public function test_get_user_membership_empty_for_unknown_stored_type(): void {
		update_user_meta( 7, rytkoset_theme_get_user_membership_type_meta_key(), 'gold' );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 7 )['type'] );
	}

	public function test_get_user_membership_empty_without_user(): void {
		$this->assertSame( '', rytkoset_theme_get_user_membership( 0 )['type'] );
	}

	// --- user_is_active_member (the gate) ------------------------------------

	public function test_inactive_when_no_membership(): void {
		$this->assertFalse( rytkoset_theme_user_is_active_member( 1 ) );
	}

	public function test_lifetime_is_always_active(): void {
		update_user_meta( 1, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 1 ) );
	}

	public function test_time_bound_active_with_future_expiry(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		$this->seed_time_bound( 1, 'annual', '2026-2029', '2029-12-31' );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 1 ) );
	}

	public function test_time_bound_active_on_expiry_day(): void {
		$GLOBALS['rytkoset_test_now'] = '2029-12-31';
		$this->seed_time_bound( 1, 'family', '2026-2029', '2029-12-31' );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 1 ) );
	}

	public function test_time_bound_inactive_after_expiry(): void {
		$GLOBALS['rytkoset_test_now'] = '2030-01-01';
		$this->seed_time_bound( 1, 'annual', '2026-2029', '2029-12-31' );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 1 ) );
	}

	public function test_time_bound_inactive_without_expiry(): void {
		$this->seed_time_bound( 1, 'annual', '2026-2029', '' );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 1 ) );
	}

	public function test_inactive_without_user(): void {
		$this->assertFalse( rytkoset_theme_user_is_active_member( 0 ) );
	}

	// --- Family membership structure (#524) ---------------------------------

	public function test_family_member_normalization(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-09 12:00:00';
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		$member = rytkoset_theme_normalize_family_member(
			array(
				'name'           => '  Lapsi Rytkönen  ',
				'email'          => ' LAPSI@EXAMPLE.TEST ',
				'linked_user_id' => 20,
				'status'         => '',
				'source_order_id' => '123',
			)
		);

		$this->assertSame( 'Lapsi Rytkönen', $member['name'] );
		$this->assertSame( 'lapsi@example.test', $member['email'] );
		$this->assertSame( 20, $member['linked_user_id'] );
		$this->assertSame( 'active', $member['status'] );
		$this->assertSame( 123, $member['source_order_id'] );
		$this->assertSame( '2026-07-09 12:00:00', $member['updated_at'] );
	}

	public function test_family_member_unknown_status_normalizes_to_pending_account(): void {
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		$member = rytkoset_theme_normalize_family_member(
			array(
				'name'           => 'Lapsi',
				'linked_user_id' => 20,
				'status'         => 'aktiivinen',
			)
		);

		$this->assertSame( 'pending_account', $member['status'] );
	}

	public function test_family_member_without_linked_user_defaults_pending_account(): void {
		$member = rytkoset_theme_normalize_family_member(
			array(
				'name'  => 'Odottava jäsen',
				'email' => 'odottaa@example.test',
			)
		);

		$this->assertSame( 'pending_account', $member['status'] );
		$this->assertSame( 0, $member['linked_user_id'] );
	}

	public function test_update_family_members_stores_list_and_reverse_meta(): void {
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		$result = rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Lapsi Rytkönen',
					'email'          => 'lapsi@example.test',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$this->assertTrue( $result );

		$members = rytkoset_theme_get_family_members( 10 );
		$this->assertCount( 1, $members );
		$this->assertSame( 20, $members[0]['linked_user_id'] );
		$this->assertSame( 10, rytkoset_theme_get_family_primary_user_id( 20 ) );
	}

	public function test_update_family_members_rejects_duplicate_email(): void {
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );

		$result = rytkoset_theme_update_family_members(
			10,
			array(
				array( 'email' => 'sama@example.test' ),
				array( 'email' => 'SAMA@example.test' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'rytkoset_duplicate_family_member_email', $result->get_error_codes() );
		$this->assertSame( array(), rytkoset_theme_get_family_members( 10 ) );
	}

	public function test_update_family_members_rejects_duplicate_linked_user(): void {
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		$result = rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Eka',
					'linked_user_id' => 20,
				),
				array(
					'name'           => 'Toka',
					'linked_user_id' => 20,
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'rytkoset_duplicate_family_member_user', $result->get_error_codes() );
	}

	public function test_update_family_members_rejects_user_already_linked_to_another_primary(): void {
		rytkoset_test_register_user( 10, 'eka@example.test', 'Eka päätili' );
		rytkoset_test_register_user( 11, 'toka@example.test', 'Toka päätili' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'active',
					),
				)
			)
		);

		$result = rytkoset_theme_update_family_members(
			11,
			array(
				array(
					'name'           => 'Lapsi',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'rytkoset_family_member_already_linked', $result->get_error_codes() );
		$this->assertSame( 10, rytkoset_theme_get_family_primary_user_id( 20 ) );
	}

	public function test_removed_row_does_not_block_save_when_user_moved_to_another_primary(): void {
		rytkoset_test_register_user( 10, 'eka@example.test', 'Eka päätili' );
		rytkoset_test_register_user( 11, 'toka@example.test', 'Toka päätili' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		// Family A removes the member (reverse meta is cleared), then family B links the user.
		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'removed',
					),
				)
			)
		);
		$this->assertTrue(
			rytkoset_theme_update_family_members(
				11,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'active',
					),
				)
			)
		);

		// Family A must still be able to save its list while the historical removed row remains.
		$result = rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Lapsi',
					'linked_user_id' => 20,
					'status'         => 'removed',
				),
				array(
					'name'  => 'Uusi jäsen',
					'email' => 'uusi@example.test',
				),
			)
		);

		$this->assertTrue( $result );
		$this->assertSame( 11, rytkoset_theme_get_family_primary_user_id( 20 ) );

		// Flipping the removed row back to active must still be blocked while linked elsewhere.
		$reactivated = rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Lapsi',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $reactivated );
		$this->assertContains( 'rytkoset_family_member_already_linked', $reactivated->get_error_codes() );
	}

	public function test_removed_family_member_clears_reverse_meta(): void {
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'active',
					),
				)
			)
		);

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'removed',
					),
				)
			)
		);

		$this->assertSame( 0, rytkoset_theme_get_family_primary_user_id( 20 ) );
		$this->assertSame( 'removed', rytkoset_theme_get_family_members( 10 )[0]['status'] );
	}

	public function test_effective_membership_inherits_active_family_primary(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );
		$this->seed_time_bound( 10, 'family', '2026-2029', '2029-12-31' );

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'active',
					),
				)
			)
		);

		$membership = rytkoset_theme_get_effective_user_membership( 20 );

		$this->assertFalse( rytkoset_theme_user_has_own_active_membership( 20 ) );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 20 ) );
		$this->assertSame( 'family', $membership['type'] );
		$this->assertSame( 'family', $membership['source'] );
		$this->assertSame( 10, $membership['primary_user_id'] );
	}

	public function test_effective_membership_does_not_inherit_expired_or_non_family_primary(): void {
		$GLOBALS['rytkoset_test_now'] = '2030-01-01';
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 11, 'vuosijasen@example.test', 'Vuosijäsen' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );
		rytkoset_test_register_user( 21, 'toinen@example.test', 'Toinen' );

		$this->seed_time_bound( 10, 'family', '2026-2029', '2029-12-31' );
		$this->seed_time_bound( 11, 'annual', '2026-2035', '2035-12-31' );

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'active',
					),
				)
			)
		);
		$this->assertTrue(
			rytkoset_theme_update_family_members(
				11,
				array(
					array(
						'name'           => 'Toinen',
						'linked_user_id' => 21,
						'status'         => 'active',
					),
				)
			)
		);

		$this->assertFalse( rytkoset_theme_user_is_active_member( 20 ) );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 21 ) );
	}

	public function test_effective_membership_prefers_own_active_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'lapsi@example.test', 'Lapsi' );
		$this->seed_time_bound( 10, 'family', '2026-2029', '2029-12-31' );
		$this->seed_time_bound( 20, 'annual', '2026-2029', '2029-12-31' );

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				10,
				array(
					array(
						'name'           => 'Lapsi',
						'linked_user_id' => 20,
						'status'         => 'active',
					),
				)
			)
		);

		$membership = rytkoset_theme_get_effective_user_membership( 20 );

		$this->assertSame( 'annual', $membership['type'] );
		$this->assertSame( 'own', $membership['source'] );
		$this->assertSame( 0, $membership['primary_user_id'] );
	}

	// --- Confirmation email --------------------------------------------------

	public function test_confirmation_email_not_sent_for_unknown_user(): void {
		$this->assertFalse( rytkoset_theme_send_membership_confirmation_email( 999 ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_confirmation_email_not_sent_without_membership(): void {
		rytkoset_test_register_user( 5, 'jasen@rytkoset.test', 'Matti Meikäläinen' );
		$this->assertFalse( rytkoset_theme_send_membership_confirmation_email( 5 ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_confirmation_email_for_lifetime_states_permanent(): void {
		rytkoset_test_register_user( 5, 'jasen@rytkoset.test', 'Matti Meikäläinen' );
		update_user_meta( 5, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$this->assertTrue( rytkoset_theme_send_membership_confirmation_email( 5 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );

		$mail = $GLOBALS['rytkoset_test_mails'][0];
		$this->assertSame( 'jasen@rytkoset.test', $mail['to'] );
		$this->assertStringContainsString( 'Ainaisjäsen', $mail['message'] );
		$this->assertStringContainsString( 'pysyvästi', $mail['message'] );
	}

	public function test_confirmation_email_for_annual_states_period_and_expiry(): void {
		rytkoset_test_register_user( 5, 'jasen@rytkoset.test', 'Matti Meikäläinen' );
		$this->seed_time_bound( 5, 'annual', '2026-2029', '2029-12-31' );

		$this->assertTrue( rytkoset_theme_send_membership_confirmation_email( 5 ) );

		$mail = $GLOBALS['rytkoset_test_mails'][0];
		$this->assertStringContainsString( 'Vuosijäsen', $mail['message'] );
		$this->assertStringContainsString( 'Jäsenkausi: 2026-2029', $mail['message'] );
		$this->assertStringContainsString( 'Voimassa asti: 31.12.2029', $mail['message'] );
	}

	private function seed_time_bound( int $user_id, string $type, string $period, string $expires ): void {
		update_user_meta( $user_id, rytkoset_theme_get_user_membership_type_meta_key(), $type );
		update_user_meta( $user_id, rytkoset_theme_get_user_membership_period_meta_key(), $period );
		update_user_meta( $user_id, rytkoset_theme_get_user_membership_expires_meta_key(), $expires );
	}
}
