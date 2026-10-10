<?php
/**
 * Digital magazine admin list and editor helpers (#698).
 *
 * Makes the magazine > article hierarchy visible in the list, adds an
 * "add article to this magazine" path and lists a magazine's articles in its
 * editor. Data model and access rules stay in inc/digital-magazines.php.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the query argument that preselects an article's magazine.
 *
 * @return string
 */
function rytkoset_theme_get_digital_magazine_parent_query_arg() {
	return 'rytkoset_parent';
}

/**
 * Returns the "new article in this magazine" URL.
 *
 * @param int $magazine_id Top-level magazine ID.
 * @return string
 */
function rytkoset_theme_get_digital_magazine_new_article_url( $magazine_id ) {
	return add_query_arg(
		array(
			'post_type' => 'digital_magazine',
			rytkoset_theme_get_digital_magazine_parent_query_arg() => absint( $magazine_id ),
		),
		admin_url( 'post-new.php' )
	);
}

/**
 * Tells whether a post ID is a top-level digital magazine.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function rytkoset_theme_is_top_level_digital_magazine( $post_id ) {
	$post = get_post( absint( $post_id ) );

	return $post instanceof WP_Post && 'digital_magazine' === $post->post_type && 0 === (int) $post->post_parent && 'trash' !== $post->post_status;
}

/**
 * Tells whether the current user may add an article to a magazine: a
 * top-level, non-trashed magazine they can edit, plus the create capability.
 * The row action, the Jutut box button and the parent preselect share this
 * rule, so a link is never shown that would create a top-level post instead.
 *
 * @param int $magazine_id Magazine ID.
 * @return bool
 */
function rytkoset_theme_user_can_add_digital_magazine_article( $magazine_id ) {
	$post_type = get_post_type_object( 'digital_magazine' );

	return $post_type
		&& current_user_can( $post_type->cap->create_posts )
		&& rytkoset_theme_is_top_level_digital_magazine( $magazine_id )
		&& current_user_can( 'edit_post', absint( $magazine_id ) );
}

/**
 * Returns a magazine's articles in every editable status, ordered as in the
 * magazine; private articles only when the user can edit them. The public
 * rytkoset_theme_get_digital_magazine_articles() returns published articles only.
 *
 * @param int $magazine_id Top-level magazine ID.
 * @return WP_Post[]
 */
function rytkoset_theme_get_digital_magazine_admin_articles( $magazine_id ) {
	$articles = get_posts(
		array(
			'post_type'      => 'digital_magazine',
			'post_parent'    => absint( $magazine_id ),
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);

	// Private articles stay hidden from users who cannot edit them, as in the core list.
	return array_values(
		array_filter(
			$articles,
			static function ( $article ) {
				return 'private' !== $article->post_status || current_user_can( 'edit_post', $article->ID );
			}
		)
	);
}

/**
 * Adds the type, article count and order columns to the magazine list.
 *
 * @param array<string, string> $columns List columns.
 * @return array<string, string>
 */
function rytkoset_theme_digital_magazine_admin_columns( $columns ) {
	$updated = array();

	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$updated['rytkoset_magazine_articles'] = __( 'Juttuja', 'rytkoset-theme' );
			$updated['rytkoset_magazine_order']    = __( 'Järjestys', 'rytkoset-theme' );
		}

		$updated[ $key ] = $label;

		if ( 'title' === $key ) {
			$updated['rytkoset_magazine_kind'] = __( 'Tyyppi', 'rytkoset-theme' );
		}
	}

	return $updated;
}
add_filter( 'manage_digital_magazine_posts_columns', 'rytkoset_theme_digital_magazine_admin_columns' );

/**
 * Renders the custom magazine list columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function rytkoset_theme_render_digital_magazine_admin_column( $column, $post_id ) {
	$post      = get_post( $post_id );
	$parent_id = $post instanceof WP_Post ? (int) $post->post_parent : 0;

	if ( 'rytkoset_magazine_kind' === $column ) {
		if ( 0 === $parent_id ) {
			echo '<span class="ra-badge ra-badge--info">' . esc_html__( 'Lehti', 'rytkoset-theme' ) . '</span>';
			return;
		}

		// The magazine name keeps the context when the list is searched or filtered.
		echo '<span class="ra-badge">' . esc_html__( 'Juttu', 'rytkoset-theme' ) . '</span>';
		echo '<span class="ra-sub">' . esc_html( get_the_title( $parent_id ) ) . '</span>';
		return;
	}

	if ( 'rytkoset_magazine_articles' === $column && 0 === $parent_id ) {
		echo esc_html( (string) count( rytkoset_theme_get_digital_magazine_admin_articles( $post_id ) ) );
		return;
	}

	if ( 'rytkoset_magazine_order' === $column && $parent_id > 0 && $post instanceof WP_Post ) {
		echo esc_html( (string) (int) $post->menu_order );
	}
}
add_action( 'manage_digital_magazine_posts_custom_column', 'rytkoset_theme_render_digital_magazine_admin_column', 10, 2 );

/**
 * Adds "Lisää juttu tähän lehteen" to a magazine row.
 *
 * Hierarchical post types use page_row_actions.
 *
 * @param array<string, string> $actions Row actions.
 * @param WP_Post               $post    Row post.
 * @return array<string, string>
 */
function rytkoset_theme_digital_magazine_row_actions( $actions, $post ) {
	if ( ! $post instanceof WP_Post || 'digital_magazine' !== $post->post_type || ! rytkoset_theme_user_can_add_digital_magazine_article( $post->ID ) ) {
		return $actions;
	}

	$link = '<a href="' . esc_url( rytkoset_theme_get_digital_magazine_new_article_url( $post->ID ) ) . '">' . esc_html__( 'Lisää juttu tähän lehteen', 'rytkoset-theme' ) . '</a>';

	return array_slice( $actions, 0, 1, true ) + array( 'rytkoset_add_article' => $link ) + array_slice( $actions, 1, null, true );
}
add_filter( 'page_row_actions', 'rytkoset_theme_digital_magazine_row_actions', 10, 2 );

/**
 * Preselects the magazine of a new article opened from "Lisää juttu tähän lehteen".
 *
 * Runs while WordPress creates the auto-draft for the new post screen, so the
 * editor opens with the parent already set. The user can still change it.
 *
 * @param array<string, mixed> $data Sanitized post data.
 * @return array<string, mixed>
 */
function rytkoset_theme_preselect_digital_magazine_parent( $data ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only default for a new auto-draft; the real save is nonce-protected by WordPress.
	$parent_id = isset( $_GET[ rytkoset_theme_get_digital_magazine_parent_query_arg() ] ) ? absint( wp_unslash( $_GET[ rytkoset_theme_get_digital_magazine_parent_query_arg() ] ) ) : 0;

	if (
		$parent_id <= 0
		|| 'digital_magazine' !== ( $data['post_type'] ?? '' )
		|| 'auto-draft' !== ( $data['post_status'] ?? '' )
		|| ! rytkoset_theme_user_can_add_digital_magazine_article( $parent_id )
	) {
		return $data;
	}

	$data['post_parent'] = $parent_id;

	return $data;
}
add_filter( 'wp_insert_post_data', 'rytkoset_theme_preselect_digital_magazine_parent' );

/**
 * Hides Rank Math's tall "SEO Details" column in a user's magazine list once.
 *
 * WordPress's default_hidden_columns never applies here: Rank Math stores a
 * hidden-columns list for every user on their first admin load. Same pattern as
 * Rank Math: add the column to that list a single time and remember it, so a
 * user who brings the column back from Screen Options keeps it.
 *
 * @param int $user_id User ID.
 * @return bool Whether the column was hidden now.
 */
function rytkoset_theme_hide_digital_magazine_seo_column_once( $user_id ) {
	$user_id = absint( $user_id );
	$flag    = 'rytkoset_magazine_seo_column_hidden';

	if ( $user_id <= 0 || get_user_meta( $user_id, $flag, true ) ) {
		return false;
	}

	$option = 'manageedit-digital_magazinecolumnshidden';
	$hidden = get_user_meta( $user_id, $option, true );
	$hidden = is_array( $hidden ) ? $hidden : array();

	if ( ! in_array( 'rank_math_seo_details', $hidden, true ) ) {
		$hidden[] = 'rank_math_seo_details';
	}

	update_user_meta( $user_id, $option, $hidden );
	update_user_meta( $user_id, $flag, '1' );

	return true;
}

/**
 * Runs the one-time SEO column hiding when the magazine list loads.
 *
 * @return void
 */
function rytkoset_theme_maybe_hide_digital_magazine_seo_column() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the list's post type only.
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

	if ( 'digital_magazine' === $post_type ) {
		rytkoset_theme_hide_digital_magazine_seo_column_once( get_current_user_id() );
	}
}
add_action( 'load-edit.php', 'rytkoset_theme_maybe_hide_digital_magazine_seo_column' );

/**
 * Removes Rank Math's "Pillar Content" view, which magazines do not use.
 *
 * @param array<string, string> $views List views.
 * @return array<string, string>
 */
function rytkoset_theme_digital_magazine_list_views( $views ) {
	unset( $views['pillar_content'] );

	return $views;
}
add_filter( 'views_edit-digital_magazine', 'rytkoset_theme_digital_magazine_list_views', 20 );

/**
 * Registers the "Jutut" box on a saved top-level magazine.
 *
 * @param WP_Post $post Edited post.
 * @return void
 */
function rytkoset_theme_register_digital_magazine_articles_metabox( $post ) {
	if ( ! $post instanceof WP_Post || 0 !== (int) $post->post_parent || 'auto-draft' === $post->post_status ) {
		return;
	}

	add_meta_box(
		'rytkoset-digital-magazine-articles',
		__( 'Jutut', 'rytkoset-theme' ),
		'rytkoset_theme_render_digital_magazine_articles_metabox',
		'digital_magazine',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_digital_magazine', 'rytkoset_theme_register_digital_magazine_articles_metabox' );

/**
 * Renders the magazine's articles with edit links and an add button.
 *
 * @param WP_Post $post Edited magazine.
 * @return void
 */
function rytkoset_theme_render_digital_magazine_articles_metabox( $post ) {
	$articles = rytkoset_theme_get_digital_magazine_admin_articles( $post->ID );
	$statuses = get_post_statuses();

	if ( empty( $articles ) ) {
		echo '<p>' . esc_html__( 'Lehdessä ei ole vielä juttuja.', 'rytkoset-theme' ) . '</p>';
	} else {
		echo '<ol class="rytkoset-magazine-articles">';

		foreach ( $articles as $article ) {
			$edit_url = get_edit_post_link( $article->ID );
			$title    = '' !== $article->post_title ? $article->post_title : __( '(ei otsikkoa)', 'rytkoset-theme' );

			echo '<li>';
			echo '' !== (string) $edit_url
				? '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $title ) . '</a>'
				: esc_html( $title );

			if ( 'publish' !== $article->post_status && isset( $statuses[ $article->post_status ] ) ) {
				echo ' <span class="ra-sub">' . esc_html( $statuses[ $article->post_status ] ) . '</span>';
			}

			echo '</li>';
		}

		echo '</ol>';
	}

	if ( rytkoset_theme_user_can_add_digital_magazine_article( $post->ID ) ) {
		echo '<p><a class="button" href="' . esc_url( rytkoset_theme_get_digital_magazine_new_article_url( $post->ID ) ) . '">' . esc_html__( 'Lisää juttu tähän lehteen', 'rytkoset-theme' ) . '</a></p>';
	}

	echo '<p class="description">' . esc_html__( 'Juttujen järjestys tulee jutun Järjestys-kentästä (Sivun attribuutit).', 'rytkoset-theme' ) . '</p>';
}

/**
 * Loads the small script that shows only the product fields the chosen
 * access mode uses. Without JavaScript every field stays visible.
 *
 * @param string $hook_suffix Admin page hook.
 * @return void
 */
function rytkoset_theme_enqueue_digital_magazine_admin_script( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'digital_magazine' !== $screen->post_type ) {
		return;
	}

	$path = get_template_directory() . '/assets/js/digital-magazine-admin.js';

	wp_enqueue_script(
		'rytkoset-digital-magazine-admin',
		get_template_directory_uri() . '/assets/js/digital-magazine-admin.js',
		array(),
		rytkoset_theme_get_asset_version( $path ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'rytkoset_theme_enqueue_digital_magazine_admin_script' );
