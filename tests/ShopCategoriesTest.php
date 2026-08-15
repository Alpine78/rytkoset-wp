<?php
/**
 * Tests for the WooCommerce shop category navigation (#653).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ShopCategoriesTest extends Rytkoset_Theme_Test_Case {

	public function test_category_with_only_hidden_published_product_is_not_catalog_visible(): void {
		rytkoset_test_register_product(
			10,
			'publish',
			'Tampere 2026 osallistumismaksu',
			array( '_catalog_visibility' => 'hidden' )
		);
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'tapahtumat' );

		$term = (object) array( 'slug' => 'tapahtumat' );

		$this->assertFalse( rytkoset_theme_product_category_has_catalog_products( $term ) );
	}

	public function test_category_returns_when_catalog_visible_product_is_published(): void {
		rytkoset_test_register_product( 10, 'publish', 'Piilotettu', array( '_catalog_visibility' => 'hidden' ) );
		rytkoset_test_register_product( 11, 'publish', 'Näkyvä tapahtuma', array( '_catalog_visibility' => 'catalog' ) );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'tapahtumat' );
		$GLOBALS['rytkoset_test_object_terms'][11]['product_cat'] = array( 'tapahtumat' );

		$term = (object) array( 'slug' => 'tapahtumat' );

		$this->assertTrue( rytkoset_theme_product_category_has_catalog_products( $term ) );
	}

	public function test_draft_product_does_not_make_category_catalog_visible(): void {
		rytkoset_test_register_product( 10, 'draft', 'Luonnos' );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'tapahtumat' );

		$term = (object) array( 'slug' => 'tapahtumat' );

		$this->assertFalse( rytkoset_theme_product_category_has_catalog_products( $term ) );
	}

	public function test_category_bar_is_hooked_before_empty_products_notice(): void {
		$hooks = $GLOBALS['rytkoset_test_hooks']['woocommerce_no_products_found'] ?? array();

		$this->assertContains(
			array( 5, 'rytkoset_theme_render_shop_category_bar' ),
			$hooks
		);
	}

	public function test_shortcode_compatibility_loop_has_empty_state_callback(): void {
		$hooks = $GLOBALS['rytkoset_test_hooks']['woocommerce_shortcode_products_loop_no_results'] ?? array();

		$this->assertContains(
			array( 5, 'rytkoset_theme_render_shortcode_shop_no_products' ),
			$hooks
		);
	}
}
