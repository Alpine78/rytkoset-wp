<?php
/**
 * Tests for inc/chat.php — the chat usage stats counters (#472).
 *
 * Covers the pure bump helpers, the wp_options-backed recorder functions,
 * the usage-stats summary and the error-type label formatter. The Dashboard
 * widget registration/render is admin-screen glue and is validated manually
 * (wp-admin) rather than unit tested, per the project's testing guidance for
 * render-heavy admin screens.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatUsageStatsTest extends Rytkoset_Theme_Test_Case {

	// --- rytkoset_theme_chat_stat_value() ------------------------------------

	public function test_stat_value_returns_fallback_for_non_array(): void {
		$this->assertSame( 0, rytkoset_theme_chat_stat_value( 'not-an-array', 'count', 0 ) );
		$this->assertSame( 0, rytkoset_theme_chat_stat_value( null, 'count', 0 ) );
	}

	public function test_stat_value_returns_fallback_for_missing_key(): void {
		$this->assertSame( '', rytkoset_theme_chat_stat_value( array( 'count' => 3 ), 'last_type', '' ) );
	}

	public function test_stat_value_returns_existing_key(): void {
		$this->assertSame( 3, rytkoset_theme_chat_stat_value( array( 'count' => 3 ), 'count', 0 ) );
	}

	// --- rytkoset_theme_chat_bump_stat() -------------------------------------

	public function test_bump_stat_starts_at_one_for_missing_stat(): void {
		$bumped = rytkoset_theme_chat_bump_stat( array(), 1000 );

		$this->assertSame( 1, $bumped['count'] );
		$this->assertSame( 1000, $bumped['last_at'] );
	}

	public function test_bump_stat_increments_existing_count(): void {
		$bumped = rytkoset_theme_chat_bump_stat( array( 'count' => 4, 'last_at' => 500 ), 1000 );

		$this->assertSame( 5, $bumped['count'] );
		$this->assertSame( 1000, $bumped['last_at'] );
	}

	public function test_bump_stat_ignores_invalid_stored_value(): void {
		$bumped = rytkoset_theme_chat_bump_stat( 'corrupted', 1000 );

		$this->assertSame( 1, $bumped['count'] );
	}

	// --- rytkoset_theme_chat_bump_error_stat() -------------------------------

	public function test_bump_error_stat_includes_last_type(): void {
		$bumped = rytkoset_theme_chat_bump_error_stat( array(), 1000, 'network' );

		$this->assertSame( 1, $bumped['count'] );
		$this->assertSame( 1000, $bumped['last_at'] );
		$this->assertSame( 'network', $bumped['last_type'] );
	}

	public function test_bump_error_stat_overwrites_previous_type(): void {
		$previous = array(
			'count'     => 2,
			'last_at'   => 500,
			'last_type' => 'empty_reply',
		);

		$bumped = rytkoset_theme_chat_bump_error_stat( $previous, 1000, 'http_502' );

		$this->assertSame( 3, $bumped['count'] );
		$this->assertSame( 'http_502', $bumped['last_type'] );
	}

	// --- rytkoset_theme_chat_record_*_stat() ---------------------------------

	public function test_record_message_sent_stat_persists_to_option(): void {
		rytkoset_theme_chat_record_message_sent_stat();
		rytkoset_theme_chat_record_message_sent_stat();

		$stat = get_option( 'rytkoset_chat_stat_messages' );

		$this->assertSame( 2, $stat['count'] );
		$this->assertGreaterThan( 0, $stat['last_at'] );
	}

	public function test_record_rate_limit_hit_stat_persists_to_option(): void {
		rytkoset_theme_chat_record_rate_limit_hit_stat();

		$stat = get_option( 'rytkoset_chat_stat_rate_limit' );

		$this->assertSame( 1, $stat['count'] );
		$this->assertGreaterThan( 0, $stat['last_at'] );
	}

	public function test_record_error_stat_persists_type_and_increments_count(): void {
		rytkoset_theme_chat_record_error_stat( 'network' );
		rytkoset_theme_chat_record_error_stat( 'http_503' );

		$stat = get_option( 'rytkoset_chat_stat_error' );

		$this->assertSame( 2, $stat['count'] );
		$this->assertSame( 'http_503', $stat['last_type'] );
	}

	// --- rytkoset_theme_chat_register_rate_limit_hit() side effect ----------

	public function test_register_rate_limit_hit_does_not_record_stat_below_limit(): void {
		rytkoset_theme_chat_register_rate_limit_hit( '203.0.113.1', 5 );

		$this->assertFalse( get_option( 'rytkoset_chat_stat_rate_limit', false ) );
	}

	public function test_register_rate_limit_hit_records_stat_once_limit_exceeded(): void {
		$ip    = '203.0.113.2';
		$limit = 2;

		rytkoset_theme_chat_register_rate_limit_hit( $ip, $limit ); // 1st request, count -> 1.
		rytkoset_theme_chat_register_rate_limit_hit( $ip, $limit ); // 2nd request, count -> 2.
		$exceeded = rytkoset_theme_chat_register_rate_limit_hit( $ip, $limit ); // 3rd request, exceeded.

		$this->assertTrue( $exceeded );

		$stat = get_option( 'rytkoset_chat_stat_rate_limit' );
		$this->assertSame( 1, $stat['count'] );
	}

	// --- rytkoset_theme_chat_get_usage_stats() -------------------------------

	public function test_get_usage_stats_defaults_to_zero_when_nothing_recorded(): void {
		$stats = rytkoset_theme_chat_get_usage_stats();

		$this->assertSame( array( 'count' => 0, 'last_at' => 0 ), $stats['messages_sent'] );
		$this->assertSame( array( 'count' => 0, 'last_at' => 0 ), $stats['rate_limit_hits'] );
		$this->assertSame( array( 'count' => 0, 'last_at' => 0, 'last_type' => '' ), $stats['last_error'] );
	}

	public function test_get_usage_stats_reflects_recorded_values(): void {
		rytkoset_theme_chat_record_message_sent_stat();
		rytkoset_theme_chat_record_rate_limit_hit_stat();
		rytkoset_theme_chat_record_error_stat( 'empty_reply' );

		$stats = rytkoset_theme_chat_get_usage_stats();

		$this->assertSame( 1, $stats['messages_sent']['count'] );
		$this->assertSame( 1, $stats['rate_limit_hits']['count'] );
		$this->assertSame( 1, $stats['last_error']['count'] );
		$this->assertSame( 'empty_reply', $stats['last_error']['last_type'] );
	}

	// --- rytkoset_theme_chat_get_error_type_label() --------------------------

	public function test_error_type_label_maps_known_types(): void {
		$this->assertSame( 'Yhteysvirhe Mistraliin', rytkoset_theme_chat_get_error_type_label( 'network' ) );
		$this->assertSame( 'Tyhjä vastaus Mistralilta', rytkoset_theme_chat_get_error_type_label( 'empty_reply' ) );
	}

	public function test_error_type_label_formats_http_status_dynamically(): void {
		$this->assertSame( 'Mistral vastasi HTTP-virheellä 502', rytkoset_theme_chat_get_error_type_label( 'http_502' ) );
		$this->assertSame( 'Mistral vastasi HTTP-virheellä 429', rytkoset_theme_chat_get_error_type_label( 'http_429' ) );
	}

	public function test_error_type_label_returns_empty_for_empty_type(): void {
		$this->assertSame( '', rytkoset_theme_chat_get_error_type_label( '' ) );
	}

	public function test_error_type_label_falls_back_to_raw_type_for_unknown_values(): void {
		$this->assertSame( 'jokin_uusi_tyyppi', rytkoset_theme_chat_get_error_type_label( 'jokin_uusi_tyyppi' ) );
	}
}
