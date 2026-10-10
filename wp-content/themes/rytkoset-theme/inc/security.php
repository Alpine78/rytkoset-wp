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
 *   4. `wp-login.php`:n tunnuksen olemassaolon paljastavat virheilmoitukset
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public request inspection.
		if ( ! isset( $_GET['author'] ) ) {
			return;
		}

		// Vain numeerinen author-parametri on luettelointiyritys.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public request inspection.
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

if ( ! function_exists( 'rytkoset_theme_filter_login_errors' ) ) {
	/**
	 * Yhdistää tunnuksen olemassaolon paljastavat kirjautumisvirheet.
	 *
	 * Tuntematon käyttäjänimi tai sähköposti ja olemassa olevan tunnuksen
	 * väärä salasana näyttävät saman viestin. Muut wp-login.php:n virheet
	 * ja ilmoitukset säilyvät ennallaan.
	 *
	 * @param WP_Error $errors Kirjautumissivun virheet ja ilmoitukset.
	 * @return WP_Error
	 */
	function rytkoset_theme_filter_login_errors( $errors ) {
		if ( ! rytkoset_theme_security_hardening_enabled() || ! is_wp_error( $errors ) ) {
			return $errors;
		}

		$credential_error_codes = array(
			'invalid_username',
			'invalid_email',
			'incorrect_password',
		);
		$matched_error_codes    = array_intersect( $credential_error_codes, $errors->get_error_codes() );

		if ( empty( $matched_error_codes ) ) {
			return $errors;
		}

		foreach ( $matched_error_codes as $error_code ) {
			$errors->remove( $error_code );
		}

		$errors->add(
			'rytkoset_invalid_credentials',
			__( 'Kirjautuminen epäonnistui. Tarkista käyttäjätunnus tai sähköpostiosoite ja salasana.', 'rytkoset-theme' )
		);

		return $errors;
	}
}
add_filter( 'wp_login_errors', 'rytkoset_theme_filter_login_errors' );

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

		$email  = strtolower( $user_email );
		$at     = strrpos( $email, '@' );
		$local  = false !== $at ? substr( $email, 0, $at ) : $email;
		$domain = false !== $at ? substr( $email, $at + 1 ) : $email;

		// RFC 5321: paikallisosa ei saa päättyä pisteeseen.
		if ( str_ends_with( $local, '.' ) ) {
			$errors->add( 'rytkoset_blocked_email', __( 'Tällä sähköpostiosoitteella ei voi rekisteröityä.', 'rytkoset-theme' ) );
			return $errors;
		}

		// Yli 6 pistettä paikallisosassa on botti-signaali (normaali nimi ei käytä niitä näin paljon).
		if ( substr_count( $local, '.' ) > 6 ) {
			$errors->add( 'rytkoset_blocked_email', __( 'Tällä sähköpostiosoitteella ei voi rekisteröityä.', 'rytkoset-theme' ) );
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

if ( ! function_exists( 'rytkoset_theme_text_contains_personal_id' ) ) {
	/**
	 * Tells whether a text contains something shaped like a Finnish personal
	 * identity code (henkilötunnus, #726).
	 *
	 * Matches ddmmyy with a valid day and month, a century sign (-, +, A-F,
	 * U-Y), three digits and a check character, in any case and also inside a
	 * longer string. The check character is not validated: a mistyped code is
	 * still personal data.
	 *
	 * @param string $text Text to inspect, e.g. a username.
	 * @return bool
	 */
	function rytkoset_theme_text_contains_personal_id( $text ) {
		// No word boundaries: a code is personal data also when glued to other characters.
		return 1 === preg_match( '/(?:0[1-9]|[12][0-9]|3[01])(?:0[1-9]|1[0-2])[0-9]{2}[-+A-FU-Y][0-9]{3}[0-9A-Y]/i', (string) $text );
	}
}

if ( ! function_exists( 'rytkoset_theme_block_personal_id_usernames_enabled' ) ) {
	/**
	 * Tells whether usernames shaped like a personal identity code are blocked.
	 *
	 * @return bool
	 */
	function rytkoset_theme_block_personal_id_usernames_enabled() {
		// Deliberately independent of rytkoset_theme_enable_security_hardening: this is
		// personal data protection, not spam hardening.
		/**
		 * Filters whether new usernames shaped like a personal identity code are blocked (#726).
		 *
		 * @param bool $enabled Whether the block is on.
		 */
		return (bool) apply_filters( 'rytkoset_theme_block_personal_id_usernames', true );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_personal_id_username_error' ) ) {
	/**
	 * Returns the error shown when a typed username looks like a personal identity code.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_personal_id_username_error() {
		return __( 'Käyttäjätunnus näyttää henkilötunnukselta. Valitse toinen tunnus: käyttäjätunnus näkyy sivuston ylläpidossa, eikä henkilötunnusta saa käyttää siinä.', 'rytkoset-theme' );
	}
}

if ( ! function_exists( 'rytkoset_theme_reject_personal_id_username' ) ) {
	/**
	 * Rejects a typed username shaped like a personal identity code on
	 * WordPress registration and WooCommerce account creation.
	 *
	 * WooCommerce runs woocommerce_registration_errors also for usernames it
	 * generated itself; those are replaced earlier in
	 * rytkoset_theme_replace_generated_personal_id_username(), so only typed
	 * usernames reach this error.
	 *
	 * @param WP_Error $errors   Registration errors.
	 * @param string   $username Username.
	 * @return WP_Error
	 */
	function rytkoset_theme_reject_personal_id_username( $errors, $username ) {
		if ( $errors instanceof WP_Error && rytkoset_theme_block_personal_id_usernames_enabled() && rytkoset_theme_text_contains_personal_id( $username ) ) {
			$errors->add( 'rytkoset_personal_id_username', rytkoset_theme_get_personal_id_username_error() );
		}

		return $errors;
	}
}
add_filter( 'registration_errors', 'rytkoset_theme_reject_personal_id_username', 10, 2 );
add_filter( 'woocommerce_registration_errors', 'rytkoset_theme_reject_personal_id_username', 10, 2 );

if ( ! function_exists( 'rytkoset_theme_reject_personal_id_username_in_admin' ) ) {
	/**
	 * Rejects the same usernames when an administrator adds a user. Existing
	 * users are not touched: WordPress does not change a login on update.
	 *
	 * @param WP_Error $errors Errors (passed by reference by core).
	 * @param bool     $update Whether an existing user is updated.
	 * @param stdClass $user   User data about to be saved.
	 * @return void
	 */
	function rytkoset_theme_reject_personal_id_username_in_admin( $errors, $update, $user ) {
		if ( $update || ! is_object( $user ) || ! isset( $user->user_login ) ) {
			return;
		}

		rytkoset_theme_reject_personal_id_username( $errors, (string) $user->user_login );
	}
}
add_action( 'user_profile_update_errors', 'rytkoset_theme_reject_personal_id_username_in_admin', 10, 3 );

if ( ! function_exists( 'rytkoset_theme_replace_generated_personal_id_username' ) ) {
	/**
	 * Replaces a username WooCommerce generated from a name or an email address
	 * when it looks like a personal identity code. The customer did not choose
	 * it, so showing them an error would not help.
	 *
	 * @param string               $username      Generated username.
	 * @param string               $email         New customer email address.
	 * @param array<string, mixed> $new_user_args New user args, maybe with first and last name.
	 * @return string
	 */
	function rytkoset_theme_replace_generated_personal_id_username( $username, $email = '', $new_user_args = array() ) {
		if ( ! rytkoset_theme_block_personal_id_usernames_enabled() ) {
			return $username;
		}

		// The username is already sanitized, which drops a "+" century sign, so the
		// sources it was built from (email local part, first and last name) are checked too.
		$at      = strrpos( (string) $email, '@' );
		$sources = array(
			(string) $username,
			false !== $at ? substr( (string) $email, 0, $at ) : (string) $email,
			(string) ( $new_user_args['first_name'] ?? '' ),
			(string) ( $new_user_args['last_name'] ?? '' ),
		);

		if ( ! rytkoset_theme_text_contains_personal_id( implode( ' ', $sources ) ) ) {
			return $username;
		}

		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$candidate = 'asiakas-' . str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

			if ( ! username_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return 'asiakas-' . wp_generate_password( 12, false );
	}
}
add_filter( 'woocommerce_new_customer_username', 'rytkoset_theme_replace_generated_personal_id_username', 10, 3 );

if ( ! function_exists( 'rytkoset_theme_prevent_personal_id_user_insert' ) ) {
	/**
	 * Last line of defence for new users created without a form, e.g. through
	 * the REST API (/wp/v2/users), WP-CLI or another plugin calling
	 * wp_insert_user() directly. Returning an empty array makes core stop with
	 * its own error. Updates of existing users are never touched.
	 *
	 * The raw requested login is checked too, because core's sanitizing drops a
	 * "+" century sign from the stored one.
	 *
	 * @param array<string, mixed> $data     User data about to be inserted.
	 * @param bool                 $update   Whether an existing user is updated.
	 * @param int|null             $user_id  User ID, null for a new user.
	 * @param array<string, mixed> $userdata Raw data passed to wp_insert_user().
	 * @return array<string, mixed>
	 */
	function rytkoset_theme_prevent_personal_id_user_insert( $data, $update, $user_id = null, $userdata = array() ) {
		if ( $update || ! is_array( $data ) || ! rytkoset_theme_block_personal_id_usernames_enabled() ) {
			return $data;
		}

		$logins = (string) ( $data['user_login'] ?? '' ) . ' ' . ( is_array( $userdata ) ? (string) ( $userdata['user_login'] ?? '' ) : '' );

		return rytkoset_theme_text_contains_personal_id( $logins ) ? array() : $data;
	}
}
add_filter( 'wp_pre_insert_user_data', 'rytkoset_theme_prevent_personal_id_user_insert', 10, 4 );

if ( ! function_exists( 'rytkoset_theme_reject_personal_id_rest_user' ) ) {
	/**
	 * Gives a REST user creation a clear 400 error instead of the generic
	 * insert failure from rytkoset_theme_prevent_personal_id_user_insert().
	 *
	 * Runs before the endpoint callback: core's users controller does not check
	 * a WP_Error returned from rest_pre_insert_user.
	 *
	 * @param mixed           $response Response so far (null to continue).
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	function rytkoset_theme_reject_personal_id_rest_user( $response, $handler, $request ) {
		if ( null !== $response || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! rytkoset_theme_block_personal_id_usernames_enabled() ) {
			return $response;
		}

		if ( 'POST' !== $request->get_method() || '/wp/v2/users' !== untrailingslashit( $request->get_route() ) ) {
			return $response;
		}

		if ( rytkoset_theme_text_contains_personal_id( (string) $request->get_param( 'username' ) ) ) {
			return new WP_Error( 'rytkoset_personal_id_username', rytkoset_theme_get_personal_id_username_error(), array( 'status' => 400 ) );
		}

		return $response;
	}
}
add_filter( 'rest_request_before_callbacks', 'rytkoset_theme_reject_personal_id_rest_user', 10, 3 );
