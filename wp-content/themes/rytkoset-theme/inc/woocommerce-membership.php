<?php
/**
 * Jäsenmaksutuotteet WooCommercessa: tuotteen meta-kentät, kassan ilmoitus, tilauslistauksen sarake ja metaboxi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the product meta key used to mark WooCommerce membership products.
 *
 * @return string
 */
function rytkoset_theme_get_membership_product_meta_key() {
	return '_rytkoset_membership_product';
}

/**
 * Returns the product meta key used for the membership product type.
 *
 * @return string
 */
function rytkoset_theme_get_membership_type_meta_key() {
	return '_rytkoset_membership_type';
}

/**
 * Returns the product meta key used for the membership period.
 *
 * @return string
 */
function rytkoset_theme_get_membership_period_meta_key() {
	return '_rytkoset_membership_period';
}

/**
 * Returns the product meta key used when checkout member names are required.
 *
 * @return string
 */
function rytkoset_theme_get_member_names_required_meta_key() {
	return '_rytkoset_member_names_required';
}

/**
 * Returns the product meta key used for the membership expiry date (ISO YYYY-MM-DD).
 *
 * @return string
 */
function rytkoset_theme_get_membership_expiry_date_meta_key() {
	return '_rytkoset_membership_expiry_date';
}

/**
 * Returns supported membership product types.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_membership_type_options() {
	return array(
		'annual_individual' => __( 'Vuosijäsen: Yksityishenkilö', 'rytkoset-theme' ),
		'annual_family'     => __( 'Vuosijäsen: Perhe', 'rytkoset-theme' ),
		'lifetime'          => __( 'Ainaisjäsen', 'rytkoset-theme' ),
	);
}

/**
 * Normalizes a membership type value.
 *
 * @param string $type Raw membership type.
 * @return string
 */
function rytkoset_theme_normalize_membership_type( $type ) {
	$type    = sanitize_key( $type );
	$options = rytkoset_theme_get_membership_type_options();

	return isset( $options[ $type ] ) ? $type : '';
}

/**
 * Returns the membership type label.
 *
 * @param string $type Membership type key.
 * @return string
 */
function rytkoset_theme_get_membership_type_label( $type ) {
	$options = rytkoset_theme_get_membership_type_options();

	return isset( $options[ $type ] ) ? $options[ $type ] : __( 'Jäsenmaksu', 'rytkoset-theme' );
}

/**
 * Returns true when the product is managed as a membership product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_membership_product( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	return 'yes' === $product->get_meta( rytkoset_theme_get_membership_product_meta_key(), true );
}

/**
 * Returns the membership product type for a product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_membership_product_type( $product ) {
	if ( ! rytkoset_theme_is_membership_product( $product ) ) {
		return '';
	}

	return rytkoset_theme_normalize_membership_type(
		(string) $product->get_meta( rytkoset_theme_get_membership_type_meta_key(), true )
	);
}

/**
 * Returns the membership period configured for a product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_membership_product_period( $product ) {
	if ( ! rytkoset_theme_is_membership_product( $product ) ) {
		return '';
	}

	return rytkoset_theme_sanitize_user_membership_period(
		(string) $product->get_meta( rytkoset_theme_get_membership_period_meta_key(), true )
	);
}

/**
 * Returns validation errors for the activation settings of a membership product.
 *
 * Lifetime membership intentionally needs neither a period nor an expiry date. Annual
 * individual and family memberships require both so a paid order can always activate a
 * complete membership.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return array<string, string> Error code => Finnish error message.
 */
function rytkoset_theme_get_membership_product_validation_errors( $product ) {
	if ( ! rytkoset_theme_is_membership_product( $product ) ) {
		return array();
	}

	$errors = array();
	$type   = rytkoset_theme_get_membership_product_type( $product );

	if ( '' === $type ) {
		$errors['type'] = __( 'Jäsenmaksun tyyppi puuttuu tai on virheellinen.', 'rytkoset-theme' );
		return $errors;
	}

	if ( 'lifetime' === $type ) {
		return $errors;
	}

	if ( '' === rytkoset_theme_get_membership_product_period( $product ) ) {
		$errors['period'] = __( 'Jäsenkausi puuttuu tai ei ole muodossa VVVV-VVVV.', 'rytkoset-theme' );
	}

	if ( '' === rytkoset_theme_get_membership_product_expiry_date( $product ) ) {
		$errors['expiry_date'] = __( 'Jäsenyys voimassa asti -päivä puuttuu tai on virheellinen.', 'rytkoset-theme' );
	}

	return $errors;
}

/**
 * Returns the customer-facing error for an incomplete membership product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string Empty when the product is valid or is not a membership product.
 */
function rytkoset_theme_get_membership_product_purchase_block_message( $product ) {
	$errors = rytkoset_theme_get_membership_product_validation_errors( $product );

	if ( empty( $errors ) ) {
		return '';
	}

	$product_name = $product instanceof WC_Product ? $product->get_name() : '';

	return sprintf(
		/* translators: 1: product name, 2: membership product validation errors. */
		__( 'Jäsenmaksutuotetta ”%1$s” ei voi ostaa: %2$s Ota yhteyttä sivuston ylläpitoon.', 'rytkoset-theme' ),
		$product_name,
		implode( ' ', $errors )
	);
}

/**
 * Returns the membership expiry date configured for a product, in ISO YYYY-MM-DD format.
 *
 * This is the date through which a membership bought via this product stays active (e.g. the
 * next family meeting / sukukokous date). The automatic order update (#302) copies it to the
 * user's membership expiry; an empty value means the membership cannot be auto-activated.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string ISO date (YYYY-MM-DD), or '' when unset/invalid.
 */
function rytkoset_theme_get_membership_product_expiry_date( $product ) {
	if ( ! rytkoset_theme_is_membership_product( $product ) ) {
		return '';
	}

	return rytkoset_theme_sanitize_user_membership_expires(
		(string) $product->get_meta( rytkoset_theme_get_membership_expiry_date_meta_key(), true )
	);
}

/**
 * Returns true when a product requires member names in the order note.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_membership_product_requires_member_names( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	return 'yes' === $product->get_meta( rytkoset_theme_get_member_names_required_meta_key(), true );
}

/**
 * Returns a compact admin label for a membership product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_membership_product_admin_label( $product ) {
	$type = rytkoset_theme_get_membership_product_type( $product );

	if ( '' === $type ) {
		return '';
	}

	$label  = rytkoset_theme_get_membership_type_label( $type );
	$period = rytkoset_theme_get_membership_product_period( $product );

	if ( '' !== $period && 'lifetime' !== $type ) {
		$label .= ', ' . $period;
	}

	return $label;
}

/**
 * Renders membership product settings in WooCommerce product admin.
 *
 * @return void
 */
function rytkoset_theme_render_membership_product_fields() {
	global $post;

	if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
		return;
	}

	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	echo '<div class="options_group">';
	echo '<p><strong>' . esc_html__( 'Jäsenmaksun hallinta', 'rytkoset-theme' ) . '</strong></p>';

	woocommerce_wp_checkbox(
		array(
			'id'          => rytkoset_theme_get_membership_product_meta_key(),
			'label'       => __( 'Jäsenmaksutuote', 'rytkoset-theme' ),
			'description' => __( 'Merkitse tuote jäsenmaksuksi, jotta se tunnistetaan tilausadminissa.', 'rytkoset-theme' ),
			'value'       => $product->get_meta( rytkoset_theme_get_membership_product_meta_key(), true ),
		)
	);

	woocommerce_wp_select(
		array(
			'id'          => rytkoset_theme_get_membership_type_meta_key(),
			'label'       => __( 'Jäsenmaksun tyyppi', 'rytkoset-theme' ),
			'options'     => array( '' => __( 'Ei valintaa', 'rytkoset-theme' ) ) + rytkoset_theme_get_membership_type_options(),
			'value'       => rytkoset_theme_get_membership_product_type( $product ),
			'description' => __( 'Tyyppi näytetään tilauksissa ja jäsenmaksujen käsittelyssä.', 'rytkoset-theme' ),
		)
	);

	woocommerce_wp_text_input(
		array(
			'id'          => rytkoset_theme_get_membership_period_meta_key(),
			'label'       => __( 'Jäsenkausi', 'rytkoset-theme' ),
			'placeholder' => '2026-2029',
			'value'       => rytkoset_theme_get_membership_product_period( $product ),
			'description' => __( 'Käytä vuosijäsenmaksuilla muotoa 2026-2029. Ainaisjäsenmaksulla kentän voi jättää tyhjäksi.', 'rytkoset-theme' ),
			'desc_tip'    => true,
		)
	);

	woocommerce_wp_text_input(
		array(
			'id'          => rytkoset_theme_get_membership_expiry_date_meta_key(),
			'label'       => __( 'Jäsenyys voimassa asti', 'rytkoset-theme' ),
			'type'        => 'date',
			'value'       => rytkoset_theme_get_membership_product_expiry_date( $product ),
			'description' => __( 'Vuosi- ja perhejäsenmaksulle: päivä, johon asti ostettu jäsenyys on voimassa (yleensä seuraavan sukukokouksen päivä). Automaattinen jäsenyyspäivitys käyttää tätä päivää. Ainaisjäsenmaksulle kentän voi jättää tyhjäksi.', 'rytkoset-theme' ),
			'desc_tip'    => true,
		)
	);

	woocommerce_wp_checkbox(
		array(
			'id'          => rytkoset_theme_get_member_names_required_meta_key(),
			'label'       => __( 'Vaatii jäsenten nimet kassalla', 'rytkoset-theme' ),
			'description' => __( 'Näyttää kassalla rakenteiset kentät jäsenten nimille ja sähköposteille. Perhejäsenmaksulla (tyyppi: Vuosijäsen: Perhe) kenttiä näytetään useita.', 'rytkoset-theme' ),
			'value'       => $product->get_meta( rytkoset_theme_get_member_names_required_meta_key(), true ),
		)
	);

	echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'rytkoset_theme_render_membership_product_fields' );

/**
 * Saves membership product settings from WooCommerce product admin.
 *
 * @param WC_Product $product WooCommerce product object.
 * @return void
 */
function rytkoset_theme_save_membership_product_fields( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product edit nonce before this hook runs.
	$is_membership_product = isset( $_POST[ rytkoset_theme_get_membership_product_meta_key() ] ) ? 'yes' : 'no';
	$requires_names        = isset( $_POST[ rytkoset_theme_get_member_names_required_meta_key() ] ) ? 'yes' : 'no';
	$type                  = isset( $_POST[ rytkoset_theme_get_membership_type_meta_key() ] )
		? rytkoset_theme_normalize_membership_type( wp_unslash( $_POST[ rytkoset_theme_get_membership_type_meta_key() ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalizer returns only allowlisted membership types.
		: '';
	$period                = isset( $_POST[ rytkoset_theme_get_membership_period_meta_key() ] )
		? sanitize_text_field( wp_unslash( $_POST[ rytkoset_theme_get_membership_period_meta_key() ] ) )
		: '';
	$expiry_date           = isset( $_POST[ rytkoset_theme_get_membership_expiry_date_meta_key() ] )
		? rytkoset_theme_sanitize_user_membership_expires( wp_unslash( $_POST[ rytkoset_theme_get_membership_expiry_date_meta_key() ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizer validates and returns a normalized ISO date string.
		: '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$product->update_meta_data( rytkoset_theme_get_membership_product_meta_key(), $is_membership_product );
	$product->update_meta_data( rytkoset_theme_get_member_names_required_meta_key(), $requires_names );

	if ( 'yes' !== $is_membership_product ) {
		$product->delete_meta_data( rytkoset_theme_get_membership_type_meta_key() );
		$product->delete_meta_data( rytkoset_theme_get_membership_period_meta_key() );
		$product->delete_meta_data( rytkoset_theme_get_membership_expiry_date_meta_key() );
		return;
	}

	$product->update_meta_data( rytkoset_theme_get_membership_type_meta_key(), $type );

	if ( 'lifetime' === $type ) {
		$product->delete_meta_data( rytkoset_theme_get_membership_period_meta_key() );
		$product->delete_meta_data( rytkoset_theme_get_membership_expiry_date_meta_key() );
		return;
	}

	$product->update_meta_data( rytkoset_theme_get_membership_period_meta_key(), $period );
	$product->update_meta_data( rytkoset_theme_get_membership_expiry_date_meta_key(), $expiry_date );

	$validation_errors = rytkoset_theme_get_membership_product_validation_errors( $product );

	if ( 'publish' === $product->get_status() && ! empty( $validation_errors ) ) {
		$product->set_status( 'draft' );

		if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
			WC_Admin_Meta_Boxes::add_error(
				sprintf(
					/* translators: %s: membership product validation errors. */
					__( 'Jäsenmaksutuotetta ei julkaistu, koska asetukset ovat puutteelliset: %s', 'rytkoset-theme' ),
					implode( ' ', $validation_errors )
				)
			);
		}
	}
}
add_action( 'woocommerce_admin_process_product_object', 'rytkoset_theme_save_membership_product_fields' );

/**
 * Prevents adding an incomplete membership product to the cart.
 *
 * @param bool $passed     Whether add to cart should proceed.
 * @param int  $product_id Product ID.
 * @return bool
 */
function rytkoset_theme_validate_membership_product_add_to_cart( $passed, $product_id ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	$message = rytkoset_theme_get_membership_product_purchase_block_message( $product );

	if ( '' === $message ) {
		return $passed;
	}

	if ( function_exists( 'wc_add_notice' ) && ! wc_has_notice( $message, 'error' ) ) {
		wc_add_notice( $message, 'error' );
	}

	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'rytkoset_theme_validate_membership_product_add_to_cart', 10, 2 );

/**
 * Validates membership products already present in the cart and at checkout.
 *
 * @return void
 */
function rytkoset_theme_validate_membership_product_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$messages = array();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$message = rytkoset_theme_get_membership_product_purchase_block_message( $product );

		if ( '' !== $message ) {
			$messages[ $message ] = true;
		}
	}

	foreach ( array_keys( $messages ) as $message ) {
		if ( function_exists( 'wc_add_notice' ) && ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}
}
add_action( 'woocommerce_check_cart_items', 'rytkoset_theme_validate_membership_product_cart_items' );

/**
 * Returns true when the current cart contains a membership product.
 *
 * @return bool
 */
function rytkoset_theme_cart_has_membership_product() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		if ( rytkoset_theme_is_membership_product( $product ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns membership products found on an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_get_membership_order_items( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$membership_items = array();

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( ! rytkoset_theme_is_membership_product( $product ) ) {
			continue;
		}

		$type = rytkoset_theme_get_membership_product_type( $product );

		$membership_items[] = array(
			'product_name'   => $item->get_name(),
			'quantity'       => (int) $item->get_quantity(),
			'type'           => $type,
			'type_label'     => rytkoset_theme_get_membership_type_label( $type ),
			'period'         => rytkoset_theme_get_membership_product_period( $product ),
			'expiry_date'    => rytkoset_theme_get_membership_product_expiry_date( $product ),
			'admin_label'    => rytkoset_theme_get_membership_product_admin_label( $product ),
			'requires_names' => rytkoset_theme_membership_product_requires_member_names( $product ),
		);
	}

	return $membership_items;
}

/**
 * Returns true when an order contains membership products.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_is_membership_order( $order ) {
	return ! empty( rytkoset_theme_get_membership_order_items( $order ) );
}

/**
 * Adds the membership column to WooCommerce order lists.
 *
 * @param array<string, mixed> $columns Existing order list columns.
 * @return array<string, mixed>
 */
function rytkoset_theme_add_membership_orders_column( $columns ) {
	$new_columns = array();

	foreach ( $columns as $column_name => $column_label ) {
		$new_columns[ $column_name ] = $column_label;

		if ( 'order_status' === $column_name ) {
			$new_columns['rytkoset_membership'] = __( 'Jäsenmaksu', 'rytkoset-theme' );
		}
	}

	if ( ! isset( $new_columns['rytkoset_membership'] ) ) {
		$new_columns['rytkoset_membership'] = __( 'Jäsenmaksu', 'rytkoset-theme' );
	}

	return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'rytkoset_theme_add_membership_orders_column', 12 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'rytkoset_theme_add_membership_orders_column', 12 );

/**
 * Renders the membership order list column value.
 *
 * @param string   $column_name Column name.
 * @param WC_Order $order       WooCommerce order object.
 * @return void
 */
function rytkoset_theme_render_membership_orders_column( $column_name, $order ) {
	if ( 'rytkoset_membership' !== $column_name ) {
		return;
	}

	if ( ! $order instanceof WC_Order ) {
		echo '&mdash;';
		return;
	}

	$membership_items = rytkoset_theme_get_membership_order_items( $order );

	if ( empty( $membership_items ) ) {
		echo '&mdash;';
		return;
	}

	$labels = array();

	foreach ( $membership_items as $membership_item ) {
		if ( '' !== $membership_item['admin_label'] ) {
			$labels[] = $membership_item['admin_label'];
		}
	}

	$labels = array_unique( $labels );

	if ( empty( $labels ) ) {
		echo '&mdash;';
		return;
	}

	echo esc_html( implode( '; ', $labels ) );
}

/**
 * Legacy renderer wrapper for the membership order list column.
 *
 * @param string $column_name Column name.
 * @return void
 */
function rytkoset_theme_render_membership_orders_column_legacy( $column_name ) {
	global $the_order;

	if ( ! $the_order instanceof WC_Order ) {
		return;
	}

	rytkoset_theme_render_membership_orders_column( $column_name, $the_order );
}
add_action( 'manage_shop_order_posts_custom_column', 'rytkoset_theme_render_membership_orders_column_legacy', 26, 1 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'rytkoset_theme_render_membership_orders_column', 26, 2 );

/**
 * Registers the membership metabox for order admin screens.
 *
 * @return void
 */
function rytkoset_theme_register_membership_order_metabox() {
	if ( ! function_exists( 'wc_get_page_screen_id' ) || ! function_exists( 'wc_get_container' ) ) {
		add_meta_box(
			'rytkoset-membership-order',
			__( 'Jäsenmaksu', 'rytkoset-theme' ),
			'rytkoset_theme_render_membership_order_metabox',
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
		'rytkoset-membership-order',
		__( 'Jäsenmaksu', 'rytkoset-theme' ),
		'rytkoset_theme_render_membership_order_metabox',
		$screen,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'rytkoset_theme_register_membership_order_metabox' );

/**
 * Renders membership handling details inside the order admin metabox.
 *
 * @param mixed $post_or_order_object Either a WP_Post or WC_Order.
 * @return void
 */
function rytkoset_theme_render_membership_order_metabox( $post_or_order_object ) {
	$order = rytkoset_theme_get_order_from_admin_screen_object( $post_or_order_object );

	if ( ! $order instanceof WC_Order ) {
		echo '<p>' . esc_html__( 'Tilausta ei voitu lukea.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	$membership_items = rytkoset_theme_get_membership_order_items( $order );

	if ( empty( $membership_items ) ) {
		echo '<p>' . esc_html__( 'Tällä tilauksella ei ole jäsenmaksutuotteita.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	$customer_note  = trim( (string) $order->get_customer_note() );
	$requires_names = false;

	echo '<p><strong>' . esc_html__( 'Jäsenmaksut:', 'rytkoset-theme' ) . '</strong></p>';
	echo '<ul>';

	foreach ( $membership_items as $membership_item ) {
		$requires_names = $requires_names || (bool) $membership_item['requires_names'];

		echo '<li>';
		echo '<strong>' . esc_html( $membership_item['type_label'] ) . '</strong>';

		if ( '' !== $membership_item['period'] && 'lifetime' !== $membership_item['type'] ) {
			echo '<br>' . esc_html__( 'Kausi:', 'rytkoset-theme' ) . ' ' . esc_html( $membership_item['period'] );
		}

		echo '<br>' . esc_html__( 'Tuote:', 'rytkoset-theme' ) . ' ' . esc_html( $membership_item['product_name'] );

		if ( $membership_item['quantity'] > 1 ) {
			echo '<br>' . esc_html__( 'Määrä:', 'rytkoset-theme' ) . ' ' . esc_html( (string) $membership_item['quantity'] );
		}

		echo '</li>';
	}

	echo '</ul>';

	echo '<p><strong>' . esc_html__( 'Tila:', 'rytkoset-theme' ) . '</strong> ' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Yhteyshenkilö:', 'rytkoset-theme' ) . '</strong><br>';
	echo esc_html( $order->get_formatted_billing_full_name() );

	if ( '' !== $order->get_billing_email() ) {
		echo '<br>' . esc_html( $order->get_billing_email() );
	}

	if ( '' !== $order->get_billing_phone() ) {
		echo '<br>' . esc_html( $order->get_billing_phone() );
	}

	echo '</p>';

	$members = rytkoset_theme_get_membership_order_members( $order );

	if ( ! empty( $members ) ) {
		echo '<p><strong>' . esc_html__( 'Jäsenet:', 'rytkoset-theme' ) . '</strong></p>';
		echo '<ol>';

		foreach ( $members as $member ) {
			echo '<li>';
			echo '<strong>' . esc_html( '' !== $member['name'] ? $member['name'] : __( 'Nimi puuttuu', 'rytkoset-theme' ) ) . '</strong>';

			if ( '' !== $member['email'] ) {
				echo '<br>' . esc_html( $member['email'] );
			} else {
				echo '<br>' . esc_html__( '(ei sähköpostia)', 'rytkoset-theme' );
			}

			echo '</li>';
		}

		echo '</ol>';
	}

	if ( '' !== $customer_note ) {
		echo '<p><strong>' . esc_html__( 'Tilauksen lisätiedot:', 'rytkoset-theme' ) . '</strong><br>';
		echo wp_kses_post( nl2br( esc_html( $customer_note ) ) );
		echo '</p>';
	} elseif ( $requires_names && empty( $members ) ) {
		echo '<p><strong>' . esc_html__( 'Huomio:', 'rytkoset-theme' ) . '</strong> ';
		echo esc_html__( 'Jäsenten nimikentät ovat tyhjät. Tarkista tarvittaessa jäsenen tai perheenjäsenten nimet ennen jäsenrekisteriin vientiä.', 'rytkoset-theme' );
		echo '</p>';
	}

	echo '<p>' . esc_html__( 'Käsittely: tarkista tilisiirtomaksu ja merkitse tilaus käsitellyksi tai valmiiksi. Jäsenyystiedot päivittyvät automaattisesti, kun tilaus saavuttaa hyväksytyn tilan.', 'rytkoset-theme' ) . '</p>';
}

/**
 * Returns the maximum number of member rows shown for a family membership at checkout.
 *
 * @return int
 */
function rytkoset_theme_get_membership_max_member_rows() {
	/**
	 * Filters the maximum number of member rows available for a family membership (#520).
	 *
	 * @param int $max_rows Maximum member row count. Default 6.
	 */
	$max_rows = (int) apply_filters( 'rytkoset_theme_membership_max_member_rows', 6 );

	return max( 1, $max_rows );
}

/**
 * Returns the additional checkout field IDs for a member row.
 *
 * @param int $index Member row index.
 * @return array<int, string>
 */
function rytkoset_theme_get_membership_member_field_ids( $index ) {
	$index = absint( $index );

	return array(
		sprintf( 'rytkoset/member_%d_name', $index ),
		sprintf( 'rytkoset/member_%d_email', $index ),
	);
}

/**
 * Resolves the allowed member row range for the checkout (#520).
 *
 * Pure resolver: family memberships allow adding rows up to the configured
 * maximum, other names-required memberships show exactly one row, and carts
 * without a names-required membership product show none.
 *
 * @param bool $requires_names Cart contains a names-required membership product.
 * @param bool $is_family      Cart contains a names-required family membership product.
 * @return array{min:int,max:int}
 */
function rytkoset_theme_resolve_membership_member_row_bounds( $requires_names, $is_family ) {
	if ( ! $requires_names ) {
		return array(
			'min' => 0,
			'max' => 0,
		);
	}

	return array(
		'min' => 1,
		'max' => $is_family ? rytkoset_theme_get_membership_max_member_rows() : 1,
	);
}

/**
 * Returns the allowed member row range for the current cart (#520).
 *
 * @return array{min:int,max:int}
 */
function rytkoset_theme_get_membership_member_row_bounds() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return rytkoset_theme_resolve_membership_member_row_bounds( false, false );
	}

	$requires_names = false;
	$is_family      = false;

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_membership_product( $product ) ) {
			continue;
		}

		if ( ! rytkoset_theme_membership_product_requires_member_names( $product ) ) {
			continue;
		}

		$requires_names = true;

		if ( 'annual_family' === rytkoset_theme_get_membership_product_type( $product ) ) {
			$is_family = true;
		}
	}

	return rytkoset_theme_resolve_membership_member_row_bounds( $requires_names, $is_family );
}

/**
 * Clamps a requested member row count into the allowed range (#520).
 *
	 * @param int                      $requested Requested member row count.
	 * @param array{min:int,max:int} $bounds  Allowed row range.
 * @return int
 */
function rytkoset_theme_clamp_membership_member_rows( $requested, $bounds ) {
	$min = isset( $bounds['min'] ) ? (int) $bounds['min'] : 0;
	$max = isset( $bounds['max'] ) ? (int) $bounds['max'] : 0;

	if ( $max < 1 ) {
		return 0;
	}

	return min( $max, max( $min, absint( $requested ) ) );
}

/**
 * Returns the WooCommerce session key for the requested member row count (#520).
 *
 * @return string
 */
function rytkoset_theme_get_membership_member_rows_session_key() {
	return 'rytkoset_membership_member_rows';
}

/**
 * Returns the member row count the buyer has requested in the checkout session (#520).
 *
 * Defaults to one row so the family checkout starts with member 1 only.
 *
 * @return int
 */
function rytkoset_theme_get_requested_membership_member_rows() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return 1;
	}

	$requested = absint( WC()->session->get( rytkoset_theme_get_membership_member_rows_session_key(), 1 ) );

	return max( 1, $requested );
}

/**
 * Stores the requested member row count in the WooCommerce session (#520).
 *
 * The stored value is clamped again on every read, so a stale or out-of-range
 * request can never widen the row range beyond the cart contents.
 *
 * @param int $rows Requested member row count.
 * @return void
 */
function rytkoset_theme_set_requested_membership_member_rows( $rows ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	WC()->session->set(
		rytkoset_theme_get_membership_member_rows_session_key(),
		max( 1, absint( $rows ) )
	);
}

/**
 * Handles the Store API extensionCartUpdate call for member rows (#520).
 *
 * The client JS posts the desired row count; the server stores it in the
 * session and stays the source of truth via clamping on read.
 *
 * @param array<string, mixed> $data Extension data from the Store API request.
 * @return void
 */
function rytkoset_theme_handle_membership_member_rows_update( $data ) {
	if ( ! is_array( $data ) || ! isset( $data['member_rows'] ) ) {
		return;
	}

	rytkoset_theme_set_requested_membership_member_rows( absint( $data['member_rows'] ) );
}

/**
 * Clears the requested member row count when the cart is emptied (#520).
 *
 * Hooked to woocommerce_cart_emptied (fired by WC_Cart::empty_cart(), e.g.
 * after a successful checkout) so the next family purchase starts from one
 * row again. The checkout-order-processed hook is intentionally not used: it
 * also fires when the payment step fails, and resetting there would wipe the
 * buyer's extra rows from the retry attempt.
 *
 * @return void
 */
function rytkoset_theme_reset_requested_membership_member_rows() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	WC()->session->set( rytkoset_theme_get_membership_member_rows_session_key(), null );
}
add_action( 'woocommerce_cart_emptied', 'rytkoset_theme_reset_requested_membership_member_rows' );

/**
 * Returns the number of member rows the checkout should show for the current cart.
 *
 * Individual and lifetime memberships show one row; family memberships show the
 * buyer-requested count (1..max, dynamic add/remove #520). Membership products
 * without the names-required flag do not add rows.
 *
 * @return int
 */
function rytkoset_theme_get_membership_member_row_count() {
	return rytkoset_theme_clamp_membership_member_rows(
		rytkoset_theme_get_requested_membership_member_rows(),
		rytkoset_theme_get_membership_member_row_bounds()
	);
}

/**
 * Returns true when the current cart contains a membership product that requires member names.
 *
 * @return bool
 */
function rytkoset_theme_cart_requires_member_names() {
	return rytkoset_theme_get_membership_member_row_count() > 0;
}

/**
 * Returns the Store API extension namespace for membership cart data.
 *
 * @return string
 */
function rytkoset_theme_get_membership_store_api_namespace() {
	return 'rytkoset_membership';
}

/**
 * Returns membership cart data for the WooCommerce Store API.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_membership_store_api_cart_data() {
	$bounds = rytkoset_theme_get_membership_member_row_bounds();

	return array(
		'member_row_count' => rytkoset_theme_get_membership_member_row_count(),
		'member_row_max'   => $bounds['max'],
	);
}

/**
 * Returns the schema for membership Store API cart data.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_membership_store_api_cart_schema() {
	return array(
		'member_row_count' => array(
			'description' => __( 'Jäsenmaksun jäsenrivien määrä ostoskorissa.', 'rytkoset-theme' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'readonly'    => true,
		),
		'member_row_max'   => array(
			'description' => __( 'Jäsenrivien enimmäismäärä nykyiselle ostoskorille.', 'rytkoset-theme' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'readonly'    => true,
		),
	);
}

/**
 * Registers membership cart data for Checkout Block conditions.
 *
 * @return void
 */
function rytkoset_theme_register_membership_store_api_cart_data() {
	if (
		! function_exists( 'woocommerce_store_api_register_endpoint_data' )
		|| ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' )
	) {
		return;
	}

	woocommerce_store_api_register_endpoint_data(
		array(
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace'       => rytkoset_theme_get_membership_store_api_namespace(),
			'data_callback'   => 'rytkoset_theme_get_membership_store_api_cart_data',
			'schema_callback' => 'rytkoset_theme_get_membership_store_api_cart_schema',
			'schema_type'     => ARRAY_A,
		)
	);

	if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => rytkoset_theme_get_membership_store_api_namespace(),
				'callback'  => 'rytkoset_theme_handle_membership_member_rows_update',
			)
		);
	}
}

/**
 * Registers Store API data after WooCommerce Blocks is available.
 */
if ( did_action( 'woocommerce_blocks_loaded' ) ) {
	rytkoset_theme_register_membership_store_api_cart_data();
} else {
	add_action( 'woocommerce_blocks_loaded', 'rytkoset_theme_register_membership_store_api_cart_data' );
}

/**
 * Returns a JSON Schema fragment that matches an active member field.
 *
 * @param int $index Member row index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_membership_member_active_schema( $index ) {
	$namespace = rytkoset_theme_get_membership_store_api_namespace();

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
									'member_row_count' => array(
										'type'    => 'integer',
										'minimum' => max( 1, (int) $index ),
									),
								),
								'required'   => array( 'member_row_count' ),
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
 * Returns a JSON Schema fragment that matches when a member field should be hidden.
 *
 * @param int $index Member row index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_membership_member_hidden_schema( $index ) {
	return array(
		'not' => rytkoset_theme_get_membership_member_active_schema( $index ),
	);
}

/**
 * Validates a member email field value.
 *
 * Presence of the first row is enforced by the required schema, so empty values
 * (optional rows or hidden fields) are skipped here and only the format is checked.
 *
 * @param string $value Submitted field value.
 * @return WP_Error|void
 */
function rytkoset_theme_validate_membership_member_email_field( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return;
	}

	if ( ! is_email( $value ) ) {
		return new WP_Error(
			'rytkoset_invalid_member_email',
			__( 'Anna jäsenen sähköpostiosoite oikeassa muodossa.', 'rytkoset-theme' )
		);
	}
}

/**
 * Registers structured member name and email fields for the membership checkout flow.
 *
 * Fields are always registered for Store API submissions and conditionally shown
 * based on the member row count published in the cart extension.
 *
 * @return void
 */
function rytkoset_theme_register_membership_checkout_fields() {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	$max_rows = rytkoset_theme_get_membership_max_member_rows();

	for ( $index = 1; $index <= $max_rows; $index++ ) {
		$name_field_id  = sprintf( 'rytkoset/member_%d_name', $index );
		$email_field_id = sprintf( 'rytkoset/member_%d_email', $index );
		$is_first_row   = ( 1 === $index );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $name_field_id,
				/* translators: %d: member row number. */
				'label'             => sprintf( __( 'Jäsen %d: nimi', 'rytkoset-theme' ), $index ),
				/* translators: %d: member row number. */
				'optionalLabel'     => sprintf( __( 'Jäsen %d: nimi (valinnainen)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => $is_first_row ? rytkoset_theme_get_membership_member_active_schema( 1 ) : false,
				'hidden'            => rytkoset_theme_get_membership_member_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-member-%d-name new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $email_field_id,
				/* translators: %d: member row number. */
				'label'             => sprintf( __( 'Jäsen %d: sähköposti', 'rytkoset-theme' ), $index ),
				/* translators: %d: member row number. */
				'optionalLabel'     => sprintf( __( 'Jäsen %d: sähköposti (valinnainen)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => $is_first_row ? rytkoset_theme_get_membership_member_active_schema( 1 ) : false,
				'hidden'            => rytkoset_theme_get_membership_member_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => 'rytkoset_theme_validate_membership_member_email_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-member-%d-email new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);
	}
}
add_action( 'woocommerce_init', 'rytkoset_theme_register_membership_checkout_fields' );

/**
 * Returns the prefill name for member row 1 from a user's profile.
 *
 * Prefers the profile first and last name; falls back to the display name.
 *
 * @param WP_User|null $user User object.
 * @return string Prefill name, or an empty string when none is available.
 */
function rytkoset_theme_get_membership_member_prefill_name( $user ) {
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return '';
	}

	$first_name = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
	$last_name  = trim( (string) get_user_meta( $user->ID, 'last_name', true ) );
	$name       = trim( $first_name . ' ' . $last_name );

	if ( '' === $name ) {
		$name = trim( (string) $user->display_name );
	}

	return $name;
}

/**
 * Returns the prefill email for member row 1 from a user's account email.
 *
 * @param WP_User|null $user User object.
 * @return string Prefill email, or an empty string when none is available.
 */
function rytkoset_theme_get_membership_member_prefill_email( $user ) {
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return '';
	}

	$email = trim( (string) $user->user_email );

	return is_email( $email ) ? $email : '';
}

/**
 * Returns true when member row 1 may be prefilled in the current request.
 *
 * @return bool
 */
function rytkoset_theme_membership_member_prefill_is_available() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return false;
	}

	return rytkoset_theme_cart_requires_member_names();
}

/**
 * Returns the member row 1 prefill values for a user, or an empty array when
 * there is nothing to prefill.
 *
 * @param WP_User|null $user User object.
 * @return array<string, string> Field ID => prefill value.
 */
function rytkoset_theme_get_membership_member_prefill_values( $user ) {
	$values = array(
		'rytkoset/member_1_name'  => rytkoset_theme_get_membership_member_prefill_name( $user ),
		'rytkoset/member_1_email' => rytkoset_theme_get_membership_member_prefill_email( $user ),
	);

	return array_filter( $values, 'strlen' );
}

/**
 * Enqueues the checkout script that suggests the logged-in buyer's own details
 * for member row 1.
 *
 * WooCommerce's server-side additional-field default filter
 * (woocommerce_get_default_value_for_*) proved unreliable in Block Checkout
 * hydration: on the first checkout visit the client store ignores the default,
 * and the customer-object read overrides values already saved on the draft
 * order. The script instead fills only still-empty member 1 fields once via
 * the checkout data store, so user-edited or draft-persisted values are never
 * overwritten.
 *
 * @return void
 */
function rytkoset_theme_enqueue_membership_checkout_prefill() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	if ( ! rytkoset_theme_membership_member_prefill_is_available() ) {
		return;
	}

	$values = rytkoset_theme_get_membership_member_prefill_values( wp_get_current_user() );

	if ( empty( $values ) ) {
		return;
	}

	$script_path = '/assets/js/membership-checkout-prefill.js';

	wp_enqueue_script(
		'rytkoset-membership-checkout-prefill',
		get_template_directory_uri() . $script_path,
		array( 'wp-data' ),
		rytkoset_theme_get_asset_version( get_template_directory() . $script_path ),
		true
	);

	wp_add_inline_script(
		'rytkoset-membership-checkout-prefill',
		'window.rytkosetMembershipPrefill = ' . wp_json_encode( $values ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_enqueue_membership_checkout_prefill' );

/**
 * Enqueues the dynamic member row controls for the family membership checkout (#520).
 *
 * The script renders the "Jäsentiedot" section heading above the member rows
 * and, for family memberships (more than one allowed row), the "+ Lisää
 * jäsen" button and per-row remove buttons that post the desired row count to
 * the Store API via extensionCartUpdate. Loaded whenever the cart shows
 * member rows at all.
 *
 * @return void
 */
function rytkoset_theme_enqueue_membership_checkout_rows() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	$bounds = rytkoset_theme_get_membership_member_row_bounds();

	if ( $bounds['max'] < 1 ) {
		return;
	}

	$script_path = '/assets/js/membership-checkout-rows.js';

	wp_enqueue_script(
		'rytkoset-membership-checkout-rows',
		get_template_directory_uri() . $script_path,
		array( 'wp-data', 'wc-blocks-checkout' ),
		rytkoset_theme_get_asset_version( get_template_directory() . $script_path ),
		true
	);

	$config = array(
		'namespace' => rytkoset_theme_get_membership_store_api_namespace(),
		'i18n'      => array(
			'add'         => __( 'Lisää jäsen', 'rytkoset-theme' ),
			/* translators: %d: member row number. */
			'remove'      => __( 'Poista jäsen %d', 'rytkoset-theme' ),
			/* translators: 1: visible member row count, 2: maximum member row count. */
			'count'       => __( '%1$d / %2$d jäsentä', 'rytkoset-theme' ),
			'maxNote'     => __( 'Enimmäismäärä täynnä.', 'rytkoset-theme' ),
			/* translators: %d: member row number. */
			'added'       => __( 'Jäsen %d lisätty', 'rytkoset-theme' ),
			/* translators: %d: member row number. */
			'removed'     => __( 'Jäsen %d poistettu', 'rytkoset-theme' ),
			'error'       => __( 'Jäsenrivin päivitys epäonnistui. Yritä uudelleen.', 'rytkoset-theme' ),
			'heading'     => __( 'Jäsentiedot', 'rytkoset-theme' ),
			'introFamily' => __( 'Kirjaa perheenjäsenet jäsenrekisteriä varten. Lisärivien sähköpostiosoitteet ovat valinnaisia.', 'rytkoset-theme' ),
			'introSingle' => __( 'Kirjaa jäsenen nimi ja sähköpostiosoite jäsenrekisteriä varten.', 'rytkoset-theme' ),
		),
	);

	wp_add_inline_script(
		'rytkoset-membership-checkout-rows',
		'window.rytkosetMembershipRows = ' . wp_json_encode( $config ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_enqueue_membership_checkout_rows' );

/**
 * Returns structured member rows saved on an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_get_membership_order_members( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$members  = array();
	$max_rows = rytkoset_theme_get_membership_max_member_rows();

	for ( $index = 1; $index <= $max_rows; $index++ ) {
		$name  = trim(
			rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/member_%d_name', $index ) )
		);
		$email = trim(
			rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/member_%d_email', $index ) )
		);

		if ( '' === $name && '' === $email ) {
			continue;
		}

		$members[] = array(
			'name'  => $name,
			'email' => $email,
		);
	}

	return $members;
}

/**
 * Returns the member row indices that contain data on an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, int>
 */
function rytkoset_theme_get_membership_visible_member_indices( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$indices  = array();
	$max_rows = rytkoset_theme_get_membership_max_member_rows();

	for ( $index = 1; $index <= $max_rows; $index++ ) {
		$name  = trim(
			rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/member_%d_name', $index ) )
		);
		$email = trim(
			rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/member_%d_email', $index ) )
		);

		if ( '' !== $name || '' !== $email ) {
			$indices[] = $index;
		}
	}

	return $indices;
}

/**
 * Removes WooCommerce's order meta prefix from a member field ID.
 *
 * @param string $field_id Field ID or order meta key.
 * @return string
 */
function rytkoset_theme_normalize_membership_member_field_id( $field_id ) {
	$field_id = (string) $field_id;
	$prefix   = '_wc_other/';

	if ( 0 === strpos( $field_id, $prefix ) ) {
		return substr( $field_id, strlen( $prefix ) );
	}

	return $field_id;
}

/**
 * Parses a member row index from an additional checkout field ID.
 *
 * @param string $field_id Field ID or order meta key.
 * @return int Member row index, or 0 when the field is not a membership member field.
 */
function rytkoset_theme_get_membership_member_index_from_field_id( $field_id ) {
	$field_id = rytkoset_theme_normalize_membership_member_field_id( $field_id );

	if ( ! preg_match( '/^rytkoset\/member_(\d+)_(?:name|email)$/', $field_id, $matches ) ) {
		return 0;
	}

	return absint( $matches[1] );
}

/**
 * Hides empty member fields from order confirmation views and emails.
 *
 * @param bool                 $show    Whether WooCommerce would show the field.
 * @param array<string, mixed> $field   Field data.
 * @param array<string, mixed> $fields  All fields in the current confirmation context.
 * @param array<string, mixed> $context Confirmation context.
 * @return bool
 */
function rytkoset_theme_filter_membership_order_confirmation_fields( $show, $field, $fields, $context ) {
	$field_id = rytkoset_theme_get_order_confirmation_checkout_field_id( $field, $fields );
	$index    = rytkoset_theme_get_membership_member_index_from_field_id( $field_id );

	if ( $index < 1 ) {
		return $show;
	}

	$order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;

	if ( ! $order instanceof WC_Order ) {
		return $show;
	}

	return $show && in_array( $index, rytkoset_theme_get_membership_visible_member_indices( $order ), true );
}
add_filter( 'woocommerce_filter_fields_for_order_confirmation', 'rytkoset_theme_filter_membership_order_confirmation_fields', 10, 4 );

/**
 * Removes empty member fields from WooCommerce admin order fields.
 *
 * @param array<string, mixed> $fields Admin field definitions.
 * @param WC_Order|null        $order  Order object.
 * @return array<string, mixed>
 */
function rytkoset_theme_filter_membership_admin_order_fields( $fields, $order = null ) {
	if ( ! $order instanceof WC_Order ) {
		return $fields;
	}

	$visible = rytkoset_theme_get_membership_visible_member_indices( $order );

	foreach ( $fields as $field_key => $field ) {
		$field_id = is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : (string) $field_key;
		$index    = rytkoset_theme_get_membership_member_index_from_field_id( $field_id );

		if ( $index > 0 && ! in_array( $index, $visible, true ) ) {
			unset( $fields[ $field_key ] );
		}
	}

	return $fields;
}
add_filter( 'woocommerce_admin_shipping_fields', 'rytkoset_theme_filter_membership_admin_order_fields', 20, 2 );

/**
 * Deletes empty member field meta from new Store API orders.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_cleanup_membership_empty_member_order_meta( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$visible      = rytkoset_theme_get_membership_visible_member_indices( $order );
	$max_rows     = rytkoset_theme_get_membership_max_member_rows();
	$deleted_meta = false;

	for ( $index = 1; $index <= $max_rows; $index++ ) {
		if ( in_array( $index, $visible, true ) ) {
			continue;
		}

		foreach ( rytkoset_theme_get_membership_member_field_ids( $index ) as $field_id ) {
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
add_action( 'woocommerce_store_api_checkout_order_processed', 'rytkoset_theme_cleanup_membership_empty_member_order_meta', 20 );

// ---------------------------------------------------------------------------
// Automatic membership update from WooCommerce order (#302)
// ---------------------------------------------------------------------------

/**
 * Maps a WooCommerce membership product type to the user membership type.
 *
 * @param string $product_type Product membership type (annual_individual, annual_family, lifetime).
 * @return string User membership type (annual, family, lifetime), or '' when unmapped.
 */
function rytkoset_theme_map_product_to_user_membership_type( $product_type ) {
	$map = array(
		'annual_individual' => 'annual',
		'annual_family'     => 'family',
		'lifetime'          => 'lifetime',
	);

	return isset( $map[ $product_type ] ) ? $map[ $product_type ] : '';
}

/**
 * Returns the sort priority of a user membership type (higher = better).
 *
 * @param string $user_type User membership type.
 * @return int
 */
function rytkoset_theme_get_user_membership_type_priority( $user_type ) {
	$priorities = array(
		'annual'   => 1,
		'family'   => 2,
		'lifetime' => 3,
	);

	return isset( $priorities[ $user_type ] ) ? $priorities[ $user_type ] : 0;
}

/**
 * Resolves the best membership to apply from a list of membership order items.
 *
 * When multiple membership products are present, lifetime beats time-bound types,
 * family beats individual annual membership, and the latest expiry wins between
 * items of the same membership type.
 *
 * @param array<int, array<string, mixed>> $membership_items Result of rytkoset_theme_get_membership_order_items().
 * @return array{type:string,period:string,expires:string}|array<never> Best membership, or empty array when nothing is applicable.
 */
function rytkoset_theme_resolve_order_membership( $membership_items ) {
	$best             = null;
	$best_priority    = 0;
	$best_expiry_rank = 0;

	foreach ( $membership_items as $item ) {
		$user_type = rytkoset_theme_map_product_to_user_membership_type( (string) $item['type'] );

		if ( '' === $user_type ) {
			continue;
		}

		$priority = rytkoset_theme_get_user_membership_type_priority( $user_type );
		$period   = (string) $item['period'];
		$expires  = ( 'lifetime' !== $user_type ) ? (string) $item['expiry_date'] : '';

		// Lifetime has no expiry; rank it after time-bound dates so it always wins on priority.
		$expiry_rank = '' !== $expires ? (int) str_replace( '-', '', $expires ) : 0;

		$is_better = ( null === $best )
			|| ( $priority > $best_priority )
			|| ( $priority === $best_priority && $expiry_rank > $best_expiry_rank );

		if ( $is_better ) {
			$best             = array(
				'type'    => $user_type,
				'period'  => $period,
				'expires' => $expires,
			);
			$best_priority    = $priority;
			$best_expiry_rank = $expiry_rank;
		}
	}

	return is_array( $best ) ? $best : array();
}

/**
 * Returns the order meta key used to mark a membership order as processed.
 *
 * Set only after a valid membership was applied or safely skipped by the
 * never-shorten rule. Invalid product metadata intentionally leaves the order
 * retryable. A guest order without an account is marked awaiting instead (#518)
 * so a later registration can still apply it.
 *
 * @return string
 */
function rytkoset_theme_get_membership_order_processed_meta_key() {
	return '_rytkoset_membership_order_processed';
}

/**
 * Returns the order meta key marking family member rows as persisted (#519).
 *
 * @return string
 */
function rytkoset_theme_get_family_members_processed_meta_key() {
	return '_rytkoset_family_members_processed';
}

/**
 * Returns the order meta key storing family-member account notices sent (#519).
 *
 * The value is an associative array keyed by normalized email address. Keeping
 * the marker per recipient prevents duplicate messages when an order moves from
 * processing to completed.
 *
 * @return string
 */
function rytkoset_theme_get_family_account_notices_sent_meta_key() {
	return '_rytkoset_family_account_notices_sent';
}

/**
 * Returns the order meta key marking a guest membership order as awaiting a site account (#518).
 *
 * @return string
 */
function rytkoset_theme_get_membership_awaiting_account_meta_key() {
	return '_rytkoset_membership_awaiting_account';
}

/**
 * Returns the order meta key marking that the "create an account" notice email was sent (#518).
 *
 * @return string
 */
function rytkoset_theme_get_membership_account_notice_sent_meta_key() {
	return '_rytkoset_membership_account_notice_sent';
}

/**
 * Returns true when a membership order is still awaiting a site account (#518).
 *
 * Awaiting = the guest path marked the order, and no user-matched processing has
 * locked it via the processed meta.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_order_membership_is_awaiting_account( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	return '' !== (string) $order->get_meta( rytkoset_theme_get_membership_awaiting_account_meta_key(), true )
		&& '' === (string) $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key(), true );
}

/**
 * Returns true when an order contains a family membership product (#519).
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_order_has_family_membership( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	foreach ( rytkoset_theme_get_membership_order_items( $order ) as $item ) {
		if ( 'annual_family' === (string) $item['type'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolves the WordPress user associated with a membership order.
 *
 * The linked order user wins. Guest orders fall back to an existing account
 * whose email matches the billing email, following the #518 membership path.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return int User ID, or 0 when no account can be resolved.
 */
function rytkoset_theme_get_membership_order_user_id( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return 0;
	}

	$user_id = absint( $order->get_user_id() );

	if ( $user_id > 0 && get_userdata( $user_id ) instanceof WP_User ) {
		return $user_id;
	}

	$email = rytkoset_theme_normalize_family_member_email( (string) $order->get_billing_email() );

	if ( '' === $email ) {
		return 0;
	}

	$user = get_user_by( 'email', $email );

	return $user instanceof WP_User ? absint( $user->ID ) : 0;
}

/**
 * Normalizes and deduplicates checkout member rows before family-list storage.
 *
 * Rows whose email belongs to the buyer are omitted to prevent self-linking.
 * Other duplicate emails keep their first occurrence. Rows without an email
 * remain separate name/register rows.
 *
 * @param array<int, array<string, mixed>> $members         Raw checkout member rows.
 * @param string[]                         $excluded_emails Buyer/primary email addresses.
 * @return array<int, array{name:string,email:string}>
 */
function rytkoset_theme_prepare_family_order_members( $members, $excluded_emails = array() ) {
	$excluded = array();

	foreach ( $excluded_emails as $excluded_email ) {
		$email = rytkoset_theme_normalize_family_member_email( (string) $excluded_email );

		if ( '' !== $email ) {
			$excluded[ $email ] = true;
		}
	}

	$prepared   = array();
	$seen_email = array();

	foreach ( is_array( $members ) ? $members : array() as $member ) {
		if ( ! is_array( $member ) ) {
			continue;
		}

		$name  = sanitize_text_field( (string) ( $member['name'] ?? '' ) );
		$email = rytkoset_theme_normalize_family_member_email( (string) ( $member['email'] ?? '' ) );

		if ( '' !== $email && ( isset( $excluded[ $email ] ) || isset( $seen_email[ $email ] ) ) ) {
			continue;
		}

		if ( '' === $name && '' === $email ) {
			continue;
		}

		if ( '' !== $email ) {
			$seen_email[ $email ] = true;
		}

		$prepared[] = array(
			'name'  => $name,
			'email' => $email,
		);
	}

	return $prepared;
}

/**
 * Upserts one normalized family row into a family member list.
 *
 * Email is the primary identity; a linked user ID is the fallback. Rows with
 * neither remain append-only and rely on the order-level processed marker for
 * idempotency.
 *
 * @param array<int, array<string, mixed>> $members Existing family member rows.
 * @param array<string, mixed>             $incoming Incoming normalized row.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_upsert_family_order_member( $members, $incoming ) {
	$email          = isset( $incoming['email'] ) ? (string) $incoming['email'] : '';
	$linked_user_id = isset( $incoming['linked_user_id'] ) ? absint( $incoming['linked_user_id'] ) : 0;

	foreach ( $members as $index => $member ) {
		$member_email   = isset( $member['email'] ) ? (string) $member['email'] : '';
		$member_user_id = isset( $member['linked_user_id'] ) ? absint( $member['linked_user_id'] ) : 0;
		$email_matches  = '' !== $email && $member_email === $email;
		$user_matches   = $linked_user_id > 0 && $member_user_id === $linked_user_id;

		if ( ! $email_matches && ! $user_matches ) {
			continue;
		}

		$members[ $index ] = array_merge( $member, $incoming );

		return array_values( $members );
	}

	$members[] = $incoming;

	return array_values( $members );
}

/**
 * Returns true when checkout member rows include a normalized email address.
 *
 * @param array<int, array<string, mixed>> $members Checkout member rows.
 * @param string                           $email   Email to find.
 * @return bool
 */
function rytkoset_theme_family_order_members_include_email( $members, $email ) {
	$email = rytkoset_theme_normalize_family_member_email( (string) $email );

	if ( '' === $email ) {
		return false;
	}

	foreach ( is_array( $members ) ? $members : array() as $member ) {
		if (
			is_array( $member )
			&& rytkoset_theme_normalize_family_member_email( (string) ( $member['email'] ?? '' ) ) === $email
		) {
			return true;
		}
	}

	return false;
}

/**
 * Sends the "create an account" notice to a guest membership buyer (#518).
 *
 * Plain-text Finnish email to the billing address: the membership fee was
 * received, member benefits need a site account, and registering with this
 * same email address activates the membership automatically. Sender comes
 * from the inc/email.php wp_mail filters.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool True when the email was sent.
 */
function rytkoset_theme_send_membership_account_notice_email( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	$email = trim( (string) $order->get_billing_email() );

	if ( ! is_email( $email ) ) {
		return false;
	}

	$subject = __( 'Jäsenmaksusi on vastaanotettu – luo tili jäsenetuja varten', 'rytkoset-theme' );

	$lines = array(
		__( 'Hei,', 'rytkoset-theme' ),
		'',
		__( 'kiitos! Jäsenmaksusi Rytkösten sukuseura ry:lle on vastaanotettu.', 'rytkoset-theme' ),
		'',
		__( 'Jäsenedut sivustolla (jäsenille rajatut sivut, jäsenhinnat ja digilehdet) vaativat käyttäjätilin. Jäsenyytesi automaattinen aktivointi odottaa, että sinulla on tili sivustolla.', 'rytkoset-theme' ),
		'',
		sprintf(
			/* translators: %s: buyer's billing email address. */
			__( 'Luo tili tällä samalla sähköpostiosoitteella (%s), niin jäsenyytesi aktivoituu automaattisesti:', 'rytkoset-theme' ),
			$email
		),
		wp_registration_url(),
	);

	$contact_email = rytkoset_theme_get_contact_email();

	$lines[] = '';

	if ( is_email( $contact_email ) ) {
		$lines[] = sprintf(
			/* translators: %s: association contact email address. */
			__( 'Kysymyksissä voit olla yhteydessä: %s', 'rytkoset-theme' ),
			$contact_email
		);
		$lines[] = '';
	}

	$lines[] = __( 'Terveisin', 'rytkoset-theme' );
	$lines[] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	return wp_mail(
		$email,
		$subject,
		implode( "\n", $lines ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Sends a data-source notice to a family member entered at checkout (#519).
 *
 * The message states where the address came from and avoids disclosing order
 * details or the buyer's identity. Unknown addresses also receive account
 * registration instructions.
 *
 * @param string $email         Family member email address.
 * @param bool   $needs_account Whether the address still needs a user account.
 * @return bool True when the email was sent.
 */
function rytkoset_theme_send_family_member_account_notice_email( $email, $needs_account = true ) {
	$email = rytkoset_theme_normalize_family_member_email( (string) $email );

	if ( '' === $email ) {
		return false;
	}

	$subject = $needs_account
		? __( 'Perhejäsenyytesi odottaa käyttäjätiliä', 'rytkoset-theme' )
		: __( 'Sinut on ilmoitettu perhejäseneksi', 'rytkoset-theme' );
	$lines   = array(
		__( 'Hei,', 'rytkoset-theme' ),
		'',
		__( 'Sähköpostiosoitteesi on ilmoitettu Rytkösten sukuseura ry:n perhejäsenmaksun yhteydessä.', 'rytkoset-theme' ),
	);

	if ( $needs_account ) {
		$lines[] = '';
		$lines[] = __( 'Jäsenedut sivustolla (jäsenille rajatut sivut, jäsenhinnat ja digilehdet) vaativat henkilökohtaisen käyttäjätilin.', 'rytkoset-theme' );
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: family member email address. */
			__( 'Luo tili tällä samalla sähköpostiosoitteella (%s), niin perhejäsenyys linkittyy tiliisi automaattisesti:', 'rytkoset-theme' ),
			$email
		);
		$lines[] = wp_registration_url();
	} else {
		$lines[] = '';
		$lines[] = __( 'Perhejäsenyys on linkitetty tällä sähköpostiosoitteella olevaan käyttäjätiliisi.', 'rytkoset-theme' );
	}

	$privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

	if ( '' !== $privacy_url ) {
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: privacy statement URL. */
			__( 'Tietosuojaselosteessa kerrotaan tarkemmin tietojen käsittelystä ja oikeuksistasi: %s', 'rytkoset-theme' ),
			$privacy_url
		);
	}

	$contact_email = rytkoset_theme_get_contact_email();

	$lines[] = '';

	if ( is_email( $contact_email ) ) {
		$lines[] = sprintf(
			/* translators: %s: association contact email address. */
			__( 'Jos osoite on ilmoitettu virheellisesti tai sinulla on kysyttävää, ota yhteyttä: %s', 'rytkoset-theme' ),
			$contact_email
		);
		$lines[] = '';
	}

	$lines[] = __( 'Terveisin', 'rytkoset-theme' );
	$lines[] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	return wp_mail(
		$email,
		$subject,
		implode( "\n", $lines ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Persists family checkout rows under the order's primary account (#519).
 *
 * Existing accounts are linked immediately. Unknown emails become
 * `pending_account` rows. Every non-primary address receives one data-source
 * notice per order; unknown addresses also receive account instructions. Rows
 * without an email are retained as register-only data. The family list write is
 * atomic; the processed marker is set only after the #524 helper accepts the
 * full list.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return true|false|WP_Error True when handled, false when not applicable or awaiting a primary account.
 */
function rytkoset_theme_process_family_members_from_order( $order ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_order_has_family_membership( $order ) ) {
		return false;
	}

	$primary_user_id = rytkoset_theme_get_membership_order_user_id( $order );

	if ( $primary_user_id <= 0 ) {
		return false;
	}

	$primary_user   = get_userdata( $primary_user_id );
	$excluded_email = array( (string) $order->get_billing_email() );

	if ( $primary_user instanceof WP_User ) {
		$excluded_email[] = (string) $primary_user->user_email;
	}

	$order_user = get_userdata( absint( $order->get_user_id() ) );

	if ( $order_user instanceof WP_User ) {
		$excluded_email[] = (string) $order_user->user_email;
	}

	$incoming      = rytkoset_theme_prepare_family_order_members(
		rytkoset_theme_get_membership_order_members( $order ),
		$excluded_email
	);
	$processed_key = rytkoset_theme_get_family_members_processed_meta_key();
	$is_processed  = '' !== (string) $order->get_meta( $processed_key, true );
	$order_changed = false;

	if ( ! $is_processed ) {
		$members         = rytkoset_theme_get_family_members( $primary_user_id );
		$source_order_id = absint( $order->get_id() );

		foreach ( $incoming as $member ) {
			$linked_user_id = 0;

			if ( '' !== $member['email'] ) {
				$linked_user = get_user_by( 'email', $member['email'] );

				if ( $linked_user instanceof WP_User ) {
					$linked_user_id = absint( $linked_user->ID );
				}
			}

			$members = rytkoset_theme_upsert_family_order_member(
				$members,
				array(
					'name'            => $member['name'],
					'email'           => $member['email'],
					'linked_user_id'  => $linked_user_id,
					'status'          => $linked_user_id > 0 ? 'active' : 'pending_account',
					'source_order_id' => $source_order_id,
					'updated_at'      => current_time( 'mysql' ),
				)
			);
		}

		$updated = rytkoset_theme_update_family_members( $primary_user_id, $members );

		if ( is_wp_error( $updated ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: family-list validation error. */
					__( 'Perhejäsenrivejä ei voitu tallentaa päätilille: %s', 'rytkoset-theme' ),
					$updated->get_error_message()
				),
				false
			);
			$order->save();

			return $updated;
		}

		$order->update_meta_data( $processed_key, current_time( 'mysql' ) );
		$order->add_order_note(
			sprintf(
				/* translators: %d: number of stored family member rows from the order. */
				__( 'Perhejäsenmaksun jäsenrivejä käsiteltiin %d ja ne tallennettiin päätilin perhejäsenlistaan.', 'rytkoset-theme' ),
				count( $incoming )
			),
			false
		);
		$order_changed = true;
	}

	$stored_members = rytkoset_theme_get_family_members( $primary_user_id );
	$sent_key       = rytkoset_theme_get_family_account_notices_sent_meta_key();
	$sent_notices   = $order->get_meta( $sent_key, true );
	$sent_notices   = is_array( $sent_notices ) ? $sent_notices : array();

	foreach ( $incoming as $member ) {
		$email = $member['email'];

		if ( '' === $email || isset( $sent_notices[ $email ] ) ) {
			continue;
		}

		$stored_status = '';

		foreach ( $stored_members as $stored_member ) {
			if ( $email === $stored_member['email'] ) {
				$stored_status = $stored_member['status'];
				break;
			}
		}

		if ( '' === $stored_status ) {
			continue;
		}

		if ( rytkoset_theme_send_family_member_account_notice_email( $email, 'pending_account' === $stored_status ) ) {
			$sent_notices[ $email ] = current_time( 'mysql' );
			$order->update_meta_data( $sent_key, $sent_notices );
			$order_changed = true;
		}
	}

	if ( $order_changed ) {
		$order->save();
	}

	return true;
}

/**
 * Returns paid family membership orders containing a member email (#519).
 *
 * The query uses WooCommerce's storage-agnostic order API and limits candidates
 * to accepted statuses that have already passed the theme's family-row processing.
 * Member-field meta queries are HPOS-only, so the remaining candidate set is
 * filtered through order objects to keep legacy storage compatible without direct SQL.
 *
 * @param string $email Family member email address.
 * @return WC_Order[]
 */
function rytkoset_theme_get_family_membership_orders_for_email( $email ) {
	$email = rytkoset_theme_normalize_family_member_email( (string) $email );

	if ( '' === $email || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$orders = wc_get_orders(
		array(
			'status'     => array( 'processing', 'completed' ),
			'limit'      => -1,
			'return'     => 'objects',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Theme-owned marker prevents hydrating the full paid-order history.
				array(
					'key'     => rytkoset_theme_get_family_members_processed_meta_key(),
					'compare' => 'EXISTS',
				),
			),
		)
	);

	if ( ! is_array( $orders ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$orders,
			static function ( $order ) use ( $email ) {
				return $order instanceof WC_Order
					&& rytkoset_theme_order_has_family_membership( $order )
					&& rytkoset_theme_family_order_members_include_email(
						rytkoset_theme_get_membership_order_members( $order ),
						$email
					);
			}
		)
	);
}

/**
 * Links a newly registered user to their pending family member row (#519).
 *
 * @param WC_Order $order   Family membership order containing the member email.
 * @param int      $user_id Newly registered user ID.
 * @return true|false|WP_Error True when linked/already linked, false when not applicable.
 */
function rytkoset_theme_link_family_member_account_from_order( $order, $user_id ) {
	$user = get_userdata( absint( $user_id ) );

	if ( ! $order instanceof WC_Order || ! $user instanceof WP_User ) {
		return false;
	}

	$email = rytkoset_theme_normalize_family_member_email( (string) $user->user_email );

	if (
		'' === $email
		|| ! rytkoset_theme_order_has_family_membership( $order )
		|| ! rytkoset_theme_family_order_members_include_email(
			rytkoset_theme_get_membership_order_members( $order ),
			$email
		)
	) {
		return false;
	}

	$primary_user_id = rytkoset_theme_get_membership_order_user_id( $order );

	if ( 0 >= $primary_user_id || absint( $user->ID ) === $primary_user_id ) {
		return false;
	}

	$processed = rytkoset_theme_process_family_members_from_order( $order );

	if ( is_wp_error( $processed ) ) {
		return $processed;
	}

	$members = rytkoset_theme_get_family_members( $primary_user_id );

	foreach ( $members as $index => $member ) {
		if ( $email !== $member['email'] ) {
			continue;
		}

		if ( absint( $member['linked_user_id'] ) === absint( $user->ID ) && 'active' === $member['status'] ) {
			return true;
		}

		$members[ $index ]['linked_user_id'] = absint( $user->ID );
		$members[ $index ]['status']         = 'active';
		$members[ $index ]['updated_at']     = current_time( 'mysql' );
		$updated                             = rytkoset_theme_update_family_members( $primary_user_id, $members );

		if ( is_wp_error( $updated ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: family-account linking validation error. */
					__( 'Perheenjäsenen käyttäjätiliä ei voitu linkittää: %s', 'rytkoset-theme' ),
					$updated->get_error_message()
				),
				false
			);
			$order->save();

			return $updated;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: linked family member email address. */
				__( 'Perheenjäsenen käyttäjätili linkitettiin automaattisesti sähköpostilla %s.', 'rytkoset-theme' ),
				$email
			),
			false
		);
		$order->save();

		return true;
	}

	return false;
}

/**
 * Marks a guest membership order as awaiting a site account (#518).
 *
 * The processed meta is intentionally not set, so a later registration with the
 * same billing email can still apply the membership. Runs once per order:
 * repeated processing/completed transitions return without a new note or email.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_mark_membership_order_awaiting_account( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( '' !== (string) $order->get_meta( rytkoset_theme_get_membership_awaiting_account_meta_key(), true ) ) {
		return;
	}

	$order->update_meta_data( rytkoset_theme_get_membership_awaiting_account_meta_key(), current_time( 'mysql' ) );

	if ( rytkoset_theme_send_membership_account_notice_email( $order ) ) {
		$order->update_meta_data( rytkoset_theme_get_membership_account_notice_sent_meta_key(), current_time( 'mysql' ) );
		$order->add_order_note(
			__( 'Jäsenmaksutilausta ei voitu yhdistää WordPress-käyttäjään. Ostajalle lähetettiin laskutussähköpostiin ohje luoda tili: jäsenyys kytkeytyy automaattisesti, kun tili luodaan samalla sähköpostiosoitteella. Tarvittaessa jäsenyyden voi asettaa manuaalisesti käyttäjähallinnassa.', 'rytkoset-theme' ),
			false
		);
	} else {
		$order->add_order_note(
			__( 'Jäsenmaksutilausta ei voitu yhdistää WordPress-käyttäjään, eikä ostajalle voitu lähettää ohjetta tilin luontiin, koska laskutussähköposti puuttuu tai on epäkelpo. Tarkista ja aseta jäsenyystiedot manuaalisesti käyttäjähallinnassa.', 'rytkoset-theme' ),
			false
		);
	}

	$order->save();
}

/**
 * Applies membership from a WooCommerce order to the associated WordPress user.
 *
 * Idempotent: a processed order is marked with order meta so status transitions
 * (e.g. on-hold → processing → completed) do not apply the membership twice.
 * A guest order without a matching account is marked awaiting instead of
 * processed (#518), so a later registration with the same billing email can
 * still run this function successfully.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_apply_membership_from_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$processed_at = (string) $order->get_meta( rytkoset_theme_get_membership_order_processed_meta_key(), true );

	if ( '' !== $processed_at ) {
		return;
	}

	$membership_items = rytkoset_theme_get_membership_order_items( $order );

	if ( empty( $membership_items ) ) {
		return;
	}

	$validation_errors = array();

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		foreach ( rytkoset_theme_get_membership_product_validation_errors( $product ) as $code => $message ) {
			$validation_errors[ $code ] = $message;
		}
	}

	if ( ! empty( $validation_errors ) ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: membership product validation errors. */
				__( 'Jäsenyyttä ei aktivoitu: %s Korjaa jäsenmaksutuotteen asetukset ja käsittele tilaus uudelleen. Tilausta ei merkitty käsitellyksi.', 'rytkoset-theme' ),
				implode( ' ', $validation_errors )
			),
			false
		);
		$order->save();
		return;
	}

	$user_id = rytkoset_theme_get_membership_order_user_id( $order );

	if ( ! $user_id ) {
		rytkoset_theme_mark_membership_order_awaiting_account( $order );
		return;
	}

	$membership = rytkoset_theme_resolve_order_membership( $membership_items );

	if ( empty( $membership ) ) {
		$order->add_order_note(
			__( 'Jäsenmaksun automaattista jäsenyyttä ei voitu määrittää: tilauksen jäsenmaksutuotteelta puuttuu jäsenmaksun tyyppi. Tarkista tuotteen asetukset ja aseta jäsenyys tarvittaessa manuaalisesti.', 'rytkoset-theme' ),
			false
		);
		$order->save();
		return;
	}

	$current = rytkoset_theme_get_user_membership( $user_id );

	// A purchase must never shorten an existing membership. Lifetime is permanent, and a
	// time-bound purchase that does not extend the current expiry date is ignored so a
	// mistaken or duplicate order cannot reduce a member's standing.
	if ( 'lifetime' !== $membership['type'] ) {
		$current_covers_new = 'lifetime' === $current['type']
			|| (
				rytkoset_theme_user_has_own_active_membership( $user_id )
				&& '' !== $current['expires']
				&& '' !== $membership['expires']
				&& $current['expires'] >= $membership['expires']
			);

		if ( $current_covers_new ) {
			$order->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), current_time( 'mysql' ) );
			$order->add_order_note(
				__( 'Jäsenyystilaa ei muutettu: käyttäjällä on jo vähintään yhtä pitkään voimassa oleva jäsenyys.', 'rytkoset-theme' ),
				false
			);
			$order->save();
			return;
		}
	}

	$was_active  = rytkoset_theme_user_has_own_active_membership( $user_id );
	$type_key    = rytkoset_theme_get_user_membership_type_meta_key();
	$period_key  = rytkoset_theme_get_user_membership_period_meta_key();
	$expires_key = rytkoset_theme_get_user_membership_expires_meta_key();

	update_user_meta( $user_id, $type_key, $membership['type'] );

	if ( 'lifetime' === $membership['type'] ) {
		delete_user_meta( $user_id, $period_key );
		delete_user_meta( $user_id, $expires_key );
	} else {
		update_user_meta( $user_id, $period_key, $membership['period'] );
		update_user_meta( $user_id, $expires_key, $membership['expires'] );
	}

	$user       = get_userdata( $user_id );
	$type_label = rytkoset_theme_get_user_membership_type_label( $membership['type'] );

	$note_parts = array(
		sprintf(
			/* translators: 1: WordPress username, 2: membership type label. */
			__( 'Jäsenyystiedot päivitetty automaattisesti käyttäjälle %1$s (%2$s).', 'rytkoset-theme' ),
			$user instanceof WP_User ? $user->user_login : (string) $user_id,
			$type_label
		),
	);

	if ( '' !== $membership['period'] ) {
		$note_parts[] = sprintf(
			/* translators: %s: membership period (e.g. 2026-2029). */
			__( 'Jäsenkausi: %s.', 'rytkoset-theme' ),
			$membership['period']
		);
	}

	if ( '' !== $membership['expires'] ) {
		$note_parts[] = sprintf(
			/* translators: %s: expiry date (d.m.Y). */
			__( 'Voimassa asti: %s.', 'rytkoset-theme' ),
			rytkoset_theme_get_user_membership_expires_display( $membership['expires'] )
		);
	}

	$is_now_active = rytkoset_theme_user_has_own_active_membership( $user_id );

	$order->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), current_time( 'mysql' ) );
	$order->add_order_note( implode( ' ', $note_parts ), false );
	$order->save();

	if ( ! $was_active && $is_now_active ) {
		rytkoset_theme_send_membership_confirmation_email( $user_id );
	}
}

/**
 * Hook handler for order status transitions that trigger membership updates.
 *
 * @param int      $order_id WooCommerce order ID.
 * @param WC_Order $order    WooCommerce order object.
 * @return void
 */
function rytkoset_theme_maybe_apply_membership_from_order( $order_id, $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	rytkoset_theme_apply_membership_from_order( $order );
	rytkoset_theme_process_family_members_from_order( $order );
}
add_action( 'woocommerce_order_status_processing', 'rytkoset_theme_maybe_apply_membership_from_order', 10, 2 );
add_action( 'woocommerce_order_status_completed', 'rytkoset_theme_maybe_apply_membership_from_order', 10, 2 );

// ---------------------------------------------------------------------------
// Membership linking on account registration (#518, #519)
// ---------------------------------------------------------------------------

/**
 * Returns paid membership orders awaiting an account for a billing email (#518).
 *
 * @param string $email Billing email address.
 * @return WC_Order[]
 */
function rytkoset_theme_get_awaiting_membership_orders_for_email( $email ) {
	$email = trim( (string) $email );

	if ( ! is_email( $email ) || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$orders = wc_get_orders(
		array(
			'billing_email' => $email,
			'status'        => array( 'processing', 'completed' ),
			'limit'         => -1,
			'return'        => 'objects',
		)
	);

	if ( ! is_array( $orders ) ) {
		return array();
	}

	return array_values( array_filter( $orders, 'rytkoset_theme_order_membership_is_awaiting_account' ) );
}

/**
 * Applies awaiting guest membership orders when a new account is registered (#518).
 *
 * WordPress registration verifies control of the email address via the password
 * set link, so the membership cannot be used by the wrong person. Each awaiting
 * order goes through the same apply path (never-shorten rule, confirmation
 * email, order note) as a normal status transition; the apply resolves the new
 * user by billing email.
 *
 * @param int $user_id Newly registered user ID.
 * @return void
 */
function rytkoset_theme_apply_membership_on_user_register( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User ) {
		return;
	}

	foreach ( rytkoset_theme_get_awaiting_membership_orders_for_email( (string) $user->user_email ) as $order ) {
		rytkoset_theme_apply_membership_from_order( $order );
		rytkoset_theme_process_family_members_from_order( $order );
	}

	foreach ( rytkoset_theme_get_pending_family_primary_user_ids_for_email( (string) $user->user_email ) as $primary_user_id ) {
		rytkoset_theme_link_family_member_account_from_primary( $primary_user_id, (int) $user->ID );
	}

	foreach ( rytkoset_theme_get_family_membership_orders_for_email( (string) $user->user_email ) as $order ) {
		rytkoset_theme_link_family_member_account_from_order( $order, (int) $user->ID );
	}
}
add_action( 'user_register', 'rytkoset_theme_apply_membership_on_user_register' );
