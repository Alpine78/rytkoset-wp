<?php
/**
 * Digilehtien WooCommerce-tuotelinkitys ja jäsenhinnoittelu (#201).
 *
 * Sisältö pysyy `digital_magazine`-CPT:ssä; WooCommerce hoitaa maksullisen ostamisen. Malli on
 * sama kuin tapahtumien maksutuotelinkityksessä (`inc/events.php`): lehteen tallennetaan
 * tuotteen ID:t metatietona, eikä hintaa lasketa dynaamisilla hintafilttereillä.
 *
 * Kaksi erillistä tuotetta (#199): normaalihintatuote (`paid` + `member_and_regular`) ja
 * valinnainen jäsenhintatuote (`member_and_regular`). Ostotunnistus tehdään live-haulla
 * (`wc_customer_bought_product`) erillisenä callbackina samaan suodattimeen kuin #420:n
 * manuaaliset myönnöt — tulokset yhdistyvät OR-logiikalla. Ostosta lähtevä lukuoikeusilmoitus
 * kutsuu #420:n jaettua helperiä `rytkoset_theme_send_digital_magazine_access_email()`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the meta key for the regular-price product linked to a magazine.
 *
 * @return string
 */
function rytkoset_theme_get_digital_magazine_regular_product_meta_key() {
	return '_rytkoset_magazine_regular_product_id';
}

/**
 * Returns the meta key for the member-price product linked to a magazine.
 *
 * @return string
 */
function rytkoset_theme_get_digital_magazine_member_product_meta_key() {
	return '_rytkoset_magazine_member_product_id';
}

/**
 * Returns the regular-price product ID linked to a magazine or article.
 *
 * @param int $magazine_id Magazine or article post ID.
 * @return int
 */
function rytkoset_theme_get_digital_magazine_regular_product_id( $magazine_id ) {
	$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );

	return absint( get_post_meta( $magazine_id, rytkoset_theme_get_digital_magazine_regular_product_meta_key(), true ) );
}

/**
 * Returns the member-price product ID linked to a magazine or article.
 *
 * @param int $magazine_id Magazine or article post ID.
 * @return int
 */
function rytkoset_theme_get_digital_magazine_member_product_id( $magazine_id ) {
	$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );

	return absint( get_post_meta( $magazine_id, rytkoset_theme_get_digital_magazine_member_product_meta_key(), true ) );
}

/**
 * Returns the non-zero product IDs linked to a magazine (regular and member).
 *
 * @param int $magazine_id Magazine or article post ID.
 * @return int[]
 */
function rytkoset_theme_get_digital_magazine_product_ids( $magazine_id ) {
	$ids = array(
		rytkoset_theme_get_digital_magazine_regular_product_id( $magazine_id ),
		rytkoset_theme_get_digital_magazine_member_product_id( $magazine_id ),
	);

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Returns a published WooCommerce product object, or null.
 *
 * @param int $product_id Product ID.
 * @return WC_Product|null
 */
function rytkoset_theme_get_digital_magazine_published_product( $product_id ) {
	$product_id = absint( $product_id );

	if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	$product = wc_get_product( $product_id );

	if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
		return null;
	}

	return $product;
}

/**
 * Returns the product a given user should buy to unlock a magazine, or null.
 *
 * Active members are offered the member-price product for member_and_regular magazines; everyone
 * else (and any uncertain membership state) is offered the regular-price product (fail-safe to the
 * normal price, per #199). Free and members-only magazines have no purchase product.
 *
 * @param int      $magazine_id Magazine or article post ID.
 * @param int|null $user_id     User ID. Defaults to the current user.
 * @return WC_Product|null
 */
function rytkoset_theme_get_digital_magazine_purchase_product( $magazine_id, $user_id = null ) {
	$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );
	$mode        = rytkoset_theme_get_digital_magazine_access_mode( $magazine_id );

	if ( 'paid' === $mode ) {
		return rytkoset_theme_get_digital_magazine_published_product(
			rytkoset_theme_get_digital_magazine_regular_product_id( $magazine_id )
		);
	}

	if ( 'member_and_regular' === $mode ) {
		$is_member = function_exists( 'rytkoset_theme_user_is_active_member' )
			&& rytkoset_theme_user_is_active_member( $user_id );

		if ( $is_member ) {
			$member_product = rytkoset_theme_get_digital_magazine_published_product(
				rytkoset_theme_get_digital_magazine_member_product_id( $magazine_id )
			);

			if ( $member_product instanceof WC_Product ) {
				return $member_product;
			}
		}

		return rytkoset_theme_get_digital_magazine_published_product(
			rytkoset_theme_get_digital_magazine_regular_product_id( $magazine_id )
		);
	}

	return null;
}

/**
 * Returns the purchase CTA (url + label) for a magazine, or an empty array.
 *
 * @param int      $magazine_id Magazine or article post ID.
 * @param int|null $user_id     User ID. Defaults to the current user.
 * @return array{url?:string,label?:string}
 */
function rytkoset_theme_get_digital_magazine_purchase_cta( $magazine_id, $user_id = null ) {
	$product = rytkoset_theme_get_digital_magazine_purchase_product( $magazine_id, $user_id );

	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$url = get_permalink( $product->get_id() );

	if ( ! is_string( $url ) || '' === $url ) {
		return array();
	}

	$member_product_id = rytkoset_theme_get_digital_magazine_member_product_id( $magazine_id );
	$label             = ( $member_product_id > 0 && $product->get_id() === $member_product_id )
		? __( 'Osta jäsenhintaan', 'rytkoset-theme' )
		: __( 'Osta lukuoikeus', 'rytkoset-theme' );

	return array(
		'url'   => $url,
		'label' => $label,
	);
}

/**
 * Returns the magazine ID linked to a product through either product meta, or 0.
 *
 * @param int $product_id Product ID.
 * @return int
 */
function rytkoset_theme_get_magazine_id_by_product( $product_id ) {
	$product_id = absint( $product_id );

	if ( $product_id <= 0 ) {
		return 0;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'digital_magazine',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reverse lookup keyed by an indexed product ID; runs only on cart/checkout/order events.
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => rytkoset_theme_get_digital_magazine_regular_product_meta_key(),
					'value' => $product_id,
				),
				array(
					'key'   => rytkoset_theme_get_digital_magazine_member_product_meta_key(),
					'value' => $product_id,
				),
			),
		)
	);

	return ! empty( $ids ) ? (int) $ids[0] : 0;
}

/**
 * Returns true when a product is used as a member-price product for any magazine.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function rytkoset_theme_product_is_magazine_member_product( $product_id ) {
	$product_id = absint( $product_id );

	if ( $product_id <= 0 ) {
		return false;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'digital_magazine',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => rytkoset_theme_get_digital_magazine_member_product_meta_key(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Reverse lookup keyed by an indexed product ID; cart/checkout only.
			'meta_value'     => $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
		)
	);

	return ! empty( $ids );
}

/**
 * Merges WooCommerce purchases into the shared magazine access filter (Model A live lookup).
 *
 * Runs alongside #420's manual-grant callback on the same filter; the OR-logic combines both.
 * wc_customer_bought_product() only counts completed/processing orders by default, matching the
 * #199 status decision.
 *
 * @param bool $has_access  Whether access was granted by an earlier callback.
 * @param int  $magazine_id Parent magazine post ID.
 * @param int  $user_id     User ID, or 0 for anonymous users.
 * @return bool
 */
function rytkoset_theme_filter_purchased_digital_magazine( $has_access, $magazine_id, $user_id ) {
	if ( $has_access || (int) $user_id <= 0 || ! function_exists( 'wc_customer_bought_product' ) ) {
		return $has_access;
	}

	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
		return $has_access;
	}

	foreach ( rytkoset_theme_get_digital_magazine_product_ids( $magazine_id ) as $product_id ) {
		if ( wc_customer_bought_product( $user->user_email, (int) $user_id, $product_id ) ) {
			return true;
		}
	}

	return $has_access;
}
add_filter( 'rytkoset_theme_user_has_purchased_digital_magazine', 'rytkoset_theme_filter_purchased_digital_magazine', 10, 3 );

/**
 * Returns the message shown when a non-member tries to buy a member-price magazine product.
 *
 * @return string
 */
function rytkoset_theme_get_digital_magazine_member_product_block_message() {
	return __( 'Tämä on jäsenhintainen digilehti. Vain seuran aktiiviset jäsenet voivat ostaa sen jäsenhintaan.', 'rytkoset-theme' );
}

/**
 * Blocks adding a member-price magazine product to the cart without an active membership.
 *
 * @param bool $passed     Whether add to cart should proceed.
 * @param int  $product_id Product ID.
 * @return bool
 */
function rytkoset_theme_validate_digital_magazine_member_add_to_cart( $passed, $product_id ) {
	if ( ! rytkoset_theme_product_is_magazine_member_product( $product_id ) ) {
		return $passed;
	}

	if ( function_exists( 'rytkoset_theme_user_is_active_member' ) && rytkoset_theme_user_is_active_member() ) {
		return $passed;
	}

	$message = rytkoset_theme_get_digital_magazine_member_product_block_message();

	if ( function_exists( 'wc_add_notice' ) && ! wc_has_notice( $message, 'error' ) ) {
		wc_add_notice( $message, 'error' );
	}

	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'rytkoset_theme_validate_digital_magazine_member_add_to_cart', 10, 2 );

/**
 * Validates member-price magazine products already present in cart or checkout.
 *
 * @return void
 */
function rytkoset_theme_validate_digital_magazine_member_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	if ( function_exists( 'rytkoset_theme_user_is_active_member' ) && rytkoset_theme_user_is_active_member() ) {
		return;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product || ! rytkoset_theme_product_is_magazine_member_product( $product->get_id() ) ) {
			continue;
		}

		$message = rytkoset_theme_get_digital_magazine_member_product_block_message();

		if ( function_exists( 'wc_add_notice' ) && ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}

		return;
	}
}
add_action( 'woocommerce_check_cart_items', 'rytkoset_theme_validate_digital_magazine_member_cart_items' );

/**
 * Sends the access email for magazines unlocked by a completed/processing order.
 *
 * Access itself stays a live lookup (Model A); this only sends the notification once per order
 * and magazine, tracked in the order meta `_rytkoset_magazine_access_email_sent`. Guest orders
 * without a WordPress user are skipped, because access is account-bound.
 *
 * @param int $order_id Order ID.
 * @return void
 */
function rytkoset_theme_send_digital_magazine_purchase_emails( $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	// Re-entrancy guard: saving the order below re-fires the status hook within the same
	// request, before the sent-meta persists. Cross-request idempotency stays on the order meta.
	static $in_progress = array();
	$order_id           = (int) $order_id;

	if ( isset( $in_progress[ $order_id ] ) ) {
		return;
	}
	$in_progress[ $order_id ] = true;

	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$user_id = $order->get_user_id();

	if ( $user_id <= 0 ) {
		return;
	}

	$sent_key = '_rytkoset_magazine_access_email_sent';
	$sent     = $order->get_meta( $sent_key );
	$sent     = is_array( $sent ) ? array_map( 'absint', $sent ) : array();
	$changed  = false;

	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			continue;
		}

		$magazine_id = rytkoset_theme_get_magazine_id_by_product( $item->get_product_id() );

		if ( $magazine_id <= 0 || in_array( $magazine_id, $sent, true ) ) {
			continue;
		}

		rytkoset_theme_send_digital_magazine_access_email( $user_id, $magazine_id );
		$sent[]  = $magazine_id;
		$changed = true;
	}

	if ( $changed ) {
		$order->update_meta_data( $sent_key, array_values( array_unique( $sent ) ) );
		$order->save();
	}
}
add_action( 'woocommerce_order_status_completed', 'rytkoset_theme_send_digital_magazine_purchase_emails' );
add_action( 'woocommerce_order_status_processing', 'rytkoset_theme_send_digital_magazine_purchase_emails' );

/**
 * Returns published WooCommerce products for the magazine product selectors.
 *
 * @return WC_Product[]
 */
function rytkoset_theme_get_digital_magazine_product_options() {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	return wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => -1,
			'orderby' => 'title',
			'order'   => 'ASC',
		)
	);
}

/**
 * Registers the product link metabox for top-level magazines.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function rytkoset_theme_register_digital_magazine_product_metabox( $post ) {
	if ( $post instanceof WP_Post && 0 < (int) $post->post_parent ) {
		return;
	}

	add_meta_box(
		'rytkoset-digital-magazine-products',
		__( 'Maksutuotteet', 'rytkoset-theme' ),
		'rytkoset_theme_render_digital_magazine_product_metabox',
		'digital_magazine',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_digital_magazine', 'rytkoset_theme_register_digital_magazine_product_metabox' );

/**
 * Renders a single magazine product selector.
 *
 * @param string       $field_name  Input name.
 * @param int          $selected_id Currently selected product ID.
 * @param WC_Product[] $products    Available products.
 * @return void
 */
function rytkoset_theme_render_digital_magazine_product_select( $field_name, $selected_id, $products ) {
	?>
	<select id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>" class="widefat">
		<option value=""><?php esc_html_e( 'Ei tuotetta', 'rytkoset-theme' ); ?></option>
		<?php foreach ( $products as $product ) : ?>
			<?php
			if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
				continue;
			}
			?>
			<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>" <?php selected( $selected_id, $product->get_id() ); ?>>
				<?php echo esc_html( $product->get_name() ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Renders the magazine product link metabox.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function rytkoset_theme_render_digital_magazine_product_metabox( $post ) {
	wp_nonce_field( 'rytkoset_save_digital_magazine_products', 'rytkoset_digital_magazine_products_nonce' );

	if ( ! function_exists( 'wc_get_products' ) ) {
		echo '<p>' . esc_html__( 'WooCommerce ei ole käytössä, joten maksutuotetta ei voi valita.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	$products    = rytkoset_theme_get_digital_magazine_product_options();
	$regular_key = rytkoset_theme_get_digital_magazine_regular_product_meta_key();
	$member_key  = rytkoset_theme_get_digital_magazine_member_product_meta_key();
	$regular_id  = absint( get_post_meta( $post->ID, $regular_key, true ) );
	$member_id   = absint( get_post_meta( $post->ID, $member_key, true ) );
	?>
	<p>
		<label for="<?php echo esc_attr( $regular_key ); ?>">
			<strong><?php esc_html_e( 'Normaalihintatuote', 'rytkoset-theme' ); ?></strong>
		</label>
	</p>
	<?php rytkoset_theme_render_digital_magazine_product_select( $regular_key, $regular_id, $products ); ?>
	<p class="description">
		<?php esc_html_e( 'Ei-jäsenten ja kaikille maksullisen lehden hinta. Käytössä malleissa “Kaikille maksullinen” ja “Jäsenhinta + normaalihinta”.', 'rytkoset-theme' ); ?>
	</p>

	<p style="margin-top:1em;">
		<label for="<?php echo esc_attr( $member_key ); ?>">
			<strong><?php esc_html_e( 'Jäsenhintatuote', 'rytkoset-theme' ); ?></strong>
		</label>
	</p>
	<?php rytkoset_theme_render_digital_magazine_product_select( $member_key, $member_id, $products ); ?>
	<p class="description">
		<?php esc_html_e( 'Aktiivisen jäsenen hinta. Käytössä vain mallissa “Jäsenhinta + normaalihinta”. Vain jäsen voi ostaa tämän tuotteen.', 'rytkoset-theme' ); ?>
	</p>
	<?php
}

/**
 * Validates and saves a single magazine product link.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Product meta key.
 * @return void
 */
function rytkoset_theme_save_digital_magazine_product_field( $post_id, $meta_key ) {
	$product_id = isset( $_POST[ $meta_key ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling save handler.
		? absint( wp_unslash( $_POST[ $meta_key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
		: 0;

	if ( $product_id <= 0 || ! rytkoset_theme_get_digital_magazine_published_product( $product_id ) ) {
		delete_post_meta( $post_id, $meta_key );
		return;
	}

	update_post_meta( $post_id, $meta_key, $product_id );
}

/**
 * Saves the magazine product link metabox.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function rytkoset_theme_save_digital_magazine_products( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! $post instanceof WP_Post || 0 < (int) $post->post_parent ) {
		delete_post_meta( $post_id, rytkoset_theme_get_digital_magazine_regular_product_meta_key() );
		delete_post_meta( $post_id, rytkoset_theme_get_digital_magazine_member_product_meta_key() );
		return;
	}

	if ( ! isset( $_POST['rytkoset_digital_magazine_products_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_digital_magazine_products_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_digital_magazine_products' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	rytkoset_theme_save_digital_magazine_product_field( $post_id, rytkoset_theme_get_digital_magazine_regular_product_meta_key() );
	rytkoset_theme_save_digital_magazine_product_field( $post_id, rytkoset_theme_get_digital_magazine_member_product_meta_key() );
}
add_action( 'save_post_digital_magazine', 'rytkoset_theme_save_digital_magazine_products', 10, 2 );
