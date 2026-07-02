<?php
/**
 * WooCommerce payment retry helpers for My Account orders (#462).
 *
 * Exposes WooCommerce's own order-pay URL in customer-facing places when the
 * order is still payable. The theme does not create a custom payment flow.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the My Account page URL.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_my_account_url' ) ) {
	function rytkoset_theme_get_my_account_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );

			if ( is_string( $url ) && '' !== $url && '#' !== $url ) {
				return $url;
			}
		}

		return home_url( '/oma-tili/' );
	}
}

/**
 * Returns a My Account endpoint URL.
 *
 * @param string $endpoint WooCommerce endpoint key, e.g. orders.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_my_account_endpoint_url' ) ) {
	function rytkoset_theme_get_my_account_endpoint_url( $endpoint = '' ) {
		$endpoint = trim( (string) $endpoint, '/' );

		if ( '' !== $endpoint && function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( $endpoint );
		}

		$account_url = trailingslashit( rytkoset_theme_get_my_account_url() );

		return '' === $endpoint ? $account_url : trailingslashit( $account_url . $endpoint );
	}
}

/**
 * Returns true when WooCommerce says the order can still be paid.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_can_retry_payment' ) ) {
	function rytkoset_theme_order_can_retry_payment( $order ) {
		if ( ! $order instanceof WC_Order || ! method_exists( $order, 'needs_payment' ) ) {
			return false;
		}

		return (bool) $order->needs_payment();
	}
}

/**
 * Returns WooCommerce's payment URL for an unpaid order.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_order_payment_retry_url' ) ) {
	function rytkoset_theme_get_order_payment_retry_url( $order ) {
		if ( ! rytkoset_theme_order_can_retry_payment( $order ) || ! method_exists( $order, 'get_checkout_payment_url' ) ) {
			return '';
		}

		$url = (string) $order->get_checkout_payment_url();

		return '' !== $url ? $url : '';
	}
}

/**
 * Adds or relabels the pay action in My Account > Orders for payable orders.
 *
 * WooCommerce normally adds a pay action for payable statuses. This filter makes
 * the action explicit in Finnish and provides the same URL if another plugin has
 * omitted it from the action list.
 *
 * @param array<string, array<string, string>> $actions Order actions.
 * @param WC_Order                             $order   WooCommerce order object.
 * @return array<string, array<string, string>>
 */
if ( ! function_exists( 'rytkoset_theme_add_payment_retry_order_action' ) ) {
	function rytkoset_theme_add_payment_retry_order_action( $actions, $order ) {
		$url = rytkoset_theme_get_order_payment_retry_url( $order );

		if ( '' === $url ) {
			return $actions;
		}

		$label = __( 'Maksa / yritä uudelleen', 'rytkoset-theme' );

		if ( isset( $actions['pay'] ) && is_array( $actions['pay'] ) ) {
			$actions['pay']['name'] = $label;

			if ( empty( $actions['pay']['url'] ) ) {
				$actions['pay']['url'] = $url;
			}

			return $actions;
		}

		return array(
			'pay' => array(
				'url'  => $url,
				'name' => $label,
			),
		) + $actions;
	}
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'rytkoset_theme_add_payment_retry_order_action', 9, 2 );
