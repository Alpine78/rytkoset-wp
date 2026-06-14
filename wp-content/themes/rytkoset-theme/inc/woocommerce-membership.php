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

	return sanitize_text_field(
		(string) $product->get_meta( rytkoset_theme_get_membership_period_meta_key(), true )
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

	woocommerce_wp_checkbox(
		array(
			'id'          => rytkoset_theme_get_member_names_required_meta_key(),
			'label'       => __( 'Vaatii jäsenten nimet kassalla', 'rytkoset-theme' ),
			'description' => __( 'Näyttää kassalla ohjeen nimien lisäämisestä Lisätietoja-kenttään.', 'rytkoset-theme' ),
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
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$product->update_meta_data( rytkoset_theme_get_membership_product_meta_key(), $is_membership_product );
	$product->update_meta_data( rytkoset_theme_get_member_names_required_meta_key(), $requires_names );

	if ( 'yes' !== $is_membership_product ) {
		$product->delete_meta_data( rytkoset_theme_get_membership_type_meta_key() );
		$product->delete_meta_data( rytkoset_theme_get_membership_period_meta_key() );
		return;
	}

	$product->update_meta_data( rytkoset_theme_get_membership_type_meta_key(), $type );

	if ( 'lifetime' === $type ) {
		$product->delete_meta_data( rytkoset_theme_get_membership_period_meta_key() );
		return;
	}

	$product->update_meta_data( rytkoset_theme_get_membership_period_meta_key(), $period );
}
add_action( 'woocommerce_admin_process_product_object', 'rytkoset_theme_save_membership_product_fields' );

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
 * Builds the checkout notice shown for membership payments.
 *
 * @return string
 */
function rytkoset_theme_get_membership_checkout_notice_markup() {
	$notice_text = html_entity_decode(
		'<strong>J&auml;senmaksu:</strong> Valitse kassalla <strong>Lis&auml;&auml; muistiinpano tilaukseesi</strong> ja kirjoita muistiinpanoon j&auml;senen tai j&auml;senten nimet ja s&auml;hk&ouml;postiosoitteet, jotta tiedot voidaan kirjata j&auml;senrekisteriin.',
		ENT_QUOTES,
		'UTF-8'
	);

	return sprintf(
		'<div class="rytkoset-checkout-note" role="note"><p>%s</p></div>',
		wp_kses_post( $notice_text )
	);
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

	if ( '' !== $customer_note ) {
		echo '<p><strong>' . esc_html__( 'Tilauksen lisätiedot:', 'rytkoset-theme' ) . '</strong><br>';
		echo wp_kses_post( nl2br( esc_html( $customer_note ) ) );
		echo '</p>';
	} elseif ( $requires_names ) {
		echo '<p><strong>' . esc_html__( 'Huomio:', 'rytkoset-theme' ) . '</strong> ';
		echo esc_html__( 'Vuosijäsenmaksun lisätietokenttä on tyhjä. Tarkista tarvittaessa jäsenen tai perheenjäsenten nimet ennen jäsenrekisteriin vientiä.', 'rytkoset-theme' );
		echo '</p>';
	}

	echo '<p>' . esc_html__( 'Käsittely: tarkista tilisiirtomaksu, merkitse tilaus käsitellyksi tai valmiiksi ja vie jäsenmaksun tiedot manuaalisesti jäsenrekisteriin.', 'rytkoset-theme' ) . '</p>';
}
