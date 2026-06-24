<?php
/**
 * Tests for inc/digital-magazines.php — access mode normalization and the shared read-access gate.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class DigitalMagazineAccessModeTest extends Rytkoset_Theme_Test_Case {

	private const MODE_KEY = '_rytkoset_magazine_access_mode';

	/**
	 * Registers a top-level magazine with the given access mode.
	 *
	 * @param int    $id   Magazine post ID.
	 * @param string $mode Access mode, or '' to leave the meta unset.
	 * @return void
	 */
	private function magazine( int $id, string $mode = '' ): void {
		rytkoset_test_register_post( $id, 'digital_magazine', 'Sukuviesti', 0 );

		if ( '' !== $mode ) {
			update_post_meta( $id, self::MODE_KEY, $mode );
		}
	}

	/**
	 * Marks the given user as an active lifetime member.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function make_member( int $user_id ): void {
		update_user_meta( $user_id, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );
	}

	// --- normalize ----------------------------------------------------------

	public function test_empty_mode_normalizes_to_free(): void {
		$this->assertSame( 'free', rytkoset_theme_normalize_digital_magazine_access_mode( '' ) );
	}

	public function test_unknown_mode_normalizes_to_members_only_fail_closed(): void {
		$this->assertSame( 'members_only', rytkoset_theme_normalize_digital_magazine_access_mode( 'bogus' ) );
	}

	public function test_known_modes_pass_through(): void {
		foreach ( array( 'free', 'members_only', 'member_and_regular', 'paid' ) as $mode ) {
			$this->assertSame( $mode, rytkoset_theme_normalize_digital_magazine_access_mode( $mode ) );
		}
	}

	public function test_label_falls_back_for_unknown_mode(): void {
		$options = rytkoset_theme_get_digital_magazine_access_mode_options();
		$this->assertSame( $options['members_only'], rytkoset_theme_get_digital_magazine_access_mode_label( 'bogus' ) );
		$this->assertSame( $options['paid'], rytkoset_theme_get_digital_magazine_access_mode_label( 'paid' ) );
	}

	// --- parent / article resolution ----------------------------------------

	public function test_article_is_detected_and_resolves_to_parent(): void {
		rytkoset_test_register_post( 50, 'digital_magazine', 'Juttu', 10 );

		$this->assertTrue( rytkoset_theme_is_digital_magazine_article( 50 ) );
		$this->assertSame( 10, rytkoset_theme_get_digital_magazine_parent_id( 50 ) );
	}

	public function test_top_level_magazine_is_its_own_parent(): void {
		$this->magazine( 10 );

		$this->assertFalse( rytkoset_theme_is_digital_magazine_article( 10 ) );
		$this->assertSame( 10, rytkoset_theme_get_digital_magazine_parent_id( 10 ) );
	}

	public function test_access_mode_reads_parent_meta_for_article(): void {
		$this->magazine( 10, 'paid' );
		rytkoset_test_register_post( 50, 'digital_magazine', 'Juttu', 10 );

		$this->assertSame( 'paid', rytkoset_theme_get_digital_magazine_access_mode( 50 ) );
	}

	public function test_is_restricted_only_for_non_free_modes(): void {
		$this->magazine( 10, 'free' );
		$this->assertFalse( rytkoset_theme_digital_magazine_is_restricted( 10 ) );

		$this->magazine( 11, 'members_only' );
		$this->assertTrue( rytkoset_theme_digital_magazine_is_restricted( 11 ) );
	}

	// --- user_can_read: free + editor --------------------------------------

	public function test_free_magazine_is_readable_by_anyone(): void {
		$this->magazine( 10, 'free' );
		$this->assertTrue( rytkoset_theme_user_can_read_digital_magazine( 10, 0 ) );
	}

	public function test_editor_can_always_read_restricted_magazine(): void {
		$this->magazine( 10, 'members_only' );
		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;

		$this->assertTrue( rytkoset_theme_user_can_read_digital_magazine( 10, 0 ) );
	}

	// --- user_can_read: members_only ---------------------------------------

	public function test_members_only_readable_by_active_member(): void {
		$this->magazine( 10, 'members_only' );
		$this->make_member( 1 );

		$this->assertTrue( rytkoset_theme_user_can_read_digital_magazine( 10, 1 ) );
	}

	public function test_members_only_denied_for_non_member(): void {
		$this->magazine( 10, 'members_only' );

		$this->assertFalse( rytkoset_theme_user_can_read_digital_magazine( 10, 1 ) );
	}

	// --- user_can_read: paid / member_and_regular (purchase) ---------------

	public function test_paid_denied_without_purchase(): void {
		$this->magazine( 10, 'paid' );

		$this->assertFalse( rytkoset_theme_user_can_read_digital_magazine( 10, 1 ) );
	}

	public function test_paid_readable_with_manual_grant(): void {
		$this->magazine( 10, 'paid' );
		rytkoset_theme_grant_manual_magazine_access( 1, 10 );

		$this->assertTrue( rytkoset_theme_user_can_read_digital_magazine( 10, 1 ) );
	}

	public function test_member_and_regular_readable_with_woocommerce_purchase(): void {
		$this->magazine( 10, 'member_and_regular' );
		update_post_meta( 10, '_rytkoset_magazine_regular_product_id', 5 );
		rytkoset_test_register_user( 1, 'ostaja@rytkoset.test', 'Ostaja' );
		$GLOBALS['rytkoset_test_bought']['1:5'] = true;

		$this->assertTrue( rytkoset_theme_user_can_read_digital_magazine( 10, 1 ) );
	}

	// --- should_hide + excerpt protection ----------------------------------

	public function test_should_hide_false_for_non_magazine_post(): void {
		rytkoset_test_register_post( 70, 'post', 'Blogi' );
		$this->assertFalse( rytkoset_theme_should_hide_digital_magazine_content( 70 ) );
	}

	public function test_should_hide_true_for_restricted_without_access(): void {
		$this->magazine( 10, 'members_only' );
		$this->assertTrue( rytkoset_theme_should_hide_digital_magazine_content( 10 ) );
	}

	public function test_excerpt_replaced_with_locked_notice_when_hidden(): void {
		$this->magazine( 10, 'members_only' );

		$this->assertSame(
			rytkoset_theme_get_digital_magazine_locked_notice(),
			rytkoset_theme_protect_digital_magazine_excerpt( 'Salainen ote', 10 )
		);
	}

	public function test_manual_excerpt_is_kept_as_public_teaser(): void {
		$this->magazine( 10, 'members_only' );
		$GLOBALS['rytkoset_test_posts'][10]->post_excerpt = 'Julkinen houkutin';

		$this->assertSame(
			'Salainen ote',
			rytkoset_theme_protect_digital_magazine_excerpt( 'Salainen ote', 10 )
		);
	}

	public function test_excerpt_untouched_for_free_magazine(): void {
		$this->magazine( 10, 'free' );

		$this->assertSame(
			'Vapaa ote',
			rytkoset_theme_protect_digital_magazine_excerpt( 'Vapaa ote', 10 )
		);
	}
}
