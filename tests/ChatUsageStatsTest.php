<?php
/**
 * Tests for inc/chat.php — the chat usage stats counters (#472, #567).
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

	// --- prompt-cache usage extraction and aggregation -----------------------

	public function test_extract_prompt_cache_usage_reads_mistral_usage_fields(): void {
		$body = array(
			'usage' => array(
				'prompt_tokens'         => 1013,
				'prompt_tokens_details' => array( 'cached_tokens' => 1008 ),
			),
		);

		$this->assertSame(
			array(
				'prompt_tokens' => 1013,
				'cached_tokens' => 1008,
			),
			rytkoset_theme_chat_extract_prompt_cache_usage( $body )
		);
	}

	public function test_extract_prompt_cache_usage_accepts_zero_cache_hit(): void {
		$body = array(
			'usage' => array(
				'prompt_tokens'         => 500,
				'prompt_tokens_details' => array( 'cached_tokens' => 0 ),
			),
		);

		$this->assertSame(
			array(
				'prompt_tokens' => 500,
				'cached_tokens' => 0,
			),
			rytkoset_theme_chat_extract_prompt_cache_usage( $body )
		);
	}

	public function test_extract_prompt_cache_usage_rejects_missing_or_invalid_values(): void {
		$this->assertNull( rytkoset_theme_chat_extract_prompt_cache_usage( null ) );
		$this->assertNull( rytkoset_theme_chat_extract_prompt_cache_usage( array( 'usage' => array( 'prompt_tokens' => 100 ) ) ) );
		$this->assertNull(
			rytkoset_theme_chat_extract_prompt_cache_usage(
				array(
					'usage' => array(
						'prompt_tokens'         => '100',
						'prompt_tokens_details' => array( 'cached_tokens' => 50 ),
					),
				)
			)
		);
		$this->assertNull(
			rytkoset_theme_chat_extract_prompt_cache_usage(
				array(
					'usage' => array(
						'prompt_tokens'         => 100,
						'prompt_tokens_details' => array( 'cached_tokens' => 101 ),
					),
				)
			)
		);
	}

	public function test_bump_prompt_cache_stat_aggregates_calls_tokens_and_hits(): void {
		$stat = rytkoset_theme_chat_bump_prompt_cache_stat(
			array(),
			array(
				'prompt_tokens' => 500,
				'cached_tokens' => 0,
			),
			1000
		);
		$stat = rytkoset_theme_chat_bump_prompt_cache_stat(
			$stat,
			array(
				'prompt_tokens' => 800,
				'cached_tokens' => 600,
			),
			2000
		);

		$this->assertSame( 2, $stat['api_calls'] );
		$this->assertSame( 1, $stat['cache_hit_calls'] );
		$this->assertSame( 1300, $stat['prompt_tokens'] );
		$this->assertSame( 600, $stat['cached_tokens'] );
		$this->assertSame( 800, $stat['last_prompt_tokens'] );
		$this->assertSame( 600, $stat['last_cached_tokens'] );
		$this->assertSame( 2000, $stat['last_at'] );
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

	public function test_record_prompt_cache_usage_stat_persists_only_aggregate_values(): void {
		rytkoset_theme_chat_record_prompt_cache_usage_stat(
			array(
				'prompt_tokens' => 1013,
				'cached_tokens' => 1008,
			)
		);

		$stat = get_option( 'rytkoset_chat_stat_prompt_cache' );

		$this->assertSame(
			array(
				'api_calls',
				'cache_hit_calls',
				'prompt_tokens',
				'cached_tokens',
				'last_prompt_tokens',
				'last_cached_tokens',
				'last_at',
			),
			array_keys( $stat )
		);
		$this->assertSame( 1013, $stat['prompt_tokens'] );
		$this->assertSame( 1008, $stat['cached_tokens'] );
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
		$this->assertSame(
			array(
				'api_calls'          => 0,
				'cache_hit_calls'    => 0,
				'prompt_tokens'      => 0,
				'cached_tokens'      => 0,
				'last_prompt_tokens' => 0,
				'last_cached_tokens' => 0,
				'last_at'            => 0,
			),
			$stats['prompt_cache']
		);
	}

	public function test_get_usage_stats_reflects_recorded_values(): void {
		rytkoset_theme_chat_record_message_sent_stat();
		rytkoset_theme_chat_record_rate_limit_hit_stat();
		rytkoset_theme_chat_record_error_stat( 'empty_reply' );
		rytkoset_theme_chat_record_prompt_cache_usage_stat(
			array(
				'prompt_tokens' => 1000,
				'cached_tokens' => 800,
			)
		);

		$stats = rytkoset_theme_chat_get_usage_stats();

		$this->assertSame( 1, $stats['messages_sent']['count'] );
		$this->assertSame( 1, $stats['rate_limit_hits']['count'] );
		$this->assertSame( 1, $stats['last_error']['count'] );
		$this->assertSame( 'empty_reply', $stats['last_error']['last_type'] );
		$this->assertSame( 1, $stats['prompt_cache']['api_calls'] );
		$this->assertSame( 1000, $stats['prompt_cache']['prompt_tokens'] );
		$this->assertSame( 800, $stats['prompt_cache']['cached_tokens'] );
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
