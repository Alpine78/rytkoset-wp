<?php
/**
 * Tests for inc/event-participants-messaging.php — hourly send limit and the rolling-window
 * send-attempt accounting.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventMessagingTest extends Rytkoset_Theme_Test_Case {

	private const OPTION = 'rytkoset_event_messaging_send_attempts';

	public function test_hourly_limit_is_eighteen(): void {
		$this->assertSame( 18, rytkoset_theme_get_event_messaging_hourly_limit() );
	}

	public function test_only_attempts_within_the_last_hour_are_counted(): void {
		$now = 100000;
		update_option(
			self::OPTION,
			array(
				$now - 100,          // recent → kept
				$now - HOUR_IN_SECONDS, // exactly at the cutoff → excluded (strictly greater required)
				$now - 7200,         // older than an hour → excluded
				$now + 50,           // in the future → excluded
			)
		);

		$recent = rytkoset_theme_get_event_messaging_send_attempts( $now );

		$this->assertSame( array( $now - 100 ), $recent );
	}

	public function test_non_array_option_returns_empty(): void {
		update_option( self::OPTION, 'corrupt' );

		$this->assertSame( array(), rytkoset_theme_get_event_messaging_send_attempts( 100000 ) );
	}

	public function test_empty_attempts_return_empty(): void {
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_send_attempts( 100000 ) );
	}
}
