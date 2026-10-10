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
add_action( 'customize_controls_enqueue_scripts', 'rytkoset_theme_enqueue_admin_appearance' );

if ( ! function_exists( 'rytkoset_theme_add_admin_editor_content_styles' ) ) {
	/**
	 * Gives the block editor canvas the public theme's base text styles (#700).
	 *
	 * Added through the editor settings instead of add_theme_support( 'editor-styles' ):
	 * that support flag makes WordPress 7 replace "Lohkomallit" with the full Site
	 * Editor under Ulkoasu for this classic theme. The editor still scopes these
	 * rules to its canvas. Follows the same emergency switch as the admin stylesheet.
	 *
	 * @param array $settings Block editor settings.
	 * @return array
	 */
	function rytkoset_theme_add_admin_editor_content_styles( $settings ) {
		$disabled_by_constant = defined( 'RYTKOSET_DISABLE_ADMIN_APPEARANCE' ) && RYTKOSET_DISABLE_ADMIN_APPEARANCE;

		if ( ! is_array( $settings ) || ! rytkoset_theme_admin_appearance_should_load( $disabled_by_constant ) ) {
			return $settings;
		}

		$path = get_template_directory() . '/assets/css/editor-style.css';

		if ( ! is_readable( $path ) ) {
			return $settings;
		}

		$settings['styles']   = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$settings['styles'][] = array(
			'css'            => (string) file_get_contents( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file, not a remote request.
			'__unstableType' => 'theme',
			'isGlobalStyles' => false,
		);

		return $settings;
	}
}
add_filter( 'block_editor_settings_all', 'rytkoset_theme_add_admin_editor_content_styles' );
