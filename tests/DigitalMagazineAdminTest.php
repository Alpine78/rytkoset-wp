<?php
/**
 * Tests for inc/digital-magazines-admin.php: magazine list and editor helpers (#698).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class DigitalMagazineAdminTest extends Rytkoset_Theme_Test_Case {

	protected function tearDown(): void {
		unset( $_GET[ rytkoset_theme_get_digital_magazine_parent_query_arg() ] );
		parent::tearDown();
	}

	private function magazine( int $id, string $title, int $parent = 0, int $order = 0 ): WP_Post {
		$post                                  = new WP_Post( $id, 'digital_magazine', $title, $parent, $order );
		$GLOBALS['rytkoset_test_posts'][ $id ] = $post;
		return $post;
	}

	private function render_column( string $column, int $post_id ): string {
		ob_start();
		try {
			rytkoset_theme_render_digital_magazine_admin_column( $column, $post_id );
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	public function test_columns_add_type_after_title_and_counts_before_date(): void {
		$columns = rytkoset_theme_digital_magazine_admin_columns(
			array(
				'cb'    => '',
				'title' => 'Otsikko',
				'date'  => 'Päivämäärä',
			)
		);

		$this->assertSame( array( 'cb', 'title', 'rytkoset_magazine_kind', 'rytkoset_magazine_articles', 'rytkoset_magazine_order', 'date' ), array_keys( $columns ) );
	}

	public function test_type_column_marks_magazine_and_article_with_its_magazine(): void {
		$this->magazine( 50, 'Sukuviesti 2/2026' );
		$this->magazine( 51, 'Pääkirjoitus', 50, 3 );

		$this->assertStringContainsString( 'ra-badge--info">Lehti<', $this->render_column( 'rytkoset_magazine_kind', 50 ) );

		$article = $this->render_column( 'rytkoset_magazine_kind', 51 );
		$this->assertStringContainsString( '>Juttu<', $article );
		$this->assertStringContainsString( 'Sukuviesti 2/2026', $article );

		$this->assertSame( '3', $this->render_column( 'rytkoset_magazine_order', 51 ) );
		$this->assertSame( '', $this->render_column( 'rytkoset_magazine_order', 50 ) );
	}

	public function test_new_article_is_preselected_into_a_top_level_magazine(): void {
		$this->magazine( 50, 'Sukuviesti 2/2026' );
		$this->magazine( 51, 'Pääkirjoitus', 50 );
		$GLOBALS['rytkoset_test_caps']['edit_posts'] = true;
		$GLOBALS['rytkoset_test_caps']['edit_post']  = true;
		$draft = array(
			'post_type'   => 'digital_magazine',
			'post_status' => 'auto-draft',
			'post_parent' => 0,
		);

		$_GET[ rytkoset_theme_get_digital_magazine_parent_query_arg() ] = '50';
		$this->assertSame( 50, rytkoset_theme_preselect_digital_magazine_parent( $draft )['post_parent'] );

		// An article is not a valid parent: only one level of hierarchy.
		$_GET[ rytkoset_theme_get_digital_magazine_parent_query_arg() ] = '51';
		$this->assertSame( 0, rytkoset_theme_preselect_digital_magazine_parent( $draft )['post_parent'] );

		// Only the new auto-draft is touched, never a real save.
		$_GET[ rytkoset_theme_get_digital_magazine_parent_query_arg() ] = '50';
		$this->assertSame( 0, rytkoset_theme_preselect_digital_magazine_parent( array( 'post_status' => 'draft' ) + $draft )['post_parent'] );

		unset( $GLOBALS['rytkoset_test_caps']['edit_post'] );
		$this->assertSame( 0, rytkoset_theme_preselect_digital_magazine_parent( $draft )['post_parent'] );
	}

	public function test_row_action_only_on_magazines_for_users_who_can_create(): void {
		$magazine = $this->magazine( 50, 'Sukuviesti 2/2026' );
		$article  = $this->magazine( 51, 'Pääkirjoitus', 50 );
		$actions  = array(
			'edit'  => 'Muokkaa',
			'trash' => 'Roskakoriin',
		);

		$this->assertSame( $actions, rytkoset_theme_digital_magazine_row_actions( $actions, $magazine ) );

		// Create capability alone is not enough: the magazine must be editable too,
		// otherwise the preselect would refuse it and create a top-level post.
		$GLOBALS['rytkoset_test_caps']['edit_posts'] = true;
		$this->assertSame( $actions, rytkoset_theme_digital_magazine_row_actions( $actions, $magazine ) );

		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;
		$this->assertSame( array( 'edit', 'rytkoset_add_article', 'trash' ), array_keys( rytkoset_theme_digital_magazine_row_actions( $actions, $magazine ) ) );
		$this->assertStringContainsString( 'rytkoset_parent=50', rytkoset_theme_digital_magazine_row_actions( $actions, $magazine )['rytkoset_add_article'] );
		$this->assertSame( $actions, rytkoset_theme_digital_magazine_row_actions( $actions, $article ) );

		// A trashed magazine cannot receive new articles.
		$magazine->post_status = 'trash';
		$this->assertSame( $actions, rytkoset_theme_digital_magazine_row_actions( $actions, $magazine ) );
	}

	public function test_articles_hide_private_ones_the_user_cannot_edit(): void {
		$this->magazine( 50, 'Sukuviesti 2/2026' );
		$this->magazine( 51, 'Pääkirjoitus', 50, 1 );
		$private              = $this->magazine( 52, 'Toisen yksityinen juttu', 50, 2 );
		$private->post_status = 'private';

		$this->assertSame( array( 51 ), array_column( rytkoset_theme_get_digital_magazine_admin_articles( 50 ), 'ID' ) );
		$this->assertSame( '1', $this->render_column( 'rytkoset_magazine_articles', 50 ) );

		$GLOBALS['rytkoset_test_caps']['edit_post'] = true;
		$this->assertSame( array( 51, 52 ), array_column( rytkoset_theme_get_digital_magazine_admin_articles( 50 ), 'ID' ) );
	}

	private function render_access_box( WP_Post $post ): string {
		ob_start();
		try {
			rytkoset_theme_render_digital_magazine_access_metabox( $post );
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	public function test_access_box_holds_product_links_with_both_nonces(): void {
		$html = $this->render_access_box( $this->magazine( 50, 'Sukuviesti 2/2026' ) );

		// Each part keeps its own nonce, so both existing save handlers still run.
		$this->assertStringContainsString( 'name="rytkoset_digital_magazine_access_nonce"', $html );
		$this->assertStringContainsString( 'name="rytkoset_digital_magazine_products_nonce"', $html );
		$this->assertStringContainsString( 'id="rytkoset_magazine_access_mode"', $html );
		$this->assertStringContainsString( '>Maksutuotteet</h4>', $html );
		$this->assertLessThan( strpos( $html, 'rytkoset_digital_magazine_products_nonce' ), strpos( $html, 'rytkoset_magazine_access_mode' ) );
	}

	public function test_access_box_works_without_the_woocommerce_module(): void {
		remove_action( 'rytkoset_theme_digital_magazine_access_metabox_after', 'rytkoset_theme_render_digital_magazine_product_metabox' );

		try {
			$html = $this->render_access_box( $this->magazine( 50, 'Sukuviesti 2/2026' ) );
		} finally {
			add_action( 'rytkoset_theme_digital_magazine_access_metabox_after', 'rytkoset_theme_render_digital_magazine_product_metabox' );
		}

		$this->assertStringContainsString( 'name="rytkoset_digital_magazine_access_nonce"', $html );
		$this->assertStringNotContainsString( 'rytkoset_digital_magazine_products_nonce', $html );
	}

	public function test_seo_column_is_hidden_once_and_user_choice_then_wins(): void {
		// Rank Math has already stored its own hidden list for the user.
		update_user_meta( 9, 'manageedit-digital_magazinecolumnshidden', array( 'rank_math_title' ) );

		$this->assertTrue( rytkoset_theme_hide_digital_magazine_seo_column_once( 9 ) );
		$this->assertSame( array( 'rank_math_title', 'rank_math_seo_details' ), get_user_meta( 9, 'manageedit-digital_magazinecolumnshidden', true ) );

		// The user brings the column back from Screen Options; it is not hidden again.
		update_user_meta( 9, 'manageedit-digital_magazinecolumnshidden', array( 'rank_math_title' ) );
		$this->assertFalse( rytkoset_theme_hide_digital_magazine_seo_column_once( 9 ) );
		$this->assertSame( array( 'rank_math_title' ), get_user_meta( 9, 'manageedit-digital_magazinecolumnshidden', true ) );
	}

	public function test_list_drops_pillar_view(): void {
		$this->assertSame(
			array( 'all' => 'Kaikki' ),
			rytkoset_theme_digital_magazine_list_views(
				array(
					'all'            => 'Kaikki',
					'pillar_content' => 'Pillar Content',
				)
			)
		);
	}
}
