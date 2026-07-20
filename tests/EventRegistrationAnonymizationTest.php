<?php
/**
 * Tests for inc/event-registration-anonymization.php — the scheduled 12-month
 * anonymization of free event registrations (#580).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventRegistrationAnonymizationTest extends Rytkoset_Theme_Test_Case {

	private function reference( string $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'Europe/Helsinki' ) );
	}

	public function test_default_retention_window_is_twelve_months(): void {
		$this->assertSame( 12, rytkoset_theme_get_event_registration_anonymization_months() );
	}

	public function test_retention_window_is_filterable_and_clamped(): void {
		$six = static fn() => 6;
		add_filter( 'rytkoset_theme_event_registration_anonymization_months', $six );
		$this->assertSame( 6, rytkoset_theme_get_event_registration_anonymization_months() );
		remove_filter( 'rytkoset_theme_event_registration_anonymization_months', $six );

		$negative = static fn() => -3;
		add_filter( 'rytkoset_theme_event_registration_anonymization_months', $negative );
		$this->assertSame( 0, rytkoset_theme_get_event_registration_anonymization_months() );
		remove_filter( 'rytkoset_theme_event_registration_anonymization_months', $negative );
	}

	public function test_is_due_true_when_more_than_twelve_months_passed(): void {
		$this->assertTrue(
			rytkoset_theme_event_registration_anonymization_is_due( '2025-01-01', $this->reference( '2026-06-01 00:00:00' ), 12 )
		);
	}

	public function test_is_due_false_before_window_ends(): void {
		// Event on 2025-06-15; window ends end of that day + 12 months = 2026-06-16 00:00.
		$this->assertFalse(
			rytkoset_theme_event_registration_anonymization_is_due( '2025-06-15', $this->reference( '2026-06-15 23:59:59' ), 12 )
		);
	}

	public function test_is_due_true_exactly_at_cutoff(): void {
		$this->assertTrue(
			rytkoset_theme_event_registration_anonymization_is_due( '2025-06-15', $this->reference( '2026-06-16 00:00:00' ), 12 )
		);
	}

	public function test_is_due_false_for_invalid_date(): void {
		$this->assertFalse(
			rytkoset_theme_event_registration_anonymization_is_due( '', $this->reference( '2030-01-01 00:00:00' ), 12 )
		);
		$this->assertFalse(
			rytkoset_theme_event_registration_anonymization_is_due( '2025-13-40', $this->reference( '2030-01-01 00:00:00' ), 12 )
		);
	}

	public function test_is_due_respects_custom_window(): void {
		// Six-month window: event on 2025-01-01 is due by 2025-08-01, not by 2025-05-01.
		$this->assertTrue(
			rytkoset_theme_event_registration_anonymization_is_due( '2025-01-01', $this->reference( '2025-08-01 00:00:00' ), 6 )
		);
		$this->assertFalse(
			rytkoset_theme_event_registration_anonymization_is_due( '2025-01-01', $this->reference( '2025-05-01 00:00:00' ), 6 )
		);
	}

	public function test_normalize_state_returns_defaults_for_garbage(): void {
		$state = rytkoset_theme_normalize_event_registration_anonymization_state( 'not-an-array' );

		$this->assertSame( '', $state['last_run_at'] );
		$this->assertSame( 0, $state['last_anonymized'] );
		$this->assertSame( 0, $state['total_anonymized'] );
		$this->assertSame( array(), $state['missing_date_event_ids'] );
	}

	public function test_normalize_state_sanitizes_missing_date_ids(): void {
		$state = rytkoset_theme_normalize_event_registration_anonymization_state(
			array(
				'last_run_at'            => '2026-07-20 03:00:00',
				'last_anonymized'        => '4',
				'total_anonymized'       => -2,
				'missing_date_event_ids' => array( '10', 0, 10, 'x', 22 ),
			)
		);

		$this->assertSame( '2026-07-20 03:00:00', $state['last_run_at'] );
		$this->assertSame( 4, $state['last_anonymized'] );
		$this->assertSame( 0, $state['total_anonymized'] );
		$this->assertSame( array( 10, 22 ), $state['missing_date_event_ids'] );
	}

	public function test_record_run_accumulates_total(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		rytkoset_theme_record_event_registration_anonymization_run( 3, array() );
		$state = rytkoset_theme_record_event_registration_anonymization_run( 2, array( 5 ) );

		$this->assertSame( '2026-07-20 03:00:00', $state['last_run_at'] );
		$this->assertSame( 2, $state['last_anonymized'] );
		$this->assertSame( 5, $state['total_anonymized'] );
		$this->assertSame( array( 5 ), $state['missing_date_event_ids'] );
	}

	private function register_registration( int $id, int $event_id, string $name, string $email ): void {
		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		rytkoset_test_register_post( $id, 'event_registration', $name . ' - Tapahtuma' );
		update_post_meta( $id, $meta_keys['event_id'], $event_id );
		update_post_meta( $id, $meta_keys['name'], $name );
		update_post_meta( $id, $meta_keys['email'], $email );
		update_post_meta( $id, $meta_keys['status'], 'confirmed' );
	}

	public function test_run_anonymizes_registrations_of_old_event(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		rytkoset_test_register_post( 10, 'rytkoset_event', 'Vanha tapahtuma' );
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2025-01-15' );
		$this->register_registration( 101, 10, 'Maija Meikäläinen', 'maija@example.test' );

		$result = rytkoset_theme_run_event_registration_anonymization();

		$this->assertSame( 1, $result['anonymized'] );
		$this->assertSame( array(), $result['missing_date_event_ids'] );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		$this->assertSame( 'Anonymisoitu osallistuja', get_post_meta( 101, $meta_keys['name'], true ) );
		$this->assertSame( '', get_post_meta( 101, $meta_keys['email'], true ) );
		$this->assertNotSame( '', get_post_meta( 101, $meta_keys['anonymized_at'], true ) );
		// Event reference and status preserved.
		$this->assertSame( '10', (string) get_post_meta( 101, $meta_keys['event_id'], true ) );
		$this->assertSame( 'confirmed', get_post_meta( 101, $meta_keys['status'], true ) );
	}

	public function test_run_skips_recent_event(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		rytkoset_test_register_post( 10, 'rytkoset_event', 'Tuore tapahtuma' );
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2026-06-01' );
		$this->register_registration( 101, 10, 'Maija Meikäläinen', 'maija@example.test' );

		$result = rytkoset_theme_run_event_registration_anonymization();

		$this->assertSame( 0, $result['anonymized'] );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		$this->assertSame( 'Maija Meikäläinen', get_post_meta( 101, $meta_keys['name'], true ) );
		$this->assertSame( 'maija@example.test', get_post_meta( 101, $meta_keys['email'], true ) );
	}

	public function test_run_is_idempotent_for_already_anonymized_rows(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		rytkoset_test_register_post( 10, 'rytkoset_event', 'Vanha tapahtuma' );
		update_post_meta( 10, rytkoset_theme_get_event_date_meta_key(), '2025-01-15' );
		$this->register_registration( 101, 10, 'Maija Meikäläinen', 'maija@example.test' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		update_post_meta( 101, $meta_keys['name'], 'Anonymisoitu osallistuja' );
		update_post_meta( 101, $meta_keys['anonymized_at'], '2026-02-01 00:00:00' );
		delete_post_meta( 101, $meta_keys['email'] );

		$result = rytkoset_theme_run_event_registration_anonymization();

		$this->assertSame( 0, $result['anonymized'] );
		// The original anonymization timestamp is untouched.
		$this->assertSame( '2026-02-01 00:00:00', get_post_meta( 101, $meta_keys['anonymized_at'], true ) );
	}

	public function test_run_reports_events_without_valid_date(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		rytkoset_test_register_post( 10, 'rytkoset_event', 'Päivätön tapahtuma' );
		$this->register_registration( 101, 10, 'Maija Meikäläinen', 'maija@example.test' );

		$result = rytkoset_theme_run_event_registration_anonymization();

		$this->assertSame( 0, $result['anonymized'] );
		$this->assertSame( array( 10 ), $result['missing_date_event_ids'] );

		$state = rytkoset_theme_get_event_registration_anonymization_state();
		$this->assertSame( array( 10 ), $state['missing_date_event_ids'] );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		$this->assertSame( 'Maija Meikäläinen', get_post_meta( 101, $meta_keys['name'], true ) );
	}

	public function test_run_anonymizes_registration_whose_event_was_deleted(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		$this->register_registration( 101, 99, 'Maija Meikäläinen', 'maija@example.test' );

		$first_result = rytkoset_theme_run_event_registration_anonymization();
		$meta_keys    = rytkoset_theme_get_event_registration_meta_keys();
		$timestamp    = get_post_meta( 101, $meta_keys['anonymized_at'], true );

		$this->assertSame( 1, $first_result['anonymized'] );
		$this->assertSame( 'Anonymisoitu osallistuja', get_post_meta( 101, $meta_keys['name'], true ) );
		$this->assertSame( '', get_post_meta( 101, $meta_keys['email'], true ) );
		$this->assertNotSame( '', $timestamp );

		$second_result = rytkoset_theme_run_event_registration_anonymization();

		$this->assertSame( 0, $second_result['anonymized'] );
		$this->assertSame( $timestamp, get_post_meta( 101, $meta_keys['anonymized_at'], true ) );
	}

	public function test_run_anonymizes_registration_without_event_reference(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-20 03:00:00';

		$this->register_registration( 101, 0, 'Maija Meikäläinen', 'maija@example.test' );

		$result    = rytkoset_theme_run_event_registration_anonymization();
		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		$this->assertSame( 1, $result['anonymized'] );
		$this->assertSame( 'Anonymisoitu osallistuja', get_post_meta( 101, $meta_keys['name'], true ) );
		$this->assertSame( '', get_post_meta( 101, $meta_keys['email'], true ) );
		$this->assertNotSame( '', get_post_meta( 101, $meta_keys['anonymized_at'], true ) );
	}
}
