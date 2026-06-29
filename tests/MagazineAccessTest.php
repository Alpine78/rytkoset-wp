<?php
/**
 * Tests for inc/digital-magazine-access.php — manual digital magazine access grants (#420).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MagazineAccessTest extends Rytkoset_Theme_Test_Case {

	private const ACCESS_KEY = 'rytkoset_magazine_access';

	// --- Reading stored grants ----------------------------------------------

	public function test_no_grants_returns_empty_array(): void {
		$this->assertSame( array(), rytkoset_theme_get_user_manual_magazine_access( 1 ) );
	}

	public function test_grants_are_normalized_to_int_keys(): void {
		update_user_meta( 1, self::ACCESS_KEY, array( '10' => '2026-01-01 12:00:00', 0 => 'skip' ) );

		$access = rytkoset_theme_get_user_manual_magazine_access( 1 );

		$this->assertSame( array( 10 => '2026-01-01 12:00:00' ), $access );
	}

	public function test_non_array_meta_returns_empty(): void {
		update_user_meta( 1, self::ACCESS_KEY, 'corrupt' );
		$this->assertSame( array(), rytkoset_theme_get_user_manual_magazine_access( 1 ) );
	}

	// --- has_manual_magazine_access -----------------------------------------

	public function test_has_access_true_when_granted(): void {
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );
		$this->assertTrue( rytkoset_theme_user_has_manual_magazine_access( 10, 1 ) );
	}

	public function test_has_access_false_when_not_granted(): void {
		$this->assertFalse( rytkoset_theme_user_has_manual_magazine_access( 10, 1 ) );
	}

	public function test_has_access_false_for_anonymous(): void {
		$this->assertFalse( rytkoset_theme_user_has_manual_magazine_access( 10, 0 ) );
	}

	public function test_article_normalizes_to_parent_magazine(): void {
		$GLOBALS['rytkoset_test_parent_map'][ 50 ] = 10; // article 50 belongs to magazine 10
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );

		$this->assertTrue( rytkoset_theme_user_has_manual_magazine_access( 50, 1 ) );
	}

	// --- grant ---------------------------------------------------------------

	public function test_grant_returns_true_on_transition(): void {
		$this->assertTrue( rytkoset_theme_grant_manual_magazine_access( 1, 10 ) );
	}

	public function test_grant_returns_false_when_already_granted(): void {
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );
		$this->assertFalse( rytkoset_theme_grant_manual_magazine_access( 1, 10 ) );
	}

	public function test_grant_rejects_invalid_input(): void {
		$this->assertFalse( rytkoset_theme_grant_manual_magazine_access( 0, 10 ) );
		$this->assertFalse( rytkoset_theme_grant_manual_magazine_access( 1, 0 ) );
	}

	// --- revoke --------------------------------------------------------------

	public function test_revoke_removes_existing_grant(): void {
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );

		$this->assertTrue( rytkoset_theme_revoke_manual_magazine_access( 1, 10 ) );
		$this->assertFalse( rytkoset_theme_user_has_manual_magazine_access( 10, 1 ) );
	}

	public function test_revoke_returns_false_when_no_grant(): void {
		$this->assertFalse( rytkoset_theme_revoke_manual_magazine_access( 1, 10 ) );
	}

	public function test_revoke_last_grant_clears_meta(): void {
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );
		rytkoset_theme_revoke_manual_magazine_access( 1, 10 );

		$this->assertSame( array(), rytkoset_theme_get_user_manual_magazine_access( 1 ) );
	}

	// --- filter (OR-logic merge with #201 purchase lookup) -------------------

	public function test_filter_returns_existing_true_unchanged(): void {
		// Another callback already granted access; this one must not flip it back.
		$this->assertTrue( rytkoset_theme_filter_manual_magazine_access( true, 10, 1 ) );
	}

	public function test_filter_grants_for_user_with_manual_access(): void {
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );
		$this->assertTrue( rytkoset_theme_filter_manual_magazine_access( false, 10, 1 ) );
	}

	public function test_filter_denies_user_without_manual_access(): void {
		$this->assertFalse( rytkoset_theme_filter_manual_magazine_access( false, 10, 1 ) );
	}

	public function test_filter_ignores_anonymous_user(): void {
		$this->assertFalse( rytkoset_theme_filter_manual_magazine_access( false, 10, 0 ) );
	}

	// --- access email --------------------------------------------------------

	public function test_access_email_sent_for_valid_magazine(): void {
		rytkoset_test_register_user( 1, 'lukija@rytkoset.test', 'Lukija' );
		rytkoset_test_register_post( 10, 'digital_magazine', 'Sukuviesti 2026' );

		$this->assertTrue( rytkoset_theme_send_digital_magazine_access_email( 1, 10 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Sukuviesti 2026', $GLOBALS['rytkoset_test_mails'][0]['subject'] );
	}

	public function test_access_email_not_sent_for_non_magazine_post(): void {
		rytkoset_test_register_user( 1, 'lukija@rytkoset.test', 'Lukija' );
		rytkoset_test_register_post( 10, 'post', 'Tavallinen artikkeli' );

		$this->assertFalse( rytkoset_theme_send_digital_magazine_access_email( 1, 10 ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_access_email_not_sent_for_unknown_user(): void {
		rytkoset_test_register_post( 10, 'digital_magazine', 'Sukuviesti 2026' );
		$this->assertFalse( rytkoset_theme_send_digital_magazine_access_email( 999, 10 ) );
	}
}
