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
}
