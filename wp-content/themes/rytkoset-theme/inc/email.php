<?php
/**
 * Sähköpostin oletuslähettäjä teeman lähettämille viesteille.
 *
 * Korvaa WordPressin ruman oletuslähettäjän (WordPress <wordpress@domain>)
 * yhdistyksen nimellä ja yhteysosoitteella. Suodattimet vaikuttavat VAIN
 * silloin, kun arvo on WordPressin oletus — WooCommercen omat tilausviestit
 * ja AcyMailing asettavat oman lähettäjänsä, eikä niihin kosketa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palauttaa teeman lähettämien viestien lähettäjänimen.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_mail_from_name' ) ) {
	function rytkoset_theme_get_mail_from_name() {
		/**
		 * Suodattaa teeman sähköpostien lähettäjänimen.
		 *
		 * @param string $name Lähettäjänimi.
		 */
		return apply_filters( 'rytkoset_theme_mail_from_name', 'Rytkösten sukuseura ry' );
	}
}

/**
 * Korvaa WordPressin oletuslähettäjäosoitteen yhteysosoitteella.
 *
 * @param string $from_email Nykyinen lähettäjäosoite.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_filter_mail_from' ) ) {
	function rytkoset_theme_filter_mail_from( $from_email ) {
		// Korvaa vain WordPressin oletus (wordpress@...), jotta WooCommercen
		// ja muiden lisäosien omat lähettäjät säilyvät.
		if ( is_string( $from_email ) && 0 === strpos( $from_email, 'wordpress@' ) ) {
			return rytkoset_theme_get_contact_email();
		}

		return $from_email;
	}
}
add_filter( 'wp_mail_from', 'rytkoset_theme_filter_mail_from' );

/**
 * Korvaa WordPressin oletuslähettäjänimen ("WordPress") yhdistyksen nimellä.
 *
 * @param string $from_name Nykyinen lähettäjänimi.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_filter_mail_from_name' ) ) {
	function rytkoset_theme_filter_mail_from_name( $from_name ) {
		if ( 'WordPress' === $from_name ) {
			return rytkoset_theme_get_mail_from_name();
		}

		return $from_name;
	}
}
add_filter( 'wp_mail_from_name', 'rytkoset_theme_filter_mail_from_name' );
