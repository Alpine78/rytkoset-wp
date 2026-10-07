<?php
/**
 * Admin appearance: association brand colors and fonts in wp-admin.
 *
 * Plan and slices: GitHub epic #690. Operations and post-update checks:
 * docs/admin-ulkoasu.md.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_admin_appearance_should_load' ) ) {
	/**
	 * Decides whether the admin stylesheet loads.
	 *
	 * The constant is the emergency switch for wp-config.php; the filter is
	 * for code. Either one can turn the restyling off.
	 *
	 * @param bool $disabled_by_constant Whether RYTKOSET_DISABLE_ADMIN_APPEARANCE is true.
	 * @return bool
	 */
	function rytkoset_theme_admin_appearance_should_load( $disabled_by_constant ) {
		if ( $disabled_by_constant ) {
			return false;
		}

		return (bool) apply_filters( 'rytkoset_theme_enable_admin_appearance', true );
	}
}

if ( ! function_exists( 'rytkoset_theme_enqueue_admin_appearance' ) ) {
	/**
	 * Loads the admin stylesheet after WordPress's own admin color scheme.
	 */
	function rytkoset_theme_enqueue_admin_appearance() {
		$disabled_by_constant = defined( 'RYTKOSET_DISABLE_ADMIN_APPEARANCE' ) && RYTKOSET_DISABLE_ADMIN_APPEARANCE;

		if ( ! rytkoset_theme_admin_appearance_should_load( $disabled_by_constant ) ) {
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
