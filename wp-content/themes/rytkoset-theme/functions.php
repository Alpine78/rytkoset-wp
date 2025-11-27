<?php
/**
 * Rytköset Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rytkoset_theme_setup() {
	// Otsikkotagi WP:n hallintaan
	add_theme_support( 'title-tag' );

	// Esikatselukuvat
	add_theme_support( 'post-thumbnails' );

	// HTML5-markup
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' )
	);

	// Navigaatiomenut
	register_nav_menus(
		array(
			'primary'   => __( 'Päävalikko', 'rytkoset-theme' ),
			'footer'    => __( 'Footer-valikko', 'rytkoset-theme' ),
			'account'   => __( 'Käyttäjä/tili-valikko', 'rytkoset-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'rytkoset_theme_setup' );

/**
 * Lataa tyylit ja skriptit.
 */
function rytkoset_theme_scripts() {
    $theme_version = wp_get_theme()->get( 'Version' );

    // Teeman päätyyli (style.css) – WordPress hoitaa tämän usein automaattisesti, mutta tehdään eksplisiittisesti.
    wp_enqueue_style(
        'rytkoset-theme-style',
        get_stylesheet_uri(),
        array(),
        $theme_version
    );

    // Mobiilivalikon JS
    wp_enqueue_script(
        'rytkoset-theme-main',
        get_template_directory_uri() . '/assets/js/main.js',
        array(),
        $theme_version,
        true // footer
    );
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_scripts' );

