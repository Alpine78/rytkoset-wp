<?php
/**
 * Prevent late Store API checkout updates from restoring an emptied cart.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a short-lived cart-clear marker key for the active WooCommerce session.
 *
 * @return string Empty when no session is available.
 */
function rytkoset_theme_get_cart_clear_marker_key() {
	$session = function_exists( 'WC' ) && WC() ? WC()->session : null;
	if ( ! $session || ! is_callable( array( $session, 'get_customer_id' ) ) ) {
		return '';
	}
	$customer_id = (string) $session->get_customer_id();
	return '' === $customer_id ? '' : 'rytkoset_cart_cleared_' . hash( 'sha256', $customer_id );
}

/**
 * Clears checkout-only values and schedules the cart-clear marker.
 *
 * WooCommerce saves its session at shutdown priority 20. Marking the clear
 * before that save would miss requests that start while the old cart is still
 * in the database. The marker must live outside the session being overwritten.
 * WooCommerce does not clear the Store API customer note with the cart, so it
 * must be removed here before the next checkout is opened.
 *
 * @return void
 */
function rytkoset_theme_schedule_cart_clear_marker() {
	$session = function_exists( 'WC' ) && WC() ? WC()->session : null;
	if ( $session && is_callable( array( $session, 'set' ) ) ) {
		$session->set( 'store_api_customer_note', null );
	}

	add_action( 'shutdown', 'rytkoset_theme_record_cart_clear_marker', 21 );
}
add_action( 'woocommerce_cart_emptied', 'rytkoset_theme_schedule_cart_clear_marker' );

/**
 * Records a cart clear without storing the customer ID or cart contents.
 *
 * @return void
 */
function rytkoset_theme_record_cart_clear_marker() {
	$key = rytkoset_theme_get_cart_clear_marker_key();
	if ( '' !== $key ) {
		set_transient( $key, microtime( true ), HOUR_IN_SECONDS );
	}
}

/**
 * Guards only checkout background updates, never order submission or cart edits.
 *
 * @param mixed           $response Existing REST response, if any.
 * @param array           $handler  Matched REST handler.
 * @param WP_REST_Request $request  Current REST request.
 * @return mixed
 */
function rytkoset_theme_guard_checkout_session_update( $response, $handler, $request ) {
	if ( '/wc/store/v1/checkout' === $request->get_route() && in_array( $request->get_method(), array( 'PUT', 'PATCH' ), true ) ) {
		add_action( 'shutdown', 'rytkoset_theme_discard_stale_checkout_session_update', 19 );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'rytkoset_theme_guard_checkout_session_update', 10, 3 );

/**
 * Prevents a pre-clear background request from overwriting the newer session.
 *
 * A delayed checkout PUT/PATCH can finish after the thank-you page has emptied
 * the cart. WC_Session_Handler then saves its entire old in-memory snapshot,
 * resurrecting the purchased items. Discard only that stale session write;
 * do not empty the cart again, as a new purchase may already have started.
 *
 * @return void
 */
function rytkoset_theme_discard_stale_checkout_session_update() {
	$key     = rytkoset_theme_get_cart_clear_marker_key();
	$started = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : 0;
	if ( '' === $key || $started <= 0 ) {
		return;
	}
	$cleared = (float) get_transient( $key );
	if ( $cleared > $started ) {
		remove_action( 'shutdown', array( WC()->session, 'save_data' ), 20 );
	}
}
