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
		'<strong>J&auml;senmaksu:</strong> T&auml;yt&auml; j&auml;senen nimi ja s&auml;hk&ouml;postiosoite kassan kenttiin, jotta tiedot voidaan kirjata j&auml;senrekisteriin. Perhej&auml;senmaksussa voit lis&auml;t&auml; useamman perheenj&auml;senen &mdash; lis&auml;rivien s&auml;hk&ouml;postit ovat valinnaisia.',
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
	return 6;
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
 * Returns the number of member rows the checkout should show for the current cart.
 *
 * Individual and lifetime memberships need one row; family memberships need several.
 * Membership products without the names-required flag do not add rows.
 *
 * @return int
 */
function rytkoset_theme_get_membership_member_row_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	$rows = 0;

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_membership_product( $product ) ) {
			continue;
		}

		if ( ! rytkoset_theme_membership_product_requires_member_names( $product ) ) {
			continue;
		}

		$needed = ( 'annual_family' === rytkoset_theme_get_membership_product_type( $product ) )
			? rytkoset_theme_get_membership_max_member_rows()
			: 1;

		$rows = max( $rows, $needed );
	}

	return $rows;
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
	return array(
		'member_row_count' => rytkoset_theme_get_membership_member_row_count(),
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
 * Derives an ISO expiry date from a membership period string.
 *
 * A period like 2026-2029 expires on the last day of the end year (2029-12-31).
 *
 * @param string $period Membership period (e.g. 2026-2029).
 * @return string ISO expiry date (YYYY-MM-DD), or '' when the period is not recognized.
 */
function rytkoset_theme_get_membership_expiry_from_period( $period ) {
	if ( ! preg_match( '/^(\d{4})-(\d{4})$/', (string) $period, $matches ) ) {
		return '';
	}

	return sprintf( '%04d-12-31', (int) $matches[2] );
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
 * When multiple membership products are present, lifetime beats time-bound types.
 * Among equal types the longest period (by end year) wins.
 *
 * @param array<int, array<string, mixed>> $membership_items Result of rytkoset_theme_get_membership_order_items().
 * @return array{type:string,period:string,expires:string}|array<never> Best membership, or empty array when nothing is applicable.
 */
function rytkoset_theme_resolve_order_membership( $membership_items ) {
	$best          = null;
	$best_priority = 0;
	$best_end_year = 0;

	foreach ( $membership_items as $item ) {
		$user_type = rytkoset_theme_map_product_to_user_membership_type( (string) $item['type'] );

		if ( '' === $user_type ) {
			continue;
		}

		$priority = rytkoset_theme_get_user_membership_type_priority( $user_type );
		$period   = (string) $item['period'];
		$expires  = ( 'lifetime' !== $user_type )
			? rytkoset_theme_get_membership_expiry_from_period( $period )
			: '';
		$end_year = '' !== $expires ? (int) substr( $expires, 0, 4 ) : 0;

		$is_better = ( null === $best )
			|| ( $priority > $best_priority )
			|| ( $priority === $best_priority && $end_year > $best_end_year );

		if ( $is_better ) {
			$best          = array(
				'type'    => $user_type,
				'period'  => $period,
				'expires' => $expires,
			);
			$best_priority = $priority;
			$best_end_year = $end_year;
		}
	}

	return is_array( $best ) ? $best : array();
}

/**
 * Returns the order meta key used to mark a membership order as processed.
 *
 * @return string
 */
function rytkoset_theme_get_membership_order_processed_meta_key() {
	return '_rytkoset_membership_order_processed';
}

/**
 * Applies membership from a WooCommerce order to the associated WordPress user.
 *
 * Idempotent: a processed order is marked with order meta so status transitions
 * (e.g. on-hold → processing → completed) do not apply the membership twice.
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

	$user_id = (int) $order->get_user_id();

	if ( ! $user_id ) {
		$email = trim( (string) $order->get_billing_email() );

		if ( is_email( $email ) ) {
			$existing_user = get_user_by( 'email', $email );

			if ( $existing_user instanceof WP_User ) {
				$user_id = $existing_user->ID;
			}
		}
	}

	$order->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), current_time( 'mysql' ) );

	if ( ! $user_id ) {
		$order->add_order_note(
			__( 'Jäsenmaksun automaattinen jäsenyystilan päivitys ohitettiin: tilausta ei voitu yhdistää WordPress-käyttäjään. Tarkista ja aseta jäsenyystiedot manuaalisesti käyttäjähallinnassa.', 'rytkoset-theme' ),
			false
		);
		$order->save();
		return;
	}

	$membership = rytkoset_theme_resolve_order_membership( $membership_items );

	if ( empty( $membership ) ) {
		$order->save();
		return;
	}

	$current = rytkoset_theme_get_user_membership( $user_id );

	// Never downgrade an existing lifetime membership to a time-bound type.
	if ( 'lifetime' === $current['type'] && 'lifetime' !== $membership['type'] ) {
		$order->add_order_note(
			__( 'Jäsenyystilaa ei päivitetty: käyttäjällä on jo ainaisjäsenyys, eikä sitä korvata määräaikaisella jäsenyysmaksulla.', 'rytkoset-theme' ),
			false
		);
		$order->save();
		return;
	}

	$was_active  = rytkoset_theme_user_is_active_member( $user_id );
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

	$order->add_order_note( implode( ' ', $note_parts ), false );
	$order->save();

	if ( ! $was_active && rytkoset_theme_user_is_active_member( $user_id ) ) {
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
}
add_action( 'woocommerce_order_status_processing', 'rytkoset_theme_maybe_apply_membership_from_order', 10, 2 );
add_action( 'woocommerce_order_status_completed', 'rytkoset_theme_maybe_apply_membership_from_order', 10, 2 );
