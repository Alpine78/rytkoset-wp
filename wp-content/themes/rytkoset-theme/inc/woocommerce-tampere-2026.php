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

	return rytkoset_theme_get_tampere_2026_registration_sku() === (string) $product->get_sku();
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

	$stored_deadline = $product->get_meta( rytkoset_theme_get_tampere_2026_registration_deadline_meta_key(), true );
	$deadline        = rytkoset_theme_normalize_registration_deadline_date( (string) $stored_deadline );

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

	$raw_deadline = isset( $_POST[ rytkoset_theme_get_tampere_2026_registration_deadline_meta_key() ] )
		? sanitize_text_field( wp_unslash( $_POST[ rytkoset_theme_get_tampere_2026_registration_deadline_meta_key() ] ) )
		: '';
	$deadline     = rytkoset_theme_normalize_registration_deadline_date( $raw_deadline );

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
 * Returns a JSON Schema fragment that matches when the Tampere 2026 product is in cart.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_cart_presence_schema() {
	$product_id = wc_get_product_id_by_sku( rytkoset_theme_get_tampere_2026_registration_sku() );

	if ( ! $product_id ) {
		return array(
			'type' => 'object',
			'not'  => array(),
		);
	}

	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'items' => array(
						'contains' => array(
							'const' => (int) $product_id,
						),
					),
				),
			),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the Tampere 2026 product is not in cart.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_cart_absence_schema() {
	return array(
		'not' => rytkoset_theme_get_tampere_2026_cart_presence_schema(),
	);
}

/**
 * Returns a JSON Schema fragment that matches when cart item count is at least the given threshold.
 *
 * This MVP assumes the Tampere registration product is purchased on its own,
 * so total cart quantity is used as the participant count.
 *
 * @param int $minimum Minimum item count.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_cart_items_count_minimum_schema( $minimum ) {
	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'items_count' => array(
						'minimum' => (int) $minimum,
					),
				),
			),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when cart item count is below the given threshold.
 *
 * @param int $minimum Minimum item count expected for the field to be relevant.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_cart_items_count_below_schema( $minimum ) {
	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'items_count' => array(
						'maximum' => max( 0, (int) $minimum - 1 ),
					),
				),
			),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be required.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_required_schema( $index ) {
	return array(
		'allOf' => array(
			rytkoset_theme_get_tampere_2026_cart_presence_schema(),
			rytkoset_theme_get_cart_items_count_minimum_schema( $index ),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be hidden.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ) {
	return array(
		'anyOf' => array(
			rytkoset_theme_get_tampere_2026_cart_absence_schema(),
			rytkoset_theme_get_cart_items_count_below_schema( $index ),
		),
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
		$name_field_id = sprintf( 'rytkoset/participant_%d_name', $index );
		$diet_field_id = sprintf( 'rytkoset/participant_%d_diet', $index );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $name_field_id,
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
				'label'             => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat', 'rytkoset-theme' ), $index ),
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

	$participants = array();

	for ( $index = 1; $index <= $participant_count; $index++ ) {
		$name = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_name', $index )
			)
		);
		$diet = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_diet', $index )
			)
		);

		if ( '' === $name && '' === $diet ) {
			continue;
		}

		$participants[] = array(
			'name' => $name,
			'diet' => $diet,
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

		if ( '' !== $participant['diet'] ) {
			echo '<br>';
			echo esc_html__( 'Ruokarajoitteet / allergiat:', 'rytkoset-theme' ) . ' ' . esc_html( $participant['diet'] );
		}

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
 * Returns the option name used for Tampere 2026 organizer notification recipients.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_notification_recipients_option_name() {
	return 'rytkoset_tampere_2026_notification_recipients';
}

/**
 * Normalizes a raw email list into unique, valid recipient addresses.
 *
 * @param string $raw_value Raw textarea value.
 * @return array<int, string>
 */
function rytkoset_theme_normalize_email_list( $raw_value ) {
	$parts   = preg_split( '/[\r\n,;]+/', (string) $raw_value );
	$emails  = array();
	$results = array();

	if ( ! is_array( $parts ) ) {
		return array();
	}

	foreach ( $parts as $part ) {
		$email = sanitize_email( trim( (string) $part ) );

		if ( '' === $email || ! is_email( $email ) ) {
			continue;
		}

		$index = strtolower( $email );

		if ( isset( $emails[ $index ] ) ) {
			continue;
		}

		$emails[ $index ] = true;
		$results[]        = $email;
	}

	return $results;
}

/**
 * Sanitizes the organizer notification recipients option.
 *
 * @param string $raw_value Raw textarea value.
 * @return string
 */
function rytkoset_theme_sanitize_tampere_2026_notification_recipients_option( $raw_value ) {
	$emails = rytkoset_theme_normalize_email_list( $raw_value );

	return implode( "\n", $emails );
}

/**
 * Returns the configured organizer notification recipients.
 *
 * @return array<int, string>
 */
function rytkoset_theme_get_tampere_2026_notification_recipients() {
	$value = get_option( rytkoset_theme_get_tampere_2026_notification_recipients_option_name(), '' );

	return rytkoset_theme_normalize_email_list( (string) $value );
}

/**
 * Renders the General Settings field for organizer notification recipients.
 *
 * @return void
 */
function rytkoset_theme_render_tampere_2026_notification_recipients_setting() {
	$option_name = rytkoset_theme_get_tampere_2026_notification_recipients_option_name();
	$value       = (string) get_option( $option_name, '' );
	?>
	<textarea
		name="<?php echo esc_attr( $option_name ); ?>"
		id="<?php echo esc_attr( $option_name ); ?>"
		rows="5"
		cols="50"
		class="large-text"
	><?php echo esc_textarea( $value ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'Anna vastaanottajaosoitteet pilkuilla tai rivinvaihdoilla eroteltuna. Vain kelvolliset sähköpostiosoitteet tallennetaan.', 'rytkoset-theme' ); ?>
	</p>
	<?php
}

/**
 * Registers the General Settings field for organizer notification recipients.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_notification_recipients_setting() {
	$option_name = rytkoset_theme_get_tampere_2026_notification_recipients_option_name();

	register_setting(
		'general',
		$option_name,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'rytkoset_theme_sanitize_tampere_2026_notification_recipients_option',
			'default'           => '',
		)
	);

	add_settings_field(
		$option_name,
		__( 'Tampere 2026 järjestäjäilmoitusten vastaanottajat', 'rytkoset-theme' ),
		'rytkoset_theme_render_tampere_2026_notification_recipients_setting',
		'general'
	);
}
add_action( 'admin_init', 'rytkoset_theme_register_tampere_2026_notification_recipients_setting' );

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
 * @param string   $subject       Email subject.
 * @param string   $message       Email body.
 * @param string   $error_summary Formatted wp_mail failure summary.
 * @return void
 */
function rytkoset_theme_add_tampere_2026_notification_debug_note( $order, $subject, $message, $error_summary ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$note_lines = array(
		__( 'Tampere 2026 -jarjestajailmoituksen debug (local/dev).', 'rytkoset-theme' ),
		__( 'Virhe:', 'rytkoset-theme' ) . ' ' . $error_summary,
		'',
		__( 'Aihe:', 'rytkoset-theme' ) . ' ' . $subject,
		'',
		__( 'Lahetyksen viestisisalto:', 'rytkoset-theme' ),
		$message,
	);

	$order->add_order_note( implode( PHP_EOL, $note_lines ), false );
}

/**
 * Captures outgoing Tampere 2026 organizer notification email arguments.
 *
 * @param array<string, mixed> $mail_atts wp_mail arguments.
 * @return array<string, mixed>
 */
function rytkoset_theme_capture_tampere_2026_notification_mail_args( $mail_atts ) {
	$subject = isset( $mail_atts['subject'] ) ? (string) $mail_atts['subject'] : '';

	if ( 0 === strpos( $subject, 'Tampere 2026 ilmoittautuminen #' ) ) {
		$GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] = $mail_atts;
	}

	return $mail_atts;
}
add_filter( 'wp_mail', 'rytkoset_theme_capture_tampere_2026_notification_mail_args' );

/**
 * Attempts to resolve a WooCommerce order from captured notification mail args.
 *
 * @param array<string, mixed> $mail_atts wp_mail arguments.
 * @return WC_Order|false
 */
function rytkoset_theme_get_tampere_2026_order_from_mail_args( $mail_atts ) {
	if ( ! is_array( $mail_atts ) ) {
		return false;
	}

	$message = isset( $mail_atts['message'] ) ? (string) $mail_atts['message'] : '';
	$subject = isset( $mail_atts['subject'] ) ? (string) $mail_atts['subject'] : '';

	if ( preg_match( '/[?&]id=(\d+)/', $message, $matches ) ) {
		return wc_get_order( (int) $matches[1] );
	}

	if ( preg_match( '/[?&]post=(\d+)/', $message, $matches ) ) {
		return wc_get_order( (int) $matches[1] );
	}

	if ( preg_match( '/#(\d+)\s*$/', $subject, $matches ) ) {
		return wc_get_order( (int) $matches[1] );
	}

	return false;
}

/**
 * Adds a local/dev-only debug note when wp_mail fails for Tampere 2026 notifications.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_maybe_log_tampere_2026_notification_mail_failure( $error ) {
	if ( ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$mail_atts = isset( $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] ) && is_array( $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] )
		? $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args']
		: null;

	unset( $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] );

	if ( ! is_array( $mail_atts ) ) {
		return;
	}

	$subject = isset( $mail_atts['subject'] ) ? (string) $mail_atts['subject'] : '';

	if ( 0 !== strpos( $subject, 'Tampere 2026 ilmoittautuminen #' ) ) {
		return;
	}

	$order = rytkoset_theme_get_tampere_2026_order_from_mail_args( $mail_atts );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$message       = isset( $mail_atts['message'] ) ? (string) $mail_atts['message'] : '';
	$error_summary = rytkoset_theme_format_wp_mail_failure( $error );

	rytkoset_theme_add_tampere_2026_notification_debug_note( $order, $subject, $message, $error_summary );
}
add_action( 'wp_mail_failed', 'rytkoset_theme_maybe_log_tampere_2026_notification_mail_failure' );

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
 * Builds the organizer notification email subject for a Tampere 2026 order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_notification_subject( $order ) {
	return sprintf( 'Tampere 2026 ilmoittautuminen #%s', $order->get_order_number() );
}

/**
 * Builds the organizer notification email body for a Tampere 2026 order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_notification_message( $order ) {
	$participants   = rytkoset_theme_get_tampere_2026_order_participants( $order );
	$admin_edit_url = rytkoset_theme_get_order_admin_edit_url( $order );
	$contact_name   = trim( $order->get_formatted_billing_full_name() );
	$email          = trim( (string) $order->get_billing_email() );
	$phone          = trim( (string) $order->get_billing_phone() );
	$payment_method = trim( (string) $order->get_payment_method_title() );
	$customer_note  = trim( (string) $order->get_customer_note() );
	$created_at     = $order->get_date_created();
	$created_text   = $created_at ? wp_date( 'j.n.Y H:i', $created_at->getTimestamp(), wp_timezone() ) : __( 'Ei tiedossa', 'rytkoset-theme' );
	$status_name    = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();
	$lines          = array(
		'Rytkösten sukuseura / Tampere 2026 ilmoittautuminen',
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
			$participant_name = '' !== $participant['name'] ? $participant['name'] : __( 'Nimi puuttuu', 'rytkoset-theme' );
			$lines[]          = sprintf( '%d. %s', $index + 1, $participant_name );

			if ( '' !== $participant['diet'] ) {
				$lines[] = '   ruokarajoitteet / allergiat: ' . $participant['diet'];
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
 * Sends the Tampere 2026 organizer notification for an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_send_tampere_2026_organizer_notification( $order ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_tampere_2026_registration_order( $order ) ) {
		return false;
	}

	$sent_at = (string) $order->get_meta( '_rytkoset_tampere_2026_notification_sent_at', true );

	if ( '' !== $sent_at ) {
		return false;
	}

	$recipients = rytkoset_theme_get_tampere_2026_notification_recipients();

	if ( empty( $recipients ) ) {
		$order->add_order_note(
			__( 'Tampere 2026 -järjestäjäilmoitusta ei lähetetty, koska vastaanottajaosoitteita ei ole asetettu kohdassa Asetukset > Yleiset.', 'rytkoset-theme' ),
			false
		);
		return false;
	}

	$sent = wp_mail(
		$recipients,
		rytkoset_theme_get_tampere_2026_notification_subject( $order ),
		rytkoset_theme_get_tampere_2026_notification_message( $order )
	);

	if ( ! $sent ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: recipient email list. */
				__( 'Tampere 2026 -järjestäjäilmoituksen lähetys epäonnistui. Vastaanottajat: %s', 'rytkoset-theme' ),
				implode( ', ', $recipients )
			),
			false
		);
		return false;
	}

	$order->update_meta_data( '_rytkoset_tampere_2026_notification_sent_at', current_time( 'mysql' ) );
	$order->update_meta_data( '_rytkoset_tampere_2026_notification_recipients', implode( ', ', $recipients ) );
	$order->save();

	$order->add_order_note(
		sprintf(
			/* translators: %s: recipient email list. */
			__( 'Tampere 2026 -järjestäjäilmoitus lähetettiin osoitteisiin: %s', 'rytkoset-theme' ),
			implode( ', ', $recipients )
		),
		false
	);

	return true;
}

/**
 * Sends the Tampere 2026 organizer notification when an order moves to on-hold.
 *
 * @param int      $order_id WooCommerce order ID.
 * @param WC_Order $order    WooCommerce order object.
 * @return void
 */
function rytkoset_theme_maybe_send_tampere_2026_organizer_notification( $order_id, $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	rytkoset_theme_send_tampere_2026_organizer_notification( $order );
}
add_action( 'woocommerce_order_status_on-hold', 'rytkoset_theme_maybe_send_tampere_2026_organizer_notification', 10, 2 );
