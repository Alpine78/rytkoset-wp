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
 * Tulevat tapahtumat -osio kertoo eksplisiittisesti, jos tapahtumia ei ole
 * (ettei malli keksi niitä). Jäsenyys- ja muut tuoteosiot jätetään kokonaan
 * pois, jos tuotteita ei löydy. Koko lohko katkaistaan suodatettavaan
 * merkkirajaan.
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

		// 5. Payload: system-prompt + rajattu historia.
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
			'max_tokens'  => rytkoset_theme_chat_get_max_tokens(),
			'temperature' => rytkoset_theme_chat_get_temperature(),
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

		// 8. Vastauksen poiminta.
		$reply = rytkoset_theme_chat_extract_reply( $body );
		if ( '' === $reply ) {
			rytkoset_theme_chat_log_error( 'Tyhjä tai odottamaton Mistral-vaste.' );
			rytkoset_theme_chat_record_error_stat( 'empty_reply' );
			return rytkoset_theme_chat_upstream_error();
		}

		// 9. Onnistumislaskuri (#472) — ei sisältöä eikä IP:tä, vain määrä + ajankohta.
		rytkoset_theme_chat_record_message_sent_stat();

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
 * Palauttaa chatin käyttötilastojen wp_options-avaimet (#472).
 *
 * Kevyet laskurit ylläpitäjän näkyvyyteen (lähetetyt viestit, rate limit
 * -osumat, Mistral-/upstream-virheet) — vain kokonaismäärä + viimeisin
 * ajankohta (virheillä myös viimeisin tyyppi). Ei koskaan raakaa
 * IP-osoitetta eikä viestisisältöä, sama periaate kuin rate limit
 * -transientissa (joka tallentaa vain MD5-tiivisteen).
 *
 * @return array{messages:string,rate_limit:string,error:string}
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_stat_option_names' ) ) {
	function rytkoset_theme_chat_get_stat_option_names() {
		return array(
			'messages'   => 'rytkoset_chat_stat_messages',
			'rate_limit' => 'rytkoset_chat_stat_rate_limit',
			'error'      => 'rytkoset_chat_stat_error',
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
 *     last_error: array{count:int,last_at:int,last_type:string}
 * }
 */
if ( ! function_exists( 'rytkoset_theme_chat_get_usage_stats' ) ) {
	function rytkoset_theme_chat_get_usage_stats() {
		$names = rytkoset_theme_chat_get_stat_option_names();

		$messages   = get_option( $names['messages'], array() );
		$rate_limit = get_option( $names['rate_limit'], array() );
		$error      = get_option( $names['error'], array() );

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

		$stats = rytkoset_theme_chat_get_usage_stats();

		echo '<p><strong>' . esc_html__( 'Tila:', 'rytkoset-theme' ) . '</strong> ' . esc_html( $status ) . '</p>';

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
			'- Tapahtumat: /tapahtumat/. Foorumi: /foorumi/. Blogi: /blogi/. Digilehdet: /digilehdet/.',
			'- Foorumi on käytössä sivustolla. Kirjautunut käyttäjä voi aloittaa keskustelun, jos hänellä on foorumin julkaisuoikeus. Älä väitä foorumia suljetuksi vain siksi, että viimeisin viesti on vanha.',
			'- Blogi näyttää julkaistut kirjoitukset. Blogikirjoituksia voi ehdottaa tai lähettää ylläpidolle sähköpostitse; julkaisu tehdään ylläpidon kautta. Älä väitä, ettei blogitekstejä oteta vastaan.',
			'- Blogikirjoituksissa ja albumeissa voi olla kommentointi käytössä; kommentit moderoidaan ennen julkaisua.',
			'- Digilehdet ovat sivuston HTML-sisältöä, eivät PDF-latauksia. Älä lupaa PDF-latauksia, ellei erillinen tietopohja niin sano.',
			'- Sosiaalisen median linkit ovat sivustolla: Facebook https://www.facebook.com/rytkoset, YouTube https://www.youtube.com/@rytkoset, Instagram https://www.instagram.com/rytkoset/ ja X https://x.com/rytkoset. Älä väitä, ettei some-tilejä ole.',
			'- Sukukirjaa voi lainata eri kirjastoista. Älä väitä, ettei sukukirjaa ole olemassa tai että uusi sukukirjatyö olisi käynnissä, ellei ajantasainen tietopohja niin kerro.',
			'- Rytkösten sukulainen nro 9 on julkaistu ja myynnissä verkkokaupassa: /kauppa/sukulehdet/rytkosten-sukulainen-nro-9/. Ohjaa tuotteen sivulle ajantasaisen hinnan ja saatavuuden tarkistamiseksi.',
			'- Sukuseuran hallitus kerrotaan sivulla /sukuseura/sukuseuran-hallitus/. Hallituskausi on 2023-2026. Hallitukseen kuuluvat: Esa Rytkönen (jäsen, Espoo / Maaninka), Mikko Rytkönen (suvun esimies, Runni), Antti Rytkönen (puheenjohtaja, Tampere), Eeli Rytkönen (Kinnulanlahti / Maaninka), Eeva-Liisa Ryhänen (jäsen, Helsinki), Ilkka Rytkönen (jäsen / rytkoset.net ylläpitäjä, Kuopio), Juha Rytkönen (jäsen, Maavesi / Joroinen), Mauri Rytkönen (jäsen, Helsinki), Tapani Rytkönen (sihteeri, Pieksämäki) ja Kimmo Tuulenkari (jäsen, Kajaani). Kun kysytään hallituksesta, käytä vain tätä listaa äläkä lisää muita nimiä.',
			'- Maksuongelmissa ohjeista Oma tili -> Tilaukset vain ehdollisesti: jos tilauksella näkyy Maksa / yritä uudelleen -painike, maksua voi jatkaa ja valita kassalla toisen maksutavan; jos painiketta ei näy, ohjaa sähköpostiin.',
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
		$prompt .= "- Pysy yhdistyksen ja sen verkkosivujen aiheissa: jäsenyys, tapahtumat, sukujuhlat, sukututkimus, kuvat/albumit, digilehdet ja yhteystiedot.\n";
		$prompt .= "- Käytä faktoihin vain tässä system-promptissa annettuja lähteitä: ajantasainen sivustolta koottu tieto, pysyvä sivustokonteksti ja ylläpitäjän tietopohja.\n";
		$prompt .= "- Älä täydennä puuttuvia kohtia yleisellä tiedolla, oletuksilla, vanhoilla verkkosivumalleilla tai WordPressin tavanomaisella toiminnalla.\n";
		$prompt .= "- Älä keksi tietoa. Jos et tiedä vastausta, et löydä sitä lähteistä tai kysymys ei liity yhdistykseen, kerro se rehellisesti.\n";
		$prompt .= "- Älä arvaa tulevia suunnitelmia, henkilöitä, julkaisujen saatavuutta, tuotteiden ostettavuutta, käyttöoikeuksia tai yksittäisen tilauksen tilaa.\n";
		$prompt .= "- Kun käyttäjä pyytää henkilölistaa tai hallituksen kokoonpanoa, toista vain lähteessä annetut nimet ja roolit. Älä täydennä listaa oletetuilla nimillä.\n";
		$prompt .= "- Ohjaa epävarmoissa tai henkilökohtaisissa asioissa ottamaan yhteyttä sähköpostitse osoitteeseen {$contact_email}.\n";
		$prompt .= '- Älä pyydä äläkä käsittele arkaluontoisia tietoja (salasanat, maksutiedot).';

		$stable_context = rytkoset_theme_chat_get_stable_site_context();
		if ( '' !== $stable_context ) {
			$prompt .= "\n\nPysyvä sivustokonteksti (käytä näitä perusasioita, kun kysymys koskee sivuston toimintoja tai polkuja):\n\n";
			$prompt .= $stable_context;
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
