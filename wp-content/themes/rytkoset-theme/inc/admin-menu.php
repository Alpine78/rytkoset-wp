<?php
/**
 * Admin menu grouping and per-role cleanup (#699, part of epic #690).
 *
 * Groups the sidebar into Toiminta, Sisältö, Kauppa and Ylläpito, renames
 * AcyMailing to "Uutiskirje", removes technical admin bar items from
 * non-administrators, removes the per-user admin color scheme choice and
 * shows role names in Finnish. Visibility itself still comes from
 * capabilities; nothing here grants or hides access.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the menu groups in display order: group key => known top-level slugs
 * in their order inside the group.
 *
 * @return array<string, string[]>
 */
function rytkoset_theme_get_admin_menu_groups() {
	$groups = array(
		'toiminta' => array(
			'edit.php?post_type=rytkoset_event',
			'acymailing_dashboard',
			'edit.php?post_type=forum',
			'edit.php?post_type=topic',
			'edit.php?post_type=reply',
		),
		'sisalto'  => array(
			'edit.php?post_type=page',
			'edit.php',
			'edit.php?post_type=gallery_album',
			'edit.php?post_type=digital_magazine',
			'upload.php',
			'edit-comments.php',
		),
		'kauppa'   => array(
			'woocommerce',
			'edit.php?post_type=product',
			'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM',
			'wc-admin&path=/analytics/overview',
			'wc-admin&path=/marketing',
		),
		'yllapito' => array(
			'users.php',
			'profile.php',
			'themes.php',
			'plugins.php',
			'tools.php',
			'options-general.php',
			'rank-math',
			'edit.php?post_type=acf-field-group',
			'ai1wm_export',
		),
	);

	/**
	 * Filters the admin menu groups (#699).
	 *
	 * @param array<string, string[]> $groups Group key => top-level menu slugs.
	 */
	return (array) apply_filters( 'rytkoset_theme_admin_menu_groups', $groups );
}

/**
 * Returns the menu slug of a group's separator.
 *
 * @param string $group Group key.
 * @return string
 */
function rytkoset_theme_get_admin_menu_separator_slug( $group ) {
	return 'separator-rytkoset-' . $group;
}

/**
 * Tells which group a top-level menu slug belongs to.
 *
 * WooCommerce adds and moves several top-level pages, so its slugs are also
 * matched by prefix. Anything unknown (a newly installed plugin) goes to
 * Ylläpito, the administrators' group.
 *
 * @param string $slug Top-level menu slug (the original parent, see below).
 * @return string Group key.
 */
function rytkoset_theme_get_admin_menu_group( $slug ) {
	foreach ( rytkoset_theme_get_admin_menu_groups() as $group => $slugs ) {
		if ( in_array( $slug, $slugs, true ) ) {
			return $group;
		}
	}

	if ( 0 === strpos( $slug, 'wc-' ) || false !== strpos( $slug, 'woocommerce' ) || false !== strpos( $slug, 'post_type=product' ) || false !== strpos( $slug, 'post_type=shop_' ) ) {
		return 'kauppa';
	}

	return 'yllapito';
}

/**
 * Returns the original top-level slug for grouping.
 *
 * When the user cannot open a top-level page, core points the item at its
 * first allowed submenu page and records original => new in
 * $_wp_real_parent_file (for example the editor's "Tuotteet" opens product
 * reviews). That map also holds back-compat entries such as post.php =>
 * edit.php, so a slug that is already known keeps its own group.
 *
 * @param string                $slug         Visible top-level slug.
 * @param array<string, string> $real_parents Core's $_wp_real_parent_file.
 * @return string
 */
function rytkoset_theme_get_admin_menu_original_slug( $slug, $real_parents ) {
	$known = array_merge( ...array_values( rytkoset_theme_get_admin_menu_groups() ) );

	if ( in_array( $slug, $known, true ) ) {
		return $slug;
	}

	foreach ( (array) $real_parents as $original => $current ) {
		if ( $current === $slug && in_array( (string) $original, $known, true ) ) {
			return (string) $original;
		}
	}

	return $slug;
}

/**
 * Orders the visible top-level menu slugs into groups (pure, see the
 * menu_order filter below).
 *
 * Core runs this after removing items the user cannot access, then keeps the
 * first of any adjacent separators. A group the user sees nothing in, and
 * every core or plugin separator, is therefore placed directly after the first
 * visible group's separator, where core drops it. When no group is visible at
 * all, the leftover separator is removed in rytkoset_theme_remove_trailing_admin_menu_separators().
 *
 * @param string[]              $slugs        Visible top-level slugs in core order.
 * @param array<string, string> $real_parents Core's $_wp_real_parent_file (original => current).
 * @return string[]
 */
function rytkoset_theme_order_admin_menu( $slugs, $real_parents = array() ) {
	$groups    = rytkoset_theme_get_admin_menu_groups();
	$members   = array_fill_keys( array_keys( $groups ), array() );
	$leftovers = array();
	$head      = array();

	foreach ( $slugs as $slug ) {
		$slug = (string) $slug;

		if ( 'index.php' === $slug ) {
			$head[] = $slug;
			continue;
		}

		if ( 0 === strpos( $slug, 'separator' ) ) {
			if ( 0 !== strpos( $slug, 'separator-rytkoset-' ) ) {
				$leftovers[] = $slug;
			}
			continue;
		}

		$original = rytkoset_theme_get_admin_menu_original_slug( $slug, $real_parents );
		$group    = rytkoset_theme_get_admin_menu_group( $original );

		$members[ $group ][] = array(
			'slug' => $slug,
			'rank' => array_search( $original, $groups[ $group ] ?? array(), true ),
		);
	}

	$ordered      = $head;
	$first_marker = null;

	foreach ( array_keys( $groups ) as $group ) {
		$separator = rytkoset_theme_get_admin_menu_separator_slug( $group );

		if ( array() === $members[ $group ] ) {
			$leftovers[] = $separator;
			continue;
		}

		// Known slugs in the group's order; unknown ones after them in core order.
		$items = $members[ $group ];
		uasort(
			$items,
			static function ( $a, $b ) {
				$a_rank = false === $a['rank'] ? PHP_INT_MAX : $a['rank'];
				$b_rank = false === $b['rank'] ? PHP_INT_MAX : $b['rank'];

				return $a_rank <=> $b_rank;
			}
		);

		$ordered[] = $separator;

		if ( null === $first_marker ) {
			$first_marker = count( $ordered );
		}

		foreach ( $items as $item ) {
			$ordered[] = $item['slug'];
		}
	}

	if ( null === $first_marker ) {
		return array_merge( $ordered, $leftovers );
	}

	array_splice( $ordered, $first_marker, 0, $leftovers );

	return $ordered;
}

/**
 * Adds the four group separators to the menu.
 *
 * @return void
 */
function rytkoset_theme_add_admin_menu_group_separators() {
	global $menu;

	if ( ! is_array( $menu ) ) {
		return;
	}

	$position = 1000;

	foreach ( array_keys( rytkoset_theme_get_admin_menu_groups() ) as $group ) {
		// The id is where rytkoset_theme_print_admin_menu_group_headings() adds the heading.
		$menu[ $position++ ] = array( '', 'read', rytkoset_theme_get_admin_menu_separator_slug( $group ), '', 'wp-menu-separator ra-sep ra-sep-' . $group, 'ra-sep-' . $group ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Adding menu entries is the purpose of this callback.
	}
}
add_action( 'admin_menu', 'rytkoset_theme_add_admin_menu_group_separators', 999 );

/**
 * Returns the visible group headings.
 *
 * @return array<string, string> Group key => heading.
 */
function rytkoset_theme_get_admin_menu_group_labels() {
	return array(
		'toiminta' => __( 'Toiminta', 'rytkoset-theme' ),
		'sisalto'  => __( 'Sisältö', 'rytkoset-theme' ),
		'kauppa'   => __( 'Kauppa', 'rytkoset-theme' ),
		'yllapito' => __( 'Ylläpito', 'rytkoset-theme' ),
	);
}

/**
 * Turns the group separators into headings.
 *
 * Core prints separators empty and aria-hidden, so the heading text is added
 * here as a real h2 that screen readers announce, and the separator is exposed.
 * Runs on in_admin_header, right after the menu is printed, so the menu does
 * not shift. Only with the admin appearance active, because the headings need
 * admin.css; without it the separators stay plain lines.
 *
 * @return void
 */
function rytkoset_theme_print_admin_menu_group_headings() {
	if ( ! rytkoset_theme_admin_color_scheme_is_pinned() ) {
		return;
	}

	$script = 'Object.entries(' . wp_json_encode( rytkoset_theme_get_admin_menu_group_labels() ) . ').forEach(function(e){'
		. 'var li=document.getElementById("ra-sep-"+e[0]);if(!li){return;}'
		. 'var h=document.createElement("h2");h.className="ra-sep__label";h.textContent=e[1];'
		. 'li.removeAttribute("aria-hidden");li.appendChild(h);});';

	wp_print_inline_script_tag( $script );
}
add_action( 'in_admin_header', 'rytkoset_theme_print_admin_menu_group_headings' );

/**
 * Applies the grouped order. Late priority so it runs after WooCommerce's own
 * menu_order callback.
 *
 * @param string[] $menu_order Visible top-level slugs.
 * @return string[]
 */
function rytkoset_theme_filter_admin_menu_order( $menu_order ) {
	global $_wp_real_parent_file;

	return rytkoset_theme_order_admin_menu( (array) $menu_order, is_array( $_wp_real_parent_file ) ? $_wp_real_parent_file : array() );
}
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', 'rytkoset_theme_filter_admin_menu_order', 999 );

/**
 * Removes separators left at the end of the menu.
 *
 * Core only drops a trailing separator whose class is exactly
 * "wp-menu-separator", so a group separator would otherwise stay when the user
 * sees nothing but the dashboard.
 *
 * @param array<int|string, array> $menu Menu with classes added.
 * @return array<int|string, array>
 */
function rytkoset_theme_remove_trailing_admin_menu_separators( $menu ) {
	while ( ! empty( $menu ) ) {
		$last = array_key_last( $menu );

		if ( false === strpos( (string) ( $menu[ $last ][4] ?? '' ), 'wp-menu-separator' ) ) {
			break;
		}

		unset( $menu[ $last ] );
	}

	return $menu;
}
add_filter( 'add_menu_classes', 'rytkoset_theme_remove_trailing_admin_menu_separators', 999 );

/**
 * Renames AcyMailing to "Uutiskirje" in the sidebar. The plugin's own pages
 * keep their titles.
 *
 * @return void
 */
function rytkoset_theme_rename_newsletter_admin_menu() {
	global $menu;

	foreach ( (array) $menu as $position => $item ) {
		if ( isset( $item[2] ) && 'acymailing_dashboard' === $item[2] ) {
			$menu[ $position ][0] = __( 'Uutiskirje', 'rytkoset-theme' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Renaming one menu label.
		}
	}
}
add_action( 'admin_menu', 'rytkoset_theme_rename_newsletter_admin_menu', 999 );

/**
 * Removes Rank Math and Comments from the admin bar for non-administrators.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar.
 * @return void
 */
function rytkoset_theme_trim_admin_bar( $admin_bar ) {
	if ( current_user_can( 'manage_options' ) || ! is_object( $admin_bar ) ) {
		return;
	}

	$admin_bar->remove_node( 'rank-math' );
	$admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'rytkoset_theme_trim_admin_bar', 999 );

/**
 * Tells whether the admin color scheme is pinned to the default.
 *
 * Follows the admin appearance switch, so RYTKOSET_DISABLE_ADMIN_APPEARANCE
 * also restores each user's own color scheme.
 *
 * @return bool
 */
function rytkoset_theme_admin_color_scheme_is_pinned() {
	return rytkoset_theme_admin_appearance_should_load( defined( 'RYTKOSET_DISABLE_ADMIN_APPEARANCE' ) && RYTKOSET_DISABLE_ADMIN_APPEARANCE );
}

/**
 * Removes the color scheme choice from the profile screen.
 *
 * @return void
 */
function rytkoset_theme_remove_admin_color_scheme_picker() {
	if ( rytkoset_theme_admin_color_scheme_is_pinned() ) {
		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
	}
}
add_action( 'admin_init', 'rytkoset_theme_remove_admin_color_scheme_picker' );

/**
 * Uses WordPress's default color scheme for everyone, so the brand colors in
 * admin.css are the same for all users. The saved choice is not changed.
 *
 * @param mixed $color Saved color scheme.
 * @return mixed
 */
function rytkoset_theme_pin_admin_color_scheme( $color ) {
	return rytkoset_theme_admin_color_scheme_is_pinned() ? 'modern' : $color;
}
add_filter( 'get_user_option_admin_color', 'rytkoset_theme_pin_admin_color_scheme' );

/**
 * Keeps the saved color scheme when a profile is saved without the picker.
 *
 * Core writes "modern" when the form has no admin_color field, which would
 * overwrite the user's choice and lose it if the switch is turned off later.
 *
 * @param WP_Error $errors Errors (passed by reference by core).
 * @param bool     $update Whether an existing user is updated.
 * @param stdClass $user   User data about to be saved (passed by reference by core).
 * @return void
 */
function rytkoset_theme_keep_saved_admin_color( $errors, $update, $user ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core verified the profile nonce before this hook.
	if ( ! $update || isset( $_POST['admin_color'] ) || ! is_object( $user ) || empty( $user->ID ) || ! rytkoset_theme_admin_color_scheme_is_pinned() ) {
		return;
	}

	$saved = get_user_meta( (int) $user->ID, 'admin_color', true );

	if ( is_string( $saved ) && '' !== $saved ) {
		$user->admin_color = $saved;
	}
}
add_action( 'user_profile_update_errors', 'rytkoset_theme_keep_saved_admin_color', 10, 3 );

/**
 * Returns Finnish names for roles that have none: bbPress's forum roles in the
 * users list filters and the theme's Event Organizer role. The words match
 * bbPress's own Finnish translation used elsewhere in the admin.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_finnish_role_names() {
	return array(
		'Keymaster'       => 'Avainmestari',
		'Moderator'       => 'Valvoja',
		'Participant'     => 'Osallistuja',
		'Spectator'       => 'Katsoja',
		'Blocked'         => 'Estetty',
		'Event Organizer' => 'Tapahtumien järjestäjä',
	);
}

/**
 * Translates role names passed through translate_user_role().
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @param string $context     Context.
 * @return string
 */
function rytkoset_theme_translate_role_name( $translation, $text, $context ) {
	if ( 'User role' !== $context || $translation !== $text ) {
		return $translation;
	}

	$names = rytkoset_theme_get_finnish_role_names();

	return $names[ $text ] ?? $translation;
}
add_filter( 'gettext_with_context', 'rytkoset_theme_translate_role_name', 10, 3 );

/**
 * Hides bbPress's forum role selector on the profile screen from users who
 * cannot change roles. bbPress shows it to anyone who can edit the user but
 * saves it only with promote_user, so others saw a control that did nothing.
 *
 * @return void
 */
function rytkoset_theme_hide_forum_role_selector() {
	global $wp_filter;

	if ( current_user_can( 'promote_users' ) ) {
		return;
	}

	foreach ( array( 'show_user_profile', 'edit_user_profile' ) as $hook ) {
		if ( ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
			continue;
		}

		foreach ( (array) $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				$function = $callback['function'] ?? null;

				if ( is_array( $function ) && isset( $function[0], $function[1] ) && is_object( $function[0] ) && 'BBP_Users_Admin' === get_class( $function[0] ) && 'secondary_role_display' === $function[1] ) {
					remove_action( $hook, $function, $priority );
				}
			}
		}
	}
}
add_action( 'load-profile.php', 'rytkoset_theme_hide_forum_role_selector' );
add_action( 'load-user-edit.php', 'rytkoset_theme_hide_forum_role_selector' );
