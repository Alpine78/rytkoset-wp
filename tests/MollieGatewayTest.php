<?php
/**
 * Tests for inc/woocommerce-mollie.php — gateway title localization and bank-gateway demotion.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MollieGatewayTest extends Rytkoset_Theme_Test_Case {

	// --- demote_mollie_bank_gateways ---------------------------------------

	public function test_bank_gateways_moved_to_end_preserving_order(): void {
		$gateways = array(
			'mollie_wc_gateway_banktransfer' => 'pankki',
			'mollie_wc_gateway_creditcard'   => 'kortti',
			'mollie_wc_gateway_paybybank'    => 'verkkopankki',
			'mollie_wc_gateway_ideal'        => 'ideal',
		);

		$ordered = rytkoset_theme_demote_mollie_bank_gateways( $gateways );

		$this->assertSame(
			array(
				'mollie_wc_gateway_creditcard',
				'mollie_wc_gateway_ideal',
				'mollie_wc_gateway_banktransfer',
				'mollie_wc_gateway_paybybank',
			),
			array_keys( $ordered )
		);
	}

	public function test_non_array_returned_unchanged(): void {
		$this->assertSame( 'nope', rytkoset_theme_demote_mollie_bank_gateways( 'nope' ) );
		$this->assertSame( array(), rytkoset_theme_demote_mollie_bank_gateways( array() ) );
	}

	// --- mollie_gateway_title ----------------------------------------------

	public function test_paybybank_title_localized_in_finnish(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$this->assertSame(
			'Verkkopankkimaksu',
			rytkoset_theme_mollie_gateway_title( 'Pay by bank', 'mollie_wc_gateway_paybybank' )
		);
	}

	public function test_other_gateway_title_unchanged_in_finnish(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$this->assertSame(
			'Luottokortti',
			rytkoset_theme_mollie_gateway_title( 'Luottokortti', 'mollie_wc_gateway_creditcard' )
		);
	}

	public function test_paybybank_title_unchanged_in_non_finnish_locale(): void {
		$GLOBALS['rytkoset_test_locale'] = 'en_US';

		$this->assertSame(
			'Pay by bank',
			rytkoset_theme_mollie_gateway_title( 'Pay by bank', 'mollie_wc_gateway_paybybank' )
		);
	}
}
