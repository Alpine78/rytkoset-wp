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

	// --- personal identity code usernames (#726) ----------------------------

	public function test_personal_id_shapes_are_recognized(): void {
		foreach ( array( '010190-123A', '311299+9999', '150305A001Y', '150305b001x', 'matti010190-123a', '010190-123A.virtanen', '150305Y001N', '010190-125cvirtanen', '010190-125c9', '9010190-125c' ) as $text ) {
			$this->assertTrue( rytkoset_theme_text_contains_personal_id( $text ), $text );
		}

		// Impossible dates, missing separator or digits, and ordinary names.
		foreach ( array( '320190-123A', '011390-123A', '010190123A', '010190-12A', 'matti.virtanen', 'jasen2026', '0101-90-123A' ) as $text ) {
			$this->assertFalse( rytkoset_theme_text_contains_personal_id( $text ), $text );
		}
	}

	public function test_typed_personal_id_username_is_rejected(): void {
		$errors = rytkoset_theme_reject_personal_id_username( new WP_Error(), '010190-123a' );
		$this->assertContains( 'rytkoset_personal_id_username', $errors->get_error_codes() );

		$this->assertSame( array(), rytkoset_theme_reject_personal_id_username( new WP_Error(), 'matti.virtanen' )->get_error_codes() );
	}

	public function test_registration_errors_also_runs_the_personal_id_check(): void {
		$errors = apply_filters( 'registration_errors', new WP_Error(), '010190-123a', 'jasen@rytkoset.fi' );
		$this->assertContains( 'rytkoset_personal_id_username', $errors->get_error_codes() );

		$errors = apply_filters( 'woocommerce_registration_errors', new WP_Error(), '010190-123a', 'jasen@rytkoset.fi' );
		$this->assertContains( 'rytkoset_personal_id_username', $errors->get_error_codes() );
	}

	public function test_admin_add_user_is_checked_but_existing_users_are_not(): void {
		$user             = new stdClass();
		$user->user_login = '010190-123a';

		$errors = new WP_Error();
		rytkoset_theme_reject_personal_id_username_in_admin( $errors, false, $user );
		$this->assertContains( 'rytkoset_personal_id_username', $errors->get_error_codes() );

		$errors = new WP_Error();
		rytkoset_theme_reject_personal_id_username_in_admin( $errors, true, $user );
		$this->assertSame( array(), $errors->get_error_codes() );
	}

	public function test_generated_woocommerce_username_is_replaced(): void {
		rytkoset_test_register_user( 30, 'varattu@example.test', '', 'asiakas-000001' );
		$GLOBALS['rytkoset_test_wp_rand'] = array( 1, 2 );

		try {
			// The first candidate is taken, the second is used.
			$this->assertSame( 'asiakas-000002', rytkoset_theme_replace_generated_personal_id_username( '010190-123a' ) );
		} finally {
			unset( $GLOBALS['rytkoset_test_wp_rand'] );
		}

		$this->assertSame( 'matti.virtanen', rytkoset_theme_replace_generated_personal_id_username( 'matti.virtanen', 'matti.virtanen@example.test' ) );
	}

	public function test_generated_username_sources_are_checked_before_sanitizing(): void {
		// sanitize_user() drops the "+" century sign, so the email it came from is checked.
		$this->assertStringStartsWith( 'asiakas-', rytkoset_theme_replace_generated_personal_id_username( '010190125c', '010190+125c@example.test' ) );
		$this->assertStringStartsWith( 'asiakas-', rytkoset_theme_replace_generated_personal_id_username( 'matti.010190125c', 'm@example.test', array( 'first_name' => 'Matti', 'last_name' => '010190+125c' ) ) );
		$this->assertSame( 'matti.virtanen', rytkoset_theme_replace_generated_personal_id_username( 'matti.virtanen', 'm@example.test', array( 'first_name' => 'Matti', 'last_name' => 'Virtanen' ) ) );
	}

	public function test_direct_user_insert_is_stopped_for_new_users_only(): void {
		$data = array( 'user_login' => '010190-123a' );

		// REST, WP-CLI or a plugin calling wp_insert_user(): an empty array makes core stop.
		$this->assertSame( array(), rytkoset_theme_prevent_personal_id_user_insert( $data, false ) );
		$this->assertSame( $data, rytkoset_theme_prevent_personal_id_user_insert( $data, true ) );
		$this->assertSame( array( 'user_login' => 'jasen' ), rytkoset_theme_prevent_personal_id_user_insert( array( 'user_login' => 'jasen' ), false ) );

		// Core strips "+" from the stored login, so the raw requested login is checked as well.
		$this->assertSame( array(), rytkoset_theme_prevent_personal_id_user_insert( array( 'user_login' => 'review010190129g' ), false, null, array( 'user_login' => 'review010190+129g' ) ) );
	}

	public function test_personal_id_block_can_be_turned_off(): void {
		$off = static function () {
			return false;
		};
		add_filter( 'rytkoset_theme_block_personal_id_usernames', $off );

		try {
			$this->assertSame( array(), rytkoset_theme_reject_personal_id_username( new WP_Error(), '010190-123a' )->get_error_codes() );
			$this->assertSame( '010190-123a', rytkoset_theme_replace_generated_personal_id_username( '010190-123a' ) );
		} finally {
			remove_filter( 'rytkoset_theme_block_personal_id_usernames', $off );
		}
	}

	public function test_rest_user_creation_gets_a_clear_error(): void {
		$request = new class( 'POST', '/wp/v2/users', '010190-123a' ) {
			public function __construct( private string $method, private string $route, private string $username ) {}

			public function get_method() {
				return $this->method;
			}

			public function get_route() {
				return $this->route;
			}

			public function get_param( $key ) {
				return 'username' === $key ? $this->username : null;
			}
		};

		$error = rytkoset_theme_reject_personal_id_rest_user( null, array(), $request );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertContains( 'rytkoset_personal_id_username', $error->get_error_codes() );

		// Other routes and an earlier response pass untouched.
		$update = new class( 'POST', '/wp/v2/users/7', '010190-123a' ) {
			public function __construct( private string $method, private string $route, private string $username ) {}

			public function get_method() {
				return $this->method;
			}

			public function get_route() {
				return $this->route;
			}

			public function get_param( $key ) {
				return 'username' === $key ? $this->username : null;
			}
		};
		$this->assertNull( rytkoset_theme_reject_personal_id_rest_user( null, array(), $update ) );
		$this->assertSame( 'earlier', rytkoset_theme_reject_personal_id_rest_user( 'earlier', array(), $request ) );
	}
}
