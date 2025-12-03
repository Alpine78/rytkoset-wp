<?php
/**
 * Sosiaalisen median linkit – keskitetty listaus, jota voidaan käyttää useissa paikoissa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_social_links' ) ) {
	/**
	 * Palauttaa sosiaalisen median linkit.
	 *
	 * @return array[] Lista sosiaalisen median linkkejä.
	 */
	function rytkoset_theme_get_social_links() {
		return array(
			array(
				'label' => 'Facebook',
				'url'   => 'https://www.facebook.com/rytkoset',
				'icon'  => 'facebook',
			),
			array(
				'label' => 'YouTube',
				'url'   => 'https://www.youtube.com/@rytkoset',
				'icon'  => 'youtube',
			),
			array(
				'label' => 'Instagram',
				'url'   => 'https://www.instagram.com/rytkoset/',
				'icon'  => 'instagram',
			),
			array(
				'label' => 'X',
				'url'   => 'https://x.com/rytkoset',
				'icon'  => 'x',
			),
		);
	}
}
