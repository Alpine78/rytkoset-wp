<?php
/**
 * Tampere 2026 -osallistumismaksu: WooCommerce-tuotteen logiikka, kassan osallistujakentät,
 * tilausnäkymät, järjestäjäilmoitukset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the SKU used to identify the Tampere 2026 registration product.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_sku() {
	return 'tampere-2026-osallistumismaksu';
}

/**
 * Returns true when a product is the Tampere 2026 registration product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_tampere_2026_registration_product( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$registration_mode = $product->get_meta( '_rytkoset_registration_mode', true );

	if ( 'tampere_2026' === $registration_mode ) {
		return true;
	}

	if ( rytkoset_theme_get_tampere_2026_registration_sku() === (string) $product->get_sku() ) {
		return true;
	}

	$parent_id = $product->get_parent_id();

	if ( $parent_id <= 0 ) {
		return false;
	}

	$parent = wc_get_product( $parent_id );

	if ( ! $parent instanceof WC_Product ) {
		return false;
	}

	return 'tampere_2026' === $parent->get_meta( '_rytkoset_registration_mode', true )
		|| rytkoset_theme_get_tampere_2026_registration_sku() === (string) $parent->get_sku();
}

/**
 * Returns the parent registration product for a Tampere 2026 product or variation.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return WC_Product|null
 */
function rytkoset_theme_get_tampere_2026_registration_parent_product( $product ) {
	if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return null;
	}

	if ( $product instanceof WC_Product && $product->get_parent_id() > 0 ) {
		$parent = wc_get_product( $product->get_parent_id() );

		if ( $parent instanceof WC_Product ) {
			return $parent;
		}
	}

	return $product instanceof WC_Product ? $product : null;
}

/**
 * Returns the product meta key used for the Tampere 2026 registration deadline.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_deadline_meta_key() {
	return '_rytkoset_registration_deadline';
}

/**
 * Returns the default registration deadline date for Tampere 2026.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_default_deadline() {
	return '2026-07-30';
}

/**
 * Normalizes a registration deadline date into Y-m-d format.
 *
 * @param string $raw_date Raw date value.
 * @return string
 */
function rytkoset_theme_normalize_registration_deadline_date( $raw_date ) {
	$raw_date = trim( (string) $raw_date );

	if ( '' === $raw_date ) {
		return '';
	}

	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw_date, wp_timezone() );

	if ( ! $date ) {
		return '';
	}

	return $date->format( 'Y-m-d' );
}

/**
 * Returns the registration deadline date for the Tampere 2026 product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_deadline( $product ) {
	if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return '';
	}

	$deadline_product = rytkoset_theme_get_tampere_2026_registration_parent_product( $product );
	$stored_deadline  = $deadline_product instanceof WC_Product
		? $deadline_product->get_meta( rytkoset_theme_get_tampere_2026_registration_deadline_meta_key(), true )
		: $product->get_meta( rytkoset_theme_get_tampere_2026_registration_deadline_meta_key(), true );
	$deadline         = rytkoset_theme_normalize_registration_deadline_date( (string) $stored_deadline );

	if ( '' !== $deadline ) {
		return $deadline;
	}

	return rytkoset_theme_get_tampere_2026_registration_default_deadline();
}

/**
 * Returns the deadline cutoff timestamp for the Tampere 2026 product.
 *
 * The product remains purchasable until the end of the configured day.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return DateTimeImmutable|null
 */
function rytkoset_theme_get_tampere_2026_registration_deadline_cutoff( $product ) {
	$deadline = rytkoset_theme_get_tampere_2026_registration_deadline( $product );

	if ( '' === $deadline ) {
		return null;
	}

	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline, wp_timezone() );

	if ( ! $date ) {
		return null;
	}

	return $date->modify( '+1 day' )->setTime( 0, 0, 0 );
}

/**
 * Returns true when the Tampere 2026 registration deadline has passed.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_tampere_2026_registration_deadline_passed( $product ) {
	$cutoff = rytkoset_theme_get_tampere_2026_registration_deadline_cutoff( $product );

	if ( ! $cutoff instanceof \DateTimeImmutable ) {
		return false;
	}

	return current_datetime() >= $cutoff;
}

/**
 * Returns true when the Tampere 2026 registration product is full.
 *
 * Capacity is based on WooCommerce stock quantity.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_tampere_2026_registration_full( $product ) {
	if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return false;
	}

	if ( $product->backorders_allowed() ) {
		return false;
	}

	return ! $product->is_in_stock();
}

/**
 * Returns the current unavailability reason for the Tampere 2026 product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_unavailability_reason( $product ) {
	if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return '';
	}

	if ( rytkoset_theme_is_tampere_2026_registration_deadline_passed( $product ) ) {
		return 'deadline';
	}

	if ( rytkoset_theme_is_tampere_2026_registration_full( $product ) ) {
		return 'full';
	}

	return '';
}

/**
 * Returns the customer-facing unavailability message for the Tampere 2026 product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_unavailability_message( $product ) {
	$reason = rytkoset_theme_get_tampere_2026_registration_unavailability_reason( $product );

	if ( 'deadline' === $reason ) {
		return __( 'Ilmoittautuminen on päättynyt.', 'rytkoset-theme' );
	}

	if ( 'full' === $reason ) {
		return __( 'Ilmoittautuminen on täynnä.', 'rytkoset-theme' );
	}

	return '';
}

/**
 * Adds Tampere 2026 management fields to the WooCommerce product inventory tab.
 *
 * @return void
 */
function rytkoset_theme_render_tampere_2026_product_management_fields() {
	global $post;

	if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
		return;
	}

	$product = wc_get_product( $post->ID );

	if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return;
	}

	echo '<div class="options_group">';

	woocommerce_wp_text_input(
		array(
			'id'          => rytkoset_theme_get_tampere_2026_registration_deadline_meta_key(),
			'label'       => __( 'Ilmoittautumisen määräpäivä', 'rytkoset-theme' ),
			'description' => __( 'Kapasiteetti tulee tämän tuotteen varastosaldosta. Ota varastonhallinta käyttöön, aseta osallistujapaikkojen määrä Stock quantity -kenttään ja pidä backorders pois päältä.', 'rytkoset-theme' ),
			'desc_tip'    => false,
			'type'        => 'date',
			'value'       => rytkoset_theme_get_tampere_2026_registration_deadline( $product ),
		),
		$product
	);

	echo '</div>';
}
add_action( 'woocommerce_product_options_inventory_product_data', 'rytkoset_theme_render_tampere_2026_product_management_fields' );

/**
 * Saves Tampere 2026 product management settings.
 *
 * @param WC_Product $product WooCommerce product object.
 * @return void
 */
function rytkoset_theme_save_tampere_2026_product_management_fields( $product ) {
	if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product edit nonce before this hook runs.
	$raw_deadline = isset( $_POST[ rytkoset_theme_get_tampere_2026_registration_deadline_meta_key() ] )
		? sanitize_text_field( wp_unslash( $_POST[ rytkoset_theme_get_tampere_2026_registration_deadline_meta_key() ] ) )
		: '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	$deadline = rytkoset_theme_normalize_registration_deadline_date( $raw_deadline );

	if ( '' === $deadline ) {
		$deadline = rytkoset_theme_get_tampere_2026_registration_default_deadline();
	}

	$product->update_meta_data( rytkoset_theme_get_tampere_2026_registration_deadline_meta_key(), $deadline );
}
add_action( 'woocommerce_admin_process_product_object', 'rytkoset_theme_save_tampere_2026_product_management_fields' );

/**
 * Returns the Tampere 2026 participant count from the current cart.
 *
 * @return int
 */
function rytkoset_theme_get_tampere_2026_participant_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	$participant_count = 0;

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$participant_count += isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
	}

	return max( 0, $participant_count );
}

/**
 * Returns true when the current cart contains Tampere 2026 registrations.
 *
 * @return bool
 */
function rytkoset_theme_cart_has_tampere_2026_registration() {
	return rytkoset_theme_get_tampere_2026_participant_count() > 0;
}

/**
 * Builds the checkout notice shown for Tampere 2026 registrations.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_checkout_notice_markup() {
	$notice_text = html_entity_decode(
		'<strong>Tampere 2026 sukukokous:</strong> Täytä jokaiselle osallistujalle nimi, mahdolliset ruokarajoitteet tai allergiat sekä perjantain buffet-illallisen valinta kohdassa Tilauksen lisätiedot.',
		ENT_QUOTES,
		'UTF-8'
	);

	return sprintf(
		'<div class="rytkoset-checkout-note" role="note"><p>%s</p></div>',
		wp_kses_post( $notice_text )
	);
}

/**
 * Filters purchasability for the Tampere 2026 registration product.
 *
 * @param bool            $is_purchasable Current purchasable state.
 * @param WC_Product|null $product        WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_filter_tampere_2026_product_purchasability( $is_purchasable, $product ) {
	if ( ! $is_purchasable || ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return $is_purchasable;
	}

	return '' === rytkoset_theme_get_tampere_2026_registration_unavailability_reason( $product );
}
add_filter( 'woocommerce_is_purchasable', 'rytkoset_theme_filter_tampere_2026_product_purchasability', 10, 2 );

/**
 * Filters availability text for the Tampere 2026 registration product.
 *
 * @param string          $availability Availability text.
 * @param WC_Product|null $product      WooCommerce product object.
 * @return string
 */
function rytkoset_theme_filter_tampere_2026_product_availability_text( $availability, $product ) {
	if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return $availability;
	}

	$message = rytkoset_theme_get_tampere_2026_registration_unavailability_message( $product );

	return '' !== $message ? $message : $availability;
}
add_filter( 'woocommerce_get_availability_text', 'rytkoset_theme_filter_tampere_2026_product_availability_text', 10, 2 );

/**
 * Renders an explicit status message on the Tampere 2026 product page when needed.
 *
 * @return void
 */
function rytkoset_theme_render_tampere_2026_product_page_notice() {
	if ( ! is_product() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$message = rytkoset_theme_get_tampere_2026_registration_unavailability_message( $product );

	if ( '' === $message ) {
		return;
	}

	printf(
		'<div class="woocommerce-info rytkoset-tampere-2026-product-notice">%s</div>',
		esc_html( $message )
	);
}
add_action( 'woocommerce_single_product_summary', 'rytkoset_theme_render_tampere_2026_product_page_notice', 25 );

/**
 * Prevents adding unavailable Tampere 2026 registrations to the cart.
 *
 * @param bool $passed     Whether add to cart should proceed.
 * @param int  $product_id Product ID.
 * @return bool
 */
function rytkoset_theme_validate_tampere_2026_add_to_cart( $passed, $product_id ) {
	$product = wc_get_product( $product_id );

	if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
		return $passed;
	}

	$message = rytkoset_theme_get_tampere_2026_registration_unavailability_message( $product );

	if ( '' === $message ) {
		return $passed;
	}

	if ( ! wc_has_notice( $message, 'error' ) ) {
		wc_add_notice( $message, 'error' );
	}

	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'rytkoset_theme_validate_tampere_2026_add_to_cart', 10, 2 );

/**
 * Validates Tampere 2026 registrations already present in cart or checkout.
 *
 * @return void
 */
function rytkoset_theme_validate_tampere_2026_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$messages = array();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$message = rytkoset_theme_get_tampere_2026_registration_unavailability_message( $product );

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
add_action( 'woocommerce_check_cart_items', 'rytkoset_theme_validate_tampere_2026_cart_items' );

/**
 * Returns the maximum number of Tampere 2026 participants supported in one order.
 *
 * @return int
 */
function rytkoset_theme_get_tampere_2026_max_participants() {
	return 10;
}

/**
 * Returns the Store API extension namespace for Tampere 2026 cart data.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_store_api_namespace() {
	return 'rytkoset_tampere_2026';
}

/**
 * Builds Tampere 2026 participant lines (type + unit price) from cart items (#520).
 *
 * One line per participant in the same order the participant checkout fields
 * are indexed (cart item order, quantity expanded), so line N describes
 * participant N. Used by the checkout participant card UI.
 *
 * @param array<int|string, array<string, mixed>> $cart_items WooCommerce cart items.
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_build_tampere_2026_cart_participant_lines( $cart_items ) {
	$lines = array();

	foreach ( $cart_items as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$type_label = rytkoset_theme_get_tampere_2026_participant_type_label( $product );
		$price      = html_entity_decode(
			wp_strip_all_tags( wc_price( wc_get_price_including_tax( $product ) ) ),
			ENT_QUOTES,
			'UTF-8'
		);
		$quantity   = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;

		for ( $i = 0; $i < $quantity; $i++ ) {
			$lines[] = array(
				'type'  => $type_label,
				'price' => $price,
			);
		}
	}

	return $lines;
}

/**
 * Returns Tampere 2026 participant lines for the current cart (#520).
 *
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_get_tampere_2026_cart_participant_lines() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	return rytkoset_theme_build_tampere_2026_cart_participant_lines( WC()->cart->get_cart() );
}

/**
 * Returns Tampere 2026 cart data for the WooCommerce Store API.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_store_api_cart_data() {
	return array(
		'participant_count' => rytkoset_theme_get_tampere_2026_participant_count(),
		'participants'      => rytkoset_theme_get_tampere_2026_cart_participant_lines(),
	);
}

/**
 * Returns the schema for Tampere 2026 Store API cart data.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_store_api_cart_schema() {
	return array(
		'participant_count' => array(
			'description' => __( 'Tampere 2026 -osallistujien määrä ostoskorissa.', 'rytkoset-theme' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'readonly'    => true,
		),
		'participants'      => array(
			'description' => __( 'Tampere 2026 -osallistujarivit (osallistujatyyppi ja hinta) ostoskorissa.', 'rytkoset-theme' ),
			'type'        => 'array',
			'readonly'    => true,
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'type'  => array( 'type' => 'string' ),
					'price' => array( 'type' => 'string' ),
				),
			),
		),
	);
}

/**
 * Registers Tampere 2026 cart data for Checkout Block conditions.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_store_api_cart_data() {
	if (
		! function_exists( 'woocommerce_store_api_register_endpoint_data' )
		|| ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' )
	) {
		return;
	}

	woocommerce_store_api_register_endpoint_data(
		array(
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace'       => rytkoset_theme_get_tampere_2026_store_api_namespace(),
			'data_callback'   => 'rytkoset_theme_get_tampere_2026_store_api_cart_data',
			'schema_callback' => 'rytkoset_theme_get_tampere_2026_store_api_cart_schema',
			'schema_type'     => ARRAY_A,
		)
	);
}

/**
 * Registers Store API data after WooCommerce Blocks is available.
 */
if ( did_action( 'woocommerce_blocks_loaded' ) ) {
	rytkoset_theme_register_tampere_2026_store_api_cart_data();
} else {
	add_action( 'woocommerce_blocks_loaded', 'rytkoset_theme_register_tampere_2026_store_api_cart_data' );
}

/**
 * Enqueues the participant card header UI for the Tampere 2026 checkout (#520).
 *
 * Injects a numbered header with the participant type and unit price above
 * each participant's field group, using the participant lines published in
 * the Store API cart extension.
 *
 * @return void
 */
function rytkoset_theme_enqueue_tampere_2026_checkout_participants() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	if ( ! rytkoset_theme_cart_has_tampere_2026_registration() ) {
		return;
	}

	$script_path = '/assets/js/tampere-checkout-participants.js';

	wp_enqueue_script(
		'rytkoset-tampere-checkout-participants',
		get_template_directory_uri() . $script_path,
		array( 'wp-data' ),
		rytkoset_theme_get_asset_version( get_template_directory() . $script_path ),
		true
	);

	$config = array(
		'namespace' => rytkoset_theme_get_tampere_2026_store_api_namespace(),
		'i18n'      => array(
			/* translators: %d: participant number. */
			'title'   => __( 'Osallistuja %d', 'rytkoset-theme' ),
			'heading' => __( 'Osallistujat — Tampere 2026', 'rytkoset-theme' ),
			'intro'   => __( 'Täytä jokaiselle osallistujalle nimi, mahdolliset ruokarajoitteet tai allergiat sekä perjantain buffet-illallisen valinta.', 'rytkoset-theme' ),
		),
	);

	wp_add_inline_script(
		'rytkoset-tampere-checkout-participants',
		'window.rytkosetTampereParticipants = ' . wp_json_encode( $config ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_enqueue_tampere_2026_checkout_participants' );

/**
 * Returns a JSON Schema fragment that matches an active participant field.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_active_schema( $index ) {
	$namespace = rytkoset_theme_get_tampere_2026_store_api_namespace();

	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'type'       => 'object',
				'properties' => array(
					'extensions' => array(
						'type'       => 'object',
						'properties' => array(
							$namespace => array(
								'type'       => 'object',
								'properties' => array(
									'participant_count' => array(
										'type'    => 'integer',
										'minimum' => max( 1, (int) $index ),
									),
								),
								'required'   => array( 'participant_count' ),
							),
						),
						'required'   => array( $namespace ),
					),
				),
				'required'   => array( 'extensions' ),
			),
		),
		'required'   => array( 'cart' ),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be required.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_required_schema( $index ) {
	return rytkoset_theme_get_tampere_2026_participant_active_schema( $index );
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be hidden.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ) {
	return array(
		'not' => rytkoset_theme_get_tampere_2026_participant_active_schema( $index ),
	);
}

/**
 * Registers participant fields for the Tampere 2026 registration checkout flow.
 *
 * Uses WooCommerce Blocks' additional checkout fields API so the fields are
 * always registered for Store API submissions and then conditionally shown.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_checkout_fields() {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	$product_id = wc_get_product_id_by_sku( rytkoset_theme_get_tampere_2026_registration_sku() );

	if ( ! $product_id ) {
		return;
	}

	for ( $index = 1; $index <= rytkoset_theme_get_tampere_2026_max_participants(); $index++ ) {
		$name_field_id   = sprintf( 'rytkoset/participant_%d_name', $index );
		$diet_field_id   = sprintf( 'rytkoset/participant_%d_diet', $index );
		$buffet_field_id = sprintf( 'rytkoset/participant_%d_friday_buffet', $index );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $name_field_id,
				/* translators: %d: participant number. */
				'label'             => sprintf( __( 'Osallistuja %d: nimi', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => rytkoset_theme_get_tampere_2026_participant_required_schema( $index ),
				'hidden'            => rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-participant-%d-name new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $diet_field_id,
				/* translators: %d: participant number. */
				'label'             => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat', 'rytkoset-theme' ), $index ),
				/* translators: %d: participant number. */
				'optionalLabel'     => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat (valinnainen)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'hidden'            => rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-participant-%d-diet new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $buffet_field_id,
				/* translators: %d: participant number. */
				'label'             => sprintf( __( 'Osallistuja %d: osallistuu perjantain 28.8. buffet-illalliselle (n. 30 €, maksu paikan päällä)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'checkbox',
				'required'          => false,
				'hidden'            => rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ),
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}
}
add_action( 'woocommerce_init', 'rytkoset_theme_register_tampere_2026_checkout_fields' );

/**
 * Reads an additional checkout field value from an order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param string   $field_id Additional checkout field ID.
 * @return string
 */
function rytkoset_theme_get_order_additional_checkout_field_value( $order, $field_id ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	if (
		class_exists( '\Automattic\WooCommerce\Blocks\Package' )
		&& class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' )
	) {
		$checkout_fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
			\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
		);

		if ( is_object( $checkout_fields ) && method_exists( $checkout_fields, 'get_all_fields_from_object' ) ) {
			$fields = $checkout_fields->get_all_fields_from_object( $order, 'other', true );

			if ( isset( $fields[ $field_id ] ) ) {
				return (string) $fields[ $field_id ];
			}
		}
	}

	return (string) $order->get_meta( '_wc_other/' . $field_id, true );
}

/**
 * Returns true when an additional checkout checkbox field was checked.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param string   $field_id Additional checkout field ID.
 * @return bool
 */
function rytkoset_theme_get_order_additional_checkout_field_bool( $order, $field_id ) {
	$value = rytkoset_theme_get_order_additional_checkout_field_value( $order, $field_id );
	$value = strtolower( trim( (string) $value ) );

	return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Returns the participant type label for a Tampere 2026 variation.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_participant_type_label( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$raw_value = '';

	if ( $product instanceof WC_Product_Variation ) {
		$variation_attributes = $product->get_variation_attributes();

		foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
			if ( false !== strpos( (string) $attribute_name, 'osallistujatyyppi' ) ) {
				$raw_value = (string) $attribute_value;
				break;
			}
		}
	}

	if ( '' === $raw_value ) {
		$raw_value = (string) $product->get_attribute( 'osallistujatyyppi' );
	}

	if ( '' === $raw_value ) {
		$raw_value = (string) $product->get_attribute( 'pa_osallistujatyyppi' );
	}

	if ( '' === $raw_value ) {
		return '';
	}

	$taxonomy = taxonomy_exists( 'pa_osallistujatyyppi' ) ? 'pa_osallistujatyyppi' : '';

	if ( '' !== $taxonomy ) {
		$term = get_term_by( 'slug', $raw_value, $taxonomy );

		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->name;
		}
	}

	if ( false !== strpos( $raw_value, ' ' ) ) {
		return wc_clean( $raw_value );
	}

	return wc_clean( str_replace( '-', ' ', $raw_value ) );
}

/**
 * Returns participant type labels in the same order as order-level participant fields.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, string>
 */
function rytkoset_theme_get_tampere_2026_order_participant_type_sequence( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$types = array();

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$type_label = rytkoset_theme_get_tampere_2026_participant_type_label( $product );

		for ( $i = 0; $i < (int) $item->get_quantity(); $i++ ) {
			$types[] = $type_label;
		}
	}

	return $types;
}

/**
 * Returns Tampere 2026 participant data saved on an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_get_tampere_2026_order_participants( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$participant_count = 0;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$participant_count += (int) $item->get_quantity();
	}

	if ( $participant_count < 1 ) {
		return array();
	}

	$participants      = array();
	$participant_types = rytkoset_theme_get_tampere_2026_order_participant_type_sequence( $order );

	for ( $index = 1; $index <= $participant_count; $index++ ) {
		$name   = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_name', $index )
			)
		);
		$diet   = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_diet', $index )
			)
		);
		$buffet = rytkoset_theme_get_order_additional_checkout_field_bool(
			$order,
			sprintf( 'rytkoset/participant_%d_friday_buffet', $index )
		);
		$type   = isset( $participant_types[ $index - 1 ] ) ? (string) $participant_types[ $index - 1 ] : '';

		if ( '' === $name && '' === $diet && ! $buffet ) {
			continue;
		}

		$participants[] = array(
			'name'             => $name,
			'diet'             => $diet,
			'participant_type' => $type,
			'friday_buffet'    => $buffet,
		);
	}

	return $participants;
}

/**
 * Returns true when an order contains Tampere 2026 registrations.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_is_tampere_2026_registration_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the participant quantity purchased on a Tampere 2026 order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return int
 */
function rytkoset_theme_get_tampere_2026_order_participant_quantity( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return 0;
	}

	$participant_quantity = 0;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$participant_quantity += (int) $item->get_quantity();
	}

	return max( 0, $participant_quantity );
}

/**
 * Returns the highest Tampere 2026 participant field index that should be shown for an order.
 *
 * Non-Tampere orders can still contain stale hidden Store API checkbox meta. Those
 * participant fields are not relevant for the order and should never be displayed.
 *
 * @param WC_Order|null $order WooCommerce order object.
 * @return int
 */
function rytkoset_theme_get_tampere_2026_visible_participant_field_limit( $order ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_tampere_2026_registration_order( $order ) ) {
		return 0;
	}

	return rytkoset_theme_get_tampere_2026_order_participant_quantity( $order );
}

/**
 * Returns the Tampere 2026 participant field IDs for a participant index.
 *
 * @param int $index Participant index.
 * @return array<int, string>
 */
function rytkoset_theme_get_tampere_2026_participant_field_ids( $index ) {
	$index = absint( $index );

	return array(
		sprintf( 'rytkoset/participant_%d_name', $index ),
		sprintf( 'rytkoset/participant_%d_diet', $index ),
		sprintf( 'rytkoset/participant_%d_friday_buffet', $index ),
	);
}

/**
 * Removes WooCommerce's order meta prefix from an additional checkout field ID.
 *
 * @param string $field_id Field ID or order meta key.
 * @return string
 */
function rytkoset_theme_normalize_tampere_2026_participant_field_id( $field_id ) {
	$field_id = (string) $field_id;
	$prefix   = '_wc_other/';

	if ( 0 === strpos( $field_id, $prefix ) ) {
		return substr( $field_id, strlen( $prefix ) );
	}

	return $field_id;
}

/**
 * Parses a Tampere 2026 participant index from an additional checkout field ID.
 *
 * @param string $field_id Field ID or order meta key.
 * @return int Participant index, or 0 when the field is not a Tampere 2026 participant field.
 */
function rytkoset_theme_get_tampere_2026_participant_index_from_field_id( $field_id ) {
	$field_id = rytkoset_theme_normalize_tampere_2026_participant_field_id( $field_id );

	if ( ! preg_match( '/^rytkoset\/participant_(\d+)_(?:name|diet|friday_buffet)$/', $field_id, $matches ) ) {
		return 0;
	}

	return absint( $matches[1] );
}

/**
 * Resolves the original checkout field ID from WooCommerce order confirmation field data.
 *
 * @param array<string, mixed> $field Field data.
 * @param array<string, mixed> $fields All fields in the current confirmation context.
 * @return string
 */
function rytkoset_theme_get_order_confirmation_checkout_field_id( $field, $fields ) {
	if ( isset( $field['id'] ) && is_string( $field['id'] ) ) {
		return $field['id'];
	}

	foreach ( $fields as $field_id => $candidate_field ) {
		if ( $candidate_field === $field ) {
			return (string) $field_id;
		}
	}

	return '';
}

/**
 * Hides extra Tampere 2026 participant fields from order confirmation views and emails.
 *
 * WooCommerce Blocks stores unchecked hidden checkboxes as false, which would otherwise
 * render as "Ei" for participants that were not actually purchased.
 *
 * @param bool                 $show Whether WooCommerce would show the field.
 * @param array<string, mixed> $field Field data.
 * @param array<string, mixed> $fields All fields in the current confirmation context.
 * @param array<string, mixed> $context Confirmation context.
 * @return bool
 */
function rytkoset_theme_filter_tampere_2026_order_confirmation_fields( $show, $field, $fields, $context ) {
	$field_id = rytkoset_theme_get_order_confirmation_checkout_field_id( $field, $fields );
	$index    = rytkoset_theme_get_tampere_2026_participant_index_from_field_id( $field_id );

	if ( $index < 1 ) {
		return $show;
	}

	$order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;

	return $show && $index <= rytkoset_theme_get_tampere_2026_visible_participant_field_limit( $order );
}
add_filter( 'woocommerce_filter_fields_for_order_confirmation', 'rytkoset_theme_filter_tampere_2026_order_confirmation_fields', 10, 4 );

/**
 * Removes extra Tampere 2026 participant fields from WooCommerce admin order fields.
 *
 * @param array<string, mixed> $fields Admin field definitions.
 * @param WC_Order|null        $order Order object.
 * @return array<string, mixed>
 */
function rytkoset_theme_filter_tampere_2026_admin_order_fields( $fields, $order = null ) {
	if ( ! $order instanceof WC_Order ) {
		return $fields;
	}

	$participant_quantity = rytkoset_theme_get_tampere_2026_visible_participant_field_limit( $order );

	foreach ( $fields as $field_key => $field ) {
		$field_id = is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : (string) $field_key;
		$index    = rytkoset_theme_get_tampere_2026_participant_index_from_field_id( $field_id );

		if ( $index > $participant_quantity ) {
			unset( $fields[ $field_key ] );
		}
	}

	return $fields;
}
add_filter( 'woocommerce_admin_shipping_fields', 'rytkoset_theme_filter_tampere_2026_admin_order_fields', 20, 2 );

/**
 * Deletes hidden extra Tampere 2026 participant meta from new Store API orders.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_cleanup_tampere_2026_extra_participant_order_meta( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$participant_quantity = rytkoset_theme_get_tampere_2026_visible_participant_field_limit( $order );
	$max_participants     = rytkoset_theme_get_tampere_2026_max_participants();
	$deleted_meta         = false;

	for ( $index = $participant_quantity + 1; $index <= $max_participants; $index++ ) {
		foreach ( rytkoset_theme_get_tampere_2026_participant_field_ids( $index ) as $field_id ) {
			$meta_key = '_wc_other/' . $field_id;

			if ( ! $order->meta_exists( $meta_key ) ) {
				continue;
			}

			$order->delete_meta_data( $meta_key );
			$deleted_meta = true;
		}
	}

	if ( $deleted_meta ) {
		$order->save();
	}
}
add_action( 'woocommerce_store_api_checkout_order_processed', 'rytkoset_theme_cleanup_tampere_2026_extra_participant_order_meta', 20 );

/**
 * Registers the Tampere 2026 participants metabox for order admin screens.
 *
 * Uses a dedicated metabox so the participant list is visible in both legacy
 * and HPOS order editors.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_order_metabox() {
	if ( ! function_exists( 'wc_get_page_screen_id' ) || ! function_exists( 'wc_get_container' ) ) {
		add_meta_box(
			'rytkoset-tampere-2026-participants',
			__( 'Tampere 2026 osallistujat', 'rytkoset-theme' ),
			'rytkoset_theme_render_tampere_2026_order_participants_metabox',
			'shop_order',
			'side',
			'default'
		);

		return;
	}

	$screen = 'shop_order';

	if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
		$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
		$screen     = $controller->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	add_meta_box(
		'rytkoset-tampere-2026-participants',
		__( 'Tampere 2026 osallistujat', 'rytkoset-theme' ),
		'rytkoset_theme_render_tampere_2026_order_participants_metabox',
		$screen,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'rytkoset_theme_register_tampere_2026_order_metabox' );

/**
 * Renders participant details inside the order admin metabox.
 *
 * @param mixed $post_or_order_object Either a WP_Post or WC_Order.
 * @return void
 */
function rytkoset_theme_render_tampere_2026_order_participants_metabox( $post_or_order_object ) {
	$order = rytkoset_theme_get_order_from_admin_screen_object( $post_or_order_object );

	if ( ! $order instanceof WC_Order ) {
		echo '<p>' . esc_html__( 'Tilausta ei voitu lukea.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	$participants = rytkoset_theme_get_tampere_2026_order_participants( $order );

	if ( empty( $participants ) ) {
		echo '<p>' . esc_html__( 'Tälle tilaukselle ei löytynyt osallistujatietoja.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	echo '<p><strong>' . esc_html__( 'Osallistujia:', 'rytkoset-theme' ) . '</strong> ' . esc_html( (string) count( $participants ) ) . '</p>';
	echo '<ol>';

	foreach ( $participants as $participant ) {
		echo '<li>';
		echo '<strong>' . esc_html( $participant['name'] ) . '</strong>';

		if ( ! empty( $participant['participant_type'] ) ) {
			echo '<br>';
			echo esc_html__( 'Osallistujatyyppi:', 'rytkoset-theme' ) . ' ' . esc_html( $participant['participant_type'] );
		}

		if ( '' !== $participant['diet'] ) {
			echo '<br>';
			echo esc_html__( 'Ruokarajoitteet / allergiat:', 'rytkoset-theme' ) . ' ' . esc_html( $participant['diet'] );
		}

		echo '<br>';
		echo esc_html__( 'Perjantain buffet:', 'rytkoset-theme' ) . ' ';
		echo ! empty( $participant['friday_buffet'] )
			? esc_html__( 'Kyllä', 'rytkoset-theme' )
			: esc_html__( 'Ei', 'rytkoset-theme' );

		echo '</li>';
	}

	echo '</ol>';
}

/**
 * Adds the Tampere 2026 column to WooCommerce order lists.
 *
 * @param array<string, mixed> $columns Existing order list columns.
 * @return array<string, mixed>
 */
function rytkoset_theme_add_tampere_2026_orders_column( $columns ) {
	$new_columns = array();

	foreach ( $columns as $column_name => $column_label ) {
		$new_columns[ $column_name ] = $column_label;

		if ( 'order_status' === $column_name ) {
			$new_columns['rytkoset_tampere_2026'] = __( 'Tampere 2026', 'rytkoset-theme' );
		}
	}

	if ( ! isset( $new_columns['rytkoset_tampere_2026'] ) ) {
		$new_columns['rytkoset_tampere_2026'] = __( 'Tampere 2026', 'rytkoset-theme' );
	}

	return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'rytkoset_theme_add_tampere_2026_orders_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'rytkoset_theme_add_tampere_2026_orders_column' );

/**
 * Renders the Tampere 2026 order list column value.
 *
 * @param string   $column_name Column name.
 * @param WC_Order $order       WooCommerce order object.
 * @return void
 */
function rytkoset_theme_render_tampere_2026_orders_column( $column_name, $order ) {
	if ( 'rytkoset_tampere_2026' !== $column_name ) {
		return;
	}

	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_tampere_2026_registration_order( $order ) ) {
		echo '&mdash;';
		return;
	}

	$participant_quantity = rytkoset_theme_get_tampere_2026_order_participant_quantity( $order );

	if ( $participant_quantity < 1 ) {
		echo '&mdash;';
		return;
	}

	echo esc_html(
		sprintf(
			/* translators: %d: participant count. */
			_n( '%d osallistuja', '%d osallistujaa', $participant_quantity, 'rytkoset-theme' ),
			$participant_quantity
		)
	);
}

/**
 * Legacy renderer wrapper for the Tampere 2026 order list column.
 *
 * @param string $column_name Column name.
 * @return void
 */
function rytkoset_theme_render_tampere_2026_orders_column_legacy( $column_name ) {
	global $the_order;

	if ( ! $the_order instanceof WC_Order ) {
		return;
	}

	rytkoset_theme_render_tampere_2026_orders_column( $column_name, $the_order );
}
add_action( 'manage_shop_order_posts_custom_column', 'rytkoset_theme_render_tampere_2026_orders_column_legacy', 25, 1 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'rytkoset_theme_render_tampere_2026_orders_column', 25, 2 );

/**
 * Returns true when the current site runs in a local or development environment.
 *
 * @return bool
 */
function rytkoset_theme_is_local_or_dev_environment() {
	if ( function_exists( 'wp_get_environment_type' ) ) {
		$environment_type = wp_get_environment_type();

		if ( in_array( $environment_type, array( 'local', 'development' ), true ) ) {
			return true;
		}
	}

	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	if ( ! is_string( $host ) || '' === $host ) {
		return false;
	}

	return in_array(
		strtolower( $host ),
		array( 'localhost', '127.0.0.1', 'dev.rytkoset.net' ),
		true
	);
}

/**
 * Captures the latest wp_mail failure for debugging.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_capture_wp_mail_failure( $error ) {
	$GLOBALS['rytkoset_theme_last_wp_mail_failure'] = $error;
}

/**
 * Formats a wp_mail failure into a readable string.
 *
 * @param WP_Error|null $error wp_mail failure object.
 * @return string
 */
function rytkoset_theme_format_wp_mail_failure( $error ) {
	if ( ! $error instanceof WP_Error ) {
		return __( 'Tarkempaa virhesyyta ei saatu wp_mail-kutsusta.', 'rytkoset-theme' );
	}

	$parts = array();

	foreach ( $error->get_error_codes() as $code ) {
		$messages = $error->get_error_messages( $code );
		$message  = ! empty( $messages ) ? implode( ' | ', $messages ) : __( 'Ei virheviestia.', 'rytkoset-theme' );

		$parts[] = sprintf( '%s: %s', $code, $message );
	}

	return implode( ' || ', $parts );
}

/**
 * Adds a local/dev-only debug note for organizer notification failures.
 *
 * @param WC_Order $order         WooCommerce order object.
 * @param int      $event_id      Event post ID.
 * @param string   $subject       Email subject.
 * @param string   $message       Email body.
 * @param string   $error_summary Formatted wp_mail failure summary.
 * @return void
 */
function rytkoset_theme_add_event_organizer_notification_debug_note( $order, $event_id, $subject, $message, $error_summary ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$event_title = $event_id > 0 ? get_the_title( $event_id ) : '';

	$note_lines = array(
		__( 'Tapahtuman järjestäjäilmoituksen debug (local/dev).', 'rytkoset-theme' ),
		__( 'Tapahtuma:', 'rytkoset-theme' ) . ' ' . ( '' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		__( 'Virhe:', 'rytkoset-theme' ) . ' ' . $error_summary,
		'',
		__( 'Aihe:', 'rytkoset-theme' ) . ' ' . $subject,
		'',
		__( 'Lähetyksen viestisisältö:', 'rytkoset-theme' ),
		$message,
	);

	$order->add_order_note( implode( PHP_EOL, $note_lines ), false );
}

/**
 * Adds a local/dev-only debug note when wp_mail fails for event organizer notifications.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_maybe_log_event_organizer_notification_mail_failure( $error ) {
	if ( ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$context = isset( $GLOBALS['rytkoset_theme_current_event_organizer_notification'] ) && is_array( $GLOBALS['rytkoset_theme_current_event_organizer_notification'] )
		? $GLOBALS['rytkoset_theme_current_event_organizer_notification']
		: null;

	if ( ! is_array( $context ) ) {
		return;
	}

	$order_id = isset( $context['order_id'] ) ? absint( $context['order_id'] ) : 0;
	$event_id = isset( $context['event_id'] ) ? absint( $context['event_id'] ) : 0;
	$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$subject       = isset( $context['subject'] ) ? (string) $context['subject'] : '';
	$message       = isset( $context['message'] ) ? (string) $context['message'] : '';
	$error_summary = rytkoset_theme_format_wp_mail_failure( $error );

	rytkoset_theme_add_event_organizer_notification_debug_note( $order, $event_id, $subject, $message, $error_summary );
}
add_action( 'wp_mail_failed', 'rytkoset_theme_maybe_log_event_organizer_notification_mail_failure' );

/**
 * Returns the edit URL for an order in the current admin configuration.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_order_admin_edit_url( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
		$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );

		if ( is_object( $controller ) && method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) && $controller->custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );
		}
	}

	return admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
}

/**
 * Returns the order meta key used to deduplicate organizer notifications per event.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_sent_at_meta_key( $event_id ) {
	return '_rytkoset_event_organizer_notification_sent_at_' . absint( $event_id );
}

/**
 * Returns the order meta key used to store the recipients used per event.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_recipients_order_meta_key( $event_id ) {
	return '_rytkoset_event_organizer_notification_recipients_' . absint( $event_id );
}

/**
 * Returns product IDs that identify an order item, including variations and parents.
 *
 * @param mixed $item WooCommerce order item.
 * @return array<int, int>
 */
function rytkoset_theme_get_order_item_product_reference_ids( $item ) {
	$ids = array();

	if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
		$ids[] = absint( $item->get_product_id() );
	}

	if ( is_object( $item ) && method_exists( $item, 'get_variation_id' ) ) {
		$ids[] = absint( $item->get_variation_id() );
	}

	$product = is_object( $item ) && method_exists( $item, 'get_product' ) ? $item->get_product() : null;

	if ( $product instanceof WC_Product ) {
		$ids[] = absint( $product->get_id() );
		$ids[] = absint( $product->get_parent_id() );
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Returns product IDs represented in an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, int>
 */
function rytkoset_theme_get_order_product_reference_ids( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$product_ids = array();

	foreach ( $order->get_items() as $item ) {
		$product_ids = array_merge( $product_ids, rytkoset_theme_get_order_item_product_reference_ids( $item ) );
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
}

/**
 * Returns paid events linked to products in an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, int>
 */
function rytkoset_theme_get_order_paid_event_ids( $order ) {
	$product_ids = rytkoset_theme_get_order_product_reference_ids( $order );

	if ( empty( $product_ids ) ) {
		return array();
	}

	$event_ids = get_posts(
		array(
			'post_type'          => 'rytkoset_event',
			'post_status'        => array( 'publish', 'private' ),
			'posts_per_page'     => -1,
			'fields'             => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'     => rytkoset_theme_get_event_product_meta_key(),
							'value'   => $product_ids,
							'compare' => 'IN',
							'type'    => 'NUMERIC',
						),
					),
		)
	);

	return array_values( array_unique( array_map( 'absint', $event_ids ) ) );
}

/**
 * Returns the ordered quantity for the WooCommerce product linked to an event.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return int
 */
function rytkoset_theme_get_order_event_product_quantity( $order, $event_id ) {
	if ( ! $order instanceof WC_Order ) {
		return 0;
	}

	$product_id = absint( get_post_meta( absint( $event_id ), rytkoset_theme_get_event_product_meta_key(), true ) );

	if ( $product_id <= 0 ) {
		return 0;
	}

	$quantity = 0;

	foreach ( $order->get_items() as $item ) {
		if ( ! in_array( $product_id, rytkoset_theme_get_order_item_product_reference_ids( $item ), true ) ) {
			continue;
		}

		$quantity += is_object( $item ) && method_exists( $item, 'get_quantity' ) ? absint( $item->get_quantity() ) : 0;
	}

	return $quantity;
}

/**
 * Returns participant rows for an event organizer notification.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_get_event_order_notification_participants( $order, $event_id ) {
	$product = rytkoset_theme_get_event_linked_product( $event_id );

	if (
		function_exists( 'rytkoset_theme_is_tampere_2026_registration_product' )
		&& function_exists( 'rytkoset_theme_get_tampere_2026_order_participants' )
		&& rytkoset_theme_is_tampere_2026_registration_product( $product )
	) {
		return rytkoset_theme_get_tampere_2026_order_participants( $order );
	}

	$quantity     = max( 1, rytkoset_theme_get_order_event_product_quantity( $order, $event_id ) );
	$contact_name = trim( $order->get_formatted_billing_full_name() );
	$participants = array();

	if ( '' === $contact_name ) {
		$contact_name = __( 'Nimi puuttuu', 'rytkoset-theme' );
	}

	for ( $index = 0; $index < $quantity; $index++ ) {
		$participants[] = array(
			'name'             => $contact_name,
			'email'            => (string) $order->get_billing_email(),
			'phone'            => (string) $order->get_billing_phone(),
			'diet'             => '',
			'participant_type' => '',
			'friday_buffet'    => null,
		);
	}

	return $participants;
}

/**
 * Builds the organizer notification email subject for an event order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_subject( $order, $event_id ) {
	$event_title = wp_strip_all_tags( get_the_title( $event_id ) );

	if ( '' === $event_title ) {
		$event_title = __( 'Tapahtuma', 'rytkoset-theme' );
	}

	return sprintf(
		/* translators: 1: event title, 2: order number. */
		__( 'Tapahtumailmoittautuminen: %1$s / tilaus #%2$s', 'rytkoset-theme' ),
		$event_title,
		$order->get_order_number()
	);
}

/**
 * Builds the organizer notification email body for an event order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_message( $order, $event_id ) {
	$participants   = rytkoset_theme_get_event_order_notification_participants( $order, $event_id );
	$admin_edit_url = rytkoset_theme_get_order_admin_edit_url( $order );
	$event_title    = wp_strip_all_tags( get_the_title( $event_id ) );
	$event_date     = rytkoset_theme_get_event_date_display( $event_id );
	$event_location = rytkoset_theme_get_event_location( $event_id );
	$contact_name   = trim( $order->get_formatted_billing_full_name() );
	$email          = trim( (string) $order->get_billing_email() );
	$phone          = trim( (string) $order->get_billing_phone() );
	$payment_method = trim( (string) $order->get_payment_method_title() );
	$customer_note  = trim( (string) $order->get_customer_note() );
	$created_at     = $order->get_date_created();
	$created_text   = $created_at ? wp_date( 'j.n.Y H:i', $created_at->getTimestamp(), wp_timezone() ) : __( 'Ei tiedossa', 'rytkoset-theme' );
	$status_name    = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();
	$lines          = array(
		'Rytkösten sukuseura / tapahtumailmoittautuminen',
		'',
		'tapahtuma: ' . ( '' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		'tapahtumapäivä: ' . ( '' !== $event_date ? $event_date : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		'paikka: ' . ( '' !== $event_location ? $event_location : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		'',
		'tilausnumero: #' . $order->get_order_number(),
		'päiväys: ' . $created_text,
		'tila: ' . $status_name,
		'maksutapa: ' . ( '' !== $payment_method ? $payment_method : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
	);

	if ( '' !== $admin_edit_url ) {
		$lines[] = 'tilauksen hallinta: ' . $admin_edit_url;
	}

	$lines[] = '';
	$lines[] = 'Yhteyshenkilö:';
	$lines[] = 'nimi: ' . ( '' !== $contact_name ? $contact_name : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = 'sähköposti: ' . ( '' !== $email ? $email : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = 'puhelin: ' . ( '' !== $phone ? $phone : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = '';
	$lines[] = 'Osallistujat:';

	if ( empty( $participants ) ) {
		$lines[] = '- Osallistujatietoja ei löytynyt tilaukselta.';
	} else {
		foreach ( $participants as $index => $participant ) {
			if ( ! is_array( $participant ) ) {
				continue;
			}

			$participant_name = isset( $participant['name'] ) && '' !== $participant['name'] ? (string) $participant['name'] : __( 'Nimi puuttuu', 'rytkoset-theme' );
			$lines[]          = sprintf( '%d. %s', $index + 1, $participant_name );

			if ( ! empty( $participant['participant_type'] ) ) {
				$lines[] = '   osallistujatyyppi: ' . $participant['participant_type'];
			}

			if ( ! empty( $participant['email'] ) ) {
				$lines[] = '   sähköposti: ' . $participant['email'];
			}

			if ( ! empty( $participant['phone'] ) ) {
				$lines[] = '   puhelin: ' . $participant['phone'];
			}

			if ( ! empty( $participant['diet'] ) ) {
				$lines[] = '   ruokarajoitteet / allergiat: ' . $participant['diet'];
			}

			if ( array_key_exists( 'friday_buffet', $participant ) && null !== $participant['friday_buffet'] ) {
				$lines[] = '   perjantain buffet: ' . ( ! empty( $participant['friday_buffet'] ) ? 'kyllä' : 'ei' );
			}
		}
	}

	if ( '' !== $customer_note ) {
		$lines[] = '';
		$lines[] = 'Asiakkaan lisätiedot:';
		$lines[] = $customer_note;
	}

	return implode( PHP_EOL, $lines );
}

/**
 * Sends the organizer notification for a paid event order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_send_event_organizer_notification( $order, $event_id ) {
	$event_id = absint( $event_id );

	if ( ! $order instanceof WC_Order || $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) ) {
		return false;
	}

	$sent_at_meta_key = rytkoset_theme_get_event_organizer_notification_sent_at_meta_key( $event_id );
	$sent_at          = (string) $order->get_meta( $sent_at_meta_key, true );

	if ( '' !== $sent_at ) {
		return false;
	}

	$recipients  = rytkoset_theme_get_event_organizer_notification_recipients( $event_id );
	$event_title = wp_strip_all_tags( get_the_title( $event_id ) );

	if ( empty( $recipients ) ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: event title. */
				__( 'Tapahtuman järjestäjäilmoitusta ei lähetetty tapahtumalle "%s", koska tapahtumalle ei ole asetettu vastaanottajia.', 'rytkoset-theme' ),
				'' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' )
			),
			false
		);
		return false;
	}

	$subject = rytkoset_theme_get_event_organizer_notification_subject( $order, $event_id );
	$message = rytkoset_theme_get_event_organizer_notification_message( $order, $event_id );

	$GLOBALS['rytkoset_theme_current_event_organizer_notification'] = array(
		'order_id' => $order->get_id(),
		'event_id' => $event_id,
		'subject'  => $subject,
		'message'  => $message,
	);

	$sent = wp_mail( $recipients, $subject, $message );

	unset( $GLOBALS['rytkoset_theme_current_event_organizer_notification'] );

	if ( ! $sent ) {
		$order->add_order_note(
			sprintf(
				/* translators: 1: event title, 2: recipient email list. */
				__( 'Tapahtuman järjestäjäilmoituksen lähetys epäonnistui tapahtumalle "%1$s". Vastaanottajat: %2$s', 'rytkoset-theme' ),
				'' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ),
				implode( ', ', $recipients )
			),
			false
		);
		return false;
	}

	$order->update_meta_data( $sent_at_meta_key, current_time( 'mysql' ) );
	$order->update_meta_data( rytkoset_theme_get_event_organizer_notification_recipients_order_meta_key( $event_id ), implode( ', ', $recipients ) );
	$order->save();

	$order->add_order_note(
		sprintf(
			/* translators: 1: event title, 2: recipient email list. */
			__( 'Tapahtuman järjestäjäilmoitus lähetettiin tapahtumalle "%1$s" osoitteisiin: %2$s', 'rytkoset-theme' ),
			'' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ),
			implode( ', ', $recipients )
		),
		false
	);

	return true;
}

/**
 * Sends paid event organizer notifications when an order reaches an active status.
 *
 * @param int      $order_id WooCommerce order ID.
 * @param WC_Order $order    WooCommerce order object.
 * @return void
 */
function rytkoset_theme_maybe_send_event_organizer_notifications( $order_id, $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	foreach ( rytkoset_theme_get_order_paid_event_ids( $order ) as $event_id ) {
		rytkoset_theme_send_event_organizer_notification( $order, $event_id );
	}
}
add_action( 'woocommerce_order_status_on-hold', 'rytkoset_theme_maybe_send_event_organizer_notifications', 10, 2 );
add_action( 'woocommerce_order_status_processing', 'rytkoset_theme_maybe_send_event_organizer_notifications', 10, 2 );
add_action( 'woocommerce_order_status_completed', 'rytkoset_theme_maybe_send_event_organizer_notifications', 10, 2 );
