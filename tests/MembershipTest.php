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
