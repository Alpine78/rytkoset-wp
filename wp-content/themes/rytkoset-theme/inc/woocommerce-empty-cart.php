<?php
/**
 * Empty cart redesign (#581).
 *
 * The cart page uses the WooCommerce Cart Block, so the empty state is the
 * `woocommerce/empty-cart-block` inner block rather than the classic
 * `cart/cart-empty.php` template. This module replaces that block's rendered
 * output with a branded, inviting hero (Claude Design "Ostoskori tyhjä",
 * variant A): eyebrow, warm heading, a clear path to the shop, an optional
 * secondary link bound to the next upcoming event, and a small "Poimintoja
 * kaupasta" product grid.
 *
 * The block frontend renders the server HTML of the empty-cart-block as the
 * empty state, so replacing it via `render_block` keeps the design versioned in
 * the theme (no per-environment cart-page block content editing) while still
 * showing through the Cart Block's JS toggle.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_empty_cart_custom_view_enabled' ) ) {
	/**
	 * Whether the branded empty-cart view should replace the default block.
	 *
	 * @return bool
	 */
	function rytkoset_theme_empty_cart_custom_view_enabled() {
		/**
		 * Toggle the branded empty-cart view.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'rytkoset_theme_empty_cart_custom_view', true );
	}
}

if ( ! function_exists( 'rytkoset_theme_render_empty_cart_block' ) ) {
	/**
	 * Replaces the WooCommerce empty-cart block with the branded hero.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	function rytkoset_theme_render_empty_cart_block( $block_content, $block ) {
		if ( ! is_array( $block ) || ! isset( $block['blockName'] ) ) {
			return $block_content;
		}

		if ( 'woocommerce/empty-cart-block' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! rytkoset_theme_empty_cart_custom_view_enabled() ) {
			return $block_content;
		}

		$inner = rytkoset_theme_get_empty_cart_markup();

		if ( '' === $inner ) {
			return $block_content;
		}

		// Keep both identifiers used by the Cart Block frontend so React can
		// hydrate this node and toggle it against the filled-cart block.
		return '<div data-block-name="woocommerce/empty-cart-block" class="wp-block-woocommerce-empty-cart-block">' . $inner . '</div>';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_empty_cart_markup' ) ) {
	/**
	 * Builds the branded empty-cart hero + suggestions markup.
	 *
	 * @return string Escaped HTML, or empty string when it cannot be built.
	 */
	function rytkoset_theme_get_empty_cart_markup() {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		$shop_url = wc_get_page_permalink( 'shop' );

		if ( ! is_string( $shop_url ) || '' === $shop_url ) {
			$shop_url = home_url( '/' );
		}

		$illustration = get_template_directory_uri() . '/assets/images/home/home-kauppa-illustration.png';

		$spark = rytkoset_theme_empty_cart_icon( 'spark' );
		$arrow = rytkoset_theme_empty_cart_icon( 'arrow' );

		ob_start();
		?>
		<div class="rytkoset-empty-cart">
			<div class="rytkoset-empty-cart__hero">
				<div class="rytkoset-empty-cart__body">
					<p class="rytkoset-empty-cart__eyebrow"><?php echo $spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?><span><?php esc_html_e( 'Ostoskori', 'rytkoset-theme' ); ?></span></p>
					<h2 class="rytkoset-empty-cart__title"><?php esc_html_e( 'Korisi odottaa vielä ensimmäistä löytöä', 'rytkoset-theme' ); ?></h2>
					<p class="rytkoset-empty-cart__lede"><?php esc_html_e( 'Kaupasta löydät sukukirjat ja -lehdet, jäsenmaksut sekä liput sukuseuran tapahtumiin. Kaikki tuotot tukevat sukuseuran toimintaa.', 'rytkoset-theme' ); ?></p>
					<div class="rytkoset-empty-cart__actions">
						<a class="button rytkoset-empty-cart__cta" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Siirry kauppaan', 'rytkoset-theme' ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></a>
						<?php echo rytkoset_theme_get_empty_cart_event_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
					</div>
				</div>
				<div class="rytkoset-empty-cart__art">
					<img src="<?php echo esc_url( $illustration ); ?>" alt="" loading="lazy" decoding="async" />
				</div>
			</div>
			<?php echo rytkoset_theme_get_empty_cart_suggestions_markup( $shop_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
		</div>
		<?php

		return trim( (string) ob_get_clean() );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_empty_cart_event_link' ) ) {
	/**
	 * Builds the optional secondary link to the next upcoming event.
	 *
	 * Returns an empty string when there is no upcoming event, so the hero falls
	 * back to a single primary action.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_empty_cart_event_link() {
		if ( ! function_exists( 'rytkoset_theme_get_next_upcoming_event_id' ) ) {
			return '';
		}

		$event_id = rytkoset_theme_get_next_upcoming_event_id( 'paid', true );

		if ( $event_id <= 0 ) {
			return '';
		}

		$title = trim( (string) get_the_title( $event_id ) );
		$url   = get_permalink( $event_id );

		if ( '' === $title || ! is_string( $url ) || '' === $url ) {
			return '';
		}

		/* translators: %s: event name. */
		$label = sprintf( __( 'Tutustu: %s', 'rytkoset-theme' ), $title );

		return sprintf(
			'<a class="rytkoset-empty-cart__ghost" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_empty_cart_suggested_products' ) ) {
	/**
	 * Whether a product is suitable for the empty-cart suggestions.
	 *
	 * @param mixed $product Product candidate.
	 * @return bool
	 */
	function rytkoset_theme_empty_cart_product_is_suggestable( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		$catalog_visible = ! method_exists( $product, 'get_catalog_visibility' )
			|| in_array( $product->get_catalog_visibility(), array( 'visible', 'catalog' ), true );
		$purchasable     = ! method_exists( $product, 'is_purchasable' ) || $product->is_purchasable();

		return $catalog_visible && $purchasable;
	}

	/**
	 * Returns a small set of published products to suggest on the empty cart.
	 *
	 * Prefers featured products and fills the remaining slots with the most
	 * recent visible products, de-duplicated. Catalog-hidden and non-purchasable
	 * items are skipped so every card links to a real, buyable product.
	 *
	 * @param int $limit Maximum number of products.
	 * @return WC_Product[]
	 */
	function rytkoset_theme_get_empty_cart_suggested_products( $limit = 4 ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$limit = max( 1, (int) $limit );

		/**
		 * Number of product suggestions on the empty cart.
		 *
		 * @param int $limit Default 4.
		 */
		$limit = max( 1, (int) apply_filters( 'rytkoset_theme_empty_cart_suggestions_limit', $limit ) );

		$collected = array();

		$featured = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => $limit,
				'featured' => true,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		foreach ( (array) $featured as $product ) {
			if ( rytkoset_theme_empty_cart_product_is_suggestable( $product ) && ! isset( $collected[ $product->get_id() ] ) ) {
				$collected[ $product->get_id() ] = $product;
			}
		}

		if ( count( $collected ) < $limit ) {
			$recent = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => $limit * 3,
					'orderby' => 'date',
					'order'   => 'DESC',
					'exclude' => array_keys( $collected ),
				)
			);

			foreach ( (array) $recent as $product ) {
				if ( count( $collected ) >= $limit ) {
					break;
				}

				if ( rytkoset_theme_empty_cart_product_is_suggestable( $product ) && ! isset( $collected[ $product->get_id() ] ) ) {
					$collected[ $product->get_id() ] = $product;
				}
			}
		}

		return array_slice( array_values( $collected ), 0, $limit );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_empty_cart_suggestions_markup' ) ) {
	/**
	 * Builds the "Poimintoja kaupasta" product grid.
	 *
	 * @param string $shop_url Shop page URL for the "Koko valikoima" link.
	 * @return string
	 */
	function rytkoset_theme_get_empty_cart_suggestions_markup( $shop_url ) {
		$products = rytkoset_theme_get_empty_cart_suggested_products( 4 );

		if ( empty( $products ) ) {
			return '';
		}

		$arrow = rytkoset_theme_empty_cart_icon( 'arrow' );

		ob_start();
		?>
		<div class="rytkoset-empty-cart__section">
			<div class="rytkoset-empty-cart__section-head">
				<h3 class="rytkoset-empty-cart__h2"><?php esc_html_e( 'Poimintoja kaupasta', 'rytkoset-theme' ); ?></h3>
				<a class="rytkoset-empty-cart__section-link" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Koko valikoima', 'rytkoset-theme' ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></a>
			</div>
			<ul class="rytkoset-empty-cart__grid">
				<?php foreach ( $products as $product ) : ?>
					<?php echo rytkoset_theme_get_empty_cart_product_card( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php

		return trim( (string) ob_get_clean() );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_empty_cart_product_card' ) ) {
	/**
	 * Builds one suggestion card for a product.
	 *
	 * Cards are static links (no ajax) so they survive the Cart Block's React
	 * re-render of the empty state. The action links to the product's add-to-cart
	 * URL (a plain GET add for simple products, the product page for variable
	 * ones), matching WooCommerce's own loop behavior.
	 *
	 * @param WC_Product $product Product instance.
	 * @return string
	 */
	function rytkoset_theme_get_empty_cart_product_card( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$permalink = get_permalink( $product->get_id() );
		$title     = $product->get_name();
		$price     = $product->get_price_html();
		$image     = $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) );

		$cta_url  = $product->add_to_cart_url();
		$cta_text = $product->add_to_cart_text();

		ob_start();
		?>
		<li class="rytkoset-empty-cart__card">
			<a class="rytkoset-empty-cart__card-media" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo wp_kses_post( $image ); ?>
			</a>
			<div class="rytkoset-empty-cart__card-body">
				<h4 class="rytkoset-empty-cart__card-title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h4>
				<?php if ( '' !== $price ) : ?>
					<div class="rytkoset-empty-cart__card-price"><?php echo wp_kses_post( $price ); ?></div>
				<?php endif; ?>
				<a class="button rytkoset-empty-cart__card-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
			</div>
		</li>
		<?php

		return trim( (string) ob_get_clean() );
	}
}

if ( ! function_exists( 'rytkoset_theme_empty_cart_icon' ) ) {
	/**
	 * Returns a small inline SVG icon used in the empty-cart view.
	 *
	 * @param string $name Icon name: 'spark' or 'arrow'.
	 * @return string
	 */
	function rytkoset_theme_empty_cart_icon( $name ) {
		switch ( $name ) {
			case 'spark':
				return '<svg class="rytkoset-empty-cart__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2l2.1 5.9L20 10l-5.9 2.1L12 18l-2.1-5.9L4 10l5.9-2.1z"/></svg>';
			case 'arrow':
				return '<svg class="rytkoset-empty-cart__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
			default:
				return '';
		}
	}
}

add_filter( 'render_block', 'rytkoset_theme_render_empty_cart_block', 10, 2 );
