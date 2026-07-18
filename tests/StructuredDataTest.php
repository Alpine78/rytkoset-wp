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

	public function test_helsinki_summer_event_uses_daylight_saving_offset(): void {
		$this->assertSame(
			'2026-08-29T11:30:00+03:00',
			rytkoset_theme_build_event_iso_datetime( '2026-08-29', '11:30' )
		);
	}
}
