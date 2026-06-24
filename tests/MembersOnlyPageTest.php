<?php
/**
 * Tests for inc/members-only-pages.php — member-gated static pages (#392).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembersOnlyPageTest extends Rytkoset_Theme_Test_Case {

	private const LOCK_KEY = '_rytkoset_members_only';

	/**
	 * Registers a page, optionally flagged members-only.
	 *
	 * @param int  $id     Page post ID.
	 * @param bool $locked Whether to flag it members-only.
	 * @return void
	 */
	private function page( int $id, bool $locked ): void {
		rytkoset_test_register_post( $id, 'page', 'Jäsensivu', 0 );

		if ( $locked ) {
			update_post_meta( $id, self::LOCK_KEY, 'yes' );
		}
	}

	private function make_member( int $user_id ): void {
		update_user_meta( $user_id, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );
	}

	// --- page_is_members_only -----------------------------------------------

	public function test_flagged_page_is_members_only(): void {
		$this->page( 10, true );
		$this->assertTrue( rytkoset_theme_page_is_members_only( 10 ) );
	}

	public function test_unflagged_page_is_not_members_only(): void {
		$this->page( 10, false );
		$this->assertFalse( rytkoset_theme_page_is_members_only( 10 ) );
	}

	public function test_non_page_post_type_is_never_members_only(): void {
		rytkoset_test_register_post( 10, 'post', 'Blogi' );
		update_post_meta( 10, self::LOCK_KEY, 'yes' );

		$this->assertFalse( rytkoset_theme_page_is_members_only( 10 ) );
	}

	// --- user_can_read_members_only_page -----------------------------------

	public function test_unrestricted_page_readable_by_anyone(): void {
		$this->page( 10, false );
		$this->assertTrue( rytkoset_theme_user_can_read_members_only_page( 10, 0 ) );
	}

	public function test_editor_can_read_restricted_page(): void {
		$this->page( 10, true );
		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;

		$this->assertTrue( rytkoset_theme_user_can_read_members_only_page( 10, 0 ) );
	}

	public function test_active_member_can_read_restricted_page(): void {
		$this->page( 10, true );
		$this->make_member( 1 );

		$this->assertTrue( rytkoset_theme_user_can_read_members_only_page( 10, 1 ) );
	}

	public function test_non_member_cannot_read_restricted_page(): void {
		$this->page( 10, true );
		$this->assertFalse( rytkoset_theme_user_can_read_members_only_page( 10, 1 ) );
	}

	// --- should_hide + excerpt protection ----------------------------------

	public function test_should_hide_false_for_unrestricted_page(): void {
		$this->page( 10, false );
		$this->assertFalse( rytkoset_theme_should_hide_members_only_page( 10 ) );
	}

	public function test_should_hide_true_for_restricted_page_without_access(): void {
		$this->page( 10, true );
		$this->assertTrue( rytkoset_theme_should_hide_members_only_page( 10 ) );
	}

	public function test_excerpt_replaced_with_locked_notice_when_hidden(): void {
		$this->page( 10, true );

		$this->assertSame(
			rytkoset_theme_get_members_only_page_locked_notice(),
			rytkoset_theme_protect_members_only_page_excerpt( 'Salainen ote', 10 )
		);
	}

	public function test_manual_excerpt_kept_as_public_teaser(): void {
		$this->page( 10, true );
		$GLOBALS['rytkoset_test_posts'][10]->post_excerpt = 'Julkinen houkutin';

		$this->assertSame(
			'Salainen ote',
			rytkoset_theme_protect_members_only_page_excerpt( 'Salainen ote', 10 )
		);
	}

	public function test_excerpt_untouched_for_unrestricted_page(): void {
		$this->page( 10, false );

		$this->assertSame(
			'Vapaa ote',
			rytkoset_theme_protect_members_only_page_excerpt( 'Vapaa ote', 10 )
		);
	}
}
