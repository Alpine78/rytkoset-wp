<?php
/**
 * Kaupan kategoriapalkki.
 *
 * Kevyt linkkirivi tuotelistan yläpuolelle, jolla asiakas voi selata
 * tuotekategorioita kaupan etusivulla ja kategoriaarkistoissa. Ei vaadi
 * kategoriakuvia. Korostaa nykyisen näkymän.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_product_category_has_catalog_products' ) ) {
	/**
	 * Checks whether a product category contains a published catalog-visible product.
	 *
	 * WooCommerce stores catalog visibility in the product_visibility taxonomy, so
	 * the product query must apply WooCommerce's visibility handling instead of
	 * relying on the product category term count.
	 *
	 * @param WP_Term|object $term Product category term.
	 * @return bool
	 */
	function rytkoset_theme_product_category_has_catalog_products( $term ) {
		if ( ! function_exists( 'wc_get_products' ) || empty( $term->slug ) ) {
			return false;
		}

		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'category'   => array( (string) $term->slug ),
				'visibility' => 'catalog',
				'limit'      => 1,
				'return'     => 'ids',
			)
		);

		return ! empty( $products );
	}
}

if ( ! function_exists( 'rytkoset_theme_render_shop_category_bar' ) ) {
	/**
	 * Renderöi kategorialinkkipalkin WooCommerce-tuotelistan yläpuolelle.
	 *
	 * Näkyy kaupan etusivulla (`is_shop()`) ja tuotekategoriaarkistoissa
	 * (`is_product_category()`). Näyttää "Kaikki"-linkin sekä kategoriat,
	 * joissa on katalogissa näkyviä tuotteita (oletuskategoria / Uncategorized
	 * pois lukien).
	 *
	 * @return void
	 */
	function rytkoset_theme_render_shop_category_bar() {
		if ( ! function_exists( 'is_shop' ) || ( ! is_shop() && ! is_product_category() ) ) {
			return;
		}

		$default_cat = (int) get_option( 'default_product_cat' );

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'exclude'    => array_filter( array( $default_cat ) ),
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		// Keep the slug fallback in case the default category option is stale.
		$terms = array_filter(
			$terms,
			static function ( $term ) {
				return 'uncategorized' !== $term->slug
					&& rytkoset_theme_product_category_has_catalog_products( $term );
			}
		);

		$shop_url        = wc_get_page_permalink( 'shop' );
		$current_term_id = is_product_category() ? (int) get_queried_object_id() : 0;

		echo '<nav class="rytkoset-shop-cats" aria-label="' . esc_attr__( 'Tuotekategoriat', 'rytkoset-theme' ) . '">';

		printf(
			'<a class="rytkoset-shop-cats__link%1$s" href="%2$s"%3$s>%4$s</a>',
			is_shop() ? ' is-current' : '',
			esc_url( $shop_url ),
			is_shop() ? ' aria-current="page"' : '',
			esc_html__( 'Kaikki', 'rytkoset-theme' )
		);

		foreach ( $terms as $term ) {
			$is_current = ( (int) $term->term_id === $current_term_id );
			$term_link  = get_term_link( $term );
			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			printf(
				'<a class="rytkoset-shop-cats__link%1$s" href="%2$s"%3$s>%4$s</a>',
				$is_current ? ' is-current' : '',
				esc_url( $term_link ),
				$is_current ? ' aria-current="page"' : '',
				esc_html( $term->name )
			);
		}

		echo '</nav>';
	}
}

if ( ! function_exists( 'rytkoset_theme_render_shortcode_shop_no_products' ) ) {
	/**
	 * Renders navigation and the standard notice for an empty shortcode shop loop.
	 *
	 * WooCommerce uses its shortcode compatibility path for product archives in
	 * themes without declared WooCommerce support. That path does not fire the
	 * standard woocommerce_no_products_found action.
	 *
	 * @return void
	 */
	function rytkoset_theme_render_shortcode_shop_no_products() {
		if ( ! function_exists( 'is_shop' ) || ( ! is_shop() && ! is_product_category() ) ) {
			return;
		}

		rytkoset_theme_render_shop_category_bar();

		if ( function_exists( 'wc_no_products_found' ) ) {
			wc_no_products_found();
		}
	}
}

add_action( 'woocommerce_before_shop_loop', 'rytkoset_theme_render_shop_category_bar', 5 );
add_action( 'woocommerce_no_products_found', 'rytkoset_theme_render_shop_category_bar', 5 );
add_action( 'woocommerce_shortcode_products_loop_no_results', 'rytkoset_theme_render_shortcode_shop_no_products', 5 );
