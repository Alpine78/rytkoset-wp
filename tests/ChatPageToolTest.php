<?php
/**
 * Tests for the chat page-read tool in inc/chat.php (#501).
 *
 * Covers the pure helpers of the lue_sivu function-calling tool: tool-call
 * extraction from the Mistral response, argument parsing, page content
 * extraction/sanitization, the leak-guarded page resolver, sitemap page-id
 * markers, system prompt wiring, the tool definition and the usage-stats
 * counter. The wp_remote_post tool loop itself is network wiring and is
 * validated manually (curl / dev), per the project's testing guidance.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatPageToolTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Builds a decoded Mistral response body with the given message fields.
	 *
	 * @param array<string,mixed> $message Message fields.
	 * @return array<string,mixed>
	 */
	private function response_body( array $message ): array {
		return array( 'choices' => array( array( 'message' => $message ) ) );
	}

	// --- rytkoset_theme_chat_extract_tool_calls() -----------------------------

	public function test_extract_tool_calls_returns_parsed_calls(): void {
		$body = $this->response_body(
			array(
				'role'       => 'assistant',
				'content'    => '',
				'tool_calls' => array(
					array(
						'id'       => 'call_abc',
						'function' => array(
							'name'      => 'lue_sivu',
							'arguments' => '{"sivu_id":20}',
						),
					),
				),
			)
		);

		$calls = rytkoset_theme_chat_extract_tool_calls( $body );

		$this->assertCount( 1, $calls );
		$this->assertSame( 'call_abc', $calls[0]['id'] );
		$this->assertSame( 'lue_sivu', $calls[0]['name'] );
		$this->assertSame( '{"sivu_id":20}', $calls[0]['arguments'] );
	}

	public function test_extract_tool_calls_returns_empty_for_plain_text_reply(): void {
		$body = $this->response_body(
			array(
				'role'    => 'assistant',
				'content' => 'Tavallinen vastaus.',
			)
		);

		$this->assertSame( array(), rytkoset_theme_chat_extract_tool_calls( $body ) );
	}

	public function test_extract_tool_calls_returns_empty_for_malformed_body(): void {
		$this->assertSame( array(), rytkoset_theme_chat_extract_tool_calls( null ) );
		$this->assertSame( array(), rytkoset_theme_chat_extract_tool_calls( 'not-an-array' ) );
		$this->assertSame( array(), rytkoset_theme_chat_extract_tool_calls( array() ) );
	}

	public function test_extract_tool_calls_skips_calls_without_id_or_name(): void {
		$body = $this->response_body(
			array(
				'role'       => 'assistant',
				'tool_calls' => array(
					array( 'function' => array( 'name' => 'lue_sivu' ) ),
					array( 'id' => 'call_no_name' ),
					array(
						'id'       => 'call_ok',
						'function' => array(
							'name'      => 'lue_sivu',
							'arguments' => '{"sivu_id":5}',
						),
					),
				),
			)
		);

		$calls = rytkoset_theme_chat_extract_tool_calls( $body );

		$this->assertCount( 1, $calls );
		$this->assertSame( 'call_ok', $calls[0]['id'] );
	}

	// --- rytkoset_theme_chat_parse_page_tool_args() ---------------------------

	public function test_parse_page_tool_args_accepts_json_string(): void {
		$this->assertSame( 20, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":20}' ) );
		$this->assertSame( 20, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":"20"}' ) );
	}

	public function test_parse_page_tool_args_accepts_array(): void {
		$this->assertSame( 20, rytkoset_theme_chat_parse_page_tool_args( array( 'sivu_id' => 20 ) ) );
	}

	public function test_parse_page_tool_args_rejects_invalid_input(): void {
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( 'ei-jsonia{' ) );
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( '{"muu_avain":1}' ) );
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":"abc"}' ) );
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( null ) );
	}

	public function test_parse_page_tool_args_rejects_non_positive_id(): void {
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":0}' ) );
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":-20}' ) );
	}

	// --- rytkoset_theme_chat_extract_page_text() ------------------------------

	public function test_extract_page_text_strips_block_comments_tags_and_shortcodes(): void {
		$html = "<!-- wp:paragraph -->\n<p>Jäsenmaksu on <strong>20 euroa</strong>.</p>\n<!-- /wp:paragraph -->\n[yhteystiedot id=\"3\"]<script>alert('x');</script>";

		$text = rytkoset_theme_chat_extract_page_text( $html );

		$this->assertStringContainsString( 'Jäsenmaksu on 20 euroa.', $text );
		$this->assertStringNotContainsString( 'wp:paragraph', $text );
		$this->assertStringNotContainsString( '[yhteystiedot', $text );
		$this->assertStringNotContainsString( 'alert', $text );
		$this->assertStringNotContainsString( '<', $text );
	}

	public function test_extract_page_text_preserves_paragraph_breaks(): void {
		$text = rytkoset_theme_chat_extract_page_text( '<p>Eka kappale.</p><p>Toka kappale.</p>' );

		$this->assertSame( "Eka kappale.\nToka kappale.", $text );
	}

	public function test_extract_page_text_returns_empty_for_markup_only_content(): void {
		$this->assertSame( '', rytkoset_theme_chat_extract_page_text( '<!-- wp:spacer --><div></div><!-- /wp:spacer -->' ) );
		$this->assertSame( '', rytkoset_theme_chat_extract_page_text( '' ) );
	}

	// --- rytkoset_theme_chat_resolve_page_tool_result() -----------------------

	public function test_resolve_returns_content_with_title_and_url_for_public_page(): void {
		$page               = rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );
		$page->post_content = '<p>Jäsenmaksu on 20 euroa vuodessa.</p>';

		$result = rytkoset_theme_chat_resolve_page_tool_result( 20 );

		$this->assertStringContainsString( 'Sivu: Jäsenyys (https://rytkoset.test/?p=20)', $result );
		$this->assertStringContainsString( 'Jäsenmaksu on 20 euroa vuodessa.', $result );
	}

	public function test_resolve_rejects_unknown_and_non_positive_ids(): void {
		$error = rytkoset_theme_chat_get_page_tool_error_text();

		$this->assertSame( $error, rytkoset_theme_chat_resolve_page_tool_result( 999 ) );
		$this->assertSame( $error, rytkoset_theme_chat_resolve_page_tool_result( 0 ) );
		$this->assertSame( $error, rytkoset_theme_chat_resolve_page_tool_result( -5 ) );
	}

	public function test_resolve_rejects_draft_page(): void {
		$page               = rytkoset_test_register_post( 20, 'page', 'Luonnos' );
		$page->post_status  = 'draft';
		$page->post_content = '<p>Salainen luonnos.</p>';

		$result = rytkoset_theme_chat_resolve_page_tool_result( 20 );

		$this->assertSame( rytkoset_theme_chat_get_page_tool_error_text(), $result );
		$this->assertStringNotContainsString( 'Salainen luonnos', $result );
	}

	public function test_resolve_rejects_non_page_post_types(): void {
		$post               = rytkoset_test_register_post( 20, 'post', 'Blogikirjoitus' );
		$post->post_content = '<p>Blogisisältö.</p>';

		$this->assertSame( rytkoset_theme_chat_get_page_tool_error_text(), rytkoset_theme_chat_resolve_page_tool_result( 20 ) );
	}

	public function test_resolve_rejects_password_protected_page(): void {
		$page                = rytkoset_test_register_post( 20, 'page', 'Suojattu' );
		$page->post_content  = '<p>Suojattu sisältö.</p>';
		$page->post_password = 'salasana';

		$this->assertSame( rytkoset_theme_chat_get_page_tool_error_text(), rytkoset_theme_chat_resolve_page_tool_result( 20 ) );
	}

	public function test_resolve_rejects_members_only_page(): void {
		$page               = rytkoset_test_register_post( 20, 'page', 'Jäsenille' );
		$page->post_content = '<p>Jäsenalennuskoodi on SUKU2026.</p>';
		update_post_meta( 20, rytkoset_theme_get_members_only_page_meta_key(), 'yes' );

		$result = rytkoset_theme_chat_resolve_page_tool_result( 20 );

		$this->assertSame( rytkoset_theme_chat_get_page_tool_error_text(), $result );
		$this->assertStringNotContainsString( 'SUKU2026', $result );
	}

	public function test_resolve_rejects_members_only_page_even_for_privileged_viewer(): void {
		// Chat-vastaukset menevät kolmannen osapuolen API:in — jäsensivut
		// suodatetaan katsojasta riippumatta, myös muokkausoikeudellisilta.
		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;

		$page               = rytkoset_test_register_post( 20, 'page', 'Jäsenille' );
		$page->post_content = '<p>Jäsenalennuskoodi on SUKU2026.</p>';
		update_post_meta( 20, rytkoset_theme_get_members_only_page_meta_key(), 'yes' );

		$this->assertSame( rytkoset_theme_chat_get_page_tool_error_text(), rytkoset_theme_chat_resolve_page_tool_result( 20 ) );
	}

	public function test_resolve_reports_empty_content_without_generic_error(): void {
		rytkoset_test_register_post( 20, 'page', 'Tyhjä sivu' );

		$this->assertSame( 'Sivulla ei ole luettavaa tekstisisältöä.', rytkoset_theme_chat_resolve_page_tool_result( 20 ) );
	}

	public function test_resolve_truncates_content_to_max_length_filter(): void {
		$page               = rytkoset_test_register_post( 20, 'page', 'Pitkä sivu' );
		$page->post_content = '<p>' . str_repeat( 'x', 500 ) . '</p>';

		$filter = static fn() => 50;
		add_filter( 'rytkoset_theme_chat_page_tool_max_length', $filter );

		$result = rytkoset_theme_chat_resolve_page_tool_result( 20 );

		remove_filter( 'rytkoset_theme_chat_page_tool_max_length', $filter );

		$this->assertSame( 50, mb_strlen( $result ) );
	}

	// --- rytkoset_theme_chat_run_page_tool() ----------------------------------

	public function test_run_page_tool_rejects_unknown_tool_name(): void {
		$this->assertSame( 'Tuntematon työkalu.', rytkoset_theme_chat_run_page_tool( 'poista_sivu', '{"sivu_id":20}' ) );
	}

	public function test_run_page_tool_reads_public_page(): void {
		$page               = rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );
		$page->post_content = '<p>Jäsenmaksu on 20 euroa.</p>';

		$result = rytkoset_theme_chat_run_page_tool( 'lue_sivu', '{"sivu_id":20}' );

		$this->assertStringContainsString( 'Jäsenmaksu on 20 euroa.', $result );
	}

	public function test_run_page_tool_handles_invalid_arguments_gracefully(): void {
		$this->assertSame(
			rytkoset_theme_chat_get_page_tool_error_text(),
			rytkoset_theme_chat_run_page_tool( 'lue_sivu', 'rikkinäinen{json' )
		);
	}

	// --- rytkoset_theme_chat_get_page_tool_definition() -----------------------

	public function test_tool_definition_declares_lue_sivu_with_required_id(): void {
		$definition = rytkoset_theme_chat_get_page_tool_definition();

		$this->assertSame( 'function', $definition['type'] );
		$this->assertSame( 'lue_sivu', $definition['function']['name'] );
		$this->assertSame( array( 'sivu_id' ), $definition['function']['parameters']['required'] );
		$this->assertSame( 'integer', $definition['function']['parameters']['properties']['sivu_id']['type'] );
	}

	// --- sitemap page-id markers ----------------------------------------------

	public function test_sitemap_includes_page_ids_when_tool_enabled(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		$this->assertStringContainsString( '- Jäsenyys: https://rytkoset.test/?p=20 (sivu-id: 20)', $sitemap );
	}

	public function test_sitemap_omits_page_ids_when_tool_disabled(): void {
		rytkoset_test_register_post( 20, 'page', 'Jäsenyys' );

		$filter = static fn() => false;
		add_filter( 'rytkoset_theme_chat_page_tool_enabled', $filter );

		$sitemap = rytkoset_theme_chat_get_sitemap_context();

		remove_filter( 'rytkoset_theme_chat_page_tool_enabled', $filter );

		$this->assertStringContainsString( '- Jäsenyys: https://rytkoset.test/?p=20', $sitemap );
		$this->assertStringNotContainsString( 'sivu-id', $sitemap );
	}

	// --- system prompt wiring ---------------------------------------------------

	public function test_system_prompt_includes_tool_instructions_when_enabled(): void {
		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'lue_sivu-työkalu', $prompt );
		$this->assertStringContainsString( 'Älä kuitenkaan käytä työkalua, kun vastaus on jo annetuissa lähteissä.', $prompt );
		// Devissä havaitut ongelmat: työkalu jäi käyttämättä ja malli väitti,
		// ettei sivulla näkyvää nimeä mainita sivustolla — ohjeen pidettävä
		// työkalun kokeilu sallittuna ja kiellettävä tarkistamaton kielto.
		$this->assertStringContainsString( 'työkalun kokeileminen ei ole kiellettyä arvaamista', $prompt );
		$this->assertStringContainsString( 'sallittu lähde', $prompt );
		$this->assertStringContainsString( 'aiheita-kohdat ovat vain hakuvihjeitä oikean sivun valintaan', $prompt );
		$this->assertStringContainsString( 'älä vastaa faktakysymykseen niiden perusteella vaan lue sivu työkalulla', $prompt );
		// Devin toisessa savutestissä yksi väärä sivuarvaus (esim. henkilön nimi
		// joka ei ole minkään otsikon ilmeinen aihe) johti virheelliseen
		// "ei mainita sivustolla" -väitteeseen, koska mallilla ei ollut
		// mahdollisuutta kokeilla toista sivua (max_rounds oli 1). Ohjeen pitää
		// nyt nimenomaan kehottaa kokeilemaan toista sivua ennen kieltäytymistä.
		$this->assertStringContainsString( 'kokeile vielä toista aiheeseen sopivaa sivustokartan sivua', $prompt );
		$this->assertStringContainsString( 'Älä koskaan väitä, ettei jotakin asiaa, nimeä tai tietoa mainita koko sivustolla, ellet ole tarkistanut useampaa aiheeseen sopivaa sivua', $prompt );
		$this->assertStringContainsString( 'käytä aiemman kysymyksen nimeä saman sivun valintaan', $prompt );
		$this->assertStringContainsString( 'viittaus on epäselvä, pyydä täsmennys äläkä arvaa', $prompt );
		$this->assertStringContainsString( 'käytä lähteessä henkilölle nimenomaisesti annettua nimikettä', $prompt );
	}

	public function test_system_prompt_unchanged_when_tool_disabled(): void {
		$filter = static fn() => false;
		add_filter( 'rytkoset_theme_chat_page_tool_enabled', $filter );

		$prompt = rytkoset_theme_chat_get_system_prompt();

		remove_filter( 'rytkoset_theme_chat_page_tool_enabled', $filter );

		$this->assertStringNotContainsString( 'lue_sivu', $prompt );
		$this->assertStringNotContainsString( 'sivu-id', $prompt );
	}

	// --- cost-guard getters -----------------------------------------------------

	public function test_max_rounds_defaults_to_two_and_is_capped_at_three(): void {
		$this->assertSame( 2, rytkoset_theme_chat_get_page_tool_max_rounds() );

		$filter = static fn() => 10;
		add_filter( 'rytkoset_theme_chat_page_tool_max_rounds', $filter );

		$rounds = rytkoset_theme_chat_get_page_tool_max_rounds();

		remove_filter( 'rytkoset_theme_chat_page_tool_max_rounds', $filter );

		$this->assertSame( 3, $rounds );
	}

	public function test_max_length_defaults_to_5000(): void {
		$this->assertSame( 5000, rytkoset_theme_chat_get_page_tool_max_length() );
	}

	// --- tool-call usage stats (#472) -------------------------------------------

	public function test_record_tool_call_stat_bumps_counter(): void {
		$names = rytkoset_theme_chat_get_stat_option_names();

		rytkoset_theme_chat_record_tool_call_stat();
		rytkoset_theme_chat_record_tool_call_stat();

		$stored = get_option( $names['tool_calls'], array() );

		$this->assertSame( 2, $stored['count'] );
		$this->assertGreaterThan( 0, $stored['last_at'] );
	}

	public function test_usage_stats_include_tool_call_counter(): void {
		rytkoset_theme_chat_record_tool_call_stat();

		$stats = rytkoset_theme_chat_get_usage_stats();

		$this->assertSame( 1, $stats['tool_calls']['count'] );
		$this->assertGreaterThan( 0, $stats['tool_calls']['last_at'] );
	}
}
