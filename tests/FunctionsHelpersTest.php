<?php
/**
 * Tests for shared theme helpers in functions.php — attachment captions/alt fallback, dev-site
 * detection, WooCommerce error de-duplication, order-status mapping, the admin order resolver and
 * the forum colour helper.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class FunctionsHelpersTest extends Rytkoset_Theme_Test_Case {

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	// --- gallery image alt fallback chain (EPIC 2 a11y) --------------------

	public function test_explicit_alt_wins(): void {
		$this->assertSame( 'Selkeä alt', rytkoset_theme_get_gallery_image_alt( 5, '  Selkeä alt  ' ) );
	}

	public function test_alt_falls_back_to_attachment_meta(): void {
		update_post_meta( 5, '_wp_attachment_image_alt', 'Meta-alt' );
		$this->assertSame( 'Meta-alt', rytkoset_theme_get_gallery_image_alt( 5 ) );
	}

	public function test_alt_falls_back_to_caption(): void {
		update_post_meta( 5, '_wp_attachment_caption', 'Kuvateksti' );
		$this->assertSame( 'Kuvateksti', rytkoset_theme_get_gallery_image_alt( 5 ) );
	}

	public function test_alt_empty_when_nothing_available(): void {
		$this->assertSame( '', rytkoset_theme_get_gallery_image_alt( 0 ) );
		$this->assertSame( '', rytkoset_theme_get_gallery_image_alt( 5 ) );
	}

	// --- attachment display caption ----------------------------------------

	public function test_display_caption_prefers_caption_over_title(): void {
		rytkoset_test_register_post( 5, 'attachment', 'IMG_1234' );
		update_post_meta( 5, '_wp_attachment_caption', 'Sukukokous 2026' );

		$this->assertSame( 'Sukukokous 2026', rytkoset_theme_get_attachment_display_caption_text( 5 ) );
	}

	public function test_display_caption_falls_back_to_title(): void {
		rytkoset_test_register_post( 5, 'attachment', 'IMG_1234' );
		$this->assertSame( 'IMG_1234', rytkoset_theme_get_attachment_display_caption_text( 5 ) );
	}

	public function test_visible_caption_html_wraps_text(): void {
		rytkoset_test_register_post( 5, 'attachment', 'IMG_1234' );
		update_post_meta( 5, '_wp_attachment_caption', 'Kuvateksti' );

		$this->assertSame(
			'<p class="pswp-caption-content__caption">Kuvateksti</p>',
			rytkoset_theme_get_attachment_visible_caption_html( 5 )
		);
	}

	public function test_visible_caption_html_empty_without_caption_or_title(): void {
		$this->assertSame( '', rytkoset_theme_get_attachment_visible_caption_html( 0 ) );
	}

	// --- is_dev_site --------------------------------------------------------

	public function test_dev_host_is_detected(): void {
		$_SERVER['HTTP_HOST'] = 'dev.rytkoset.net';
		$this->assertTrue( rytkoset_theme_is_dev_site() );
	}

	public function test_dev_host_detected_with_port(): void {
		$_SERVER['HTTP_HOST'] = 'dev.rytkoset.net:8443';
		$this->assertTrue( rytkoset_theme_is_dev_site() );
	}

	public function test_production_host_is_not_dev(): void {
		$_SERVER['HTTP_HOST'] = 'rytkoset.net';
		$this->assertFalse( rytkoset_theme_is_dev_site() );
	}

	// --- deduplicate_woocommerce_error_notice ------------------------------

	public function test_duplicate_error_notice_is_silenced(): void {
		$GLOBALS['rytkoset_test_wc_notices']['error:Virhe'] = true;
		$this->assertSame( '', rytkoset_theme_deduplicate_woocommerce_error_notice( 'Virhe' ) );
	}

	public function test_new_error_notice_passes_through(): void {
		$this->assertSame( 'Uusi virhe', rytkoset_theme_deduplicate_woocommerce_error_notice( 'Uusi virhe' ) );
	}

	// --- get_supported_order_statuses --------------------------------------

	public function test_order_statuses_strip_wc_prefix(): void {
		$statuses = rytkoset_theme_get_supported_order_statuses();

		$this->assertContains( 'processing', $statuses );
		$this->assertContains( 'completed', $statuses );
		$this->assertNotContains( 'wc-processing', $statuses );
	}

	// --- get_order_from_admin_screen_object --------------------------------

	public function test_order_object_returned_directly(): void {
		$order = new WC_Order();
		$this->assertSame( $order, rytkoset_theme_get_order_from_admin_screen_object( $order ) );
	}

	public function test_post_object_resolved_to_order(): void {
		$order                                = new WC_Order();
		$GLOBALS['rytkoset_test_orders'][ 7 ] = $order;
		$post                                 = rytkoset_test_register_post( 7, 'shop_order', 'Tilaus' );

		$this->assertSame( $order, rytkoset_theme_get_order_from_admin_screen_object( $post ) );
	}

	public function test_unknown_object_returns_false(): void {
		$this->assertFalse( rytkoset_theme_get_order_from_admin_screen_object( 'roska' ) );
	}

	// --- forum_color --------------------------------------------------------

	public function test_forum_color_maps_known_slugs(): void {
		rytkoset_test_register_post( 10, 'forum', 'Sukuseura' );
		$GLOBALS['rytkoset_test_posts'][10]->post_name = 'sukuseura';
		$this->assertSame( 'seura', rytkoset_theme_forum_color( 10 ) );
	}

	public function test_forum_color_defaults_to_rytkoset(): void {
		rytkoset_test_register_post( 11, 'forum', 'Muu' );
		$GLOBALS['rytkoset_test_posts'][11]->post_name = 'jokin-muu';
		$this->assertSame( 'rytkoset', rytkoset_theme_forum_color( 11 ) );
	}
}
