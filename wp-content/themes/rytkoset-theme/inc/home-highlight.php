<?php
/**
 * Front-page current-content highlight.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_home_highlight' ) ) {
	/**
	 * Returns the post shown in the front-page current-content highlight.
	 *
	 * The nearest upcoming public event always wins. When there are no upcoming
	 * events, the latest published gallery album or blog post is returned by
	 * publish date. Password-protected content, event-adjacent services, and
	 * past or undated events are deliberately excluded.
	 *
	 * @return WP_Post|null Highlight post, or null when no eligible content exists.
	 */
	function rytkoset_theme_get_home_highlight() {
		$event_id = rytkoset_theme_get_next_upcoming_event_id( '', false, true );

		if ( $event_id > 0 ) {
			$event     = get_post( $event_id );
			$permalink = $event instanceof WP_Post ? get_permalink( $event ) : false;

			if ( $event instanceof WP_Post && is_string( $permalink ) && '' !== $permalink ) {
				return $event;
			}
		}

		$recent_content = get_posts(
			array(
				'post_type'           => array( 'gallery_album', 'post' ),
				'post_status'         => 'publish',
				'has_password'        => false,
				'numberposts'         => 1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'suppress_filters'    => false,
			)
		);

		$highlight = reset( $recent_content );
		$permalink = $highlight instanceof WP_Post ? get_permalink( $highlight ) : false;

		return $highlight instanceof WP_Post && is_string( $permalink ) && '' !== $permalink
			? $highlight
			: null;
	}
}
