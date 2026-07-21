<?php
/**
 * Tests for inc/chat.php — the Mistral chat backend-proxy helpers (#412).
 *
 * Covers the deterministic pure/near-pure helpers (rate-limit decision,
 * message preparation/truncation, reply extraction, system prompt, config).
 * REST rejection wiring has focused tests in ChatRequestHandlerTest; live HTTP
 * integration is validated manually against a configured environment.
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

	public function test_prepare_messages_filters_malformed_assistant_history_only(): void {
		$messages = rytkoset_theme_chat_prepare_messages(
			array(
				array(
					'role'    => 'user',
					'content' => 'Mikä on tilikausi?',
				),
				array(
					'role'    => 'assistant',
					'content' => '<end_of_thinking>' . str_repeat( '9', 20 ),
				),
				array(
					'role'    => 'user',
					'content' => 'Entä toimintakausi?',
				),
				array(
					'role'    => 'assistant',
					'content' => 'Toimintakausi on varsinaisten kokousten välinen aika.',
				),
			),
			8,
			1000
		);

		$this->assertCount( 3, $messages );
		$this->assertSame( 'Mikä on tilikausi?', $messages[0]['content'] );
		$this->assertSame( 'Entä toimintakausi?', $messages[1]['content'] );
		$this->assertSame( 'assistant', $messages[2]['role'] );
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

	// --- final response validation -------------------------------------------

	public function test_reply_validator_accepts_normal_plain_text(): void {
		$this->assertTrue(
			rytkoset_theme_chat_reply_is_valid(
				"Sukuseuran tilikausi kerrotaan säännöissä.\n\nLähde: https://rytkoset.test/sukuseura/saannot/"
			)
		);
	}

	public function test_reply_validator_rejects_html_and_model_control_tokens(): void {
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '<p>Vastaus</p>' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '&lt;think&gt;ajatus&lt;/think&gt; Vastaus' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '<!DOCTYPE html><p>Vastaus</p>' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '<!-- keskeneräinen ajatus --> Vastaus' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '&lt;!-- ajatus --&gt; Vastaus' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '&lt;img src=&quot;x&quot;&gt; Vastaus' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '[INST] Vastaa uudelleen.' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '[TOOL_RESULTS] Työkalun sisältö.' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '[/TOOL_RESULTS] Vastaus.' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '[/AVAILABLE_TOOLS] Vastaus.' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '[/TOOL_CALLS] Vastaus.' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '[PREFIX] Keskeneräinen vastaus.' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '<|assistant|> Vastaus' ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( '&lt;|assistant|&gt; Vastaus' ) );
	}

	public function test_reply_validator_rejects_long_character_runs_and_motifs(): void {
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( 'Vastaus ' . str_repeat( '9', 12 ) ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( 'Vastaus ' . str_repeat( '()', 6 ) ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( str_repeat( 'a', 201 ) ) );
	}

	public function test_reply_validator_rejects_repeated_eight_word_shingle_three_times(): void {
		$phrase = 'Tilikausi alkaa heinäkuussa ja päättyy seuraavan vuoden kesäkuussa.';

		$this->assertTrue( rytkoset_theme_chat_reply_is_valid( $phrase . ' ' . $phrase ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( $phrase . ' ' . $phrase . ' ' . $phrase ) );
	}

	public function test_reply_validator_rejects_more_than_three_thousand_characters(): void {
		$unique_words = array();
		for ( $index = 0; $index < 600; ++$index ) {
			$unique_words[] = 'sana' . $index;
		}

		$at_limit = rytkoset_theme_chat_truncate( implode( ' ', $unique_words ), 3000 );

		$this->assertSame( 3000, mb_strlen( $at_limit ) );
		$this->assertTrue( rytkoset_theme_chat_reply_is_valid( $at_limit ) );
		$this->assertFalse( rytkoset_theme_chat_reply_is_valid( $at_limit . 'x' ) );
	}

	public function test_final_response_requires_stop_finish_reason(): void {
		$valid = array(
			'choices' => array(
				array(
					'finish_reason' => 'stop',
					'message'       => array( 'content' => 'Varmennettu vastaus.' ),
				),
			),
		);
		$cut_off = $valid;

		$cut_off['choices'][0]['finish_reason'] = 'length';

		$this->assertSame( '', rytkoset_theme_chat_get_final_response_error_type( $valid ) );
		$this->assertSame( 'invalid_finish_reason', rytkoset_theme_chat_get_final_response_error_type( $cut_off ) );
		$this->assertSame( 'invalid_finish_reason', rytkoset_theme_chat_get_final_response_error_type( array() ) );
	}

	public function test_final_response_rejects_invalid_plain_text_content(): void {
		$body = array(
			'choices' => array(
				array(
					'finish_reason' => 'stop',
					'message'       => array( 'content' => '<end_of_thinking>rikkinäinen' ),
				),
			),
		);

		$this->assertSame( 'invalid_reply', rytkoset_theme_chat_get_final_response_error_type( $body ) );
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
		$this->assertSame( '', $config['prompt_cache_key'] );
	}

	// --- rytkoset_theme_chat_maybe_add_prompt_cache_key() --------------------

	public function test_prompt_cache_key_is_not_added_without_setting(): void {
		$payload = array(
			'model'    => 'mistral-medium-latest',
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => 'Ohje',
				),
			),
		);

		$this->assertSame(
			$payload,
			rytkoset_theme_chat_maybe_add_prompt_cache_key(
				$payload,
				'https://api.mistral.ai/v1/chat/completions',
				''
			)
		);
	}

	public function test_prompt_cache_key_is_added_to_mistral_payload(): void {
		$payload = rytkoset_theme_chat_maybe_add_prompt_cache_key(
			array( 'model' => 'mistral-medium-latest' ),
			'https://api.mistral.ai/v1/chat/completions',
			'rytkoset-chat-dev-v1'
		);

		$this->assertSame( 'rytkoset-chat-dev-v1', $payload['prompt_cache_key'] );
	}

	public function test_prompt_cache_key_is_not_added_to_other_providers(): void {
		$payload = array( 'model' => 'mistral-medium-latest' );

		$this->assertSame(
			$payload,
			rytkoset_theme_chat_maybe_add_prompt_cache_key(
				$payload,
				'https://example.openai.azure.com/models/chat/completions',
				'rytkoset-chat-dev-v1'
			)
		);
	}

	public function test_prompt_cache_key_stays_in_payload_when_tool_messages_are_appended(): void {
		$payload = rytkoset_theme_chat_maybe_add_prompt_cache_key(
			array( 'messages' => array() ),
			'https://api.mistral.ai/v1/chat/completions',
			'rytkoset-chat-dev-v1'
		);

		$payload['messages'][] = array(
			'role'    => 'tool',
			'content' => 'Sivun sisältö',
		);

		$this->assertSame( 'rytkoset-chat-dev-v1', $payload['prompt_cache_key'] );
	}

	public function test_mistral_endpoint_detection_requires_exact_host(): void {
		$this->assertTrue( rytkoset_theme_chat_endpoint_is_mistral( 'https://api.mistral.ai/v1/chat/completions' ) );
		$this->assertFalse( rytkoset_theme_chat_endpoint_is_mistral( 'https://api.mistral.ai.example.com/v1/chat/completions' ) );
		$this->assertFalse( rytkoset_theme_chat_endpoint_is_mistral( 'not-a-url' ) );
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

	// --- rytkoset_theme_chat_get_frequency_penalty() -------------------------

	public function test_frequency_penalty_default_and_filterable(): void {
		$this->assertSame( 0.3, rytkoset_theme_chat_get_frequency_penalty() );

		$filter = static fn() => 1.0;
		add_filter( 'rytkoset_theme_chat_frequency_penalty', $filter );

		$this->assertSame( 1.0, rytkoset_theme_chat_get_frequency_penalty() );

		remove_filter( 'rytkoset_theme_chat_frequency_penalty', $filter );
	}

	public function test_frequency_penalty_is_clamped_between_zero_and_two(): void {
		$high = static fn() => 9.0;
		add_filter( 'rytkoset_theme_chat_frequency_penalty', $high );
		$this->assertSame( 2.0, rytkoset_theme_chat_get_frequency_penalty() );
		remove_filter( 'rytkoset_theme_chat_frequency_penalty', $high );

		// A negative penalty would encourage repetition, so it is clamped away.
		$low = static fn() => -1.5;
		add_filter( 'rytkoset_theme_chat_frequency_penalty', $low );
		$this->assertSame( 0.0, rytkoset_theme_chat_get_frequency_penalty() );
		remove_filter( 'rytkoset_theme_chat_frequency_penalty', $low );
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

	// --- rytkoset_theme_chat_build_response_body() (#600, AI Act 50(2)) -----

	public function test_response_body_contains_reply_and_ai_generated_flag(): void {
		$body = rytkoset_theme_chat_build_response_body( 'Tervetuloa!' );

		$this->assertSame( 'Tervetuloa!', $body['reply'] );
		$this->assertTrue( $body['ai_generated'] );
	}

	public function test_response_body_ai_generated_is_strict_boolean_true(): void {
		$body = rytkoset_theme_chat_build_response_body( '' );

		$this->assertArrayHasKey( 'ai_generated', $body );
		$this->assertSame( true, $body['ai_generated'] );
	}
}
