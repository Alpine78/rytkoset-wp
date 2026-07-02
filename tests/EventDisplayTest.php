<?php
/**
 * Tests for inc/events.php — fee/date/time display formatting derived from event meta.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventDisplayTest extends Rytkoset_Theme_Test_Case {

	/**
	 * @return array<string,string>
	 */
	private function keys(): array {
		return rytkoset_theme_get_event_details_meta_keys();
	}

	// --- get_event_fee_display ---------------------------------------------

	public function test_free_event_shows_maksuton(): void {
		update_post_meta( 10, $this->keys()['fee_type'], 'free' );
		$this->assertSame( 'Maksuton', rytkoset_theme_get_event_fee_display( 10 ) );
	}

	public function test_free_event_with_price_text_shows_formatted_price_text(): void {
		update_post_meta( 10, $this->keys()['fee_type'], 'free' );
		update_post_meta( 10, $this->keys()['price_text'], '45' );

		$this->assertSame( '45 €', rytkoset_theme_get_event_fee_display( 10 ) );
	}

	public function test_paid_event_with_price_is_formatted(): void {
		update_post_meta( 10, $this->keys()['fee_type'], 'paid' );
		update_post_meta( 10, $this->keys()['price_text'], '12,50' );

		$this->assertSame( '12,50 €', rytkoset_theme_get_event_fee_display( 10 ) );
	}

	public function test_paid_event_without_price_shows_maksullinen(): void {
		update_post_meta( 10, $this->keys()['fee_type'], 'paid' );
		$this->assertSame( 'Maksullinen', rytkoset_theme_get_event_fee_display( 10 ) );
	}

	public function test_missing_fee_type_returns_price_text(): void {
		$this->assertSame( '', rytkoset_theme_get_event_fee_display( 10 ) );
	}

	// --- get_event_time_display --------------------------------------------

	public function test_time_display_with_start_and_end(): void {
		update_post_meta( 10, $this->keys()['start_time'], '10:00' );
		update_post_meta( 10, $this->keys()['end_time'], '14:00' );

		$this->assertSame( '10:00–14:00', rytkoset_theme_get_event_time_display( 10 ) );
	}

	public function test_time_display_with_start_only(): void {
		update_post_meta( 10, $this->keys()['start_time'], '10:00' );
		$this->assertSame( '10:00', rytkoset_theme_get_event_time_display( 10 ) );
	}

	public function test_time_display_empty_when_no_times(): void {
		$this->assertSame( '', rytkoset_theme_get_event_time_display( 10 ) );
	}

	// --- get_event_date_display --------------------------------------------

	public function test_date_display_formatted_with_site_format(): void {
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2026-06-24' );
		$this->assertSame( '24.6.2026', rytkoset_theme_get_event_date_display( 10 ) );
	}

	public function test_date_display_empty_without_date(): void {
		$this->assertSame( '', rytkoset_theme_get_event_date_display( 10 ) );
	}
}
