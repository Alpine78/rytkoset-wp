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

	public function test_sitemap_lists_published_events_before_pages_with_ids(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );
		rytkoset_test_register_post( 30, 'rytkoset_event', 'Sukukokous Tampereella' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString(
			'- Sukukokous Tampereella (tapahtuma): https://rytkoset.test/?p=30 (sivu-id: 30)',
			$sitemap
		);

		// Merkkiraja katkaisee lohkon lopun, joten tapahtumat eivät saa jäädä
		// sivulistan taakse.
		$this->assertLessThan( strpos( $sitemap, '- Jäsenyys:' ), strpos( $sitemap, '(tapahtuma)' ) );
	}

	public function test_sitemap_omits_draft_and_password_protected_events(): void {
		$draft              = rytkoset_test_register_post( 30, 'rytkoset_event', 'Luonnostapahtuma' );
		$draft->post_status = 'draft';

		$protected                = rytkoset_test_register_post( 31, 'rytkoset_event', 'Suojattu tapahtuma' );
		$protected->post_password = 'salasana';

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringNotContainsString( 'Luonnostapahtuma', $sitemap );
		$this->assertStringNotContainsString( 'Suojattu tapahtuma', $sitemap );
	}

	public function test_sitemap_lists_published_albums_after_pages_with_ids(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );
		rytkoset_test_register_post( 40, 'gallery_album', '60-vuotissukujuhla Iisalmessa' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString(
			'- 60-vuotissukujuhla Iisalmessa (albumi): https://rytkoset.test/?p=40 (sivu-id: 40)',
			$sitemap
		);

		// Sivut ovat ydintietoa, joten merkkirajan katkaisu osuu albumeihin.
		$this->assertGreaterThan( strpos( $sitemap, '- Jäsenyys:' ), strpos( $sitemap, '(albumi)' ) );
	}

	public function test_sitemap_omits_draft_and_password_protected_albums(): void {
		$draft              = rytkoset_test_register_post( 40, 'gallery_album', 'Luonnosalbumi' );
		$draft->post_status = 'draft';

		$protected                = rytkoset_test_register_post( 41, 'gallery_album', 'Suojattu albumi' );
		$protected->post_password = 'salasana';

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringNotContainsString( 'Luonnosalbumi', $sitemap );
		$this->assertStringNotContainsString( 'Suojattu albumi', $sitemap );
	}

	public function test_sitemap_includes_public_page_hints_for_tool_selection(): void {
		$page               = rytkoset_test_register_post( 21, 'page', 'Sukututkimus' );
		$page->post_content = '<h2>Nimen alkuperä ja suvun levinneisyys</h2><p>Rytkönen-nimen on esitetty palautuvan vanhaan germaaniseen nimeen Hrodgaer, jonka muotoja ovat Rutger, Rötger ja Rodhger.</p><h2>Sukututkimuksen julkaisuja</h2><p>Laajimmat tiedot kokosi diplomi-insinööri Arvo Korpela. Työ pohjautui rovasti Taavi Kilven selvitykseen. Julkaisun yhteydessä mainitaan monia paikkoja ja tutkimuksia ennen pastori Teuvo Rönkön selvitystä. Sukukirjan toimitti Antero Rytkönen työryhmineen. Pitkäaikaisella puheenjohtajalla Marja-Liisa Patrikaisella oli merkittävä rooli.</p>';

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString( '- Sukututkimus: https://rytkoset.test/?p=21 (sivu-id: 21)', $sitemap );
		$this->assertStringContainsString( 'aiheita:', $sitemap );
		$this->assertStringContainsString( 'Nimen alkuperä ja suvun levinneisyys', $sitemap );
		$this->assertStringContainsString( 'Rodhger', $sitemap );
		$this->assertStringContainsString( 'Arvo Korpela', $sitemap );
		$this->assertStringContainsString( 'Taavi Kilven', $sitemap );
		$this->assertStringContainsString( 'Teuvo Rönkön', $sitemap );
		$this->assertStringContainsString( 'Antero Rytkönen', $sitemap );
		$this->assertStringContainsString( 'Marja-Liisa Patrikaisella', $sitemap );
	}

	public function test_sitemap_hint_join_skips_terms_that_would_be_cut_midword(): void {
		$hints = rytkoset_theme_chat_join_sitemap_hint_terms(
			array( str_repeat( 'a', 275 ), 'Antero Rytkönen', 'Rodhger' ),
			280
		);

		$this->assertSame( str_repeat( 'a', 275 ), $hints );
		$this->assertStringNotContainsString( 'Antero', $hints );
	}

	public function test_sitemap_hints_include_late_rule_heading_within_budget(): void {
		$page               = rytkoset_test_register_post( 21, 'page', 'Säännöt' );
		$page->post_content = '<h2>1. Nimi ja kotipaikka</h2><h2>2. Tarkoitus ja toiminnan laatu</h2><h2>3. Tarkoituksensa toteuttamiseksi yhdistys</h2><h2>4. Jäsenet</h2><h2>5. Jäsenen eroaminen ja erottaminen</h2><h2>6. Jäsenmaksu</h2><h2>7. Sukuseuran sukuhallitus ja kokoukset</h2><h2>8. Sukuhallitus</h2><h2>9. Yhdistyksen nimen kirjoittaminen</h2><h2>10. Tilikausi ja tilintarkastus</h2><p>Tilikausi alkaa 1. heinäkuuta ja päättyy 30. kesäkuuta.</p>';

		$hints = rytkoset_theme_chat_get_sitemap_page_hints( $page );

		$this->assertLessThanOrEqual( 360, mb_strlen( $hints ) );
		$this->assertStringContainsString( '10. Tilikausi ja tilintarkastus', $hints );
	}

	public function test_sitemap_omits_hints_when_page_tool_disabled(): void {
		$page               = rytkoset_test_register_post( 21, 'page', 'Sukututkimus' );
		$page->post_content = '<p>Rodhger ja Arvo Korpela mainitaan sivulla.</p>';

		$filter = static fn() => false;
		add_filter( 'rytkoset_theme_chat_page_tool_enabled', $filter );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		remove_filter( 'rytkoset_theme_chat_page_tool_enabled', $filter );

		$this->assertStringContainsString( '- Sukututkimus: https://rytkoset.test/?p=21', $sitemap );
		$this->assertStringNotContainsString( 'sivu-id', $sitemap );
		$this->assertStringNotContainsString( 'aiheita:', $sitemap );
		$this->assertStringNotContainsString( 'Rodhger', $sitemap );
		$this->assertStringNotContainsString( 'Arvo Korpela', $sitemap );
	}

	public function test_sitemap_omits_hints_for_members_only_pages(): void {
		$page               = rytkoset_test_register_post( 21, 'page', 'Jäsenille' );
		$page->post_content = '<p>Jäsenille rajattu koodi on SUKU2026.</p>';
		update_post_meta( 21, rytkoset_theme_get_members_only_page_meta_key(), 'yes' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString( '- Jäsenille: https://rytkoset.test/?p=21 (sivu-id: 21)', $sitemap );
		$this->assertStringNotContainsString( 'aiheita:', $sitemap );
		$this->assertStringNotContainsString( 'SUKU2026', $sitemap );
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
