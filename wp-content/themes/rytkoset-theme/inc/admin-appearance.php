<?php
/**
 * Admin appearance: association brand colors and fonts in wp-admin.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_enqueue_admin_appearance' ) ) {
	/**
	 * Loads the admin stylesheet after WordPress's own admin color scheme.
	 *
	 * The `rytkoset_theme_enable_admin_appearance` filter can switch the
	 * restyling off without removing the code.
	 */
	function rytkoset_theme_enqueue_admin_appearance() {
		if ( ! apply_filters( 'rytkoset_theme_enable_admin_appearance', true ) ) {
			return;
		}

		$path = get_template_directory() . '/assets/css/admin.css';

		wp_enqueue_style(
			'rytkoset-admin',
			get_template_directory_uri() . '/assets/css/admin.css',
			array( 'colors' ),
			rytkoset_theme_get_asset_version( $path )
		);
	}
}
add_action( 'admin_enqueue_scripts', 'rytkoset_theme_enqueue_admin_appearance' );
