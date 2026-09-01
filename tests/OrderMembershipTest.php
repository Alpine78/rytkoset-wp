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

	public function test_resolve_family_membership_ignores_other_types_and_uses_latest_expiry(): void {
		$resolved = rytkoset_theme_resolve_order_family_membership(
			array(
				$this->item( 'annual_individual', '2035-12-31', '2026-2035' ),
				$this->item( 'annual_family', '2029-12-31', '2026-2029' ),
				$this->item( 'annual_family', '2032-12-31', '2030-2032' ),
				$this->item( 'lifetime', '', '' ),
			)
		);

		$this->assertSame(
			array(
				'type'    => 'family',
				'period'  => '2030-2032',
				'expires' => '2032-12-31',
			),
			$resolved
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
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
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

	public function test_manual_lifetime_activation_survives_family_order_and_family_benefits_expire_separately(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-08-31';
		rytkoset_test_register_user( 150, 'primary@example.test', 'Päätili' );
		rytkoset_test_register_user( 151, 'family@example.test', 'Perheenjäsen' );

		$this->assertSame(
			'applied',
			rytkoset_theme_apply_manual_membership(
				150,
				array(
					'type'    => 'lifetime',
					'period'  => '',
					'expires' => '',
				)
			)
		);

		$order         = $this->membership_order(
			array( $this->membership_product( 'annual_family', '2029-08-31', '2026-2029' ) ),
			150,
			'primary@example.test'
		);
		$order->id     = 661;
		$order->status = 'processing';
		$this->set_member_row( $order, 1, 'Päätili', 'primary@example.test' );
		$this->set_member_row( $order, 2, 'Perheenjäsen', 'family@example.test' );

		rytkoset_theme_maybe_apply_membership_from_order( 661, $order );

		$this->assertSame( 'lifetime', rytkoset_theme_get_user_membership( 150 )['type'] );
		$this->assertSame(
			array(
				'type'    => 'family',
				'period'  => '2026-2029',
				'expires' => '2029-08-31',
			),
			rytkoset_theme_get_user_family_membership( 150 )
		);
		$this->assertTrue( rytkoset_theme_user_is_active_member( 151 ) );
		$this->assertSame( 'family', rytkoset_theme_get_effective_user_membership( 151 )['source'] );

		$GLOBALS['rytkoset_test_now'] = '2029-09-01';
		$this->assertTrue( rytkoset_theme_user_is_active_member( 150 ) );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 151 ) );
		$this->assertSame( 'lifetime', rytkoset_theme_get_user_membership( 150 )['type'] );
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

	public function test_apply_leaves_order_retryable_without_product_expiry(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 16, 'noexp@rytkoset.test', 'NoExp' );

		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '', '2026-2029' ) ),
			16
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 16 )['type'] );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 16 ) );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_mails'], 'No confirmation email when membership cannot be activated.' );
		$this->assertNotEmpty( $order->notes );
	}

	public function test_apply_leaves_order_retryable_without_product_period(): void {
		rytkoset_test_register_user( 18, 'noperiod@rytkoset.test', 'NoPeriod' );
		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-12-31', '' ) ),
			18
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 18 )['type'] );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertStringContainsString( 'Jäsenkausi', $order->notes[0] );
	}

	public function test_apply_leaves_order_retryable_without_valid_product_type(): void {
		rytkoset_test_register_user( 21, 'notype@rytkoset.test', 'NoType' );
		$order = $this->membership_order(
			array( $this->membership_product( '', '2029-12-31', '2026-2029' ) ),
			21
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 21 )['type'] );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertStringContainsString( 'tyyppi', $order->notes[0] );
	}

	public function test_apply_leaves_order_retryable_with_invalid_product_expiry(): void {
		rytkoset_test_register_user( 19, 'invaliddate@rytkoset.test', 'InvalidDate' );
		$order = $this->membership_order(
			array( $this->membership_product( 'annual_individual', '2029-02-30', '2026-2029' ) ),
			19
		);

		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( '', rytkoset_theme_get_user_membership( 19 )['type'] );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertStringContainsString( 'voimassa asti', $order->notes[0] );
	}

	public function test_apply_succeeds_after_invalid_product_is_corrected(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 20, 'retry@rytkoset.test', 'Retry' );
		$product = $this->membership_product( 'annual_individual', '', '2026-2029' );
		$order   = $this->membership_order( array( $product ), 20 );

		rytkoset_theme_apply_membership_from_order( $order );
		$product->update_meta_data( rytkoset_theme_get_membership_expiry_date_meta_key(), '2029-12-31' );
		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 20 )['type'] );
		$this->assertSame( '2029-12-31', rytkoset_theme_get_user_membership( 20 )['expires'] );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key() ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );
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

	// --- Family checkout rows -> family structure (#519) --------------------

	public function test_prepare_family_order_members_normalizes_deduplicates_and_excludes_buyer(): void {
		$prepared = rytkoset_theme_prepare_family_order_members(
			array(
				array( 'name' => ' Ostaja ', 'email' => 'OSTAJA@example.test' ),
				array( 'name' => 'Lapsi', 'email' => ' LAPSI@example.test ' ),
				array( 'name' => 'Tuplarivi', 'email' => 'lapsi@example.test' ),
				array( 'name' => 'Ei sähköpostia', 'email' => '' ),
				array( 'name' => 'Virheellinen', 'email' => 'not-an-email' ),
			),
			array( 'ostaja@example.test' )
		);

		$this->assertSame(
			array(
				array( 'name' => 'Lapsi', 'email' => 'lapsi@example.test' ),
				array( 'name' => 'Ei sähköpostia', 'email' => '' ),
				array( 'name' => 'Virheellinen', 'email' => '' ),
			),
			$prepared
		);
	}

	public function test_family_order_links_existing_account_and_stores_pending_and_register_only_rows(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10';
		rytkoset_test_register_user( 100, 'ostaja@example.test', 'Ostaja' );
		rytkoset_test_register_user( 101, 'lapsi@example.test', 'Lapsi' );
		update_user_meta( 101, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$order         = $this->membership_order(
			array( $this->membership_product( 'annual_family', '2029-12-31', '2026-2029' ) ),
			100,
			'ostaja@example.test'
		);
		$order->id     = 519;
		$order->status = 'processing';
		$this->set_member_row( $order, 1, 'Ostaja', 'OSTAJA@example.test' );
		$this->set_member_row( $order, 2, 'Lapsi', 'LAPSI@example.test' );
		$this->set_member_row( $order, 3, 'Puoliso', 'puoliso@example.test' );
		$this->set_member_row( $order, 4, 'Ei sähköpostia', '' );
		$this->set_member_row( $order, 5, 'Tuplarivi', 'puoliso@example.test' );

		rytkoset_theme_maybe_apply_membership_from_order( 519, $order );

		$members = rytkoset_theme_get_family_members( 100 );

		$this->assertCount( 3, $members, 'Buyer and duplicate email rows must not create family rows.' );
		$this->assertSame( 'lapsi@example.test', $members[0]['email'] );
		$this->assertSame( 101, $members[0]['linked_user_id'] );
		$this->assertSame( 'active', $members[0]['status'] );
		$this->assertSame( 'pending_account', $members[1]['status'] );
		$this->assertSame( 0, $members[1]['linked_user_id'] );
		$this->assertSame( '', $members[2]['email'] );
		$this->assertSame( 519, $members[2]['source_order_id'] );
		$this->assertSame( 100, rytkoset_theme_get_family_primary_user_id( 101 ) );
		$this->assertSame( 'lifetime', rytkoset_theme_get_user_membership( 101 )['type'], "A linked user's own lifetime membership must not be changed." );
		$this->assertSame( 'own', rytkoset_theme_get_effective_user_membership( 101 )['source'] );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_family_members_processed_meta_key() ) );
		$this->assertCount( 3, $GLOBALS['rytkoset_test_mails'], 'Primary confirmation plus active- and pending-family notices.' );
		$this->assertSame( 'lapsi@example.test', $GLOBALS['rytkoset_test_mails'][1]['to'] );
		$this->assertStringContainsString( 'linkitetty tällä sähköpostiosoitteella olevaan käyttäjätiliisi', $GLOBALS['rytkoset_test_mails'][1]['message'] );
		$this->assertStringNotContainsString( 'Luo tili', $GLOBALS['rytkoset_test_mails'][1]['message'] );
		$this->assertStringContainsString( 'https://rytkoset.test/tietosuoja/', $GLOBALS['rytkoset_test_mails'][1]['message'] );
		$this->assertSame( 'puoliso@example.test', $GLOBALS['rytkoset_test_mails'][2]['to'] );
		$this->assertStringContainsString( 'ilmoitettu Rytkösten sukuseura ry:n perhejäsenmaksun yhteydessä', $GLOBALS['rytkoset_test_mails'][2]['message'] );
		$this->assertStringContainsString( 'Luo tili', $GLOBALS['rytkoset_test_mails'][2]['message'] );
		$this->assertStringContainsString( 'https://rytkoset.test/tietosuoja/', $GLOBALS['rytkoset_test_mails'][2]['message'] );
	}

	public function test_family_order_processing_is_idempotent_for_rows_links_and_notices(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10';
		rytkoset_test_register_user( 110, 'primary@example.test', 'Primary' );

		$order         = $this->membership_order(
			array( $this->membership_product( 'annual_family', '2029-12-31', '2026-2029' ) ),
			110,
			'primary@example.test'
		);
		$order->id     = 520;
		$order->status = 'processing';
		$this->set_member_row( $order, 1, 'Primary', 'primary@example.test' );
		$this->set_member_row( $order, 2, 'Pending', 'pending@example.test' );

		rytkoset_theme_maybe_apply_membership_from_order( 520, $order );
		$first_members = rytkoset_theme_get_family_members( 110 );
		$first_mails   = count( $GLOBALS['rytkoset_test_mails'] );
		$first_notes   = count( $order->notes );

		$order->status = 'completed';
		rytkoset_theme_maybe_apply_membership_from_order( 520, $order );

		$this->assertSame( $first_members, rytkoset_theme_get_family_members( 110 ) );
		$this->assertSame( $first_mails, count( $GLOBALS['rytkoset_test_mails'] ) );
		$this->assertSame( $first_notes, count( $order->notes ) );
		$this->assertCount( 1, $order->get_meta( rytkoset_theme_get_family_account_notices_sent_meta_key() ) );
	}

	public function test_registration_links_pending_family_member_and_grants_effective_membership(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10';
		rytkoset_test_register_user( 120, 'primary@example.test', 'Primary' );

		$order         = $this->membership_order(
			array( $this->membership_product( 'annual_family', '2029-12-31', '2026-2029' ) ),
			120,
			'primary@example.test'
		);
		$order->id     = 521;
		$order->status = 'processing';
		$this->set_member_row( $order, 1, 'Primary', 'primary@example.test' );
		$this->set_member_row( $order, 2, 'Uusi jäsen', 'uusi@example.test' );

		rytkoset_theme_maybe_apply_membership_from_order( 521, $order );
		$GLOBALS['rytkoset_test_orders'][521] = $order;
		$mails_before_registration            = count( $GLOBALS['rytkoset_test_mails'] );

		rytkoset_test_register_user( 121, 'uusi@example.test', 'Uusi jäsen' );
		rytkoset_theme_apply_membership_on_user_register( 121 );

		$members = rytkoset_theme_get_family_members( 120 );

		$this->assertSame( 121, $members[0]['linked_user_id'] );
		$this->assertSame( 'active', $members[0]['status'] );
		$this->assertSame( 120, rytkoset_theme_get_family_primary_user_id( 121 ) );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 121 ) );
		$this->assertSame( 'family', rytkoset_theme_get_effective_user_membership( 121 )['source'] );
		$this->assertSame( $mails_before_registration, count( $GLOBALS['rytkoset_test_mails'] ), 'Linking inherited benefits must not send an own-membership confirmation.' );

		rytkoset_theme_apply_membership_on_user_register( 121 );
		$this->assertCount( 1, rytkoset_theme_get_family_members( 120 ) );
	}

	public function test_registration_links_pending_self_service_family_member_without_order(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10';
		rytkoset_test_register_user( 140, 'primary@example.test', 'Primary' );
		update_user_meta( 140, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 140, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( 140, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );

		$this->assertTrue(
			rytkoset_theme_update_family_members(
				140,
				array(
					array(
						'name'  => 'Myöhemmin rekisteröityvä',
						'email' => 'later@example.test',
					),
				)
			)
		);
		$this->assertSame( 'pending_account', rytkoset_theme_get_family_members( 140 )[0]['status'] );

		rytkoset_test_register_user( 141, 'later@example.test', 'Myöhemmin rekisteröityvä' );
		rytkoset_theme_apply_membership_on_user_register( 141 );

		$member = rytkoset_theme_get_family_members( 140 )[0];

		$this->assertSame( 141, $member['linked_user_id'] );
		$this->assertSame( 'active', $member['status'] );
		$this->assertSame( 140, rytkoset_theme_get_family_primary_user_id( 141 ) );
		$this->assertTrue( rytkoset_theme_user_is_active_member( 141 ) );
	}

	public function test_guest_family_order_rows_are_processed_after_buyer_registers(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10';

		$order         = $this->membership_order(
			array( $this->membership_product( 'annual_family', '2029-12-31', '2026-2029' ) ),
			0,
			'guest@example.test'
		);
		$order->id     = 522;
		$order->status = 'processing';
		$this->set_member_row( $order, 1, 'Guest', 'guest@example.test' );
		$this->set_member_row( $order, 2, 'Perheenjäsen', 'family@example.test' );

		rytkoset_theme_maybe_apply_membership_from_order( 522, $order );
		$this->assertSame( '', $order->get_meta( rytkoset_theme_get_family_members_processed_meta_key() ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'], 'Only the buyer account notice is sent before a primary account exists.' );

		$GLOBALS['rytkoset_test_orders'][522] = $order;
		rytkoset_test_register_user( 130, 'guest@example.test', 'Guest' );
		rytkoset_theme_apply_membership_on_user_register( 130 );

		$members = rytkoset_theme_get_family_members( 130 );

		$this->assertCount( 1, $members );
		$this->assertSame( 'family@example.test', $members[0]['email'] );
		$this->assertSame( 'pending_account', $members[0]['status'] );
		$this->assertNotSame( '', $order->get_meta( rytkoset_theme_get_family_members_processed_meta_key() ) );
		$this->assertCount( 3, $GLOBALS['rytkoset_test_mails'], 'Buyer notice, buyer membership confirmation and family-member account notice.' );
	}

	public function test_family_member_order_lookup_filters_email_status_and_product_type(): void {
		$matching         = $this->membership_order( array( $this->membership_product( 'annual_family' ) ), 0 );
		$matching->id     = 530;
		$matching->status = 'processing';
		$this->set_member_row( $matching, 2, 'Match', 'match@example.test' );
		$matching->update_meta_data( rytkoset_theme_get_family_members_processed_meta_key(), '2026-07-10 12:00:00' );

		$wrong_email         = $this->membership_order( array( $this->membership_product( 'annual_family' ) ), 0 );
		$wrong_email->id     = 531;
		$wrong_email->status = 'completed';
		$this->set_member_row( $wrong_email, 2, 'Other', 'other@example.test' );
		$wrong_email->update_meta_data( rytkoset_theme_get_family_members_processed_meta_key(), '2026-07-10 12:00:00' );

		$wrong_type         = $this->membership_order( array( $this->membership_product( 'annual_individual' ) ), 0 );
		$wrong_type->id     = 532;
		$wrong_type->status = 'processing';
		$this->set_member_row( $wrong_type, 1, 'Match', 'match@example.test' );
		$wrong_type->update_meta_data( rytkoset_theme_get_family_members_processed_meta_key(), '2026-07-10 12:00:00' );

		$wrong_status         = $this->membership_order( array( $this->membership_product( 'annual_family' ) ), 0 );
		$wrong_status->id     = 533;
		$wrong_status->status = 'refunded';
		$this->set_member_row( $wrong_status, 2, 'Match', 'match@example.test' );
		$wrong_status->update_meta_data( rytkoset_theme_get_family_members_processed_meta_key(), '2026-07-10 12:00:00' );

		$unprocessed         = $this->membership_order( array( $this->membership_product( 'annual_family' ) ), 0 );
		$unprocessed->id     = 534;
		$unprocessed->status = 'completed';
		$this->set_member_row( $unprocessed, 2, 'Match', 'match@example.test' );

		$GLOBALS['rytkoset_test_orders'] = array(
			530 => $matching,
			531 => $wrong_email,
			532 => $wrong_type,
			533 => $wrong_status,
			534 => $unprocessed,
		);

		$this->assertSame( array( $matching ), rytkoset_theme_get_family_membership_orders_for_email( ' MATCH@example.test ' ) );
		$this->assertSame( array(), rytkoset_theme_get_family_membership_orders_for_email( 'invalid' ) );
	}

	/**
	 * Sets one membership checkout row on an order stub.
	 *
	 * @param WC_Order $order Order stub.
	 * @param int      $index Member row number.
	 * @param string   $name  Member name.
	 * @param string   $email Member email.
	 * @return void
	 */
	private function set_member_row( WC_Order $order, int $index, string $name, string $email ): void {
		$order->update_meta_data( "_wc_other/rytkoset/member_{$index}_name", $name );
		$order->update_meta_data( "_wc_other/rytkoset/member_{$index}_email", $email );
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
