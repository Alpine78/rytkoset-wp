<?php
/**
 * Media usage and delete protection (#702).
 *
 * Album images are referenced from Gallery blocks and the legacy ACF field, so
 * WordPress shows them as "(Unattached)" and offers "Delete permanently". This
 * module finds where an attachment is used, shows it in the Media Library and
 * refuses to delete an attachment that is still in use.
 *
 * Detection covers block attributes (image, gallery, cover, media-text, file,
 * video, audio, and any block's background image), the wp-image-N class on
 * image tags and [gallery ids], featured
 * images, WooCommerce product galleries, the ACF album gallery, album video
 * thumbnails, synced patterns (wp_block posts) and the site logo and icon.
 * Images referenced only by URL are not detected.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the post types scanned for media usage.
 *
 * bbPress topics and replies are left out: many rows of member content, and
 * forum images are not managed through the Media Library.
 *
 * @return string[]
 */
function rytkoset_theme_get_media_usage_post_types() {
	$post_types = array( 'gallery_album', 'rytkoset_event', 'product', 'product_variation', 'post', 'page', 'digital_magazine', 'wp_block' );

	/**
	 * Filters the post types scanned for media usage (#702).
	 *
	 * @param string[] $post_types Post types.
	 */
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'rytkoset_theme_media_usage_post_types', $post_types ) ) ) );
}

/**
 * Returns the post statuses that count as usage. Drafts and private content
 * count, because deleting their image would break them once published.
 * Trashed content does not.
 *
 * @return string[]
 */
function rytkoset_theme_get_media_usage_post_statuses() {
	return array( 'publish', 'future', 'draft', 'pending', 'private' );
}

/**
 * Normalizes a list of possible attachment IDs.
 *
 * @param array<int, mixed> $ids Raw values.
 * @return int[]
 */
function rytkoset_theme_normalize_media_usage_ids( $ids ) {
	$normalized = array();

	foreach ( (array) $ids as $id ) {
		$id = is_numeric( $id ) ? (int) $id : 0;

		if ( $id > 0 ) {
			$normalized[ $id ] = $id;
		}
	}

	return array_values( $normalized );
}

/**
 * Finds attachment IDs referenced by post content.
 *
 * Block comment attributes are decoded with the comment boundary as the end
 * marker, so nested attribute objects such as "style" do not cut the JSON
 * short (WordPress escapes "--" inside block attributes).
 *
 * @param string $content Post content.
 * @return int[]
 */
function rytkoset_theme_get_attachment_ids_from_content( $content ) {
	$content = (string) $content;
	$ids     = array();

	if ( '' === $content ) {
		return array();
	}

	$media_blocks = array( 'image', 'gallery', 'cover', 'media-text', 'file', 'video', 'audio' );

	if ( preg_match_all( '#<!--\s+wp:([a-z0-9_/-]+)\s+(\{.*?\})\s+/?-->#s', $content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$name       = 0 === strpos( $match[1], 'core/' ) ? substr( $match[1], 5 ) : $match[1];
			$attributes = json_decode( $match[2], true );

			if ( ! is_array( $attributes ) ) {
				continue;
			}

			// Any block may carry a Media Library background image (e.g. Group).
			if ( isset( $attributes['style']['background']['backgroundImage']['id'] ) ) {
				$ids[] = $attributes['style']['background']['backgroundImage']['id'];
			}

			if ( ! in_array( $name, $media_blocks, true ) ) {
				continue;
			}

			foreach ( array( 'id', 'mediaId' ) as $key ) {
				if ( isset( $attributes[ $key ] ) ) {
					$ids[] = $attributes[ $key ];
				}
			}

			// Galleries saved before WordPress 5.9 keep their images in "ids".
			if ( isset( $attributes['ids'] ) && is_array( $attributes['ids'] ) ) {
				$ids = array_merge( $ids, $attributes['ids'] );
			}
		}
	}

	// HTML comments (including block delimiters) never display an image.
	$markup = (string) preg_replace( '/<!--.*?-->/s', '', $content );

	// Only real image tags count, not the class name written as text.
	if ( preg_match_all( '/<img\b[^>]*\bclass\s*=\s*["\'][^"\']*\bwp-image-(\d+)\b/i', $markup, $matches ) ) {
		$ids = array_merge( $ids, $matches[1] );
	}

	// [gallery ids="1, 2 3"] or include="…"; a doubled [[gallery]] is an escaped text example.
	if ( preg_match_all( '/(?<!\[)\[gallery\b([^\]]*)\]/i', $markup, $shortcodes ) ) {
		foreach ( $shortcodes[1] as $attribute_text ) {
			if ( ! preg_match_all( '/([a-z_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $attribute_text, $pairs, PREG_SET_ORDER ) ) {
				continue;
			}

			foreach ( $pairs as $pair ) {
				// Shortcode attribute names are case-insensitive.
				if ( in_array( strtolower( $pair[1] ), array( 'ids', 'include' ), true ) ) {
					$list = $pair[2] . ( $pair[3] ?? '' ) . ( $pair[4] ?? '' );
					$ids  = array_merge( $ids, preg_split( '/[\s,]+/', $list, -1, PREG_SPLIT_NO_EMPTY ) );
				}
			}
		}
	}

	return rytkoset_theme_normalize_media_usage_ids( array_map( 'trim', array_map( 'strval', $ids ) ) );
}

/**
 * Finds attachment IDs referenced by a post's meta: featured image, product
 * gallery, the ACF album gallery and album video thumbnails.
 *
 * @param WP_Post $post Post.
 * @return int[]
 */
function rytkoset_theme_get_attachment_ids_from_post_meta( $post ) {
	$ids = array( get_post_meta( $post->ID, '_thumbnail_id', true ) );

	$product_gallery = (string) get_post_meta( $post->ID, '_product_image_gallery', true );

	if ( '' !== $product_gallery ) {
		$ids = array_merge( $ids, explode( ',', $product_gallery ) );
	}

	if ( 'gallery_album' === $post->post_type ) {
		$gallery = get_post_meta( $post->ID, 'gallery_images', true );

		foreach ( is_array( $gallery ) ? $gallery : array() as $image ) {
			$ids[] = rytkoset_theme_get_gallery_image_attachment_id( $image );
		}

		if ( function_exists( 'rytkoset_theme_get_gallery_album_video_rows' ) ) {
			foreach ( rytkoset_theme_get_gallery_album_video_rows( $post->ID ) as $video ) {
				$ids[] = $video['video_thumbnail_id'] ?? 0;
			}
		}
	}

	return rytkoset_theme_normalize_media_usage_ids( $ids );
}

/**
 * Builds the usage map from the database: attachment ID => post IDs.
 * Post ID 0 stands for the site logo or icon.
 *
 * @return array<int, int[]>
 */
function rytkoset_theme_build_media_usage_map() {
	$map   = array();
	$posts = get_posts(
		array(
			'post_type'              => rytkoset_theme_get_media_usage_post_types(),
			'post_status'            => rytkoset_theme_get_media_usage_post_statuses(),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		)
	);

	foreach ( $posts as $post ) {
		$ids = array_merge(
			rytkoset_theme_get_attachment_ids_from_content( $post->post_content ),
			rytkoset_theme_get_attachment_ids_from_post_meta( $post )
		);

		foreach ( rytkoset_theme_normalize_media_usage_ids( $ids ) as $attachment_id ) {
			$map[ $attachment_id ][] = (int) $post->ID;
		}
	}

	foreach ( array( get_theme_mod( 'custom_logo' ), get_option( 'site_icon' ) ) as $site_image ) {
		$site_image = is_numeric( $site_image ) ? (int) $site_image : 0;

		if ( $site_image > 0 && ! in_array( 0, $map[ $site_image ] ?? array(), true ) ) {
			$map[ $site_image ][] = 0;
		}
	}

	return $map;
}

/**
 * Returns the usage map.
 *
 * Display uses a 12-hour transient. The delete guard passes $fresh, which
 * ignores the transient and builds the map from the database once per request,
 * so a stale cache can never let an in-use image be deleted. Both copies are
 * dropped whenever a tracked source changes.
 *
 * @param bool $fresh Whether to bypass the transient.
 * @return array<int, int[]>
 */
function rytkoset_theme_get_media_usage_map( $fresh = false ) {
	if ( $fresh ) {
		if ( ! isset( $GLOBALS['rytkoset_media_usage_request_map'] ) ) {
			$GLOBALS['rytkoset_media_usage_request_map'] = rytkoset_theme_build_media_usage_map();
		}

		return $GLOBALS['rytkoset_media_usage_request_map'];
	}

	$map = get_transient( 'rytkoset_media_usage_map' );

	if ( ! is_array( $map ) ) {
		$map = rytkoset_theme_build_media_usage_map();
		set_transient( 'rytkoset_media_usage_map', $map, 12 * HOUR_IN_SECONDS );
	}

	return $map;
}

/**
 * Drops both cached copies of the usage map.
 *
 * @return void
 */
function rytkoset_theme_reset_media_usage_map() {
	unset( $GLOBALS['rytkoset_media_usage_request_map'] );
	delete_transient( 'rytkoset_media_usage_map' );
}

/**
 * Returns the IDs of the posts that use an attachment (0 = site settings).
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $fresh         Whether to bypass the transient.
 * @return int[]
 */
function rytkoset_theme_get_attachment_usage_post_ids( $attachment_id, $fresh = false ) {
	$map = rytkoset_theme_get_media_usage_map( $fresh );

	return $map[ absint( $attachment_id ) ] ?? array();
}

/**
 * Returns the Finnish label for a post type in the usage list.
 *
 * @param string $post_type Post type.
 * @return string
 */
function rytkoset_theme_get_media_usage_type_label( $post_type ) {
	$labels = array(
		'gallery_album'     => __( 'Albumi', 'rytkoset-theme' ),
		'rytkoset_event'    => __( 'Tapahtuma', 'rytkoset-theme' ),
		'product'           => __( 'Tuote', 'rytkoset-theme' ),
		'product_variation' => __( 'Tuote', 'rytkoset-theme' ),
		'post'              => __( 'Blogikirjoitus', 'rytkoset-theme' ),
		'page'              => __( 'Sivu', 'rytkoset-theme' ),
		'digital_magazine'  => __( 'Digilehti', 'rytkoset-theme' ),
		'wp_block'          => __( 'Lohkomalli', 'rytkoset-theme' ),
	);

	return $labels[ $post_type ] ?? __( 'Sisältö', 'rytkoset-theme' );
}

/**
 * Returns the usage entries the current user may see.
 *
 * The delete guard always uses the full map; this only limits what is shown.
 * A title needs read_post and the edit link edit_post; content the user cannot
 * read is only counted.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{items: array<int, array{label: string, url: string}>, hidden: int}
 */
function rytkoset_theme_get_attachment_usage_items( $attachment_id ) {
	$items  = array();
	$hidden = 0;

	foreach ( rytkoset_theme_get_attachment_usage_post_ids( $attachment_id ) as $post_id ) {
		if ( 0 === $post_id ) {
			$items[] = array(
				'label' => __( 'Sivuston logo tai kuvake', 'rytkoset-theme' ),
				'url'   => current_user_can( 'customize' ) ? admin_url( 'customize.php' ) : '',
			);
			continue;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! current_user_can( 'read_post', $post_id ) ) {
			++$hidden;
			continue;
		}

		$title = '' !== $post->post_title ? $post->post_title : __( '(ei otsikkoa)', 'rytkoset-theme' );
		$url   = current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '';

		$items[] = array(
			'label' => rytkoset_theme_get_media_usage_type_label( $post->post_type ) . ': ' . $title,
			'url'   => $url,
		);
	}

	return array(
		'items'  => $items,
		'hidden' => $hidden,
	);
}

/**
 * Renders the usage entries as a short list (at most three, then a count).
 *
 * @param int $attachment_id Attachment ID.
 * @return string Escaped HTML, empty when the attachment is not in use.
 */
function rytkoset_theme_render_attachment_usage( $attachment_id ) {
	$usage = rytkoset_theme_get_attachment_usage_items( $attachment_id );
	$items = $usage['items'];
	$more  = $usage['hidden'] + max( 0, count( $items ) - 3 );
	$items = array_slice( $items, 0, 3 );

	if ( empty( $items ) && 0 === $more ) {
		return '';
	}

	$html = '<ul class="rytkoset-media-usage">';

	foreach ( $items as $item ) {
		$html .= '<li>' . ( '' !== $item['url']
			? '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>'
			: esc_html( $item['label'] ) ) . '</li>';
	}

	if ( $more > 0 ) {
		$html .= '<li>' . esc_html(
			sprintf(
				/* translators: %d: number of other places using the image */
				_n( 'ja %d muu sisältö', 'ja %d muuta sisältöä', $more, 'rytkoset-theme' ),
				$more
			)
		) . '</li>';
	}

	return $html . '</ul>';
}

/**
 * Adds the "Käytössä" column to the Media Library list view.
 *
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function rytkoset_theme_media_usage_column( $columns ) {
	$updated = array();

	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$updated['rytkoset_media_usage'] = __( 'Käytössä', 'rytkoset-theme' );
		}

		$updated[ $key ] = $label;
	}

	if ( ! isset( $updated['rytkoset_media_usage'] ) ) {
		$updated['rytkoset_media_usage'] = __( 'Käytössä', 'rytkoset-theme' );
	}

	return $updated;
}
add_filter( 'manage_media_columns', 'rytkoset_theme_media_usage_column' );

/**
 * Renders the "Käytössä" column.
 *
 * @param string $column        Column key.
 * @param int    $attachment_id Attachment ID.
 * @return void
 */
function rytkoset_theme_render_media_usage_column( $column, $attachment_id ) {
	if ( 'rytkoset_media_usage' === $column ) {
		echo rytkoset_theme_render_attachment_usage( $attachment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while built.
	}
}
add_action( 'manage_media_custom_column', 'rytkoset_theme_render_media_usage_column', 10, 2 );

/**
 * Shows the usage in the attachment details of the grid view and media modal.
 *
 * @param array<string, mixed> $form_fields Attachment form fields.
 * @param WP_Post              $post        Attachment.
 * @return array<string, mixed>
 */
function rytkoset_theme_media_usage_attachment_field( $form_fields, $post ) {
	$html = $post instanceof WP_Post ? rytkoset_theme_render_attachment_usage( $post->ID ) : '';

	if ( '' !== $html ) {
		$form_fields['rytkoset_media_usage'] = array(
			'label' => __( 'Käytössä', 'rytkoset-theme' ),
			'input' => 'html',
			'html'  => $html,
			'helps' => __( 'Kuvaa ei voi poistaa, ennen kuin se on poistettu näistä sisällöistä.', 'rytkoset-theme' ),
		);
	}

	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'rytkoset_theme_media_usage_attachment_field', 10, 2 );

/**
 * Hides "Delete permanently" in the grid view and media modal for an image in
 * use. media-views.js derives the delete permission from nonces.delete.
 *
 * @param array<string, mixed> $response   Attachment data for JS.
 * @param WP_Post              $attachment Attachment.
 * @return array<string, mixed>
 */
function rytkoset_theme_media_usage_hide_js_delete( $response, $attachment ) {
	if ( $attachment instanceof WP_Post && rytkoset_theme_media_usage_protection_enabled() && array() !== rytkoset_theme_get_attachment_usage_post_ids( $attachment->ID ) ) {
		$response['nonces']['delete'] = false;
	}

	return $response;
}
add_filter( 'wp_prepare_attachment_for_js', 'rytkoset_theme_media_usage_hide_js_delete', 10, 2 );

/**
 * Replaces the list view's delete or trash row action for an image in use.
 *
 * @param array<string, string> $actions Row actions.
 * @param WP_Post               $post    Attachment.
 * @return array<string, string>
 */
function rytkoset_theme_media_usage_row_actions( $actions, $post ) {
	if ( $post instanceof WP_Post && ( isset( $actions['delete'] ) || isset( $actions['trash'] ) ) && rytkoset_theme_media_usage_protection_enabled() && array() !== rytkoset_theme_get_attachment_usage_post_ids( $post->ID ) ) {
		// With MEDIA_TRASH core offers "trash" instead of "delete"; both are refused.
		unset( $actions['delete'], $actions['trash'] );
		$actions['rytkoset_in_use'] = '<span class="rytkoset-media-in-use">' . esc_html__( 'Käytössä, ei poistettavissa', 'rytkoset-theme' ) . '</span>';
	}

	return $actions;
}
add_filter( 'media_row_actions', 'rytkoset_theme_media_usage_row_actions', 10, 2 );

/**
 * Tells whether in-use images are protected from deletion.
 *
 * @return bool
 */
function rytkoset_theme_media_usage_protection_enabled() {
	/**
	 * Filters whether an attachment in use may be deleted (#702).
	 *
	 * @param bool $enabled Whether the protection is on.
	 */
	return (bool) apply_filters( 'rytkoset_theme_block_in_use_attachment_delete', true );
}

/**
 * Refuses to delete an attachment that is still in use.
 *
 * Covers the list view, the grid view's AJAX delete (wp_delete_post routes to
 * wp_delete_attachment) and WP-CLI. Uses a fresh map, never the cached one.
 * Core's own bulk delete stops at the first refused image with its generic
 * error; earlier unused images in the same batch are already deleted.
 *
 * @param WP_Post|false|null $delete Short-circuit value from earlier callbacks.
 * @param WP_Post            $post   Attachment.
 * @return WP_Post|false|null
 */
function rytkoset_theme_media_usage_prevent_delete( $delete, $post ) {
	if ( null !== $delete || ! $post instanceof WP_Post || ! rytkoset_theme_media_usage_protection_enabled() ) {
		return $delete;
	}

	return array() !== rytkoset_theme_get_attachment_usage_post_ids( $post->ID, true ) ? false : $delete;
}
add_filter( 'pre_delete_attachment', 'rytkoset_theme_media_usage_prevent_delete', 10, 2 );

/**
 * Refuses to move an attachment in use to the trash. Trash is only available
 * when MEDIA_TRASH is on; wp_trash_post() does not run pre_delete_attachment.
 *
 * @param bool|null $trash Short-circuit value from earlier callbacks.
 * @param WP_Post   $post  Post being trashed.
 * @return bool|null
 */
function rytkoset_theme_media_usage_prevent_trash( $trash, $post ) {
	if ( null !== $trash || ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! rytkoset_theme_media_usage_protection_enabled() ) {
		return $trash;
	}

	return array() !== rytkoset_theme_get_attachment_usage_post_ids( $post->ID, true ) ? false : $trash;
}
add_filter( 'pre_trash_post', 'rytkoset_theme_media_usage_prevent_trash', 10, 2 );

/**
 * Drops the cached map when tracked content changes.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function rytkoset_theme_media_usage_post_changed( $post_id ) {
	if ( in_array( get_post_type( $post_id ), rytkoset_theme_get_media_usage_post_types(), true ) ) {
		rytkoset_theme_reset_media_usage_map();
	}
}
add_action( 'save_post', 'rytkoset_theme_media_usage_post_changed' );
add_action( 'deleted_post', 'rytkoset_theme_media_usage_post_changed' );
add_action( 'trashed_post', 'rytkoset_theme_media_usage_post_changed' );
add_action( 'untrashed_post', 'rytkoset_theme_media_usage_post_changed' );

/**
 * Drops the cached map when tracked post meta changes.
 *
 * @param int    $meta_id   Meta ID.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @return void
 */
function rytkoset_theme_media_usage_meta_changed( $meta_id, $object_id, $meta_key ) {
	$meta_key = (string) $meta_key;

	if ( in_array( $meta_key, array( '_thumbnail_id', '_product_image_gallery', 'gallery_images' ), true ) || 0 === strpos( $meta_key, 'gallery_videos' ) ) {
		rytkoset_theme_reset_media_usage_map();
	}
}
add_action( 'added_post_meta', 'rytkoset_theme_media_usage_meta_changed', 10, 3 );
add_action( 'updated_post_meta', 'rytkoset_theme_media_usage_meta_changed', 10, 3 );
add_action( 'deleted_post_meta', 'rytkoset_theme_media_usage_meta_changed', 10, 3 );
add_action( 'update_option_site_icon', 'rytkoset_theme_reset_media_usage_map' );
add_action( 'customize_save_after', 'rytkoset_theme_reset_media_usage_map' );
