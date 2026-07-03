<?php
/**
 * Tests for inc/woocommerce-payment-retry.php (#462).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class OrderPaymentRetryTest extends Rytkoset_Theme_Test_Case {

	private function make_order( bool $payment_needed = true ): WC_Order {
		$order                       = new WC_Order();
		$order->id                   = 462;
		$order->status               = $payment_needed ? 'pending' : 'processing';
		$order->payment_needed       = $payment_needed;
		$order->checkout_payment_url = 'https://rytkoset.test/kassa/maksa-tilaus/462/?pay_for_order=true&key=wc_order_test';

		return $order;
	}

	public function test_payable_order_can_retry_payment(): void {
		$this->assertTrue( rytkoset_theme_order_can_retry_payment( $this->make_order( true ) ) );
	}

	public function test_non_payable_order_cannot_retry_payment(): void {
		$this->assertFalse( rytkoset_theme_order_can_retry_payment( $this->make_order( false ) ) );
		$this->assertFalse( rytkoset_theme_order_can_retry_payment( 'not-an-order' ) );
	}

	public function test_payment_retry_url_uses_woocommerce_order_pay_url(): void {
		$this->assertSame(
			'https://rytkoset.test/kassa/maksa-tilaus/462/?pay_for_order=true&key=wc_order_test',
			rytkoset_theme_get_order_payment_retry_url( $this->make_order( true ) )
		);
	}

	public function test_payment_retry_action_added_when_missing(): void {
		$actions = rytkoset_theme_add_payment_retry_order_action(
			array(
				'view' => array(
					'url'  => 'https://rytkoset.test/tili/view-order/462/',
					'name' => 'Katso',
				),
			),
			$this->make_order( true )
		);

		$this->assertArrayHasKey( 'pay', $actions );
		$this->assertSame( 'Maksa / yritä uudelleen', $actions['pay']['name'] );
		$this->assertSame( 'https://rytkoset.test/kassa/maksa-tilaus/462/?pay_for_order=true&key=wc_order_test', $actions['pay']['url'] );
		$this->assertSame( array( 'pay', 'view' ), array_keys( $actions ) );
	}

	public function test_existing_pay_action_is_relabelled_without_losing_url(): void {
		$actions = rytkoset_theme_add_payment_retry_order_action(
			array(
				'pay' => array(
					'url'  => 'https://rytkoset.test/custom-pay-url/',
					'name' => 'Pay',
				),
			),
			$this->make_order( true )
		);

		$this->assertSame( 'Maksa / yritä uudelleen', $actions['pay']['name'] );
		$this->assertSame( 'https://rytkoset.test/custom-pay-url/', $actions['pay']['url'] );
	}

	public function test_non_payable_order_actions_are_unchanged(): void {
		$actions = array(
			'view' => array(
				'url'  => 'https://rytkoset.test/tili/view-order/462/',
				'name' => 'Katso',
			),
		);

		$this->assertSame( $actions, rytkoset_theme_add_payment_retry_order_action( $actions, $this->make_order( false ) ) );
	}

	public function test_my_account_endpoint_helper_uses_woocommerce_endpoint_url(): void {
		$this->assertSame(
			'https://rytkoset.test/tili/tilaukset/',
			rytkoset_theme_get_my_account_endpoint_url( 'orders' )
		);
	}
}
