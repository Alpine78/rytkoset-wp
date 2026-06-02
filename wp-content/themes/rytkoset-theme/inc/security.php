<?php
/**
 * Tietoturvan kovennukset (#336).
 *
 * Estää käyttäjien luetteloinnin (user enumeration), jolla bottiverkot
 * keräävät kirjautumisnimiä brute-force-hyökkäyksiä varten.
 *
 * Tämä siivu kattaa kaksi klassista automaattisen luetteloinnin vektoria:
 *   1. REST API:n `/wp/v2/users` -kokoelma kirjautumattomilta käyttäjiltä
 *   2. `?author=N` -numerokyselyllä tehtävä kirjautumisnimen paljastus
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
add_action( 'template_redirect', 'rytkoset_theme_block_author_enumeration' );

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
