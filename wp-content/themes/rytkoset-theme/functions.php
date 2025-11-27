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
 * Palauttaa uloskirjautumis-URL:n etusivulle ohjauksella.
 *
 * @return string Uloskirjautumis-URL.
 */
function rytkoset_theme_get_logout_url() {
        return wp_logout_url( home_url( '/' ) );
}

/**
 * Fallback-kutsu kirjautuneiden tilivalikolle.
 */
function rytkoset_theme_account_menu_logged_in_fallback() {
        echo '<ul class="account-nav__list">';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( admin_url( 'profile.php' ) ) . '">';
        echo esc_html__( 'Oma tili', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( rytkoset_theme_get_logout_url() ) . '">';
        echo esc_html__( 'Kirjaudu ulos', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';
        echo '</ul>';
}

/**
 * Fallback-kutsu vierailijoiden tilivalikolle.
 */
function rytkoset_theme_account_menu_logged_out_fallback() {
        echo '<ul class="account-nav__list">';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( wp_login_url() ) . '">';
        echo esc_html__( 'Kirjaudu', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';

        if ( get_option( 'users_can_register' ) && wp_registration_url() ) {
                echo '<li class="menu-item">';
                echo '<a href="' . esc_url( wp_registration_url() ) . '">';
                echo esc_html__( 'Rekisteröidy', 'rytkoset-theme' );
                echo '</a>';
                echo '</li>';
        }

        echo '</ul>';
}

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

