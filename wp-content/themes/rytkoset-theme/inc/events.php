<?php
/**
 * Tapahtumat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_register_event_cpt' ) ) {
	/**
	 * Rekisteröi tapahtumien CPT:n.
	 */
	function rytkoset_theme_register_event_cpt() {
		$labels = array(
			'name'               => __( 'Tapahtumat', 'rytkoset-theme' ),
			'singular_name'      => __( 'Tapahtuma', 'rytkoset-theme' ),
			'menu_name'          => __( 'Tapahtumat', 'rytkoset-theme' ),
			'name_admin_bar'     => __( 'Tapahtuma', 'rytkoset-theme' ),
			'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
			'add_new_item'       => __( 'Lisää uusi tapahtuma', 'rytkoset-theme' ),
			'new_item'           => __( 'Uusi tapahtuma', 'rytkoset-theme' ),
			'edit_item'          => __( 'Muokkaa tapahtumaa', 'rytkoset-theme' ),
			'view_item'          => __( 'Näytä tapahtuma', 'rytkoset-theme' ),
			'all_items'          => __( 'Kaikki tapahtumat', 'rytkoset-theme' ),
			'search_items'       => __( 'Etsi tapahtumia', 'rytkoset-theme' ),
			'not_found'          => __( 'Tapahtumia ei löytynyt.', 'rytkoset-theme' ),
			'not_found_in_trash' => __( 'Roskakorissa ei ole tapahtumia.', 'rytkoset-theme' ),
		);

		$args = array(
			'labels'        => $labels,
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-calendar-alt',
			'show_in_rest'  => true,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'rewrite'       => array(
				'slug'       => 'tapahtumat',
				'with_front' => false,
			),
		);

		register_post_type( 'event', $args );
	}
}
add_action( 'init', 'rytkoset_theme_register_event_cpt' );

if ( ! function_exists( 'rytkoset_theme_get_event_product_meta_key' ) ) {
	/**
	 * Returns the meta key used to link an event to a WooCommerce product.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_event_product_meta_key() {
		return '_rytkoset_event_product_id';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_linked_product' ) ) {
	/**
	 * Returns the WooCommerce product linked to an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return WC_Product|null
	 */
	function rytkoset_theme_get_event_linked_product( $event_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product_id = absint( get_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), true ) );

		if ( $product_id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			return null;
		}

		return $product;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_product_options' ) ) {
	/**
	 * Returns published WooCommerce products for the event product selector.
	 *
	 * @return WC_Product[]
	 */
	function rytkoset_theme_get_event_product_options() {
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
}

if ( ! function_exists( 'rytkoset_theme_register_event_product_metabox' ) ) {
	/**
	 * Adds the event product selector metabox.
	 */
	function rytkoset_theme_register_event_product_metabox() {
		add_meta_box(
			'rytkoset_event_product',
			__( 'Maksutuote', 'rytkoset-theme' ),
			'rytkoset_theme_render_event_product_metabox',
			'event',
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_event', 'rytkoset_theme_register_event_product_metabox' );

if ( ! function_exists( 'rytkoset_theme_print_event_admin_styles' ) ) {
	/**
	 * Prints small editor-only styles for event admin metaboxes.
	 */
	function rytkoset_theme_print_event_admin_styles() {
		$screen = get_current_screen();

		if ( ! $screen || 'event' !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			#rytkoset_event_product_id {
				box-sizing: border-box;
				max-width: calc(100% - 12px);
				width: calc(100% - 12px);
			}
		</style>
		<?php
	}
}
add_action( 'admin_head-post.php', 'rytkoset_theme_print_event_admin_styles' );
add_action( 'admin_head-post-new.php', 'rytkoset_theme_print_event_admin_styles' );

if ( ! function_exists( 'rytkoset_theme_render_event_product_metabox' ) ) {
	/**
	 * Renders the event product selector metabox.
	 *
	 * @param WP_Post $post Event post object.
	 */
	function rytkoset_theme_render_event_product_metabox( $post ) {
		$selected_product_id = absint( get_post_meta( $post->ID, rytkoset_theme_get_event_product_meta_key(), true ) );
		$products            = rytkoset_theme_get_event_product_options();

		wp_nonce_field( 'rytkoset_save_event_product_link', 'rytkoset_event_product_nonce' );

		if ( ! function_exists( 'wc_get_products' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce ei ole käytössä, joten maksutuotetta ei voi valita.', 'rytkoset-theme' ) . '</p>';
			return;
		}
		?>
		<p>
			<label for="rytkoset_event_product_id">
				<?php esc_html_e( 'WooCommerce-tuote', 'rytkoset-theme' ); ?>
			</label>
		</p>
		<select id="rytkoset_event_product_id" name="rytkoset_event_product_id" class="widefat">
			<option value=""><?php esc_html_e( 'Ei maksutuotetta', 'rytkoset-theme' ); ?></option>
			<?php foreach ( $products as $product ) : ?>
				<?php
				if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
					continue;
				}

				$product_label = $product->get_name();
				?>
				<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>" <?php selected( $selected_product_id, $product->get_id() ); ?>>
					<?php echo esc_html( $product_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		if ( $selected_product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$selected_product = wc_get_product( $selected_product_id );

			if ( class_exists( 'WC_Product' ) && $selected_product instanceof WC_Product && '' !== $selected_product->get_sku() ) {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: product SKU. */
							__( 'Valitun tuotteen SKU: %s', 'rytkoset-theme' ),
							$selected_product->get_sku()
						)
					)
				);
			}
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Valitse tapahtumaan liittyvä WooCommerce-tuote. Tapahtumasivulle lisätään painike tuotteen sivulle.', 'rytkoset-theme' ); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_save_event_product_link' ) ) {
	/**
	 * Saves the WooCommerce product linked to an event.
	 *
	 * @param int $post_id Event post ID.
	 */
	function rytkoset_theme_save_event_product_link( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['rytkoset_event_product_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_event_product_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_event_product_link' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_id = isset( $_POST['rytkoset_event_product_id'] )
			? absint( wp_unslash( $_POST['rytkoset_event_product_id'] ) )
			: 0;

		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			delete_post_meta( $post_id, rytkoset_theme_get_event_product_meta_key() );
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			delete_post_meta( $post_id, rytkoset_theme_get_event_product_meta_key() );
			return;
		}

		update_post_meta( $post_id, rytkoset_theme_get_event_product_meta_key(), $product_id );
	}
}
add_action( 'save_post_event', 'rytkoset_theme_save_event_product_link' );

if ( ! function_exists( 'rytkoset_theme_render_event_product_cta' ) ) {
	/**
	 * Renders the linked product CTA for an event.
	 *
	 * @param int $event_id Event post ID.
	 */
	function rytkoset_theme_render_event_product_cta( $event_id ) {
		$product = rytkoset_theme_get_event_linked_product( $event_id );

		if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
			return;
		}

		$product_url = get_permalink( $product->get_id() );

		if ( ! $product_url ) {
			return;
		}
		?>
		<div class="event-product-cta">
			<a class="btn btn--primary" href="<?php echo esc_url( $product_url ); ?>">
				<?php esc_html_e( 'Ilmoittaudu ja maksa', 'rytkoset-theme' ); ?>
			</a>
		</div>
		<?php
	}
}
