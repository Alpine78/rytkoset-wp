<?php
/**
 * Tests for rytkoset_theme_sort_gallery_block_by_filename() in inc/gallery-albums.php.
 *
 * Regression coverage for #677: an album can contain more than one core/gallery
 * block (one per filterable section), and each must be sorted and cached
 * independently instead of sharing a single per-album cache key.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class GalleryAlbumSortTest extends Rytkoset_Theme_Test_Case {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['rytkoset_test_is_singular']  = true;
		$GLOBALS['rytkoset_test_current_post'] = 500;
	}

	/**
	 * Registers a stub attachment with the given filename-derived title.
	 */
	private function register_attachment( int $id, string $title ): void {
		$post              = rytkoset_test_register_post( $id, 'attachment', $title );
		$post->post_status = 'inherit';
	}

	/**
	 * Builds a minimal parsed core/gallery block referencing the given image IDs.
	 *
	 * @param int[] $image_ids Attachment IDs in their as-authored (unsorted) order.
	 */
	private function gallery_block( array $image_ids ): array {
		return array(
			'blockName'   => 'core/gallery',
			'innerBlocks' => array_map(
				static fn( int $id ) => array(
					'blockName' => 'core/image',
					'attrs'     => array( 'id' => $id ),
				),
				$image_ids
			),
		);
	}

	/**
	 * Extracts the image IDs from a (possibly sorted) parsed gallery block.
	 *
	 * @return int[]
	 */
	private function block_image_ids( array $block ): array {
		return array_map(
			static fn( array $inner ) => $inner['attrs']['id'],
			$block['innerBlocks']
		);
	}

	public function test_two_galleries_in_one_album_sort_independently(): void {
		// Section A: selected out of filename order.
		$this->register_attachment( 1, 'IMG_0003' );
		$this->register_attachment( 2, 'IMG_0001' );
		$this->register_attachment( 3, 'IMG_0002' );

		// Section B: a disjoint set of images, also out of filename order.
		$this->register_attachment( 11, 'IMG_0020' );
		$this->register_attachment( 12, 'IMG_0018' );
		$this->register_attachment( 13, 'IMG_0019' );

		$section_a = $this->gallery_block( array( 1, 2, 3 ) );
		$section_b = $this->gallery_block( array( 11, 12, 13 ) );

		$sorted_a = rytkoset_theme_sort_gallery_block_by_filename( $section_a );
		$sorted_b = rytkoset_theme_sort_gallery_block_by_filename( $section_b );

		$this->assertSame( array( 2, 3, 1 ), $this->block_image_ids( $sorted_a ) );
		$this->assertSame( array( 12, 13, 11 ), $this->block_image_ids( $sorted_b ) );
	}

	public function test_old_flat_cache_is_ignored(): void {
		$this->register_attachment( 11, 'IMG_0020' );
		$this->register_attachment( 12, 'IMG_0018' );
		set_transient( 'album_gallery_order_500', array( 1, 2, 3 ), HOUR_IN_SECONDS );

		$sorted = rytkoset_theme_sort_gallery_block_by_filename( $this->gallery_block( array( 11, 12 ) ) );

		$this->assertSame( array( 12, 11 ), $this->block_image_ids( $sorted ) );
	}

	public function test_repeat_render_of_same_gallery_uses_cache_consistently(): void {
		$this->register_attachment( 1, 'IMG_0003' );
		$this->register_attachment( 2, 'IMG_0001' );
		$this->register_attachment( 3, 'IMG_0002' );

		$section_a = $this->gallery_block( array( 1, 2, 3 ) );

		$first  = rytkoset_theme_sort_gallery_block_by_filename( $section_a );
		$second = rytkoset_theme_sort_gallery_block_by_filename( $section_a );

		$this->assertSame( array( 2, 3, 1 ), $this->block_image_ids( $first ) );
		$this->assertSame( $this->block_image_ids( $first ), $this->block_image_ids( $second ) );
	}

	public function test_saving_album_clears_cache_for_every_gallery_instance(): void {
		$this->register_attachment( 1, 'IMG_0003' );
		$this->register_attachment( 2, 'IMG_0001' );
		$this->register_attachment( 11, 'IMG_0020' );
		$this->register_attachment( 12, 'IMG_0018' );

		$section_a = $this->gallery_block( array( 1, 2 ) );
		$section_b = $this->gallery_block( array( 11, 12 ) );

		rytkoset_theme_sort_gallery_block_by_filename( $section_a );
		rytkoset_theme_sort_gallery_block_by_filename( $section_b );

		rytkoset_theme_invalidate_album_gallery_order_transient( 500 );

		$this->assertFalse( get_transient( 'album_gallery_order_500' ) );
	}
}
