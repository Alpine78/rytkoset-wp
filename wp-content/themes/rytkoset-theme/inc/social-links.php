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
				'url'   => 'https://www.facebook.com/',
				'icon'  => 'facebook',
			),
			array(
				'label' => 'Instagram',
				'url'   => 'https://www.instagram.com/',
				'icon'  => 'instagram',
			),
			array(
				'label' => 'X',
				'url'   => 'https://www.x.com/',
				'icon'  => 'x',
			),
			array(
				'label' => 'YouTube',
				'url'   => 'https://www.youtube.com/',
				'icon'  => 'youtube',
			),
		);
	}
}
