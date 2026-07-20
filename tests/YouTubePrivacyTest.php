<?php
/**
 * Tests for inc/youtube-privacy.php — rewriting YouTube oEmbed iframes to the
 * privacy-enhanced youtube-nocookie.com host.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class YouTubePrivacyTest extends Rytkoset_Theme_Test_Case {

	public function test_www_youtube_embed_is_rewritten(): void {
		$html   = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" title="Video"></iframe>';
		$result = rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html );

		$this->assertStringContainsString( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?feature=oembed', $result );
		$this->assertStringNotContainsString( '//www.youtube.com/embed/', $result );
	}

	public function test_iframe_title_attribute_is_preserved(): void {
		$html   = '<iframe src="https://www.youtube.com/embed/abc123" title="Tapahtuman tallenne" allowfullscreen></iframe>';
		$result = rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html );

		$this->assertStringContainsString( 'title="Tapahtuman tallenne"', $result );
		$this->assertStringContainsString( 'allowfullscreen', $result );
	}

	public function test_non_www_youtube_embed_is_rewritten(): void {
		$html   = '<iframe src="https://youtube.com/embed/abc123"></iframe>';
		$result = rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html );

		$this->assertStringContainsString( 'https://www.youtube-nocookie.com/embed/abc123', $result );
	}

	public function test_protocol_relative_embed_is_rewritten(): void {
		$html   = '<iframe src="//www.youtube.com/embed/abc123"></iframe>';
		$result = rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html );

		$this->assertStringContainsString( '//www.youtube-nocookie.com/embed/abc123', $result );
	}

	public function test_already_nocookie_embed_is_unchanged(): void {
		$html = '<iframe src="https://www.youtube-nocookie.com/embed/abc123" title="Video"></iframe>';

		$this->assertSame( $html, rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html ) );
	}

	public function test_non_youtube_html_passes_through(): void {
		$html = '<iframe src="https://player.vimeo.com/video/12345" title="Video"></iframe>';

		$this->assertSame( $html, rytkoset_theme_rewrite_youtube_embed_to_nocookie( $html ) );
	}

	public function test_empty_and_non_string_input(): void {
		$this->assertSame( '', rytkoset_theme_rewrite_youtube_embed_to_nocookie( '' ) );
		$this->assertNull( rytkoset_theme_rewrite_youtube_embed_to_nocookie( null ) );
	}

	public function test_filter_is_registered_on_embed_oembed_html(): void {
		$html   = '<iframe src="https://www.youtube.com/embed/abc123?feature=oembed" title="Video"></iframe>';
		$result = apply_filters( 'embed_oembed_html', $html, 'https://youtu.be/abc123', array(), 0 );

		$this->assertStringContainsString( 'www.youtube-nocookie.com/embed/abc123', $result );
	}
}
