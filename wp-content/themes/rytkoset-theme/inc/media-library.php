<?php
/**
 * Admin media library adjustments.
 *
 * @package RytkosetTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sorts the Media Library admin screen by attachment title by default.
 *
 * WordPress creates attachment titles from the uploaded filename by default.
 * For zero-padded album filenames this gives the intended chronological order
 * without requiring a paid media library ordering plugin.
 *
 * @param WP_Query $query Current admin query.
 */
function rytkoset_theme_sort_media_library_by_filename( $query ) {
	global $pagenow;

	if ( ! is_admin() || 'upload.php' !== $pagenow || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}

	if ( 'attachment' !== $query->get( 'post_type' ) ) {
		return;
	}

	// Respect explicit admin sorting from column headers or query parameters.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only Media Library sorting parameters.
	if ( isset( $_GET['orderby'] ) || isset( $_GET['order'] ) ) {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$query->set( 'orderby', 'title' );
	$query->set( 'order', 'ASC' );
}
add_action( 'pre_get_posts', 'rytkoset_theme_sort_media_library_by_filename' );

/**
 * Sorts the media selection modal by attachment title when no custom order is requested.
 *
 * This affects the WordPress media modal used by ACF gallery fields while keeping
 * search, MIME type filters and date filters intact.
 *
 * @param array $query_args Attachment query arguments.
 * @return array
 */
function rytkoset_theme_sort_media_modal_by_filename( $query_args ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only media modal query parameters.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

	if ( 'query-attachments' !== $action ) {
		return $query_args;
	}

	$request_query = array();

	if ( isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ) {
		$request_query = wp_unslash( $_REQUEST['query'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual allowlisted values are sanitized below.
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$requested_orderby = isset( $request_query['orderby'] ) ? sanitize_key( $request_query['orderby'] ) : '';
	$requested_order   = isset( $request_query['order'] ) ? strtoupper( sanitize_key( $request_query['order'] ) ) : '';

	// Do not override plugin- or user-provided custom ordering.
	if ( $requested_orderby && ! in_array( $requested_orderby, array( 'date', 'title' ), true ) ) {
		return $query_args;
	}

	if ( $requested_order && ! in_array( $requested_order, array( 'ASC', 'DESC' ), true ) ) {
		return $query_args;
	}

	$query_args['orderby'] = 'title';
	$query_args['order']   = 'ASC';

	return $query_args;
}
add_filter( 'ajax_query_attachments_args', 'rytkoset_theme_sort_media_modal_by_filename' );
