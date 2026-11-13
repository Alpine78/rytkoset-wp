<?php
/**
 * Tests for the event to WooCommerce product link permission boundary (#745).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventProductLinkTest extends Rytkoset_Theme_Test_Case {

	private const EVENT_ID        = 500;
	private const PLAIN_ID        = 601; // Unrelated shop product, e.g. a membership fee.
	private const REGISTRATION_ID = 602; // Opt-in registration product.
	private const LEGACY_MODE_ID  = 603;
	private const LEGACY_SKU_ID   = 604;
	private const VARIATION_ID    = 605;
	private const DRAFT_ID        = 606;
	private const OTHER_PLAIN_ID  = 607;
	private const META_KEY        = '_rytkoset_event_product_id';

	protected function setUp(): void {
		parent::setUp();

		$_POST = array();

		rytkoset_test_register_post( self::EVENT_ID, 'rytkoset_event', 'Kesäjuhla' );
		rytkoset_test_register_product( self::PLAIN_ID, 'publish', 'Jäsenmaksu' );
		rytkoset_test_register_product( self::OTHER_PLAIN_ID, 'publish', 'Sukulehti' );
		rytkoset_test_register_product( self::REGISTRATION_ID, 'publish', 'Kesäjuhlan osallistumismaksu', array( '_rytkoset_registration_mode' => 'event_participants' ) );
		rytkoset_test_register_product( self::LEGACY_MODE_ID, 'publish', 'Vanha tila', array( '_rytkoset_registration_mode' => 'tampere_2026' ) );
		rytkoset_test_register_product( self::LEGACY_SKU_ID, 'publish', 'Tampere 2026', array( '_sku' => 'tampere-2026-osallistumismaksu' ) );
		rytkoset_test_register_product( self::VARIATION_ID, 'publish', 'Aikuinen', array( '_parent_id' => self::REGISTRATION_ID ) );
		rytkoset_test_register_product( self::DRAFT_ID, 'draft', 'Luonnos', array( '_rytkoset_registration_mode' => 'event_participants' ) );
	}

	protected function tearDown(): void {
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Event organizer: may edit events, holds no product capabilities.
	 */
	private function act_as_organizer(): void {
		$GLOBALS['rytkoset_test_caps']        = array( 'edit_post' => true );
		$GLOBALS['rytkoset_test_object_caps'] = array();

		foreach ( array( self::PLAIN_ID, self::OTHER_PLAIN_ID, self::REGISTRATION_ID, self::LEGACY_MODE_ID, self::LEGACY_SKU_ID, self::VARIATION_ID, self::DRAFT_ID ) as $product_id ) {
			$GLOBALS['rytkoset_test_object_caps'][ 'edit_post:' . $product_id ] = false;
		}
	}

	/**
	 * Administrator: may edit events and every product.
	 */
	private function act_as_admin(): void {
		$GLOBALS['rytkoset_test_caps']        = array(
			'edit_post'     => true,
			'edit_products' => true,
		);
		$GLOBALS['rytkoset_test_object_caps'] = array();
	}

	/**
	 * Submits the product metabox for the test event.
	 *
	 * @param int|string $product_id Submitted select value.
	 */
	private function submit( $product_id ): void {
		$_POST = array(
			'rytkoset_event_product_nonce' => 'rytkoset_save_event_product_link',
			'rytkoset_event_product_id'    => (string) $product_id,
		);

		rytkoset_theme_save_event_product_link( self::EVENT_ID );
	}

	private function stored_link(): int {
		return absint( get_post_meta( self::EVENT_ID, self::META_KEY, true ) );
	}

	private function render_metabox(): string {
		ob_start();
		rytkoset_theme_render_event_product_metabox( get_post( self::EVENT_ID ) );

		return (string) ob_get_clean();
	}

	// --- Organizer cannot widen access ---------------------------------------

	public function test_organizer_cannot_link_unrelated_product(): void {
		$this->act_as_organizer();
		$this->submit( self::PLAIN_ID );

		$this->assertSame( 0, $this->stored_link() );
	}

	public function test_organizer_switch_to_unrelated_product_keeps_existing_registration_link(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::REGISTRATION_ID );

		$this->act_as_organizer();
		$this->submit( self::PLAIN_ID );

		$this->assertSame( self::REGISTRATION_ID, $this->stored_link() );
	}

	public function test_organizer_can_link_registration_products(): void {
		$this->act_as_organizer();

		foreach ( array( self::REGISTRATION_ID, self::LEGACY_MODE_ID, self::LEGACY_SKU_ID, self::VARIATION_ID ) as $product_id ) {
			$this->submit( $product_id );

			$this->assertSame( $product_id, $this->stored_link() );
		}
	}

	public function test_organizer_can_remove_registration_link(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::REGISTRATION_ID );

		$this->act_as_organizer();
		$this->submit( '' );

		$this->assertSame( 0, $this->stored_link() );
	}

	public function test_organizer_resaving_same_registration_link_keeps_it(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::LEGACY_SKU_ID );

		$this->act_as_organizer();
		$this->submit( self::LEGACY_SKU_ID );

		$this->assertSame( self::LEGACY_SKU_ID, $this->stored_link() );
	}

	// --- Existing administrator link is locked for the organizer --------------

	public function test_organizer_cannot_remove_admin_set_unrelated_link(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::PLAIN_ID );

		$this->act_as_organizer();
		$this->submit( '' );

		$this->assertSame( self::PLAIN_ID, $this->stored_link() );
	}

	public function test_organizer_cannot_replace_admin_set_unrelated_link(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::PLAIN_ID );

		$this->act_as_organizer();
		$this->submit( self::REGISTRATION_ID );
		$this->assertSame( self::PLAIN_ID, $this->stored_link() );

		$this->submit( self::OTHER_PLAIN_ID );
		$this->assertSame( self::PLAIN_ID, $this->stored_link() );
	}

	public function test_locked_link_survives_missing_field(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::PLAIN_ID );

		$this->act_as_organizer();
		$_POST = array( 'rytkoset_event_product_nonce' => 'rytkoset_save_event_product_link' );
		rytkoset_theme_save_event_product_link( self::EVENT_ID );

		$this->assertSame( self::PLAIN_ID, $this->stored_link() );
	}

	public function test_dangling_link_is_not_locked(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, 99999 );

		$this->act_as_organizer();
		$this->assertFalse( rytkoset_theme_event_product_link_is_locked( self::EVENT_ID ) );

		$this->submit( self::REGISTRATION_ID );
		$this->assertSame( self::REGISTRATION_ID, $this->stored_link() );
	}

	public function test_link_becomes_locked_when_product_loses_registration_mode(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::REGISTRATION_ID );
		wc_get_product( self::REGISTRATION_ID )->delete_meta_data( '_rytkoset_registration_mode' );

		$this->act_as_organizer();
		$this->assertTrue( rytkoset_theme_event_product_link_is_locked( self::EVENT_ID ) );

		$this->submit( '' );
		$this->assertSame( self::REGISTRATION_ID, $this->stored_link() );
	}

	public function test_product_edit_permission_is_checked_per_product(): void {
		$this->act_as_organizer();
		$GLOBALS['rytkoset_test_object_caps'][ 'edit_post:' . self::PLAIN_ID ] = true;

		$this->submit( self::OTHER_PLAIN_ID );
		$this->assertSame( 0, $this->stored_link() );

		$this->submit( self::PLAIN_ID );
		$this->assertSame( self::PLAIN_ID, $this->stored_link() );
	}

	// --- Administrator workflow is unchanged ---------------------------------

	public function test_admin_can_link_change_and_remove_any_product(): void {
		$this->act_as_admin();

		$this->submit( self::PLAIN_ID );
		$this->assertSame( self::PLAIN_ID, $this->stored_link() );

		$this->submit( self::REGISTRATION_ID );
		$this->assertSame( self::REGISTRATION_ID, $this->stored_link() );

		$this->submit( self::OTHER_PLAIN_ID );
		$this->assertSame( self::OTHER_PLAIN_ID, $this->stored_link() );

		$this->submit( '' );
		$this->assertSame( 0, $this->stored_link() );
	}

	public function test_unpublished_product_still_clears_the_link(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::REGISTRATION_ID );

		$this->act_as_admin();
		$this->submit( self::DRAFT_ID );

		$this->assertSame( 0, $this->stored_link() );
	}

	// --- Existing guards ------------------------------------------------------

	public function test_save_requires_valid_nonce(): void {
		$this->act_as_admin();

		$_POST = array( 'rytkoset_event_product_id' => (string) self::REGISTRATION_ID );
		rytkoset_theme_save_event_product_link( self::EVENT_ID );
		$this->assertSame( 0, $this->stored_link() );

		$_POST['rytkoset_event_product_nonce'] = 'wrong';
		rytkoset_theme_save_event_product_link( self::EVENT_ID );
		$this->assertSame( 0, $this->stored_link() );
	}

	public function test_save_requires_event_edit_permission(): void {
		$this->act_as_admin();
		$GLOBALS['rytkoset_test_object_caps'][ 'edit_post:' . self::EVENT_ID ] = false;

		$this->submit( self::REGISTRATION_ID );

		$this->assertSame( 0, $this->stored_link() );
	}

	// --- Helper and selector --------------------------------------------------

	public function test_link_permission_helper(): void {
		$this->act_as_organizer();

		$this->assertFalse( rytkoset_theme_user_can_link_event_product( null ) );
		$this->assertFalse( rytkoset_theme_user_can_link_event_product( wc_get_product( self::PLAIN_ID ) ) );
		$this->assertTrue( rytkoset_theme_user_can_link_event_product( wc_get_product( self::REGISTRATION_ID ) ) );

		$this->act_as_admin();

		$this->assertTrue( rytkoset_theme_user_can_link_event_product( wc_get_product( self::PLAIN_ID ) ) );
	}

	public function test_selector_lists_only_linkable_published_products(): void {
		$ids = static fn( array $products ): array => array_map( static fn( WC_Product $product ): int => $product->get_id(), $products );

		$this->act_as_organizer();
		$organizer_ids = $ids( rytkoset_theme_get_event_product_options() );
		sort( $organizer_ids );

		$this->assertSame(
			array( self::REGISTRATION_ID, self::LEGACY_MODE_ID, self::LEGACY_SKU_ID, self::VARIATION_ID ),
			$organizer_ids
		);

		$this->act_as_admin();
		$admin_ids = $ids( rytkoset_theme_get_event_product_options() );

		$this->assertContains( self::PLAIN_ID, $admin_ids );
		$this->assertContains( self::OTHER_PLAIN_ID, $admin_ids );
		$this->assertNotContains( self::DRAFT_ID, $admin_ids );
	}

	public function test_locked_metabox_renders_no_form_control(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::PLAIN_ID );

		$this->act_as_organizer();
		$html = $this->render_metabox();

		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringContainsString( 'Jäsenmaksu', $html );
		$this->assertStringContainsString( 'ylläpitäjä', $html );
	}

	public function test_organizer_metabox_offers_only_registration_products(): void {
		$this->act_as_organizer();
		$html = $this->render_metabox();

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'value="' . self::REGISTRATION_ID . '"', $html );
		$this->assertStringNotContainsString( 'value="' . self::PLAIN_ID . '"', $html );
	}

	public function test_admin_metabox_offers_unrelated_products_for_existing_link(): void {
		update_post_meta( self::EVENT_ID, self::META_KEY, self::PLAIN_ID );

		$this->act_as_admin();
		$html = $this->render_metabox();

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'value="' . self::PLAIN_ID . '"', $html );
	}
}
