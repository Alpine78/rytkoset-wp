<?php
/**
 * Base test case that resets the in-memory WordPress stub state before each test.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

abstract class Rytkoset_Theme_Test_Case extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		rytkoset_test_reset();
	}

	/**
	 * Builds a membership WooCommerce product stub.
	 *
	 * @param string $type   Product membership type (annual_individual, annual_family, lifetime).
	 * @param string $expiry ISO expiry date (YYYY-MM-DD) or ''.
	 * @param string $period Membership period (e.g. 2026-2029) or ''.
	 * @return WC_Product
	 */
	protected function membership_product( string $type, string $expiry = '', string $period = '' ): WC_Product {
		return new WC_Product(
			array(
				rytkoset_theme_get_membership_product_meta_key()      => 'yes',
				rytkoset_theme_get_membership_type_meta_key()         => $type,
				rytkoset_theme_get_membership_period_meta_key()       => $period,
				rytkoset_theme_get_membership_expiry_date_meta_key()  => $expiry,
			)
		);
	}

	/**
	 * Builds an order stub containing the given membership products.
	 *
	 * @param WC_Product[] $products      Membership products.
	 * @param int          $user_id       Linked user ID (0 for guest).
	 * @param string       $billing_email Billing email.
	 * @return WC_Order
	 */
	protected function membership_order( array $products, int $user_id = 0, string $billing_email = '' ): WC_Order {
		$order                = new WC_Order();
		$order->user_id       = $user_id;
		$order->billing_email = $billing_email;

		foreach ( $products as $product ) {
			$order->items[] = new Rytkoset_Test_Order_Item( $product );
		}

		return $order;
	}
}
