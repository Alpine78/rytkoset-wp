<?php
/**
 * AI-tukichatin palvelinpuolen välityskerros (EPIC 11, #412).
 *
 * Rekisteröi REST-reitin `rytkoset/v1/chat`, kokoaa system-promptin ja välittää
 * keskustelun Mistralin EU-päätepisteeseen `wp_remote_post()`-kutsulla. API-avain
 * ja endpoint luetaan `wp-config.php`-vakioista, jotta avain ei koskaan päädy
 * selaimeen. Julkinen reitti (`__return_true`); väärinkäyttöä vastaan suojaavat
 * `wp_rest`-nonce, IP-pohjainen rate limit sekä syöte-, historia- ja token-rajat.
 *
 * Vastauksen escapetys ja käyttöliittymä kuuluvat widget-tikettiin (#413).
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palauttaa chat-välityksen konfiguraation `wp-config.php`-vakioista.
 *
 * Malli oletetaan aina saatavaksi (fallback), mutta avain ja endpoint ovat
 * pakollisia — ilman niitä `is_configured` on false ja reitti palauttaa
 * hallitun virheen fatalin sijaan.
 *
 * @return array{api_key:string,endpoint:string,model:string,is_configured:bool}
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_config' ) ) {
	function rytkoset_theme_chat_get_config() {
		$api_key  = defined( 'RYTKOSET_CHAT_API_KEY' ) ? trim( (string) constant( 'RYTKOSET_CHAT_API_KEY' ) ) : '';
		$endpoint = defined( 'RYTKOSET_CHAT_API_ENDPOINT' ) ? trim( (string) constant( 'RYTKOSET_CHAT_API_ENDPOINT' ) ) : '';
		$model    = defined( 'RYTKOSET_CHAT_API_MODEL' ) ? trim( (string) constant( 'RYTKOSET_CHAT_API_MODEL' ) ) : '';

		if ( '' === $model ) {
			$model = 'mistral-small-latest';
		}

		return array(
			'api_key'       => $api_key,
			'endpoint'      => $endpoint,
			'model'         => $model,
			'is_configured' => ( '' !== $api_key && '' !== $endpoint ),
		);
	}
}

/**
 * Palauttaa yksittäisen chat-viestin merkkirajan.
 *
 * Jaettu backendin (syötteen katkaisu) ja frontendin (widgetin `maxlength`)
 * kesken, jotta käyttöliittymän raja vastaa palvelimen rajaa.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_max_input_length' ) ) {
	function rytkoset_theme_chat_get_max_input_length() {
		return (int) apply_filters( 'rytkoset_theme_chat_max_input_length', 1000 );
	}
}

/**
 * Kertoo, onko chatti kytketty päälle Customizerissa.
 *
 * Oletus on päällä, jotta jo konfiguroitujen ympäristöjen toiminta säilyy
 * ennallaan. Gate koskee sekä widgetiä että REST-reittiä.
 *
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_admin_enabled' ) ) {
	function rytkoset_theme_chat_admin_enabled() {
		return (bool) get_theme_mod( 'rytkoset_theme_chat_enabled', true );
	}
}

/**
 * Palauttaa chatin tervetuloviestin (Customizer, oletus fallbackina).
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_welcome_message' ) ) {
	function rytkoset_theme_chat_get_welcome_message() {
		$default = __( 'Hei! Kysy minulta Rytkösten sukuseurasta, kuten jäsenyydestä, tapahtumista, kuvista tai sukututkimuksesta.', 'rytkoset-theme' );
		$message = trim( (string) get_theme_mod( 'rytkoset_theme_chat_welcome_message', '' ) );

		return '' !== $message ? $message : $default;
	}
}

/**
 * Palauttaa ylläpitäjän Customizeriin syöttämän FAQ-/tietopohjatekstin.
 *
 * Liitetään system-promptiin, jotta ei-tekninen ylläpitäjä voi muokata chatin
 * tietämystä ilman koodimuutosta. Tyhjä = ei erillistä tietopohjaa.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_faq_text' ) ) {
	function rytkoset_theme_chat_get_faq_text() {
		return trim( (string) get_theme_mod( 'rytkoset_theme_chat_faq', '' ) );
	}
}

/**
 * Sanitoi Customizerin valintaruudun arvon boolean-arvoksi.
 *
 * @param mixed $value Syötetty arvo.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_sanitize_checkbox' ) ) {
	function rytkoset_theme_chat_sanitize_checkbox( $value ) {
		return (bool) $value;
	}
}

/**
 * Rekisteröi Customizeriin "Tukichatti"-osion: päälle/pois, tervetuloviesti
 * ja FAQ-teksti. FAQ syötetään system-promptiin (#412).
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_register_customizer' ) ) {
	function rytkoset_theme_chat_register_customizer( $wp_customize ) {
		$wp_customize->add_section(
			'rytkoset_theme_chat',
			array(
				'title'       => __( 'Tukichatti', 'rytkoset-theme' ),
				'description' => __( 'AI-tukichatin asetukset. Chatti näkyy sivustolla vain, kun palvelinpuolen API-avain on asetettu (wp-config.php) ja chatti on kytketty päälle alta.', 'rytkoset-theme' ),
				'priority'    => 84,
			)
		);

		$wp_customize->add_setting(
			'rytkoset_theme_chat_enabled',
			array(
				'default'           => true,
				'sanitize_callback' => 'rytkoset_theme_chat_sanitize_checkbox',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'rytkoset_theme_chat_enabled',
			array(
				'label'       => __( 'Näytä tukichatti sivustolla', 'rytkoset-theme' ),
				'description' => __( 'Kun tämä on pois päältä, chatti-ikkuna piilotetaan eikä chatti vastaa (myös suora rajapintakutsu estetään).', 'rytkoset-theme' ),
				'section'     => 'rytkoset_theme_chat',
				'type'        => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'rytkoset_theme_chat_welcome_message',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'rytkoset_theme_chat_welcome_message',
			array(
				'label'       => __( 'Tervetuloviesti', 'rytkoset-theme' ),
				'description' => __( 'Ensimmäinen viesti, jonka chatti näyttää avattaessa. Jätä tyhjäksi käyttääksesi oletusviestiä.', 'rytkoset-theme' ),
				'section'     => 'rytkoset_theme_chat',
				'type'        => 'textarea',
			)
		);

		$wp_customize->add_setting(
			'rytkoset_theme_chat_faq',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'rytkoset_theme_chat_faq',
			array(
				'label'       => __( 'Tietopohja / usein kysytyt kysymykset', 'rytkoset-theme' ),
				'description' => __( 'Chatti käyttää tätä tekstiä ensisijaisena tietolähteenä (esim. jäsenyys ja maksut, tapahtumat, sisäänkirjautuminen ja salasana, yhteystiedot). Teksti lähetetään tekoälylle jokaisen kysymyksen mukana, joten pidä se ytimekkäänä.', 'rytkoset-theme' ),
				'section'     => 'rytkoset_theme_chat',
				'type'        => 'textarea',
			)
		);
	}
}
add_action( 'customize_register', 'rytkoset_theme_chat_register_customizer' );

/**
 * Rekisteröi REST-reitin `POST /wp-json/rytkoset/v1/chat`.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_register_routes' ) ) {
	function rytkoset_theme_chat_register_routes() {
		register_rest_route(
			'rytkoset/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => 'rytkoset_theme_chat_handle_request',
				'permission_callback' => '__return_true',
			)
		);
	}
}
add_action( 'rest_api_init', 'rytkoset_theme_chat_register_routes' );

/**
 * Käsittelee chat-pyynnön: tarkistaa nonce/konfiguraation/rate limitin,
 * rajaa syötteen ja välittää keskustelun Mistralille.
 *
 * @param WP_REST_Request $request Pyyntöobjekti.
 * @return WP_REST_Response|WP_Error
 */
if ( ! function_exists( 'rytkoset_theme_chat_handle_request' ) ) {
	function rytkoset_theme_chat_handle_request( $request ) {
		// 1. Nonce (CSRF-suoja). Julkinen reitti, mutta pyyntö on tultava sivustolta.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rytkoset_chat_invalid_nonce',
				__( 'Istunto on vanhentunut. Päivitä sivu ja yritä uudelleen.', 'rytkoset-theme' ),
				array( 'status' => 403 )
			);
		}

		// 2. Konfiguraatio. Puuttuva avain/endpoint tuottaa hallitun virheen.
		$config = rytkoset_theme_chat_get_config();
		if ( ! $config['is_configured'] ) {
			return new WP_Error(
				'rytkoset_chat_not_configured',
				__( 'Chat ei ole juuri nyt käytettävissä. Ota yhteyttä sähköpostitse.', 'rytkoset-theme' ),
				array( 'status' => 503 )
			);
		}

		// 2b. Ylläpitäjän kytkin (Customizer). Pois → hallittu virhe, myös suora kutsu.
		if ( ! rytkoset_theme_chat_admin_enabled() ) {
			return new WP_Error(
				'rytkoset_chat_disabled',
				__( 'Chat ei ole juuri nyt käytettävissä. Ota yhteyttä sähköpostitse.', 'rytkoset-theme' ),
				array( 'status' => 503 )
			);
		}

		// 3. Rate limit (IP-pohjainen, kustannussuoja).
		$limit = (int) apply_filters( 'rytkoset_theme_chat_rate_limit', 20 );
		if ( rytkoset_theme_chat_register_rate_limit_hit( rytkoset_theme_chat_get_client_ip(), $limit ) ) {
			return new WP_Error(
				'rytkoset_chat_rate_limited',
				__( 'Liikaa viestejä lyhyessä ajassa. Yritä hetken kuluttua uudelleen.', 'rytkoset-theme' ),
				array( 'status' => 429 )
			);
		}

		// 4. Syötteen jäsennys ja rajat.
		$max_input   = rytkoset_theme_chat_get_max_input_length();
		$max_history = (int) apply_filters( 'rytkoset_theme_chat_max_history', 8 );

		$raw_messages = $request->get_param( 'messages' );
		if ( ! is_array( $raw_messages ) ) {
			$raw_messages = array();
		}

		$messages = rytkoset_theme_chat_prepare_messages( $raw_messages, $max_history, $max_input );
		if ( empty( $messages ) ) {
			return new WP_Error(
				'rytkoset_chat_empty_message',
				__( 'Kirjoita viesti ennen lähettämistä.', 'rytkoset-theme' ),
				array( 'status' => 400 )
			);
		}

		// 5. Payload: system-prompt + rajattu historia.
		$max_tokens = (int) apply_filters( 'rytkoset_theme_chat_max_tokens', 512 );

		$payload_messages = array_merge(
			array(
				array(
					'role'    => 'system',
					'content' => rytkoset_theme_chat_get_system_prompt(),
				),
			),
			$messages
		);

		$payload = array(
			'model'       => $config['model'],
			'messages'    => $payload_messages,
			'max_tokens'  => $max_tokens,
			'temperature' => 0.3,
		);

		// 6. Mistral-kutsu.
		$response = wp_remote_post(
			$config['endpoint'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $config['api_key'],
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		// 7. Virheenkäsittely — avainta tai teknisiä yksityiskohtia ei vuodeta.
		if ( is_wp_error( $response ) ) {
			rytkoset_theme_chat_log_error( 'wp_remote_post: ' . $response->get_error_message() );
			return rytkoset_theme_chat_upstream_error();
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			rytkoset_theme_chat_log_error( 'Mistral HTTP ' . $status_code );
			return rytkoset_theme_chat_upstream_error();
		}

		// 8. Vastauksen poiminta.
		$reply = rytkoset_theme_chat_extract_reply( $body );
		if ( '' === $reply ) {
			rytkoset_theme_chat_log_error( 'Tyhjä tai odottamaton Mistral-vaste.' );
			return rytkoset_theme_chat_upstream_error();
		}

		return new WP_REST_Response( array( 'reply' => $reply ), 200 );
	}
}

/**
 * Palauttaa yhtenäisen "upstream ei vastannut" -virheen (ei vuoda syytä).
 *
 * @return WP_Error
 */
if ( ! function_exists( 'rytkoset_theme_chat_upstream_error' ) ) {
	function rytkoset_theme_chat_upstream_error() {
		return new WP_Error(
			'rytkoset_chat_upstream_error',
			__( 'Vastauksen haku ei juuri nyt onnistunut. Yritä hetken kuluttua uudelleen.', 'rytkoset-theme' ),
			array( 'status' => 502 )
		);
	}
}

/**
 * Kirjaa chatin sisäisen virheen palvelimen lokiin vain kun WP_DEBUG on päällä.
 *
 * Ei koskaan tulosta API-avainta eikä pyynnön sisältöä.
 *
 * @param string $message Tekninen virheviesti.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_log_error' ) ) {
	function rytkoset_theme_chat_log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Vain WP_DEBUG-tilassa; ei sisällä salaisuuksia.
			error_log( '[rytkoset-chat] ' . $message );
		}
	}
}

/**
 * Rajaa ja siistii keskusteluhistorian ennen API-kutsua.
 *
 * Säilyttää vain `user`/`assistant`-roolit, sanitoi sisällön, pudottaa tyhjät,
 * katkaisee jokaisen viestin merkkirajaan ja palauttaa enintään viimeiset
 * `$max_history` viestiä.
 *
 * @param array $messages    Raaka viestilista (rooli + sisältö).
 * @param int   $max_history Historian enimmäispituus.
 * @param int   $max_length  Yksittäisen viestin merkkiraja.
 * @return array<int,array{role:string,content:string}>
 */
if ( ! function_exists( 'rytkoset_theme_chat_prepare_messages' ) ) {
	function rytkoset_theme_chat_prepare_messages( $messages, $max_history, $max_length ) {
		$max_history = max( 1, (int) $max_history );
		$max_length  = max( 1, (int) $max_length );
		$allowed     = array( 'user', 'assistant' );
		$clean       = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = isset( $message['role'] ) ? (string) $message['role'] : '';
			if ( ! in_array( $role, $allowed, true ) ) {
				continue;
			}

			$content = isset( $message['content'] ) ? sanitize_textarea_field( (string) $message['content'] ) : '';
			$content = rytkoset_theme_chat_truncate( $content, $max_length );

			if ( '' === $content ) {
				continue;
			}

			$clean[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		if ( count( $clean ) > $max_history ) {
			$clean = array_slice( $clean, -$max_history );
		}

		return $clean;
	}
}

/**
 * Katkaisee merkkijonon annettuun merkkirajaan (monitavuinen).
 *
 * @param string $text   Teksti.
 * @param int    $length Enimmäispituus merkkeinä.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_truncate' ) ) {
	function rytkoset_theme_chat_truncate( $text, $length ) {
		$text   = (string) $text;
		$length = max( 1, (int) $length );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length );
		}

		return substr( $text, 0, $length );
	}
}

/**
 * Kertoo, onko rate limit ylittynyt annetulla laskurin arvolla.
 *
 * Puhdas päätösfunktio (testattava): tosi kun jo tehtyjen pyyntöjen määrä on
 * saavuttanut rajan.
 *
 * @param int $count Ikkunassa jo tehdyt pyynnöt.
 * @param int $limit Sallittu enimmäismäärä.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_rate_limit_exceeded' ) ) {
	function rytkoset_theme_chat_rate_limit_exceeded( $count, $limit ) {
		return (int) $count >= (int) $limit;
	}
}

/**
 * Kirjaa yhden pyynnön IP:n rate limit -laskuriin ja kertoo, ylittyikö raja.
 *
 * Kiinteä ikkuna transientilla: ensimmäinen pyyntö avaa ikkunan, seuraavat
 * kasvattavat laskuria ja säilyttävät alkuperäisen umpeutumisajan.
 *
 * @param string $ip    Asiakkaan IP.
 * @param int    $limit Sallittu enimmäismäärä ikkunassa.
 * @return bool True, jos raja on ylittynyt (pyyntö tulisi hylätä).
 */
if ( ! function_exists( 'rytkoset_theme_chat_register_rate_limit_hit' ) ) {
	function rytkoset_theme_chat_register_rate_limit_hit( $ip, $limit ) {
		$window = (int) apply_filters( 'rytkoset_theme_chat_rate_window', HOUR_IN_SECONDS );
		$window = max( 60, $window );
		$key    = 'rytkoset_chat_rl_' . md5( (string) $ip );
		$now    = time();

		$record = get_transient( $key );
		if ( ! is_array( $record ) || ! isset( $record['count'], $record['start'] ) ) {
			set_transient(
				$key,
				array(
					'count' => 1,
					'start' => $now,
				),
				$window
			);
			return rytkoset_theme_chat_rate_limit_exceeded( 0, $limit );
		}

		if ( rytkoset_theme_chat_rate_limit_exceeded( (int) $record['count'], $limit ) ) {
			return true;
		}

		$record['count'] = (int) $record['count'] + 1;
		$remaining       = max( 1, $window - ( $now - (int) $record['start'] ) );
		set_transient( $key, $record, $remaining );

		return false;
	}
}

/**
 * Poimii Mistralin chat-completion-vastauksesta assistentin tekstin.
 *
 * Puhdas funktio (testattava): odottaa jo dekoodattua vastausrakennetta ja
 * palauttaa tyhjän merkkijonon, jos rakenne on odottamaton.
 *
 * @param mixed $body Dekoodattu API-vaste.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_extract_reply' ) ) {
	function rytkoset_theme_chat_extract_reply( $body ) {
		if ( ! is_array( $body ) || empty( $body['choices'][0]['message']['content'] ) ) {
			return '';
		}

		$content = $body['choices'][0]['message']['content'];
		if ( ! is_string( $content ) ) {
			return '';
		}

		return trim( $content );
	}
}

/**
 * Palauttaa asiakkaan IP-osoitteen rate limitiä varten.
 *
 * Käyttää vain `REMOTE_ADDR`:ia (välityspalvelinotsakkeisiin ei luoteta).
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_client_ip' ) ) {
	function rytkoset_theme_chat_get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}
}

/**
 * Kokoaa Mistralille annettavan system-promptin (rooli + ohjeet).
 *
 * MVP: yhdistyksen identiteetti ja käyttäytymisohjeet. Laajemman FAQ-tietämyksen
 * voi lisätä `rytkoset_theme_chat_system_prompt`-suodattimella.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_system_prompt' ) ) {
	function rytkoset_theme_chat_get_system_prompt() {
		$contact_email = function_exists( 'rytkoset_theme_get_contact_email' )
			? rytkoset_theme_get_contact_email()
			: 'info@rytkoset.net';

		$prompt  = "Olet Rytkösten sukuseura ry:n verkkosivujen suomenkielinen tukiassistentti.\n\n";
		$prompt .= "Ohjeet:\n";
		$prompt .= "- Vastaa aina ja vain suomeksi, ystävällisesti ja tiiviisti.\n";
		$prompt .= "- Pysy yhdistyksen ja sen verkkosivujen aiheissa: jäsenyys, tapahtumat, sukujuhlat, sukututkimus, kuvat/albumit, digilehdet ja yhteystiedot.\n";
		$prompt .= "- Älä keksi tietoa. Jos et tiedä vastausta tai kysymys ei liity yhdistykseen, kerro se rehellisesti.\n";
		$prompt .= "- Ohjaa epävarmoissa tai henkilökohtaisissa asioissa ottamaan yhteyttä sähköpostitse osoitteeseen {$contact_email}.\n";
		$prompt .= '- Älä pyydä äläkä käsittele arkaluontoisia tietoja (salasanat, maksutiedot).';

		// Ylläpitäjän Customizeriin syöttämä tietopohja (#414) ensisijaisena lähteenä.
		$faq = rytkoset_theme_chat_get_faq_text();
		if ( '' !== $faq ) {
			$prompt .= "\n\nKäytä ensisijaisena tietolähteenä seuraavaa yhdistyksen tietopohjaa. Jos vastaus ei löydy siitä etkä ole varma, kerro ettet tiedä ja ohjaa sähköpostiin.\n\n";
			$prompt .= "Tietopohja:\n" . $faq;
		}

		/**
		 * Suodattaa chat-assistentin system-promptin.
		 *
		 * @param string $prompt        Koottu system-prompt.
		 * @param string $contact_email Yhdistyksen yhteysosoite.
		 */
		return apply_filters( 'rytkoset_theme_chat_system_prompt', $prompt, $contact_email );
	}
}

/**
 * Kertoo, näytetäänkö chat-widget frontendissä.
 *
 * True vain kun backend on konfiguroitu (ei kuollutta widgetiä ilman avainta);
 * suodatettavissa `rytkoset_theme_chat_widget_enabled`-suotimella.
 *
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_widget_is_enabled' ) ) {
	function rytkoset_theme_chat_widget_is_enabled() {
		$config  = rytkoset_theme_chat_get_config();
		$enabled = ! empty( $config['is_configured'] ) && rytkoset_theme_chat_admin_enabled();

		/**
		 * Suodattaa chat-widgetin näyttämisen frontendissä.
		 *
		 * @param bool $enabled Näytetäänkö widget.
		 */
		return (bool) apply_filters( 'rytkoset_theme_chat_widget_enabled', $enabled );
	}
}

/**
 * Tulostaa chat-widgetin merkkauksen sivun alatunnisteeseen.
 *
 * Renderöi vain semanttisen, escapetetun kuoren; viestit lisää JS turvallisesti
 * (`textContent`). Piilotettu ilman JS:ää (`hidden`-attribuutti), jonka JS poistaa
 * alustuksessa.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_render_widget' ) ) {
	function rytkoset_theme_chat_render_widget() {
		if ( ! rytkoset_theme_chat_widget_is_enabled() ) {
			return;
		}

		$greeting  = rytkoset_theme_chat_get_welcome_message();
		$max_input = (string) rytkoset_theme_chat_get_max_input_length();
		?>
		<div class="rytkoset-chat" data-rytkoset-chat hidden>
			<button
				type="button"
				class="rytkoset-chat__toggle"
				aria-expanded="false"
				aria-controls="rytkoset-chat-panel"
				aria-label="<?php esc_attr_e( 'Avaa tukichatti', 'rytkoset-theme' ); ?>"
				data-rytkoset-chat-toggle
			>
				<span class="rytkoset-chat__toggle-icon" aria-hidden="true">💬</span>
				<span class="rytkoset-chat__toggle-label"><?php esc_html_e( 'Kysy', 'rytkoset-theme' ); ?></span>
			</button>

			<section
				id="rytkoset-chat-panel"
				class="rytkoset-chat__panel"
				role="dialog"
				aria-label="<?php esc_attr_e( 'Rytkösten sukuseuran tukichatti', 'rytkoset-theme' ); ?>"
				data-rytkoset-chat-panel
				hidden
			>
				<header class="rytkoset-chat__header">
					<h2 class="rytkoset-chat__title"><?php esc_html_e( 'Kysy sukuseurasta', 'rytkoset-theme' ); ?></h2>
					<button
						type="button"
						class="rytkoset-chat__close"
						aria-label="<?php esc_attr_e( 'Sulje chatti', 'rytkoset-theme' ); ?>"
						data-rytkoset-chat-close
					>
						<span aria-hidden="true">&times;</span>
					</button>
				</header>

				<p class="rytkoset-chat__disclaimer">
					<?php esc_html_e( 'Tekoälyavustaja. Älä syötä arkaluonteisia tietoja; varmista tärkeät asiat sähköpostitse.', 'rytkoset-theme' ); ?>
				</p>

				<div
					class="rytkoset-chat__log"
					role="log"
					aria-live="polite"
					aria-atomic="false"
					data-rytkoset-chat-log
				>
					<?php // Yhdellä rivillä: white-space: pre-wrap säilyttäisi sisennyksen tyhjätilan. ?>
					<div class="rytkoset-chat__msg rytkoset-chat__msg--assistant"><?php echo esc_html( $greeting ); ?></div>
				</div>

				<form class="rytkoset-chat__form" data-rytkoset-chat-form>
					<textarea
						class="rytkoset-chat__input"
						rows="1"
						maxlength="<?php echo esc_attr( $max_input ); ?>"
						placeholder="<?php esc_attr_e( 'Kirjoita kysymyksesi…', 'rytkoset-theme' ); ?>"
						aria-label="<?php esc_attr_e( 'Kirjoita viesti', 'rytkoset-theme' ); ?>"
						data-rytkoset-chat-input
					></textarea>
					<button
						type="submit"
						class="rytkoset-chat__send"
						aria-label="<?php esc_attr_e( 'Lähetä viesti', 'rytkoset-theme' ); ?>"
						data-rytkoset-chat-send
					>
						<?php echo rytkoset_theme_inline_icon( 'send', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Teeman oma sanitoitu SVG. ?>
					</button>
				</form>
			</section>
		</div>
		<?php
	}
}
add_action( 'wp_footer', 'rytkoset_theme_chat_render_widget' );
