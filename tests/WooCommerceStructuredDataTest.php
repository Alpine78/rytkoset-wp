<?php
/**
 * Tests for inc/woocommerce-structured-data.php.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class WooCommerceStructuredDataTest extends Rytkoset_Theme_Test_Case {

	public function test_printed_family_magazine_gets_verified_merchant_data(): void {
		rytkoset_test_register_post( 10, 'product', 'Rytkösten sukulainen nro 6' );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'sukulehdet' );
		$GLOBALS['rytkoset_test_queried_id']                      = 10;
		$entity = array(
			'@type'  => 'Product',
			'sku'    => 'sukulehti-6',
			'offers' => array( 'price' => '5.00' ),
		);

		$result = rytkoset_theme_rankmath_add_printed_magazine_merchant_data( $entity );

		$this->assertSame(
			array(
				'@type' => 'Brand',
				'name'  => 'Rytkösten sukuseura',
			),
			$result['brand']
		);
		$this->assertSame(
			array(
				'@type'               => 'OfferShippingDetails',
				'shippingRate'        => array(
					'@type'    => 'MonetaryAmount',
					'value'    => 5.90,
					'currency' => 'EUR',
				),
				'shippingDestination' => array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => 'FI',
				),
				'deliveryTime'        => array(
					'@type'        => 'ShippingDeliveryTime',
					'handlingTime' => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => 1,
						'maxValue' => 3,
						'unitCode' => 'DAY',
					),
					'transitTime'  => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => 2,
						'maxValue' => 5,
						'unitCode' => 'DAY',
					),
				),
			),
			$result['offers']['shippingDetails']
		);
		$this->assertSame(
			array(
				'@type'                => 'MerchantReturnPolicy',
				'applicableCountry'    => 'FI',
				'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
				'merchantReturnDays'   => 14,
				'returnMethod'         => 'https://schema.org/ReturnByMail',
				'returnFees'           => 'https://schema.org/ReturnFeesCustomerResponsibility',
			),
			$result['offers']['hasMerchantReturnPolicy']
		);
		$this->assertSame( '5.00', $result['offers']['price'] );
	}

	public function test_printed_magazine_without_offer_gets_only_brand(): void {
		rytkoset_test_register_post( 10, 'product', 'Rytkösten sukulainen nro 6' );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'sukulehdet' );
		$GLOBALS['rytkoset_test_queried_id']                      = 10;

		$this->assertSame(
			array(
				'@type' => 'Product',
				'brand' => array(
					'@type' => 'Brand',
					'name'  => 'Rytkösten sukuseura',
				),
			),
			rytkoset_theme_rankmath_add_printed_magazine_merchant_data( array( '@type' => 'Product' ) )
		);
	}

	public function test_virtual_magazine_does_not_get_physical_product_brand(): void {
		rytkoset_test_register_post( 10, 'product', 'Digilehti' );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'sukulehdet' );
		$GLOBALS['rytkoset_test_queried_id']                      = 10;
		update_post_meta( 10, '_virtual', 'yes' );
		$entity = array( '@type' => 'Product' );

		$this->assertSame( $entity, rytkoset_theme_rankmath_add_printed_magazine_merchant_data( $entity ) );
	}

	public function test_downloadable_magazine_does_not_get_physical_product_brand(): void {
		rytkoset_test_register_post( 10, 'product', 'Ladattava lehti' );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'sukulehdet' );
		$GLOBALS['rytkoset_test_queried_id']                      = 10;
		update_post_meta( 10, '_downloadable', 'yes' );
		$entity = array( '@type' => 'Product' );

		$this->assertSame( $entity, rytkoset_theme_rankmath_add_printed_magazine_merchant_data( $entity ) );
	}

	public function test_other_product_category_is_unchanged(): void {
		rytkoset_test_register_post( 10, 'product', 'Tampere 2026 osallistumismaksu' );
		$GLOBALS['rytkoset_test_object_terms'][10]['product_cat'] = array( 'tapahtumat' );
		$GLOBALS['rytkoset_test_queried_id']                      = 10;
		$entity = array( '@type' => 'Product' );

		$this->assertSame( $entity, rytkoset_theme_rankmath_add_printed_magazine_merchant_data( $entity ) );
	}
}
