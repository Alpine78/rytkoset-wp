<?php
/**
 * Finnish translations for WooCommerce core strings the bundled language pack does not cover.
 *
 * WooCommerce 11.0 added the customer email-verification feature
 * (Automattic\WooCommerce\Internal\CustomerEmailVerification\VerificationController).
 * It shows a prompt on My Account > Orders asking a logged-in customer to confirm
 * their email address so past guest orders can be linked to the account. The
 * bundled Finnish language pack does not yet contain these source strings, so the
 * notice and its button render in English. Translate them with a `gettext` filter
 * without editing WooCommerce's own files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_woocommerce_finnish_string_overrides' ) ) {
	/**
	 * Returns source string => Finnish translation overrides for the `woocommerce` text domain.
	 *
	 * All keys are WooCommerce core email-verification prompt and result strings that
	 * appear on the My Account > Orders page and its confirmation flow. The English
	 * source strings must match VerificationController verbatim, including the em dash
	 * in the throttled prompt.
	 *
	 * @return array<string, string>
	 */
	function rytkoset_theme_get_woocommerce_finnish_string_overrides() {
		return array(
			// Orders-page prompt and its call-to-action button.
			'Confirm your email address to check for past orders and link them to your account.' => 'Vahvista sähköpostiosoitteesi, jotta voimme etsiä aiemmat tilauksesi ja yhdistää ne käyttäjätiliisi.',
			'Confirm email address'                  => 'Vahvista sähköpostiosoite',
			'Confirm your email address to check for past orders. A confirmation link was sent recently — please check your inbox.' => 'Vahvista sähköpostiosoitteesi, jotta voimme etsiä aiemmat tilauksesi. Vahvistuslinkki on lähetetty äskettäin – tarkista sähköpostisi.',

			// One-off result notices shown after a send or confirm action.
			'A confirmation link has been sent to your email address. Please check your inbox.' => 'Vahvistuslinkki on lähetetty sähköpostiosoitteeseesi. Tarkista sähköpostisi.',
			'A confirmation link was sent recently. Please check your inbox, or wait a moment before requesting a new one.' => 'Vahvistuslinkki on lähetetty äskettäin. Tarkista sähköpostisi tai odota hetki ennen uuden linkin pyytämistä.',
			'Your email address has been confirmed.' => 'Sähköpostiosoitteesi on vahvistettu.',
			'This confirmation link is invalid or has expired. Please request a new one.' => 'Vahvistuslinkki on virheellinen tai vanhentunut. Pyydä uusi linkki.',
			'Unable to confirm this email while you are logged in to a different account. Please log out and open the link again.' => 'Sähköpostiosoitetta ei voi vahvistaa, kun olet kirjautuneena toiselle tilille. Kirjaudu ulos ja avaa linkki uudelleen.',
			'Invalid request. Please try again.'     => 'Virheellinen pyyntö. Yritä uudelleen.',

			// Shown when a logged-out visitor opens the verification link.
			'You need to be logged in to confirm your email address.' => 'Sinun on kirjauduttava sisään, jotta voit vahvistaa sähköpostiosoitteesi.',
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_woocommerce_finnish_strings' ) ) {
	/**
	 * Translates selected WooCommerce core strings to Finnish.
	 *
	 * @param string $translated Current translation.
	 * @param string $original   Source string.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	function rytkoset_theme_woocommerce_finnish_strings( $translated, $original, $domain ) {
		if ( 'woocommerce' !== $domain ) {
			return $translated;
		}

		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		if ( 0 !== strpos( (string) $locale, 'fi' ) ) {
			return $translated;
		}

		$overrides = rytkoset_theme_get_woocommerce_finnish_string_overrides();

		return isset( $overrides[ $original ] ) ? $overrides[ $original ] : $translated;
	}
}
add_filter( 'gettext', 'rytkoset_theme_woocommerce_finnish_strings', 10, 3 );
