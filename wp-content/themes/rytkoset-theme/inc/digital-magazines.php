<?php
/**
 * Digilehdet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_register_digital_magazine_cpt' ) ) {
	/**
	 * Rekisteröi digilehtien hierarkkisen sisältötyypin.
	 */
	function rytkoset_theme_register_digital_magazine_cpt() {
		$labels = array(
			'name'               => __( 'Digilehdet', 'rytkoset-theme' ),
			'singular_name'      => __( 'Digilehti', 'rytkoset-theme' ),
			'menu_name'          => __( 'Digilehdet', 'rytkoset-theme' ),
			'name_admin_bar'     => __( 'Digilehti', 'rytkoset-theme' ),
			'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
			'add_new_item'       => __( 'Lisää uusi digilehti tai juttu', 'rytkoset-theme' ),
			'new_item'           => __( 'Uusi digilehti', 'rytkoset-theme' ),
			'edit_item'          => __( 'Muokkaa digilehteä', 'rytkoset-theme' ),
			'view_item'          => __( 'Näytä digilehti', 'rytkoset-theme' ),
			'all_items'          => __( 'Kaikki digilehdet', 'rytkoset-theme' ),
			'search_items'       => __( 'Etsi digilehtiä', 'rytkoset-theme' ),
			'parent_item_colon'  => __( 'Ylälehti:', 'rytkoset-theme' ),
			'not_found'          => __( 'Digilehtiä ei löytynyt.', 'rytkoset-theme' ),
			'not_found_in_trash' => __( 'Roskakorissa ei ole digilehtiä.', 'rytkoset-theme' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'hierarchical' => true,
			'menu_icon'    => 'dashicons-book-alt',
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'rewrite'      => array(
				'slug'         => 'digilehdet',
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_post_type( 'digital_magazine', $args );
	}
}
add_action( 'init', 'rytkoset_theme_register_digital_magazine_cpt' );

if ( ! function_exists( 'rytkoset_theme_is_digital_magazine_article' ) ) {
	/**
	 * Checks whether a digital magazine post is an article.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function rytkoset_theme_is_digital_magazine_article( $post_id ) {
		return 0 < (int) wp_get_post_parent_id( $post_id );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_digital_magazine_parent_id' ) ) {
	/**
	 * Returns the parent magazine ID for a magazine or article.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	function rytkoset_theme_get_digital_magazine_parent_id( $post_id ) {
		$parent_id = (int) wp_get_post_parent_id( $post_id );

		return 0 < $parent_id ? $parent_id : (int) $post_id;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_digital_magazine_articles' ) ) {
	/**
	 * Returns published articles for one digital magazine.
	 *
	 * @param int $magazine_id Magazine post ID.
	 * @return WP_Post[]
	 */
	function rytkoset_theme_get_digital_magazine_articles( $magazine_id ) {
		$magazine_id = absint( $magazine_id );

		if ( 0 === $magazine_id ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'digital_magazine',
				'post_status'    => 'publish',
				'post_parent'    => $magazine_id,
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_adjacent_digital_magazine_article' ) ) {
	/**
	 * Returns the previous or next article within the same digital magazine.
	 *
	 * @param int    $article_id Article post ID.
	 * @param string $direction  Direction: previous or next.
	 * @return WP_Post|null
	 */
	function rytkoset_theme_get_adjacent_digital_magazine_article( $article_id, $direction ) {
		$article = get_post( $article_id );

		if ( ! $article instanceof WP_Post || 'digital_magazine' !== $article->post_type || 0 === (int) $article->post_parent ) {
			return null;
		}

		$articles = rytkoset_theme_get_digital_magazine_articles( (int) $article->post_parent );
		$ids      = array_map( 'intval', wp_list_pluck( $articles, 'ID' ) );
		$index    = array_search( (int) $article_id, $ids, true );

		if ( false === $index ) {
			return null;
		}

		$target_index = 'previous' === $direction ? $index - 1 : $index + 1;

		return isset( $articles[ $target_index ] ) ? $articles[ $target_index ] : null;
	}
}

if ( ! function_exists( 'rytkoset_theme_limit_digital_magazine_archive_to_top_level' ) ) {
	/**
	 * Shows only top-level magazines on the public archive.
	 *
	 * @param WP_Query $query Query instance.
	 */
	function rytkoset_theme_limit_digital_magazine_archive_to_top_level( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'digital_magazine' ) ) {
			return;
		}

		$query->set( 'post_parent', 0 );
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'rytkoset_theme_limit_digital_magazine_archive_to_top_level' );
