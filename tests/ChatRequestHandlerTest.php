<?php
/**
 * REST handler regressions for malformed Mistral responses (#604).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ChatRequestHandlerTest extends Rytkoset_Theme_Test_Case {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_fiscal_year_source_reply_bypasses_mistral(): void {
		$this->configure_chat_backend();
		$this->register_rules_page(
			'<h2>10. Tilikausi ja tilintarkastus</h2><p>Tilikausi alkaa lähteen mukaan.</p>'
			. '<h2>11. Sääntöjen muuttaminen</h2><p>Ei kuulu vastaukseen.</p>'
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on tilikausi?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'Tilikausi alkaa lähteen mukaan.', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( 'Ei kuulu vastaukseen.', $result->get_data()['reply'] );
		$this->assertTrue( $result->get_data()['ai_generated'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_fiscal_year_source_returns_safe_502_without_mistral(): void {
		$this->configure_chat_backend();

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on tilikausi?' ) );

		$this->assert_safe_upstream_error( $result, 'Mallivastaus' );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 'direct_source_missing', rytkoset_theme_chat_get_usage_stats()['last_error']['last_type'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_verified_chair_source_uses_one_plain_completion_without_forced_tool(): void {
		$this->configure_chat_backend();
		$this->register_board_page( '<p>Antti Rytkönen, puheenjohtaja</p><p>Mauri Rytkönen, jäsen</p>' );
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Puheenjohtaja on Antti Rytkönen.\n\nLähde: https://rytkoset.test/?p=81",
						),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Kuka on puheenjohtaja nyt?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'Antti Rytkönen', $result->get_data()['reply'] );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'tools', $payload );
		$this->assertSame( 'none', $payload['tool_choice'] );
		$this->assertStringContainsString( 'Palvelin on lukenut ja varmistanut', $payload['messages'][0]['content'] );
		$this->assertStringContainsString( 'Antti Rytkönen, puheenjohtaja', $payload['messages'][0]['content'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_verified_concept_sections_use_one_plain_completion(): void {
		$this->configure_chat_backend();
		$this->register_payment_terms_page(
			'<h2>Maksutavat</h2><p>Maksut välittää Paytrail. Käytettävissä olevat maksutavat näkyvät kassalla.</p>'
			. '<h2>Maksupalvelutarjoaja</h2><p>Maksupalveluntarjoajana toimii Paytrail Oyj.</p>'
			. '<h2>Toimitus</h2><p>Postitettavat tuotteet käsitellään 1–3 arkipäivässä.</p>'
		);
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Maksutavat näkyvät kassalla.\n\nLähde: https://rytkoset.test/?p=61",
						),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mitä maksutapoja on käytössä?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertIsArray( $payload );
		$this->assertSame( 'none', $payload['tool_choice'] );
		$this->assertStringContainsString( 'Maksut välittää Paytrail', $payload['messages'][0]['content'] );
		$this->assertStringContainsString( 'Maksupalveluntarjoajana toimii Paytrail Oyj', $payload['messages'][0]['content'] );
		$this->assertStringNotContainsString( 'Postitettavat tuotteet', $payload['messages'][0]['content'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_order_cancellation_uses_verified_account_guest_and_handling_sections(): void {
		$this->configure_chat_backend();
		$this->register_payment_terms_page(
			'<h2>Digitaaliset tuotteet</h2><p>Digitaalisen sisällön oikeus voi päättyä nimenomaisella suostumuksella.</p>'
			. '<h2>Tapahtumamaksut</h2><p>Määräaikaiseen tapahtumamaksuun ei sovelleta peruuttamisoikeutta.</p>'
			. '<h2>Peruuttaminen ja palautukset</h2>'
			. '<p>Käyttäjätilillä toiminto löytyy kohdasta Oma tili → Tilaukset. Ilman käyttäjätiliä tehdyn tilauksen henkilökohtainen Peruuta tilaus -linkki on tilausvahvistuksessa.</p>'
			. '<p>Maksamaton tilaus peruuntuu heti, jos kaikki tuotteet kuuluvat peruuttamisoikeuden piiriin; muut pyynnöt käsitellään manuaalisesti.</p>'
		);
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Käyttäjätilin tilaus perutaan Oma tili -kohdasta ja vierastilaus vahvistusviestin linkistä. Muut pyynnöt käsitellään manuaalisesti.\n\nLähde: https://rytkoset.test/?p=61",
						),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Voinko perua tilaukseni?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertSame( 'none', $payload['tool_choice'] );
		$this->assertStringContainsString( 'Oma tili → Tilaukset', $payload['messages'][0]['content'] );
		$this->assertStringContainsString( 'henkilökohtainen Peruuta tilaus -linkki on tilausvahvistuksessa', $payload['messages'][0]['content'] );
		$this->assertStringContainsString( 'muut pyynnöt käsitellään manuaalisesti', $payload['messages'][0]['content'] );
		$this->assertStringContainsString( 'Älä väitä, että painike tai linkki näkyy aina', $payload['messages'][0]['content'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_concept_page_returns_deterministic_reply_without_mistral(): void {
		$this->configure_chat_backend();
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array( 'content' => 'Mallin muistista keksimä vastaus.' ),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mitkä ovat toimitusehdot?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'En pystynyt varmistamaan vastausta', $result->get_data()['reply'] );
		$this->assertStringContainsString( 'julkaistua julkista lähdesivua', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( 'Mallin muistista', $result->get_data()['reply'] );
		$this->assertTrue( $result->get_data()['ai_generated'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_named_query_without_public_source_returns_safe_reply_without_mistral(): void {
		$this->configure_chat_backend();

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Kuka on Ruotsin presidentti?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'chatin käytettävissä olevista', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( 'presidentti on', $result->get_data()['reply'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_future_runni_family_party_does_not_use_the_1963_foundation_meeting(): void {
		$this->configure_chat_backend();
		$page               = rytkoset_test_register_post( 70, 'page', 'Sukuseura' );
		$page->post_content = '<p>Rytkösten sukuseuran perustava kokous pidettiin 18.8.1963 Runnin Terveyskylpylällä.</p>';

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Milloin pidetään Runnin sukujuhla?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'En löytänyt tähän varmennettua vastausta', $result->get_data()['reply'] );
		$this->assertStringContainsString( '?s=Runnin', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( '18.8.1963', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( 'perustava kokous', $result->get_data()['reply'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_runni_history_question_uses_the_founding_meeting_as_source(): void {
		// The founding meeting genuinely was a sukukokous, so a history-phrased
		// question ("has there been") may now use it as a verified source,
		// unlike the future-phrased question in the previous test.
		$this->configure_chat_backend();
		$page               = rytkoset_test_register_post( 70, 'page', 'Sukuseura' );
		$page->post_content = '<p>Rytkösten sukuseuran perustava kokous pidettiin 18.8.1963 Runnin Terveyskylpylällä.</p>';
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Kyllä, Rytkösten sukuseuran perustava kokous pidettiin 18.8.1963 Runnin Terveyskylpylällä.\n\nLähde: https://rytkoset.test/?p=70",
						),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Onko Runnilla ollut sukukokousta?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( '18.8.1963', $result->get_data()['reply'] );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertIsArray( $payload );
		$this->assertSame( 'none', $payload['tool_choice'] );
		$this->assertStringContainsString( 'perustava kokous', $payload['messages'][0]['content'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_lowercase_runni_history_question_uses_the_founding_meeting_as_source(): void {
		// Same as the previous test, but written entirely in lowercase — many
		// visitors do not capitalize proper names, and the answer must not
		// depend on it.
		$this->configure_chat_backend();
		$page               = rytkoset_test_register_post( 70, 'page', 'Sukuseura' );
		$page->post_content = '<p>Rytkösten sukuseuran perustava kokous pidettiin 18.8.1963 Runnin Terveyskylpylällä.</p>';
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Kyllä, Rytkösten sukuseuran perustava kokous pidettiin 18.8.1963 Runnin Terveyskylpylällä.\n\nLähde: https://rytkoset.test/?p=70",
						),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'onko runnilla ollut sukukokousta?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( '18.8.1963', $result->get_data()['reply'] );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertIsArray( $payload );
		$this->assertSame( 'none', $payload['tool_choice'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_lowercase_future_runni_party_gets_the_same_safe_reply_as_capitalized(): void {
		// Consistency regression: a lowercase "next occurrence" question must
		// fail exactly like its capitalized counterpart
		// (test_future_runni_family_party_does_not_use_the_1963_foundation_meeting)
		// rather than silently falling through to an unverified Mistral answer
		// just because the place name was not capitalized.
		$this->configure_chat_backend();
		$page               = rytkoset_test_register_post( 70, 'page', 'Sukuseura' );
		$page->post_content = '<p>Rytkösten sukuseuran perustava kokous pidettiin 18.8.1963 Runnin Terveyskylpylällä.</p>';

		$result = rytkoset_theme_chat_handle_request( $this->request( 'milloin runnin sukujuhlat ovat?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'En löytänyt tähän varmennettua vastausta', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( '18.8.1963', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( 'perustava kokous', $result->get_data()['reply'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_photo_query_without_matching_album_ignores_history_page_and_bypasses_mistral(): void {
		$this->configure_chat_backend();
		$page               = rytkoset_test_register_post( 70, 'page', 'Sukuseura' );
		$page->post_content = '<p>Perustava kokous pidettiin Runnin Terveyskylpylällä 18.8.1963.</p>';
		rytkoset_test_register_post( 71, 'gallery_album', '60-vuotissukujuhla Iisalmessa 19.8.2023' );
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'message' => array( 'content' => 'Mallin keksimä väite Runnin kuvista.' ),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Onko Runnin sukujuhlista kuvia?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'En löytänyt kysytystä tilaisuudesta julkaistua kuva-albumia', $result->get_data()['reply'] );
		$this->assertStringContainsString( '?s=Runnin', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( '18.8.1963', $result->get_data()['reply'] );
		$this->assertStringNotContainsString( 'Mallin keksimä', $result->get_data()['reply'] );
		$this->assertCount( 0, $GLOBALS['rytkoset_test_http_requests'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_event_catering_followup_uses_one_prefetched_section_completion(): void {
		$this->configure_chat_backend();
		$event               = rytkoset_test_register_post( 20, 'rytkoset_event', 'Rytkösten sukukokous Tampereella' );
		$event->post_content = '<p>Ohjelmassa on yhdessäoloa.</p>'
			. '<h2>Juhlan aikana</h2><p>Tilaisuudessa on vastaanottotiski.</p>'
			. '<h2>Perjantain buffet-illallinen</h2>'
			. '<p>Illallinen järjestetään perjantaina noin klo 20 ja maksetaan paikan päällä.</p>';
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Illallinen järjestetään perjantaina noin klo 20.\n\nLähde: https://rytkoset.test/?p=20",
						),
					),
				),
			)
		);
		$request = $this->request_with_messages(
			array(
				array(
					'role'    => 'user',
					'content' => 'Milloin Tampereen sukukokous pidetään?',
				),
				array(
					'role'    => 'assistant',
					'content' => 'Se pidetään 29.8.2026. https://rytkoset.test/?p=20',
				),
				array(
					'role'    => 'user',
					'content' => 'Entä illalista?',
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );
		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertSame( 'none', $payload['tool_choice'] );
		$this->assertStringContainsString( 'Perjantain buffet-illallinen', $payload['messages'][0]['content'] );
		$this->assertStringContainsString( 'maksetaan paikan päällä', $payload['messages'][0]['content'] );
		$this->assertStringNotContainsString( 'vastaanottotiski', $payload['messages'][0]['content'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_generic_event_catering_uses_the_complete_fee_inclusions_section(): void {
		$this->configure_chat_backend();
		$transport               = rytkoset_test_register_post( 19, 'rytkoset_event', 'Yhteiskuljetus Tampereen sukukokoukseen ja juhlaan' );
		$transport->post_content = '<h2>Paluu lauantaina</h2><p>Kyyti lähtee sukujuhlan jälkeen takaisin.</p>';
		$event                   = rytkoset_test_register_post( 20, 'rytkoset_event', 'Rytkösten sukukokous ja -juhla Tampereella 29.8.2026' );
		$event->post_content     = '<h2>Ilmoittautuminen</h2>'
			. '<p>Osallistumismaksu sisältää lauantain buffetlounaan, iltapäiväkahvitarjoilun sekä kahvia/teetä kokouksen ajaksi.</p>'
			. '<h2>Perjantain buffet-illallinen</h2><p>Illallinen järjestetään perjantaina.</p>'
			. '<h2>Ilmoittautuminen ilman verkkokauppaa</h2><p>Ilmoita ruokarajoitteet ja buffet kyllä/ei.</p>';
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array(
							'content' => "Osallistumismaksu sisältää buffetlounaan, iltapäiväkahvin sekä kahvia ja teetä.\n\nLähde: https://rytkoset.test/?p=20",
						),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Onko sukujuhlissa tarjolla ruokaa?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );

		$payload = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$this->assertSame( 'none', $payload['tool_choice'] );
		$this->assertStringContainsString( 'buffetlounaan, iltapäiväkahvitarjoilun sekä kahvia/teetä', $payload['messages'][0]['content'] );
		$this->assertStringNotContainsString( 'Kyyti lähtee sukujuhlan jälkeen', $payload['messages'][0]['content'] );
		$this->assertStringNotContainsString( 'Ilmoittautuminen ilman verkkokauppaa', $payload['messages'][0]['content'] );
		$this->assertStringNotContainsString( 'Perjantain buffet-illallinen', $payload['messages'][0]['content'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_forced_plain_text_response_retries_without_forcing(): void {
		$this->configure_chat_backend();
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array( 'content' => 'Mallin varmentamaton vastaus.' ),
					),
				),
			)
		);
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array( 'content' => 'Vastaus tavallisella työkaluvalinnalla.' ),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on hänen ammattinsa?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'Vastaus tavallisella työkaluvalinnalla.', $result->get_data()['reply'] );

		// The rejected first response must never reach the visitor.
		$this->assertStringNotContainsString( 'varmentamaton', $result->get_data()['reply'] );

		// Exactly one retry: forcing is dropped, so the second response is
		// judged by the ordinary validation path.
		$this->assertCount( 2, $GLOBALS['rytkoset_test_http_requests'] );

		$first  = json_decode( $GLOBALS['rytkoset_test_http_requests'][0]['args']['body'], true );
		$second = json_decode( $GLOBALS['rytkoset_test_http_requests'][1]['args']['body'], true );
		$this->assertSame( 'any', $first['tool_choice'] );
		$this->assertArrayNotHasKey( 'tool_choice', $second );
		$this->assertArrayHasKey( 'tools', $second );

		// The failure stays visible on the dashboard even though it recovered.
		$this->assertSame( 'forced_tool_missing', rytkoset_theme_chat_get_usage_stats()['last_error']['last_type'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_forced_malformed_tool_call_retries_without_forcing(): void {
		$this->configure_chat_backend();
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'tool_calls',
						'message'       => array(
							'content'    => '',
							'tool_calls' => array(
								array(
									'id'       => 'call-invalid',
									'type'     => 'function',
									'function' => array(
										'name'      => 'lue_sivu',
										'arguments' => '{"sivu_id":"ei-numero"}',
									),
								),
							),
						),
					),
				),
			)
		);

		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array( 'content' => 'Vastaus toisella kierroksella.' ),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on hänen ammattinsa?' ) );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( 'Vastaus toisella kierroksella.', $result->get_data()['reply'] );

		// A malformed sivu_id must not be executed as a page read.
		$this->assertStringNotContainsString( 'call-invalid', $result->get_data()['reply'] );
		$this->assertCount( 2, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 'forced_tool_missing', rytkoset_theme_chat_get_usage_stats()['last_error']['last_type'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_failed_retry_after_forced_tool_still_returns_safe_502(): void {
		$this->configure_chat_backend();
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'stop',
						'message'       => array( 'content' => 'Mallin varmentamaton vastaus.' ),
					),
				),
			)
		);
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'length',
						'message'       => array( 'content' => 'Katkennut mallivastaus.' ),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Mikä on hänen ammattinsa?' ) );

		// Dropping the forcing does not weaken the #604 response validation.
		$this->assert_safe_upstream_error( $result, 'Katkennut mallivastaus.' );
		$this->assertCount( 2, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 'invalid_finish_reason', rytkoset_theme_chat_get_usage_stats()['last_error']['last_type'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_length_finish_reason_returns_safe_502(): void {
		$this->configure_chat_backend();
		$this->queue_mistral_response(
			array(
				'choices' => array(
					array(
						'finish_reason' => 'length',
						'message'       => array( 'content' => 'Katkennut mallivastaus.' ),
					),
				),
			)
		);

		$result = rytkoset_theme_chat_handle_request( $this->request( 'Hei' ) );

		$this->assert_safe_upstream_error( $result, 'Katkennut mallivastaus.' );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_http_requests'] );
		$this->assertSame( 'invalid_finish_reason', rytkoset_theme_chat_get_usage_stats()['last_error']['last_type'] );
	}

	private function configure_chat_backend(): void {
		if ( ! defined( 'RYTKOSET_CHAT_API_KEY' ) ) {
			define( 'RYTKOSET_CHAT_API_KEY', 'test-key' );
		}

		if ( ! defined( 'RYTKOSET_CHAT_API_ENDPOINT' ) ) {
			define( 'RYTKOSET_CHAT_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions' );
		}
	}

	private function request( string $message ): WP_REST_Request {
		return $this->request_with_messages(
			array(
				array(
					'role'    => 'user',
					'content' => $message,
				),
			)
		);
	}

	/** @param array<int,array{role:string,content:string}> $messages Chat history. */
	private function request_with_messages( array $messages ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'wp_rest' );
		$request->set_param( 'messages', $messages );

		return $request;
	}

	private function register_rules_page( string $content ): void {
		$parent            = rytkoset_test_register_post( 90, 'page', 'Sukuseura' );
		$parent->post_name = 'sukuseura';

		$page               = rytkoset_test_register_post( 91, 'page', 'Säännöt', 90 );
		$page->post_name    = 'saannot';
		$page->post_content = $content;
	}

	private function register_board_page( string $content ): void {
		$parent            = rytkoset_test_register_post( 80, 'page', 'Sukuseura' );
		$parent->post_name = 'sukuseura';

		$page               = rytkoset_test_register_post( 81, 'page', 'Sukuseuran hallitus', 80 );
		$page->post_name    = 'sukuseuran-hallitus';
		$page->post_content = $content;
	}

	private function register_payment_terms_page( string $content ): void {
		$parent            = rytkoset_test_register_post( 60, 'page', 'Kauppa' );
		$parent->post_name = 'kauppa';

		$page               = rytkoset_test_register_post( 61, 'page', 'Maksu- ja toimitusehdot', 60 );
		$page->post_name    = 'maksu-ja-toimitusehdot';
		$page->post_content = $content;
	}

	/** @param array<string,mixed> $body Decoded Mistral response body. */
	private function queue_mistral_response( array $body ): void {
		$GLOBALS['rytkoset_test_http_responses'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}

	private function assert_safe_upstream_error( $result, string $forbidden ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_chat_upstream_error', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] ?? null );
		$this->assertStringNotContainsString( $forbidden, $result->get_error_message() );
	}
}
