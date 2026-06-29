<?php
/**
 * Jäsenyyteen sidottu alennuskuponki (#393).
 *
 * Lisää WooCommercen kuponkiin "Vain jäsenille" -valinnan ja validointikoukun, joka
 * hyväksyy jäsenkupongin vain kirjautuneelle käyttäjälle, jolla on aktiivinen jäsenyys
 * (rytkoset_theme_user_is_active_member(), #301). Tuoterajaukset, alennusprosentti ja
 * voimassaolo hoidetaan WooCommercen omilla kuponkiasetuksilla — teema lisää vain
 * jäsenyysvalidoinnin. Validointi kulkee WC_Discounts-luokan kautta, joten se pätee sekä
 * ostoskorissa että kassalla (myös Checkout Block / Store API).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the coupon meta key used to mark a coupon as members-only.
 *
 * @return string
 */
function rytkoset_theme_get_member_coupon_meta_key() {
	return '_rytkoset_member_coupon';
}

/**
 * Returns the Finnish error shown when a non-member tries to use a member coupon.
 *
 * @return string
 */
function rytkoset_theme_get_member_coupon_error_message() {
	return __( 'Tämä alennuskoodi on tarkoitettu vain sukuseuran jäsenille. Kirjaudu sisään tai liity jäseneksi käyttääksesi koodia.', 'rytkoset-theme' );
}

/**
 * Returns true when the coupon is marked as members-only.
 *
 * @param WC_Coupon|mixed $coupon WooCommerce coupon object.
 * @return bool
 */
function rytkoset_theme_coupon_requires_active_membership( $coupon ) {
	if ( ! $coupon instanceof WC_Coupon ) {
		return false;
	}

	return 'yes' === $coupon->get_meta( rytkoset_theme_get_member_coupon_meta_key() );
}

/**
 * Renders the "members-only" checkbox in the coupon Usage restriction panel.
 *
 * @param int             $coupon_id Coupon post ID.
 * @param WC_Coupon|mixed $coupon    WooCommerce coupon object.
 * @return void
 */
function rytkoset_theme_render_member_coupon_field( $coupon_id, $coupon ) {
	echo '<div class="options_group">';

	woocommerce_wp_checkbox(
		array(
			'id'          => rytkoset_theme_get_member_coupon_meta_key(),
			'label'       => __( 'Vain jäsenille', 'rytkoset-theme' ),
			'description' => __( 'Kuponki kelpaa vain kirjautuneelle käyttäjälle, jolla on voimassa oleva jäsenyys. Muille näytetään suomenkielinen virheilmoitus.', 'rytkoset-theme' ),
			'value'       => $coupon instanceof WC_Coupon ? $coupon->get_meta( rytkoset_theme_get_member_coupon_meta_key() ) : '',
		)
	);

	echo '</div>';
}
add_action( 'woocommerce_coupon_options_usage_restriction', 'rytkoset_theme_render_member_coupon_field', 10, 2 );

/**
 * Saves the "members-only" coupon setting.
 *
 * WooCommerce saves the coupon object before firing this hook, so the value is persisted
 * directly with update_post_meta() on the shop_coupon post.
 *
 * @param int             $post_id Coupon post ID.
 * @param WC_Coupon|mixed $coupon  WooCommerce coupon object (unused).
 * @return void
 */
function rytkoset_theme_save_member_coupon_field( $post_id, $coupon ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies woocommerce_meta_nonce before this hook runs.
	$enabled = isset( $_POST[ rytkoset_theme_get_member_coupon_meta_key() ] ) ? 'yes' : 'no';

	update_post_meta( $post_id, rytkoset_theme_get_member_coupon_meta_key(), $enabled );
}
add_action( 'woocommerce_coupon_options_save', 'rytkoset_theme_save_member_coupon_field', 10, 2 );

/**
 * Blocks member coupons for everyone except logged-in active members.
 *
 * Hooked into WC_Discounts validation, so it covers the cart, the classic checkout and the
 * Checkout Block / Store API. Throwing here is caught by WC_Discounts::is_coupon_valid() and
 * surfaced to the customer as the thrown message.
 *
 * @param bool            $valid     Current validity.
 * @param WC_Coupon|mixed $coupon    Coupon being validated.
 * @param mixed           $discounts WC_Discounts instance (unused).
 * @return bool
 * @throws Exception When a member coupon is used without an active membership.
 */
function rytkoset_theme_validate_member_coupon( $valid, $coupon, $discounts ) {
	if ( ! rytkoset_theme_coupon_requires_active_membership( $coupon ) ) {
		return $valid;
	}

	if ( is_user_logged_in() && rytkoset_theme_user_is_active_member() ) {
		return $valid;
	}

	throw new Exception( esc_html( rytkoset_theme_get_member_coupon_error_message() ) );
}
add_filter( 'woocommerce_coupon_is_valid', 'rytkoset_theme_validate_member_coupon', 10, 3 );
