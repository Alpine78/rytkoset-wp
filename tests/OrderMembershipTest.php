<?php
/**
 * Tests for the automatic membership update from a WooCommerce order (#302),
 * in inc/woocommerce-membership.php.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class OrderMembershipTest extends Rytkoset_Theme_Test_Case {

	// --- Product -> user type mapping & priority -----------------------------

	public function test_product_type_maps_to_user_type(): void {
		$this->assertSame( 'annual', rytkoset_theme_map_product_to_user_membership_type( 'annual_individual' ) );
		$this->assertSame( 'family', rytkoset_theme_map_product_to_user_membership_type( 'annual_family' ) );
		$this->assertSame( 'lifetime', rytkoset_theme_map_product_to_user_membership_type( 'lifetime' ) );
		$this->assertSame( '', rytkoset_theme_map_product_to_user_membership_type( 'unknown' ) );
		$this->assertSame( '', rytkoset_theme_map_product_to_user_membership_type( '' ) );
	}

	public function test_user_type_priority_ordering(): void {
		$this->assertSame( 1, rytkoset_theme_get_user_membership_type_priority( 'annual' ) );
		$this->assertSame( 2, rytkoset_theme_get_user_membership_type_priority( 'family' ) );
		$this->assertSame( 3, rytkoset_theme_get_user_membership_type_priority( 'lifetime' ) );
		$this->assertSame( 0, rytkoset_theme_get_user_membership_type_priority( 'unknown' ) );
	}

	// --- resolve_order_membership (best membership from items) ---------------

	public function test_resolve_empty_for_no_items(): void {
		$this->assertSame( array(), rytkoset_theme_resolve_order_membership( array() ) );
	}

	public function test_resolve_single_annual(): void {
		$resolved = rytkoset_theme_resolve_order_membership(
			array( $this->item( 'annual_individual', '2029-12-31', '2026-2029' ) )
		);

		$this->assertSame( array(
			'type'    => 'annual',
			'period'  => '2026-2029',
			'expires' => '2029-12-31',
		), $resolved );
	}

	public function test_resolve_lifetime_drops_expiry(): void {
		$resolved = rytkoset_theme_resolve_order_membership(
			array( $this->item( 'lifetime', '2029-12-31', '' ) )
		);

		$this->assertSame( 'lifetime', $resolved['type'] );
		$this->assertSame( '', $resolved['expires'] );
	}

	public function test_resolve_lifetime_beats_time_bound(): void {
		$resolved = rytkoset_theme_resolve_order_membership(
			array(
				$this->item( 'annual_individual', '2029-12-31', '2026-2029' ),
				$this->item( 'lifetime', '', '' ),
			)
		);

		$this->assertSame( 'lifetime', $resolved['type'] );
	}

	public function test_resolve_picks_latest_expiry_for_same_priority(): void {
		$resolved = rytkoset_theme_resolve_order_membership(
			array(
				$this->item( 'annual_individual', '2027-12-31', '2026-2027' ),
				$this->item( 'annual_individual', '2030-12-31', '2026-2030' ),
			)
		);

		$this->assertSame( '2030-12-31', $resolved['expires'] );
		$this->assertSame( '2026-2030', $resolved['period'] );
	}

	public function test_resolve_higher_priority_wins_over_later_expiry(): void {
		// family (priority 2) beats annual (priority 1) even with an earlier expiry date.
		$resolved = rytkoset_theme_resolve_order_membership(
			array(
				$this->item( 'annual_individual', '2035-12-31', '2026-2035' ),
				$this->item( 'annual_family', '2027-12-31', '2026-2027' ),
			)
		);

		$this->assertSame( 'family', $resolved['type'] );
		$this->assertSame( '2027-12-31', $resolved['expires'] );
	}

	public function test_resolve_skips_unmapped_items(): void {
		$resolved = rytkoset_theme_resolve_order_membership(
			array(
				$this->item( 'unknown', '2030-12-31', '' ),
				$this->item( 'annual_individual', '2029-12-31', '2026-2029' ),
			)
		);

		$this->assertSame( 'annual', $resolved['type'] );
	}

	public function test_resolve_empty_when_all_unmapped(): void {
		$this->assertSame(
			array(),
			rytkoset_theme_resolve_order_membership( array( $this->item( 'unknown', '', '' ) ) )
		);
	}

	// --- apply_membership_from_order (end-to-end with stubs) -----------------

	public function test_apply_sets_membership_for_linked_user(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 10, 'ostaja@rytkoset.test', 'Ostaja' );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			10
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 10 )['type'] );
		$this->assertSame( '2029-12-31', rytkoset_theme_get_user_membership( 10 )['expires'] );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 10 ) );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_apply_sends_confirmation_when_user_only_inherits_family_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 80, 'paakayttaja@rytkoset.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 81, 'lapsi@rytkoset.test', 'Lapsi' );
		update_user_meta( 80, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 80, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( 80, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		rytkoset_theme_update_family_members(
			80,
			array(
				array(
					'name'           => 'Lapsi',
					'email'          => 'lapsi@rytkoset.test',
					'linked_user_id' => 81,
					'status'         => 'active',
				),
			)
		);

		$this->assertTrue( rytkoset_theme_user_is_active_member( 81 ) );
		$this->assertFalse( rytkoset_theme_user_has_own_active_membership( 81 ) );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2030-12-31', '2026-2030' ) ),
			81
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 81 )['type'] );
		$this->assertTrue( rytkoset_theme_user_has_own_active_membership( 81 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'], 'Inherited family membership must not suppress the own-membership confirmation.' );
		$this->assertSame( 'lapsi@rytkoset.test', $GLOBALS['rytkoset_test_mails'][0]['to'] );
	}

	public function test_apply_resolves_guest_order_by_billing_email(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 11, 'match@rytkoset.test', 'Match' );

		$order = $this->membership_order(
			array( $this->membership_product( 'lifetime' ) ),
			0,
			'match@rytkoset.test'
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( 'lifetime', rytkoset_theme_get_user_membership( 11 )['type'] );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_awaiting_account_meta_key() ), 'A matched guest order must not be marked awaiting an account.' );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'], 'Only the membership confirmation email, no create-account notice.' );
	}

	// --- Guest order awaiting an account (#518) -------------------------------

	public function test_apply_marks_unmatched_guest_awaiting_and_sends_notice(): void {
		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			0,
			'nobody@rytkoset.test'
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ), 'The processed meta must not lock an order awaiting an account.' );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_membership_awaiting_account_meta_key() ) );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_membership_account_notice_sent_meta_key() ) );
		$this->assertTrue( rytkoset_theme_order_membership_is_awaiting_account( $order ) );
		$this->assertNotEmpty( $order->notes );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( 'nobody@rytkoset.test', $GLOBALS['rytkoset_test_mails'][0]['to'] );
		$this->assertStringContainsString( wp_registration_url(), $GLOBALS['rytkoset_test_mails'][0]['message'] );
	}

	public function test_apply_skips_notice_email_without_valid_billing_email(): void {
		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			0,
			'not-an-email'
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertTrue( rytkoset_theme_order_membership_is_awaiting_account( $order ) );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_account_notice_sent_meta_key() ) );
		$this->assertNotEmpty( $order->notes, 'The admin note is still added when the notice email cannot be sent.' );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_apply_does_not_repeat_notice_on_reprocessing(): void {
		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			0,
			'nobody@rytkoset.test'
		);

		rytkoset_theme_apply_membership_from_order( $order );
		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertCount( 1, $order->notes );
	}

	public function test_awaiting_account_state_requires_awaiting_meta_without_processed(): void {
		$order = $this->membership_order( array(), 0 );
		$this->assertFalse( rytkoset_theme_order_membership_is_awaiting_account( $order ) );

		$order->update_meta_data( rytkoset_theme_get_membership_awaiting_account_meta_key(), '2026-07-01 12:00:00' );
		$this->assertTrue( rytkoset_theme_order_membership_is_awaiting_account( $order ) );

		$order->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), '2026-07-02 12:00:00' );
		$this->assertFalse( rytkoset_theme_order_membership_is_awaiting_account( $order ) );

		$this->assertFalse( rytkoset_theme_order_membership_is_awaiting_account( null ) );
	}

	// --- Membership linking on account registration (#518) --------------------

	public function test_registration_applies_awaiting_membership_order(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';

		$order         = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			0,
			'uusi@rytkoset.test'
		);
		$order->status = 'processing';

		rytkoset_theme_apply_membership_from_order( $order );
		$GLOBALS['rytkoset_test_orders'][1] = $order;
		$mails_before_registration          = count( $GLOBALS['rytkoset_test_mails'] );

		rytkoset_test_register_user( 30, 'uusi@rytkoset.test', 'Uusi' );
		rytkoset_theme_apply_membership_on_user_register( 30 );

		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 30 )['type'] );
		$this->assertSame( '2029-12-31', rytkoset_theme_get_user_membership( 30 )['expires'] );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 30 ) );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertFalse( rytkoset_theme_order_membership_is_awaiting_account( $order ) );
		$this->assertCount( 2, $order->notes, 'The awaiting note plus the applied note.' );
		$this->assertSame( $mails_before_registration + 1, count( $GLOBALS['rytkoset_test_mails'] ), 'The membership confirmation email (#390) is sent on linking.' );
		$this->assertSame( 'uusi@rytkoset.test', $GLOBALS['rytkoset_test_mails'][ $mails_before_registration ]['to'] );
	}

	public function test_registration_ignores_processed_and_wrong_status_orders(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';

		$processed         = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			0,
			'valmis@rytkoset.test'
		);
		$processed->status = 'processing';
		$processed->update_meta_data( rytkoset_theme_get_membership_awaiting_account_meta_key(), '2026-06-01 12:00:00' );
		$processed->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), '2026-06-01 12:00:00' );

		$refunded         = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			0,
			'valmis@rytkoset.test'
		);
		$refunded->status = 'refunded';
		$refunded->update_meta_data( rytkoset_theme_get_membership_awaiting_account_meta_key(), '2026-06-01 12:00:00' );

		$GLOBALS['rytkoset_test_orders'] = array(
			1 => $processed,
			2 => $refunded,
		);

		rytkoset_test_register_user( 31, 'valmis@rytkoset.test', 'Valmis' );
		rytkoset_theme_apply_membership_on_user_register( 31 );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 31 )['type'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_registration_without_membership_orders_changes_nothing(): void {
		rytkoset_test_register_user( 32, 'tyhja@rytkoset.test', 'Tyhja' );

		rytkoset_theme_apply_membership_on_user_register( 32 );
		rytkoset_theme_apply_membership_on_user_register( 999 ); // unknown user

		$this->assertSame( '', rytkoset_theme_get_user_membership( 32 )['type'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_apply_is_idempotent(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 12, 'idem@rytkoset.test', 'Idem' );

		$product = $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' );
		$order   = $this->membership_order( array( $product ), 12 );

		rytkoset_theme_apply_membership_from_order( $order );
		$mails_after_first = count( $GLOBALS['rytkoset_test_mails'] );

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( $mails_after_first, count( $GLOBALS['rytkoset_test_mails'] ), 'No second email on re-processing.' );
	}

	public function test_apply_never_shortens_lifetime(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 13, 'life@rytkoset.test', 'Life' );
		update_user_meta( 13, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			13
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( 'lifetime', rytkoset_theme_get_user_membership( 13 )['type'] );
		$this->assertNotEmpty( $order->notes );
	}

	public function test_apply_does_not_shorten_longer_existing_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 14, 'longer@rytkoset.test', 'Longer' );
		update_user_meta( 14, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( 14, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2035' );
		update_user_meta( 14, rytkoset_theme_get_user_membership_expires_meta_key(), '2035-12-31' );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ),
			14
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '2035-12-31', rytkoset_theme_get_user_membership( 14 )['expires'] );
	}

	public function test_apply_extends_shorter_existing_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 15, 'extend@rytkoset.test', 'Extend' );
		update_user_meta( 15, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( 15, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2027' );
		update_user_meta( 15, rytkoset_theme_get_user_membership_expires_meta_key(), '2027-12-31' );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2030-12-31', '2026-2030' ) ),
			15
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '2030-12-31', rytkoset_theme_get_user_membership( 15 )['expires'] );
	}

	public function test_apply_stores_but_flags_membership_without_product_expiry(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 16, 'noexp@rytkoset.test', 'NoExp' );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '', '2026-2029' ) ),
			16
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 16 )['type'] );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 16 ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'], 'No confirmation email when membership cannot be activated.' );
		$this->assertNotEmpty( $order->notes );
	}

	public function test_apply_ignores_order_without_membership_products(): void {
		rytkoset_test_register_user( 17, 'plain@rytkoset.test', 'Plain' );

		$order          = new WC_Order();
		$order->user_id = 17;
		$order->items[] = new Rytkoset_Test_Order_Item( new WC_Product() ); // not a membership product

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 17 )['type'] );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
	}

	/**
	 * Builds a resolve_order_membership() input item.
	 *
	 * @param string $type        Product membership type.
	 * @param string $expiry_date ISO expiry date or ''.
	 * @param string $period      Period string or ''.
	 * @return array<string,mixed>
	 */
	private function item( string $type, string $expiry_date, string $period ): array {
		return array(
			'type'        => $type,
			'expiry_date' => $expiry_date,
			'period'      => $period,
		);
	}
}
