<?php
/**
 * Tests for inc/chat.php — the Mistral chat backend-proxy helpers (#412).
 *
 * Covers only the deterministic pure/near-pure helpers (rate-limit decision,
 * message preparation/truncation, reply extraction, system prompt, config).
 * The REST wiring and the live wp_remote_post() call are validated manually
 * with curl against a configured environment.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ChatProxyTest extends Rytkoset_Theme_Test_Case {

	// --- rytkoset_theme_chat_rate_limit_exceeded() ---------------------------

	public function test_rate_limit_not_exceeded_below_limit(): void {
		$this->assertFalse( rytkoset_theme_chat_rate_limit_exceeded( 0, 20 ) );
		$this->assertFalse( rytkoset_theme_chat_rate_limit_exceeded( 19, 20 ) );
	}

	public function test_rate_limit_exceeded_at_and_above_limit(): void {
		$this->assertTrue( rytkoset_theme_chat_rate_limit_exceeded( 20, 20 ) );
		$this->assertTrue( rytkoset_theme_chat_rate_limit_exceeded( 21, 20 ) );
	}

	// --- rytkoset_theme_chat_truncate() --------------------------------------

	public function test_truncate_cuts_to_length(): void {
		$this->assertSame( 'abcde', rytkoset_theme_chat_truncate( 'abcdefghij', 5 ) );
	}

	public function test_truncate_leaves_short_text_untouched(): void {
		$this->assertSame( 'hei', rytkoset_theme_chat_truncate( 'hei', 100 ) );
	}

	public function test_truncate_counts_multibyte_characters(): void {
		// Five multibyte characters must survive a length-5 cap.
		$this->assertSame( 'ääkkö', rytkoset_theme_chat_truncate( 'ääkkönen', 5 ) );
	}

	// --- rytkoset_theme_chat_prepare_messages() ------------------------------

	public function test_prepare_messages_filters_disallowed_roles(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'system',
					'content' => 'yritys huijata',
				),
				array(
					'role'    => 'user',
					'content' => 'Milloin on sukukokous?',
				),
			),
			8,
			1000
		);

		$this->assertCount( 1, $messages );
		$this->assertSame( 'user', $messages[0]['role'] );
		$this->assertSame( 'Milloin on sukukokous?', $messages[0]['content'] );
	}

	public function test_prepare_messages_drops_empty_and_non_array_entries(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				'not-an-array',
				array(
					'role'    => 'user',
					'content' => '   ',
				),
				array(
					'role'    => 'assistant',
					'content' => 'Hei!',
				),
			),
			8,
			1000
		);

		$this->assertCount( 1, $messages );
		$this->assertSame( 'assistant', $messages[0]['role'] );
	}

	public function test_prepare_messages_truncates_content_to_max_length(): void {
		$long     = str_repeat( 'a', 50 );
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'user',
					'content' => $long,
				),
			),
			8,
			10
		);

		$this->assertSame( 10, strlen( $messages[0]['content'] ) );
	}

	public function test_prepare_messages_keeps_only_last_history_entries(): void {
		$raw = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$raw[] = array(
				'role'    => 'user',
				'content' => 'viesti ' . $i,
			);
		}

		$messages = rytkoset_theme_chat_prepare_messages( $raw, 8, 1000 );

		$this->assertCount( 8, $messages );
		// Oldest kept entry is #5 (12 - 8 + 1), newest is #12.
		$this->assertSame( 'viesti 5', $messages[0]['content'] );
		$this->assertSame( 'viesti 12', $messages[7]['content'] );
	}

	public function test_prepare_messages_preserves_context_for_pronoun_follow_up(): void {
		$raw = array(
			array(
				'role'    => 'user',
				'content' => 'Kuka on Teuvo Rönkkö?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Teuvo Rönkkö mainitaan pastorina.',
			),
			array(
				'role'    => 'user',
				'content' => 'Mikä on hänen ammattinsa?',
			),
		);

		$this->assertSame( $raw, rytkoset_theme_chat_prepare_messages( $raw, 8, 1000 ) );
	}

	public function test_prepare_messages_returns_empty_for_no_valid_messages(): void {
		$this->assertSame( array(), rytkoset_theme_chat_prepare_messages( array(), 8, 1000 ) );
	}

	// --- rytkoset_theme_chat_extract_reply() ---------------------------------

	public function test_extract_reply_reads_first_choice_content(): void {
		$body = array(
			'choices' => array(
				array(
					'message' => array(
						'role'    => 'assistant',
						'content' => '  Tervetuloa!  ',
					),
				),
			),
		);

		$this->assertSame( 'Tervetuloa!', rytkoset_theme_chat_extract_reply( $body ) );
	}

	public function test_extract_reply_returns_empty_for_malformed_body(): void {
		$this->assertSame( '', rytkoset_theme_chat_extract_reply( null ) );
		$this->assertSame( '', rytkoset_theme_chat_extract_reply( array() ) );
		$this->assertSame( '', rytkoset_theme_chat_extract_reply( array( 'choices' => array() ) ) );
	}

	// --- rytkoset_theme_chat_get_system_prompt() -----------------------------

	public function test_system_prompt_is_finnish_only_and_points_to_contact_email(): void {
		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'vain suomeksi', $prompt );
		$this->assertStringContainsString( 'Älä keksi tietoa', $prompt );
		$this->assertStringContainsString( 'vain tässä system-promptissa annettuja lähteitä', $prompt );
		$this->assertStringContainsString( 'Älä täydennä puuttuvia kohtia yleisellä tiedolla', $prompt );
		$this->assertStringContainsString( 'Älä arvaa tulevia suunnitelmia', $prompt );
		$this->assertStringContainsString( rytkoset_theme_get_contact_email(), $prompt );
	}

	public function test_system_prompt_forbids_guessing_numbers_dates_and_prices(): void {
		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'Älä koskaan esitä vuosilukua, päivämäärää, hintaa tai lukumäärää', $prompt );
		$this->assertStringContainsString( 'älä myöskään arvaa tai päättele sellaista', $prompt );
	}

	public function test_stable_site_context_contains_known_paths_and_site_features(): void {
		$context = rytkoset_theme_chat_get_stable_site_context();

		$this->assertStringContainsString( 'Verkkokauppa: /kauppa/', $context );
		$this->assertStringContainsString( 'Oma tili: /oma-tili/', $context );
		$this->assertStringContainsString( 'Tilaukset: /oma-tili/tilaukset/', $context );
		$this->assertStringContainsString( 'Foorumi: /foorumi/', $context );
		$this->assertStringContainsString( 'Blogi: /blogi/', $context );
		$this->assertStringContainsString( 'Digilehdet: /digilehdet/', $context );
		$this->assertStringContainsString( 'Foorumi on käytössä sivustolla', $context );
		$this->assertStringContainsString( 'Blogikirjoituksia voi ehdottaa tai lähettää', $context );
		$this->assertStringContainsString( 'Digilehdet ovat sivuston HTML-sisältöä, eivät PDF-latauksia', $context );
		$this->assertStringContainsString( 'mutta älä oleta niitä olevan jo julkaistu', $context );
		$this->assertStringContainsString( 'https://www.facebook.com/rytkoset', $context );
		$this->assertStringContainsString( 'https://www.youtube.com/@rytkoset', $context );
		$this->assertStringContainsString( 'https://www.instagram.com/rytkoset/', $context );
		$this->assertStringContainsString( 'https://x.com/rytkoset', $context );
		$this->assertStringContainsString( 'Älä väitä, ettei some-tilejä ole', $context );
		$this->assertStringContainsString( 'Sukukirjaa voi lainata eri kirjastoista', $context );
		$this->assertStringContainsString( 'Rytkösten sukulainen nro 9 on julkaistu ja myynnissä verkkokaupassa', $context );
		$this->assertStringContainsString( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-9/', $context );
		$this->assertStringContainsString( 'Se on painettu lehti eikä digilehti', $context );
		$this->assertStringContainsString( 'Hallituskausi on 2023-2026', $context );
		$this->assertStringContainsString( 'Esa Rytkönen (jäsen, Espoo / Maaninka)', $context );
		$this->assertStringContainsString( 'Mikko Rytkönen (suvun esimies, Runni)', $context );
		$this->assertStringContainsString( 'Antti Rytkönen (puheenjohtaja, Tampere)', $context );
		$this->assertStringContainsString( 'Eeli Rytkönen (Kinnulanlahti / Maaninka)', $context );
		$this->assertStringContainsString( 'Eeva-Liisa Ryhänen (jäsen, Helsinki)', $context );
		$this->assertStringContainsString( 'Ilkka Rytkönen (jäsen / rytkoset.net ylläpitäjä, Kuopio)', $context );
		$this->assertStringContainsString( 'Juha Rytkönen (jäsen, Maavesi / Joroinen)', $context );
		$this->assertStringContainsString( 'Mauri Rytkönen (jäsen, Helsinki)', $context );
		$this->assertStringContainsString( 'Tapani Rytkönen (sihteeri, Pieksämäki)', $context );
		$this->assertStringContainsString( 'Kimmo Tuulenkari (jäsen, Kajaani)', $context );
		$this->assertStringContainsString( 'äläkä lisää muita nimiä', $context );
		$this->assertStringNotContainsString( 'Jari Rytkönen', $context );
		$this->assertStringContainsString( 'Maksa / yritä uudelleen -painike', $context );
		$this->assertStringContainsString( 'upotettuja YouTube-videoita omassa Videot-osiossaan', $context );
		$this->assertStringContainsString( 'Älä väitä, ettei sivustolla ole videoita', $context );
	}

	public function test_system_prompt_includes_stable_site_context(): void {
		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'Pysyvä sivustokonteksti', $prompt );
		$this->assertStringContainsString( 'Foorumi on käytössä sivustolla', $prompt );
		$this->assertStringContainsString( 'Blogikirjoituksia voi ehdottaa tai lähettää', $prompt );
		$this->assertStringContainsString( 'Sukukirjaa voi lainata eri kirjastoista', $prompt );
		$this->assertStringContainsString( 'Rytkösten sukulainen nro 9 on julkaistu ja myynnissä verkkokaupassa', $prompt );
		$this->assertStringContainsString( 'Antti Rytkönen (puheenjohtaja, Tampere)', $prompt );
		$this->assertStringContainsString( 'Kun käyttäjä pyytää henkilölistaa tai hallituksen kokoonpanoa', $prompt );
		$this->assertStringContainsString( 'Kimmo Tuulenkari (jäsen, Kajaani)', $prompt );
		$this->assertStringNotContainsString( 'Pertti Rytkönen', $prompt );
	}

	public function test_system_prompt_instructs_plain_text_output_and_full_urls(): void {
		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'ei Markdownia', $prompt );
		$this->assertStringContainsString( 'Sivuston osoite on https://rytkoset.test', $prompt );
		$this->assertStringContainsString( 'https://rytkoset.test/kauppa/', $prompt );
		$this->assertStringContainsString( 'URL-osoitteen jälkeen aina välilyönti tai rivinvaihto', $prompt );
	}

	public function test_stable_site_context_is_filterable(): void {
		$filter = static fn() => 'TESTIKONTEKSTI';
		add_filter( 'rytkoset_theme_chat_stable_site_context', $filter );

		$this->assertSame( 'TESTIKONTEKSTI', rytkoset_theme_chat_get_stable_site_context() );
		$this->assertStringContainsString( 'TESTIKONTEKSTI', rytkoset_theme_chat_get_system_prompt() );

		remove_filter( 'rytkoset_theme_chat_stable_site_context', $filter );
	}

	public function test_system_prompt_is_filterable(): void {
		$filter = static fn() => 'MUKAUTETTU';
		add_filter( 'rytkoset_theme_chat_system_prompt', $filter );

		$this->assertSame( 'MUKAUTETTU', rytkoset_theme_chat_get_system_prompt() );

		remove_filter( 'rytkoset_theme_chat_system_prompt', $filter );
	}

	// --- rytkoset_theme_chat_get_config() ------------------------------------

	public function test_config_is_not_configured_without_constants(): void {
		$config = rytkoset_theme_chat_get_config();

		$this->assertFalse( $config['is_configured'] );
		$this->assertSame( 'mistral-small-latest', $config['model'] );
	}

	// --- rytkoset_theme_chat_get_max_input_length() --------------------------

	public function test_max_input_length_default(): void {
		$this->assertSame( 1000, rytkoset_theme_chat_get_max_input_length() );
	}

	public function test_max_input_length_is_filterable(): void {
		$filter = static fn() => 250;
		add_filter( 'rytkoset_theme_chat_max_input_length', $filter );

		$this->assertSame( 250, rytkoset_theme_chat_get_max_input_length() );

		remove_filter( 'rytkoset_theme_chat_max_input_length', $filter );
	}

	// --- rytkoset_theme_chat_get_rate_limit() / ..._get_max_history() ---------

	public function test_rate_limit_default_and_filterable(): void {
		$this->assertSame( 20, rytkoset_theme_chat_get_rate_limit() );

		$filter = static fn() => 200;
		add_filter( 'rytkoset_theme_chat_rate_limit', $filter );

		$this->assertSame( 200, rytkoset_theme_chat_get_rate_limit() );

		remove_filter( 'rytkoset_theme_chat_rate_limit', $filter );
	}

	public function test_max_history_default_and_filterable(): void {
		$this->assertSame( 8, rytkoset_theme_chat_get_max_history() );

		$filter = static fn() => 30;
		add_filter( 'rytkoset_theme_chat_max_history', $filter );

		$this->assertSame( 30, rytkoset_theme_chat_get_max_history() );

		remove_filter( 'rytkoset_theme_chat_max_history', $filter );
	}

	public function test_rate_limit_and_max_history_never_below_one(): void {
		$filter = static fn() => 0;
		add_filter( 'rytkoset_theme_chat_rate_limit', $filter );
		add_filter( 'rytkoset_theme_chat_max_history', $filter );

		$this->assertSame( 1, rytkoset_theme_chat_get_rate_limit() );
		$this->assertSame( 1, rytkoset_theme_chat_get_max_history() );

		remove_filter( 'rytkoset_theme_chat_rate_limit', $filter );
		remove_filter( 'rytkoset_theme_chat_max_history', $filter );
	}

	// --- rytkoset_theme_chat_get_max_tokens() / ..._get_temperature() ---------

	public function test_max_tokens_default_and_filterable(): void {
		$this->assertSame( 800, rytkoset_theme_chat_get_max_tokens() );

		$filter = static fn() => 300;
		add_filter( 'rytkoset_theme_chat_max_tokens', $filter );

		$this->assertSame( 300, rytkoset_theme_chat_get_max_tokens() );

		remove_filter( 'rytkoset_theme_chat_max_tokens', $filter );
	}

	public function test_max_tokens_never_below_one(): void {
		$filter = static fn() => -5;
		add_filter( 'rytkoset_theme_chat_max_tokens', $filter );

		$this->assertSame( 1, rytkoset_theme_chat_get_max_tokens() );

		remove_filter( 'rytkoset_theme_chat_max_tokens', $filter );
	}

	public function test_temperature_default_and_filterable(): void {
		$this->assertSame( 0.2, rytkoset_theme_chat_get_temperature() );

		$filter = static fn() => 0.7;
		add_filter( 'rytkoset_theme_chat_temperature', $filter );

		$this->assertSame( 0.7, rytkoset_theme_chat_get_temperature() );

		remove_filter( 'rytkoset_theme_chat_temperature', $filter );
	}

	public function test_temperature_is_clamped_between_zero_and_one(): void {
		$high = static fn() => 3.5;
		add_filter( 'rytkoset_theme_chat_temperature', $high );
		$this->assertSame( 1.0, rytkoset_theme_chat_get_temperature() );
		remove_filter( 'rytkoset_theme_chat_temperature', $high );

		$low = static fn() => -1;
		add_filter( 'rytkoset_theme_chat_temperature', $low );
		$this->assertSame( 0.0, rytkoset_theme_chat_get_temperature() );
		remove_filter( 'rytkoset_theme_chat_temperature', $low );
	}

	// --- rytkoset_theme_chat_widget_is_enabled() -----------------------------

	public function test_widget_disabled_when_backend_not_configured(): void {
		// No wp-config constants defined in tests → backend not configured.
		$this->assertFalse( rytkoset_theme_chat_widget_is_enabled() );
	}

	public function test_widget_enabled_filter_can_force_on(): void {
		$filter = static fn() => true;
		add_filter( 'rytkoset_theme_chat_widget_enabled', $filter );

		$this->assertTrue( rytkoset_theme_chat_widget_is_enabled() );

		remove_filter( 'rytkoset_theme_chat_widget_enabled', $filter );
	}

	// --- Customizer: FAQ, tervetuloviesti ja päälle/pois-kytkin (#414) -------

	public function test_faq_text_empty_by_default(): void {
		$this->assertSame( '', rytkoset_theme_chat_get_faq_text() );
	}

	public function test_faq_text_is_trimmed(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_chat_faq'] = "  Jäsenmaksu on 20 €.  \n";

		$this->assertSame( 'Jäsenmaksu on 20 €.', rytkoset_theme_chat_get_faq_text() );
	}

	public function test_system_prompt_includes_faq_when_set(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_chat_faq'] = 'Seuraava sukukokous on 12.7.2026.';

		$prompt = rytkoset_theme_chat_get_system_prompt();

		$this->assertStringContainsString( 'Tietopohja:', $prompt );
		$this->assertStringContainsString( 'Seuraava sukukokous on 12.7.2026.', $prompt );
	}

	public function test_system_prompt_omits_faq_section_when_empty(): void {
		$this->assertStringNotContainsString( 'Tietopohja:', rytkoset_theme_chat_get_system_prompt() );
	}

	public function test_welcome_message_falls_back_to_default(): void {
		$this->assertStringContainsString( 'Kysy minulta Rytkösten sukuseurasta', rytkoset_theme_chat_get_welcome_message() );
	}

	public function test_welcome_message_uses_custom_value(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_chat_welcome_message'] = 'Moi! Miten voin auttaa?';

		$this->assertSame( 'Moi! Miten voin auttaa?', rytkoset_theme_chat_get_welcome_message() );
	}

	public function test_welcome_message_whitespace_only_falls_back(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_chat_welcome_message'] = "   \n";

		$this->assertStringContainsString( 'Kysy minulta', rytkoset_theme_chat_get_welcome_message() );
	}

	public function test_admin_enabled_defaults_to_true(): void {
		$this->assertTrue( rytkoset_theme_chat_admin_enabled() );
	}

	public function test_admin_enabled_reflects_disabled_setting(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_chat_enabled'] = false;

		$this->assertFalse( rytkoset_theme_chat_admin_enabled() );
	}

	public function test_sanitize_checkbox_coerces_to_bool(): void {
		$this->assertTrue( rytkoset_theme_chat_sanitize_checkbox( '1' ) );
		$this->assertTrue( rytkoset_theme_chat_sanitize_checkbox( true ) );
		$this->assertFalse( rytkoset_theme_chat_sanitize_checkbox( '' ) );
		$this->assertFalse( rytkoset_theme_chat_sanitize_checkbox( '0' ) );
	}
}
