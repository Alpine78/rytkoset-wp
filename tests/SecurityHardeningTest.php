<?php
/**
 * Tests for inc/security.php — REST user-endpoint restriction, pingback removal and the generic
 * login-error / registration-spam filters.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class SecurityHardeningTest extends Rytkoset_Theme_Test_Case {

	protected function tearDown(): void {
		unset( $_POST['rytkoset_url'] );
		parent::tearDown();
	}

	// --- restrict_rest_user_endpoints --------------------------------------

	public function test_user_endpoints_removed_for_logged_out_visitor(): void {
		$endpoints = array(
			'/wp/v2/users'                 => 'callback',
			'/wp/v2/users/(?P<id>[\d]+)'   => 'callback',
			'/wp/v2/posts'                 => 'callback',
		);

		$filtered = rytkoset_theme_restrict_rest_user_endpoints( $endpoints );

		$this->assertArrayNotHasKey( '/wp/v2/users', $filtered );
		$this->assertArrayNotHasKey( '/wp/v2/users/(?P<id>[\d]+)', $filtered );
		$this->assertArrayHasKey( '/wp/v2/posts', $filtered );
	}

	public function test_user_endpoints_kept_for_logged_in_user(): void {
		$GLOBALS['rytkoset_test_current_user'] = 7;
		$endpoints                             = array( '/wp/v2/users' => 'callback' );

		$this->assertArrayHasKey( '/wp/v2/users', rytkoset_theme_restrict_rest_user_endpoints( $endpoints ) );
	}

	// --- remove_pingback_methods -------------------------------------------

	public function test_pingback_methods_removed(): void {
		$methods = array(
			'pingback.ping'                     => 'callback',
			'pingback.extensions.getPingbacks'  => 'callback',
			'demo.sayHello'                     => 'callback',
		);

		$filtered = rytkoset_theme_remove_pingback_methods( $methods );

		$this->assertArrayNotHasKey( 'pingback.ping', $filtered );
		$this->assertArrayNotHasKey( 'pingback.extensions.getPingbacks', $filtered );
		$this->assertArrayHasKey( 'demo.sayHello', $filtered );
	}

	// --- filter_login_errors -----------------------------------------------

	public function test_credential_error_is_replaced_with_generic_message(): void {
		$errors   = new WP_Error( 'incorrect_password', 'Antamasi salasana on virheellinen.' );
		$filtered = rytkoset_theme_filter_login_errors( $errors );

		$codes = $filtered->get_error_codes();
		$this->assertNotContains( 'incorrect_password', $codes );
		$this->assertContains( 'rytkoset_invalid_credentials', $codes );
	}

	public function test_non_credential_error_is_left_intact(): void {
		$errors   = new WP_Error( 'empty_username', 'Anna käyttäjätunnus.' );
		$filtered = rytkoset_theme_filter_login_errors( $errors );

		$codes = $filtered->get_error_codes();
		$this->assertContains( 'empty_username', $codes );
		$this->assertNotContains( 'rytkoset_invalid_credentials', $codes );
	}

	// --- filter_registration_spam ------------------------------------------

	public function test_honeypot_field_blocks_registration(): void {
		$_POST['rytkoset_url'] = 'http://spam.example';
		$errors                = rytkoset_theme_filter_registration_spam( new WP_Error(), 'kayttaja', 'kayttaja@example.fi' );

		$this->assertContains( 'rytkoset_spam', $errors->get_error_codes() );
	}

	public function test_blocked_email_domain_is_rejected(): void {
		$errors = rytkoset_theme_filter_registration_spam( new WP_Error(), 'pelaaja', 'pelaaja@kasino.casino' );

		$this->assertContains( 'rytkoset_blocked_email', $errors->get_error_codes() );
	}

	public function test_clean_registration_passes(): void {
		$errors = rytkoset_theme_filter_registration_spam( new WP_Error(), 'jasen', 'jasen@rytkoset.fi' );

		$this->assertSame( array(), $errors->get_error_codes() );
	}
}
