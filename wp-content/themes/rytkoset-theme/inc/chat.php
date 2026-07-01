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
		$max_input   = (int) apply_filters( 'rytkoset_theme_chat_max_input_length', 1000 );
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

		/**
		 * Suodattaa chat-assistentin system-promptin.
		 *
		 * @param string $prompt        Koottu system-prompt.
		 * @param string $contact_email Yhdistyksen yhteysosoite.
		 */
		return apply_filters( 'rytkoset_theme_chat_system_prompt', $prompt, $contact_email );
	}
}
