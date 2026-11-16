<?php
/**
 * Tests for the proof of email control in inc/user-email-verification.php (#744).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class UserEmailVerificationTest extends Rytkoset_Theme_Test_Case {

	/** @var int[] User IDs passed to the verified action. */
	private array $verified_events = array();

	/** @var callable|null Listener registered for the verified action. */
	private $listener = null;

	protected function setUp(): void {
		parent::setUp();

		$this->verified_events = array();
		$this->listener        = function ( $user_id ): void {
			$this->verified_events[] = (int) $user_id;
		};

		add_action( 'rytkoset_theme_user_email_verified', $this->listener, 99 );
	}

	protected function tearDown(): void {
		remove_action( 'rytkoset_theme_user_email_verified', $this->listener, 99 );

		parent::tearDown();
	}

	public function test_account_without_proof_is_unverified(): void {
		rytkoset_test_register_user( 7, 'uusi@example.test' );
		do_action( 'user_register', 7, array() );

		$this->assertFalse( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertFalse( rytkoset_theme_user_email_is_verified( 999 ) );
		$this->assertFalse( rytkoset_theme_get_verified_user_by_email( 'uusi@example.test' ) );
		$this->assertFalse( rytkoset_theme_get_verified_user_by_email( '' ) );
		$this->assertSame( array(), $this->verified_events );
	}

	public function test_password_reset_proves_the_current_address(): void {
		$user = rytkoset_test_register_user( 7, 'Uusi@Example.test' );

		do_action( 'after_password_reset', $user, 'new-password' );

		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertSame( 'uusi@example.test', get_user_meta( 7, rytkoset_theme_get_verified_email_meta_key(), true ) );
		$this->assertSame( $user, rytkoset_theme_get_verified_user_by_email( 'Uusi@Example.test' ) );
		$this->assertSame( array( 7 ), $this->verified_events );
	}

	public function test_woocommerce_verification_is_accepted_and_repeat_events_fire_once(): void {
		$user = rytkoset_test_register_user( 7, 'uusi@example.test' );

		// A WooCommerce password reset fires both events for the same proof.
		do_action( 'after_password_reset', $user, 'new-password' );
		do_action( 'woocommerce_customer_email_verified', 7 );
		do_action( 'after_password_reset', $user, 'another-password' );

		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertSame( array( 7 ), $this->verified_events );
	}

	public function test_woocommerce_verification_alone_proves_the_address(): void {
		rytkoset_test_register_user( 7, 'uusi@example.test' );

		do_action( 'woocommerce_customer_email_verified', 7 );
		do_action( 'woocommerce_customer_email_verified', 'not-a-user' );

		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertSame( array( 7 ), $this->verified_events );
	}

	public function test_changing_the_address_invalidates_the_proof(): void {
		$user = rytkoset_test_register_verified_user( 7, 'oma@example.test' );

		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );

		$user->user_email = 'toinen@example.test';

		$this->assertFalse( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertFalse( rytkoset_theme_get_verified_user_by_email( 'toinen@example.test' ) );

		// Changing only the letter case keeps the proof.
		$user->user_email = 'Oma@Example.test';
		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );

		// Proving the new address verifies it and fires the action again.
		$user->user_email = 'toinen@example.test';
		do_action( 'after_password_reset', $user, 'new-password' );

		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertSame( array( 7 ), $this->verified_events );
	}

	public function test_returning_to_an_earlier_address_requires_new_proof(): void {
		$user = rytkoset_test_register_verified_user( 7, 'oma@example.test' );

		// A profile save that keeps the address keeps the proof.
		do_action( 'profile_update', 7, $user, array() );
		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );

		$user->user_email = 'toinen@example.test';
		do_action( 'profile_update', 7, $user, array() );

		$this->assertSame( '', (string) get_user_meta( 7, rytkoset_theme_get_verified_email_meta_key(), true ) );

		// Returning to the earlier address does not revive the old proof.
		$user->user_email = 'oma@example.test';
		do_action( 'profile_update', 7, $user, array() );
		$this->assertFalse( rytkoset_theme_user_email_is_verified( 7 ) );

		// Proving it again fires the verified event so pending benefits are applied.
		do_action( 'after_password_reset', $user, 'new-password' );

		$this->assertTrue( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertSame( array( 7 ), $this->verified_events );
	}

	public function test_invalid_inputs_never_verify(): void {
		rytkoset_test_register_user( 7, '' );

		do_action( 'after_password_reset', 'not-a-user', 'x' );
		$this->assertFalse( rytkoset_theme_mark_user_email_verified( 7 ) );
		$this->assertFalse( rytkoset_theme_mark_user_email_verified( 999 ) );

		update_user_meta( 7, rytkoset_theme_get_verified_email_meta_key(), '' );
		$this->assertFalse( rytkoset_theme_user_email_is_verified( 7 ) );
		$this->assertSame( array(), $this->verified_events );
	}

	public function test_account_hint_shows_only_for_unverified_non_members(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10';

		rytkoset_test_register_user( 7, 'uusi@example.test' );
		rytkoset_test_register_verified_user( 8, 'vahvistettu@example.test' );
		rytkoset_test_register_user( 9, 'jasen@example.test' );
		update_user_meta( 9, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$this->assertTrue( rytkoset_theme_account_should_show_email_verification_hint( 7 ) );
		$this->assertFalse( rytkoset_theme_account_should_show_email_verification_hint( 8 ) );
		$this->assertFalse( rytkoset_theme_account_should_show_email_verification_hint( 9 ) );
		$this->assertFalse( rytkoset_theme_account_should_show_email_verification_hint( 0 ) );
	}

	public function test_instruction_lines_cover_both_missing_and_unverified_accounts(): void {
		$text = implode( "\n", rytkoset_theme_get_email_verification_instruction_lines( 'uusi@example.test' ) );

		$this->assertStringContainsString( 'uusi@example.test', $text );
		$this->assertStringContainsString( 'action=register', $text );
		$this->assertStringContainsString( 'action=lostpassword', $text );
	}
}
