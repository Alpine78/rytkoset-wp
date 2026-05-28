<?php
/**
 * SVG-ikonien inline-apufunktio.
 *
 * Yksi yhtenäinen tapa inlinettaa teeman omat SVG-glyfit niin, että
 * `currentColor`-värjäys toimii. Ikonitiedostot ovat ainoa totuuden lähde —
 * uuden ikonin lisäys = yksi tiedosto kansioon, ei koodimuutosta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_inline_icon' ) ) {
	/**
	 * Palauttaa teeman SVG-ikonin inline-merkkauksena.
	 *
	 * Lukee VAIN kiinteästä teemahakemistosta `assets/icons/{$dir}/{$name}.svg`.
	 * `$name` ja `$dir` validoidaan whitelistilla → ei polkuhyökkäystä.
	 *
	 * @param string $name Ikonin tiedostonimi ilman päätettä (esim. 'Facebook', 'copy').
	 * @param string $dir  Alikansio: 'social' tai 'ui'. Oletus 'social'.
	 * @return string SVG-merkkaus, tai '' jos tiedosto puuttuu tai syöte on virheellinen.
	 */
	function rytkoset_theme_inline_icon( $name, $dir = 'social' ) {
		static $cache = array();

		$allowed_dirs = array( 'social', 'ui' );

		if ( ! in_array( $dir, $allowed_dirs, true ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) $name ) ) {
			return '';
		}

		$cache_key = $dir . '/' . $name;

		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$icons_root = get_template_directory() . '/assets/icons';
		$path       = $icons_root . '/' . $dir . '/' . $name . '.svg';
		$real_path  = realpath( $path );
		$real_root  = realpath( $icons_root );

		// Polkuhyökkäyssuoja: tiedoston on oltava icons-juuren sisällä.
		if ( false === $real_path || false === $real_root || 0 !== strpos( $real_path, $real_root ) ) {
			$cache[ $cache_key ] = '';
			return '';
		}

		$svg = file_get_contents( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $svg ) {
			$cache[ $cache_key ] = '';
			return '';
		}

		// Strippaa XML-julistus ja kommentit turvallisuuden ja siisteyden vuoksi.
		$svg = preg_replace( '/<\?xml.*?\?>/s', '', $svg );
		$svg = preg_replace( '/<!--.*?-->/s', '', $svg );
		$svg = trim( $svg );

		$cache[ $cache_key ] = $svg;

		return $svg;
	}
}
