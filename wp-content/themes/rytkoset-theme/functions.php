<?php
/**
 * Rytköset Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/social-links.php';

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
        $current_user = wp_get_current_user();

        if ( ! $current_user instanceof WP_User ) {
                return;
        }

        $display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        $profile_url  = admin_url( 'profile.php' );
        $avatar       = wp_kses_post( get_avatar( $current_user->ID, 40 ) );

        echo '<ul class="account-nav__list">';
        echo '<li class="menu-item menu-item-has-children account-menu__user">';
        echo '<button type="button" class="account-menu__user-trigger" aria-haspopup="true" aria-expanded="false">';
        echo '<span class="account-menu__avatar">' . $avatar . '</span>';
        echo '<span class="account-menu__meta">';
        echo '<span class="account-menu__greeting">' . esc_html__( 'Tervehdys,', 'rytkoset-theme' ) . '</span>';
        echo '<span class="account-menu__name">' . esc_html( $display_name ) . '</span>';
        echo '</span>';
        echo '</button>';
        echo '<ul class="sub-menu" aria-label="' . esc_attr__( 'Tilivalikko', 'rytkoset-theme' ) . '">';
        echo '<li class="menu-item account-menu__summary">';
        echo '<div class="account-menu__summary-inner">';
        echo '<span class="account-menu__avatar">' . $avatar . '</span>';
        echo '<span class="account-menu__meta">';
        echo '<span class="account-menu__greeting">' . esc_html__( 'Tervehdys,', 'rytkoset-theme' ) . '</span>';
        echo '<span class="account-menu__name">' . esc_html( $display_name ) . '</span>';
        echo '</span>';
        echo '</div>';
        echo '</li>';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( $profile_url ) . '">';
        echo esc_html__( 'Muokkaa profiilia', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( rytkoset_theme_get_logout_url() ) . '">';
        echo esc_html__( 'Kirjaudu ulos', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';
        echo '</ul>';
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

