<?php
/**
 * Tests for the empty-cart redesign (#581) in inc/woocommerce-empty-cart.php
 * and the shared next-upcoming-event helper in inc/events.php.
 *
 * Covers the pure/near-pure decision logic: soonest-event selection, the
 * optional secondary event link, and the render_block routing guard. The
 * WooCommerce-heavy markup (hero + product suggestions) is validated in the
 * browser instead, per the theme's testing strategy.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EmptyCartTest extends Rytkoset_Theme_Test_Case {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['rytkoset_test_now'] = '2026-07-02 12:00:00';
	}

	/**
	 * Registers a published event with a date and optional status override.
	 */
	private function register_event( int $id, string $title, string $date, string $status = 'publish', string $fee_type = '' ): WP_Post {
		$post = rytkoset_test_register_post( $id, 'rytkoset_event', $title );

		if ( '' !== $date ) {
			update_post_meta( $id, rytkoset_theme_get_event_date_meta_key(), $date );
		}

		$post->post_status = $status;

		if ( '' !== $fee_type ) {
			$meta_keys = rytkoset_theme_get_event_details_meta_keys();
			update_post_meta( $id, $meta_keys['fee_type'], $fee_type );
		}

		return $post;
	}

	/**
	 * Links a Tampere-mode registration product and deadline to an event.
	 */
	private function link_registration_product( int $event_id, int $product_id, string $deadline ): void {
		rytkoset_test_register_product(
			$product_id,
			'publish',
			'Osallistumismaksu',
			array(
				'_rytkoset_registration_mode'     => 'tampere_2026',
				'_rytkoset_registration_deadline' => $deadline,
			)
		);
		update_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), $product_id );
	}

	// --- rytkoset_theme_get_next_upcoming_event_id() -------------------------

	public function test_next_upcoming_event_returns_soonest(): void {
		$this->register_event( 10, 'Mennyt', '2026-06-01' );
		$this->register_event( 11, 'Elokuu', '2026-08-10' );
		$this->register_event( 12, 'Tänään', '2026-07-02' );
		$this->register_event( 13, 'Heinäkuu', '2026-07-15' );

		// The event day itself is still upcoming until the end of the day.
		$this->assertSame( 12, rytkoset_theme_get_next_upcoming_event_id() );
	}

	public function test_next_upcoming_event_returns_zero_when_none(): void {
		$this->assertSame( 0, rytkoset_theme_get_next_upcoming_event_id() );

		$this->register_event( 10, 'Mennyt', '2026-06-01' );
		$this->assertSame( 0, rytkoset_theme_get_next_upcoming_event_id() );
	}

	public function test_next_upcoming_event_excludes_drafts_and_undated(): void {
		$this->register_event( 10, 'Ilman päivää', '' );
		$this->register_event( 11, 'Luonnos', '2026-08-01', 'draft' );
		$this->register_event( 12, 'Julkaistu', '2026-08-02' );

		$this->assertSame( 12, rytkoset_theme_get_next_upcoming_event_id() );
	}

	// --- rytkoset_theme_get_empty_cart_event_link() --------------------------

	public function test_event_link_is_empty_without_upcoming_event(): void {
		$this->register_event( 10, 'Mennyt', '2026-06-01', 'publish', 'paid' );
		$this->register_event( 11, 'Maksuton yhteiskuljetus', '2026-08-01', 'publish', 'free' );

		$this->assertSame( '', rytkoset_theme_get_empty_cart_event_link() );
	}

	public function test_event_link_renders_anchor_for_next_paid_event(): void {
		$this->register_event( 19, 'Maksuton yhteiskuljetus', '2026-07-20', 'publish', 'free' );
		$this->register_event( 20, 'Sukujuhla 2026', '2026-08-02', 'publish', 'paid' );
		$this->link_registration_product( 20, 100, '2026-07-30' );

		$link = rytkoset_theme_get_empty_cart_event_link();

		$this->assertStringContainsString( 'class="rytkoset-empty-cart__ghost"', $link );
		$this->assertStringContainsString( 'Tutustu: Sukujuhla 2026', $link );
		$this->assertStringContainsString( 'href="https://rytkoset.test/?p=20"', $link );
	}

	public function test_event_link_skips_paid_event_after_registration_deadline(): void {
		$this->register_event( 20, 'Ilmoittautuminen päättynyt', '2026-08-02', 'publish', 'paid' );
		$this->link_registration_product( 20, 100, '2026-07-01' );
		$this->register_event( 21, 'Ilmoittautuminen avoinna', '2026-08-10', 'publish', 'paid' );
		$this->link_registration_product( 21, 101, '2026-07-30' );

		$link = rytkoset_theme_get_empty_cart_event_link();

		$this->assertStringNotContainsString( 'Ilmoittautuminen päättynyt', $link );
		$this->assertStringContainsString( 'Tutustu: Ilmoittautuminen avoinna', $link );
	}

	// --- rytkoset_theme_render_empty_cart_block() routing guard --------------

	public function test_render_block_ignores_unrelated_blocks(): void {
		$content = '<p>Alkuperäinen</p>';

		$this->assertSame(
			$content,
			rytkoset_theme_render_empty_cart_block( $content, array( 'blockName' => 'core/paragraph' ) )
		);
	}

	public function test_render_block_respects_disable_filter(): void {
		$disable = static fn(): bool => false;
		add_filter( 'rytkoset_theme_empty_cart_custom_view', $disable );

		$content = '<div class="wp-block-woocommerce-empty-cart-block">Oletus</div>';

		$rendered = rytkoset_theme_render_empty_cart_block(
			$content,
			array( 'blockName' => 'woocommerce/empty-cart-block' )
		);

		remove_filter( 'rytkoset_theme_empty_cart_custom_view', $disable );

		$this->assertSame( $content, $rendered );
	}

	public function test_render_block_keeps_woocommerce_frontend_identifier(): void {
		$rendered = rytkoset_theme_render_empty_cart_block(
			'<div class="wp-block-woocommerce-empty-cart-block">Oletus</div>',
			array( 'blockName' => 'woocommerce/empty-cart-block' )
		);

		$this->assertStringContainsString( 'data-block-name="woocommerce/empty-cart-block"', $rendered );
		$this->assertStringContainsString( 'class="wp-block-woocommerce-empty-cart-block"', $rendered );
	}

	// --- rytkoset_theme_get_empty_cart_suggested_products() -----------------

	public function test_suggestions_exclude_hidden_and_non_purchasable_products(): void {
		rytkoset_test_register_product( 30, 'publish', 'Piilotettu', array( '_catalog_visibility' => 'hidden' ) );
		rytkoset_test_register_product( 31, 'publish', 'Ei ostettavissa', array( '_is_purchasable' => false ) );
		rytkoset_test_register_product( 32, 'publish', 'Sukukirja' );

		$products = rytkoset_theme_get_empty_cart_suggested_products( 4 );

		$this->assertCount( 1, $products );
		$this->assertSame( 32, $products[0]->get_id() );
	}
}
