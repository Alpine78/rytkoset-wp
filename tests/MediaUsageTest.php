<?php
/**
 * Tests for inc/media-usage.php: media usage and delete protection (#702).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MediaUsageTest extends Rytkoset_Theme_Test_Case {

	protected function setUp(): void {
		parent::setUp();
		unset( $GLOBALS['rytkoset_media_usage_request_map'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rytkoset_media_usage_request_map'] );
		parent::tearDown();
	}

	private function post( int $id, string $type, string $title, string $content = '', string $status = 'publish' ): WP_Post {
		$post                                  = new WP_Post( $id, $type, $title );
		$post->post_content                    = $content;
		$post->post_status                     = $status;
		$GLOBALS['rytkoset_test_posts'][ $id ] = $post;
		return $post;
	}

	public function test_content_ids_cover_blocks_classic_markup_and_shortcode(): void {
		$content = '<!-- wp:gallery {"linkTo":"none","style":{"spacing":{"blockGap":"8px"}}} -->'
			. '<!-- wp:image {"id":11,"sizeSlug":"large"} --><img class="wp-image-11"/><!-- /wp:image -->'
			. '<!-- /wp:gallery -->'
			. '<!-- wp:gallery {"ids":[12,"13"]} /-->'
			. '<!-- wp:core/cover {"id":14,"dimRatio":50} -->x<!-- /wp:core/cover -->'
			. '<!-- wp:media-text {"mediaId":15} -->x<!-- /wp:media-text -->'
			. '<!-- wp:paragraph {"id":99} --><p>Ei mediaa</p><!-- /wp:paragraph -->'
			. '<!-- wp:image {broken json} /-->'
			. '<p><img class="alignnone wp-image-16 size-full" /></p>'
			. '[gallery columns="3" ids="17, 18,19"]';

		$this->assertSame( array( 11, 12, 13, 14, 15, 16, 17, 18, 19 ), rytkoset_theme_get_attachment_ids_from_content( $content ) );
		$this->assertSame( array(), rytkoset_theme_get_attachment_ids_from_content( '' ) );
	}

	public function test_content_ids_cover_background_images_and_space_separated_shortcode(): void {
		$content = '<!-- wp:group {"style":{"background":{"backgroundImage":{"url":"https://rytkoset.test/a.jpg","id":40,"source":"file"}}}} --><div></div><!-- /wp:group -->'
			. '[gallery ids="41 42"]'
			. "[gallery ids='43']"
			. '[gallery ids=44,45]'
			. '[gallery IDS="46 47"]'
			. '[gallery include="48,49" columns="2"]';

		$this->assertSame( array( 40, 41, 42, 43, 44, 45, 46, 47, 48, 49 ), rytkoset_theme_get_attachment_ids_from_content( $content ) );
	}

	public function test_text_examples_are_not_usage(): void {
		$content = '<p>Kuvan luokka on muotoa wp-image-30.</p>'
			. '<p>Esimerkki: [[gallery ids="31"]]</p>'
			. '<pre>&lt;img class="wp-image-32"&gt;</pre>'
			. '<!-- <img class="wp-image-33"> [gallery ids="34"] -->';

		$this->assertSame( array(), rytkoset_theme_get_attachment_ids_from_content( $content ) );
	}

	public function test_meta_ids_cover_thumbnail_product_gallery_acf_and_video_thumbnails(): void {
		$album = $this->post( 50, 'gallery_album', 'Sukujuhla 2026' );
		update_post_meta( 50, '_thumbnail_id', '21' );
		update_post_meta( 50, 'gallery_images', array( '22', array( 'ID' => 23 ), 24 ) );
		update_post_meta( 50, 'gallery_videos', 1 );
		update_post_meta( 50, 'gallery_videos_0_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
		update_post_meta( 50, 'gallery_videos_0_video_thumbnail', '25' );

		$product = $this->post( 60, 'product', 'Sukulehti' );
		update_post_meta( 60, '_product_image_gallery', '26,27,' );

		$this->assertSame( array( 21, 22, 23, 24, 25 ), rytkoset_theme_get_attachment_ids_from_post_meta( $album ) );
		$this->assertSame( array( 26, 27 ), rytkoset_theme_get_attachment_ids_from_post_meta( $product ) );
	}

	public function test_map_counts_drafts_and_patterns_but_not_trash_and_site_images(): void {
		$this->post( 50, 'gallery_album', 'Albumi', '<!-- wp:image {"id":30} /-->' );
		$this->post( 51, 'rytkoset_event', 'Tapahtuma', '<!-- wp:image {"id":30} /-->', 'draft' );
		$this->post( 52, 'wp_block', 'Synkronoitu malli', '<!-- wp:image {"id":31} /-->' );
		$this->post( 53, 'page', 'Roskakorissa', '<!-- wp:image {"id":32} /-->', 'trash' );
		$this->post( 54, 'topic', 'Foorumi', '<!-- wp:image {"id":33} /-->' );
		$GLOBALS['rytkoset_test_options']['theme_mod_custom_logo'] = 34;
		$GLOBALS['rytkoset_test_options']['site_icon']             = '34';

		$map = rytkoset_theme_build_media_usage_map();

		$this->assertSame( array( 50, 51 ), $map[30] );
		$this->assertSame( array( 52 ), $map[31] );
		$this->assertArrayNotHasKey( 32, $map );
		$this->assertArrayNotHasKey( 33, $map );
		$this->assertSame( array( 0 ), $map[34] );
	}

	public function test_fresh_map_ignores_stale_transient_and_resets_on_change(): void {
		set_transient( 'rytkoset_media_usage_map', array() );
		$this->post( 50, 'gallery_album', 'Albumi', '<!-- wp:image {"id":30} /-->' );

		// The cached copy says unused, the fresh one knows better.
		$this->assertSame( array(), rytkoset_theme_get_attachment_usage_post_ids( 30 ) );
		$this->assertSame( array( 50 ), rytkoset_theme_get_attachment_usage_post_ids( 30, true ) );

		// Removing the image from the album in the same request drops both copies.
		$GLOBALS['rytkoset_test_posts'][50]->post_content = '';
		rytkoset_theme_media_usage_post_changed( 50 );
		$this->assertSame( array(), rytkoset_theme_get_attachment_usage_post_ids( 30, true ) );
		$this->assertFalse( get_transient( 'rytkoset_media_usage_map' ) );

		// Untracked meta keys keep the cache.
		set_transient( 'rytkoset_media_usage_map', array( 1 => array( 2 ) ) );
		rytkoset_theme_media_usage_meta_changed( 1, 50, '_edit_lock' );
		$this->assertNotFalse( get_transient( 'rytkoset_media_usage_map' ) );
		rytkoset_theme_media_usage_meta_changed( 1, 50, 'gallery_videos_0_video_thumbnail' );
		$this->assertFalse( get_transient( 'rytkoset_media_usage_map' ) );
	}

	public function test_delete_is_refused_only_for_images_in_use(): void {
		$this->post( 50, 'gallery_album', 'Albumi', '<!-- wp:image {"id":30} /-->' );
		$used   = $this->post( 30, 'attachment', 'kuva-001' );
		$unused = $this->post( 31, 'attachment', 'kuva-002' );

		$this->assertFalse( rytkoset_theme_media_usage_prevent_delete( null, $used ) );
		$this->assertNull( rytkoset_theme_media_usage_prevent_delete( null, $unused ) );

		// An earlier callback's decision is respected.
		$this->assertSame( $used, rytkoset_theme_media_usage_prevent_delete( $used, $used ) );

		$off = static function () {
			return false;
		};
		add_filter( 'rytkoset_theme_block_in_use_attachment_delete', $off );
		try {
			$this->assertNull( rytkoset_theme_media_usage_prevent_delete( null, $used ) );
		} finally {
			remove_filter( 'rytkoset_theme_block_in_use_attachment_delete', $off );
		}
	}

	public function test_trash_is_refused_only_for_attachments_in_use(): void {
		$this->post( 50, 'gallery_album', 'Albumi', '<!-- wp:image {"id":30} /-->' );
		$used   = $this->post( 30, 'attachment', 'kuva-001' );
		$unused = $this->post( 31, 'attachment', 'kuva-002' );
		$page   = $this->post( 32, 'page', 'Sivu' );

		$this->assertFalse( rytkoset_theme_media_usage_prevent_trash( null, $used ) );
		$this->assertNull( rytkoset_theme_media_usage_prevent_trash( null, $unused ) );
		$this->assertNull( rytkoset_theme_media_usage_prevent_trash( null, $page ) );
	}

	public function test_delete_controls_are_hidden_for_images_in_use(): void {
		$this->post( 50, 'gallery_album', 'Albumi', '<!-- wp:image {"id":30} /-->' );
		$used   = $this->post( 30, 'attachment', 'kuva-001' );
		$unused = $this->post( 31, 'attachment', 'kuva-002' );

		$response = rytkoset_theme_media_usage_hide_js_delete( array( 'nonces' => array( 'delete' => 'abc' ) ), $used );
		$this->assertFalse( $response['nonces']['delete'] );
		$this->assertSame( 'abc', rytkoset_theme_media_usage_hide_js_delete( array( 'nonces' => array( 'delete' => 'abc' ) ), $unused )['nonces']['delete'] );

		$actions = array(
			'edit'   => 'Muokkaa',
			'delete' => 'Poista pysyvästi',
			'view'   => 'Näytä',
		);
		$used_actions = rytkoset_theme_media_usage_row_actions( $actions, $used );
		$this->assertArrayNotHasKey( 'delete', $used_actions );
		$this->assertStringContainsString( 'Käytössä, ei poistettavissa', $used_actions['rytkoset_in_use'] );
		$this->assertSame( $actions, rytkoset_theme_media_usage_row_actions( $actions, $unused ) );

		// MEDIA_TRASH replaces "delete" with "trash".
		$trash_actions = rytkoset_theme_media_usage_row_actions(
			array(
				'edit'  => 'Muokkaa',
				'trash' => 'Roskakoriin',
			),
			$used
		);
		$this->assertSame( array( 'edit', 'rytkoset_in_use' ), array_keys( $trash_actions ) );
	}

	public function test_usage_list_respects_read_and_edit_permissions(): void {
		$this->post( 50, 'gallery_album', 'Sukujuhla <2026>', '<!-- wp:image {"id":30} /-->' );
		$this->post( 51, 'rytkoset_event', 'Yksityinen', '<!-- wp:image {"id":30} /-->', 'private' );

		// Without read access the content is only counted.
		$this->assertSame( '<ul class="rytkoset-media-usage"><li>ja 2 muuta sisältöä</li></ul>', rytkoset_theme_render_attachment_usage( 30 ) );

		$GLOBALS['rytkoset_test_caps']['read_post'] = true;
		$html = rytkoset_theme_render_attachment_usage( 30 );
		$this->assertStringContainsString( '<li>Albumi: Sukujuhla &lt;2026&gt;</li>', $html );
		$this->assertStringNotContainsString( '<a ', $html );

		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;
		$this->assertMatchesRegularExpression( '#<a href="[^"]*post=50[^"]*">Albumi: Sukujuhla &lt;2026&gt;</a>#', rytkoset_theme_render_attachment_usage( 30 ) );

		$this->assertSame( '', rytkoset_theme_render_attachment_usage( 99 ) );
	}

	public function test_usage_list_shows_three_entries_and_a_count(): void {
		$GLOBALS['rytkoset_test_caps']['read_post'] = true;

		foreach ( array( 50, 51, 52, 53, 54 ) as $id ) {
			$this->post( $id, 'page', 'Sivu ' . $id, '<!-- wp:image {"id":30} /-->' );
		}

		$html = rytkoset_theme_render_attachment_usage( 30 );
		$this->assertSame( 4, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( 'ja 2 muuta sisältöä', $html );
	}

	public function test_column_goes_before_date(): void {
		$this->assertSame(
			array( 'title', 'author', 'rytkoset_media_usage', 'date' ),
			array_keys(
				rytkoset_theme_media_usage_column(
					array(
						'title'  => 'Tiedosto',
						'author' => 'Tekijä',
						'date'   => 'Päiväys',
					)
				)
			)
		);
	}
}
