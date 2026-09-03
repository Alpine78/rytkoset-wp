<?php
/**
 * Tests for inc/event-registration-privacy.php — anonymized name and consent timestamp formatting.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventPrivacyTest extends Rytkoset_Theme_Test_Case {

	public function test_anonymized_name_is_stable(): void {
		$this->assertSame( 'Anonymisoitu osallistuja', rytkoset_theme_get_anonymized_event_registration_name() );
	}

	public function test_consent_timestamp_formatted_in_site_timezone(): void {
		$timestamp = ( new DateTimeImmutable( '2026-06-24 12:00:00', new DateTimeZone( 'Europe/Helsinki' ) ) )->getTimestamp();

		$this->assertSame(
			'24.6.2026 12:00',
			rytkoset_theme_format_event_registration_consent_timestamp( $timestamp )
		);
	}

	public function test_consent_timestamp_empty_for_zero(): void {
		$this->assertSame( '', rytkoset_theme_format_event_registration_consent_timestamp( 0 ) );
	}

	public function test_consent_timestamp_empty_for_non_numeric(): void {
		$this->assertSame( '', rytkoset_theme_format_event_registration_consent_timestamp( 'eilen' ) );
	}

	public function test_anonymize_clears_personal_data_source_but_keeps_coded_metadata(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Sukujuhla' );
		rytkoset_test_register_post( 120, 'event_registration', 'Maija Meikäläinen - Sukujuhla' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		update_post_meta( 120, $meta_keys['event_id'], 10 );
		update_post_meta( 120, $meta_keys['name'], 'Maija Meikäläinen' );
		update_post_meta( 120, $meta_keys['email'], 'maija@example.test' );
		update_post_meta( 120, $meta_keys['status'], 'confirmed' );
		update_post_meta( 120, $meta_keys['source'], 'manual' );
		update_post_meta( 120, $meta_keys['personal_data_source'], 'huoltaja ilmoitti puhelimitse' );
		update_post_meta( 120, $meta_keys['informed_status'], 'informed' );

		$this->assertTrue( rytkoset_theme_anonymize_event_registration( 120 ) );

		$this->assertSame( '', get_post_meta( 120, $meta_keys['personal_data_source'], true ) );
		$this->assertSame( '', get_post_meta( 120, $meta_keys['email'], true ) );
		$this->assertSame( 'Anonymisoitu osallistuja', get_post_meta( 120, $meta_keys['name'], true ) );
		// Coded operational metadata is not personal data and is kept.
		$this->assertSame( 'manual', get_post_meta( 120, $meta_keys['source'], true ) );
		$this->assertSame( 'informed', get_post_meta( 120, $meta_keys['informed_status'], true ) );
		$this->assertSame( 'confirmed', get_post_meta( 120, $meta_keys['status'], true ) );
	}
}
