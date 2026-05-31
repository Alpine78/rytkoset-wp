<?php
/**
 * WooCommerce "Tulossa pian" -templaatin ohjaus teeman tiedostoon.
 *
 * WooCommerce rekisteröi oman block-templaattinsa slugille "coming-soon"
 * (woocommerce//coming-soon). WordPressin get_query_template() ajaa
 * locate_block_template():n locate_template():n jälkeen, joten WC:n
 * block-templaatti ohittaa teeman coming-soon.php:n.
 *
 * Tämä filtteri pakottaa coming-soon-templaatiksi teeman oman tiedoston
 * — se ajetaan get_query_template():n viimeisenä vaiheena (ks. WP core).
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pakottaa WooCommercen "Tulossa pian" -sivun käyttämään teeman omaa
 * coming-soon.php:tä WC:n rekisteröimän block-templaatin sijaan.
 *
 * @param string $template Block-templaatin tai teeman tiedoston polku, jonka
 *                         locate_block_template() palautti.
 * @return string Teeman coming-soon.php-tiedoston polku, jos olemassa.
 */
function rytkoset_theme_force_coming_soon_template( $template ) {
	$theme_template = get_template_directory() . '/coming-soon.php';

	if ( file_exists( $theme_template ) ) {
		return $theme_template;
	}

	return $template;
}
add_filter( 'coming-soon_template', 'rytkoset_theme_force_coming_soon_template', 99 );
