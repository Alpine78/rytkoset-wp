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
