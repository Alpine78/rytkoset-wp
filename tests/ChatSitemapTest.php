<?php
/**
 * Tests for the chat sitemap-context builder in inc/chat.php.
 *
 * Covers the dynamically built sitemap block (published pages + CPT archive
 * links) appended to the system prompt so the model refers only to real URLs
 * instead of inventing paths (a production case invented /kuvat/).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatSitemapTest extends Rytkoset_Theme_Test_Case {

	// --- rytkoset_theme_chat_get_sitemap_context() ---------------------------

	public function test_sitemap_lists_published_pages_with_titles_and_urls(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );
		rytkoset_test_register_post( 21, 'page', 'Sukututkimus' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString( '- Jäsenyys: https://rytkoset.test/?p=20', $sitemap );
		$this->assertStringContainsString( '- Sukututkimus: https://rytkoset.test/?p=21', $sitemap );
	}

	public function test_sitemap_includes_event_and_album_archives(): void {
		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString( '- Tapahtumat (arkisto): https://rytkoset.test/rytkoset_event/', $sitemap );
		$this->assertStringContainsString( '- Kuvat ja albumit (arkisto): https://rytkoset.test/gallery_album/', $sitemap );
	}

	public function test_sitemap_excludes_unpublished_pages(): void {
		$draft              = rytkoset_test_register_post( 20, 'page', 'Luonnossivu' );
		$draft->post_status = 'draft';
		rytkoset_test_register_post( 21, 'page', 'Julkaistu sivu' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringNotContainsString( 'Luonnossivu', $sitemap );
		$this->assertStringContainsString( 'Julkaistu sivu', $sitemap );
	}

	public function test_sitemap_excludes_pages_without_title(): void {
		rytkoset_test_register_post( 20, 'page', '' );
		rytkoset_test_register_post( 21, 'page', 'Otsikollinen' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringNotContainsString( '?p=20', $sitemap );
		$this->assertStringContainsString( '?p=21', $sitemap );
	}

	public function test_sitemap_respects_max_pages_filter(): void {
		rytkoset_test_register_post( 20, 'page', 'Eka sivu' );
		rytkoset_test_register_post( 21, 'page', 'Toka sivu' );

		$filter = static fn() => 1;
		add_filter( 'rytkoset_theme_chat_sitemap_max_pages', $filter );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		remove_filter( 'rytkoset_theme_chat_sitemap_max_pages', $filter );

		$this->assertStringContainsString( 'Eka sivu', $sitemap );
		$this->assertStringNotContainsString( 'Toka sivu', $sitemap );
	}

	public function test_sitemap_truncated_by_max_length_filter(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );

		$filter = static fn() => 10;
		add_filter( 'rytkoset_theme_chat_sitemap_max_length', $filter );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		remove_filter( 'rytkoset_theme_chat_sitemap_max_length', $filter );

		$this->assertSame( 10, mb_strlen( $sitemap ) );
	}

	public function test_sitemap_disabled_by_filter(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );

		$filter = static fn() => false;
		add_filter( 'rytkoset_theme_chat_sitemap_enabled', $filter );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		remove_filter( 'rytkoset_theme_chat_sitemap_enabled', $filter );

		$this->assertSame( '', $sitemap );
	}

	// --- system prompt wiring -------------------------------------------------

	public function test_system_prompt_includes_sitemap_section_and_url_guardrail(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );

		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'Sivustokartta', $prompt );
		$this->assertStringContainsString( '- Jäsenyys: https://rytkoset.test/?p=20', $prompt );
		$this->assertStringContainsString( 'Kuvat ja albumit (arkisto)', $prompt );
		$this->assertStringContainsString( 'Älä koskaan keksi, arvaa tai päättele osoitetta', $prompt );
	}

	public function test_stable_context_names_albums_path_and_denies_kuvat_path(): void {
		$context = rytkoset_theme_chat_get_stable_site_context();

		$this->assertStringContainsString( 'Kuvat ja albumit: /albumit/', $context );
		$this->assertStringContainsString( 'ei ole /kuvat/-', $context );
	}
}
