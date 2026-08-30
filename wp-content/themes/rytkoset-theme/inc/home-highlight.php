<?php
/**
 * Front-page current-content highlight.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the post shown in the front-page current-content highlight.
 *
 * The nearest upcoming event always wins. When there are no upcoming events,
 * the latest published gallery album or blog post is returned by publish date.
 * Past and undated events are deliberately excluded from the fallback.
 *
 * @return WP_Post|null Highlight post, or null when no eligible content exists.
 */
function rytkoset_theme_get_home_highlight() {
	$event_id = rytkoset_theme_get_next_upcoming_event_id();

	if ( $event_id > 0 ) {
		$event = get_post( $event_id );

		if ( $event instanceof WP_Post ) {
			return $event;
		}
	}

	$recent_content = get_posts(
		array(
			'post_type'           => array( 'gallery_album', 'post' ),
			'post_status'         => 'publish',
			'numberposts'         => 1,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
		)
	);

	$highlight = reset( $recent_content );

	return $highlight instanceof WP_Post ? $highlight : null;
}
