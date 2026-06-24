<?php
/**
 * Tests for inc/woocommerce-digital-magazine.php — product linking, member pricing and purchase
 * detection for digital magazines (#201).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class DigitalMagazineProductTest extends Rytkoset_Theme_Test_Case {

	private const MODE_KEY    = '_rytkoset_magazine_access_mode';
	private const REGULAR_KEY = '_rytkoset_magazine_regular_product_id';
	private const MEMBER_KEY  = '_rytkoset_magazine_member_product_id';

	/**
	 * Registers a magazine with an access mode and optional linked product IDs.
	 *
	 * @param int    $id         Magazine post ID.
	 * @param string $mode       Access mode.
	 * @param int    $regular_id Regular-price product ID (0 = none).
	 * @param int    $member_id  Member-price product ID (0 = none).
	 * @return void
	 */
	private function magazine( int $id, string $mode, int $regular_id = 0, int $member_id = 0 ): void {
		rytkoset_test_register_post( $id, 'digital_magazine', 'Sukuviesti', 0 );
		update_post_meta( $id, self::MODE_KEY, $mode );

		if ( $regular_id > 0 ) {
			update_post_meta( $id, self::REGULAR_KEY, $regular_id );
		}

		if ( $member_id > 0 ) {
			update_post_meta( $id, self::MEMBER_KEY, $member_id );
		}
	}

	private function make_member( int $user_id ): void {
		update_user_meta( $user_id, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );
	}

	// --- product ID getters -------------------------------------------------

	public function test_product_ids_filtered_and_deduped(): void {
		$this->magazine( 10, 'member_and_regular', 5, 5 ); // same product linked to both slots
		$this->assertSame( array( 5 ), rytkoset_theme_get_digital_magazine_product_ids( 10 ) );
	}

	public function test_product_ids_include_both_distinct_products(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		$this->assertSame( array( 5, 6 ), rytkoset_theme_get_digital_magazine_product_ids( 10 ) );
	}

	public function test_product_ids_empty_when_unlinked(): void {
		$this->magazine( 10, 'paid' );
		$this->assertSame( array(), rytkoset_theme_get_digital_magazine_product_ids( 10 ) );
	}

	public function test_article_resolves_product_to_parent_magazine(): void {
		$this->magazine( 10, 'paid', 5 );
		rytkoset_test_register_post( 50, 'digital_magazine', 'Juttu', 10 );

		$this->assertSame( 5, rytkoset_theme_get_digital_magazine_regular_product_id( 50 ) );
	}

	// --- published product guard -------------------------------------------

	public function test_published_product_returns_null_for_draft(): void {
		rytkoset_test_register_product( 5, 'draft', 'Luonnos' );
		$this->assertNull( rytkoset_theme_get_digital_magazine_published_product( 5 ) );
	}

	public function test_published_product_returns_product_for_publish(): void {
		$product = rytkoset_test_register_product( 5, 'publish', 'Lukuoikeus' );
		$this->assertSame( $product, rytkoset_theme_get_digital_magazine_published_product( 5 ) );
	}

	// --- purchase product selection ----------------------------------------

	public function test_free_and_members_only_have_no_purchase_product(): void {
		$this->magazine( 10, 'free', 5 );
		$this->magazine( 11, 'members_only', 5 );
		rytkoset_test_register_product( 5, 'publish', 'Lukuoikeus' );

		$this->assertNull( rytkoset_theme_get_digital_magazine_purchase_product( 10, 1 ) );
		$this->assertNull( rytkoset_theme_get_digital_magazine_purchase_product( 11, 1 ) );
	}

	public function test_paid_offers_regular_product(): void {
		$this->magazine( 10, 'paid', 5 );
		$regular = rytkoset_test_register_product( 5, 'publish', 'Lukuoikeus' );

		$this->assertSame( $regular, rytkoset_theme_get_digital_magazine_purchase_product( 10, 1 ) );
	}

	public function test_member_and_regular_offers_member_product_to_member(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		rytkoset_test_register_product( 5, 'publish', 'Normaali' );
		$member = rytkoset_test_register_product( 6, 'publish', 'Jäsenhinta' );
		$this->make_member( 1 );

		$this->assertSame( $member, rytkoset_theme_get_digital_magazine_purchase_product( 10, 1 ) );
	}

	public function test_member_and_regular_offers_regular_product_to_non_member(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		$regular = rytkoset_test_register_product( 5, 'publish', 'Normaali' );
		rytkoset_test_register_product( 6, 'publish', 'Jäsenhinta' );

		$this->assertSame( $regular, rytkoset_theme_get_digital_magazine_purchase_product( 10, 1 ) );
	}

	public function test_member_falls_back_to_regular_when_member_product_unpublished(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		$regular = rytkoset_test_register_product( 5, 'publish', 'Normaali' );
		rytkoset_test_register_product( 6, 'draft', 'Jäsenhinta' );
		$this->make_member( 1 );

		$this->assertSame( $regular, rytkoset_theme_get_digital_magazine_purchase_product( 10, 1 ) );
	}

	// --- purchase CTA -------------------------------------------------------

	public function test_cta_uses_member_label_for_member_product(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		rytkoset_test_register_product( 5, 'publish', 'Normaali' );
		rytkoset_test_register_product( 6, 'publish', 'Jäsenhinta' );
		$this->make_member( 1 );

		$cta = rytkoset_theme_get_digital_magazine_purchase_cta( 10, 1 );

		$this->assertSame( 'Osta jäsenhintaan', $cta['label'] );
		$this->assertNotEmpty( $cta['url'] );
	}

	public function test_cta_uses_regular_label_for_regular_product(): void {
		$this->magazine( 10, 'paid', 5 );
		rytkoset_test_register_product( 5, 'publish', 'Lukuoikeus' );

		$cta = rytkoset_theme_get_digital_magazine_purchase_cta( 10, 1 );

		$this->assertSame( 'Osta lukuoikeus', $cta['label'] );
	}

	public function test_cta_empty_without_product(): void {
		$this->magazine( 10, 'free' );
		$this->assertSame( array(), rytkoset_theme_get_digital_magazine_purchase_cta( 10, 1 ) );
	}

	// --- reverse product lookups -------------------------------------------

	public function test_magazine_id_by_product_matches_either_slot(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );

		$this->assertSame( 10, rytkoset_theme_get_magazine_id_by_product( 5 ) );
		$this->assertSame( 10, rytkoset_theme_get_magazine_id_by_product( 6 ) );
		$this->assertSame( 0, rytkoset_theme_get_magazine_id_by_product( 99 ) );
	}

	public function test_magazine_id_by_product_rejects_invalid_id(): void {
		$this->assertSame( 0, rytkoset_theme_get_magazine_id_by_product( 0 ) );
	}

	public function test_product_is_member_product_only_for_member_slot(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );

		$this->assertTrue( rytkoset_theme_product_is_magazine_member_product( 6 ) );
		$this->assertFalse( rytkoset_theme_product_is_magazine_member_product( 5 ) );
		$this->assertFalse( rytkoset_theme_product_is_magazine_member_product( 0 ) );
	}

	// --- add-to-cart validation --------------------------------------------

	public function test_add_to_cart_unaffected_for_non_member_product(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		$this->assertTrue( rytkoset_theme_validate_digital_magazine_member_add_to_cart( true, 5 ) );
	}

	public function test_add_to_cart_allowed_for_member(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		$GLOBALS['rytkoset_test_current_user'] = 1;
		$this->make_member( 1 );

		$this->assertTrue( rytkoset_theme_validate_digital_magazine_member_add_to_cart( true, 6 ) );
	}

	public function test_add_to_cart_blocked_for_non_member(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );

		$this->assertFalse( rytkoset_theme_validate_digital_magazine_member_add_to_cart( true, 6 ) );
	}

	// --- purchase filter (live WooCommerce lookup, #201) -------------------

	public function test_purchase_filter_returns_existing_true_unchanged(): void {
		$this->assertTrue( rytkoset_theme_filter_purchased_digital_magazine( true, 10, 1 ) );
	}

	public function test_purchase_filter_ignores_anonymous_user(): void {
		$this->assertFalse( rytkoset_theme_filter_purchased_digital_magazine( false, 10, 0 ) );
	}

	public function test_purchase_filter_grants_when_customer_bought_product(): void {
		$this->magazine( 10, 'paid', 5 );
		rytkoset_test_register_user( 1, 'ostaja@rytkoset.test', 'Ostaja' );
		$GLOBALS['rytkoset_test_bought']['1:5'] = true;

		$this->assertTrue( rytkoset_theme_filter_purchased_digital_magazine( false, 10, 1 ) );
	}

	public function test_purchase_filter_denies_when_not_bought(): void {
		$this->magazine( 10, 'paid', 5 );
		rytkoset_test_register_user( 1, 'ostaja@rytkoset.test', 'Ostaja' );

		$this->assertFalse( rytkoset_theme_filter_purchased_digital_magazine( false, 10, 1 ) );
	}
}
