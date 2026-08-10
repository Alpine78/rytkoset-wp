<?php
/**
 * Tests for inc/structured-data.php — Event eligibility, offers and timezones.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class StructuredDataTest extends Rytkoset_Theme_Test_Case {

	public function test_event_schema_is_enabled_by_default(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Sukukokous' );

		$this->assertTrue( rytkoset_theme_event_schema_is_enabled( 10 ) );
	}

	public function test_event_schema_can_be_disabled_explicitly(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Yhteiskuljetus' );
		update_post_meta( 10, rytkoset_theme_get_event_schema_enabled_meta_key(), 'no' );

		$this->assertFalse( rytkoset_theme_event_schema_is_enabled( 10 ) );
		$this->assertNull( rytkoset_theme_get_event_schema( 10 ) );
	}

	public function test_past_event_has_no_active_offer(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Mennyt tapahtuma' );
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2025-10-07' );
		update_post_meta( 10, '_rytkoset_event_fee_type', 'free' );
		$GLOBALS['rytkoset_test_now'] = '2026-07-18 12:00:00';

		$this->assertNull( rytkoset_theme_get_event_schema_offers( 10 ) );
	}

	public function test_future_free_event_keeps_its_offer(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Tuleva tapahtuma' );
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2026-08-29' );
		update_post_meta( 10, '_rytkoset_event_fee_type', 'free' );
		$GLOBALS['rytkoset_test_now'] = '2026-07-18 12:00:00';

		$this->assertSame(
			array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'EUR',
				'availability'  => 'https://schema.org/InStock',
			),
			rytkoset_theme_get_event_schema_offers( 10 )
		);
	}

	// --- paid event offers.price (#650) -----------------------------------

	/**
	 * Registers an upcoming paid event and returns its ID.
	 */
	private function register_upcoming_paid_event(): int {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Sukukokous Tampereella' );
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2026-08-29' );
		update_post_meta( 10, '_rytkoset_event_fee_type', 'paid' );
		$GLOBALS['rytkoset_test_now'] = '2026-07-18 12:00:00';

		return 10;
	}

	public function test_paid_event_takes_price_from_linked_simple_product(): void {
		$event_id = $this->register_upcoming_paid_event();
		update_post_meta( $event_id, '_rytkoset_event_price_text', 'Osallistumismaksu 49 € / aikuinen ja 24,50 € / lapsi' );
		rytkoset_test_register_product( 50, 'publish', 'Osallistumismaksu', array( '_price' => '49.00' ) );
		update_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), 50 );

		$offers = rytkoset_theme_get_event_schema_offers( $event_id );

		$this->assertSame( '49.00', $offers['price'] );
		$this->assertSame( 'EUR', $offers['priceCurrency'] );
		$this->assertArrayHasKey( 'url', $offers );
	}

	public function test_paid_event_takes_lowest_variation_price_from_variable_product(): void {
		$event_id = $this->register_upcoming_paid_event();
		update_post_meta( $event_id, '_rytkoset_event_price_text', 'Aikuiset 49 €, lapset 24,50 €' );
		rytkoset_test_register_variable_product(
			50,
			'publish',
			'Osallistumismaksu',
			array(
				'_price'            => '49.00',
				'_variation_prices' => array( '49.00', '24.50' ),
			)
		);
		update_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), 50 );

		$offers = rytkoset_theme_get_event_schema_offers( $event_id );

		// Google defines offers.price as the lowest available ticket price.
		$this->assertSame( '24.50', $offers['price'] );
	}

	public function test_paid_event_without_product_falls_back_to_numeric_price_text(): void {
		$event_id = $this->register_upcoming_paid_event();
		update_post_meta( $event_id, '_rytkoset_event_price_text', '20,50' );

		$offers = rytkoset_theme_get_event_schema_offers( $event_id );

		$this->assertSame( '20.50', $offers['price'] );
		$this->assertArrayNotHasKey( 'url', $offers );
	}

	public function test_paid_event_never_guesses_a_free_form_price(): void {
		$event_id = $this->register_upcoming_paid_event();
		update_post_meta( $event_id, '_rytkoset_event_price_text', 'Aikuiset 20 €, lapset 10 €' );

		$this->assertNull( rytkoset_theme_get_event_schema_offers( $event_id ) );
	}

	public function test_unpriced_linked_product_falls_back_to_numeric_price_text(): void {
		$event_id = $this->register_upcoming_paid_event();
		update_post_meta( $event_id, '_rytkoset_event_price_text', '30' );
		rytkoset_test_register_product( 50, 'publish', 'Osallistumismaksu' );
		update_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), 50 );

		$offers = rytkoset_theme_get_event_schema_offers( $event_id );

		$this->assertSame( '30', $offers['price'] );
	}

	public function test_schema_price_normalization_rejects_non_numeric_values(): void {
		$this->assertSame( '24.50', rytkoset_theme_normalize_schema_price( '24,50' ) );
		$this->assertSame( '49', rytkoset_theme_normalize_schema_price( ' 49 ' ) );
		$this->assertSame( '', rytkoset_theme_normalize_schema_price( '49 €' ) );
		$this->assertSame( '', rytkoset_theme_normalize_schema_price( '' ) );
		$this->assertSame( '', rytkoset_theme_normalize_schema_price( array( '49' ) ) );
	}

	public function test_helsinki_summer_event_uses_daylight_saving_offset(): void {
		$this->assertSame(
			'2026-08-29T11:30:00+03:00',
			rytkoset_theme_build_event_iso_datetime( '2026-08-29', '11:30' )
		);
	}
}
