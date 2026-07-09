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

	return sanitize_text_field(
		(string) $product->get_meta( rytkoset_theme_get_membership_period_meta_key(), true )
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
 * Among time-bound types the latest expiry date wins.
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
 * Set only when the order could be matched to a WordPress user (whether or not
 * the membership was ultimately applied). A guest order without an account is
 * marked awaiting instead (#518) so a later registration can still apply it.
 *
 * @return string
 */
function rytkoset_theme_get_membership_order_processed_meta_key() {
	return '_rytkoset_membership_order_processed';
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

	if ( ! $user_id ) {
		rytkoset_theme_mark_membership_order_awaiting_account( $order );
		return;
	}

	$order->update_meta_data( rytkoset_theme_get_membership_order_processed_meta_key(), current_time( 'mysql' ) );

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

	// A time-bound membership that cannot be activated (the product has no expiry date set) is
	// stored but flagged so an admin sets the expiry date manually.
	if ( ! $is_now_active && 'lifetime' !== $membership['type'] ) {
		$note_parts[] = __( 'Huom: jäsenyyttä ei voitu aktivoida automaattisesti, koska tuotteelle ei ole asetettu Jäsenyys voimassa asti -päivää. Aseta voimassaolopäivä käyttäjähallinnassa.', 'rytkoset-theme' );
	}

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
}
add_action( 'woocommerce_order_status_processing', 'rytkoset_theme_maybe_apply_membership_from_order', 10, 2 );
add_action( 'woocommerce_order_status_completed', 'rytkoset_theme_maybe_apply_membership_from_order', 10, 2 );

// ---------------------------------------------------------------------------
// Membership linking on account registration (#518)
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
	}
}
add_action( 'user_register', 'rytkoset_theme_apply_membership_on_user_register' );
