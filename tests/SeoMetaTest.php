<?php
/**
 * Tests for inc/seo-meta.php — the shared access-restriction gate and Rank Math JSON-LD guard.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class SeoMetaTest extends Rytkoset_Theme_Test_Case {

	private function restricted_magazine( int $id ): void {
		rytkoset_test_register_post( $id, 'digital_magazine', 'Sukuviesti', 0 );
		update_post_meta( $id, '_rytkoset_magazine_access_mode', 'members_only' );
	}

	private function restricted_page( int $id ): void {
		rytkoset_test_register_post( $id, 'page', 'Jäsensivu', 0 );
		update_post_meta( $id, '_rytkoset_members_only', 'yes' );
	}

	// --- content_is_access_restricted --------------------------------------

	public function test_non_post_is_not_restricted(): void {
		$this->assertFalse( rytkoset_theme_content_is_access_restricted( 0 ) );
	}

	public function test_restricted_magazine_is_restricted(): void {
		$this->restricted_magazine( 10 );
		$this->assertTrue( rytkoset_theme_content_is_access_restricted( 10 ) );
	}

	public function test_restricted_members_only_page_is_restricted(): void {
		$this->restricted_page( 20 );
		$this->assertTrue( rytkoset_theme_content_is_access_restricted( 20 ) );
	}

	public function test_free_magazine_is_not_restricted(): void {
		rytkoset_test_register_post( 10, 'digital_magazine', 'Avoin', 0 );
		update_post_meta( 10, '_rytkoset_magazine_access_mode', 'free' );

		$this->assertFalse( rytkoset_theme_content_is_access_restricted( 10 ) );
	}

	public function test_plain_page_is_not_restricted(): void {
		rytkoset_test_register_post( 30, 'page', 'Tavallinen', 0 );
		$this->assertFalse( rytkoset_theme_content_is_access_restricted( 30 ) );
	}

	// --- rankmath_protect_jsonld -------------------------------------------

	public function test_jsonld_descriptions_blanked_when_restricted(): void {
		$this->restricted_magazine( 10 );
		$GLOBALS['rytkoset_test_is_singular'] = true;
		$GLOBALS['rytkoset_test_queried_id']  = 10;

		$data = array(
			'article' => array( 'description' => 'Salainen kuvaus' ),
			'website' => array( 'name' => 'Rytköset' ),
		);

		$filtered = rytkoset_theme_rankmath_protect_jsonld( $data );

		$this->assertSame( '', $filtered['article']['description'] );
		$this->assertSame( 'Rytköset', $filtered['website']['name'] );
	}

	public function test_jsonld_untouched_when_not_restricted(): void {
		rytkoset_test_register_post( 30, 'page', 'Tavallinen', 0 );
		$GLOBALS['rytkoset_test_is_singular'] = true;
		$GLOBALS['rytkoset_test_queried_id']  = 30;

		$data     = array( 'article' => array( 'description' => 'Julkinen' ) );
		$filtered = rytkoset_theme_rankmath_protect_jsonld( $data );

		$this->assertSame( 'Julkinen', $filtered['article']['description'] );
	}
}
