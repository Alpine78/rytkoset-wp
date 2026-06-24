<?php
/**
 * Tests for inc/woocommerce-member-coupon.php — the membership-bound coupon gate (#393).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MemberCouponTest extends Rytkoset_Theme_Test_Case {

	private const MEMBER_ID = 9;

	/**
	 * Builds a coupon stub, optionally flagged members-only.
	 */
	private function make_coupon( bool $members_only ): WC_Coupon {
		return new WC_Coupon(
			array(
				rytkoset_theme_get_member_coupon_meta_key() => $members_only ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Logs in user MEMBER_ID with an active lifetime membership.
	 */
	private function login_active_member(): void {
		$GLOBALS['rytkoset_test_current_user'] = self::MEMBER_ID;
		update_user_meta( self::MEMBER_ID, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );
	}

	// --- requires_active_membership() ----------------------------------------

	public function test_requires_membership_true_for_flagged_coupon(): void {
		$this->assertTrue( rytkoset_theme_coupon_requires_active_membership( $this->make_coupon( true ) ) );
	}

	public function test_requires_membership_false_for_unflagged_coupon(): void {
		$this->assertFalse( rytkoset_theme_coupon_requires_active_membership( $this->make_coupon( false ) ) );
	}

	public function test_requires_membership_false_for_non_coupon(): void {
		$this->assertFalse( rytkoset_theme_coupon_requires_active_membership( null ) );
	}

	// --- validate_member_coupon() --------------------------------------------

	public function test_non_member_coupon_passes_through_for_guest(): void {
		$this->assertTrue(
			rytkoset_theme_validate_member_coupon( true, $this->make_coupon( false ), null )
		);
	}

	public function test_member_coupon_valid_for_active_member(): void {
		$this->login_active_member();

		$this->assertTrue(
			rytkoset_theme_validate_member_coupon( true, $this->make_coupon( true ), null )
		);
	}

	public function test_member_coupon_blocks_guest(): void {
		$this->expectException( Exception::class );

		rytkoset_theme_validate_member_coupon( true, $this->make_coupon( true ), null );
	}

	public function test_member_coupon_blocks_logged_in_non_member(): void {
		$GLOBALS['rytkoset_test_current_user'] = self::MEMBER_ID; // Logged in, but no membership meta.

		$this->expectException( Exception::class );

		rytkoset_theme_validate_member_coupon( true, $this->make_coupon( true ), null );
	}

	public function test_member_coupon_blocks_expired_member(): void {
		$GLOBALS['rytkoset_test_current_user'] = self::MEMBER_ID;
		update_user_meta( self::MEMBER_ID, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( self::MEMBER_ID, rytkoset_theme_get_user_membership_expires_meta_key(), '2020-01-01' );

		$this->expectException( Exception::class );

		rytkoset_theme_validate_member_coupon( true, $this->make_coupon( true ), null );
	}
}
