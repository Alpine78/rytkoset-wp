<?php
/**
 * Open Graph- ja Twitter Card -metatagit etusivulle ja yksittäisille postauksille.
 *
 * Sisältää myös vuotosuojan jäsenrajatulle sisällölle (digilehdet #381, jäsensivut #392):
 * teeman oma OG-/Twitter-kuvaus sekä Rank Math -lisäosan kuvaus ja JSON-LD eivät saa johtaa
 * suojattua post_contentia näkyviin ei-oikeutetulle. Jaettu päätös tehdään funktiossa
 * rytkoset_theme_content_is_access_restricted().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns true when the post's content must be hidden from the current viewer.
 *
 * Combines the per-feature gates so every SEO/social meta route honours the same access
 * decision: restricted digital magazines (#381) and members-only pages (#392). Each gate is
 * itself fail-closed; this helper just ORs them.
 *
 * @param int|WP_Post|null $post Post object or ID. Defaults to the current post.
 * @return bool
 */
function rytkoset_theme_content_is_access_restricted( $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if (
		function_exists( 'rytkoset_theme_should_hide_digital_magazine_content' )
		&& rytkoset_theme_should_hide_digital_magazine_content( $post )
	) {
		return true;
	}

	if (
		function_exists( 'rytkoset_theme_should_hide_members_only_page' )
		&& rytkoset_theme_should_hide_members_only_page( $post )
	) {
		return true;
	}

	return false;
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

	$post_id   = is_singular() ? get_queried_object_id() : 0;
	$site_name = get_bloginfo( 'name' );
	$title     = $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : $site_name;

	if ( ! $post_id ) {
		$description = get_bloginfo( 'description' );
	} elseif ( rytkoset_theme_content_is_access_restricted( $post_id ) ) {
		// Restricted content (digital magazine #381 / members-only page #392): never derive the
		// description from the protected post content. Keep an editor excerpt as a public teaser.
		$description = has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: get_bloginfo( 'description' );
	} elseif ( has_excerpt( $post_id ) ) {
		$description = wp_strip_all_tags( get_the_excerpt( $post_id ) );
	} else {
		$description = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30 );
	}
	$url  = $post_id ? get_permalink( $post_id ) : home_url( '/' );
	$type = $post_id ? 'article' : 'website';

	$image        = '';
	$image_width  = 0;
	$image_height = 0;
	$image_type   = '';
	$image_alt    = '';

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );

		// Suositaan 1200 x 630 -rajattua OG-kokoa; jos sitä ei vielä ole
		// generoitu, wp_get_attachment_image_src palauttaa alkuperäiskuvan.
		$image_data = wp_get_attachment_image_src( $thumbnail_id, 'rytkoset-og' );

		if ( is_array( $image_data ) ) {
			$image        = $image_data[0];
			$image_width  = isset( $image_data[1] ) ? (int) $image_data[1] : 0;
			$image_height = isset( $image_data[2] ) ? (int) $image_data[2] : 0;
			$image_type   = (string) get_post_mime_type( $thumbnail_id );
			$image_alt    = trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
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
			$image_type   = $logo_id ? (string) get_post_mime_type( $logo_id ) : '';
		}
	}

	if ( '' === $image_alt ) {
		$image_alt = $title;
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

		// Facebook ja LinkedIn suosivat https-osoitetta erikseen.
		if ( 0 === strpos( $image, 'https://' ) ) {
			$meta['og:image:secure_url'] = $image;
		}

		if ( $image_width > 0 && $image_height > 0 ) {
			$meta['og:image:width']  = $image_width;
			$meta['og:image:height'] = $image_height;
		}

		// Facebook tukee vain JPG/PNG/GIF-kuvia og:image-tagissa.
		if ( ! empty( $image_type ) ) {
			$meta['og:image:type'] = $image_type;
		}

		if ( ! empty( $image_alt ) ) {
			$meta['og:image:alt'] = $image_alt;
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

		if ( ! empty( $image_alt ) ) {
			$meta['twitter:image:alt'] = $image_alt;
		}
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

/**
 * Replaces the Rank Math meta description for restricted content.
 *
 * Rank Math auto-generates its `<meta name="description">`, `og:description` and
 * `twitter:description` from post_content when no manual description is set. All three derive
 * from this single filtered value, so returning the site tagline for restricted posts closes
 * every Rank Math description route at once without leaking the protected content (#381/#392).
 *
 * @param string $description Generated meta description.
 * @return string
 */
function rytkoset_theme_rankmath_protect_description( $description ) {
	if ( is_admin() || ! is_singular() ) {
		return $description;
	}

	if ( ! rytkoset_theme_content_is_access_restricted( get_queried_object_id() ) ) {
		return $description;
	}

	$post_id = get_queried_object_id();

	// Keep a manual excerpt as an intentional public teaser; otherwise fall back to the site
	// tagline so Rank Math never reaches the protected post content.
	return has_excerpt( $post_id )
		? wp_strip_all_tags( get_the_excerpt( $post_id ) )
		: (string) get_bloginfo( 'description' );
}
add_filter( 'rank_math/frontend/description', 'rytkoset_theme_rankmath_protect_description' );

/**
 * Strips the protected post content from Rank Math's JSON-LD output.
 *
 * The Article/WebPage graph nodes carry a `description` built from post_content. Blank every
 * node description for restricted singular content; site-level nodes (Organization/WebSite)
 * derive their description from the blog tagline, so clearing them on a restricted page is
 * harmless (#381/#392).
 *
 * @param array $data JSON-LD graph pieces.
 * @return array
 */
function rytkoset_theme_rankmath_protect_jsonld( $data ) {
	if ( is_admin() || ! is_singular() ) {
		return $data;
	}

	if ( ! is_array( $data ) || ! rytkoset_theme_content_is_access_restricted( get_queried_object_id() ) ) {
		return $data;
	}

	foreach ( $data as $key => $piece ) {
		if ( is_array( $piece ) && array_key_exists( 'description', $piece ) ) {
			$data[ $key ]['description'] = '';
		}
	}

	return $data;
}
add_filter( 'rank_math/json_ld', 'rytkoset_theme_rankmath_protect_jsonld', 99 );
