<?php
/**
 * Tests for the chat's cost and load guards (#749): the site-wide upstream
 * cap, the per-request time budget, message list limits, IPv6 bucketing and
 * the foreign-origin check.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ChatCostGuardsTest extends Rytkoset_Theme_Test_Case {

	// --- rytkoset_theme_chat_advance_upstream_budget() -----------------------

	public function test_budget_opens_both_windows_on_first_call(): void {
		$result = rytkoset_theme_chat_advance_upstream_budget( array(), 1000, 5, 10 );

		$this->assertTrue( $result['allowed'] );
		$this->assertSame(
			array(
				'hour_start' => 1000,
				'hour_count' => 1,
				'day_start'  => 1000,
				'day_count'  => 1,
			),
			$result['record']
		);
	}

	public function test_budget_denies_at_hour_cap_without_counting(): void {
		$record = array(
			'hour_start' => 1000,
			'hour_count' => 5,
			'day_start'  => 1000,
			'day_count'  => 5,
		);

		$result = rytkoset_theme_chat_advance_upstream_budget( $record, 1000 + HOUR_IN_SECONDS - 1, 5, 10 );

		$this->assertFalse( $result['allowed'] );
		$this->assertSame( $record, $result['record'] );
	}

	public function test_budget_hour_window_rolls_over_but_day_keeps_counting(): void {
		$record = array(
			'hour_start' => 1000,
			'hour_count' => 5,
			'day_start'  => 1000,
			'day_count'  => 5,
		);

		$result = rytkoset_theme_chat_advance_upstream_budget( $record, 1000 + HOUR_IN_SECONDS, 5, 10 );

		$this->assertTrue( $result['allowed'] );
		$this->assertSame( 1000 + HOUR_IN_SECONDS, $result['record']['hour_start'] );
		$this->assertSame( 1, $result['record']['hour_count'] );
		$this->assertSame( 1000, $result['record']['day_start'] );
		$this->assertSame( 6, $result['record']['day_count'] );
	}

	public function test_budget_day_cap_blocks_even_in_a_fresh_hour(): void {
		$record = array(
			'hour_start' => 1000,
			'hour_count' => 2,
			'day_start'  => 1000,
			'day_count'  => 10,
		);

		$result = rytkoset_theme_chat_advance_upstream_budget( $record, 1000 + 5 * HOUR_IN_SECONDS, 5, 10 );

		$this->assertFalse( $result['allowed'] );
		$this->assertSame( 0, $result['record']['hour_count'] );
		$this->assertSame( 10, $result['record']['day_count'] );
	}

	public function test_budget_day_window_rolls_over(): void {
		$record = array(
			'hour_start' => 1000,
			'hour_count' => 5,
			'day_start'  => 1000,
			'day_count'  => 10,
		);

		$result = rytkoset_theme_chat_advance_upstream_budget( $record, 1000 + DAY_IN_SECONDS, 5, 10 );

		$this->assertTrue( $result['allowed'] );
		$this->assertSame( 1, $result['record']['hour_count'] );
		$this->assertSame( 1, $result['record']['day_count'] );
		$this->assertSame( 1000 + DAY_IN_SECONDS, $result['record']['day_start'] );
	}

	public function test_budget_treats_corrupt_or_future_record_as_new(): void {
		$this->assertTrue( rytkoset_theme_chat_advance_upstream_budget( 'garbage', 1000, 1, 1 )['allowed'] );

		$future = array(
			'hour_start' => 9000,
			'hour_count' => 99,
			'day_start'  => 9000,
			'day_count'  => 99,
		);
		$result = rytkoset_theme_chat_advance_upstream_budget( $future, 1000, 5, 10 );

		$this->assertTrue( $result['allowed'] );
		$this->assertSame( 1, $result['record']['day_count'] );
	}

	// --- rytkoset_theme_chat_reserve_upstream_call() + stats -----------------

	public function test_reserve_counts_calls_and_records_denials(): void {
		add_filter( 'rytkoset_theme_chat_global_hourly_limit', array( $this, 'limit_two' ) );

		try {
			$this->assertTrue( rytkoset_theme_chat_reserve_upstream_call() );
			$this->assertTrue( rytkoset_theme_chat_reserve_upstream_call() );
			$this->assertFalse( rytkoset_theme_chat_reserve_upstream_call() );
			$this->assertFalse( rytkoset_theme_chat_reserve_upstream_call() );
		} finally {
			remove_filter( 'rytkoset_theme_chat_global_hourly_limit', array( $this, 'limit_two' ) );
		}

		$stats = rytkoset_theme_chat_get_usage_stats();
		$this->assertSame( 2, $stats['upstream_budget']['hour_count'] );
		$this->assertSame( 2, $stats['upstream_budget']['day_count'] );
		$this->assertSame( 2, $stats['global_limit_hits']['count'] );
		$this->assertGreaterThan( 0, $stats['global_limit_hits']['last_at'] );
	}

	public function test_usage_stats_show_expired_budget_windows_as_empty(): void {
		update_option(
			rytkoset_theme_chat_get_upstream_budget_option_name(),
			array(
				'hour_start' => time() - 2 * HOUR_IN_SECONDS,
				'hour_count' => 40,
				'day_start'  => time() - 2 * HOUR_IN_SECONDS,
				'day_count'  => 70,
			)
		);

		$stats = rytkoset_theme_chat_get_usage_stats();

		$this->assertSame( 0, $stats['upstream_budget']['hour_count'] );
		$this->assertSame( 70, $stats['upstream_budget']['day_count'] );
		$this->assertSame( 0, $stats['global_limit_hits']['count'] );
	}

	public function test_global_limit_defaults(): void {
		$this->assertSame( 120, rytkoset_theme_chat_get_global_hourly_limit() );
		$this->assertSame( 500, rytkoset_theme_chat_get_global_daily_limit() );
		$this->assertSame( 40.0, rytkoset_theme_chat_get_request_time_budget() );
		$this->assertSame( 100, rytkoset_theme_chat_get_max_request_messages() );
	}

	public function test_time_budget_error_type_has_a_label(): void {
		$this->assertSame( 'Pyynnön aikabudjetti ylittyi', rytkoset_theme_chat_get_error_type_label( 'time_budget' ) );
	}

	// --- rytkoset_theme_chat_get_upstream_timeout() --------------------------

	public function test_upstream_timeout_is_capped_by_the_per_call_ceiling(): void {
		$this->assertSame( 20.0, rytkoset_theme_chat_get_upstream_timeout( 140.0, 100.0 ) );
	}

	public function test_upstream_timeout_shrinks_to_the_remaining_budget(): void {
		$this->assertSame( 12.5, rytkoset_theme_chat_get_upstream_timeout( 140.0, 127.5 ) );
		$this->assertSame( 3.0, rytkoset_theme_chat_get_upstream_timeout( 140.0, 137.0 ) );
	}

	public function test_upstream_timeout_is_zero_when_too_little_remains(): void {
		$this->assertSame( 0.0, rytkoset_theme_chat_get_upstream_timeout( 140.0, 137.5 ) );
		$this->assertSame( 0.0, rytkoset_theme_chat_get_upstream_timeout( 140.0, 150.0 ) );
	}

	// --- rytkoset_theme_chat_normalize_rate_limit_ip() -----------------------

	public function test_ipv4_bucket_is_the_address_itself(): void {
		$this->assertSame( '203.0.113.7', rytkoset_theme_chat_normalize_rate_limit_ip( '203.0.113.7' ) );
	}

	public function test_ipv6_addresses_in_one_64_share_a_bucket(): void {
		$first  = rytkoset_theme_chat_normalize_rate_limit_ip( '2001:db8:abcd:12:1::1' );
		$second = rytkoset_theme_chat_normalize_rate_limit_ip( '2001:0db8:abcd:0012:ffff:ffff:ffff:ffff' );

		$this->assertSame( '2001:db8:abcd:12::/64', $first );
		$this->assertSame( $first, $second );
		$this->assertNotSame( $first, rytkoset_theme_chat_normalize_rate_limit_ip( '2001:db8:abcd:13::1' ) );
	}

	public function test_ipv4_mapped_ipv6_shares_the_ipv4_bucket(): void {
		$this->assertSame( '203.0.113.7', rytkoset_theme_chat_normalize_rate_limit_ip( '::ffff:203.0.113.7' ) );
		$this->assertSame( '203.0.113.7', rytkoset_theme_chat_normalize_rate_limit_ip( '::ffff:cb00:7107' ) );
	}

	public function test_invalid_addresses_share_the_unknown_bucket(): void {
		$this->assertSame( 'unknown', rytkoset_theme_chat_normalize_rate_limit_ip( '' ) );
		$this->assertSame( 'unknown', rytkoset_theme_chat_normalize_rate_limit_ip( 'unknown' ) );
		$this->assertSame( 'unknown', rytkoset_theme_chat_normalize_rate_limit_ip( 'fe80::1%eth0' ) );
		$this->assertSame( 'unknown', rytkoset_theme_chat_normalize_rate_limit_ip( array( '::1' ) ) );
	}

	public function test_rate_limit_counts_a_rotating_ipv6_64_as_one_client(): void {
		$this->assertFalse( rytkoset_theme_chat_register_rate_limit_hit( '2001:db8:1:2::1', 2 ) );
		$this->assertFalse( rytkoset_theme_chat_register_rate_limit_hit( '2001:db8:1:2::2', 2 ) );
		$this->assertTrue( rytkoset_theme_chat_register_rate_limit_hit( '2001:db8:1:2:aaaa::3', 2 ) );

		// Another /64 still has its own quota.
		$this->assertFalse( rytkoset_theme_chat_register_rate_limit_hit( '2001:db8:1:3::1', 2 ) );
	}

	// --- rytkoset_theme_chat_origin_is_allowed() -----------------------------

	public function test_missing_origin_is_allowed(): void {
		$this->assertTrue( rytkoset_theme_chat_origin_is_allowed( '' ) );
	}

	public function test_own_origin_is_allowed_across_scheme_www_and_default_port(): void {
		$this->assertTrue( rytkoset_theme_chat_origin_is_allowed( 'https://rytkoset.test' ) );
		$this->assertTrue( rytkoset_theme_chat_origin_is_allowed( 'http://rytkoset.test' ) );
		$this->assertTrue( rytkoset_theme_chat_origin_is_allowed( 'https://www.rytkoset.test' ) );
		$this->assertTrue( rytkoset_theme_chat_origin_is_allowed( 'https://RYTKOSET.test:443' ) );
	}

	public function test_foreign_origins_are_rejected(): void {
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'https://evil.example' ) );
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'https://rytkoset.test.evil.example' ) );
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'https://evilrytkoset.test' ) );
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'https://rytkoset.test:8443' ) );
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'null' ) );
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'chrome-extension://abcdef' ) );
		$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'https://' ) );
	}

	public function test_extra_origin_hosts_can_be_allowed_by_filter(): void {
		add_filter( 'rytkoset_theme_chat_allowed_origin_hosts', array( $this, 'allow_staging_origin' ) );

		try {
			$this->assertTrue( rytkoset_theme_chat_origin_is_allowed( 'https://staging.example:8443' ) );
			$this->assertFalse( rytkoset_theme_chat_origin_is_allowed( 'https://staging.example' ) );
		} finally {
			remove_filter( 'rytkoset_theme_chat_allowed_origin_hosts', array( $this, 'allow_staging_origin' ) );
		}
	}

	// --- rytkoset_theme_chat_prepare_messages() ------------------------------

	public function test_prepare_messages_skips_non_string_role_and_content(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'user',
					'content' => array( 'taulukko' ),
				),
				array(
					'role'    => array( 'user' ),
					'content' => 'rooli taulukkona',
				),
				array(
					'role'    => 'user',
					'content' => 12345,
				),
				array( 'role' => 'user' ),
				array(
					'role'    => 'user',
					'content' => 'Kelvollinen viesti',
				),
			),
			8,
			1000
		);

		$this->assertSame(
			array(
				array(
					'role'    => 'user',
					'content' => 'Kelvollinen viesti',
				),
			),
			$messages
		);
	}

	public function test_prepare_messages_rejects_a_non_array_list(): void {
		$this->assertSame( array(), rytkoset_theme_chat_prepare_messages( 'ei taulukko', 8, 1000 ) );
	}

	public function test_prepare_messages_keeps_last_valid_rows_past_invalid_recent_ones(): void {
		$raw = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$raw[] = array(
				'role'    => 'user',
				'content' => 'Kysymys ' . $i . ' äöå',
			);
		}
		// Recent rows that the validator drops must not shrink the history.
		$raw[] = array(
			'role'    => 'assistant',
			'content' => '<p>rikkinäinen vastaus</p>',
		);
		$raw[] = array(
			'role'    => 'system',
			'content' => 'ei sallittu rooli',
		);
		$raw[] = array(
			'role'    => 'user',
			'content' => 'Viimeisin kysymys',
		);

		$messages = rytkoset_theme_chat_prepare_messages( $raw, 4, 1000 );

		$this->assertSame(
			array( 'Kysymys 4 äöå', 'Kysymys 5 äöå', 'Kysymys 6 äöå', 'Viimeisin kysymys' ),
			array_column( $messages, 'content' )
		);
	}

	public function test_prepare_messages_bounds_oversized_multibyte_content(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'user',
					'content' => str_repeat( 'ä', 50000 ),
				),
			),
			8,
			10
		);

		$this->assertSame( str_repeat( 'ä', 10 ), $messages[0]['content'] );
	}

	// --- REST handler --------------------------------------------------------

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_exhausted_cap_returns_503_without_calling_provider(): void {
		$this->configure_chat_backend();
		$this->exhaust_hourly_budget();

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Hei' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_busy', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'Mistral', $result->get_error_message() );
		$this->assertStringNotContainsString( '120', $result->get_error_message() );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );

		$stats = rytkoset_theme_chat_get_usage_stats();
		$this->assertSame( 1, $stats['global_limit_hits']['count'] );
		$this->assertSame( 0, $stats['messages_sent']['count'] );
		$this->assertSame( 0, $stats['last_error']['count'] );
		$this->assertSame( 120, $stats['upstream_budget']['hour_count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_deterministic_reply_works_with_exhausted_cap_and_does_not_consume_it(): void {
		$this->configure_chat_backend();
		$this->exhaust_hourly_budget();
		$before = get_option( rytkoset_theme_chat_get_upstream_budget_option_name() );

		$parent            = rytkoset_test_register_post( 90, 'page', 'Sukuseura' );
		$parent->post_name = 'sukuseura';

		$page               = rytkoset_test_register_post( 91, 'page', 'Säännöt', 90 );
		$page->post_name    = 'saannot';
		$page->post_content = '<h2>10. Tilikausi ja tilintarkastus</h2><p>Tilikausi alkaa lähteen mukaan.</p>';

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on tilikausi?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( $before, get_option( rytkoset_theme_chat_get_upstream_budget_option_name() ) );
		$this->assertSame( 0, rytkoset_theme_chat_get_usage_stats()['global_limit_hits']['count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_named_source_fallback_does_not_consume_the_cap(): void {
		$this->configure_chat_backend();

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Kuka on Testi Tuntematon?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 0, rytkoset_theme_chat_get_usage_stats()['upstream_budget']['day_count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_ordinary_answer_consumes_one_call_and_disables_redirects(): void {
		$this->configure_chat_backend();
		$this->queue_text_response( 'Hei! Miten voin auttaa?' );

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Hei' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$args = $GLOBALS['rytkoset_test_http_requests'][0]['args'];
		$this->assertSame( 0, $args['redirection'] );
		$this->assertGreaterThan( 19.0, $args['timeout'] );
		$this->assertLessThanOrEqual( 20.0, $args['timeout'] );

		$stats = rytkoset_theme_chat_get_usage_stats();
		$this->assertSame( 1, $stats['upstream_budget']['hour_count'] );
		$this->assertSame( 1, $stats['upstream_budget']['day_count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_failed_upstream_attempt_still_consumes_the_cap(): void {
		$this->configure_chat_backend();

		// No queued response: the stub returns a network error.
		$result = rytkoset_theme_chat_handle_request( $this->request( 'Hei' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_upstream_error', $result->get_error_code() );
		$this->assertSame( 1, rytkoset_theme_chat_get_usage_stats()['upstream_budget']['hour_count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_forced_retry_counts_as_a_tool_round(): void {
		$this->configure_chat_backend();
		$this->queue_text_response( 'Mallin varmentamaton vastaus.' );
		$this->queue_tool_call_response( 'call-1' );
		$this->queue_text_response( 'Vastaus sivun lukemisen jälkeen.' );
		// Would be reachable only if the retry did not use up a round.
		$this->queue_text_response( 'Ylimääräinen kierros.' );

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on hänen ammattinsa?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertStringContainsString( 'Vastaus sivun lukemisen jälkeen.', $result->get_data()['reply'] );
		$this->assertCount( 3, $GLOBALS['rytkoset_test_http_requests'] );

		$first = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$retry = json_decode( $GLOBALS['rytkoset_test_http_requests'][1]['args']['body'], true );
		$final = json_decode( $GLOBALS['rytkoset_test_http_requests'][2]['args']['body'], true );
		$this->assertSame( 'any', $first['tool_choice'] );
		$this->assertArrayNotHasKey( 'tool_choice', $retry );
		// One round was left after the retry, so the model must now answer.
		$this->assertSame( 'none', $final['tool_choice'] );

		$this->assertSame( 3, rytkoset_theme_chat_get_usage_stats()['upstream_budget']['hour_count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unforced_tool_flow_keeps_both_rounds(): void {
		$this->configure_chat_backend();
		$this->queue_tool_call_response( 'call-1' );
		$this->queue_tool_call_response( 'call-2' );
		$this->queue_text_response( 'Vastaus kahden sivunluvun jälkeen.' );

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Kerro sukuseuran toiminnasta' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertStringContainsString( 'Vastaus kahden sivunluvun jälkeen.', $result->get_data()['reply'] );
		$this->assertCount( 3, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 3, rytkoset_theme_chat_get_usage_stats()['upstream_budget']['day_count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_cap_reached_mid_request_ends_with_503(): void {
		$this->configure_chat_backend();
		add_filter( 'rytkoset_theme_chat_global_hourly_limit', array( $this, 'limit_one' ) );
		$this->queue_tool_call_response( 'call-1' );
		$this->queue_text_response( 'Ei saa näkyä.' );

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Kerro sukuseuran toiminnasta' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_busy', $result->get_error_code() );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 1, rytkoset_theme_chat_get_usage_stats()['global_limit_hits']['count'] );
		$this->assertSame( 0, rytkoset_theme_chat_get_usage_stats()['messages_sent']['count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_time_budget_stops_the_upstream_loop(): void {
		$this->configure_chat_backend();
		// Every upstream call "takes" 25 seconds of the 40 second budget.
		add_filter( 'rytkoset_theme_chat_current_time', array( $this, 'slow_clock' ) );
		$this->queue_tool_call_response( 'call-1' );
		$this->queue_tool_call_response( 'call-2' );
		$this->queue_text_response( 'Ei saa näkyä.' );

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Kerro sukuseuran toiminnasta' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_upstream_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );

		// Call 1 at 0 s gets the full 20 s, call 2 at 25 s only the 15 s left,
		// and at 50 s no third call is started.
		$this->assertCount( 2, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 20.0, $GLOBALS['rytkoset_test_http_requests'][0]['args']['timeout'] );
		$this->assertSame( 15.0, $GLOBALS['rytkoset_test_http_requests'][1]['args']['timeout'] );

		$stats = rytkoset_theme_chat_get_usage_stats();
		$this->assertSame( 'time_budget', $stats['last_error']['last_type'] );
		$this->assertSame( 2, $stats['upstream_budget']['hour_count'] );
		$this->assertSame( 0, $stats['messages_sent']['count'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_oversized_message_list_is_rejected_before_validation(): void {
		$this->configure_chat_backend();

		$messages = array_fill(
			0,
			101,
			array(
				'role'    => 'user',
				'content' => 'Hei',
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request_with_messages( $messages ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_too_many_messages', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_message_list_at_the_limit_is_accepted(): void {
		$this->configure_chat_backend();
		$this->queue_text_response( 'Hei!' );

		$messages = array_fill(
			0,
			100,
			array(
				'role'    => 'user',
				'content' => 'Hei',
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request_with_messages( $messages ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		// System prompt + the eight most recent messages.
		$this->assertCount( 9, $payload['messages'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_array_content_is_treated_as_an_empty_message(): void {
		$this->configure_chat_backend();

		$result = rytkoset_theme_chat_handle_request(
			$this->request_with_messages(
				array(
					array(
						'role'    => 'user',
						'content' => array( 'Hei' ),
					),
				)
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_empty_message', $result->get_error_code() );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_foreign_origin_is_rejected_before_any_state_is_touched(): void {
		$this->configure_chat_backend();
		$this->queue_text_response( 'Ei saa näkyä.' );

		foreach ( array( 'https://evil.example', 'null' ) as $origin ) {
			$request = $this->request( 'Hei' );
			$request->set_header( 'Origin', $origin );

			$result = rytkoset_theme_chat_handle_request( $request );

			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'rytkoset_chat_forbidden_origin', $result->get_error_code() );
			$this->assertSame( 403, $result->get_error_data()['status'] );
		}

		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( array(), $GLOBALS['rytkoset_test_transients'] );
		$this->assertSame( array(), $GLOBALS['rytkoset_test_options'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_own_origin_is_accepted(): void {
		$this->configure_chat_backend();
		$this->queue_text_response( 'Hei!' );

		$request = $this->request( 'Hei' );
		$request->set_header( 'Origin', 'https://rytkoset.test' );

		$result = rytkoset_theme_chat_handle_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
	}

	// --- helpers -------------------------------------------------------------

	public function limit_one(): int {
		return 1;
	}

	public function limit_two(): int {
		return 2;
	}

	/**
	 * @param array<int,string> $hosts Allowed origins.
	 * @return array<int,string>
	 */
	public function allow_staging_origin( array $hosts ): array {
		$hosts[] = 'staging.example:8443';

		return $hosts;
	}

	public function slow_clock(): float {
		return 1000.0 + 25.0 * count( $GLOBALS['rytkoset_test_http_requests'] );
	}

	private function configure_chat_backend(): void {
		if ( ! defined( 'RYTKOSET_CHAT_API_KEY' ) ) {
			define( 'RYTKOSET_CHAT_API_KEY', 'test-key' );
		}

		if ( ! defined( 'RYTKOSET_CHAT_API_ENDPOINT' ) ) {
			define( 'RYTKOSET_CHAT_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions' );
		}
	}

	private function exhaust_hourly_budget(): void {
		update_option(
			rytkoset_theme_chat_get_upstream_budget_option_name(),
			array(
				'hour_start' => time(),
				'hour_count' => rytkoset_theme_chat_get_global_hourly_limit(),
				'day_start'  => time(),
				'day_count'  => rytkoset_theme_chat_get_global_hourly_limit(),
			)
		);
	}

	private function request( string $message ): WP_REST_Request {
		return $this->request_with_messages(
			array(
				array(
					'role'    => 'user',
					'content' => $message,
				),
			)
		);
	}

	/** @param array<int,array<string,mixed>> $messages Chat history. */
	private function request_with_messages( array $messages ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'wp_rest' );
		$request->set_param( 'messages', $messages );

		return $request;
	}

	private function queue_text_response( string $text ): void {
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array( 'content' => $text ),
					),
				),
			)
		);
	}

	private function queue_tool_call_response( string $call_id ): void {
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'tool_calls',
						'message'       => array(
							'content'    => '',
							'tool_calls' => array(
								array(
									'id'       => $call_id,
									'type'     => 'function',
									'function' => array(
										'name'      => 'lue_sivu',
										'arguments' => '{"sivu_id":91}',
									),
								),
							),
						),
					),
				),
			)
		);
	}

	/** @param array<string,mixed> $body Decoded Mistral response body. */
	private function queue_mistral_response( array $body ): void {
		$GLOBALS['rytkoset_test_http_responses'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}
}
