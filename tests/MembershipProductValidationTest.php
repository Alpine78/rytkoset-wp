<?php
/**
 * Tests for membership product publication and purchase validation (#537).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembershipProductValidationTest extends Rytkoset_Theme_Test_Case {

	public function test_membership_product_labels_use_current_terminology(): void {
		$options = rytkoset_theme_get_membership_type_options();

		$this->assertSame( 'Jäsenmaksu toimintakaudelle: Henkilö', $options['annual_individual'] );
		$this->assertSame( 'Jäsenmaksu toimintakaudelle: Perhe', $options['annual_family'] );
		$this->assertSame( 'Ainaisjäsen', $options['lifetime'] );
	}

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
			'Jäsenmaksu toimintakaudelle',
			array(
				rytkoset_theme_get_membership_product_meta_key() => 'yes',
				rytkoset_theme_get_membership_type_meta_key()    => 'annual_individual',
			)
		);

		$this->assertFalse( rytkoset_theme_validate_membership_product_add_to_cart( true, 537 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_wc_notices'] );
	}

	public function test_guest_cannot_add_valid_membership_product_to_cart(): void {
		rytkoset_test_register_product(
			661,
			'publish',
			'Perhejäsenmaksu',
			array(
				rytkoset_theme_get_membership_product_meta_key()      => 'yes',
				rytkoset_theme_get_membership_type_meta_key()         => 'annual_family',
				rytkoset_theme_get_membership_period_meta_key()       => '2026-2029',
				rytkoset_theme_get_membership_expiry_date_meta_key()  => '2029-08-31',
			)
		);

		$this->assertFalse( rytkoset_theme_validate_membership_product_add_to_cart( true, 661 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_wc_notices'] );
		$this->assertStringContainsString( 'vaatii käyttäjätilin', implode( '', array_keys( $GLOBALS['rytkoset_test_wc_notices'] ) ) );
	}

	public function test_logged_in_user_can_add_valid_membership_product_to_cart(): void {
		rytkoset_test_register_user( 10, 'primary@example.test', 'Päätili' );
		$GLOBALS['rytkoset_test_current_user'] = 10;
		rytkoset_test_register_product(
			662,
			'publish',
			'Perhejäsenmaksu',
			array(
				rytkoset_theme_get_membership_product_meta_key()      => 'yes',
				rytkoset_theme_get_membership_type_meta_key()         => 'annual_family',
				rytkoset_theme_get_membership_period_meta_key()       => '2026-2029',
				rytkoset_theme_get_membership_expiry_date_meta_key()  => '2029-08-31',
			)
		);

		$this->assertTrue( rytkoset_theme_validate_membership_product_add_to_cart( true, 662 ) );
		$this->assertSame( array(), $GLOBALS['rytkoset_test_wc_notices'] );
	}

	public function test_cart_validation_blocks_product_that_became_invalid_after_addition(): void {
		$GLOBALS['rytkoset_test_wc']->cart        = new Rytkoset_Test_Cart();
		$GLOBALS['rytkoset_test_wc']->cart->items = array(
			array( 'data' => $this->membership_product( 'annual_family', '', '2026-2029' ) ),
		);

		rytkoset_theme_validate_membership_product_cart_items();

		$this->assertCount( 1, $GLOBALS['rytkoset_test_wc_notices'] );
	}

	public function test_cart_validation_blocks_restored_membership_cart_for_guest(): void {
		$GLOBALS['rytkoset_test_wc']->cart        = new Rytkoset_Test_Cart();
		$GLOBALS['rytkoset_test_wc']->cart->items = array(
			array( 'data' => $this->membership_product( 'annual_family', '2029-08-31', '2026-2029' ) ),
		);

		rytkoset_theme_validate_membership_product_cart_items();

		$this->assertCount( 1, $GLOBALS['rytkoset_test_wc_notices'] );
		$this->assertStringContainsString( 'vaatii käyttäjätilin', implode( '', array_keys( $GLOBALS['rytkoset_test_wc_notices'] ) ) );
	}

	public function test_publishing_incomplete_annual_product_forces_draft_and_admin_error(): void {
		$product = new WC_Product( array(), 537, 'publish', 'Jäsenmaksu toimintakaudelle' );
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
