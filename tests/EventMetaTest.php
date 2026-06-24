<?php
/**
 * Tests for inc/events.php — email-list, date/time validators and price/recipient formatting.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventMetaTest extends Rytkoset_Theme_Test_Case {

	// --- normalize_email_list -----------------------------------------------

	public function test_email_list_splits_dedupes_and_drops_invalid(): void {
		$result = rytkoset_theme_normalize_email_list( "a@b.fi, A@B.fi; c@d.fi\nroskaa" );

		$this->assertSame( array( 'a@b.fi', 'c@d.fi' ), $result );
	}

	public function test_email_list_empty_input_returns_empty(): void {
		$this->assertSame( array(), rytkoset_theme_normalize_email_list( '' ) );
	}

	// --- is_valid_event_date ------------------------------------------------

	public function test_valid_event_date(): void {
		$this->assertTrue( rytkoset_theme_is_valid_event_date( '2026-06-24' ) );
	}

	public function test_impossible_calendar_date_is_invalid(): void {
		$this->assertFalse( rytkoset_theme_is_valid_event_date( '2026-02-30' ) );
	}

	public function test_unpadded_or_malformed_date_is_invalid(): void {
		$this->assertFalse( rytkoset_theme_is_valid_event_date( '2026-6-4' ) );
		$this->assertFalse( rytkoset_theme_is_valid_event_date( 'eilen' ) );
		$this->assertFalse( rytkoset_theme_is_valid_event_date( 20260624 ) );
	}

	// --- is_valid_event_time ------------------------------------------------

	public function test_valid_event_times(): void {
		$this->assertTrue( rytkoset_theme_is_valid_event_time( '09:30' ) );
		$this->assertTrue( rytkoset_theme_is_valid_event_time( '23:59' ) );
		$this->assertTrue( rytkoset_theme_is_valid_event_time( '00:00' ) );
	}

	public function test_invalid_event_times(): void {
		$this->assertFalse( rytkoset_theme_is_valid_event_time( '24:00' ) );
		$this->assertFalse( rytkoset_theme_is_valid_event_time( '9:30' ) );
		$this->assertFalse( rytkoset_theme_is_valid_event_time( '12:60' ) );
	}

	// --- deadline normalization + cutoff ------------------------------------

	public function test_deadline_normalizes_valid_date(): void {
		$this->assertSame( '2026-06-24', rytkoset_theme_normalize_event_registration_deadline_date( '2026-06-24' ) );
	}

	public function test_deadline_empty_and_invalid_become_empty(): void {
		$this->assertSame( '', rytkoset_theme_normalize_event_registration_deadline_date( '' ) );
		$this->assertSame( '', rytkoset_theme_normalize_event_registration_deadline_date( 'ei-päivä' ) );
	}

	public function test_cutoff_is_day_after_deadline_at_midnight(): void {
		$cutoff = rytkoset_theme_get_registration_deadline_cutoff_from_date( '2026-06-24' );

		$this->assertInstanceOf( DateTimeImmutable::class, $cutoff );
		$this->assertSame( '2026-06-25 00:00:00', $cutoff->format( 'Y-m-d H:i:s' ) );
	}

	public function test_cutoff_null_for_empty_deadline(): void {
		$this->assertNull( rytkoset_theme_get_registration_deadline_cutoff_from_date( '' ) );
	}

	// --- format_event_price_text --------------------------------------------

	public function test_numeric_price_gets_euro_suffix(): void {
		$this->assertSame( '10 €', rytkoset_theme_format_event_price_text( '10' ) );
		$this->assertSame( '10,50 €', rytkoset_theme_format_event_price_text( '10,50' ) );
		$this->assertSame( '7.5 €', rytkoset_theme_format_event_price_text( '7.5' ) );
	}

	public function test_non_numeric_price_is_left_untouched(): void {
		$this->assertSame( 'Vapaa pääsy', rytkoset_theme_format_event_price_text( 'Vapaa pääsy' ) );
		$this->assertSame( '15 €', rytkoset_theme_format_event_price_text( '15 €' ) );
		$this->assertSame( '', rytkoset_theme_format_event_price_text( '   ' ) );
	}

	// --- sanitize_event_organizer_notification_recipients -------------------

	public function test_recipients_sanitized_to_newline_list(): void {
		$this->assertSame(
			"a@b.fi\nc@d.fi",
			rytkoset_theme_sanitize_event_organizer_notification_recipients( "a@b.fi, a@b.fi\nc@d.fi" )
		);
	}
}
