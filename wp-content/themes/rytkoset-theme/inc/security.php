<?php
/**
 * Tietoturvan kovennukset (#336).
 *
 * Estää käyttäjien luetteloinnin (user enumeration), jolla bottiverkot
 * keräävät kirjautumisnimiä brute-force-hyökkäyksiä varten.
 *
 * Tämä siivu kattaa automaattisen luetteloinnin vektorit:
 *   1. REST API:n `/wp/v2/users` -kokoelma kirjautumattomilta käyttäjiltä
 *   2. `?author=N` -numerokyselyllä tehtävä kirjautumisnimen paljastus
 *   3. Coren käyttäjäsitemap `/wp-sitemap-users-1.xml`
 *
 * Kirjautuneiden käyttäjien toiminta säilyy ennallaan (mm. wp-admin,
 * blokkieditorin tekijävalinta). Voidaan poistaa kokonaan käytöstä
 * `rytkoset_theme_enable_security_hardening` -suodattimella.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_security_hardening_enabled' ) ) {
	/**
	 * Onko teeman tietoturvakovennukset päällä.
	 *
	 * @return bool
	 */
	function rytkoset_theme_security_hardening_enabled() {
		return (bool) apply_filters( 'rytkoset_theme_enable_security_hardening', true );
	}
}

if ( ! function_exists( 'rytkoset_theme_restrict_rest_user_endpoints' ) ) {
	/**
	 * Poistaa REST API:n käyttäjäpäätepisteet kirjautumattomilta.
	 *
	 * `/wp/v2/users` ja `/wp/v2/users/<id>` paljastavat oletuksena sivuston
	 * käyttäjien nimet ja slugit kenelle tahansa. Kirjautuneille käyttäjille
	 * (joilla on tarvittavat oikeudet) päätepisteet säilyvät.
	 *
	 * @param array $endpoints REST-päätepisteet.
	 * @return array
	 */
	function rytkoset_theme_restrict_rest_user_endpoints( $endpoints ) {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return $endpoints;
		}

		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}

		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}

		return $endpoints;
	}
}
add_filter( 'rest_endpoints', 'rytkoset_theme_restrict_rest_user_endpoints' );

if ( ! function_exists( 'rytkoset_theme_block_author_enumeration' ) ) {
	/**
	 * Estää `?author=N` -numerokyselyllä tehtävän käyttäjänimen paljastuksen.
	 *
	 * Oletuksena WordPress ohjaa `/?author=1` osoitteeseen
	 * `/author/<kirjautumisnimi>/`, jolloin numerolla voi kartoittaa
	 * kirjautumisnimet. Estetään kysely kirjautumattomilta etusivun puolella;
	 * `/author/<slug>/` -arkistot säilyvät ennallaan.
	 */
	function rytkoset_theme_block_author_enumeration() {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return;
		}

		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_GET['author'] ) ) {
			return;
		}

		// Vain numeerinen author-parametri on luettelointiyritys.
		$author = sanitize_text_field( wp_unslash( $_GET['author'] ) );
		if ( '' === $author || ! preg_match( '/^\d+$/', $author ) ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
// Prioriteetti 0 ajaa eston ennen coren redirect_canonical()-funktiota
// (template_redirect, prioriteetti 10), joka muutoin ohjaisi `?author=N` ->
// `/author/<slug>/` ja paljastaisi kirjautumisnimen jo ennen tätä estoa.
add_action( 'template_redirect', 'rytkoset_theme_block_author_enumeration', 0 );

if ( ! function_exists( 'rytkoset_theme_remove_users_sitemap_provider' ) ) {
	/**
	 * Poistaa coren käyttäjäsitemapin (`/wp-sitemap-users-1.xml`).
	 *
	 * WordPress 5.5+ julkaisee sitemapin, joka listaa kaikki julkaistuja
	 * postauksia tehneet tekijät ja paljastaa heidän `/author/<nicename>/`
	 * -arkistonsa. Tämä on REST- ja `?author=N`-estoista erillinen, edelleen
	 * avoin käyttäjien luettelointivektori. Muut sitemap-providerit (postit,
	 * sivut, taksonomiat) säilyvät.
	 *
	 * @param WP_Sitemaps_Provider $provider Sitemap-provider.
	 * @param string               $name     Providerin nimi.
	 * @return WP_Sitemaps_Provider|false `false` poistaa providerin.
	 */
	function rytkoset_theme_remove_users_sitemap_provider( $provider, $name ) {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return $provider;
		}

		return ( 'users' === $name ) ? false : $provider;
	}
}
add_filter( 'wp_sitemaps_add_provider', 'rytkoset_theme_remove_users_sitemap_provider', 10, 2 );

if ( ! function_exists( 'rytkoset_theme_xmlrpc_disabled' ) ) {
	/**
	 * Onko XML-RPC poistettu käytöstä.
	 *
	 * `xmlrpc.php` on yleinen brute-force- ja DDoS-vahvistuskohde
	 * (`system.multicall`, `pingback.ping`). Sivusto ei käytä XML-RPC:tä
	 * (ei Jetpackia eikä mobiilisovellusliitäntää), joten se voidaan estää.
	 *
	 * @return bool
	 */
	function rytkoset_theme_xmlrpc_disabled() {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return false;
		}

		return (bool) apply_filters( 'rytkoset_theme_disable_xmlrpc', true );
	}
}

if ( ! function_exists( 'rytkoset_theme_remove_pingback_methods' ) ) {
	/**
	 * Poistaa pingback-metodit XML-RPC-rajapinnasta.
	 *
	 * `pingback.ping` mahdollistaa sivuston käytön DDoS-heijastimena;
	 * poistetaan myös vaikka itse XML-RPC olisi muuten päällä.
	 *
	 * @param array $methods XML-RPC-metodit.
	 * @return array
	 */
	function rytkoset_theme_remove_pingback_methods( $methods ) {
		if ( ! rytkoset_theme_xmlrpc_disabled() ) {
			return $methods;
		}

		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );

		return $methods;
	}
}
add_filter( 'xmlrpc_methods', 'rytkoset_theme_remove_pingback_methods' );

if ( ! function_exists( 'rytkoset_theme_remove_pingback_header' ) ) {
	/**
	 * Poistaa `X-Pingback`-otsakkeen, joka mainostaa XML-RPC-päätepistettä.
	 *
	 * @param array $headers HTTP-otsakkeet.
	 * @return array
	 */
	function rytkoset_theme_remove_pingback_header( $headers ) {
		if ( ! rytkoset_theme_xmlrpc_disabled() ) {
			return $headers;
		}

		unset( $headers['X-Pingback'] );

		return $headers;
	}
}
add_filter( 'wp_headers', 'rytkoset_theme_remove_pingback_header' );

// Estää kaikki todennusta vaativat XML-RPC-metodit (mm. system.multicall).
add_filter(
	'xmlrpc_enabled',
	function ( $enabled ) {
		return rytkoset_theme_xmlrpc_disabled() ? false : $enabled;
	}
);

if ( ! function_exists( 'rytkoset_theme_send_security_headers' ) ) {
	/**
	 * Lähettää selaintason tietoturvaotsakkeet etusivun pyynnöille.
	 *
	 * Vain sisältöä rikkomattomat otsakkeet. HSTS jätetään palvelin-/HTTPS-tasolle
	 * ja Content-Security-Policy palvelintyöksi, koska tiukka CSP rikkoisi
	 * wp-adminin, WooCommercen ja Mollien hostatun iframen.
	 *
	 * X-Frame-Options (SAMEORIGIN) estää sivuston upottamisen toisen sivuston
	 * kehykseen (clickjacking); albumivideoiden YouTube-iframet ovat oman
	 * sivun lapsia, joten ne säilyvät.
	 */
	function rytkoset_theme_send_security_headers() {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return;
		}

		// wp-admin lähettää omat otsakkeensa; ei puututa hallintaan eikä editoriin.
		if ( is_admin() ) {
			return;
		}

		$headers = array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
		);

		/**
		 * Suodata lähetettävät tietoturvaotsakkeet. Tyhjä arvo ohittaa otsakkeen.
		 *
		 * @param array $headers Otsakenimi => arvo.
		 */
		$headers = apply_filters( 'rytkoset_theme_security_headers', $headers );

		foreach ( $headers as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}

			header( sprintf( '%s: %s', $name, $value ) );
		}
	}
}
add_action( 'send_headers', 'rytkoset_theme_send_security_headers' );

if ( ! function_exists( 'rytkoset_theme_render_registration_honeypot' ) ) {
	/**
	 * Renderöi piilotetun honeypot-kentän rekisteröitymislomakkeeseen.
	 *
	 * Ihminen ei näe kenttää (piilotettu pois ruudulta, aria-hidden,
	 * tabindex -1), joten se jää tyhjäksi. Lomakkeet automaattisesti
	 * täyttävät botit kirjoittavat siihen ja paljastuvat. Tarkistus tehdään
	 * `rytkoset_theme_filter_registration_spam()`-funktiossa.
	 */
	function rytkoset_theme_render_registration_honeypot() {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return;
		}
		?>
		<p class="rytkoset-hp-field" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
			<label for="rytkoset_url"><?php esc_html_e( 'Jätä tämä kenttä tyhjäksi', 'rytkoset-theme' ); ?></label>
			<input type="text" name="rytkoset_url" id="rytkoset_url" value="" tabindex="-1" autocomplete="off" />
		</p>
		<?php
	}
}
add_action( 'register_form', 'rytkoset_theme_render_registration_honeypot' );

if ( ! function_exists( 'rytkoset_theme_filter_registration_spam' ) ) {
	/**
	 * Hylkää roskarekisteröinnit: täytetty honeypot tai estetty sähköpostidomain.
	 *
	 * Estetyt domain-päätteet ovat oletuksena uhkapeli-TLD:itä, joita
	 * sukuseuran jäsenet eivät käytä. Listaa voi laajentaa suodattimella
	 * `rytkoset_theme_blocked_registration_email_patterns`.
	 *
	 * @param WP_Error $errors               Rekisteröinnin virheet.
	 * @param string   $sanitized_user_login Käyttäjätunnus.
	 * @param string   $user_email           Sähköpostiosoite.
	 * @return WP_Error
	 */
	function rytkoset_theme_filter_registration_spam( $errors, $sanitized_user_login, $user_email ) {
		if ( ! rytkoset_theme_security_hardening_enabled() ) {
			return $errors;
		}

		// Honeypot: piilokentän pitää olla tyhjä.
		if ( ! empty( $_POST['rytkoset_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Anti-spam check on the public registration form.
			$errors->add( 'rytkoset_spam', __( 'Rekisteröityminen estettiin. Yritä uudelleen.', 'rytkoset-theme' ) );
			return $errors;
		}

		/**
		 * Suodata estetyt sähköpostidomainin päätteet (esim. `.casino`).
		 * Vertailu tehdään domainin loppuosaan.
		 *
		 * @param array $patterns Estetyt päätteet/domainit.
		 */
		$blocked = apply_filters(
			'rytkoset_theme_blocked_registration_email_patterns',
			array( '.casino', '.bet', '.poker' )
		);

		$email  = strtolower( $user_email );
		$at     = strrpos( $email, '@' );
		$domain = false !== $at ? substr( $email, $at + 1 ) : $email;

		foreach ( $blocked as $pattern ) {
			$pattern = strtolower( (string) $pattern );
			if ( '' !== $pattern && str_ends_with( $domain, $pattern ) ) {
				$errors->add( 'rytkoset_blocked_email', __( 'Tällä sähköpostiosoitteella ei voi rekisteröityä.', 'rytkoset-theme' ) );
				break;
			}
		}

		return $errors;
	}
}
add_filter( 'registration_errors', 'rytkoset_theme_filter_registration_spam', 10, 3 );
