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

	public function test_recipient_names_follow_address_owner_across_orders_and_paths(): void {
		$rows = array(
			array( 'email' => 'buyer@example.test', 'name' => 'Muu osallistuja', 'contact_email' => 'BUYER@example.test', 'contact_name' => 'Ostaja', 'source' => 'paid' ),
			array( 'email' => 'shared@example.test', 'name' => 'Aino', 'contact_email' => 'buyer@example.test', 'contact_name' => 'Ostaja', 'source' => 'paid' ),
			array( 'email' => 'SHARED@example.test', 'name' => 'Bertta', 'contact_email' => 'other@example.test', 'contact_name' => 'Toinen ostaja', 'source' => 'paid' ),
			array( 'email' => 'shared@example.test', 'name' => 'Aino', 'contact_email' => 'third@example.test', 'contact_name' => 'Kolmas ostaja', 'source' => 'paid' ),
			array( 'email' => 'free@example.test', 'name' => 'Maksuton', 'contact_email' => 'free@example.test', 'contact_name' => 'Maksuton', 'source' => 'free', 'additional_emails' => array( 'buyer@example.test' ) ),
		);
		$result = rytkoset_theme_build_event_messaging_recipients( $rows );
		$this->assertCount( 3, $result['recipients'] );
		$this->assertSame( 'Ostaja', $result['recipients']['buyer@example.test']['name'] );
		$this->assertSame( '', $result['recipients']['shared@example.test']['name'] );
		$this->assertSame( 'Hei osallistuja!', rytkoset_theme_personalize_event_message( 'Hei {nimi}!', $result['recipients']['shared@example.test']['name'], '' ) );
	}
}
