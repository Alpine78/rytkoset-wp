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
 * Sivun lukutyökalu (#501): malli saa `lue_sivu`-function calling -työkalun,
 * jolla se voi tarvittaessa hakea sivustokartassa listatun julkisen sivun
 * tekstisisällön. Työkalukysymyksissä sallitaan rajattu määrä lisäkierroksia
 * Mistralille; peruskysymykset vastataan edelleen yhdellä API-kutsulla suoraan
 * promptin lähteistä.
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
 * @return array{api_key:string,endpoint:string,model:string,prompt_cache_key:string,is_configured:bool}
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_config' ) ) {
	function rytkoset_theme_chat_get_config() {
		$api_key          = defined( 'RYTKOSET_CHAT_API_KEY' ) ? trim( (string) constant( 'RYTKOSET_CHAT_API_KEY' ) ) : '';
		$endpoint         = defined( 'RYTKOSET_CHAT_API_ENDPOINT' ) ? trim( (string) constant( 'RYTKOSET_CHAT_API_ENDPOINT' ) ) : '';
		$model            = defined( 'RYTKOSET_CHAT_API_MODEL' ) ? trim( (string) constant( 'RYTKOSET_CHAT_API_MODEL' ) ) : '';
		$prompt_cache_key = defined( 'RYTKOSET_CHAT_PROMPT_CACHE_KEY' ) ? trim( (string) constant( 'RYTKOSET_CHAT_PROMPT_CACHE_KEY' ) ) : '';

		if ( '' === $model ) {
			$model = 'mistral-small-latest';
		}

		return array(
			'api_key'          => $api_key,
			'endpoint'         => $endpoint,
			'model'            => $model,
			'prompt_cache_key' => $prompt_cache_key,
			'is_configured'    => ( '' !== $api_key && '' !== $endpoint ),
		);
	}
}

/**
 * Kertoo, onko endpoint Mistralin oma chat-rajapinta.
 *
 * Prompt-välimuistin kenttää ei lähetetä Azurelle tai muille mahdollisille
 * palveluntarjoajille ilman erillistä yhteensopivuuden varmistusta.
 *
 * @param string $endpoint API-endpointin URL.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_endpoint_is_mistral' ) ) {
	function rytkoset_theme_chat_endpoint_is_mistral( $endpoint ) {
		$host = wp_parse_url( (string) $endpoint, PHP_URL_HOST );

		return is_string( $host ) && 'api.mistral.ai' === strtolower( $host );
	}
}

/**
 * Lisää Mistralin kokeellisen prompt-välimuistiavaimen payloadiin tarvittaessa.
 *
 * Tyhjä avain tai muu palveluntarjoaja palauttaa payloadin täsmälleen
 * ennallaan. Palautettu payload säilyy samana myös sivunlukutyökalun sisäisillä
 * jatkokierroksilla, joilla vain messages- ja tool_choice-kenttiä muutetaan.
 *
 * @param array  $payload          Chat completions -payload.
 * @param string $endpoint         API-endpointin URL.
 * @param string $prompt_cache_key Ympäristökohtainen, henkilötiedoton avain.
 * @return array
 */
if ( ! function_exists( 'rytkoset_theme_chat_maybe_add_prompt_cache_key' ) ) {
	function rytkoset_theme_chat_maybe_add_prompt_cache_key( $payload, $endpoint, $prompt_cache_key ) {
		$prompt_cache_key = trim( (string) $prompt_cache_key );

		if ( '' !== $prompt_cache_key && rytkoset_theme_chat_endpoint_is_mistral( $endpoint ) ) {
			$payload['prompt_cache_key'] = $prompt_cache_key;
		}

		return $payload;
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
 * Palauttaa rate limitin (viestiä / IP / ikkuna).
 *
 * Oletuksen (20) voi ylikirjoittaa ympäristökohtaisesti `wp-config.php`-vakiolla
 * `RYTKOSET_CHAT_RATE_LIMIT` (esim. dev-testaus) — suodattimia ei voi käyttää
 * wp-configissa, koska `add_filter()` ei ole siellä vielä määritelty.
 * Suodatin `rytkoset_theme_chat_rate_limit` ajetaan vakion päälle.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_rate_limit' ) ) {
	function rytkoset_theme_chat_get_rate_limit() {
		$limit = defined( 'RYTKOSET_CHAT_RATE_LIMIT' ) ? (int) constant( 'RYTKOSET_CHAT_RATE_LIMIT' ) : 20;
		$limit = (int) apply_filters( 'rytkoset_theme_chat_rate_limit', $limit );

		return max( 1, $limit );
	}
}

/**
 * Palauttaa API-kutsuun sisällytettävän keskusteluhistorian enimmäispituuden.
 *
 * Oletuksen (8 viestiä) voi ylikirjoittaa ympäristökohtaisesti vakiolla
 * `RYTKOSET_CHAT_MAX_HISTORY`; suodatin `rytkoset_theme_chat_max_history`
 * ajetaan vakion päälle.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_max_history' ) ) {
	function rytkoset_theme_chat_get_max_history() {
		$max_history = defined( 'RYTKOSET_CHAT_MAX_HISTORY' ) ? (int) constant( 'RYTKOSET_CHAT_MAX_HISTORY' ) : 8;
		$max_history = (int) apply_filters( 'rytkoset_theme_chat_max_history', $max_history );

		return max( 1, $max_history );
	}
}

/**
 * Palauttaa vastauksen token-rajan (`max_tokens`) API-kutsulle.
 *
 * Oletus 800: 512 katkaisi monikohtaiset vastaukset (esim. jäsenyystyypit +
 * ohjeet) kesken. Kuluvaikutus on pieni, koska rate limit rajaa viestimäärän.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_max_tokens' ) ) {
	function rytkoset_theme_chat_get_max_tokens() {
		$max_tokens = (int) apply_filters( 'rytkoset_theme_chat_max_tokens', 800 );

		return max( 1, $max_tokens );
	}
}

/**
 * Palauttaa mallin temperature-arvon (0–1) API-kutsulle.
 *
 * Oletus 0.2: tukichatin vastaukset ovat faktavastauksia, joissa satunnaisuus
 * lisää vain epäjohdonmukaisuutta ja hallusinaatioriskiä.
 *
 * @return float
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_temperature' ) ) {
	function rytkoset_theme_chat_get_temperature() {
		$temperature = (float) apply_filters( 'rytkoset_theme_chat_temperature', 0.2 );

		return min( 1.0, max( 0.0, $temperature ) );
	}
}

/**
 * Returns the frequency penalty applied to the generated response.
 *
 * Mistral documents penalties as the parameter for avoiding the repetition
 * loops a model can fall into with long contexts or long outputs. The penalty
 * accumulates per repeated token, so a low value still compounds quickly once a
 * phrase starts repeating. The default is deliberately conservative: a Finnish
 * support answer legitimately reuses domain words such as "sukuseura" or
 * "jäsenyys", and an aggressive penalty distorts that wording. Raise it through
 * the filter if a repetition loop still reproduces.
 *
 * Clamped to 0–2 rather than the API's own bounds: a negative penalty would
 * encourage repetition, which is never wanted here.
 *
 * @return float
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_frequency_penalty' ) ) {
	function rytkoset_theme_chat_get_frequency_penalty() {
		$penalty = (float) apply_filters( 'rytkoset_theme_chat_frequency_penalty', 0.3 );

		return min( 2.0, max( 0.0, $penalty ) );
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
 * Kertoo, liitetäänkö system-promptiin automaattisesti koottu ajantasainen
 * tietolohko (tulevat tapahtumat + jäsenyystuotteet, #459).
 *
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_live_context_is_enabled' ) ) {
	function rytkoset_theme_chat_live_context_is_enabled() {
		/**
		 * Suodattaa ajantasaisen tietolohkon käytön (koko lohkon voi kytkeä pois).
		 *
		 * @param bool $enabled Liitetäänkö lohko system-promptiin.
		 */
		return (bool) apply_filters( 'rytkoset_theme_chat_live_context_enabled', true );
	}
}

/**
 * Muotoilee ISO-päivämäärän (YYYY-MM-DD) suomalaiseen muotoon (j.n.Y).
 *
 * Tarkoituksella riippumaton `date_format`-asetuksesta: promptin päivämäärän
 * pitää olla deterministinen ja yksiselitteinen mallille.
 *
 * @param string $iso_date ISO-päivämäärä.
 * @return string Muotoiltu päivämäärä tai tyhjä.
 */
if ( ! function_exists( 'rytkoset_theme_chat_format_iso_date' ) ) {
	function rytkoset_theme_chat_format_iso_date( $iso_date ) {
		$iso_date = trim( (string) $iso_date );

		if ( '' === $iso_date ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $iso_date );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $iso_date ) {
			return '';
		}

		return $date->format( 'j.n.Y' );
	}
}

/**
 * Palauttaa tulevien julkaistujen tapahtumien ID:t aikajärjestyksessä.
 *
 * "Tuleva" = tapahtumalla on kelvollinen päivämäärä eikä se ole mennyt
 * (`rytkoset_theme_is_event_date_passed()` pitää tapahtumapäivän voimassa
 * päivän loppuun). Määrä rajataan suodattimella.
 *
 * @param int $limit Enimmäismäärä; 0 = suodattimen oletus (5).
 * @return array<int,int>
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_upcoming_event_ids' ) ) {
	function rytkoset_theme_chat_get_upcoming_event_ids( $limit = 0 ) {
		$limit = (int) $limit;

		if ( $limit <= 0 ) {
			/**
			 * Suodattaa promptiin liitettävien tulevien tapahtumien enimmäismäärän.
			 *
			 * @param int $max_events Tapahtumien enimmäismäärä.
			 */
			$limit = (int) apply_filters( 'rytkoset_theme_chat_live_context_max_events', 5 );
		}

		$limit = max( 1, $limit );

		if ( ! function_exists( 'rytkoset_theme_get_event_date_raw' ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'   => 'rytkoset_event',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		$upcoming = array();

		foreach ( (array) $ids as $event_id ) {
			$event_id = (int) $event_id;

			if ( 'publish' !== get_post_status( $event_id ) ) {
				continue;
			}

			$date = rytkoset_theme_get_event_date_raw( $event_id );

			if ( '' === $date || rytkoset_theme_is_event_date_passed( $event_id ) ) {
				continue;
			}

			$upcoming[ $event_id ] = $date;
		}

		// ISO-päivämäärät järjestyvät oikein merkkijonovertailulla.
		asort( $upcoming );

		return array_slice( array_keys( $upcoming ), 0, $limit );
	}
}

/**
 * Muotoilee yhden tapahtuman tekstilohkon ajantasaista tietolohkoa varten.
 *
 * Kokoaa olemassa olevista tapahtumagettereistä (nimi, ajankohta, paikka,
 * hinta, ilmoittautumisen takaraja, #450-lisävalinnat, URL) tiiviin
 * luettelokohdan. Ei lupaa paikkatilannetta (#459 rajaus).
 *
 * @param int $event_id Tapahtuman ID.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_format_event_context' ) ) {
	function rytkoset_theme_chat_format_event_context( $event_id ) {
		$event_id = (int) $event_id;
		$title    = trim( (string) get_the_title( $event_id ) );

		if ( '' === $title ) {
			return '';
		}

		$lines   = array();
		$lines[] = '- ' . $title;

		$date = rytkoset_theme_chat_format_iso_date( rytkoset_theme_get_event_date_raw( $event_id ) );
		$time = rytkoset_theme_get_event_time_display( $event_id );

		if ( '' !== $date ) {
			$lines[] = '  Ajankohta: ' . $date . ( '' !== $time ? ' klo ' . $time : '' );
		}

		$location = rytkoset_theme_get_event_location( $event_id );
		if ( '' !== $location ) {
			$lines[] = '  Paikka: ' . $location;
		}

		$fee = rytkoset_theme_get_event_fee_display( $event_id );
		if ( '' !== $fee ) {
			$lines[] = '  Hinta: ' . $fee;
		}

		if ( 'paid' === rytkoset_theme_get_event_fee_type( $event_id ) ) {
			$lines[] = '  Ilmoittautuminen ja maksu tapahtuman sivun kautta.';
		}

		// Sama ilmoittautumisajan lähde kuin tapahtumasivun yhteenvetokortilla.
		$deadline         = rytkoset_theme_get_event_registration_deadline_raw( $event_id );
		$deadline_display = rytkoset_theme_chat_format_iso_date( $deadline );
		if ( '' !== $deadline_display ) {
			$deadline_label = rytkoset_theme_is_event_registration_deadline_passed( $event_id )
				? 'Ilmoittautuminen päättyi'
				: 'Ilmoittautuminen päättyy';
			$lines[]        = '  ' . $deadline_label . ': ' . $deadline_display;
		}

		if ( rytkoset_theme_event_has_choice_field( $event_id ) ) {
			$options = rytkoset_theme_get_event_choice_options( $event_id );

			if ( ! empty( $options ) ) {
				$lines[] = '  ' . rytkoset_theme_get_event_choice_field_label( $event_id ) . ': ' . implode( ', ', $options );
			}
		}

		if ( rytkoset_theme_event_collects_quantity( $event_id ) ) {
			$lines[] = '  Ilmoittautumisessa kysytään myös: ' . rytkoset_theme_get_event_quantity_field_label( $event_id );
		}

		$lines[] = '  Lisätiedot ja ilmoittautuminen: ' . get_permalink( $event_id );

		return implode( "\n", $lines );
	}
}

/**
 * Kokoaa jäsenyystuotteiden rivit ajantasaista tietolohkoa varten.
 *
 * Fail-safe: ilman WooCommercea (tai jäsenyysmoduulia) palautuu tyhjä
 * merkkijono eikä mikään kaadu. Vain julkaistut jäsenyystuotteet listataan.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_membership_context' ) ) {
	function rytkoset_theme_chat_get_membership_context() {
		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'rytkoset_theme_is_membership_product' ) ) {
			return '';
		}

		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => rytkoset_theme_get_membership_product_meta_key(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Jäsenyystuotteita on kourallinen; haku ajetaan vain chat-pyynnön yhteydessä.
				'meta_value'  => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Ks. yllä.
			)
		);

		$lines = array();

		foreach ( (array) $ids as $product_id ) {
			$product = wc_get_product( (int) $product_id );

			if ( ! rytkoset_theme_is_membership_product( $product ) || 'publish' !== $product->get_status() ) {
				continue;
			}

			$name = trim( (string) $product->get_name() );
			if ( '' === $name ) {
				continue;
			}

			$price      = rytkoset_theme_format_event_price_text( (string) $product->get_price() );
			$type_label = rytkoset_theme_get_membership_type_label( rytkoset_theme_get_membership_product_type( $product ) );
			$expiry     = rytkoset_theme_chat_format_iso_date( rytkoset_theme_get_membership_product_expiry_date( $product ) );

			$line = '- ' . $name;
			if ( '' !== $price ) {
				$line .= ': ' . $price;
			}
			$line .= ' (' . $type_label . ')';
			if ( '' !== $expiry ) {
				$line .= ' — jäsenyys voimassa ' . $expiry . ' asti';
			}

			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}
}

/**
 * Kokoaa julkaistujen digilehtien rivit ajantasaista tietolohkoa varten.
 *
 * Digilehdet-sisältötyyppi on olemassa, mutta arkisto voi olla tyhjä eikä
 * yhtään lehteä ole vielä julkaistu — ilman tätä lohkoa malli päättelisi
 * pysyvän kontekstin HTML-muotomaininnasta virheellisesti, että digilehtiä
 * on jo saatavilla (tuotannossa havaittu tapaus). Vain ylälehdet lasketaan
 * mukaan, ei niiden alle kuuluvia juttuja. Fail-safe: tyhjä merkkijono, jos
 * digilehti-CPT ei ole rekisteröity.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_digital_magazine_context' ) ) {
	function rytkoset_theme_chat_get_digital_magazine_context() {
		if ( ! function_exists( 'rytkoset_theme_get_digital_magazine_access_mode_label' ) ) {
			return '';
		}

		$ids = get_posts(
			array(
				'post_type'   => 'digital_magazine',
				'post_status' => 'publish',
				'post_parent' => 0,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		/**
		 * Suodattaa promptiin liitettävien digilehtien enimmäismäärän.
		 *
		 * @param int $max_magazines Digilehtien enimmäismäärä.
		 */
		$limit = max( 1, (int) apply_filters( 'rytkoset_theme_chat_live_context_max_magazines', 10 ) );

		$lines = array();

		foreach ( (array) $ids as $magazine_id ) {
			if ( count( $lines ) >= $limit ) {
				break;
			}

			$magazine_id = (int) $magazine_id;

			if ( 'publish' !== get_post_status( $magazine_id ) ) {
				continue;
			}

			$title = trim( (string) get_the_title( $magazine_id ) );

			if ( '' === $title ) {
				continue;
			}

			$access_label = rytkoset_theme_get_digital_magazine_access_mode_label(
				rytkoset_theme_get_digital_magazine_access_mode( $magazine_id )
			);

			$lines[] = '- ' . $title . ' (' . $access_label . '): ' . get_permalink( $magazine_id );
		}

		return implode( "\n", $lines );
	}
}

/**
 * Kokoaa muiden verkkokaupan tuotteiden rivit ajantasaista tietolohkoa varten (#471).
 *
 * Sama rivimalli kuin `rytkoset_theme_chat_get_membership_context()`: nimi, hinta
 * ja permalink. Jäsenyystuotteet jätetään pois, koska niillä on jo oma osionsa
 * (ei duplikointia). "Ostettavissa oleva" tulkitaan yksinkertaisesti julkaistuksi
 * tuotteeksi, jolla on hinta — sama perusehto kuin WooCommercen omassa
 * `is_purchasable()`:ssa, mutta puhtaana ja testattavana ehtona ilman riippuvuutta
 * nykyiseen käyttäjään. Fail-safe: ilman WooCommercea palautuu tyhjä merkkijono.
 * Ei varastotilannetta eikä saatavuuslupauksia — tuotesivulle ohjaava guardrail on
 * system-promptin puolella (`rytkoset_theme_chat_get_system_prompt()`).
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_shop_products_context' ) ) {
	function rytkoset_theme_chat_get_shop_products_context() {
		if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'rytkoset_theme_is_membership_product' ) ) {
			return '';
		}

		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);

		/**
		 * Suodattaa promptiin liitettävien muiden verkkokaupan tuotteiden enimmäismäärän.
		 *
		 * @param int $max_products Tuotteiden enimmäismäärä.
		 */
		$limit = max( 1, (int) apply_filters( 'rytkoset_theme_chat_live_context_max_products', 20 ) );

		$lines = array();

		foreach ( (array) $ids as $product_id ) {
			if ( count( $lines ) >= $limit ) {
				break;
			}

			$product = wc_get_product( (int) $product_id );

			if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
				continue;
			}

			if ( rytkoset_theme_is_membership_product( $product ) ) {
				continue;
			}

			$name  = trim( (string) $product->get_name() );
			$price = rytkoset_theme_format_event_price_text( (string) $product->get_price() );

			if ( '' === $name || '' === $price ) {
				continue;
			}

			$lines[] = '- ' . $name . ': ' . $price . ' — ' . get_permalink( (int) $product_id );
		}

		return implode( "\n", $lines );
	}
}

/**
 * Kokoaa system-promptiin liitettävän ajantasaisen tietolohkon (#459, #471).
 *
 * Tulevat tapahtumat- ja Digilehdet-osiot kertovat eksplisiittisesti, jos
 * tapahtumia/digilehtiä ei ole (ettei malli keksi niitä tai päättele niitä
 * olemassa olevan pysyvän kontekstin yleisluontoisista maininnoista).
 * Jäsenyys- ja muut tuoteosiot jätetään kokonaan pois, jos tuotteita ei
 * löydy. Koko lohko katkaistaan suodatettavaan merkkirajaan.
 *
 * @return string Tyhjä, kun lohko on kytketty pois suodattimella.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_live_context' ) ) {
	function rytkoset_theme_chat_get_live_context() {
		if ( ! rytkoset_theme_chat_live_context_is_enabled() ) {
			return '';
		}

		$event_blocks = array();

		foreach ( rytkoset_theme_chat_get_upcoming_event_ids() as $event_id ) {
			$block = rytkoset_theme_chat_format_event_context( $event_id );

			if ( '' !== $block ) {
				$event_blocks[] = $block;
			}
		}

		$sections   = array();
		$sections[] = "Tulevat tapahtumat:\n" . ( ! empty( $event_blocks ) ? implode( "\n", $event_blocks ) : '- Ei tulevia tapahtumia tiedossa juuri nyt.' );

		$magazines  = rytkoset_theme_chat_get_digital_magazine_context();
		$sections[] = "Digilehdet:\n" . ( '' !== $magazines ? $magazines : '- Digilehtiä ei ole vielä julkaistu.' );

		$membership = rytkoset_theme_chat_get_membership_context();
		if ( '' !== $membership ) {
			$sections[] = "Jäsenyystuotteet verkkokaupassa:\n" . $membership;
		}

		$shop_products = rytkoset_theme_chat_get_shop_products_context();
		if ( '' !== $shop_products ) {
			$sections[] = "Muut verkkokaupan tuotteet:\n" . $shop_products;
		}

		/**
		 * Suodattaa ajantasaisen tietolohkon enimmäispituuden merkkeinä.
		 *
		 * @param int $max_length Merkkiraja.
		 */
		$max_length = (int) apply_filters( 'rytkoset_theme_chat_live_context_max_length', 4000 );

		return rytkoset_theme_chat_truncate( implode( "\n\n", $sections ), max( 1, $max_length ) );
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
		$limit = rytkoset_theme_chat_get_rate_limit();
		if ( rytkoset_theme_chat_register_rate_limit_hit( rytkoset_theme_chat_get_client_ip(), $limit ) ) {
			return new WP_Error(
				'rytkoset_chat_rate_limited',
				__( 'Liikaa viestejä lyhyessä ajassa. Yritä hetken kuluttua uudelleen.', 'rytkoset-theme' ),
				array( 'status' => 429 )
			);
		}

		// 4. Syötteen jäsennys ja rajat.
		$max_input   = rytkoset_theme_chat_get_max_input_length();
		$max_history = rytkoset_theme_chat_get_max_history();

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

		// Fiscal-year questions are answered from the verified rules page without
		// involving the language model. A missing or restricted source fails closed.
		$fiscal_year_result = rytkoset_theme_chat_get_fiscal_year_source_reply( $messages );
		if ( $fiscal_year_result['matched'] ) {
			if ( '' === $fiscal_year_result['reply'] ) {
				rytkoset_theme_chat_log_error( 'Verified fiscal-year source was unavailable.' );
				rytkoset_theme_chat_record_error_stat( 'direct_source_missing' );

				return rytkoset_theme_chat_upstream_error();
			}

			rytkoset_theme_chat_record_message_sent_stat();

			return new WP_REST_Response( rytkoset_theme_chat_build_response_body( $fiscal_year_result['reply'] ), 200 );
		}

		// 5. Payload: system-prompt + rajattu historia (+ sivun lukutyökalu, #501).
		$latest_user_message = rytkoset_theme_chat_get_latest_user_message( $messages );
		$concept_matched     = '' !== rytkoset_theme_chat_get_concept_source_path( $latest_user_message );
		$tool_enabled        = rytkoset_theme_chat_page_tool_is_enabled();
		$prefetched_source   = $tool_enabled ? rytkoset_theme_chat_get_prefetched_public_source( $messages ) : '';
		$tool_rounds_enabled = $tool_enabled && '' === $prefetched_source;
		$system_prompt       = rytkoset_theme_chat_get_system_prompt();

		// Known privacy and commerce concepts must never fall back to model
		// memory. If their expected public page or complete source section cannot
		// be verified, return a deterministic visitor-facing reply without an API
		// call. This also covers an intentionally disabled page tool.
		if ( $concept_matched && '' === $prefetched_source ) {
			rytkoset_theme_chat_record_message_sent_stat();

			return new WP_REST_Response(
				rytkoset_theme_chat_build_response_body( rytkoset_theme_chat_get_concept_source_fallback_reply() ),
				200
			);
		}

		// A named fact question with no verified public source must not be sent
		// through the fragile forced-tool path or answered from model memory.
		if ( $tool_enabled && '' === $prefetched_source && rytkoset_theme_chat_is_named_source_query( $messages ) ) {
			rytkoset_theme_chat_record_message_sent_stat();

			// The visitor gets the site's own search for the name terms we could
			// not verify, so a dead end still has one concrete next step.
			$search_phrase  = implode(
				' ',
				rytkoset_theme_chat_get_named_search_terms( rytkoset_theme_chat_get_latest_user_message( $messages ) )
			);
			$fallback_reply = rytkoset_theme_chat_is_photo_query( $latest_user_message )
				? rytkoset_theme_chat_get_photo_source_fallback_reply( $search_phrase )
				: rytkoset_theme_chat_get_named_source_fallback_reply( $search_phrase );

			return new WP_REST_Response(
				rytkoset_theme_chat_build_response_body( $fallback_reply ),
				200
			);
		}

		if ( '' !== $prefetched_source ) {
			$system_prompt .= "\n\n" . $prefetched_source;
		}

		$payload_messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
		);

		$payload_messages = array_merge( $payload_messages, $messages );

		$payload = array(
			'model'             => $config['model'],
			'messages'          => $payload_messages,
			'max_tokens'        => rytkoset_theme_chat_get_max_tokens(),
			'temperature'       => rytkoset_theme_chat_get_temperature(),
			'frequency_penalty' => rytkoset_theme_chat_get_frequency_penalty(),
		);
		$payload = rytkoset_theme_chat_maybe_add_prompt_cache_key(
			$payload,
			$config['endpoint'],
			$config['prompt_cache_key']
		);

		$max_rounds      = $tool_rounds_enabled ? rytkoset_theme_chat_get_page_tool_max_rounds() : 0;
		$force_page_tool = $tool_rounds_enabled && rytkoset_theme_chat_should_force_page_tool( $messages );

		if ( $tool_enabled ) {
			$payload['tools'] = array( rytkoset_theme_chat_get_page_tool_definition() );

			if ( '' !== $prefetched_source ) {
				// The source is already verified, so expose the normal tool schema for
				// prompt consistency but prohibit an unnecessary tool round.
				$payload['tool_choice'] = 'none';
			} elseif ( $force_page_tool ) {
				// Mistral's "any" mode requires an initial tool call. Restore the
				// default automatic choice after the first tool result.
				$payload['tool_choice'] = 'any';
			}
		}

		// 6.–7. Mistral-kutsu + työkalusilmukka (#501) ja virheenkäsittely — avainta
		// tai teknisiä yksityiskohtia ei vuodeta. Peruskysymys päättyy ensimmäiseen
		// kierrokseen (ei tool_calls-vastausta) täsmälleen kuten ennen työkalua.
		$rounds_used = 0;
		$body        = null;
		// Loop-invariant: the page read targets its excerpt at this question.
		$tool_query = rytkoset_theme_chat_get_latest_user_message( $messages );

		while ( true ) {
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

			if ( is_wp_error( $response ) ) {
				rytkoset_theme_chat_log_error( 'wp_remote_post: ' . $response->get_error_message() );
				rytkoset_theme_chat_record_error_stat( 'network' );
				return rytkoset_theme_chat_upstream_error();
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $status_code < 200 || $status_code >= 300 ) {
				rytkoset_theme_chat_log_error( 'Mistral HTTP ' . $status_code );
				rytkoset_theme_chat_record_error_stat( 'http_' . $status_code );
				return rytkoset_theme_chat_upstream_error();
			}

			if ( rytkoset_theme_chat_endpoint_is_mistral( $config['endpoint'] ) ) {
				$prompt_cache_usage = rytkoset_theme_chat_extract_prompt_cache_usage( $body );
				if ( null !== $prompt_cache_usage ) {
					rytkoset_theme_chat_record_prompt_cache_usage_stat( $prompt_cache_usage );
				}
			}

			$tool_calls = $tool_rounds_enabled ? rytkoset_theme_chat_extract_tool_calls( $body ) : array();

			if ( ! rytkoset_theme_chat_forced_tool_response_is_valid( $force_page_tool, $rounds_used, $tool_calls ) ) {
				// The model occasionally answers a forced page read with plain
				// text or a malformed call. Failing closed here turned a valid
				// question into a technical error for the visitor, so drop the
				// forcing and retry once on the ordinary automatic tool choice.
				// Named fact questions never reach this point: they are already
				// answered from a verified source or a safe deterministic reply.
				rytkoset_theme_chat_log_error( 'Forced page read returned no valid tool call; retrying without forcing.' );
				rytkoset_theme_chat_record_error_stat( 'forced_tool_missing' );

				$force_page_tool = false;
				unset( $payload['tool_choice'] );

				continue;
			}

			if ( empty( $tool_calls ) || $rounds_used >= $max_rounds ) {
				break;
			}

			++$rounds_used;

			// Assistentin tool-call-viesti takaisin keskusteluun sellaisenaan;
			// jokainen tool_call_id saa vastaavan tool-viestin (API vaatii sen).
			$payload['messages'][] = $body['choices'][0]['message'];

			foreach ( $tool_calls as $index => $tool_call ) {
				// Kulusuoja: sivusisältö luetaan enintään kolmelle kutsulle per kierros.
				if ( $index < 3 ) {
					$content = rytkoset_theme_chat_run_page_tool( $tool_call['name'], $tool_call['arguments'], $tool_query );
					rytkoset_theme_chat_record_tool_call_stat();
				} else {
					$content = 'Työkalukutsuja oli liikaa yhdellä kierroksella.';
				}

				$payload['messages'][] = array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call['id'],
					'name'         => $tool_call['name'],
					'content'      => $content,
				);
			}

			unset( $payload['tool_choice'] );

			if ( $rounds_used >= $max_rounds ) {
				// Viimeinen kierros: pakota tekstivastaus, ettei malli jää kutsumaan työkalua.
				$payload['tool_choice'] = 'none';
			}
		}

		// 8. Extract and validate the final model response before exposing it.
		$response_error = rytkoset_theme_chat_get_final_response_error_type( $body );
		if ( '' !== $response_error ) {
			if ( 'empty_reply' === $response_error ) {
				rytkoset_theme_chat_log_error( 'Empty or unexpected Mistral response.' );
			} elseif ( 'invalid_finish_reason' === $response_error ) {
				rytkoset_theme_chat_log_error( 'Mistral response rejected: finish_reason was not stop.' );
			} else {
				rytkoset_theme_chat_log_error( 'Mistral response rejected by the content validator.' );
			}

			rytkoset_theme_chat_record_error_stat( $response_error );

			return rytkoset_theme_chat_upstream_error();
		}

		$reply = rytkoset_theme_chat_extract_reply( $body );

		// 9. Onnistumislaskuri (#472) — ei sisältöä eikä IP:tä, vain määrä + ajankohta.
		rytkoset_theme_chat_record_message_sent_stat();

		return new WP_REST_Response( rytkoset_theme_chat_build_response_body( $reply ), 200 );
	}
}

/**
 * Builds the successful chat REST response body.
 *
 * The `ai_generated` flag is the machine-readable marking required by
 * Regulation (EU) 2024/1689 (AI Act) Article 50(2) for AI-generated content.
 * Every successful assistant reply is conservatively labelled in the API,
 * including deterministic source replies, so the established REST and DOM
 * contract stays uniform. The matching DOM marking (`data-ai-generated`) is
 * added in `assets/js/chat.js`. See docs/chat.md for the dated rationale.
 *
 * @param string $reply Assistant reply text.
 * @return array Response body.
 */
if ( ! function_exists( 'rytkoset_theme_chat_build_response_body' ) ) {
	function rytkoset_theme_chat_build_response_body( $reply ) {
		return array(
			'reply'        => $reply,
			'ai_generated' => true,
		);
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
 * Palauttaa chatin käyttötilastojen wp_options-avaimet (#472).
 *
 * Kevyet laskurit ylläpitäjän näkyvyyteen (lähetetyt viestit, rate limit
 * -osumat, Mistral-/upstream-virheet) — vain kokonaismäärä + viimeisin
 * ajankohta (virheillä myös viimeisin tyyppi). Ei koskaan raakaa
 * IP-osoitetta eikä viestisisältöä, sama periaate kuin rate limit
 * -transientissa (joka tallentaa vain MD5-tiivisteen).
 *
 * @return array{messages:string,rate_limit:string,error:string,tool_calls:string,prompt_cache:string}
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_stat_option_names' ) ) {
	function rytkoset_theme_chat_get_stat_option_names() {
		return array(
			'messages'     => 'rytkoset_chat_stat_messages',
			'rate_limit'   => 'rytkoset_chat_stat_rate_limit',
			'error'        => 'rytkoset_chat_stat_error',
			'tool_calls'   => 'rytkoset_chat_stat_tool_calls',
			'prompt_cache' => 'rytkoset_chat_stat_prompt_cache',
		);
	}
}

/**
 * Poimii kentän tallennetusta laskuriarvosta oletuksella (virheellinen tai
 * puuttuva arvo palauttaa oletuksen).
 *
 * @param mixed  $stat     Tallennettu arvo (odotetusti array).
 * @param string $key      Haettava kenttä.
 * @param mixed  $fallback Oletusarvo.
 * @return mixed
 */
if ( ! function_exists( 'rytkoset_theme_chat_stat_value' ) ) {
	function rytkoset_theme_chat_stat_value( $stat, $key, $fallback ) {
		return is_array( $stat ) && isset( $stat[ $key ] ) ? $stat[ $key ] : $fallback;
	}
}

/**
 * Palauttaa kasvatetun laskuriarvon (count+1, last_at=nyt) nykyisen arvon pohjalta.
 *
 * Puhdas funktio (testattava): ei kosketa wp_options-tauluun.
 *
 * @param mixed $stat Nykyinen tallennettu arvo (tai puuttuva/virheellinen).
 * @param int   $now  Nykyinen Unix-aikaleima.
 * @return array{count:int,last_at:int}
 */
if ( ! function_exists( 'rytkoset_theme_chat_bump_stat' ) ) {
	function rytkoset_theme_chat_bump_stat( $stat, $now ) {
		return array(
			'count'   => (int) rytkoset_theme_chat_stat_value( $stat, 'count', 0 ) + 1,
			'last_at' => (int) $now,
		);
	}
}

/**
 * Sama kuin rytkoset_theme_chat_bump_stat(), ja tallentaa lisäksi virhetyypin.
 *
 * @param mixed  $stat Nykyinen tallennettu arvo.
 * @param int    $now  Nykyinen Unix-aikaleima.
 * @param string $type Lyhyt virhetyypin tunniste (ei viestisisältöä eikä IP:tä).
 * @return array{count:int,last_at:int,last_type:string}
 */
if ( ! function_exists( 'rytkoset_theme_chat_bump_error_stat' ) ) {
	function rytkoset_theme_chat_bump_error_stat( $stat, $now, $type ) {
		$bumped              = rytkoset_theme_chat_bump_stat( $stat, $now );
		$bumped['last_type'] = (string) $type;

		return $bumped;
	}
}

/**
 * Poimii Mistralin vastauksesta prompt-välimuistin käyttöarvot.
 *
 * Vain ei-negatiiviset kokonaisluvut hyväksytään. Ristiriitainen vaste, jossa
 * välimuistitokeneita on enemmän kuin syötetokeneita, ohitetaan kokonaan, jotta
 * koontitilasto ei vääristy odottamattomasta tai toisen tarjoajan rakenteesta.
 *
 * @param mixed $body Purettu API-vastaus.
 * @return array{prompt_tokens:int,cached_tokens:int}|null
 */
if ( ! function_exists( 'rytkoset_theme_chat_extract_prompt_cache_usage' ) ) {
	function rytkoset_theme_chat_extract_prompt_cache_usage( $body ) {
		if (
			! is_array( $body )
			|| ! isset( $body['usage'] )
			|| ! is_array( $body['usage'] )
			|| ! isset( $body['usage']['prompt_tokens'] )
			|| ! is_int( $body['usage']['prompt_tokens'] )
			|| $body['usage']['prompt_tokens'] < 0
			|| ! isset( $body['usage']['prompt_tokens_details'] )
			|| ! is_array( $body['usage']['prompt_tokens_details'] )
			|| ! isset( $body['usage']['prompt_tokens_details']['cached_tokens'] )
			|| ! is_int( $body['usage']['prompt_tokens_details']['cached_tokens'] )
			|| $body['usage']['prompt_tokens_details']['cached_tokens'] < 0
			|| $body['usage']['prompt_tokens_details']['cached_tokens'] > $body['usage']['prompt_tokens']
		) {
			return null;
		}

		return array(
			'prompt_tokens' => $body['usage']['prompt_tokens'],
			'cached_tokens' => $body['usage']['prompt_tokens_details']['cached_tokens'],
		);
	}
}

/**
 * Kasvattaa prompt-välimuistin henkilötiedotonta koontia yhdellä API-kutsulla.
 *
 * @param mixed                                  $stat  Nykyinen tallennettu arvo.
 * @param array{prompt_tokens:int,cached_tokens:int} $usage API-kutsun käyttöarvot.
 * @param int                                    $now   Nykyinen Unix-aikaleima.
 * @return array{api_calls:int,cache_hit_calls:int,prompt_tokens:int,cached_tokens:int,last_prompt_tokens:int,last_cached_tokens:int,last_at:int}
 */
if ( ! function_exists( 'rytkoset_theme_chat_bump_prompt_cache_stat' ) ) {
	function rytkoset_theme_chat_bump_prompt_cache_stat( $stat, $usage, $now ) {
		$prompt_tokens = max( 0, (int) $usage['prompt_tokens'] );
		$cached_tokens = min( $prompt_tokens, max( 0, (int) $usage['cached_tokens'] ) );

		return array(
			'api_calls'          => (int) rytkoset_theme_chat_stat_value( $stat, 'api_calls', 0 ) + 1,
			'cache_hit_calls'    => (int) rytkoset_theme_chat_stat_value( $stat, 'cache_hit_calls', 0 ) + ( $cached_tokens > 0 ? 1 : 0 ),
			'prompt_tokens'      => (int) rytkoset_theme_chat_stat_value( $stat, 'prompt_tokens', 0 ) + $prompt_tokens,
			'cached_tokens'      => (int) rytkoset_theme_chat_stat_value( $stat, 'cached_tokens', 0 ) + $cached_tokens,
			'last_prompt_tokens' => $prompt_tokens,
			'last_cached_tokens' => $cached_tokens,
			'last_at'            => (int) $now,
		);
	}
}

/**
 * Kirjaa yhden onnistuneesti lähetetyn chat-viestin käyttötilastoon.
 *
 * Kutsutaan `rytkoset_theme_chat_handle_request()`:sta onnistuneen vastauksen
 * jälkeen. Ei tallenna viestin sisältöä eikä IP:tä.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_record_message_sent_stat' ) ) {
	function rytkoset_theme_chat_record_message_sent_stat() {
		$names = rytkoset_theme_chat_get_stat_option_names();

		update_option(
			$names['messages'],
			rytkoset_theme_chat_bump_stat( get_option( $names['messages'], array() ), time() ),
			false
		);
	}
}

/**
 * Kirjaa yhden suoritetun lue_sivu-työkalukutsun käyttötilastoon (#501).
 *
 * Kasvaa vain kutsuista, joille sivunluku oikeasti suoritettiin — ei
 * kierroskaton ylittäneistä, ohitetuista kutsuista. Viestimäärälaskuri
 * kasvaa edelleen vain kerran per käyttäjäpyyntö.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_record_tool_call_stat' ) ) {
	function rytkoset_theme_chat_record_tool_call_stat() {
		$names = rytkoset_theme_chat_get_stat_option_names();

		update_option(
			$names['tool_calls'],
			rytkoset_theme_chat_bump_stat( get_option( $names['tool_calls'], array() ), time() ),
			false
		);
	}
}

/**
 * Kirjaa yhden onnistuneen Mistral-kutsun tokenit prompt-välimuistikoontiin.
 *
 * Tietue sisältää vain tokenimääriä, osumakutsujen määrän ja aikaleiman — ei
 * viestejä, IP-osoitteita eikä prompt_cache_key-arvoa.
 *
 * @param array{prompt_tokens:int,cached_tokens:int} $usage API-kutsun käyttöarvot.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_record_prompt_cache_usage_stat' ) ) {
	function rytkoset_theme_chat_record_prompt_cache_usage_stat( $usage ) {
		$names = rytkoset_theme_chat_get_stat_option_names();

		update_option(
			$names['prompt_cache'],
			rytkoset_theme_chat_bump_prompt_cache_stat( get_option( $names['prompt_cache'], array() ), $usage, time() ),
			false
		);
	}
}

/**
 * Kirjaa yhden rate limit -osuman käyttötilastoon.
 *
 * Kutsutaan `rytkoset_theme_chat_register_rate_limit_hit()`:sta, kun raja on
 * ylittynyt (paluuarvo `true`).
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_record_rate_limit_hit_stat' ) ) {
	function rytkoset_theme_chat_record_rate_limit_hit_stat() {
		$names = rytkoset_theme_chat_get_stat_option_names();

		update_option(
			$names['rate_limit'],
			rytkoset_theme_chat_bump_stat( get_option( $names['rate_limit'], array() ), time() ),
			false
		);
	}
}

/**
 * Kirjaa yhden Mistral-/upstream-virheen käyttötilastoon.
 *
 * Kutsutaan samoista kohdista kuin `rytkoset_theme_chat_log_error()`, joka
 * säilyy ennallaan WP_DEBUG-lokitusta varten. Tyyppi on lyhyt, staattinen
 * tunniste (esim. "network", "http_502", "empty_reply") — ei koskaan
 * dynaamista virhesanomaa, joka voisi sisältää teknisiä yksityiskohtia.
 *
 * @param string $type Virhetyypin tunniste.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_record_error_stat' ) ) {
	function rytkoset_theme_chat_record_error_stat( $type ) {
		$names = rytkoset_theme_chat_get_stat_option_names();

		update_option(
			$names['error'],
			rytkoset_theme_chat_bump_error_stat( get_option( $names['error'], array() ), time(), $type ),
			false
		);
	}
}

/**
 * Palauttaa chatin käyttötilastot dashboard-widgetiä varten.
 *
 * @return array{
 *     messages_sent: array{count:int,last_at:int},
 *     rate_limit_hits: array{count:int,last_at:int},
 *     last_error: array{count:int,last_at:int,last_type:string},
 *     tool_calls: array{count:int,last_at:int},
 *     prompt_cache: array{api_calls:int,cache_hit_calls:int,prompt_tokens:int,cached_tokens:int,last_prompt_tokens:int,last_cached_tokens:int,last_at:int}
 * }
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_usage_stats' ) ) {
	function rytkoset_theme_chat_get_usage_stats() {
		$names = rytkoset_theme_chat_get_stat_option_names();

		$messages     = get_option( $names['messages'], array() );
		$rate_limit   = get_option( $names['rate_limit'], array() );
		$error        = get_option( $names['error'], array() );
		$tool_calls   = get_option( $names['tool_calls'], array() );
		$prompt_cache = get_option( $names['prompt_cache'], array() );

		return array(
			'messages_sent'   => array(
				'count'   => (int) rytkoset_theme_chat_stat_value( $messages, 'count', 0 ),
				'last_at' => (int) rytkoset_theme_chat_stat_value( $messages, 'last_at', 0 ),
			),
			'rate_limit_hits' => array(
				'count'   => (int) rytkoset_theme_chat_stat_value( $rate_limit, 'count', 0 ),
				'last_at' => (int) rytkoset_theme_chat_stat_value( $rate_limit, 'last_at', 0 ),
			),
			'last_error'      => array(
				'count'     => (int) rytkoset_theme_chat_stat_value( $error, 'count', 0 ),
				'last_at'   => (int) rytkoset_theme_chat_stat_value( $error, 'last_at', 0 ),
				'last_type' => (string) rytkoset_theme_chat_stat_value( $error, 'last_type', '' ),
			),
			'tool_calls'      => array(
				'count'   => (int) rytkoset_theme_chat_stat_value( $tool_calls, 'count', 0 ),
				'last_at' => (int) rytkoset_theme_chat_stat_value( $tool_calls, 'last_at', 0 ),
			),
			'prompt_cache'    => array(
				'api_calls'          => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'api_calls', 0 ),
				'cache_hit_calls'    => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'cache_hit_calls', 0 ),
				'prompt_tokens'      => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'prompt_tokens', 0 ),
				'cached_tokens'      => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'cached_tokens', 0 ),
				'last_prompt_tokens' => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'last_prompt_tokens', 0 ),
				'last_cached_tokens' => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'last_cached_tokens', 0 ),
				'last_at'            => (int) rytkoset_theme_chat_stat_value( $prompt_cache, 'last_at', 0 ),
			),
		);
	}
}

/**
 * Muotoilee tallennetun virhetyypin tunnisteen ihmisluettavaksi (dashboard-widget).
 *
 * @param string $type Tallennettu virhetyypin tunniste.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_error_type_label' ) ) {
	function rytkoset_theme_chat_get_error_type_label( $type ) {
		$type = (string) $type;

		if ( '' === $type ) {
			return '';
		}

		if ( 'network' === $type ) {
			return __( 'Yhteysvirhe Mistraliin', 'rytkoset-theme' );
		}

		if ( 'empty_reply' === $type ) {
			return __( 'Tyhjä vastaus Mistralilta', 'rytkoset-theme' );
		}

		if ( 'direct_source_missing' === $type ) {
			return __( 'Varmennettua tilikausilähdettä ei löytynyt', 'rytkoset-theme' );
		}

		if ( 'forced_tool_missing' === $type ) {
			return __( 'Pakotettu sivunluku epäonnistui', 'rytkoset-theme' );
		}

		if ( 'invalid_finish_reason' === $type ) {
			return __( 'Mistralin vastaus jäi kesken', 'rytkoset-theme' );
		}

		if ( 'invalid_reply' === $type ) {
			return __( 'Mistralin vastaus hylättiin', 'rytkoset-theme' );
		}

		if ( 0 === strpos( $type, 'http_' ) ) {
			return sprintf(
				/* translators: %s: HTTP-tilakoodi. */
				__( 'Mistral vastasi HTTP-virheellä %s', 'rytkoset-theme' ),
				substr( $type, 5 )
			);
		}

		return $type;
	}
}

/**
 * Rekisteröi wp-adminin Dashboard-widgetin tukichatin käyttötilastoille (#472).
 *
 * Vain manage_options-käyttäjille: sisältää operatiivista tietoa chatin
 * käytöstä ja mahdollisista virheistä, ei tarkoitettu kaikille kirjautuneille.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_register_dashboard_widget' ) ) {
	function rytkoset_theme_chat_register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'rytkoset_chat_stats',
			__( 'Tukichatti', 'rytkoset-theme' ),
			'rytkoset_theme_chat_render_dashboard_widget'
		);
	}
}
add_action( 'wp_dashboard_setup', 'rytkoset_theme_chat_register_dashboard_widget' );

/**
 * Tulostaa Dashboard-widgetin sisällön: chatin tila, lähetetyt viestit,
 * rate limit -osumat ja viimeisin Mistral-/upstream-virhe.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_render_dashboard_widget' ) ) {
	function rytkoset_theme_chat_render_dashboard_widget() {
		$config = rytkoset_theme_chat_get_config();

		if ( ! $config['is_configured'] ) {
			$status = __( 'Ei käytössä — API-avain puuttuu (wp-config.php).', 'rytkoset-theme' );
		} elseif ( ! rytkoset_theme_chat_admin_enabled() ) {
			$status = __( 'Pois päältä (Ulkoasu → Mukauta → Tukichatti).', 'rytkoset-theme' );
		} else {
			$status = __( 'Käytössä.', 'rytkoset-theme' );
		}

		$stats                = rytkoset_theme_chat_get_usage_stats();
		$prompt_cache_enabled = '' !== $config['prompt_cache_key'] && rytkoset_theme_chat_endpoint_is_mistral( $config['endpoint'] );

		echo '<p><strong>' . esc_html__( 'Tila:', 'rytkoset-theme' ) . '</strong> ' . esc_html( $status ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Mistral prompt-välimuisti:', 'rytkoset-theme' ) . '</strong> ' . esc_html( $prompt_cache_enabled ? __( 'Käytössä.', 'rytkoset-theme' ) : __( 'Pois käytöstä.', 'rytkoset-theme' ) ) . '</p>';

		echo '<ul>';

		echo '<li>' . esc_html__( 'Lähetettyjä viestejä yhteensä:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $stats['messages_sent']['count'] ) ) . '</strong>';
		if ( $stats['messages_sent']['last_at'] > 0 ) {
			echo ' &ndash; ' . esc_html__( 'viimeksi', 'rytkoset-theme' ) . ' ' . esc_html( wp_date( 'j.n.Y H:i', $stats['messages_sent']['last_at'] ) );
		}
		echo '</li>';

		echo '<li>' . esc_html__( 'Rate limit -osumia yhteensä:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $stats['rate_limit_hits']['count'] ) ) . '</strong>';
		if ( $stats['rate_limit_hits']['last_at'] > 0 ) {
			echo ' &ndash; ' . esc_html__( 'viimeksi', 'rytkoset-theme' ) . ' ' . esc_html( wp_date( 'j.n.Y H:i', $stats['rate_limit_hits']['last_at'] ) );
		}
		echo '</li>';

		echo '<li>' . esc_html__( 'Sivunlukuhakuja (lue_sivu-työkalu) yhteensä:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $stats['tool_calls']['count'] ) ) . '</strong>';
		if ( $stats['tool_calls']['last_at'] > 0 ) {
			echo ' &ndash; ' . esc_html__( 'viimeksi', 'rytkoset-theme' ) . ' ' . esc_html( wp_date( 'j.n.Y H:i', $stats['tool_calls']['last_at'] ) );
		}
		echo '</li>';

		echo '<li>' . esc_html__( 'Prompt-välimuistin API-kutsuja tokenitiedoin:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $stats['prompt_cache']['api_calls'] ) ) . '</strong>';
		if ( $stats['prompt_cache']['api_calls'] > 0 ) {
			echo ' &ndash; ' . esc_html(
				sprintf(
				/* translators: 1: cached prompt tokens, 2: all prompt tokens, 3: cache-hit API calls. */
					__( '%1$s / %2$s syötetokenia välimuistista, osumia %3$s kutsussa', 'rytkoset-theme' ),
					number_format_i18n( $stats['prompt_cache']['cached_tokens'] ),
					number_format_i18n( $stats['prompt_cache']['prompt_tokens'] ),
					number_format_i18n( $stats['prompt_cache']['cache_hit_calls'] )
				)
			);
			if ( $stats['prompt_cache']['last_at'] > 0 ) {
				echo '; ' . esc_html(
					sprintf(
						/* translators: 1: latest cached prompt tokens, 2: latest all prompt tokens, 3: timestamp. */
						__( 'viimeisin %1$s / %2$s (%3$s)', 'rytkoset-theme' ),
						number_format_i18n( $stats['prompt_cache']['last_cached_tokens'] ),
						number_format_i18n( $stats['prompt_cache']['last_prompt_tokens'] ),
						wp_date( 'j.n.Y H:i', $stats['prompt_cache']['last_at'] )
					)
				);
			}
		}
		echo '</li>';

		echo '<li>' . esc_html__( 'Mistral-/yhteysvirheitä yhteensä:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $stats['last_error']['count'] ) ) . '</strong>';
		if ( $stats['last_error']['last_at'] > 0 ) {
			echo ' &ndash; ' . esc_html__( 'viimeksi', 'rytkoset-theme' ) . ' ' . esc_html( wp_date( 'j.n.Y H:i', $stats['last_error']['last_at'] ) );
			echo ' (' . esc_html( rytkoset_theme_chat_get_error_type_label( $stats['last_error']['last_type'] ) ) . ')';
		}
		echo '</li>';

		echo '</ul>';
	}
}

/**
 * Cleans and limits conversation history before an API call.
 *
 * Keeps only user and assistant roles, rejects malformed assistant output,
 * sanitizes content, drops empty entries, truncates each message and returns
 * at most the latest `$max_history` entries.
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

			$raw_content = isset( $message['content'] ) ? (string) $message['content'] : '';
			if ( 'assistant' === $role && ! rytkoset_theme_chat_reply_is_valid( $raw_content ) ) {
				continue;
			}

			$content = sanitize_textarea_field( $raw_content );
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

			$exceeded = rytkoset_theme_chat_rate_limit_exceeded( 0, $limit );
			if ( $exceeded ) {
				rytkoset_theme_chat_record_rate_limit_hit_stat();
			}

			return $exceeded;
		}

		if ( rytkoset_theme_chat_rate_limit_exceeded( (int) $record['count'], $limit ) ) {
			rytkoset_theme_chat_record_rate_limit_hit_stat();
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
 * Checks a final assistant text for known malformed-output patterns.
 *
 * @param string $reply Assistant reply text.
 * @return bool True when the reply is safe to expose or retain in history.
 */
if ( ! function_exists( 'rytkoset_theme_chat_reply_is_valid' ) ) {
	function rytkoset_theme_chat_reply_is_valid( $reply ) {
		$reply  = (string) $reply;
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $reply ) : strlen( $reply );

		if ( '' === trim( $reply ) || $length > 3000 ) {
			return false;
		}

		$decoded_reply = wp_specialchars_decode( $reply, ENT_QUOTES );
		$html_pattern  = '/<\s*(?:!|\?|\/?\s*[a-z])[^>]*>/iu';

		// Reject raw or escaped HTML, including declarations and comments.
		if ( preg_match( $html_pattern, $reply ) || preg_match( $html_pattern, $decoded_reply ) ) {
			return false;
		}

		$control_pattern = '/<\|[^>\r\n]{1,120}\|>|\[\/?(?:INST|TOOL_CALLS?|TOOL_RESULTS?|AVAILABLE_TOOLS|PREFIX|MIDDLE|SUFFIX)\]/iu';

		if ( preg_match( $control_pattern, $reply ) || preg_match( $control_pattern, $decoded_reply ) ) {
			return false;
		}

		// Reject long character runs and short garbage motifs such as ]]]] or ()().
		$has_repeated_motif = preg_match( '/([\p{P}\p{S}]{2})\1{5,}/u', $reply )
			|| preg_match( '/([\p{P}\p{S}]{3})\1{3,}/u', $reply )
			|| preg_match( '/([\p{P}\p{S}]{4})\1{2,}/u', $reply );

		if ( preg_match( '/(\S)\1{11,}/u', $reply ) || $has_repeated_motif || preg_match( '/\S{201,}/u', $reply ) ) {
			return false;
		}

		preg_match_all( '/[\p{L}\p{N}]+/u', $reply, $word_matches );
		$words = $word_matches[0] ?? array();

		if ( count( $words ) < 8 ) {
			return true;
		}

		$shingles = array();
		$limit    = count( $words ) - 7;

		for ( $index = 0; $index < $limit; ++$index ) {
			$shingle_words = array_map(
				static function ( $word ) {
					return function_exists( 'mb_strtolower' ) ? mb_strtolower( $word ) : strtolower( $word );
				},
				array_slice( $words, $index, 8 )
			);

			$shingle = implode( ' ', $shingle_words );

			$shingles[ $shingle ] = isset( $shingles[ $shingle ] ) ? $shingles[ $shingle ] + 1 : 1;

			if ( $shingles[ $shingle ] >= 3 ) {
				return false;
			}
		}

		return true;
	}
}

/**
 * Returns the PII-free error type for a decoded final model response.
 *
 * @param mixed $body Decoded API response.
 * @return string Empty on success, otherwise a static error type.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_final_response_error_type' ) ) {
	function rytkoset_theme_chat_get_final_response_error_type( $body ) {
		$finish_reason = is_array( $body ) && isset( $body['choices'][0]['finish_reason'] )
			? $body['choices'][0]['finish_reason']
			: null;

		if ( ! is_string( $finish_reason ) || 'stop' !== $finish_reason ) {
			return 'invalid_finish_reason';
		}

		$reply = rytkoset_theme_chat_extract_reply( $body );

		if ( '' === $reply ) {
			return 'empty_reply';
		}

		return rytkoset_theme_chat_reply_is_valid( $reply ) ? '' : 'invalid_reply';
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
 * Kokoaa chatin pysyvän sivustokontekstin.
 *
 * Tämä ei korvaa FAQ:ta eikä automaattista tapahtuma-/tuotelohkoa. Tarkoitus on
 * lukita sivuston peruspolut ja vakioidut toimintalogiikat, joita mallin ei pidä
 * päätellä tai keksiä, jos ylläpitäjän FAQ ei mainitse niitä.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_stable_site_context' ) ) {
	function rytkoset_theme_chat_get_stable_site_context() {
		$lines = array(
			'Sivuston pysyvät polut ja perustoiminnot:',
			'- Verkkokauppa: /kauppa/. Ostoskori: /ostoskori/. Kassa: /kassa/. Oma tili: /oma-tili/. Tilaukset: /oma-tili/tilaukset/.',
			'- Tapahtumat: /tapahtumat/. Kuvat ja albumit: /albumit/. Foorumi: /foorumi/. Blogi: /blogi/. Digilehdet: /digilehdet/.',
			'- Kuvagalleria on osoitteessa /albumit/ — sivustolla ei ole /kuvat/- eikä /galleria/-osoitetta.',
			'- Osalla albumisivuista on upotettuja YouTube-videoita omassa Videot-osiossaan, ja tapahtumasivun sisältöön voidaan upottaa tallenteen video. Älä väitä, ettei sivustolla ole videoita — jos et tiedä onko tietyllä albumilla tai tapahtumalla videota, ohjaa albumi- tai tapahtumasivulle tarkistettavaksi.',
			'- Foorumi on käytössä sivustolla. Kirjautunut käyttäjä voi aloittaa keskustelun, jos hänellä on foorumin julkaisuoikeus. Älä väitä foorumia suljetuksi vain siksi, että viimeisin viesti on vanha.',
			'- Blogi näyttää julkaistut kirjoitukset. Blogikirjoituksia voi ehdottaa tai lähettää ylläpidolle sähköpostitse; julkaisu tehdään ylläpidon kautta. Älä väitä, ettei blogitekstejä oteta vastaan.',
			'- Blogikirjoituksissa ja albumeissa voi olla kommentointi käytössä; kommentit moderoidaan ennen julkaisua.',
			'- Digilehdet ovat sivuston HTML-sisältöä, eivät PDF-latauksia — mutta älä oleta niitä olevan jo julkaistu. Digilehtien ajantasainen julkaisutilanne (onko yhtään julkaistu, ja mitkä) kerrotaan ajantasaisen tietolohkon Digilehdet-osiossa; jos osio kertoo ettei digilehtiä ole vielä julkaistu, älä väitä toisin. Älä myöskään lupaa PDF-latauksia, ellei erillinen tietopohja niin sano.',
			'- Sosiaalisen median linkit ovat sivustolla: Facebook https://www.facebook.com/rytkoset, YouTube https://www.youtube.com/@rytkoset, Instagram https://www.instagram.com/rytkoset/ ja X https://x.com/rytkoset. Älä väitä, ettei some-tilejä ole.',
			'- Sukukirjaa voi lainata eri kirjastoista. Älä väitä, ettei sukukirjaa ole olemassa tai että uusi sukukirjatyö olisi käynnissä, ellei ajantasainen tietopohja niin kerro.',
			'- Rytkösten sukulainen nro 9 on julkaistu ja myynnissä verkkokaupassa: /kauppa/sukulehdet/rytkosten-sukulainen-nro-9/. Se on painettu lehti eikä digilehti — älä väitä sen olevan saatavilla digilehtenä, ellei ajantasainen tietolohko niin kerro. Ohjaa tuotteen sivulle ajantasaisen hinnan ja saatavuuden tarkistamiseksi.',
			'- Sukuseuran hallitus kerrotaan sivulla /sukuseura/sukuseuran-hallitus/. Hallituskausi on 2023-2026. Hallitukseen kuuluvat: Esa Rytkönen (jäsen, Espoo / Maaninka), Mikko Rytkönen (suvun esimies, Runni), Antti Rytkönen (puheenjohtaja, Tampere), Eeli Rytkönen (Kinnulanlahti / Maaninka), Eeva-Liisa Ryhänen (jäsen, Helsinki), Ilkka Rytkönen (jäsen / rytkoset.net ylläpitäjä, Kuopio), Juha Rytkönen (jäsen, Maavesi / Joroinen), Mauri Rytkönen (jäsen, Helsinki), Tapani Rytkönen (sihteeri, Pieksämäki) ja Kimmo Tuulenkari (jäsen, Kajaani). Kun kysytään hallituksesta, käytä vain tätä listaa äläkä lisää muita nimiä.',
			'- Maksuongelmissa ohjeista Oma tili -> Tilaukset vain ehdollisesti: jos tilauksella näkyy Maksa / yritä uudelleen -painike, maksua voi jatkaa ja valita kassalla toisen maksutavan; jos painiketta ei näy, ohjaa sähköpostiin.',
			'- Kaupan maksutavat: maksut välittää Paytrail, ja käytettävissä olevat maksutavat näkyvät vasta kassalla. Sivusto ei luettele yksittäisiä maksutapoja millään sivulla, joten älä luettele niitä sinäkään: älä mainitse pankki-, mobiili-, kortti-, lasku- tai osamaksua äläkä muutakaan yksittäistä maksutapaa. Älä myöskään päättele maksutapoja sivun muista sanoista — esimerkiksi Paytrailin vakiotekstin "näkyy maksun saajana tiliotteella tai korttilaskulla" ei ole luettelo maksutavoista. Toimitusehdot ja peruuttamisoikeus kerrotaan Maksu- ja toimitusehdot -sivulla.',
			'- Uutiskirjeen voi tilata sivuston alalaidan lomakkeella. Kirjautunut käyttäjä hallitsee tilaustaan kohdassa Oma tili -> Uutiskirje (/oma-tili/uutiskirje/), jossa tilauksen voi myös peruuttaa. Lisäksi jokaisen lähetetyn uutiskirjeen alalaidassa on peruutuslinkki. Älä siis ohjaa uutiskirjeen peruuttamista pelkästään sähköpostiin.',
			'- Sukuseurasta eroaminen: sivustolla ei kuvata vapaaehtoisen eroamisen menettelyä, joten älä kerro sellaista etkä päättele sitä. Sääntöjen kohta 5 käsittelee vain erottamista, josta päättää sukukokous. Eroamiseen ei ole mitään itsepalvelutoimintoa: Oma tililtä ei voi lopettaa omaa jäsenyyttä eikä kassalla ole jäsenyyden jatkumisen peruuttavaa valintaa. Oma tilin perhejäsenten poisto koskee vain perhejäsenrivejä, ei omaa jäsenyyttä. Jäsenkauden tai jäsenyyden voimassaolon päättyminen ei myöskään ole sama asia kuin eroaminen, joten älä väitä jäsenyyden päättyvän automaattisesti maksamatta jättämällä. Ohjaa eroamista koskevat kysymykset aina sähköpostitse yhdistykselle.',
			'- Tukichatista itsestään: chattiin kirjoitetut viestit välitetään vastauksen tuottamista varten tekoälypalveluun EU-alueelle, ja vastauksen pohjaksi lähetetään sivuston julkaistua julkista sisältöä. Keskusteluja ei tallenneta palvelimelle vaan ne säilyvät selaimen istuntomuistissa, eikä chatti käytä evästeitä. Kävijän IP-osoitetta käsitellään lyhytaikaisesti viestimäärän rajoittamiseksi. Älä siis väitä, ettei chatti käsittele lainkaan henkilötietoja, äläkä anna yleistä turvallisuustakuuta. Kehota olemaan kirjoittamatta arkaluonteisia tietoja ja ohjaa tarkemmat tietosuojakysymykset tietosuojaselosteeseen.',
		);

		/**
		 * Suodattaa chatin pysyvän sivustokontekstin.
		 *
		 * @param string $context Rivinvaihdoilla koottu konteksti.
		 * @param array  $lines   Kontekstin yksittäiset rivit.
		 */
		return (string) apply_filters(
			'rytkoset_theme_chat_stable_site_context',
			implode( "\n", $lines ),
			$lines
		);
	}
}

/**
 * Lisää uniikin termin sivustokartan hakuvihjelistaan.
 *
 * @param array<int,string>  $terms Hakuvihjeet.
 * @param array<string,bool> $seen  Jo lisätyt termit pienaakkosina.
 * @param string             $term  Lisättävä termi.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_chat_add_sitemap_hint_term' ) ) {
	function rytkoset_theme_chat_add_sitemap_hint_term( &$terms, &$seen, $term ) {
		$term = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( wp_specialchars_decode( (string) $term, ENT_QUOTES ) ) ) );

		if ( '' === $term || mb_strlen( $term ) < 4 ) {
			return;
		}

		$key = mb_strtolower( $term );

		if ( isset( $seen[ $key ] ) ) {
			return;
		}

		$seen[ $key ] = true;
		$terms[]      = $term;
	}
}

/**
 * Joins sitemap hints within a character limit without truncating a term.
 *
 * @param array<int,string> $terms      Hint terms in priority order.
 * @param int               $max_length Character limit.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_join_sitemap_hint_terms' ) ) {
	function rytkoset_theme_chat_join_sitemap_hint_terms( $terms, $max_length ) {
		$max_length = max( 1, (int) $max_length );
		$output     = '';

		foreach ( $terms as $term ) {
			$term      = (string) $term;
			$candidate = '' === $output ? $term : $output . ', ' . $term;

			if ( mb_strlen( $candidate ) > $max_length ) {
				continue;
			}

			$output = $candidate;
		}

		return $output;
	}
}

/**
 * Kertoo, voiko sivustokarttaan lisätä sivun sisältöön perustuvia hakuvihjeitä.
 *
 * Hakuvihjeet auttavat mallia valitsemaan oikean sivun lue_sivu-työkalulle,
 * joten niitä lisätään vain julkisille sivuille. Jäsensivuille ei lisätä
 * sisältövihjeitä, ettei sivustokartasta synny rajatun sisällön vuotoreittiä.
 *
 * @param WP_Post $post Sivupostaus.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_can_include_sitemap_hints' ) ) {
	function rytkoset_theme_chat_can_include_sitemap_hints( $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== get_post_status( $post ) ) {
			return false;
		}

		if ( '' !== trim( (string) $post->post_password ) ) {
			return false;
		}

		if ( ! function_exists( 'rytkoset_theme_page_is_members_only' ) ) {
			return false;
		}

		return ! rytkoset_theme_page_is_members_only( $post );
	}
}

/**
 * Kokoaa sivun otsikoista ja erisnimistä lyhyet hakuvihjeet sivustokarttaan.
 *
 * Vihjeet eivät ole vastauslähde; ne vain auttavat mallia valitsemaan, mikä
 * julkinen sivu kannattaa lukea työkalulla, kun sivun otsikko on liian yleinen.
 *
 * @param WP_Post $post Sivupostaus.
 * @return string Hakuvihjeet pilkuilla eroteltuna.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_sitemap_page_hints' ) ) {
	function rytkoset_theme_chat_get_sitemap_page_hints( $post ) {
		if ( ! rytkoset_theme_chat_can_include_sitemap_hints( $post ) ) {
			return '';
		}

		$terms = array();
		$seen  = array();
		$html  = (string) $post->post_content;

		if ( preg_match_all( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $html, $heading_matches ) ) {
			foreach ( $heading_matches[1] as $heading ) {
				rytkoset_theme_chat_add_sitemap_hint_term( $terms, $seen, $heading );
			}
		}

		$text = rytkoset_theme_chat_extract_page_text( $html );

		$capitalized_word = '[A-ZÅÄÖ][\p{L}]{2,}(?:-[A-ZÅÄÖ][\p{L}]{2,})?';

		// Prioritize names preceded by a profession, title, or authorship verb so
		// people near the end of a long publication list remain within the limit.
		$attribution = '(?:(?:diplomi-insinööri|insinööri|rovasti|pastori|maisteri|puheenjohtaja|sihteeri|esimies)[\p{Ll}-]*|toimitti|kokosi|kirjoitti|laati|selvitti)';
		if ( preg_match_all( '/\b' . $attribution . '(?:\s+[\p{Ll}-]+){0,2}\s+(' . $capitalized_word . '\s+' . $capitalized_word . ')\b/u', $text, $person_matches ) ) {
			foreach ( $person_matches[1] as $person ) {
				rytkoset_theme_chat_add_sitemap_hint_term( $terms, $seen, $person );
			}
		}

		// A short rare name can occur alone within a sentence (for example,
		// Rodhger), so standalone proper names precede generic phrases.
		if ( preg_match_all( '/\b' . $capitalized_word . '\b/u', $text, $word_matches ) ) {
			foreach ( $word_matches[0] as $word ) {
				rytkoset_theme_chat_add_sitemap_hint_term( $terms, $seen, $word );
			}
		}

		if ( preg_match_all( '/\b' . $capitalized_word . '(?:\s+' . $capitalized_word . '){1,3}\b/u', $text, $phrase_matches ) ) {
			foreach ( $phrase_matches[0] as $phrase ) {
				rytkoset_theme_chat_add_sitemap_hint_term( $terms, $seen, $phrase );
			}
		}

		return rytkoset_theme_chat_join_sitemap_hint_terms( $terms, 360 );
	}
}

/**
 * Kokoaa sivustokartan chatin system-promptiin.
 *
 * Listaa julkaistut sivut sekä tapahtuma- ja albumiarkistot otsikkoineen ja
 * osoitteineen suoraan WordPressistä, jotta malli viittaa vain oikeisiin
 * osoitteisiin eikä keksi polkuja (tuotannossa havaittu keksitty /kuvat/).
 * Fail-safe: tyhjä merkkijono, jos sivuja ei löydy tai lohko on kytketty pois.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_sitemap_context' ) ) {
	function rytkoset_theme_chat_get_sitemap_context() {
		/**
		 * Suodattaa sivustokarttalohkon käytön (koko lohkon voi kytkeä pois).
		 *
		 * @param bool $enabled Liitetäänkö sivustokartta system-promptiin.
		 */
		if ( ! apply_filters( 'rytkoset_theme_chat_sitemap_enabled', true ) ) {
			return '';
		}

		$lines = array();

		// CPT-arkistot, joilla ei ole omaa WordPress-sivua.
		$archives = array(
			'rytkoset_event' => 'Tapahtumat (arkisto)',
			'gallery_album'  => 'Kuvat ja albumit (arkisto)',
		);

		foreach ( $archives as $post_type => $label ) {
			$archive_url = get_post_type_archive_link( $post_type );

			if ( is_string( $archive_url ) && '' !== $archive_url ) {
				$lines[] = '- ' . $label . ': ' . $archive_url;
			}
		}

		// Sivu-id:t vain kun lue_sivu-työkalu on käytössä (#501) — muuten lohko pysyy entisellään.
		$include_ids = rytkoset_theme_chat_page_tool_is_enabled();

		/**
		 * Suodattaa sivustokarttaan listattavien tapahtumien enimmäismäärän.
		 *
		 * @param int $max_events Tapahtumien enimmäismäärä.
		 */
		$max_events = max( 0, (int) apply_filters( 'rytkoset_theme_chat_sitemap_max_events', 20 ) );

		// Tapahtumat listataan ennen sivuja, koska lohkon merkkiraja katkaisee
		// lopun: tapahtumia on vähän, ja niiden ohjelma-, aikataulu- ja
		// tarjoilutiedot ovat vain tapahtuman omalla sivulla.
		$event_ids = $max_events > 0
			? (array) get_posts(
				array(
					'post_type'   => 'rytkoset_event',
					'post_status' => 'publish',
					'numberposts' => $max_events,
					'fields'      => 'ids',
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			)
			: array();

		foreach ( $event_ids as $event_id ) {
			$event = rytkoset_theme_chat_get_public_source_post( (int) $event_id, array( 'rytkoset_event' ) );

			if ( ! $event instanceof WP_Post ) {
				continue;
			}

			$title = trim( (string) get_the_title( $event->ID ) );
			$url   = get_permalink( $event->ID );

			if ( '' === $title || ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$line = '- ' . $title . ' (tapahtuma): ' . $url;

			if ( $include_ids ) {
				$line .= ' (sivu-id: ' . $event->ID . ')';
			}

			$lines[] = $line;
		}

		/**
		 * Suodattaa sivustokarttaan listattavien sivujen enimmäismäärän.
		 *
		 * @param int $max_pages Sivujen enimmäismäärä.
		 */
		$max_pages = max( 1, (int) apply_filters( 'rytkoset_theme_chat_sitemap_max_pages', 60 ) );

		$page_ids = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);

		$page_count = 0;

		foreach ( (array) $page_ids as $page_id ) {
			if ( $page_count >= $max_pages ) {
				break;
			}

			$page_id = (int) $page_id;

			if ( 'publish' !== get_post_status( $page_id ) ) {
				continue;
			}

			$title = trim( (string) get_the_title( $page_id ) );
			$url   = get_permalink( $page_id );

			if ( '' === $title || ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$line = '- ' . $title . ': ' . $url;

			if ( $include_ids ) {
				$line .= ' (sivu-id: ' . $page_id . ')';

				$hints = rytkoset_theme_chat_get_sitemap_page_hints( get_post( $page_id ) );
				if ( '' !== $hints ) {
					$line .= ' — aiheita: ' . $hints;
				}
			}

			$lines[] = $line;
			++$page_count;
		}

		/**
		 * Suodattaa sivustokarttaan listattavien albumien enimmäismäärän.
		 *
		 * @param int $max_albums Albumien enimmäismäärä.
		 */
		$max_albums = max( 0, (int) apply_filters( 'rytkoset_theme_chat_sitemap_max_albums', 15 ) );

		// Albumit viimeisenä: sivut ovat ydintietoa, eikä merkkirajan katkaisu saa
		// syödä niitä. Jos albumirivit katkeavat, palvelimen lähde-esihaku löytää
		// albumin silti — se ei käytä sivustokarttaa.
		$album_ids = $max_albums > 0
			? (array) get_posts(
				array(
					'post_type'   => 'gallery_album',
					'post_status' => 'publish',
					'numberposts' => $max_albums,
					'fields'      => 'ids',
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			)
			: array();

		foreach ( $album_ids as $album_id ) {
			$album = rytkoset_theme_chat_get_public_source_post( (int) $album_id, array( 'gallery_album' ) );

			if ( ! $album instanceof WP_Post ) {
				continue;
			}

			$title = trim( (string) get_the_title( $album->ID ) );
			$url   = get_permalink( $album->ID );

			if ( '' === $title || ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$line = '- ' . $title . ' (albumi): ' . $url;

			if ( $include_ids ) {
				$line .= ' (sivu-id: ' . $album->ID . ')';
			}

			$lines[] = $line;
		}

		if ( empty( $lines ) ) {
			return '';
		}

		/**
		 * Suodattaa sivustokarttalohkon enimmäispituuden merkkeinä.
		 *
		 * @param int $max_length Merkkiraja.
		 */
		$max_length = (int) apply_filters( 'rytkoset_theme_chat_sitemap_max_length', 6000 );

		return rytkoset_theme_chat_truncate( implode( "\n", $lines ), max( 1, $max_length ) );
	}
}

/**
 * Returns the post types the lue_sivu tool and the sitemap may expose.
 *
 * Event bodies carry the programme, schedule and catering details that no page
 * duplicates, so they must be selectable and readable. Each type keeps its own
 * access gate in rytkoset_theme_chat_get_public_source_post(): pages run the
 * full members-only check, events must be published and passwordless.
 *
 * @return array<int,string>
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_post_types' ) ) {
	function rytkoset_theme_chat_get_page_tool_post_types() {
		return array( 'page', 'rytkoset_event', 'gallery_album' );
	}
}

/**
 * Kertoo, onko sivun lukutyökalu (#501) käytössä.
 *
 * Kun työkalu on pois päältä, API-payload ja system-prompt ovat täsmälleen
 * entisellään (ei `tools`-kenttää, ei sivu-id-merkintöjä sivustokartassa).
 *
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_page_tool_is_enabled' ) ) {
	function rytkoset_theme_chat_page_tool_is_enabled() {
		/**
		 * Suodattaa sivun lukutyökalun käytön (#501).
		 *
		 * @param bool $enabled Annetaanko mallille lue_sivu-työkalu.
		 */
		return (bool) apply_filters( 'rytkoset_theme_chat_page_tool_enabled', true );
	}
}

/**
 * Palauttaa työkalun palauttaman sivusisällön merkkirajan (#501, kulusuoja).
 *
 * Raja pysyy 5000 merkissä, mutta se käytetään nyt eri tavalla: ylirajan
 * menevästä sivusta lähetetään kysymykseen pisteytetty ote sivun alun sijaan
 * (`rytkoset_theme_chat_get_page_tool_excerpt()`). Sivuston keskeisimmät
 * viitesivut ovat rajaa selvästi pidempiä — tietosuojaseloste noin 17 000 ja
 * maksu- ja toimitusehdot noin 9 800 merkkiä — joten vanha alkukatkaisu jätti
 * vastauksen säännönmukaisesti pois. Rajan nostoa kokeiltiin, mutta se ei ole
 * oikea korjaus: 8000 merkin hyötykuorma ylitti 20 sekunnin HTTP-timeoutin
 * `mistral-medium-latest`-mallilla, ja pisteytetty ote löytää saman vastauksen
 * jo noin 3000 merkillä.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_max_length' ) ) {
	function rytkoset_theme_chat_get_page_tool_max_length() {
		/**
		 * Suodattaa työkalun palauttaman sivusisällön enimmäispituuden merkkeinä.
		 *
		 * @param int $max_length Merkkiraja.
		 */
		$max_length = (int) apply_filters( 'rytkoset_theme_chat_page_tool_max_length', 5000 );

		return max( 1, $max_length );
	}
}

/**
 * Palauttaa työkalukierrosten enimmäismäärän per käyttäjäviesti (#501, kulusuoja).
 *
 * Yksi kierros = yksi ylimääräinen API-kutsu työkalutulosten kanssa. Kova
 * yläraja 3 sitoo kustannuksen, vaikka suodatin palauttaisi suuremman arvon.
 *
 * Oletus 2 (ei 1): devin savutestissä yhden kierroksen raja pakotti mallin
 * vastaamaan sillä yhdellä sivulla, jonka se ensin arvasi sivustokartan
 * otsikoista — jos arvaus osui väärään sivuun (esim. henkilön nimi, joka ei
 * ole ilmeisesti minkään otsikon aiheena), mallilla ei ollut mahdollisuutta
 * kokeilla toista sivua ennen kuin `tool_choice: none` pakotti tekstivastauksen.
 * Kaksi kierrosta antaa mallille yhden uudelleenyrityksen.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_max_rounds' ) ) {
	function rytkoset_theme_chat_get_page_tool_max_rounds() {
		/**
		 * Suodattaa työkalukierrosten enimmäismäärän.
		 *
		 * @param int $max_rounds Kierrosten enimmäismäärä.
		 */
		$max_rounds = (int) apply_filters( 'rytkoset_theme_chat_page_tool_max_rounds', 2 );

		return min( 3, max( 1, $max_rounds ) );
	}
}

/**
 * Palauttaa lue_sivu-työkalun määrittelyn Mistralin chat-completions-kutsuun (#501).
 *
 * Kuvaus ohjaa mallia käyttämään työkalua säästeliäästi: peruskysymykset
 * vastataan suoraan promptin lähteistä ilman toista API-kutsua.
 *
 * @return array<string,mixed>
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_definition' ) ) {
	function rytkoset_theme_chat_get_page_tool_definition() {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'lue_sivu',
				'description' => 'Palauttaa sivuston julkaistun julkisen sivun tekstisisällön sivustokartassa annetulla sivu-id:llä. Kutsu tätä aina ennen kuin vastaat, ettet tiedä: valitse sivustokartasta otsikoltaan sopivin sivu ja lue sen sisältö. Älä kutsu, kun vastaus on jo annetuissa lähteissä.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'sivu_id' => array(
							'type'        => 'integer',
							'description' => 'Sivun id sivustokartan "(sivu-id: N)" -merkinnästä.',
						),
					),
					'required'   => array( 'sivu_id' ),
				),
			),
		);
	}
}

/**
 * Returns the latest prepared user message.
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return string Latest user message or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_latest_user_message' ) ) {
	function rytkoset_theme_chat_get_latest_user_message( $messages ) {
		foreach ( array_reverse( (array) $messages ) as $message ) {
			if ( is_array( $message ) && 'user' === ( $message['role'] ?? '' ) ) {
				return trim( (string) ( $message['content'] ?? '' ) );
			}
		}

		return '';
	}
}

/**
 * Extracts proper-name terms from a named fact question.
 *
 * @param string $message Latest user message.
 * @return array<int,string> Search terms in question order.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_named_search_terms' ) ) {
	function rytkoset_theme_chat_get_named_search_terms( $message ) {
		if ( ! preg_match_all( '/\b[A-ZÅÄÖ][\p{Ll}]{2,}(?:-[A-ZÅÄÖ][\p{Ll}]{2,})?\b/u', (string) $message, $matches ) ) {
			return array();
		}

		// Sentence-initial question, command and generic photo words are
		// capitalized like proper names. None of these collide with a Finnish
		// personal or place name, so ignoring them cannot drop a real search term.
		$ignored = array(
			'albumi',
			'albumia',
			'albumissa',
			'entä',
			'kenen',
			'kerro',
			'ketä',
			'keitä',
			'keistä',
			'ketkä',
			'kuka',
			'kuva',
			'kuvat',
			'kuvia',
			// Sentence-initial relation verbs ("Liittyykö X ...") are captured like
			// proper names but never name a person or place.
			'liittyi',
			'liittyikö',
			'liittyivät',
			'liittyy',
			'liittyykö',
			'listaa',
			'löytyykö',
			'luettele',
			'mikä',
			'milloin',
			'missä',
			'mistä',
			'miten',
			'mitä',
			'näytä',
			'onko',
			'rytkösten',
			'saako',
			'sukuseuran',
			'voiko',
			'voinko',
			'valokuva',
			'valokuvia',
		);
		$terms   = array();

		foreach ( $matches[0] as $term ) {
			if ( in_array( mb_strtolower( $term ), $ignored, true ) ) {
				continue;
			}

			$terms[] = $term;
		}

		return array_slice( array_values( array_unique( $terms ) ), 0, 4 );
	}
}

/**
 * Extracts proper-name terms only when the message asks about a person.
 *
 * @param string $message Latest user message.
 * @return array<int,string> Person-name terms in question order.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_person_search_terms' ) ) {
	function rytkoset_theme_chat_get_person_search_terms( $message ) {
		// "kuka tahansa" / "kuka vain" is an indefinite pronoun, never a person
		// question, so it must not route a general question to the person path.
		if ( ! preg_match( '/\b(?:kuka|kenen|ketä|kerro)\b(?!\s+(?:tahansa|vain)\b)/ui', (string) $message ) ) {
			return array();
		}

		return rytkoset_theme_chat_get_named_search_terms( $message );
	}
}

/**
 * Extracts long title words from a publication question for disambiguation.
 *
 * @param string $message Latest user message.
 * @return array<int,string> Significant publication-title terms.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_publication_search_terms' ) ) {
	function rytkoset_theme_chat_get_publication_search_terms( $message ) {
		// Only a genuine book/work reference may trigger the publication source
		// path. "kirja"/"sukukirja"/"teos" and their inflections qualify, but
		// several "kirj"-stem words do not and would otherwise bind an answer to
		// the wrong verified source: "kirjasto" (borrowing, not a title),
		// "kirjoittaa"/"kirjoita" (a writing verb), "kirjaudun" (a login verb)
		// and "kirje" (a letter). The optional "suku" prefix lets the compound
		// "sukukirja" match without letting an unrelated compound such as
		// "asiakirja" (document) match, since the leading word boundary still
		// applies before the whole match.
		if ( ! preg_match( '/\b(?:(?:suku)?kirj(?!asto|oitt|aud|e)[\p{L}-]*|teos[\p{L}-]*)\b/ui', (string) $message ) ) {
			return array();
		}

		if ( ! preg_match_all( '/\b[\p{L}][\p{L}-]{4,}\b/u', (string) $message, $matches ) ) {
			return array();
		}

		$ignored = array( 'kirja', 'kirjaa', 'kirjan', 'ostaa', 'teoksen', 'teosta', 'voinko' );
		$terms   = array();

		foreach ( $matches[0] as $term ) {
			if ( in_array( mb_strtolower( $term ), $ignored, true ) ) {
				continue;
			}

			$terms[] = $term;
		}

		return array_slice( array_values( array_unique( $terms ) ), 0, 6 );
	}
}

/**
 * Checks whether the message explicitly asks about photos or an album.
 *
 * The bounded kuva forms intentionally exclude unrelated words such as
 * "kuvittaja" and "kuvaus". Valokuva and album are safe word stems.
 *
 * @param string $message Latest user message.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_photo_query' ) ) {
	function rytkoset_theme_chat_is_photo_query( $message ) {
		return (bool) preg_match(
			'/\b(?:kuv(?:a|aa|an|assa|asta|at|ia|ien|iin|issa|ista|illa|ilta|ille)|valokuv[\p{L}-]*|album[\p{L}-]*)\b/ui',
			(string) $message
		);
	}
}

/**
 * Checks whether a question or source line concerns a family meeting or party.
 *
 * Visitors use sukukokous and sukujuhla interchangeably when asking about the
 * same event. Both must use the verified place-and-event source path so an
 * unrelated meeting mention cannot answer a party question.
 *
 * @param string $message Question or source line.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_meeting_query' ) ) {
	function rytkoset_theme_chat_is_meeting_query( $message ) {
		return (bool) preg_match( '/\b(?:sukukokou[\p{L}-]*|sukujuhl[\p{L}-]*)\b/ui', (string) $message );
	}
}

/**
 * Checks whether a question asks how a named person relates to the association.
 *
 * Patterns like "Liittyykö X ...", "Miten X liittyy ..." and "Mikä on X:n yhteys
 * ..." are person questions even without a "kuka" cue. They must reach the
 * verified source path so an upcoming speaker or performer named on an event
 * page resolves to that event instead of being missed or hallucinated onto an
 * unrelated album. Only the third-person "relates" forms match: the first-person
 * "liityn"/"liittyä" (how do I join) is answered from the stable membership
 * context and must not be treated as a person question.
 *
 * @param string $message Latest user message.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_person_relation_query' ) ) {
	function rytkoset_theme_chat_is_person_relation_query( $message ) {
		if ( ! preg_match( '/\bliittyy(?:kö)?\b|\bliittyi(?:vät|kö)?\b|\byhteys\b|\byhteydess\p{L}*/ui', (string) $message ) ) {
			return false;
		}

		return ! empty( rytkoset_theme_chat_get_named_search_terms( $message ) );
	}
}

/**
 * Checks whether a question names a public-source subject that must be verified.
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_named_source_query' ) ) {
	function rytkoset_theme_chat_is_named_source_query( $messages ) {
		$message = rytkoset_theme_chat_get_latest_user_message( $messages );

		if ( '' === $message ) {
			return false;
		}

		if ( preg_match( '/\b(?:hallitu[\p{L}-]*|puheenjohtaj[\p{L}-]*)\b/ui', $message ) ) {
			return true;
		}

		if ( rytkoset_theme_chat_is_person_relation_query( $message ) ) {
			return true;
		}

		if ( ! empty( rytkoset_theme_chat_get_person_search_terms( $message ) ) ) {
			return true;
		}

		if ( ! empty( rytkoset_theme_chat_get_publication_search_terms( $message ) ) ) {
			return true;
		}

		if ( rytkoset_theme_chat_is_photo_query( $message ) ) {
			return ! empty( rytkoset_theme_chat_get_named_search_terms( $message ) );
		}

		return rytkoset_theme_chat_is_meeting_query( $message )
			&& ! empty( rytkoset_theme_chat_get_named_search_terms( $message ) );
	}
}

/**
 * Builds the site's own search URL for a search phrase.
 *
 * WordPress search reaches content the chat cannot read as a source, so it is a
 * useful last step for the visitor. An empty phrase returns an empty string so
 * no useless bare search link is ever offered.
 *
 * @param string $phrase Search phrase.
 * @return string Search URL, or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_site_search_url' ) ) {
	function rytkoset_theme_chat_get_site_search_url( $phrase ) {
		$phrase = trim( (string) $phrase );

		if ( '' === $phrase ) {
			return '';
		}

		$home = rtrim( (string) home_url(), '/' );

		return $home . '/?s=' . rawurlencode( $phrase );
	}
}

/**
 * Returns a safe deterministic answer when no named public source was verified.
 *
 * @param string $search_phrase Optional phrase for a site-search suggestion.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_named_source_fallback_reply' ) ) {
	function rytkoset_theme_chat_get_named_source_fallback_reply( $search_phrase = '' ) {
		$reply      = 'En löytänyt tähän varmennettua vastausta chatin käytettävissä olevista Rytkösten sukuseuran julkaistuista julkisista lähteistä. Voin auttaa Rytkösten sukuseuraan liittyvissä kysymyksissä.';
		$search_url = rytkoset_theme_chat_get_site_search_url( $search_phrase );

		if ( '' !== $search_url ) {
			$reply .= "\n\nVoit myös kokeilla sivuston omaa hakua: " . $search_url;
		}

		return $reply;
	}
}

/**
 * Returns a deterministic answer when no matching public album was verified.
 *
 * The wording describes only the sources searched by the chat. It does not
 * claim that private or unpublished photos cannot exist.
 *
 * @param string $search_phrase Optional phrase for a site-search suggestion.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_photo_source_fallback_reply' ) ) {
	function rytkoset_theme_chat_get_photo_source_fallback_reply( $search_phrase = '' ) {
		$reply      = 'En löytänyt kysytystä tilaisuudesta julkaistua kuva-albumia chatin käytettävissä olevista Rytkösten sukuseuran julkisista lähteistä.';
		$search_url = rytkoset_theme_chat_get_site_search_url( $search_phrase );

		if ( '' !== $search_url ) {
			$reply .= "\n\nVoit myös kokeilla sivuston omaa hakua: " . $search_url;
		}

		return $reply;
	}
}

/**
 * Checks whether plain page text contains a complete Unicode word.
 *
 * @param string $text Page text.
 * @param string $term Search term.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_text_contains_term' ) ) {
	function rytkoset_theme_chat_text_contains_term( $text, $term ) {
		$term = trim( (string) $term );

		if ( '' === $term ) {
			return false;
		}

		return (bool) preg_match( '/(?<!\p{L})' . preg_quote( $term, '/' ) . '(?!\p{L})/ui', (string) $text );
	}
}

/**
 * Checks whether plain text contains a word starting with a bounded term stem.
 *
 * @param string $text       Plain text.
 * @param string $term       Inflected search term.
 * @param int    $stem_length Maximum stem length.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_text_contains_term_stem' ) ) {
	function rytkoset_theme_chat_text_contains_term_stem( $text, $term, $stem_length = 5 ) {
		$term = trim( (string) $term );

		if ( mb_strlen( $term ) < 4 ) {
			return rytkoset_theme_chat_text_contains_term( $text, $term );
		}

		$stem = mb_substr( $term, 0, max( 4, (int) $stem_length ) );

		return (bool) preg_match( '/(?<!\p{L})' . preg_quote( $stem, '/' ) . '[\p{L}-]*(?!\p{L})/ui', (string) $text );
	}
}

/**
 * Requires a meeting topic and a place stem within the same text line.
 *
 * @param string            $text  Plain source text.
 * @param array<int,string> $terms Place terms from the question.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_text_has_meeting_place_context' ) ) {
	function rytkoset_theme_chat_text_has_meeting_place_context( $text, $terms ) {
		$lines = preg_split( '/\R/u', (string) $text );

		if ( ! is_array( $lines ) ) {
			return false;
		}

		foreach ( $lines as $line ) {
			if ( ! rytkoset_theme_chat_is_meeting_query( $line ) ) {
				continue;
			}

			foreach ( (array) $terms as $term ) {
				if ( rytkoset_theme_chat_text_contains_term_stem( $line, $term ) ) {
					return true;
				}
			}
		}

		return false;
	}
}

/**
 * Extracts the matching lines and their immediate context from a page.
 *
 * @param string            $text       Plain page text.
 * @param array<int,string> $terms      Search terms.
 * @param int               $max_length Maximum excerpt length.
 * @param bool              $stem_match Whether terms are matched by a short word stem.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_matching_page_excerpt' ) ) {
	function rytkoset_theme_chat_get_matching_page_excerpt( $text, $terms, $max_length, $stem_match = false ) {
		$lines = preg_split( '/\R/u', (string) $text );

		if ( ! is_array( $lines ) ) {
			return '';
		}

		$selected = array();

		foreach ( $lines as $index => $line ) {
			$matches = false;

			foreach ( (array) $terms as $term ) {
				$term_matches = $stem_match
					? rytkoset_theme_chat_text_contains_term_stem( $line, $term )
					: rytkoset_theme_chat_text_contains_term( $line, $term );

				if ( $term_matches ) {
					$matches = true;
					break;
				}
			}

			if ( ! $matches ) {
				continue;
			}

			foreach ( range( max( 0, $index - 1 ), min( count( $lines ) - 1, $index + 2 ) ) as $line_index ) {
				$selected[ $line_index ] = trim( $lines[ $line_index ] );
			}
		}

		if ( empty( $selected ) ) {
			return '';
		}

		ksort( $selected );

		return rytkoset_theme_chat_truncate( trim( implode( "\n", array_filter( $selected ) ) ), max( 1, (int) $max_length ) );
	}
}

/**
 * Checks whether a source set is usable, i.e. unambiguous enough to send.
 *
 * Person and publication questions require exactly one source. A meeting may
 * legitimately have both an event page and its directly related transport page.
 *
 * @param int  $count         Number of candidate sources.
 * @param bool $meeting_query Whether the question is a meeting question.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_prefetch_candidates_are_usable' ) ) {
	function rytkoset_theme_chat_prefetch_candidates_are_usable( $count, $meeting_query ) {
		$count = (int) $count;

		if ( 1 > $count ) {
			return false;
		}

		return $meeting_query ? 3 > $count : 1 === $count;
	}
}

/**
 * Checks whether a search term is the association's own family surname.
 *
 * "Rytkönen" and its inflections (Rytköset, Rytkösiä, Rytkösen, …) appear on
 * nearly every published source, so a match on the family name alone is not a
 * distinctive verification and must not select a source — otherwise a generic
 * question such as "Mistä Rytkönen-nimi on peräisin?" binds to the lone album
 * merely because the page tier is ambiguous. Rytkölä-style place names
 * (Rytkö + l…) are intentionally not matched: they are distinctive.
 *
 * @param string $term Search term.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_term_is_family_name' ) ) {
	function rytkoset_theme_chat_term_is_family_name( $term ) {
		return (bool) preg_match( '/^rytkö(?:nen|s)/ui', trim( (string) $term ) );
	}
}

/**
 * Returns a bounded candidate ID list with WordPress search matches first.
 *
 * The relevance search lets an exact name match enter the candidate set even
 * when it would fall outside the alphabetically browsed source limit. The
 * existing catalogue query remains as a fallback for Finnish inflections that
 * WordPress search may not match. Callers must still verify publication access
 * and the actual terms from the source text before using any returned post.
 *
 * @param string            $source_type Public source post type.
 * @param array<int,string> $search_terms Verified terms from the user message.
 * @param int               $max_results Maximum number of candidate IDs.
 * @return array<int,int>
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_prefetch_candidate_ids' ) ) {
	function rytkoset_theme_chat_get_prefetch_candidate_ids( $source_type, $search_terms, $max_results ) {
		$source_type  = (string) $source_type;
		$max_results  = max( 1, (int) $max_results );
		$search_terms = array_values(
			array_filter(
				array_unique(
					array_map(
						static function ( $term ) {
							return trim( (string) $term );
						},
						(array) $search_terms
					)
				)
			)
		);
		$search       = trim( implode( ' ', $search_terms ) );
		$search_ids   = array();

		if ( '' !== $search ) {
			$search_ids = (array) get_posts(
				array(
					'post_type'   => $source_type,
					'post_status' => 'publish',
					'numberposts' => $max_results,
					'fields'      => 'ids',
					's'           => $search,
					'orderby'     => 'relevance',
					'order'       => 'DESC',
				)
			);

			// Core search has no Finnish stemming: "Antti Heikkinen" does not
			// match source text containing "Antti Heikkisen". If the complete
			// name found nothing, seed candidates with its bounded name parts.
			// The caller still requires an unambiguous source and independently
			// scores the actual text, so a first-name hit alone is never trusted.
			if ( empty( $search_ids ) && count( $search_terms ) > 1 ) {
				foreach ( $search_terms as $search_term ) {
					$search_ids = array_merge(
						$search_ids,
						(array) get_posts(
							array(
								'post_type'   => $source_type,
								'post_status' => 'publish',
								'numberposts' => $max_results,
								'fields'      => 'ids',
								's'           => $search_term,
								'orderby'     => 'relevance',
								'order'       => 'DESC',
							)
						)
					);
				}
			}
		}

		$catalogue_ids = (array) get_posts(
			array(
				'post_type'   => $source_type,
				'post_status' => 'publish',
				'numberposts' => $max_results,
				'fields'      => 'ids',
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);

		return array_slice(
			array_values(
				array_unique(
					array_map( 'intval', array_merge( $search_ids, $catalogue_ids ) )
				)
			),
			0,
			$max_results
		);
	}
}

/**
 * Wraps one or more verified source blocks in the shared prefetch instruction.
 *
 * The wrapper binds the answer to the given excerpts and their own URL and
 * forbids generalizing their dates, years and places. Both the named-source
 * prefetch and the concept prefetch (#614) build their context through it, so
 * the model sees one consistent contract.
 *
 * @param array<int,string> $source_blocks Verified "Sivu/Lähde/excerpt" blocks.
 * @return string Full source context, or an empty string when no blocks.
 */
if ( ! function_exists( 'rytkoset_theme_chat_build_prefetched_source_context' ) ) {
	function rytkoset_theme_chat_build_prefetched_source_context( $source_blocks ) {
		$source_blocks = array_values( array_filter( array_map( 'strval', (array) $source_blocks ) ) );

		if ( empty( $source_blocks ) ) {
			return '';
		}

		return "Palvelin on lukenut ja varmistanut seuraavat julkiset lähteet. Vastaa vain uusimpaan käyttäjäkysymykseen näiden lähdeotteiden perusteella. Älä vastaa samalla aiempiin vastaamatta jääneisiin kysymyksiin. Kysymys \"Kuka on Nimi?\" ei vaadi täydellistä elämäkertaa: lähteessä mainittu ammatti, rooli, puhe, esitelmä, esiintyminen tai muu teko on riittävä vastaus. Kysymys \"Miten Nimi liittyy sukuseuraan?\" tai \"Mikä on Nimen yhteys sukuseuraan?\" kysyy lähteessä näkyvää yhteyttä eikä välttämättä jäsenyyttä tai virallista tehtävää. Kerro lähteessä mainittu puhe, esitelmä, esiintyminen, kirjallinen työ, muu osallistuminen tai teko henkilön yhteytenä sukuseuraan. Älä päättele jäsenyyttä tai virallista asemaa ilman lähteen tietoa, mutta älä kiellä henkilön yhteyttä sukuseuraan, jos ote osoittaa tällaisen yhteyden. Aiempi vastaus tai kieltäytyminen ei saa ohittaa uusimman lähdeotteen tietoa. Kerro kysytystä henkilöstä tai asiasta kaikki otteessa annetut tiedot suoraan. Jos kysytty nimi esiintyy otteessa, vastaus \"en löytänyt tietoa\" on väärä ja kielletty. Vain jos otteet eivät sisällä kysytystä henkilöstä tai asiasta mitään vastaavaa tietoa, kerro ettet löytänyt vastausta. Lisää vastaukseen käyttämäsi lähteen osoite täsmälleen tässä annetussa muodossa — älä käytä sivustokartan tai muun lähteen osoitetta, vaikka aihe vaikuttaisi liittyvän toiseen sivuun. Toista otteen päivämäärät, vuosiluvut, paikat ja muut täsmälliset tiedot sellaisenaan äläkä korvaa niitä yleistyksellä kuten \"muun muassa\".\n\n"
			. implode( "\n\n---\n\n", $source_blocks );
	}
}

/**
 * Maps a concept question to the path of the public page that answers it.
 *
 * Payment/delivery terms, the privacy statement and the genealogy register
 * description each live on one long published page. Resolving the concept to a
 * known page lets the server verify and excerpt that exact page itself, so the
 * question is answered in one ordinary completion instead of the slow,
 * timeout-prone forced tool round these concepts used to take (#614; see the
 * latency measurement in docs/chat.md).
 *
 * @param string $message Latest user message.
 * @return string Page path for get_page_by_path(), or '' when no concept matched.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_concept_source_path' ) ) {
	function rytkoset_theme_chat_get_concept_source_path( $message ) {
		$message = (string) $message;

		if ( '' === trim( $message ) ) {
			return '';
		}

		// Checked before the privacy statement: the genealogy register
		// description is its own page and the word contains "-seloste".
		if ( preg_match( '/\brekisteriselost[\p{L}-]*\b/ui', $message ) ) {
			return 'sukuseura/rekisteriseloste';
		}

		// Payment, delivery, withdrawal-right and order-cancellation questions.
		if (
			preg_match( '/\b(?:maksutap[\p{L}-]*|toimitusehd[\p{L}-]*|peruuttamisoike[\p{L}-]*)\b/ui', $message )
			|| rytkoset_theme_chat_is_order_cancellation_query( $message )
		) {
			return 'kauppa/maksu-ja-toimitusehdot';
		}

		// Data-protection questions, including the data subject's own data in any
		// case form ("Missä maissa tietojani käsitellään?"). Requiring a
		// possessive suffix keeps ordinary "tietoa" / "tiedot" questions out.
		if (
			preg_match( '/\b(?:tietosuoj[\p{L}-]*|henkilötie[\p{L}-]*|eväste[\p{L}-]*)\b/ui', $message )
			|| preg_match( '/\b(?:tieto|tiedo)[\p{L}]{0,10}(?:ni|si|mme|nne)\b/ui', $message )
		) {
			return 'tietosuoja';
		}

		return '';
	}
}

/**
 * Checks whether a question asks how to cancel a store order.
 *
 * Requiring both a cancellation verb and an order/purchase term keeps
 * newsletter cancellation and association resignation on their own paths.
 *
 * @param string $message Latest user message.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_order_cancellation_query' ) ) {
	function rytkoset_theme_chat_is_order_cancellation_query( $message ) {
		return (bool) preg_match( '/\b(?:peru(?:a|n|t|u|mme|tte|vat)|peruut[\p{L}-]*)\b/ui', (string) $message )
			&& (bool) preg_match( '/\b(?:ost[\p{L}-]*|tilau[\p{L}-]*)\b/ui', (string) $message );
	}
}

/**
 * Returns the complete heading sections required for a known concept question.
 *
 * These headings mirror the maintained public pages. A narrow sub-concept gets
 * one authoritative section; a broad question gets a bounded set of complete
 * sections. The source builder fails closed unless every expected heading
 * exists and fits, so an editor rename cannot silently send an incomplete or
 * unrelated page fragment.
 *
 * @param string $message Latest user message.
 * @param string $path    Known concept page path.
 * @return array<int,string> Heading titles in source order.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_concept_source_headings' ) ) {
	function rytkoset_theme_chat_get_concept_source_headings( $message, $path ) {
		$message = (string) $message;
		$path    = (string) $path;

		if ( 'kauppa/maksu-ja-toimitusehdot' === $path ) {
			if ( preg_match( '/\bmaksutap[\p{L}-]*\b/ui', $message ) ) {
				return array( 'Maksutavat', 'Maksupalvelutarjoaja' );
			}

			if (
				preg_match( '/\bperuuttamisoike[\p{L}-]*\b/ui', $message )
				|| rytkoset_theme_chat_is_order_cancellation_query( $message )
			) {
				return array( 'Digitaaliset tuotteet', 'Tapahtumamaksut', 'Peruuttaminen ja palautukset' );
			}

			return array(
				'Tuotteet ja hinnat',
				'Maksutavat',
				'Maksupalvelutarjoaja',
				'Toimitus',
				'Digitaaliset tuotteet',
				'Jäsenmaksut',
				'Tapahtumamaksut',
				'Peruuttaminen ja palautukset',
			);
		}

		if ( 'tietosuoja' === $path ) {
			if ( preg_match( '/\beväste[\p{L}-]*\b/ui', $message ) ) {
				return array( 'Evästeet' );
			}

			if ( preg_match( '/\b(?:maissa|maihin|maiden|siir[\p{L}-]*|lähet[\p{L}-]*|ulkopuol[\p{L}-]*|EU|ETA)\b/ui', $message ) ) {
				return array( 'Kenelle jaamme tietojasi', 'Mihin lähetämme tietosi' );
			}

			if ( preg_match( '/\b(?:säily[\p{L}-]*|kauanko)\b/ui', $message ) ) {
				return array( 'Kuinka kauan säilytämme tietoja' );
			}

			if ( preg_match( '/\b(?:poist[\p{L}-]*|oikeu[\p{L}-]*|oikais[\p{L}-]*|rajoit[\p{L}-]*|vastust[\p{L}-]*|suostum[\p{L}-]*)\b/ui', $message ) ) {
				return array( 'Mitä oikeuksia sinulla on tietoihisi' );
			}

			if ( preg_match( '/\b(?:chat[\p{L}-]*|tekoäly[\p{L}-]*)\b/ui', $message ) ) {
				return array( 'AI-tukichatti', 'Mihin lähetämme tietosi' );
			}

			return array(
				'Käsittelyn oikeusperusteet',
				'Kenelle jaamme tietojasi',
				'Mitä oikeuksia sinulla on tietoihisi',
				'Mihin lähetämme tietosi',
			);
		}

		if ( 'sukuseura/rekisteriseloste' === $path ) {
			if ( preg_match( '/\b(?:siir[\p{L}-]*|EU|ETA|ulkopuol[\p{L}-]*)\b/ui', $message ) ) {
				return array( 'Tietojen siirto EU/ETA-alueen ulkopuolelle' );
			}

			return array(
				'Rekisterin tarkoitus',
				'Rekisterin tietosisältö',
				'Rekisterin säilytys ja käyttöoikeudet',
				'Rekisteröidyn oikeudet',
				'Tietojen siirto EU/ETA-alueen ulkopuolelle',
			);
		}

		return array();
	}
}

/**
 * Normalizes a heading for exact comparisons across punctuation and spacing.
 *
 * @param string $heading Heading text.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_normalize_source_heading' ) ) {
	function rytkoset_theme_chat_normalize_source_heading( $heading ) {
		$heading = mb_strtolower( wp_specialchars_decode( wp_strip_all_tags( (string) $heading ), ENT_QUOTES ) );
		$heading = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $heading );

		return trim( (string) $heading );
	}
}

/**
 * Extracts complete HTML heading sections, including nested subheadings.
 *
 * A section starts at one heading and ends before the next heading of the same
 * or a higher level. This keeps a whole h2 section together with any h3 content
 * beneath it while still allowing a specific h3 subsection to be selected.
 *
 * @param string $html Page HTML.
 * @return array<int,array{heading:string,text:string}>
 */
if ( ! function_exists( 'rytkoset_theme_chat_extract_heading_sections' ) ) {
	function rytkoset_theme_chat_extract_heading_sections( $html ) {
		$html = (string) $html;

		if ( ! preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$sections = array();
		$count    = count( $matches[0] );

		for ( $index = 0; $index < $count; ++$index ) {
			$level = (int) $matches[1][ $index ][0];
			$start = (int) $matches[0][ $index ][1];
			$end   = strlen( $html );

			for ( $next = $index + 1; $next < $count; ++$next ) {
				if ( (int) $matches[1][ $next ][0] <= $level ) {
					$end = (int) $matches[0][ $next ][1];
					break;
				}
			}

			$heading = rytkoset_theme_chat_extract_page_text( (string) $matches[2][ $index ][0] );
			$text    = rytkoset_theme_chat_extract_page_text( substr( $html, $start, $end - $start ) );

			if ( '' !== $heading && '' !== $text ) {
				$sections[] = array(
					'heading' => $heading,
					'text'    => $text,
				);
			}
		}

		return $sections;
	}
}

/**
 * Selects complete expected heading sections within a hard character budget.
 *
 * @param string            $html             Page HTML.
 * @param array<int,string> $expected_headings Expected exact heading titles.
 * @param int               $max_length       Character budget.
 * @return string Complete sections in the requested order, or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_concept_heading_sections' ) ) {
	function rytkoset_theme_chat_get_concept_heading_sections( $html, $expected_headings, $max_length ) {
		$available         = rytkoset_theme_chat_extract_heading_sections( $html );
		$expected_headings = array_values( array_unique( array_map( 'strval', (array) $expected_headings ) ) );
		$max_length        = max( 1, (int) $max_length );
		$selected          = array();
		$length            = 0;

		foreach ( (array) $expected_headings as $expected_heading ) {
			$normalized_expected = rytkoset_theme_chat_normalize_source_heading( $expected_heading );

			foreach ( $available as $section ) {
				if ( rytkoset_theme_chat_normalize_source_heading( $section['heading'] ) !== $normalized_expected ) {
					continue;
				}

				$addition = mb_strlen( $section['text'] ) + ( empty( $selected ) ? 0 : 2 );
				if ( $max_length < $length + $addition ) {
					return '';
				}

				$selected[] = $section['text'];
				$length    += $addition;

				break;
			}
		}

		if ( count( $selected ) !== count( $expected_headings ) ) {
			return '';
		}

		return implode( "\n\n", $selected );
	}
}

/**
 * Returns the notice attached to selected complete source sections.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_concept_source_notice' ) ) {
	function rytkoset_theme_chat_get_concept_source_notice() {
		return '(Lähdeote sisältää kysymykseen valitut kokonaiset otsikko-osiot, ei koko sivua. Jos vastausta ei ole näissä osioissa, kerro ettet löytänyt sitä äläkä päättele sitä itse. Kohdista jokainen ehto ja poikkeus vain siihen tahoon tai tuoteryhmään, jota lähdevirke koskee. Kun lähde käsittelee kysyttyä asiaa erikseen usealle taholle tai tuoteryhmälle, mainitse ne kaikki.)';
	}
}

/**
 * Builds a verified, complete-section source context for a concept question.
 *
 * The page goes through the same access gate as every other prefetch source, so
 * members-only, draft or password-protected content never travels. Known page
 * headings select whole relevant sections, including their nested subheadings.
 * An unavailable page, a missing expected heading or an over-budget section
 * returns an empty string for the deterministic request-handler fallback.
 *
 * @param string $message Latest user message.
 * @return string Verified source context, or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_concept_source' ) ) {
	function rytkoset_theme_chat_get_concept_source( $message ) {
		$path = rytkoset_theme_chat_get_concept_source_path( $message );

		if ( '' === $path || ! function_exists( 'get_page_by_path' ) ) {
			return '';
		}

		$resolved = get_page_by_path( $path );
		$page     = $resolved instanceof WP_Post ? rytkoset_theme_chat_get_public_page( $resolved->ID ) : null;

		if ( ! $page instanceof WP_Post ) {
			return '';
		}

		$url = get_permalink( $page->ID );

		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		$head = 'Sivu: ' . trim( (string) get_the_title( $page->ID ) ) . "\n"
			. 'Lähde: ' . trim( $url );

		// Concept pages have stable maintained headings. Send complete relevant
		// sections instead of independently scored lines, because a partial legal
		// or privacy section can lose a condition or exception and invert the
		// answer. The page-tool budget remains the hard cost cap.
		$max_length  = rytkoset_theme_chat_get_page_tool_max_length();
		$notice      = rytkoset_theme_chat_get_concept_source_notice();
		$instruction = rytkoset_theme_chat_is_order_cancellation_query( $message )
			? '(Tilauksen peruuttamista koskevassa vastauksessa kerro sekä käyttäjätilillä tehdyn että ilman käyttäjätiliä tehdyn tilauksen itsepalvelupolku. Mainitse myös maksamattoman peruuttamiskelpoisen tilauksen välitön peruutus ja muiden pyyntöjen manuaalinen käsittely. Älä väitä, että painike tai linkki näkyy aina.)'
			: '';
		$budget      = max( 400, $max_length - mb_strlen( $head ) - mb_strlen( $notice ) - mb_strlen( $instruction ) - 6 );
		$headings    = rytkoset_theme_chat_get_concept_source_headings( $message, $path );
		$excerpt     = rytkoset_theme_chat_get_concept_heading_sections( (string) $page->post_content, $headings, $budget );

		if ( '' === trim( $excerpt ) ) {
			return '';
		}

		$source = $head . "\n\n" . $excerpt . "\n\n" . $notice;

		if ( '' !== $instruction ) {
			$source .= "\n\n" . $instruction;
		}

		return rytkoset_theme_chat_build_prefetched_source_context( array( $source ) );
	}
}

/**
 * Returns the deterministic reply for a missing known concept source.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_concept_source_fallback_reply' ) ) {
	function rytkoset_theme_chat_get_concept_source_fallback_reply() {
		return sprintf(
			/* translators: %s: Association contact email address. */
			__( 'En pystynyt varmistamaan vastausta, koska tarvittavaa julkaistua julkista lähdesivua tai sen vastaavaa osiota ei löytynyt. Varmista asia sähköpostitse: %s.', 'rytkoset-theme' ),
			rytkoset_theme_get_contact_email()
		);
	}
}

/**
 * Builds a bounded, access-checked source context for named fact questions.
 *
 * This avoids a fragile forced function-call round when WordPress can resolve
 * the public source itself. Ambiguous or unavailable matches return an empty
 * context and keep the existing fail-closed tool path.
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return string Verified source context, or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_prefetched_public_source' ) ) {
	function rytkoset_theme_chat_get_prefetched_public_source( $messages ) {
		$message = rytkoset_theme_chat_get_latest_user_message( $messages );

		// Concept questions (payment/delivery terms, privacy statement, register
		// description) resolve to one known long page; try that verified page
		// first so they skip the slow, timeout-prone forced tool round (#614).
		$concept_path = rytkoset_theme_chat_get_concept_source_path( $message );
		if ( '' !== $concept_path ) {
			return rytkoset_theme_chat_get_concept_source( $message );
		}

		$event_catering_source = rytkoset_theme_chat_get_event_catering_source( $messages );
		if ( '' !== $event_catering_source ) {
			return $event_catering_source;
		}

		$board_query           = (bool) preg_match( '/\b(?:hallitu[\p{L}-]*|puheenjohtaj[\p{L}-]*)\b/ui', $message );
		$photo_query           = rytkoset_theme_chat_is_photo_query( $message );
		$meeting_query         = ! $photo_query && rytkoset_theme_chat_is_meeting_query( $message );
		$person_relation_query = ! $photo_query && ! $meeting_query && rytkoset_theme_chat_is_person_relation_query( $message );
		$person_terms          = rytkoset_theme_chat_get_person_search_terms( $message );
		$search_terms          = ! empty( $person_terms ) ? $person_terms : rytkoset_theme_chat_get_named_search_terms( $message );
		$publication_terms     = rytkoset_theme_chat_get_publication_search_terms( $message );

		if ( ! empty( $publication_terms ) ) {
			$search_terms = array_values(
				array_unique(
					array_merge( $search_terms, $publication_terms )
				)
			);
		}

		if ( ( $photo_query || ! $board_query ) && empty( $search_terms ) ) {
			return '';
		}

		$candidates = array();
		$board_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( 'sukuseura/sukuseuran-hallitus' ) : null;
		$board_page = $board_page instanceof WP_Post ? rytkoset_theme_chat_get_public_page( $board_page->ID ) : null;

		if ( ! $photo_query && $board_page instanceof WP_Post ) {
			$board_text     = rytkoset_theme_chat_extract_page_text( (string) $board_page->post_content );
			$board_has_name = ! empty( $person_terms );

			foreach ( $person_terms as $term ) {
				if ( ! rytkoset_theme_chat_text_contains_term( $board_text, $term ) ) {
					$board_has_name = false;
					break;
				}
			}

			if ( $board_query || $board_has_name ) {
				$candidates[] = array(
					'post'  => $board_page,
					'text'  => $board_text,
					// Without a verified name on the page there is no line to
					// excerpt around, and a board question may carry stray terms
					// that appear nowhere on it. The verified membership list is
					// short, so send the whole page instead of failing closed.
					'whole' => ! $board_has_name,
				);
			}
		}

		if ( empty( $candidates ) && ! empty( $search_terms ) ) {
			$max_pages = min( 100, max( 1, (int) apply_filters( 'rytkoset_theme_chat_prefetch_max_pages', 60 ) ) );

			// A page mentioning an occasion proves only that it happened, not that
			// photos exist. Explicit photo questions therefore accept only a
			// matching published album. For other questions, pages (and events for
			// a meeting) stay authoritative and albums remain the fallback tier.
			if ( $photo_query ) {
				$source_tiers = array( array( 'gallery_album' ) );
			} elseif ( $meeting_query ) {
				$source_tiers = array( array( 'rytkoset_event', 'page' ), array( 'gallery_album' ) );
			} elseif ( $person_relation_query ) {
				// A person may be named on an event page as an upcoming speaker or
				// performer, not only on ordinary pages and albums. Search events
				// alongside pages so e.g. a Tampere 2026 performer resolves to the
				// verified event source instead of being missed or hallucinated
				// onto an unrelated album.
				$source_tiers = array( array( 'page', 'rytkoset_event' ), array( 'gallery_album' ) );
			} else {
				$source_tiers = array( array( 'page' ), array( 'gallery_album' ) );
			}

			foreach ( $source_tiers as $source_types ) {
				$page_ids = array();

				foreach ( $source_types as $source_type ) {
					$page_ids = array_merge(
						$page_ids,
						rytkoset_theme_chat_get_prefetch_candidate_ids( $source_type, $search_terms, $max_pages )
					);
				}

				$page_ids   = array_slice( array_values( array_unique( array_map( 'intval', $page_ids ) ) ), 0, $max_pages );
				$best_score = 0;

				foreach ( (array) $page_ids as $page_id ) {
					$page = rytkoset_theme_chat_get_public_source_post( (int) $page_id, $source_types );

					if ( ! $page instanceof WP_Post ) {
						continue;
					}

					$text        = trim( (string) get_the_title( $page->ID ) . "\n" . rytkoset_theme_chat_extract_page_text( (string) $page->post_content ) );
					$score       = 0;
					$distinctive = 0;

					if ( $meeting_query && ! rytkoset_theme_chat_text_has_meeting_place_context( $text, $search_terms ) ) {
						continue;
					}

					foreach ( $search_terms as $term ) {
						// Finnish inflects surnames ("Heikkinen" -> "Heikkisen"), so a
						// relation question must stem-match its name terms too, or the
						// full-name match loses to a common first name and turns
						// ambiguous.
						$matches = $meeting_query || $photo_query || $person_relation_query
							? rytkoset_theme_chat_text_contains_term_stem( $text, $term )
							: rytkoset_theme_chat_text_contains_term( $text, $term );

						if ( $matches ) {
							++$score;

							if ( ! rytkoset_theme_chat_term_is_family_name( $term ) ) {
								++$distinctive;
							}
						}
					}

					// A match on the ubiquitous family surname alone is not a
					// distinctive verification (it appears on nearly every source), so
					// it must not select one — this stops a generic surname question
					// from binding to the lone album fallback.
					if ( 0 === $score || 0 === $distinctive || $score < $best_score ) {
						continue;
					}

					if ( $score > $best_score ) {
						$candidates = array();
						$best_score = $score;
					}

					$candidates[] = array(
						'post' => $page,
						'text' => $text,
					);
				}

				if ( rytkoset_theme_chat_prefetch_candidates_are_usable( count( $candidates ), $meeting_query ) ) {
					break;
				}

				// An ambiguous tier is noise, not an answer: a surname alone
				// matches many pages. Drop it and let the next tier try, so a
				// name that only a single album verifies is still found.
				$candidates = array();
			}
		}

		$candidate_count = count( $candidates );

		if ( ! rytkoset_theme_chat_prefetch_candidates_are_usable( $candidate_count, $meeting_query ) ) {
			return '';
		}

		$max_length    = max( 800, min( 5000, (int) apply_filters( 'rytkoset_theme_chat_prefetch_max_length', 3000 ) ) );
		$excerpt_max   = max( 400, (int) floor( ( $max_length - 500 ) / $candidate_count ) );
		$source_terms  = $meeting_query ? array_merge( $search_terms, array( 'sukukokous', 'sukujuhla' ) ) : $search_terms;
		$source_blocks = array();

		foreach ( $candidates as $candidate ) {
			$page    = $candidate['post'];
			$text    = $candidate['text'];
			$url     = get_permalink( $page->ID );
			$excerpt = ! empty( $candidate['whole'] )
				? rytkoset_theme_chat_truncate( $text, $excerpt_max )
				: rytkoset_theme_chat_get_matching_page_excerpt( $text, $source_terms, $excerpt_max, $meeting_query || $photo_query || $person_relation_query );

			if ( ! is_string( $url ) || '' === trim( $url ) || '' === $excerpt ) {
				return '';
			}

			$source_label    = 'gallery_album' === $page->post_type ? 'Albumi: ' : 'Sivu: ';
			$source_blocks[] = $source_label . trim( (string) get_the_title( $page->ID ) ) . "\n"
				. 'Lähde: ' . trim( $url ) . "\n\n"
				. $excerpt;
		}

		return rytkoset_theme_chat_build_prefetched_source_context( $source_blocks );
	}
}

/**
 * Checks whether the latest user message asks about a fiscal year.
 *
 * Covers the common Finnish singular and plural case forms without matching
 * unrelated account or commerce terms that merely start with "tili".
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_fiscal_year_query' ) ) {
	function rytkoset_theme_chat_is_fiscal_year_query( $messages ) {
		$message = rytkoset_theme_chat_get_latest_user_message( $messages );

		if ( '' === $message ) {
			return false;
		}

		return (bool) preg_match(
			'/\b(?:tilikausi(?:en|a|na|ksi|ssa|sta|in|lla|lta|lle|tta)?|tilikaud(?:en|eksi|essa|esta|ella|elta|elle|etta)|tilikaut(?:ta|ena|een|eni|esi|emme|enne|ensa)|tilikaudet)(?:ko|kö|kin|kaan|kään|han|hän|pa|pä)?\b/ui',
			$message
		);
	}
}

/**
 * Determines whether the first completion must read a public page.
 *
 * The model may answer a first-turn person or rare-term query without calling
 * the tool even when the sitemap contains an exact hint. Force one initial
 * read for those queries and for unambiguous pronoun follow-ups; ordinary
 * support questions retain Mistral's default automatic tool choice.
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_should_force_page_tool' ) ) {
	function rytkoset_theme_chat_should_force_page_tool( $messages ) {
		$latest_user_message = rytkoset_theme_chat_get_latest_user_message( $messages );

		if ( '' === $latest_user_message ) {
			return false;
		}

		if ( preg_match( '/\b(?:hän|hänen|häntä|hänellä|häneltä|hänelle|hänestä)\b/ui', $latest_user_message ) ) {
			return true;
		}

		if ( preg_match( '/\b(?:toimitti|kokosi|kirjoitti|laati|selvitti)\b/ui', $latest_user_message ) ) {
			return true;
		}

		if ( preg_match( '/\b(?:kuka|kenen|ketä)\b/ui', $latest_user_message ) ) {
			return ! empty( rytkoset_theme_chat_get_person_search_terms( $latest_user_message ) );
		}

		// Elliptical follow-ups and rule-specific concepts need a fresh page read;
		// prior assistant text may contain a related but different concept.
		if ( preg_match( '/^\s*(?:entä|entäs|entäpä)\b/ui', $latest_user_message ) ) {
			return true;
		}

		if ( preg_match( '/\b(?:tilikausi|toimintakausi|tilintarkastus|nimenkirjoitus|säännöt|säännöissä)\b/ui', $latest_user_message ) ) {
			return true;
		}

		// Privacy, data-protection, register-description, payment and
		// delivery-terms questions are no longer forced here (#614). The server
		// resolves them itself in rytkoset_theme_chat_get_concept_source(), which
		// verifies and excerpts the exact page and answers in one ordinary
		// tool_choice:none completion, avoiding the slow, timeout-prone
		// tool_choice:any round that made these questions intermittently 502.
		// When that prefetch cannot resolve the page the handler returns its
		// deterministic concept-source fallback without calling Mistral.
		//
		// Newsletter questions are likewise not forced: the subscribe / manage /
		// cancel self-service answer already lives in the stable site context.

		return rytkoset_theme_chat_is_named_source_query( $messages );
	}
}

/**
 * Enforces the initial tool_choice:any contract for page-read queries.
 *
 * @param bool  $forced      Whether the initial page read was forced.
 * @param int   $rounds_used Completed tool rounds before this response.
 * @param array $tool_calls  Parsed tool calls from the response.
 * @return bool True when the response may continue through the tool loop.
 */
if ( ! function_exists( 'rytkoset_theme_chat_forced_tool_response_is_valid' ) ) {
	function rytkoset_theme_chat_forced_tool_response_is_valid( $forced, $rounds_used, $tool_calls ) {
		if ( ! $forced || (int) $rounds_used > 0 ) {
			return true;
		}

		// The handler executes at most the first three calls in a response.
		foreach ( array_slice( (array) $tool_calls, 0, 3 ) as $tool_call ) {
			if (
				is_array( $tool_call )
				&& 'lue_sivu' === ( $tool_call['name'] ?? '' )
				&& rytkoset_theme_chat_parse_page_tool_args( $tool_call['arguments'] ?? '' ) > 0
			) {
				return true;
			}
		}

		return false;
	}
}

/**
 * Extracts assistant tool calls from a decoded Mistral response (#501).
 *
 * Returns an empty list for malformed responses or missing calls. Only calls
 * with both an ID and function name are returned. The handler separately
 * rejects an empty or invalid initial call set when tool_choice:any was forced.
 *
 * @param mixed $body Dekoodattu API-vaste.
 * @return array<int,array{id:string,name:string,arguments:mixed}>
 */
if ( ! function_exists( 'rytkoset_theme_chat_extract_tool_calls' ) ) {
	function rytkoset_theme_chat_extract_tool_calls( $body ) {
		if ( ! is_array( $body ) || empty( $body['choices'][0]['message']['tool_calls'] ) || ! is_array( $body['choices'][0]['message']['tool_calls'] ) ) {
			return array();
		}

		$calls = array();

		foreach ( $body['choices'][0]['message']['tool_calls'] as $call ) {
			if ( ! is_array( $call ) ) {
				continue;
			}

			$id   = isset( $call['id'] ) && is_string( $call['id'] ) ? trim( $call['id'] ) : '';
			$name = isset( $call['function']['name'] ) && is_string( $call['function']['name'] ) ? trim( $call['function']['name'] ) : '';

			if ( '' === $id || '' === $name ) {
				continue;
			}

			$calls[] = array(
				'id'        => $id,
				'name'      => $name,
				'arguments' => $call['function']['arguments'] ?? '',
			);
		}

		return $calls;
	}
}

/**
 * Jäsentää lue_sivu-työkalun argumenteista sivun id:n (#501).
 *
 * Puhdas funktio (testattava): hyväksyy sekä JSON-merkkijonon että valmiin
 * taulukon. Virheellinen JSON, puuttuva tai ei-positiivinen id palauttaa 0
 * (hallittu fallback — ei koskaan poikkeusta tai negatiivista id:tä).
 *
 * @param mixed $arguments Työkalukutsun argumentit (JSON-merkkijono tai taulukko).
 * @return int Sivun id (>= 1) tai 0 virhetilanteessa.
 */
if ( ! function_exists( 'rytkoset_theme_chat_parse_page_tool_args' ) ) {
	function rytkoset_theme_chat_parse_page_tool_args( $arguments ) {
		if ( is_string( $arguments ) ) {
			$arguments = json_decode( $arguments, true );
		}

		if ( ! is_array( $arguments ) || ! isset( $arguments['sivu_id'] ) ) {
			return 0;
		}

		$raw_page_id = $arguments['sivu_id'];
		if ( ! is_int( $raw_page_id ) && ( ! is_string( $raw_page_id ) || ! preg_match( '/^\d+$/D', trim( $raw_page_id ) ) ) ) {
			return 0;
		}

		$page_id = (int) $raw_page_id;

		return $page_id > 0 ? $page_id : 0;
	}
}

/**
 * Riisuu sivun sisällön turvalliseksi tekstiksi promptia varten (#501).
 *
 * Puhdas funktio (testattava). MVP-linja tarkoituksella ilman dynaamista
 * renderöintiä: block-kommentit, HTML-tagit ja shortcodet poistetaan raa'asta
 * `post_content`ista — shortcodeja tai dynaamisia blokkeja ei suoriteta.
 * Kappalerajat säilytetään rivinvaihtoina, ettei teksti puuroudu.
 *
 * @param string $html Sivun raaka `post_content`.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_extract_page_text' ) ) {
	function rytkoset_theme_chat_extract_page_text( $html ) {
		$text = (string) $html;
		$text = preg_replace( '/<!--.*?-->/s', "\n", $text );
		$text = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text );
		$text = preg_replace( '/<br\s*\/?>|<\/(p|div|li|h[1-6]|blockquote|figcaption|td|th|tr)>/i', "\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\[\/?[a-z][^\]\n]*\]/i', '', $text );
		$text = wp_specialchars_decode( $text, ENT_QUOTES );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = implode( "\n", array_map( 'trim', explode( "\n", $text ) ) );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}
}

/**
 * Extracts a numbered section until the next numbered heading.
 *
 * @param string $text           Plain page text with line breaks.
 * @param int    $section_number Number of the section to extract.
 * @return string Section body without its numbered heading.
 */
if ( ! function_exists( 'rytkoset_theme_chat_extract_numbered_section' ) ) {
	function rytkoset_theme_chat_extract_numbered_section( $text, $section_number ) {
		$section_number = (int) $section_number;

		if ( $section_number < 1 ) {
			return '';
		}

		$lines   = preg_split( '/\R/u', (string) $text );
		$started = false;
		$section = array();

		if ( ! is_array( $lines ) ) {
			return '';
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( ! $started ) {
				if ( preg_match( '/^' . preg_quote( (string) $section_number, '/' ) . '\.\s*\S/u', $line ) ) {
					$started = true;
				}

				continue;
			}

			if ( preg_match( '/^\d+\.\s*\S/u', $line ) ) {
				break;
			}

			$section[] = $line;
		}

		return trim( implode( "\n", $section ) );
	}
}

/**
 * Resolves a page through the chat's public-content access gate.
 *
 * @param int $page_id Page ID.
 * @return WP_Post|null Public page, or null when access cannot be verified.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_public_page' ) ) {
	function rytkoset_theme_chat_get_public_page( $page_id ) {
		$page_id = (int) $page_id;

		if ( $page_id < 1 ) {
			return null;
		}

		$post = get_post( $page_id );

		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== get_post_status( $post ) ) {
			return null;
		}

		if ( '' !== trim( (string) $post->post_password ) ) {
			return null;
		}

		if ( ! function_exists( 'rytkoset_theme_page_is_members_only' ) || rytkoset_theme_page_is_members_only( $post ) ) {
			return null;
		}

		return $post;
	}
}

/**
 * Resolves an allowed public source post for the server-side source prefetch.
 *
 * Pages reuse the full members-only gate. Events and gallery albums have no
 * members-only feature, but must still be published and passwordless before
 * their raw content is sent to the model.
 *
 * @param int               $post_id       Post ID.
 * @param array<int,string> $allowed_types Allowed public post types.
 * @return WP_Post|null Public source post, or null when access is not verified.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_public_source_post' ) ) {
	function rytkoset_theme_chat_get_public_source_post( $post_id, $allowed_types ) {
		$post_id       = (int) $post_id;
		$allowed_types = array_values( array_intersect( array( 'page', 'rytkoset_event', 'gallery_album' ), (array) $allowed_types ) );

		if ( $post_id < 1 || empty( $allowed_types ) ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, $allowed_types, true ) ) {
			return null;
		}

		if ( 'page' === $post->post_type ) {
			return rytkoset_theme_chat_get_public_page( $post_id );
		}

		if ( 'publish' !== get_post_status( $post ) || '' !== trim( (string) $post->post_password ) ) {
			return null;
		}

		return $post;
	}
}

/**
 * Builds a deterministic fiscal-year reply from the public rules page.
 *
 * The dates are never embedded in code. A matched question with an empty reply
 * means the source page, section 10, or its public URL could not be verified.
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return array{matched:bool,reply:string}
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_fiscal_year_source_reply' ) ) {
	function rytkoset_theme_chat_get_fiscal_year_source_reply( $messages ) {
		$result = array(
			'matched' => rytkoset_theme_chat_is_fiscal_year_query( $messages ),
			'reply'   => '',
		);

		if ( ! $result['matched'] || ! function_exists( 'get_page_by_path' ) ) {
			return $result;
		}

		$resolved_page = get_page_by_path( 'sukuseura/saannot' );
		$page          = $resolved_page instanceof WP_Post
			? rytkoset_theme_chat_get_public_page( $resolved_page->ID )
			: null;

		if ( ! $page instanceof WP_Post ) {
			return $result;
		}

		$page_text = rytkoset_theme_chat_extract_page_text( (string) $page->post_content );
		$section   = rytkoset_theme_chat_extract_numbered_section( $page_text, 10 );
		$url       = get_permalink( $page->ID );

		if ( '' === $section || ! is_string( $url ) || '' === trim( $url ) ) {
			return $result;
		}

		$reply = "Säännöt, kohta 10:\n" . $section . "\n\nLähde: " . trim( $url );

		if ( ! rytkoset_theme_chat_reply_is_valid( $reply ) ) {
			return $result;
		}

		$result['reply'] = $reply;

		return $result;
	}
}

/**
 * Palauttaa työkalun geneerisen virhetekstin (#501).
 *
 * Sama teksti kaikille epäämissyille (tuntematon id, luonnos, väärä
 * sisältötyyppi, salasanasuojattu, jäsensivu), ettei vastauksesta voi
 * päätellä rajatun sisällön olemassaoloa.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_error_text' ) ) {
	function rytkoset_theme_chat_get_page_tool_error_text() {
		return 'Sivua ei löytynyt tai se ei ole julkisesti saatavilla.';
	}
}

/**
 * Returns the notice appended to a page-tool result that is only an excerpt.
 *
 * Truncation used to be a silent `mb_substr()`, so the model believed it had
 * read the whole page and answered "en tiedä" — or invented an answer — for
 * content that sat past the cut. Saying so explicitly keeps the honest
 * fallback available to it.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_excerpt_notice' ) ) {
	function rytkoset_theme_chat_get_page_tool_excerpt_notice() {
		return '(Tämä on ote sivusta, ei koko sivu. Jos vastausta ei ole tässä otteessa, kerro ettet löytänyt sitä äläkä päättele sitä itse.)';
	}
}

/**
 * Extracts content words from a question for page-excerpt matching.
 *
 * Unlike the prefetch path's proper-name terms, an ordinary support question
 * ("Missä maissa tietojani käsitellään?") carries no capitalized name. Words
 * of five letters or more, minus the common Finnish question and filler words,
 * are enough to locate the right part of a long page.
 *
 * @param string $message Latest user message.
 * @return array<int,string> Content terms, at most six.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_query_terms' ) ) {
	function rytkoset_theme_chat_get_page_query_terms( $message ) {
		if ( ! preg_match_all( '/(?<!\p{L})[\p{L}][\p{L}-]{4,}(?!\p{L})/u', (string) $message, $matches ) ) {
			return array();
		}

		// Question and filler words appear on every page and would select
		// arbitrary lines instead of the ones the visitor asked about.
		$ignored = array(
			'ketkä',
			'kuinka',
			'kuka',
			'liittyy',
			'mikä',
			'milloin',
			'minkä',
			'minulla',
			'missä',
			'mistä',
			'mitkä',
			'mitäs',
			'miten',
			'noudattaako',
			'olemassa',
			'onko',
			'saako',
			'sivusto',
			'sivustolla',
			'sivuston',
			'siellä',
			'sukuseura',
			'sukuseuran',
			'tarkoittaa',
			'voiko',
			'voinko',
			'yhdistys',
			'yhdistyksen',
		);
		$terms   = array();

		foreach ( $matches[0] as $term ) {
			if ( in_array( mb_strtolower( $term ), $ignored, true ) ) {
				continue;
			}

			$terms[] = $term;
		}

		return array_slice( array_values( array_unique( $terms ) ), 0, 6 );
	}
}

/**
 * Counts stem matches of a search term in a single line of text.
 *
 * @param string $text Line of plain text.
 * @param string $term Search term.
 * @return int Number of matches.
 */
if ( ! function_exists( 'rytkoset_theme_chat_count_term_hits' ) ) {
	function rytkoset_theme_chat_count_term_hits( $text, $term ) {
		$term = trim( (string) $term );

		if ( '' === $term ) {
			return 0;
		}

		$stem = mb_strlen( $term ) < 4 ? $term : mb_substr( $term, 0, 5 );

		return (int) preg_match_all(
			'/(?<!\p{L})' . preg_quote( $stem, '/' ) . '[\p{L}-]*(?!\p{L})/ui',
			(string) $text
		);
	}
}

/**
 * Counts a catering term also when it occurs inside a Finnish compound word.
 *
 * Catering details use forms such as "buffetlounas" and
 * "iltapäiväkahvitarjoilu". The general page-excerpt matcher correctly
 * requires word boundaries, but catering scoring must recognize these curated
 * semantic terms inside compounds.
 *
 * @param string $text Text to inspect.
 * @param string $term Curated catering term or day qualifier.
 * @return int Number of matches.
 */
if ( ! function_exists( 'rytkoset_theme_chat_count_catering_term_hits' ) ) {
	function rytkoset_theme_chat_count_catering_term_hits( $text, $term ) {
		$term = trim( (string) $term );

		if ( '' === $term ) {
			return 0;
		}

		$stem = mb_strlen( $term ) < 4 ? $term : mb_substr( $term, 0, 5 );

		return (int) preg_match_all( '/' . preg_quote( $stem, '/' ) . '[\p{L}-]*/ui', (string) $text );
	}
}

/**
 * Checks whether a question asks about catering at an event.
 *
 * @param string $message Latest user message.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_is_event_catering_query' ) ) {
	function rytkoset_theme_chat_is_event_catering_query( $message ) {
		return (bool) preg_match(
			'/\b(?:aamiai[\p{L}-]*|ateri[\p{L}-]*|buffet[\p{L}-]*|illall?i[\p{L}-]*|juotav[\p{L}-]*|kahv[\p{L}-]*|louna[\p{L}-]*|ruo[\p{L}-]*|syö[\p{L}-]*|tarjoil[\p{L}-]*|tee(?:tä|n|llä|stä)?)\b/ui',
			(string) $message
		);
	}
}

/**
 * Returns semantic terms for selecting one complete event catering section.
 *
 * Fixed topic terms bridge ordinary wording such as "ruokaa" to source terms
 * such as "buffetlounas". Other question terms retain useful day qualifiers.
 *
 * @param string $message Latest user message.
 * @return array<int,string>
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_event_catering_terms' ) ) {
	function rytkoset_theme_chat_get_event_catering_terms( $message ) {
		$message = (string) $message;
		$terms   = array();

		// Keep only catering-relevant qualifiers from the original question.
		// Generic event words such as "sukujuhlissa" used to make a transport
		// or manual-registration section outrank the section that states what
		// the participation fee actually includes.
		if (
			preg_match_all(
				'/\b(?:maanantai[\p{L}-]*|tiistai[\p{L}-]*|keskiviik[\p{L}-]*|torstai[\p{L}-]*|perjantai[\p{L}-]*|lauantai[\p{L}-]*|sunnuntai[\p{L}-]*|aamupäiv[\p{L}-]*|iltapäiv[\p{L}-]*)\b/ui',
				$message,
				$qualifiers
			)
		) {
			$terms = $qualifiers[0];
		}

		if ( preg_match( '/\billall?i[\p{L}-]*\b/ui', $message ) ) {
			$terms = array_merge( $terms, array( 'illallinen', 'buffet' ) );
		} else {
			if ( preg_match( '/\b(?:aamiai[\p{L}-]*|ateri[\p{L}-]*|buffet[\p{L}-]*|louna[\p{L}-]*|ruo[\p{L}-]*|syö[\p{L}-]*)\b/ui', $message ) ) {
				$terms = array_merge( $terms, array( 'ruoka', 'lounas', 'buffet', 'ateria' ) );
			}

			if ( preg_match( '/\b(?:juotav[\p{L}-]*|kahv[\p{L}-]*|tee(?:tä|n|llä|stä)?)\b/ui', $message ) ) {
				$terms = array_merge( $terms, array( 'kahvi', 'tee', 'tarjoilu' ) );
			}

			if ( preg_match( '/\btarjoil[\p{L}-]*\b/ui', $message ) ) {
				$terms = array_merge( $terms, array( 'ruoka', 'lounas', 'buffet', 'ateria', 'kahvi', 'tee' ) );
			}
		}

		return array_values( array_unique( array_map( 'mb_strtolower', $terms ) ) );
	}
}

/**
 * Selects the complete event heading section that best answers a catering query.
 *
 * A heading match is weighted above a body-only mention, so an explicit dinner
 * question selects "Perjantain buffet-illallinen" instead of a later generic
 * registration section that merely repeats the word. The selected section is
 * never cut; an unavailable or over-budget match falls back to the ordinary
 * page-tool excerpt path.
 *
 * @param string $html       Raw event post content.
 * @param string $query      Latest user message.
 * @param int    $max_length Character budget.
 * @return string Complete section with excerpt notice, or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_event_catering_section' ) ) {
	function rytkoset_theme_chat_get_event_catering_section( $html, $query, $max_length ) {
		if ( ! rytkoset_theme_chat_is_event_catering_query( $query ) ) {
			return '';
		}

		$terms    = rytkoset_theme_chat_get_event_catering_terms( $query );
		$sections = rytkoset_theme_chat_extract_heading_sections( (string) $html );
		$scored   = array();

		foreach ( $sections as $index => $section ) {
			$distinct     = 0;
			$total        = 0;
			$heading_hits = 0;

			foreach ( $terms as $term ) {
				$term_heading_hits = rytkoset_theme_chat_count_catering_term_hits( $section['heading'], $term );
				$term_total_hits   = rytkoset_theme_chat_count_catering_term_hits( $section['text'], $term );

				if ( $term_total_hits > 0 ) {
					++$distinct;
					$total        += $term_total_hits + ( 3 * $term_heading_hits );
					$heading_hits += $term_heading_hits;
				}
			}

			if ( $distinct > 0 ) {
				$scored[] = array(
					'index'        => $index,
					'distinct'     => $distinct,
					'total'        => $total,
					'heading_hits' => $heading_hits,
					'text'         => $section['text'],
				);
			}
		}

		if ( empty( $scored ) ) {
			return '';
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return array( $b['distinct'], $b['heading_hits'], $b['total'], $a['index'] )
					<=> array( $a['distinct'], $a['heading_hits'], $a['total'], $b['index'] );
			}
		);

		$notice = rytkoset_theme_chat_get_page_tool_excerpt_notice();
		$result = $scored[0]['text'] . "\n\n" . $notice;

		return mb_strlen( $result ) <= max( 1, (int) $max_length ) ? $result : '';
	}
}

/**
 * Checks for one complete URL instead of accepting a longer URL prefix.
 *
 * @param string $text Text that may contain the URL.
 * @param string $url  Exact URL to find.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_chat_text_contains_exact_url' ) ) {
	function rytkoset_theme_chat_text_contains_exact_url( $text, $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return false;
		}

		return (bool) preg_match(
			'~' . preg_quote( $url, '~' ) . '(?=$|[\s<>()\[\]{},;.!])~u',
			(string) $text
		);
	}
}

/**
 * Builds a verified event source for a catering question.
 *
 * Follow-up wording such as "Onko siellä..." carries no event name. The most
 * recent history message may still contain one exact published event permalink;
 * that is a deterministic reference and is preferred. A first-turn question
 * may instead resolve one event whose title matches its proper-name terms and
 * whose content has a matching catering section. Ambiguous matches fail closed.
 *
 * @param array<int,array{role:string,content:string}> $messages Prepared history.
 * @return string Verified source context, or an empty string.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_event_catering_source' ) ) {
	function rytkoset_theme_chat_get_event_catering_source( $messages ) {
		$query = rytkoset_theme_chat_get_latest_user_message( $messages );

		if ( ! rytkoset_theme_chat_is_event_catering_query( $query ) ) {
			return '';
		}

		$max_events = min( 100, max( 1, (int) apply_filters( 'rytkoset_theme_chat_prefetch_max_pages', 60 ) ) );
		$event_ids  = (array) get_posts(
			array(
				'post_type'   => 'rytkoset_event',
				'post_status' => 'publish',
				'numberposts' => $max_events,
				'fields'      => 'ids',
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$max_length = max( 800, min( 5000, (int) apply_filters( 'rytkoset_theme_chat_prefetch_max_length', 3000 ) ) );
		$candidates = array();

		foreach ( array_slice( array_values( array_unique( array_map( 'intval', $event_ids ) ) ), 0, $max_events ) as $event_id ) {
			$event = rytkoset_theme_chat_get_public_source_post( $event_id, array( 'rytkoset_event' ) );

			if ( ! $event instanceof WP_Post ) {
				continue;
			}

			$url = get_permalink( $event->ID );
			if ( ! is_string( $url ) || '' === trim( $url ) ) {
				continue;
			}

			$head    = 'Tapahtuma: ' . trim( (string) get_the_title( $event->ID ) ) . "\n" . 'Lähde: ' . trim( $url );
			$budget  = max( 1, $max_length - mb_strlen( $head ) - 2 );
			$section = rytkoset_theme_chat_get_event_catering_section( (string) $event->post_content, $query, $budget );

			if ( '' === $section ) {
				continue;
			}

			$candidates[] = array(
				'post'    => $event,
				'url'     => trim( $url ),
				'head'    => $head,
				'section' => $section,
			);
		}

		if ( empty( $candidates ) ) {
			return '';
		}

		$selected = null;

		// The newest history message with exactly one real event permalink binds
		// an elliptical follow-up to that event without trusting a model-made URL.
		foreach ( array_reverse( (array) $messages ) as $message ) {
			$content = (string) ( $message['content'] ?? '' );
			$matches = array();

			foreach ( $candidates as $candidate ) {
				if ( rytkoset_theme_chat_text_contains_exact_url( $content, $candidate['url'] ) ) {
					$matches[] = $candidate;
				}
			}

			if ( 1 === count( $matches ) ) {
				$selected = $matches[0];
				break;
			}

			if ( count( $matches ) > 1 ) {
				return '';
			}
		}

		if ( null === $selected ) {
			$terms      = rytkoset_theme_chat_get_named_search_terms( $query );
			$best_score = 0;
			$best       = array();

			if ( empty( $terms ) ) {
				if ( 1 !== count( $candidates ) ) {
					return '';
				}

				$selected = $candidates[0];
			}

			foreach ( $candidates as $candidate ) {
				if ( null !== $selected ) {
					break;
				}

				$title = (string) get_the_title( $candidate['post']->ID );
				$score = 0;

				foreach ( $terms as $term ) {
					if ( rytkoset_theme_chat_text_contains_term_stem( $title, $term ) ) {
						++$score;
					}
				}

				if ( 0 === $score || $score < $best_score ) {
					continue;
				}

				if ( $score > $best_score ) {
					$best       = array();
					$best_score = $score;
				}

				$best[] = $candidate;
			}

			if ( null === $selected && 1 !== count( $best ) ) {
				return '';
			}

			if ( null === $selected ) {
				$selected = $best[0];
			}
		}

		return rytkoset_theme_chat_build_prefetched_source_context(
			array( $selected['head'] . "\n\n" . $selected['section'] )
		);
	}
}

/**
 * Selects the most informative lines of a long page within a character budget.
 *
 * Selecting matches in document order fills the budget from the top of the
 * page, which loses a late answer whenever a question term is common on that
 * page: on the privacy statement `tieto*` matches 24% of all lines, so the
 * transfer section near the end never travelled. Lines are therefore ranked by
 * how many distinct question terms they carry (then by occurrences), and only
 * the selected lines are restored to document order for readability.
 *
 * @param string            $text       Plain page text.
 * @param array<int,string> $terms      Question terms.
 * @param int               $max_length Character budget.
 * @return string Excerpt, or an empty string when nothing matched.
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_scored_page_excerpt' ) ) {
	function rytkoset_theme_chat_get_scored_page_excerpt( $text, $terms, $max_length ) {
		$lines      = preg_split( '/\R/u', (string) $text );
		$max_length = max( 1, (int) $max_length );

		if ( ! is_array( $lines ) || empty( $terms ) ) {
			return '';
		}

		$scored = array();

		foreach ( $lines as $index => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$distinct = 0;
			$total    = 0;

			foreach ( $terms as $term ) {
				$hits = rytkoset_theme_chat_count_term_hits( $line, $term );

				if ( $hits > 0 ) {
					++$distinct;
					$total += $hits;
				}
			}

			if ( $distinct > 0 ) {
				$scored[] = array(
					'index'    => $index,
					'distinct' => $distinct,
					'total'    => $total,
				);
			}
		}

		if ( empty( $scored ) ) {
			return '';
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return array( $b['distinct'], $b['total'], $a['index'] )
					<=> array( $a['distinct'], $a['total'], $b['index'] );
			}
		);

		$last     = count( $lines ) - 1;
		$selected = array();
		$length   = 0;

		foreach ( $scored as $row ) {
			// One line of context on each side keeps a matched sentence readable.
			foreach ( range( max( 0, $row['index'] - 1 ), min( $last, $row['index'] + 1 ) ) as $index ) {
				$line = trim( (string) $lines[ $index ] );

				if ( '' === $line || isset( $selected[ $index ] ) ) {
					continue;
				}

				if ( $length + mb_strlen( $line ) + 1 > $max_length ) {
					break 2;
				}

				$selected[ $index ] = $line;
				$length            += mb_strlen( $line ) + 1;
			}
		}

		if ( empty( $selected ) ) {
			return '';
		}

		ksort( $selected );

		return rytkoset_theme_chat_truncate( implode( "\n", $selected ), $max_length );
	}
}

/**
 * Builds the page text sent to the model, as an excerpt when the page is long.
 *
 * A page within the limit is returned unchanged. A longer page is reduced to
 * the lines matching the question (with their immediate context) so the answer
 * is not lost to a head-of-page cut; a question with no usable terms, or one
 * matching nothing, falls back to the previous head truncation. Either way the
 * result is marked as an excerpt.
 *
 * @param string $text       Plain page text.
 * @param string $query      Latest user message.
 * @param int    $max_length Character budget.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_page_tool_excerpt' ) ) {
	function rytkoset_theme_chat_get_page_tool_excerpt( $text, $query, $max_length ) {
		$text       = (string) $text;
		$max_length = max( 1, (int) $max_length );

		if ( mb_strlen( $text ) <= $max_length ) {
			return $text;
		}

		$notice = rytkoset_theme_chat_get_page_tool_excerpt_notice();
		$budget = max( 1, $max_length - mb_strlen( $notice ) - 2 );
		$terms  = rytkoset_theme_chat_get_page_query_terms( $query );
		// Finnish inflects heavily ("uutiskirjeen" vs. "uutiskirje"), so terms
		// are matched by stem.
		$excerpt = rytkoset_theme_chat_get_scored_page_excerpt( $text, $terms, $budget );

		if ( '' === $excerpt ) {
			$excerpt = rytkoset_theme_chat_truncate( $text, $budget );
		}

		return $excerpt . "\n\n" . $notice;
	}
}

/**
 * Hakee ja validoi työkalun pyytämän sivun sisällön (#501).
 *
 * Vuotosuoja: vain julkaistut, julkiset sivut kelpaavat. Jäsenille rajatut
 * sivut (#392) suodatetaan ehdottomasti ja katsojasta riippumatta — chatin
 * vastaukset menevät kolmannen osapuolen API:in eikä chatti saa toimia
 * jäsenmuurin ohitusreittinä edes kirjautuneelle jäsenelle. Fail-closed:
 * jos jäsensivumoduulia ei ole ladattu, mitään sivua ei palauteta.
 *
 * @param int    $page_id Sivun id.
 * @param string $query   Uusin käyttäjäviesti otteen kohdistamiseen.
 * @return string Sivun tekstisisältö otsikolla ja osoitteella, tai virheteksti.
 */
if ( ! function_exists( 'rytkoset_theme_chat_resolve_page_tool_result' ) ) {
	function rytkoset_theme_chat_resolve_page_tool_result( $page_id, $query = '' ) {
		$post = rytkoset_theme_chat_get_public_source_post( $page_id, rytkoset_theme_chat_get_page_tool_post_types() );

		if ( ! $post instanceof WP_Post ) {
			return rytkoset_theme_chat_get_page_tool_error_text();
		}

		$text = rytkoset_theme_chat_extract_page_text( (string) $post->post_content );

		if ( '' === $text ) {
			return 'Sivulla ei ole luettavaa tekstisisältöä.';
		}

		$title = trim( (string) get_the_title( $post->ID ) );
		$url   = get_permalink( $post->ID );
		$label = 'rytkoset_event' === $post->post_type ? 'Tapahtuma' : 'Sivu';
		$head  = $label . ': ' . $title . ( is_string( $url ) && '' !== $url ? ' (' . $url . ')' : '' );

		// The heading is part of the budget, so a long page is reduced against
		// what is left after it rather than losing the source URL to the cut.
		$max_length = rytkoset_theme_chat_get_page_tool_max_length();
		$budget     = max( 1, $max_length - mb_strlen( $head ) - 2 );
		$excerpt    = 'rytkoset_event' === $post->post_type
			? rytkoset_theme_chat_get_event_catering_section( (string) $post->post_content, $query, $budget )
			: '';

		if ( '' === $excerpt ) {
			$excerpt = rytkoset_theme_chat_get_page_tool_excerpt( $text, $query, $budget );
		}

		$result = $head . "\n\n" . $excerpt;

		// The budget math already keeps the result within the limit; this is the
		// cost guard's hard ceiling for filter values too small to hold the head
		// and the excerpt notice.
		return rytkoset_theme_chat_truncate( $result, $max_length );
	}
}

/**
 * Suorittaa yhden työkalukutsun ja palauttaa tool-viestin sisällön (#501).
 *
 * @param string $name      Kutsutun työkalun nimi.
 * @param mixed  $arguments Työkalukutsun argumentit.
 * @param string $query     Uusin käyttäjäviesti otteen kohdistamiseen.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_chat_run_page_tool' ) ) {
	function rytkoset_theme_chat_run_page_tool( $name, $arguments, $query = '' ) {
		if ( 'lue_sivu' !== (string) $name ) {
			return 'Tuntematon työkalu.';
		}

		return rytkoset_theme_chat_resolve_page_tool_result(
			rytkoset_theme_chat_parse_page_tool_args( $arguments ),
			$query
		);
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

		$home_url = rtrim( (string) home_url(), '/' );

		$prompt  = "Olet Rytkösten sukuseura ry:n verkkosivujen suomenkielinen tukiassistentti.\n\n";
		$prompt .= "Ohjeet:\n";
		$prompt .= "- Vastaa aina ja vain suomeksi, ystävällisesti ja tiiviisti.\n";
		$prompt .= "- Vastaa pelkkänä tekstinä ilman muotoilumerkintöjä: ei Markdownia, ei tähtiä lihavointiin, ei [teksti](osoite)-linkkejä eikä otsikkomerkkejä. Luettelot saa tehdä viivalla alkavina riveinä.\n";
		$prompt .= "- Sivuston osoite on {$home_url}. Kun viittaat sivuston sivuun, kirjoita koko osoite paljaana tekstinä (esim. {$home_url}/kauppa/) — chatti muuttaa sen automaattisesti linkiksi.\n";
		$prompt .= "- Jätä URL-osoitteen jälkeen aina välilyönti tai rivinvaihto ennen seuraavaa sanaa.\n";
		$prompt .= "- Käytä vain tässä system-promptissa annettuja osoitteita (sivustokartta ja muut lähteet). Älä koskaan keksi, arvaa tai päättele osoitetta — jos sopivaa osoitetta ei löydy lähteistä, jätä osoite mainitsematta.\n";
		$prompt .= "- Pysy yhdistyksen ja sen verkkosivujen aiheissa: jäsenyys, tapahtumat, sukujuhlat, sukututkimus, kuvat/albumit, digilehdet ja yhteystiedot.\n";
		$prompt .= "- Käytä faktoihin vain tässä system-promptissa annettuja lähteitä: ajantasainen sivustolta koottu tieto, pysyvä sivustokonteksti ja ylläpitäjän tietopohja.\n";
		$prompt .= "- Älä täydennä puuttuvia kohtia yleisellä tiedolla, oletuksilla, vanhoilla verkkosivumalleilla tai WordPressin tavanomaisella toiminnalla.\n";
		$prompt .= "- Älä keksi tietoa. Jos et tiedä vastausta, et löydä sitä lähteistä tai kysymys ei liity yhdistykseen, kerro se rehellisesti.\n";
		$prompt .= "- Jos et löydä vastausta lähteistä etkä sivun lukutyökalulla, voit lopuksi ehdottaa sivuston omaa hakua osoitteessa {$home_url}/?s=hakusana ja korvata hakusanan käyttäjän aiheella. Älä käytä hakuehdotusta silloin, kun vastaus löytyy lähteistä, äläkä sen sijaan että lukisit sopivan sivun työkalulla.\n";
		$prompt .= "- Arvioi jokainen kysymys itsenäisesti annettujen lähteiden perusteella. Älä kopioi aiemman vastauksen kieltäytymismuotoilua uuteen kysymykseen: aiempi kieltäytyminen ei kerro mitään uuden kysymyksen vastauksesta.\n";
		$prompt .= "- Älä sano samassa vastauksessa sekä ettet löytänyt tietoa että mitä lähde asiasta kertoo. Jos löydät vastauksen lähteestä, vastaa suoraan ilman kieltäytymisaloitusta.\n";
		$prompt .= "- Älä koskaan esitä vuosilukua, päivämäärää, hintaa tai lukumäärää, jota ei ole annetuissa lähteissä — älä myöskään arvaa tai päättele sellaista. Jos tarkkaa lukua ei löydy lähteistä, kerro ettet tiedä sitä.\n";
		$prompt .= "- Älä arvaa tulevia suunnitelmia, henkilöitä, julkaisujen saatavuutta, tuotteiden ostettavuutta, käyttöoikeuksia tai yksittäisen tilauksen tilaa.\n";
		$prompt .= "- Kun käyttäjä pyytää henkilölistaa tai hallituksen kokoonpanoa, toista vain lähteessä annetut nimet ja roolit. Älä täydennä listaa oletetuilla nimillä.\n";
		$prompt .= "- Tulkitse seurantakysymyksen pronomini tai muu viittaus (esimerkiksi hän tai hänen) viimeisimmän yksiselitteisen keskustelukontekstin perusteella. Jos viittaus on epäselvä, pyydä täsmennys äläkä arvaa.\n";
		$prompt .= "- Älä korvaa käyttäjän kysymää käsitettä samankaltaisella käsitteellä. Hallituskausi, toimintakausi ja tilikausi ovat eri asioita. Jos kysytyn käsitteen täsmällinen tieto ei ole jo lähteissä, lue sopiva sivu työkalulla.\n";
		$prompt .= "- Kun käyttäjä kysyy henkilön ammattia, tehtävää tai roolia, käytä lähteessä henkilölle nimenomaisesti annettua nimikettä äläkä yleistä tai päättele sitä.\n";
		$prompt .= "- Kun et löydä kysytystä henkilöstä tietoa, käytä nimeä vastauksessa perusmuodossa äläkä taivuta sitä, jottei nimi vääristy. Jos annetussa lähteessä on lähes sama nimi kuin kysytty, mainitse lähteen nimi ja kysy, tarkoittiko käyttäjä häntä — älä pelkästään ohjaa lähdesivulle.\n";
		$prompt .= "- Suomessa henkilö nimetään usein myös järjestyksessä \"Sukunimen genetiivi + etunimi\": esimerkiksi \"Virtasen Matti\" tarkoittaa samaa henkilöä kuin \"Matti Virtanen\". Tunnista tällainen kysymys samaksi henkilöksi ja vastaa lähteen perusteella normaalisti. Sukunimen taivutettu muoto voi poiketa kirjoitusasultaan perusmuodosta (Virtanen -> Virtasen), ja myös lähteessä nimi voi esiintyä taivutettuna, joten vertaa nimen vartaloa äläkä vaadi täsmälleen samaa kirjoitusasua.\n";
		$prompt .= "- Ohjaa epävarmoissa tai henkilökohtaisissa asioissa ottamaan yhteyttä sähköpostitse osoitteeseen {$contact_email}.\n";
		$prompt .= '- Älä pyydä äläkä käsittele arkaluontoisia tietoja (salasanat, maksutiedot).';

		$stable_context = rytkoset_theme_chat_get_stable_site_context();
		if ( '' !== $stable_context ) {
			$prompt .= "\n\nPysyvä sivustokonteksti (käytä näitä perusasioita, kun kysymys koskee sivuston toimintoja tai polkuja):\n\n";
			$prompt .= $stable_context;
		}

		// Sivustokartta: julkaistut sivut ja arkistot suoraan WordPressistä.
		$sitemap = rytkoset_theme_chat_get_sitemap_context();
		if ( '' !== $sitemap ) {
			$prompt .= "\n\nSivustokartta (sivuston julkaistut sivut ja arkistot; nämä ovat sivuston ainoat sivuosoitteet — älä viittaa muihin osoitteisiin kuin näihin tai muissa lähteissä annettuihin. Sivurivien aiheita-kohdat ovat vain hakuvihjeitä oikean sivun valintaan, eivät faktavastauksen lähde):\n\n";
			$prompt .= $sitemap;
		}

		// Sivun lukutyökalu (#501): ohje kutsua lue_sivu-työkalua ennen kieltäytymistä.
		// Sanamuoto tarkoituksella matalakynnyksinen: devissä havaittiin, että
		// "todennäköisesti"-ehto + promptin arvauskiellot saivat mallin jättämään
		// työkalun kokonaan käyttämättä (17 viestiä, 0 työkalukutsua) ja jopa
		// väittämään, ettei sivulla näkyvää nimeä mainita sivustolla.
		if ( rytkoset_theme_chat_page_tool_is_enabled() ) {
			$prompt .= "\n\nSivun lukutyökalu: käytössäsi on lue_sivu-työkalu, joka palauttaa sivustokartassa listatun sivun tekstisisällön (anna parametriksi sivustokartan \"(sivu-id: N)\" -merkinnän numero). Sivustokartan aiheita-kohdat ovat vain hakuvihjeitä oikean sivun valintaan; älä vastaa faktakysymykseen niiden perusteella vaan lue sivu työkalulla. Työkalulla haettu sivusisältö on sallittu lähde siinä missä muutkin tämän promptin lähteet. Kun kysymys koskee sukuseuraa tai sivuston sisältöä eikä vastaus ole jo annetuissa lähteissä, kutsu ensin lue_sivu-työkalua otsikoltaan tai aiheiltaan sopivimmalle sivustokartan sivulle ennen kuin vastaat, ettet tiedä — työkalun kokeileminen ei ole kiellettyä arvaamista, vaan oikea tapa välttää arvaus. Jos käyttäjä jatkaa aiempaa henkilöä koskevaa kysymystä pronominilla tai muulla viittauksella, käytä aiemman kysymyksen nimeä saman sivun valintaan ja lue sivu uudelleen tarvittaessa. Jos ensimmäiseltä tarkistamaltasi sivulta ei löydy vastausta, kokeile vielä toista aiheeseen sopivaa sivustokartan sivua ennen kuin toteat, ettet tiedä — yksi tarkistettu sivu ei riitä osoittamaan, ettei tietoa ole sivustolla. Älä kuitenkaan käytä työkalua, kun vastaus on jo annetuissa lähteissä. Älä koskaan väitä, ettei jotakin asiaa, nimeä tai tietoa mainita koko sivustolla, ellet ole tarkistanut useampaa aiheeseen sopivaa sivua työkalulla. Jos vastausta ei löydy työkalullakaan, kerro rehellisesti ettet tiedä. Sivustokartassa on myös tapahtumasivut (merkintä \"(tapahtuma)\") ja albumisivut (merkintä \"(albumi)\"), ja ne luetaan samalla työkalulla. Tapahtuman ohjelma, aikataulu, tarjoilut ja käytännön ohjeet ovat vain tapahtuman omalla sivulla, joten lue se työkalulla ennen kuin vastaat, ettet tiedä tapahtuman sisältöä. Albumin kuvausteksti kertoo, mitä tilaisuudessa tapahtui ja ketkä siellä esiintyivät, joten lue albumi työkalulla, kun kysymys koskee menneen tilaisuuden ohjelmaa, ajankohtaa tai siellä mainittuja henkilöitä. Albumien videoista tiedät vain, että albumilla voi olla videoita — videoiden sisältöä tai otsikoita ei ole missään lähteessä, joten älä kuvaile niitä.";
		}

		// Ylläpitäjän Customizeriin syöttämä tietopohja (#414).
		$faq = rytkoset_theme_chat_get_faq_text();
		if ( '' !== $faq ) {
			$prompt .= "\n\nKäytä seuraavaa ylläpitäjän tietopohjaa vakiintuneisiin yhdistys- ja toimintaohjeisiin. Jos vastaus ei löydy annetuista lähteistä etkä ole varma, kerro ettet tiedä ja ohjaa sähköpostiin.\n\n";
			$prompt .= "Tietopohja:\n" . $faq;
		}

		// Automaattisesti koottu ajantasainen tieto (#459, #471): tapahtumat + jäsenyys- ja muut verkkokaupan tuotteet.
		$live_context = rytkoset_theme_chat_get_live_context();
		if ( '' !== $live_context ) {
			$prompt .= "\n\nAjantasaiset tiedot sivustolta (koottu automaattisesti tapahtumista ja verkkokaupan tuotteista; käytä näitä ensisijaisesti tapahtuma-, jäsenmaksu- ja tuotekysymyksiin):\n\n";
			$prompt .= $live_context;
			$prompt .= "\n\nÄlä arvioi vapaita paikkoja, ilmoittautumistilannetta tai tuotteiden varastotilannetta — ohjaa tarkistamaan ne tapahtuman tai tuotteen sivulta.";
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
					<strong><?php esc_html_e( 'Tekoälyavustaja.', 'rytkoset-theme' ); ?></strong>
					<?php esc_html_e( 'Älä syötä arkaluonteisia tietoja; varmista tärkeät asiat sähköpostitse.', 'rytkoset-theme' ); ?>
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
