<?php
/**
 * Tampere 2026 -bussikyyti: erillinen WooCommerce-lisätuote (Savo–Tampere–Savo).
 *
 * Itsenäisesti ostettava variaatiotuote, jossa variaatio = lähtöpaikka ja koko bussin
 * kapasiteetti on jaettu kaikkien lähtöpaikkojen kesken (parent-tason varastosaldo).
 * Uudelleenkäyttää Tampere 2026 -tuotteen määräpäivä-/kapasiteettiportin viestit ja
 * normalisoinnin, mutta EI aktivoi Tampere 2026 -osallistujakenttiä eikä -admin-saraketta:
 * bussituote tunnistetaan omalla SKU:lla eikä se täsmää Tampere-osallistujatuotteeseen.
 *
 * Matkan toteutuminen (riittävä lähtijämäärä) ja mahdollinen hyvitys hoidetaan
 * operatiivisesti määräpäivän jälkeen — WooCommercessa ei ole tälle natiivia kynnystä.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the SKU used to identify the Tampere 2026 bus transport product.
 *
 * @return string
 */
function rytkoset_theme_get_bus_transport_sku() {
	return 'tampere-2026-bussikyyti';
}

/**
 * Returns true when a product is the Tampere 2026 bus transport product or one of its variations.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_bus_transport_product( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$sku = rytkoset_theme_get_bus_transport_sku();

	if ( $sku === (string) $product->get_sku() ) {
		return true;
	}

	$parent_id = $product->get_parent_id();

	if ( $parent_id <= 0 ) {
		return false;
	}

	$parent = wc_get_product( $parent_id );

	return $parent instanceof WC_Product && $sku === (string) $parent->get_sku();
}

/**
 * Returns the parent bus transport product for a product or variation.
 *
 * The deadline meta and shared capacity live on the parent product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return WC_Product|null
 */
function rytkoset_theme_get_bus_transport_parent_product( $product ) {
	if ( ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return null;
	}

	if ( $product->get_parent_id() > 0 ) {
		$parent = wc_get_product( $product->get_parent_id() );

		if ( $parent instanceof WC_Product ) {
			return $parent;
		}
	}

	return $product;
}

/**
 * Returns the product meta key used for the bus transport registration deadline.
 *
 * Shares the Tampere 2026 deadline meta key so the same admin date field renders.
 *
 * @return string
 */
function rytkoset_theme_get_bus_transport_deadline_meta_key() {
	return '_rytkoset_registration_deadline';
}

/**
 * Returns the default registration deadline date for the bus transport product.
 *
 * @return string
 */
function rytkoset_theme_get_bus_transport_default_deadline() {
	return '2026-07-30';
}

/**
 * Returns the registration deadline date for the bus transport product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_bus_transport_deadline( $product ) {
	$parent = rytkoset_theme_get_bus_transport_parent_product( $product );

	if ( ! $parent instanceof WC_Product ) {
		return '';
	}

	$stored   = (string) $parent->get_meta( rytkoset_theme_get_bus_transport_deadline_meta_key(), true );
	$deadline = rytkoset_theme_normalize_registration_deadline_date( $stored );

	if ( '' !== $deadline ) {
		return $deadline;
	}

	return rytkoset_theme_get_bus_transport_default_deadline();
}

/**
 * Returns true when the bus transport registration deadline has passed.
 *
 * The product remains purchasable until the end of the configured day.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_bus_transport_deadline_passed( $product ) {
	$deadline = rytkoset_theme_get_bus_transport_deadline( $product );

	if ( '' === $deadline ) {
		return false;
	}

	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline, wp_timezone() );

	if ( ! $date ) {
		return false;
	}

	$cutoff = $date->modify( '+1 day' )->setTime( 0, 0, 0 );

	return current_datetime() >= $cutoff;
}

/**
 * Returns true when the bus transport product is full.
 *
 * Capacity is based on the shared (parent-level) WooCommerce stock quantity.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_bus_transport_full( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	if ( $product->backorders_allowed() ) {
		return false;
	}

	return ! $product->is_in_stock();
}

/**
 * Returns the current unavailability reason for the bus transport product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_bus_transport_unavailability_reason( $product ) {
	if ( ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return '';
	}

	if ( rytkoset_theme_is_bus_transport_deadline_passed( $product ) ) {
		return 'deadline';
	}

	if ( rytkoset_theme_is_bus_transport_full( $product ) ) {
		return 'full';
	}

	return '';
}

/**
 * Returns the customer-facing unavailability message for the bus transport product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_bus_transport_unavailability_message( $product ) {
	$reason = rytkoset_theme_get_bus_transport_unavailability_reason( $product );

	if ( 'deadline' === $reason ) {
		return __( 'Ilmoittautuminen on päättynyt.', 'rytkoset-theme' );
	}

	if ( 'full' === $reason ) {
		return __( 'Ilmoittautuminen on täynnä.', 'rytkoset-theme' );
	}

	return '';
}

/**
 * Adds the bus transport deadline field to the WooCommerce product inventory tab.
 *
 * @return void
 */
function rytkoset_theme_render_bus_transport_product_management_fields() {
	global $post;

	if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
		return;
	}

	$product = wc_get_product( $post->ID );

	if ( ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return;
	}

	echo '<div class="options_group">';

	woocommerce_wp_text_input(
		array(
			'id'          => rytkoset_theme_get_bus_transport_deadline_meta_key(),
			'label'       => __( 'Ilmoittautumisen määräpäivä', 'rytkoset-theme' ),
			'description' => __( 'Bussikyydin kapasiteetti tulee tämän tuotteen varastosaldosta (koko bussi, jaettu lähtöpaikkojen kesken). Ota varastonhallinta käyttöön parent-tasolla, aseta paikkamäärä Stock quantity -kenttään ja pidä backorders pois päältä.', 'rytkoset-theme' ),
			'desc_tip'    => false,
			'type'        => 'date',
			'value'       => rytkoset_theme_get_bus_transport_deadline( $product ),
		),
		$product
	);

	echo '</div>';
}
add_action( 'woocommerce_product_options_inventory_product_data', 'rytkoset_theme_render_bus_transport_product_management_fields' );

/**
 * Saves the bus transport deadline field.
 *
 * @param WC_Product $product WooCommerce product object.
 * @return void
 */
function rytkoset_theme_save_bus_transport_product_management_fields( $product ) {
	if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product edit nonce before this hook runs.
	$raw_deadline = isset( $_POST[ rytkoset_theme_get_bus_transport_deadline_meta_key() ] )
		? sanitize_text_field( wp_unslash( $_POST[ rytkoset_theme_get_bus_transport_deadline_meta_key() ] ) )
		: '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	$deadline = rytkoset_theme_normalize_registration_deadline_date( $raw_deadline );

	if ( '' === $deadline ) {
		$deadline = rytkoset_theme_get_bus_transport_default_deadline();
	}

	$product->update_meta_data( rytkoset_theme_get_bus_transport_deadline_meta_key(), $deadline );
}
add_action( 'woocommerce_admin_process_product_object', 'rytkoset_theme_save_bus_transport_product_management_fields' );

/**
 * Filters purchasability for the bus transport product.
 *
 * @param bool            $is_purchasable Current purchasable state.
 * @param WC_Product|null $product        WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_filter_bus_transport_product_purchasability( $is_purchasable, $product ) {
	if ( ! $is_purchasable || ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return $is_purchasable;
	}

	return '' === rytkoset_theme_get_bus_transport_unavailability_reason( $product );
}
add_filter( 'woocommerce_is_purchasable', 'rytkoset_theme_filter_bus_transport_product_purchasability', 10, 2 );

/**
 * Filters availability text for the bus transport product.
 *
 * @param string          $availability Availability text.
 * @param WC_Product|null $product      WooCommerce product object.
 * @return string
 */
function rytkoset_theme_filter_bus_transport_product_availability_text( $availability, $product ) {
	if ( ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return $availability;
	}

	$message = rytkoset_theme_get_bus_transport_unavailability_message( $product );

	return '' !== $message ? $message : $availability;
}
add_filter( 'woocommerce_get_availability_text', 'rytkoset_theme_filter_bus_transport_product_availability_text', 10, 2 );

/**
 * Renders an explicit status message on the bus transport product page when needed.
 *
 * @return void
 */
function rytkoset_theme_render_bus_transport_product_page_notice() {
	if ( ! is_product() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return;
	}

	$message = rytkoset_theme_get_bus_transport_unavailability_message( $product );

	if ( '' === $message ) {
		return;
	}

	printf(
		'<div class="woocommerce-info rytkoset-bus-transport-product-notice">%s</div>',
		esc_html( $message )
	);
}
add_action( 'woocommerce_single_product_summary', 'rytkoset_theme_render_bus_transport_product_page_notice', 25 );

/**
 * Prevents adding an unavailable bus transport seat to the cart.
 *
 * @param bool $passed     Whether add to cart should proceed.
 * @param int  $product_id Product ID.
 * @return bool
 */
function rytkoset_theme_validate_bus_transport_add_to_cart( $passed, $product_id ) {
	$product = wc_get_product( $product_id );

	if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_bus_transport_product( $product ) ) {
		return $passed;
	}

	$message = rytkoset_theme_get_bus_transport_unavailability_message( $product );

	if ( '' === $message ) {
		return $passed;
	}

	if ( ! wc_has_notice( $message, 'error' ) ) {
		wc_add_notice( $message, 'error' );
	}

	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'rytkoset_theme_validate_bus_transport_add_to_cart', 10, 2 );

/**
 * Validates bus transport seats already present in the cart or checkout.
 *
 * @return void
 */
function rytkoset_theme_validate_bus_transport_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$messages = array();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_bus_transport_product( $product ) ) {
			continue;
		}

		$message = rytkoset_theme_get_bus_transport_unavailability_message( $product );

		if ( '' !== $message ) {
			$messages[ $message ] = true;
		}
	}

	foreach ( array_keys( $messages ) as $message ) {
		if ( ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}
}
add_action( 'woocommerce_check_cart_items', 'rytkoset_theme_validate_bus_transport_cart_items' );
