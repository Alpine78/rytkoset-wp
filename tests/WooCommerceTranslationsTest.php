<?php
/**
 * Tests for inc/woocommerce-translations.php — WooCommerce core string localization overrides.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class WooCommerceTranslationsTest extends Rytkoset_Theme_Test_Case {

	public function test_orders_page_prompt_translated_in_finnish(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$source = 'Confirm your email address to check for past orders and link them to your account.';

		$this->assertSame(
			'Vahvista sähköpostiosoitteesi, jotta voimme etsiä aiemmat tilauksesi ja yhdistää ne käyttäjätiliisi.',
			rytkoset_theme_woocommerce_finnish_strings( $source, $source, 'woocommerce' )
		);
	}

	public function test_confirm_button_translated_in_finnish(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$this->assertSame(
			'Vahvista sähköpostiosoite',
			rytkoset_theme_woocommerce_finnish_strings( 'Confirm email address', 'Confirm email address', 'woocommerce' )
		);
	}

	public function test_throttled_prompt_with_em_dash_translated(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$source = 'Confirm your email address to check for past orders. A confirmation link was sent recently — please check your inbox.';

		$this->assertSame(
			'Vahvista sähköpostiosoitteesi, jotta voimme etsiä aiemmat tilauksesi. Vahvistuslinkki on lähetetty äskettäin – tarkista sähköpostisi.',
			rytkoset_theme_woocommerce_finnish_strings( $source, $source, 'woocommerce' )
		);
	}

	public function test_result_notices_translated_in_finnish(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$this->assertSame(
			'Sähköpostiosoitteesi on vahvistettu.',
			rytkoset_theme_woocommerce_finnish_strings(
				'Your email address has been confirmed.',
				'Your email address has been confirmed.',
				'woocommerce'
			)
		);
		$this->assertSame(
			'Virheellinen pyyntö. Yritä uudelleen.',
			rytkoset_theme_woocommerce_finnish_strings(
				'Invalid request. Please try again.',
				'Invalid request. Please try again.',
				'woocommerce'
			)
		);
	}

	public function test_unmapped_woocommerce_string_passes_through(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$this->assertSame(
			'Ostoskori',
			rytkoset_theme_woocommerce_finnish_strings( 'Ostoskori', 'Cart', 'woocommerce' )
		);
	}

	public function test_other_text_domain_passes_through(): void {
		$GLOBALS['rytkoset_test_locale'] = 'fi_FI';

		$this->assertSame(
			'Confirm email address',
			rytkoset_theme_woocommerce_finnish_strings( 'Confirm email address', 'Confirm email address', 'my-plugin' )
		);
	}

	public function test_non_finnish_locale_passes_through(): void {
		$GLOBALS['rytkoset_test_locale'] = 'en_US';

		$this->assertSame(
			'Confirm email address',
			rytkoset_theme_woocommerce_finnish_strings( 'Confirm email address', 'Confirm email address', 'woocommerce' )
		);
	}

	public function test_every_override_has_a_non_empty_translation(): void {
		foreach ( rytkoset_theme_get_woocommerce_finnish_string_overrides() as $source => $translation ) {
			$this->assertNotSame( '', trim( (string) $translation ), (string) $source );
		}
	}
}
