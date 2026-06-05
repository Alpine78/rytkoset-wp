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

if ( ! function_exists( 'rytkoset_theme_render_shop_category_bar' ) ) {
	/**
	 * Renderöi kategorialinkkipalkin WooCommerce-tuotelistan yläpuolelle.
	 *
	 * Näkyy kaupan etusivulla (`is_shop()`) ja tuotekategoriaarkistoissa
	 * (`is_product_category()`). Näyttää "Kaikki"-linkin sekä ei-tyhjät
	 * tuotekategoriat (oletuskategoria / Uncategorized pois lukien).
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

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Varmuuden vuoksi pois myös slugilla 'uncategorized'.
		$terms = array_filter(
			$terms,
			static function ( $term ) {
				return 'uncategorized' !== $term->slug;
			}
		);

		if ( empty( $terms ) ) {
			return;
		}

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
			$is_current  = ( (int) $term->term_id === $current_term_id );
			$term_link   = get_term_link( $term );
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

add_action( 'woocommerce_before_shop_loop', 'rytkoset_theme_render_shop_category_bar', 5 );
