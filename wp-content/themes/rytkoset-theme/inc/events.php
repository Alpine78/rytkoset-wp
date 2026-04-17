<?php
/**
 * Tapahtumat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_register_event_cpt' ) ) {
	/**
	 * Rekisteröi tapahtumien CPT:n.
	 */
	function rytkoset_theme_register_event_cpt() {
		$labels = array(
			'name'               => __( 'Tapahtumat', 'rytkoset-theme' ),
			'singular_name'      => __( 'Tapahtuma', 'rytkoset-theme' ),
			'menu_name'          => __( 'Tapahtumat', 'rytkoset-theme' ),
			'name_admin_bar'     => __( 'Tapahtuma', 'rytkoset-theme' ),
			'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
			'add_new_item'       => __( 'Lisää uusi tapahtuma', 'rytkoset-theme' ),
			'new_item'           => __( 'Uusi tapahtuma', 'rytkoset-theme' ),
			'edit_item'          => __( 'Muokkaa tapahtumaa', 'rytkoset-theme' ),
			'view_item'          => __( 'Näytä tapahtuma', 'rytkoset-theme' ),
			'all_items'          => __( 'Kaikki tapahtumat', 'rytkoset-theme' ),
			'search_items'       => __( 'Etsi tapahtumia', 'rytkoset-theme' ),
			'not_found'          => __( 'Tapahtumia ei löytynyt.', 'rytkoset-theme' ),
			'not_found_in_trash' => __( 'Roskakorissa ei ole tapahtumia.', 'rytkoset-theme' ),
		);

		$args = array(
			'labels'        => $labels,
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-calendar-alt',
			'show_in_rest'  => true,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'rewrite'       => array(
				'slug'       => 'tapahtumat',
				'with_front' => false,
			),
		);

		register_post_type( 'event', $args );
	}
}
add_action( 'init', 'rytkoset_theme_register_event_cpt' );
