<?php
/**
 * Tests for membership product publication and purchase validation (#537).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembershipProductValidationTest extends Rytkoset_Theme_Test_Case {

	public function test_annual_membership_requires_valid_period_and_expiry(): void {
		$product = $this->membership_product( 'annual_individual', '2029-02-30', '2029' );
		$errors  = rytkoset_theme_get_membership_product_validation_errors( $product );

		$this->assertArrayHasKey( 'period', $errors );
		$this->assertArrayHasKey( 'expiry_date', $errors );
	}

	public function test_lifetime_membership_does_not_require_period_or_expiry(): void {
		$this->assertSame(
			array(),
			rytkoset_theme_get_membership_product_validation_errors( $this->membership_product( 'lifetime' ) )
		);
	}

	public function test_add_to_cart_blocks_invalid_membership_product_with_error_notice(): void {
		rytkoset_test_register_product(
			537,
			'publish',
			'Vuosijäsenmaksu',
			array(
				rytkoset_theme_get_membership_product_meta_key() => 'yes',
				rytkoset_theme_get_membership_type_meta_key()    => 'annual_individual',
			)
		);

		$this->assertFalse( rytkoset_theme_validate_membership_product_add_to_cart( true, 537 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_wc_notices'] );
	}

	public function test_cart_validation_blocks_product_that_became_invalid_after_addition(): void {
		$GLOBALS['rytkoset_test_wc']->cart        = new Rytkoset_Test_Cart();
		$GLOBALS['rytkoset_test_wc']->cart->items = array(
			array( 'data' => $this->membership_product( 'annual_family', '', '2026-2029' ) ),
		);

		rytkoset_theme_validate_membership_product_cart_items();

		$this->assertCount( 1, $GLOBALS['rytkoset_test_wc_notices'] );
	}

	public function test_publishing_incomplete_annual_product_forces_draft_and_admin_error(): void {
		$product = new WC_Product( array(), 537, 'publish', 'Vuosijäsenmaksu' );
		$_POST   = array(
			rytkoset_theme_get_membership_product_meta_key() => 'yes',
			rytkoset_theme_get_membership_type_meta_key()    => 'annual_individual',
			rytkoset_theme_get_membership_period_meta_key()  => '2026-2029',
		);

		rytkoset_theme_save_membership_product_fields( $product );

		$this->assertSame( 'draft', $product->get_status() );
		$this->assertCount( 1, WC_Admin_Meta_Boxes::$errors );
		$this->assertStringContainsString( 'voimassa asti', WC_Admin_Meta_Boxes::$errors[0] );
	}

	public function test_publishing_lifetime_product_without_period_or_expiry_succeeds(): void {
		$product = new WC_Product( array(), 538, 'publish', 'Ainaisjäsenmaksu' );
		$_POST   = array(
			rytkoset_theme_get_membership_product_meta_key() => 'yes',
			rytkoset_theme_get_membership_type_meta_key()    => 'lifetime',
		);

		rytkoset_theme_save_membership_product_fields( $product );

		$this->assertSame( 'publish', $product->get_status() );
		$this->assertSame( array(), WC_Admin_Meta_Boxes::$errors );
	}
}
