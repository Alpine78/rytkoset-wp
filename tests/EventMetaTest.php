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

	// --- registration choice fields -----------------------------------------

	public function test_choice_options_trim_dedupe_and_preserve_first_spelling(): void {
		$this->assertSame(
			array( 'Kuopio', 'Varkaus', 'Muu paikka reitin varrella' ),
			rytkoset_theme_normalize_choice_options( " Kuopio \nVarkaus\nkuopio\n\nMuu paikka reitin varrella" )
		);
	}

	public function test_event_choice_helpers_read_meta_and_resolve_canonical_value(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Bussikyyti' );
		update_post_meta( 10, rytkoset_theme_get_event_choice_enabled_meta_key(), 'yes' );
		update_post_meta( 10, rytkoset_theme_get_event_choice_options_meta_key(), "Kuopio\nVarkaus" );
		update_post_meta( 10, rytkoset_theme_get_event_choice_field_label_meta_key(), 'Lähtöpaikka' );

		$this->assertTrue( rytkoset_theme_event_has_choice_field( 10 ) );
		$this->assertSame( array( 'Kuopio', 'Varkaus' ), rytkoset_theme_get_event_choice_options( 10 ) );
		$this->assertSame( 'Lähtöpaikka', rytkoset_theme_get_event_choice_field_label( 10 ) );
		$this->assertSame( 'Kuopio', rytkoset_theme_resolve_event_choice_value( 10, 'kuopio' ) );
		$this->assertSame( '', rytkoset_theme_resolve_event_choice_value( 10, 'Tampere' ) );
	}

	public function test_choice_and_quantity_labels_have_defaults(): void {
		$this->assertSame( 'Lähtöpaikka', rytkoset_theme_get_event_choice_field_label( 999 ) );
		$this->assertSame( 'Matkustajien määrä', rytkoset_theme_get_event_quantity_field_label( 999 ) );
	}
}
