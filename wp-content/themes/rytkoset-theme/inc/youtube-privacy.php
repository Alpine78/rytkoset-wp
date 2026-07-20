<?php
/**
 * YouTube embed privacy hardening.
 *
 * Forces every YouTube oEmbed WordPress outputs to use YouTube's
 * privacy-enhanced host (youtube-nocookie.com), matching the album video
 * embeds built in inc/gallery-albums.php and the promise in the privacy
 * statement (docs/tietosuoja.md).
 *
 * Album videos already build their own nocookie URL, but content embeds added
 * with Gutenberg's embed block or a bare URL rely on WordPress oEmbed, which
 * defaults to www.youtube.com. The embed_oembed_html filter runs at output
 * time, so it also rewrites results cached in post meta and the bare-URL
 * embeds that WP_Embed::autoembed() resolves from the core/embed block.
 *
 * @package Rytkoset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_rewrite_youtube_embed_to_nocookie' ) ) {
	/**
	 * Rewrites youtube.com/embed iframe hosts to the privacy-enhanced domain.
	 *
	 * Only the host portion of a youtube.com/embed/ source is replaced, so the
	 * iframe title attribute (docs/media-saavutettavuus.md), query parameters
	 * and surrounding markup are preserved. Already-nocookie embeds and HTML
	 * without a YouTube embed pass through unchanged.
	 *
	 * @param string $html Embed HTML.
	 * @return string
	 */
	function rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, 'youtube.com/embed/' ) ) {
			return $html;
		}

		$rewritten = preg_replace(
			'#(https?:)?//(?:www\.)?youtube\.com/embed/#i',
			'$1//www.youtube-nocookie.com/embed/',
			$html
		);

		return is_string( $rewritten ) ? $rewritten : $html;
	}
}

add_filter( 'embed_oembed_html', 'rytkoset_theme_rewrite_youtube_embed_to_nocookie', 20 );
