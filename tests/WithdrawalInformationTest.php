<?php
/**
 * Tests for the statutory withdrawal information in WooCommerce customer emails (#573).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class WithdrawalInformationTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Builds an order containing the provided products.
	 *
	 * @param WC_Product ...$products Products to add to the order.
	 * @return WC_Order
	 */
	private function order_with_products( WC_Product ...$products ): WC_Order {
		$order     = new WC_Order();
		$order->id = 573;

		foreach ( $products as $product ) {
			$order->items[] = new Rytkoset_Test_Order_Item( $product, $product->get_name() );
		}

		return $order;
	}

	/**
	 * Builds a minimal WooCommerce email object.
	 *
	 * @param string $id WooCommerce email ID.
	 * @return object
	 */
	private function email( string $id ): object {
		$email     = new stdClass();
		$email->id = $id;

		return $email;
	}

	public function test_physical_product_order_receives_withdrawal_information(): void {
		$product = new WC_Product( array( '_virtual' => 'no' ), 10, 'publish', 'Painettu sukulehti' );
		$order   = $this->order_with_products( $product );

		$this->assertTrue( rytkoset_theme_product_has_withdrawal_right( $product, $order ) );
		$this->assertTrue( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_tampere_event_order_does_not_receive_withdrawal_information(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'tampere_2026' ), 11, 'publish', 'Tampere 2026' );
		$order   = $this->order_with_products( $product );

		$this->assertTrue( rytkoset_theme_product_is_withdrawal_exempt_event( $product, $order ) );
		$this->assertFalse( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_bus_transport_order_does_not_receive_withdrawal_information(): void {
		$product = new WC_Product( array( '_sku' => 'tampere-2026-bussikyyti' ), 12, 'publish', 'Bussikyyti' );
		$order   = $this->order_with_products( $product );

		$this->assertTrue( rytkoset_theme_product_is_withdrawal_exempt_event( $product, $order ) );
		$this->assertFalse( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_mixed_event_and_physical_order_receives_withdrawal_information(): void {
		$event    = new WC_Product( array( '_rytkoset_registration_mode' => 'tampere_2026' ), 11, 'publish', 'Tampere 2026' );
		$physical = new WC_Product( array( '_virtual' => 'no' ), 10, 'publish', 'Painettu sukulehti' );
		$order    = $this->order_with_products( $event, $physical );

		$this->assertTrue( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_membership_order_defaults_to_receiving_withdrawal_information(): void {
		$product = $this->membership_product( 'annual_individual', '2029-12-31', '2026-2029' );
		$order   = $this->order_with_products( $product );

		$this->assertTrue( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_digital_magazine_without_consent_receives_withdrawal_information(): void {
		rytkoset_test_register_post( 20, 'digital_magazine', 'Sukuviesti' );
		update_post_meta( 20, rytkoset_theme_get_digital_magazine_regular_product_meta_key(), 13 );

		$product = new WC_Product( array( '_virtual' => 'yes' ), 13, 'publish', 'Digilehti' );
		$order   = $this->order_with_products( $product );

		$this->assertTrue( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_digital_magazine_with_consent_does_not_receive_withdrawal_information(): void {
		rytkoset_test_register_post( 20, 'digital_magazine', 'Sukuviesti' );
		update_post_meta( 20, rytkoset_theme_get_digital_magazine_regular_product_meta_key(), 13 );

		$product  = new WC_Product( array( '_virtual' => 'yes' ), 13, 'publish', 'Digilehti' );
		$order    = $this->order_with_products( $product );
		$meta_key = '_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id();

		$order->meta[ $meta_key ] = '1';

		$this->assertFalse( rytkoset_theme_order_has_withdrawal_right_products( $order ) );
	}

	public function test_html_customer_order_confirmation_contains_instruction_and_form(): void {
		$order = $this->order_with_products(
			new WC_Product( array( '_virtual' => 'no' ), 10, 'publish', 'Painettu sukulehti' )
		);

		ob_start();
		rytkoset_theme_render_withdrawal_information_in_email(
			$order,
			false,
			false,
			$this->email( 'customer_processing_order' )
		);
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<h2>Peruuttamisohje ja -lomake</h2>', $output );
		$this->assertStringContainsString( 'Peruuttamisen vaikutukset', $output );
		$this->assertStringContainsString( 'Peruuttamislomakkeen malli', $output );
		$this->assertStringContainsString( 'Rytkösten sukuseura ry', $output );
		$this->assertStringContainsString( 'tapahtumamaksuihin', $output );
	}

	public function test_plain_text_completed_order_email_contains_durable_copy(): void {
		$order = $this->order_with_products(
			new WC_Product( array( '_virtual' => 'yes' ), 10, 'publish', 'Virtuaalinen palvelu' )
		);

		ob_start();
		rytkoset_theme_render_withdrawal_information_in_email(
			$order,
			false,
			true,
			$this->email( 'customer_completed_order' )
		);
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'PERUUTTAMISOHJE JA -LOMAKE', $output );
		$this->assertStringContainsString( 'Tilauspäivä (*) / Vastaanottopäivä (*)', $output );
		$this->assertStringNotContainsString( '<h2>', $output );
	}

	public function test_admin_and_unrelated_customer_emails_do_not_receive_information(): void {
		$order = $this->order_with_products(
			new WC_Product( array(), 10, 'publish', 'Testituote' )
		);

		ob_start();
		rytkoset_theme_render_withdrawal_information_in_email(
			$order,
			true,
			false,
			$this->email( 'new_order' )
		);
		$admin_output = (string) ob_get_clean();

		ob_start();
		rytkoset_theme_render_withdrawal_information_in_email(
			$order,
			false,
			false,
			$this->email( 'customer_refunded_order' )
		);
		$refund_output = (string) ob_get_clean();

		$this->assertSame( '', $admin_output );
		$this->assertSame( '', $refund_output );
	}
}
