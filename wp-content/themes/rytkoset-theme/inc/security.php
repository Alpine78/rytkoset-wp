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
