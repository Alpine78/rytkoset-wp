<?php
/**
 * Tests for the chat page-read tool in inc/chat.php (#501).
 *
 * Covers the pure helpers of the lue_sivu function-calling tool: tool-call
 * extraction from the Mistral response, argument parsing, page content
 * extraction/sanitization, the leak-guarded page resolver, sitemap page-id
 * markers, system prompt wiring, the tool definition and the usage-stats
 * counter. Focused REST rejection paths are covered in ChatRequestHandlerTest;
 * a successful live tool loop is validated manually (curl / dev).
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

	/**
	 * Registers the hierarchical public rules page used by direct-source tests.
	 *
	 * @param string $content Rules page content.
	 * @return WP_Post Rules page.
	 */
	private function register_rules_page( string $content ): WP_Post {
		$parent            = rytkoset_test_register_post( 90, 'page', 'Sukuseura' );
		$parent->post_name = 'sukuseura';

		$page               = rytkoset_test_register_post( 91, 'page', 'Säännöt', 90 );
		$page->post_name    = 'saannot';
		$page->post_content = $content;

		return $page;
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
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":1.9}' ) );
		$this->assertSame( 0, rytkoset_theme_chat_parse_page_tool_args( '{"sivu_id":"1.9"}' ) );
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

	// --- deterministic fiscal-year source -----------------------------------

	public function test_extract_numbered_section_stops_at_next_heading(): void {
		$text = "9. Kokoukset\nEdellinen kohta.\n10. Tilikausi ja tilintarkastus\nEnsimmäinen lause.\nToinen lause.\n11. Sääntöjen muuttaminen\nEi saa vuotaa.";

		$this->assertSame(
			"Ensimmäinen lause.\nToinen lause.",
			rytkoset_theme_chat_extract_numbered_section( $text, 10 )
		);
		$this->assertSame( '', rytkoset_theme_chat_extract_numbered_section( $text, 12 ) );
	}

	public function test_fiscal_year_inflections_return_section_ten_and_source_url(): void {
		$this->register_rules_page(
			'<h2>9. Sukuseuran kokoukset</h2><p>Edellinen kohta.</p>'
			. '<h2>10. Tilikausi ja tilintarkastus</h2><p>Sukuseuran tilikausi on vuosi ja toimintakausi on kokousten välinen aika.</p><p>Tilikausi alkaa 1. heinäkuuta ja päättyy 30. kesäkuuta.</p>'
			. '<h2>11. Sääntöjen muuttaminen</h2><p>Tämä ei kuulu vastaukseen.</p>'
		);

		$queries = array(
			'Mikä on tilikausi?',
			'Miten tilikauden ajankohta määräytyy?',
			'Mitä tilikautta säännöissä tarkoitetaan?',
			'Mitä tilikaudesta sanotaan?',
			'Mitä tapahtuu tilikaudella?',
			'Miten tilikausia verrataan?',
			'Entä tilikaudet?',
			'Mikä on tilikautemme?',
			'Milloin tilikautenne alkaa?',
		);

		foreach ( $queries as $query ) {
			$result = rytkoset_theme_chat_get_fiscal_year_source_reply(
				array(
					array(
						'role'    => 'user',
						'content' => $query,
					),
				)
			);

			$this->assertTrue( $result['matched'], $query );
			$this->assertStringContainsString( 'Tilikausi alkaa 1. heinäkuuta ja päättyy 30. kesäkuuta.', $result['reply'], $query );
			$this->assertStringContainsString( 'Lähde: https://rytkoset.test/?p=91', $result['reply'], $query );
			$this->assertStringNotContainsString( 'Tämä ei kuulu vastaukseen.', $result['reply'], $query );
		}
	}

	public function test_fiscal_year_reply_follows_source_instead_of_hard_coded_dates(): void {
		$this->register_rules_page(
			'<h2>10. Tilikausi ja tilintarkastus</h2><p>Testisäännön tilikausi alkaa 2. elokuuta ja päättyy 1. elokuuta.</p><h2>11. Muutokset</h2>'
		);

		$result = rytkoset_theme_chat_get_fiscal_year_source_reply(
			array(
				array(
					'role'    => 'user',
					'content' => 'Mikä on tilikausi?',
				),
			)
		);

		$this->assertStringContainsString( '2. elokuuta', $result['reply'] );
		$this->assertStringNotContainsString( '1. heinäkuuta', $result['reply'] );
	}

	public function test_non_fiscal_query_does_not_enter_direct_source_path(): void {
		$queries = array(
			'Miten liityn jäseneksi?',
			'Mistä löydän tilikausikertomuksen?',
			'Miten tilikausiraportti toimitetaan?',
		);

		foreach ( $queries as $query ) {
			$result = rytkoset_theme_chat_get_fiscal_year_source_reply(
				array(
					array(
						'role'    => 'user',
						'content' => $query,
					),
				)
			);

			$this->assertFalse( $result['matched'], $query );
			$this->assertSame( '', $result['reply'], $query );
		}
	}

	public function test_missing_rules_page_or_section_fails_closed(): void {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Mikä on tilikausi?',
			),
		);

		$this->assertSame(
			array(
				'matched' => true,
				'reply'   => '',
			),
			rytkoset_theme_chat_get_fiscal_year_source_reply( $messages )
		);

		$this->register_rules_page( '<h2>9. Kokoukset</h2><p>Ei kohtaa 10.</p><h2>11. Muutokset</h2>' );

		$this->assertSame( '', rytkoset_theme_chat_get_fiscal_year_source_reply( $messages )['reply'] );
	}

	public function test_restricted_rules_pages_fail_closed(): void {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Mikä on tilikausi?',
			),
		);

		$page              = $this->register_rules_page( '<h2>10. Tilikausi</h2><p>Salainen päivämäärä.</p>' );
		$page->post_status = 'draft';
		$this->assertSame( '', rytkoset_theme_chat_get_fiscal_year_source_reply( $messages )['reply'] );

		$page->post_status   = 'publish';
		$page->post_password = 'salasana';
		$this->assertSame( '', rytkoset_theme_chat_get_fiscal_year_source_reply( $messages )['reply'] );

		$page->post_password = '';
		update_post_meta( $page->ID, rytkoset_theme_get_members_only_page_meta_key(), 'yes' );
		$this->assertSame( '', rytkoset_theme_chat_get_fiscal_year_source_reply( $messages )['reply'] );
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

	// --- forced initial tool choice ---------------------------------------------

	public function test_forced_initial_tool_response_rejects_plain_text_and_invalid_calls(): void {
		$plain_text_body = $this->response_body(
			array(
				'role'    => 'assistant',
				'content' => 'Vastaus lukematta sivua.',
			)
		);
		$malformed_call_body = $this->response_body(
			array(
				'role'       => 'assistant',
				'tool_calls' => array(
					array( 'function' => array( 'name' => 'lue_sivu' ) ),
				),
			)
		);

		$this->assertFalse(
			rytkoset_theme_chat_forced_tool_response_is_valid(
				true,
				0,
				rytkoset_theme_chat_extract_tool_calls( $plain_text_body )
			)
		);
		$this->assertFalse(
			rytkoset_theme_chat_forced_tool_response_is_valid(
				true,
				0,
				rytkoset_theme_chat_extract_tool_calls( $malformed_call_body )
			)
		);
		$this->assertFalse(
			rytkoset_theme_chat_forced_tool_response_is_valid(
				true,
				0,
				array(
					array(
						'id'        => 'call_bad_args',
						'name'      => 'lue_sivu',
						'arguments' => '{rikkinäinen',
					),
				)
			)
		);
		$this->assertFalse(
			rytkoset_theme_chat_forced_tool_response_is_valid(
				true,
				0,
				array(
					array(
						'id'        => 'call_wrong_tool',
						'name'      => 'muu_tyokalu',
						'arguments' => '{"sivu_id":20}',
					),
				)
			)
		);
	}

	public function test_forced_initial_tool_response_accepts_valid_page_read_only(): void {
		$calls = array(
			array(
				'id'        => 'call_ok',
				'name'      => 'lue_sivu',
				'arguments' => '{"sivu_id":20}',
			),
		);

		$this->assertTrue( rytkoset_theme_chat_forced_tool_response_is_valid( true, 0, $calls ) );
		$this->assertTrue( rytkoset_theme_chat_forced_tool_response_is_valid( false, 0, array() ) );
		$this->assertTrue( rytkoset_theme_chat_forced_tool_response_is_valid( true, 1, array() ) );
	}

	public function test_forced_initial_tool_response_ignores_valid_fourth_call(): void {
		$calls = array(
			array(
				'id'        => 'call_wrong_1',
				'name'      => 'muu_tyokalu',
				'arguments' => '{"sivu_id":20}',
			),
			array(
				'id'        => 'call_wrong_2',
				'name'      => 'muu_tyokalu',
				'arguments' => '{"sivu_id":20}',
			),
			array(
				'id'        => 'call_wrong_3',
				'name'      => 'muu_tyokalu',
				'arguments' => '{"sivu_id":20}',
			),
			array(
				'id'        => 'call_valid_but_not_executed',
				'name'      => 'lue_sivu',
				'arguments' => '{"sivu_id":20}',
			),
		);

		$this->assertFalse( rytkoset_theme_chat_forced_tool_response_is_valid( true, 0, $calls ) );
	}

	public function test_person_term_and_follow_up_queries_force_initial_page_tool(): void {
		$queries = array(
			'Kuka on Marja-Liisa Patrikainen?',
			'Mikä tai kuka on Rodhger?',
			'Kuka toimitti kirjan Rytkösiä sukupolvesta toiseen?',
			'Mikä on hänen ammattinsa?',
			'Mikä on sukuseuran toimintakausi?',
			'Entä tilikausi?',
			'Mitä säännöissä sanotaan tilintarkastuksesta?',
		);

		foreach ( $queries as $query ) {
			$this->assertTrue(
				rytkoset_theme_chat_should_force_page_tool(
					array(
						array(
							'role'    => 'user',
							'content' => $query,
						),
					)
				),
				$query
			);
		}
	}

	public function test_ordinary_support_question_keeps_automatic_tool_choice(): void {
		$this->assertFalse(
			rytkoset_theme_chat_should_force_page_tool(
				array(
					array(
						'role'    => 'user',
						'content' => 'Miten jäsenmaksun voi maksaa?',
					),
				)
			)
		);
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
		$this->assertStringContainsString( 'Hallituskausi, toimintakausi ja tilikausi ovat eri asioita', $prompt );
		$this->assertStringContainsString( 'älä korvaa käyttäjän kysymää käsitettä samankaltaisella käsitteellä', mb_strtolower( $prompt ) );
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
