<?php
/**
 * Tests for the front-page current-content highlight (#657).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class HomeHighlightTest extends Rytkoset_Theme_Test_Case {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['rytkoset_test_now'] = '2026-08-30 12:00:00';
	}

	/**
	 * Registers a post with a controllable publish date.
	 */
	private function register_content( int $id, string $type, string $date, string $status = 'publish' ): WP_Post {
		$post                = rytkoset_test_register_post( $id, $type, 'Sisältö ' . $id );
		$post->post_date     = $date;
		$post->post_date_gmt = $date;
		$post->post_status   = $status;

		return $post;
	}

	/**
	 * Registers an event with its public event date.
	 */
	private function register_event( int $id, string $date, string $status = 'publish' ): WP_Post {
		$event              = rytkoset_test_register_post( $id, 'rytkoset_event', 'Tapahtuma ' . $id );
		$event->post_status = $status;
		update_post_meta( $id, rytkoset_theme_get_event_date_meta_key(), $date );

		return $event;
	}

	public function test_nearest_upcoming_event_overrides_newer_content(): void {
		$this->register_content( 10, 'gallery_album', '2026-08-30 10:00:00' );
		$this->register_content( 11, 'post', '2026-08-30 11:00:00' );
		$this->register_event( 20, '2026-09-15' );
		$this->register_event( 21, '2026-09-05' );

		$highlight = rytkoset_theme_get_home_highlight();

		$this->assertInstanceOf( WP_Post::class, $highlight );
		$this->assertSame( 21, $highlight->ID );
	}

	public function test_latest_album_or_blog_post_wins_without_upcoming_event(): void {
		$this->register_event( 5, '2026-08-29' );
		$this->register_content( 10, 'gallery_album', '2026-08-30 10:00:00' );
		$this->register_content( 11, 'post', '2026-08-30 11:00:00' );

		$highlight = rytkoset_theme_get_home_highlight();

		$this->assertInstanceOf( WP_Post::class, $highlight );
		$this->assertSame( 11, $highlight->ID );
	}

	public function test_fallback_excludes_drafts_and_past_events(): void {
		$this->register_event( 5, '2026-08-29' );
		$this->register_content( 10, 'gallery_album', '2026-08-28 10:00:00' );
		$this->register_content( 11, 'post', '2026-08-30 11:00:00', 'draft' );

		$highlight = rytkoset_theme_get_home_highlight();

		$this->assertInstanceOf( WP_Post::class, $highlight );
		$this->assertSame( 10, $highlight->ID );
	}

	public function test_returns_null_without_eligible_content(): void {
		$this->register_event( 5, '2026-08-29' );

		$this->assertNull( rytkoset_theme_get_home_highlight() );
	}
}
