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
		$this->assertSame( 'gdpr', rytkoset_theme_get_event_registration_error_field( 'missing_consent' ) );
		$this->assertSame( '', rytkoset_theme_get_event_registration_error_field( 'unknown_code' ) );
	}

	// --- rate limit defaults ------------------------------------------------

	public function test_rate_limit_defaults(): void {
		$this->assertSame( 5, rytkoset_theme_get_event_registration_rate_limit() );
		$this->assertSame( 600, rytkoset_theme_get_event_registration_rate_limit_window() );
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
}
