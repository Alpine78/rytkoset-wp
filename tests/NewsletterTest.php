<?php
/**
 * Tests for inc/newsletter.php — shortcode sanitization, form-id parsing and error redaction.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class NewsletterTest extends Rytkoset_Theme_Test_Case {

	// --- sanitize_newsletter_shortcode -------------------------------------

	public function test_valid_acymailing_shortcode_is_kept(): void {
		$shortcode = '[acymailing_form_shortcode id="3"]';
		$this->assertSame( $shortcode, rytkoset_theme_sanitize_newsletter_shortcode( $shortcode ) );
	}

	public function test_non_shortcode_value_is_rejected(): void {
		$this->assertSame( '', rytkoset_theme_sanitize_newsletter_shortcode( 'pelkkää tekstiä' ) );
	}

	public function test_non_string_value_is_rejected(): void {
		$this->assertSame( '', rytkoset_theme_sanitize_newsletter_shortcode( null ) );
	}

	// --- get_newsletter_form_id --------------------------------------------

	public function test_form_id_parsed_from_shortcode(): void {
		$this->assertSame( 3, rytkoset_theme_get_newsletter_form_id( '[acymailing_form_shortcode id="3"]' ) );
	}

	public function test_form_id_zero_without_id_attribute(): void {
		$this->assertSame( 0, rytkoset_theme_get_newsletter_form_id( '[acymailing_form_shortcode]' ) );
	}

	public function test_form_id_zero_for_non_shortcode(): void {
		$this->assertSame( 0, rytkoset_theme_get_newsletter_form_id( 'ei oikotietä' ) );
	}

	public function test_form_list_ids_are_read_from_acymailing_form_settings(): void {
		$GLOBALS['wpdb']->get_var_result = json_encode(
			array(
				'lists' => array(
					'automatic_subscribe' => array( '3', '4' ),
					'checked'             => array( '4', '0' ),
					'displayed'           => array( '5' ),
				),
			)
		);

		$this->assertSame( array( 3, 4, 5 ), rytkoset_theme_get_newsletter_form_list_ids( 2 ) );
	}

	public function test_normalize_newsletter_list_ids_deduplicates_and_filters_empty_values(): void {
		$this->assertSame( array( 3, 5 ), rytkoset_theme_normalize_newsletter_list_ids( array( '3', 0, '5', 3, '' ) ) );
	}

	// --- subscription status ------------------------------------------------

	public function test_email_subscription_status_rejects_invalid_email(): void {
		$this->assertFalse( rytkoset_theme_email_has_newsletter_subscription( 'ei-email', array( 3 ) ) );
	}

	public function test_email_subscription_status_uses_prepared_acymailing_query(): void {
		$GLOBALS['wpdb']->get_var_result = 1;

		$this->assertTrue( rytkoset_theme_email_has_newsletter_subscription( 'tilaaja@example.test', array( 3, 4 ) ) );
		$this->assertSame( array( 'tilaaja@example.test', 3, 4 ), $GLOBALS['wpdb']->last_prepare_args );
		$this->assertStringContainsString( 'wp_acym_user', $GLOBALS['wpdb']->last_query );
		$this->assertStringContainsString( 'wp_acym_user_has_list', $GLOBALS['wpdb']->last_query );
	}

	// --- subscriber ID resolution ------------------------------------------

	public function test_subscriber_id_resolved_from_object_or_array_record(): void {
		$object     = new stdClass();
		$object->id = '42';

		$this->assertSame( 42, rytkoset_theme_get_newsletter_subscriber_id_from_record( $object ) );
		$this->assertSame( 43, rytkoset_theme_get_newsletter_subscriber_id_from_record( array( 'id' => '43' ) ) );
		$this->assertSame( 0, rytkoset_theme_get_newsletter_subscriber_id_from_record( array( 'email' => 'tilaaja@example.test' ) ) );
	}

	public function test_subscriber_id_by_email_returns_zero_without_valid_email_or_acymailing(): void {
		$this->assertSame( 0, rytkoset_theme_get_newsletter_subscriber_id_by_email( 'ei-email' ) );
		$this->assertSame( 0, rytkoset_theme_get_newsletter_subscriber_id_by_email( 'tilaaja@example.test' ) );
	}

	// --- unsubscribe helper error paths ------------------------------------

	public function test_unsubscribe_rejects_invalid_email(): void {
		$result = rytkoset_theme_unsubscribe_email_from_newsletter( 'ei-email', 'unit_test', array( 3 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'invalid_email', $result->get_error_codes() );
	}

	public function test_unsubscribe_rejects_missing_newsletter_list(): void {
		$result = rytkoset_theme_unsubscribe_email_from_newsletter( 'tilaaja@example.test', 'unit_test', array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'missing_newsletter_list', $result->get_error_codes() );
	}

	public function test_unsubscribe_reports_missing_acymailing_api(): void {
		$result = rytkoset_theme_unsubscribe_email_from_newsletter( 'tilaaja@example.test', 'unit_test', array( 3 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'acymailing_missing', $result->get_error_codes() );
	}

	// --- get_newsletter_error_message --------------------------------------

	public function test_error_message_redacts_email_addresses(): void {
		$message = rytkoset_theme_get_newsletter_error_message(
			array( 'Tilaus epäonnistui osoitteelle jasen@rytkoset.fi' ),
			'Oletusviesti'
		);

		$this->assertStringContainsString( '[email redacted]', $message );
		$this->assertStringNotContainsString( 'jasen@rytkoset.fi', $message );
	}

	public function test_error_message_falls_back_when_empty(): void {
		$this->assertSame( 'Oletusviesti', rytkoset_theme_get_newsletter_error_message( array(), 'Oletusviesti' ) );
	}
}
