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

	public function test_additional_participant_exports_and_erases_only_their_own_data(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Sukujuhla' );
		rytkoset_test_register_post( 120, 'event_registration', 'Maija' );
		$keys = rytkoset_theme_get_event_registration_meta_keys();
		foreach ( array( 'event_id' => 10, 'name' => 'Maija', 'email' => 'maija@example.test', 'diet' => 'Secret diet', 'additional_emails' => array( 'friend@example.test', 'other@example.test', 'notfriend@example.test' ) ) as $key => $value ) {
			update_post_meta( 120, $keys[ $key ], $value );
		}
		$this->assertSame( array( 120 ), rytkoset_theme_get_event_registration_ids_by_email( 'FRIEND@example.test' ) );
		$export = rytkoset_theme_export_event_registration_personal_data( 'FRIEND@example.test' );
		$this->assertCount( 1, $export['data'] );
		$values = array_column( $export['data'][0]['data'], 'value' );
		$this->assertContains( 'friend@example.test', $values );
		$this->assertNotContains( 'Maija', $values );
		$this->assertNotContains( 'Secret diet', $values );
		$this->assertNotContains( 'maija@example.test', $values );
		$this->assertNotContains( 'other@example.test', $values );
		$owner_export = rytkoset_theme_export_event_registration_personal_data( 'maija@example.test' );
		$this->assertStringContainsString( 'friend@example.test', json_encode( $owner_export ) );
		$this->assertTrue( rytkoset_theme_erase_event_registration_personal_data( 'FRIEND@example.test' )['items_removed'] );
		$this->assertSame( array( 'other@example.test', 'notfriend@example.test' ), rytkoset_theme_get_event_registration_additional_emails( 120 ) );
		$this->assertSame( 'Maija', rytkoset_theme_get_event_registration_meta( 120, 'name' ) );
		$this->assertEmpty( rytkoset_theme_get_event_registration_ids_by_email( 'friend@example.test' ) );
		$this->assertTrue( rytkoset_theme_erase_event_registration_personal_data( 'maija@example.test' )['items_removed'] );
		$this->assertSame( '', get_post_meta( 120, $keys['additional_emails'], true ) );
	}

}
