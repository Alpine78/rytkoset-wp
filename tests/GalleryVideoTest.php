<?php
/**
 * Tests for inc/gallery-albums.php — YouTube ID parsing, embed URL building and caption stripping.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class GalleryVideoTest extends Rytkoset_Theme_Test_Case {

	// --- get_youtube_video_id ----------------------------------------------

	public function test_youtube_id_from_short_url(): void {
		$this->assertSame( 'dQw4w9WgXcQ', rytkoset_theme_get_youtube_video_id( 'https://youtu.be/dQw4w9WgXcQ' ) );
	}

	public function test_youtube_id_from_watch_url(): void {
		$this->assertSame( 'dQw4w9WgXcQ', rytkoset_theme_get_youtube_video_id( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );
	}

	public function test_youtube_id_from_embed_and_shorts(): void {
		$this->assertSame( 'dQw4w9WgXcQ', rytkoset_theme_get_youtube_video_id( 'https://www.youtube.com/embed/dQw4w9WgXcQ' ) );
		$this->assertSame( 'abc123XYZ_-', rytkoset_theme_get_youtube_video_id( 'https://www.youtube.com/shorts/abc123XYZ_-' ) );
	}

	public function test_non_youtube_url_returns_empty(): void {
		$this->assertSame( '', rytkoset_theme_get_youtube_video_id( 'https://vimeo.com/12345' ) );
		$this->assertSame( '', rytkoset_theme_get_youtube_video_id( '' ) );
	}

	// --- get_video_embed_url -----------------------------------------------

	public function test_embed_url_built_for_youtube(): void {
		$url = rytkoset_theme_get_video_embed_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );

		$this->assertStringContainsString( 'youtube-nocookie.com/embed/dQw4w9WgXcQ', $url );
		$this->assertStringContainsString( 'rel=0', $url );
	}

	public function test_embed_url_passthrough_for_non_youtube(): void {
		$this->assertSame(
			'https://vimeo.com/12345',
			rytkoset_theme_get_video_embed_url( 'https://vimeo.com/12345' )
		);
	}

	public function test_embed_url_empty_for_empty_input(): void {
		$this->assertSame( '', rytkoset_theme_get_video_embed_url( '' ) );
	}

	// --- strip_gallery_album_block_captions --------------------------------

	public function test_figcaptions_are_removed(): void {
		$content = "<figure><img src=\"a.jpg\"><figcaption>Kuvateksti</figcaption></figure>";
		$result  = rytkoset_theme_strip_gallery_album_block_captions( $content );

		$this->assertStringNotContainsString( '<figcaption>', $result );
		$this->assertStringNotContainsString( 'Kuvateksti', $result );
	}

	public function test_non_string_content_is_returned_as_is(): void {
		$this->assertNull( rytkoset_theme_strip_gallery_album_block_captions( null ) );
	}
}
