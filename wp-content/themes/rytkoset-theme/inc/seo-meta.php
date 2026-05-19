<?php
/**
 * Open Graph- ja Twitter Card -metatagit etusivulle ja yksittäisille postauksille.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Open Graph + Twitter Card meta.
 */
function rytkoset_theme_social_meta() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	if ( false === apply_filters( 'rytkoset_theme_output_social_meta', true ) ) {
		return;
	}

	$post_id     = is_singular() ? get_queried_object_id() : 0;
	$site_name   = get_bloginfo( 'name' );
	$title       = $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : $site_name;
	$description = $post_id
		? ( has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30 )
		)
		: get_bloginfo( 'description' );
	$url         = $post_id ? get_permalink( $post_id ) : home_url( '/' );
	$type        = $post_id ? 'article' : 'website';

	$image        = '';
	$image_width  = 0;
	$image_height = 0;

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		if ( is_array( $image_data ) ) {
			$image        = $image_data[0];
			$image_width  = isset( $image_data[1] ) ? (int) $image_data[1] : 0;
			$image_height = isset( $image_data[2] ) ? (int) $image_data[2] : 0;
		}
	}

	if ( empty( $image ) && function_exists( 'has_site_icon' ) && has_site_icon() ) {
		$icon_size = 512;
		$image     = get_site_icon_url( $icon_size );

		if ( $image ) {
			$image_width  = $icon_size;
			$image_height = $icon_size;
		}
	}

	if ( empty( $image ) ) {
		$logo_id   = get_theme_mod( 'custom_logo' );
		$logo_data = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : array();

		if ( is_array( $logo_data ) ) {
			$image        = $logo_data[0];
			$image_width  = isset( $logo_data[1] ) ? (int) $logo_data[1] : 0;
			$image_height = isset( $logo_data[2] ) ? (int) $logo_data[2] : 0;
		}
	}

	$locale = str_replace( '_', '-', get_locale() );

	$meta = array(
		'og:title'       => $title,
		'og:description' => $description,
		'og:url'         => $url,
		'og:type'        => $type,
		'og:site_name'   => $site_name,
		'og:locale'      => $locale,
	);

	if ( ! empty( $image ) ) {
		$meta['og:image'] = $image;

		if ( $image_width > 0 && $image_height > 0 ) {
			$meta['og:image:width']  = $image_width;
			$meta['og:image:height'] = $image_height;
		}
	}

	if ( $post_id ) {
		$meta['article:published_time'] = get_the_date( DATE_W3C, $post_id );
		$meta['article:modified_time']  = get_the_modified_date( DATE_W3C, $post_id );
	}

	$meta['twitter:card']        = 'summary_large_image';
	$meta['twitter:title']       = $title;
	$meta['twitter:description'] = $description;
	$meta['twitter:url']         = $url;

	if ( ! empty( $image ) ) {
		$meta['twitter:image'] = $image;
	}

	foreach ( $meta as $property => $content ) {
		if ( empty( $content ) ) {
			continue;
		}

		$attribute = 0 === strpos( $property, 'twitter:' ) ? 'name' : 'property';
		printf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			esc_attr( $attribute ),
			esc_attr( $property ),
			esc_attr( $content )
		);
	}
}
add_action( 'wp_head', 'rytkoset_theme_social_meta', 5 );
