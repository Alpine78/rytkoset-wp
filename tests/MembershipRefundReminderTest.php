<?php
/**
 * Tests for the refunded/cancelled membership order reminder (#746).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembershipRefundReminderTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Builds a paid membership order whose membership was applied to user 40.
	 */
	private function applied_order( string $type = 'annual_individual', int $id = 501 ): WC_Order {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 40, 'jasen@rytkoset.test', 'Jäsen' );

		$order                 = $this->membership_order( array( $this->membership_product( $type, '2029-12-31', '2026-2029' ) ), 40, 'jasen@rytkoset.test' );
		$order->id             = $id;
		$order->order_number   = (string) $id;
		$order->status         = 'processing';
		$order->edit_order_url = 'https://rytkoset.test/wp-admin/post.php?post=' . $id . '&action=edit';
		$GLOBALS['rytkoset_test_orders'][ $id ] = $order;

		rytkoset_theme_maybe_apply_membership_from_order( $id, $order );

		return $order;
	}

	public function test_refund_adds_note_and_mails_admin_without_revoking_membership(): void {
		$order = $this->applied_order();

		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 40 )['type'] );
		$mails_before = count( $GLOBALS['rytkoset_test_mails'] );

		$order->status = 'refunded';
		do_action( 'woocommerce_order_status_refunded', 501, $order );

		// The membership is not revoked automatically.
		$this->assertSame( 'annual', rytkoset_theme_get_user_membership( 40 )['type'] );

		$this->assertStringContainsString( 'Jäsenmaksutilaus on hyvitetty', implode( "\n", $order->notes ) );

		$mails = array_slice( $GLOBALS['rytkoset_test_mails'], $mails_before );
		$this->assertCount( 1, $mails );
		$this->assertSame( 'yhteys@rytkoset.test', $mails[0]['to'] );
		$this->assertStringContainsString( 'Tarkista jäsenyys: jäsenmaksutilaus 501', $mails[0]['subject'] );
		$this->assertStringContainsString( 'Tilaus 501 on hyvitetty', $mails[0]['message'] );
		$this->assertStringContainsString( 'käyttäjään #40: https://rytkoset.test/wp-admin/user-edit.php?user_id=40', $mails[0]['message'] );
		$this->assertStringContainsString( 'Älä poista aiempaa, tilauksesta riippumatonta jäsenyyttä', $mails[0]['message'] );
		$this->assertStringContainsString( 'Avaa tilaus: https://rytkoset.test/wp-admin/post.php?post=501&action=edit', $mails[0]['message'] );
		$this->assertStringNotContainsString( 'perhejäsenrivit', $mails[0]['message'] );
	}

	public function test_each_event_is_reminded_once(): void {
		$order        = $this->applied_order();
		$mails_before = count( $GLOBALS['rytkoset_test_mails'] );

		$order->status = 'cancelled';
		do_action( 'woocommerce_order_status_cancelled', 501, $order );
		do_action( 'woocommerce_order_status_cancelled', 501, $order );

		$this->assertCount( $mails_before + 1, $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Tilaus 501 on peruttu', end( $GLOBALS['rytkoset_test_mails'] )['message'] );

		// A later refund is a different event and is reminded about once more.
		$order->status = 'refunded';
		do_action( 'woocommerce_order_status_refunded', 501, $order );

		$this->assertCount( $mails_before + 2, $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( array( 'cancelled', 'refunded' ), $order->get_meta( rytkoset_theme_get_membership_refund_reminder_meta_key(), true ) );
	}

	public function test_each_partial_refund_is_reminded_and_family_rows_are_mentioned(): void {
		$order        = $this->applied_order( 'annual_family', 502 );
		$mails_before = count( $GLOBALS['rytkoset_test_mails'] );

		do_action( 'woocommerce_order_partially_refunded', 502, 901 );
		do_action( 'woocommerce_order_partially_refunded', 502, 901 );
		do_action( 'woocommerce_order_partially_refunded', 502, 902 );

		$mails = array_slice( $GLOBALS['rytkoset_test_mails'], $mails_before );
		$this->assertCount( 2, $mails );
		$this->assertStringContainsString( 'hyvitetty osittain', $mails[0]['message'] );
		$this->assertStringContainsString( 'perhejäsenrivit', $mails[0]['message'] );
		$this->assertStringContainsString( 'Jäsenmaksu toimintakaudelle: Perhe (2026-2029, voimassa 2029-12-31 asti)', $mails[0]['message'] );
	}

	public function test_unpaid_cancelled_order_without_applied_membership_is_ignored(): void {
		$order               = $this->membership_order( array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ), 40, 'jasen@rytkoset.test' );
		$order->id           = 503;
		$order->status       = 'cancelled';
		$GLOBALS['rytkoset_test_orders'][503] = $order;

		do_action( 'woocommerce_order_status_cancelled', 503, $order );

		$this->assertSame( array(), $GLOBALS['rytkoset_test_mails'] );
		$this->assertSame( array(), $order->notes );
	}

	public function test_non_membership_order_is_ignored(): void {
		$order          = new WC_Order();
		$order->id      = 504;
		$order->status  = 'refunded';
		$order->items[] = new Rytkoset_Test_Order_Item( new WC_Product( array(), 999, 'publish', 'T-paita' ), 'T-paita' );

		$this->assertFalse( rytkoset_theme_remind_membership_refund( $order, 'refunded', 'hyvitetty' ) );
		$this->assertSame( array(), $GLOBALS['rytkoset_test_mails'] );
	}

	public function test_processed_order_is_reminded_after_its_product_lost_the_membership_flag(): void {
		$order          = new WC_Order();
		$order->id      = 506;
		$order->status  = 'refunded';
		$order->items[] = new Rytkoset_Test_Order_Item( new WC_Product( array(), 999, 'publish', 'Poistettu jäsenmaksu' ), 'Poistettu jäsenmaksu' );
		$order->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), '2026-06-23 10:00:00' );
		$order->update_meta_data( rytkoset_theme_get_membership_order_beneficiary_meta_key(), 40 );

		$this->assertTrue( rytkoset_theme_remind_membership_refund( $order, 'refunded', 'hyvitetty' ) );

		$mail = end( $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'ei enää tunnisteta jäsenmaksua', $mail['message'] );
		$this->assertStringContainsString( 'käyttäjään #40', $mail['message'] );
	}

	public function test_reminder_names_the_stored_beneficiary_not_the_current_email_owner(): void {
		$order = $this->applied_order();

		$this->assertSame( 40, $order->get_meta( rytkoset_theme_get_membership_order_beneficiary_meta_key(), true ) );

		// The order later points elsewhere, e.g. the member changed address and another account took it.
		$order->user_id = 77;
		$order->status  = 'refunded';
		do_action( 'woocommerce_order_status_refunded', 501, $order );

		$mail = end( $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'käyttäjään #40: https://rytkoset.test/wp-admin/user-edit.php?user_id=40', $mail['message'] );
		$this->assertStringNotContainsString( '#77', $mail['message'] );
	}

	public function test_order_that_left_a_longer_membership_unchanged_says_so(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';
		rytkoset_test_register_user( 40, 'jasen@rytkoset.test', 'Jäsen' );
		update_user_meta( 40, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$order         = $this->membership_order( array( $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' ) ), 40, 'jasen@rytkoset.test' );
		$order->id     = 507;
		$order->status = 'processing';
		$GLOBALS['rytkoset_test_orders'][507] = $order;
		rytkoset_theme_maybe_apply_membership_from_order( 507, $order );

		$order->status = 'refunded';
		do_action( 'woocommerce_order_status_refunded', 507, $order );

		$mail = end( $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Tilaus ei muuttanut käyttäjän #40 jäsenyyttä', $mail['message'] );
		$this->assertSame( 'lifetime', rytkoset_theme_get_user_membership( 40 )['type'] );
	}

	public function test_legacy_order_without_stored_beneficiary_asks_to_confirm_the_user(): void {
		$order = $this->applied_order();
		$order->delete_meta_data( rytkoset_theme_get_membership_order_beneficiary_meta_key() );

		$order->status = 'refunded';
		do_action( 'woocommerce_order_status_refunded', 501, $order );

		$mail = end( $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Tilaukseen on nyt yhdistetty käyttäjä #40', $mail['message'] );
		$this->assertStringContainsString( 'varmista käyttäjä tilausmuistiinpanoista', $mail['message'] );
	}

	public function test_partial_refund_of_awaiting_guest_order_explains_pending_link(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-06-23';

		$order         = $this->membership_order( array( $this->membership_product( 'lifetime' ) ), 0, 'vieras@rytkoset.test' );
		$order->id     = 505;
		$order->status = 'processing';
		$GLOBALS['rytkoset_test_orders'][505] = $order;
		rytkoset_theme_apply_membership_from_order( $order );

		$this->assertTrue( rytkoset_theme_order_membership_is_awaiting_account( $order ) );

		do_action( 'woocommerce_order_partially_refunded', 505, 903 );

		$mail = end( $GLOBALS['rytkoset_test_mails'] );
		$this->assertStringContainsString( 'Jäsenyyttä ei ole vielä kytketty käyttäjätiliin', $mail['message'] );
		$this->assertStringNotContainsString( 'käyttäjään #', $mail['message'] );

		// A fully refunded awaiting order can no longer be applied, so it needs no reminder.
		$order->status = 'refunded';
		$this->assertFalse( rytkoset_theme_remind_membership_refund( $order, 'refunded', 'hyvitetty' ) );
	}

	public function test_recipient_is_filterable(): void {
		$order  = $this->applied_order();
		$filter = static function () {
			return 'talous@rytkoset.test';
		};

		add_filter( 'rytkoset_theme_membership_refund_reminder_recipient', $filter );
		$order->status = 'refunded';
		do_action( 'woocommerce_order_status_refunded', 501, $order );
		remove_filter( 'rytkoset_theme_membership_refund_reminder_recipient', $filter );

		$this->assertSame( 'talous@rytkoset.test', end( $GLOBALS['rytkoset_test_mails'] )['to'] );
	}
}
