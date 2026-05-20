<?php
/**
 * Synkronoi liitekuvien IPTC-headline ja -description WordPressin caption- ja description-kenttiin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads raw IPTC headline and description fields from an image file.
 *
 * @param string $file Absolute image file path.
 * @return array{headline:string,description:string}
 */
function rytkoset_theme_get_image_iptc_text_fields( $file ) {
	$fields = array(
		'headline'    => '',
		'description' => '',
	);

	if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) || ! is_callable( 'iptcparse' ) ) {
		return $fields;
	}

	$info       = array();
	$image_size = wp_getimagesize( $file, $info );

	if ( false === $image_size || empty( $info['APP13'] ) ) {
		return $fields;
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! defined( 'WP_RUN_CORE_TESTS' ) ) {
		$iptc = iptcparse( $info['APP13'] );
	} else {
		// Silencing notice and warning is intentional, same as WordPress core.
		$iptc = @iptcparse( $info['APP13'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	if ( ! is_array( $iptc ) ) {
		return $fields;
	}

	if ( ! empty( $iptc['2#105'][0] ) ) {
		$fields['headline'] = trim( (string) $iptc['2#105'][0] );
	}

	if ( ! empty( $iptc['2#120'][0] ) ) {
		$fields['description'] = trim( (string) $iptc['2#120'][0] );
	}

	return $fields;
}

/**
 * Maps IPTC Headline and Description to attachment caption and description on upload.
 *
 * Headline -> post_excerpt (WordPress media caption)
 * Description -> post_content (WordPress media description)
 *
 * @param int $attachment_id Attachment post ID.
 * @return void
 */
function rytkoset_theme_sync_attachment_iptc_text_fields( $attachment_id ) {
	$attachment_id = (int) $attachment_id;

	if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	$file = get_attached_file( $attachment_id );

	if ( ! is_string( $file ) || '' === $file ) {
		return;
	}

	$iptc_fields = rytkoset_theme_get_image_iptc_text_fields( $file );
	$post_update = array(
		'ID' => $attachment_id,
	);

	if ( '' !== $iptc_fields['headline'] ) {
		$post_update['post_excerpt'] = $iptc_fields['headline'];
	}

	if ( '' !== $iptc_fields['description'] ) {
		$post_update['post_content'] = $iptc_fields['description'];
	}

	if ( 1 === count( $post_update ) ) {
		return;
	}

	wp_update_post( wp_slash( $post_update ) );
}
add_action( 'add_attachment', 'rytkoset_theme_sync_attachment_iptc_text_fields' );
