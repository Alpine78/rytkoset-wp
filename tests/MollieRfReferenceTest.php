<?php
/**
 * Tests for inc/woocommerce-mollie.php — RF reference detection and normalization.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MollieRfReferenceTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Builds an order with the given payment method and instruction meta.
	 *
	 * @param string $method       Payment method ID.
	 * @param string $instructions _mollie_payment_instructions meta value.
	 * @return WC_Order
	 */
	private function order( string $method, string $instructions = '' ): WC_Order {
		$order                 = new WC_Order();
		$order->payment_method = $method;

		if ( '' !== $instructions ) {
			$order->update_meta_data( '_mollie_payment_instructions', $instructions );
		}

		return $order;
	}

	// --- is_mollie_rf_reference_order ---------------------------------------

	public function test_bank_transfer_is_rf_reference_order(): void {
		$this->assertTrue( rytkoset_theme_is_mollie_rf_reference_order( $this->order( 'mollie_wc_gateway_banktransfer' ) ) );
	}

	public function test_paybybank_is_rf_reference_order(): void {
		$this->assertTrue( rytkoset_theme_is_mollie_rf_reference_order( $this->order( 'mollie_wc_gateway_paybybank' ) ) );
	}

	public function test_other_gateway_is_not_rf_reference_order(): void {
		$this->assertFalse( rytkoset_theme_is_mollie_rf_reference_order( $this->order( 'mollie_wc_gateway_creditcard' ) ) );
	}

	public function test_non_order_is_not_rf_reference_order(): void {
		$this->assertFalse( rytkoset_theme_is_mollie_rf_reference_order( 'not-an-order' ) );
	}

	// --- normalize_mollie_rf_references -------------------------------------

	public function test_normalizes_spaced_reference(): void {
		$this->assertSame(
			'Maksa viitteellä RF18539007547034.',
			rytkoset_theme_normalize_mollie_rf_references( 'Maksa viitteellä RF18 5390 0754 7034.' )
		);
	}

	public function test_normalizes_and_uppercases_lowercase_reference(): void {
		$this->assertSame(
			'RF18539007547034',
			rytkoset_theme_normalize_mollie_rf_references( 'rf18 5390 0754 7034' )
		);
	}

	public function test_text_without_reference_is_unchanged(): void {
		$this->assertSame(
			'Kiitos tilauksesta!',
			rytkoset_theme_normalize_mollie_rf_references( 'Kiitos tilauksesta!' )
		);
	}

	public function test_non_string_is_returned_as_is(): void {
		$this->assertNull( rytkoset_theme_normalize_mollie_rf_references( null ) );
	}

	// --- get_mollie_rf_reference --------------------------------------------

	public function test_extracts_normalized_reference_from_instructions(): void {
		$order = $this->order( 'mollie_wc_gateway_banktransfer', 'Viite: RF18 5390 0754 7034 — maksa eräpäivään mennessä.' );

		$this->assertSame( 'RF18539007547034', rytkoset_theme_get_mollie_rf_reference( $order ) );
	}

	public function test_returns_empty_for_non_rf_order(): void {
		$order = $this->order( 'mollie_wc_gateway_creditcard', 'Viite: RF18 5390 0754 7034' );

		$this->assertSame( '', rytkoset_theme_get_mollie_rf_reference( $order ) );
	}

	public function test_returns_empty_when_no_instructions(): void {
		$this->assertSame( '', rytkoset_theme_get_mollie_rf_reference( $this->order( 'mollie_wc_gateway_banktransfer' ) ) );
	}
}
