<?php
/**
 * Tests for inc/media-library.php — default title ordering of the media selection modal.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MediaLibraryModalTest extends Rytkoset_Theme_Test_Case {

	protected function tearDown(): void {
		unset( $_REQUEST['action'], $_REQUEST['query'] );
		parent::tearDown();
	}

	public function test_modal_defaults_to_title_ascending(): void {
		$_REQUEST['action'] = 'query-attachments';

		$args = rytkoset_theme_sort_media_modal_by_filename( array() );

		$this->assertSame( 'title', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
	}

	public function test_modal_ignores_other_ajax_actions(): void {
		$_REQUEST['action'] = 'save-attachment';

		$this->assertSame( array(), rytkoset_theme_sort_media_modal_by_filename( array() ) );
	}

	public function test_modal_respects_custom_orderby(): void {
		$_REQUEST['action'] = 'query-attachments';
		$_REQUEST['query']  = array( 'orderby' => 'menuOrder' );

		$args = rytkoset_theme_sort_media_modal_by_filename( array( 'existing' => true ) );

		$this->assertArrayNotHasKey( 'orderby', $args );
		$this->assertSame( array( 'existing' => true ), $args );
	}

	public function test_modal_allows_known_orderby_to_be_normalized(): void {
		$_REQUEST['action'] = 'query-attachments';
		$_REQUEST['query']  = array( 'orderby' => 'date' );

		$args = rytkoset_theme_sort_media_modal_by_filename( array() );

		$this->assertSame( 'title', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
	}
}
