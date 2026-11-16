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

	// --- unpublished magazines and variations (#750) -----------------------

	public function test_purchase_guards_cover_unpublished_magazines(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );
		get_post( 10 )->post_status = 'future';

		// Published-only lookups stay unchanged for access mails and listings.
		$this->assertSame( 0, rytkoset_theme_get_magazine_id_by_product( 6 ) );
		$this->assertFalse( rytkoset_theme_product_is_magazine_member_product( 6 ) );

		$this->assertSame( 10, rytkoset_theme_get_magazine_id_by_product( 6, true ) );
		$this->assertTrue( rytkoset_theme_product_is_magazine_member_product( 6, true ) );

		// A non-member cannot add the member-price product of a scheduled magazine.
		$this->assertFalse( rytkoset_theme_validate_digital_magazine_member_add_to_cart( true, 6 ) );

		// The cart of a scheduled magazine still requires an account.
		$this->cart_with_products( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );
		$this->assertTrue( rytkoset_theme_cart_has_digital_magazine_product() );

		// A trashed magazine no longer guards anything.
		get_post( 10 )->post_status = 'trash';
		$this->assertSame( 0, rytkoset_theme_get_magazine_id_by_product( 6, true ) );
	}

	public function test_purchase_guards_resolve_variations_to_the_parent_product(): void {
		$this->magazine( 10, 'member_and_regular', 5, 6 );

		$variation = new WC_Product( array( '_parent_id' => 6 ), 61, 'publish', 'Jäsenhinta – PDF' );

		$this->assertSame( array( 61, 6 ), rytkoset_theme_get_magazine_lookup_product_ids( $variation ) );
		$this->assertSame( 10, rytkoset_theme_get_magazine_id_for_purchase_product( $variation ) );
		$this->assertTrue( rytkoset_theme_purchase_product_is_magazine_member_product( $variation ) );
		$this->assertFalse( rytkoset_theme_validate_digital_magazine_member_add_to_cart( true, 6, 1, 61 ) );

		$this->cart_with_products( $variation );
		$this->assertTrue( rytkoset_theme_cart_has_digital_magazine_product() );

		rytkoset_theme_validate_digital_magazine_member_cart_items();
		$this->assertTrue( wc_has_notice( rytkoset_theme_get_digital_magazine_member_product_block_message(), 'error' ) );
	}

	// --- checkout account requirement (#558) -------------------------------

	private function cart_with_products( WC_Product ...$products ): void {
		WC()->cart = new Rytkoset_Test_Cart();

		foreach ( $products as $product ) {
			WC()->cart->items[] = array( 'data' => $product );
		}
	}

	public function test_magazine_cart_requires_checkout_registration(): void {
		$this->magazine( 10, 'paid', 5 );
		$this->cart_with_products( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );

		$this->assertTrue( rytkoset_theme_cart_has_digital_magazine_product() );
		$this->assertTrue( rytkoset_theme_enable_digital_magazine_checkout_registration( false ) );
		$this->assertTrue( rytkoset_theme_require_digital_magazine_checkout_registration( false ) );
	}

	public function test_regular_product_cart_keeps_guest_checkout_settings(): void {
		$this->magazine( 10, 'paid', 5 );
		$this->cart_with_products( new WC_Product( array(), 99, 'publish', 'Tavallinen tuote' ) );

		$this->assertFalse( rytkoset_theme_cart_has_digital_magazine_product() );
		$this->assertFalse( rytkoset_theme_enable_digital_magazine_checkout_registration( false ) );
		$this->assertFalse( rytkoset_theme_require_digital_magazine_checkout_registration( false ) );
	}

	public function test_logged_in_magazine_buyer_does_not_get_guest_guidance(): void {
		$this->magazine( 10, 'paid', 5 );
		$this->cart_with_products( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );
		$GLOBALS['rytkoset_test_current_user'] = 1;

		$this->assertFalse( rytkoset_theme_digital_magazine_checkout_requires_account() );
	}

	public function test_store_api_validation_blocks_guest_magazine_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order  = $this->order_with_items( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );
		$errors = new WP_Error();

		rytkoset_theme_validate_digital_magazine_checkout_order_account( $order, $errors );

		$this->assertSame( array( 'rytkoset_digital_magazine_account_required' ), $errors->get_error_codes() );
		$this->assertStringContainsString( 'luodaan tili automaattisesti', $errors->get_error_message() );
	}

	public function test_store_api_validation_allows_account_linked_magazine_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order          = $this->order_with_items( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );
		$order->user_id = 1;
		$errors         = new WP_Error();

		rytkoset_theme_validate_digital_magazine_checkout_order_account( $order, $errors );

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	public function test_store_api_validation_allows_regular_guest_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order  = $this->order_with_items( new WC_Product( array(), 99, 'publish', 'Tavallinen tuote' ) );
		$errors = new WP_Error();

		rytkoset_theme_validate_digital_magazine_checkout_order_account( $order, $errors );

		$this->assertSame( array(), $errors->get_error_codes() );
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

	public function test_purchase_filter_matches_billing_email_only_for_verified_accounts(): void {
		$this->magazine( 10, 'paid', 5 );
		$user = rytkoset_test_register_user( 1, 'Ostaja@Rytkoset.test', 'Ostaja' );

		// Someone else's guest order used this address as its billing email.
		$GLOBALS['rytkoset_test_bought_by_email']['ostaja@rytkoset.test:5'] = true;

		$this->assertFalse( rytkoset_theme_filter_purchased_digital_magazine( false, 10, 1 ) );

		do_action( 'after_password_reset', $user, 'uusi-salasana' );

		$this->assertTrue( rytkoset_theme_filter_purchased_digital_magazine( false, 10, 1 ) );
	}

	// --- cancellation-right consent (#477) ----------------------------------

	private function order_with_consent_field( string $value ): WC_Order {
		$order     = new WC_Order();
		$order->id = 477;

		$meta_key                = '_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id();
		$order->meta[ $meta_key ] = $value;

		return $order;
	}

	public function test_consent_field_id_uses_rytkoset_namespace(): void {
		$this->assertSame(
			'rytkoset/digital_magazine_cancellation_consent',
			rytkoset_theme_get_digital_magazine_consent_field_id()
		);
	}

	public function test_order_consent_true_when_checkbox_checked(): void {
		$this->assertTrue(
			rytkoset_theme_order_has_digital_magazine_cancellation_consent( $this->order_with_consent_field( '1' ) )
		);
	}

	public function test_order_consent_false_when_checkbox_unchecked(): void {
		$this->assertFalse(
			rytkoset_theme_order_has_digital_magazine_cancellation_consent( $this->order_with_consent_field( '0' ) )
		);
	}

	public function test_order_consent_false_when_field_missing(): void {
		$order     = new WC_Order();
		$order->id = 477;

		$this->assertFalse( rytkoset_theme_order_has_digital_magazine_cancellation_consent( $order ) );
	}

	// --- consent field leak guard (#491) ------------------------------------

	private function order_with_items( WC_Product ...$products ): WC_Order {
		$order = new WC_Order();

		foreach ( $products as $product ) {
			$order->items[] = new Rytkoset_Test_Order_Item( $product, 'Tuote', 1 );
		}

		return $order;
	}

	public function test_order_has_digital_magazine_product_true_when_linked(): void {
		$this->magazine( 10, 'paid', 5 );
		$order = $this->order_with_items( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );

		$this->assertTrue( rytkoset_theme_order_has_digital_magazine_product( $order ) );
	}

	public function test_order_has_digital_magazine_product_false_for_unrelated_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order = $this->order_with_items( new WC_Product( array(), 99, 'publish', 'Tampere 2026' ) );

		$this->assertFalse( rytkoset_theme_order_has_digital_magazine_product( $order ) );
	}

	public function test_order_has_digital_magazine_product_false_for_non_order(): void {
		$this->assertFalse( rytkoset_theme_order_has_digital_magazine_product( null ) );
	}

	public function test_consent_confirmation_field_hidden_for_unrelated_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order = $this->order_with_items( new WC_Product( array(), 99, 'publish', 'Tampere 2026' ) );
		$field = array( 'id' => rytkoset_theme_get_digital_magazine_consent_field_id() );

		$this->assertFalse(
			rytkoset_theme_filter_digital_magazine_consent_order_confirmation_field( true, $field, array(), array( 'order' => $order ) )
		);
	}

	public function test_consent_confirmation_field_shown_for_magazine_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order = $this->order_with_items( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );
		$field = array( 'id' => rytkoset_theme_get_digital_magazine_consent_field_id() );

		$this->assertTrue(
			rytkoset_theme_filter_digital_magazine_consent_order_confirmation_field( true, $field, array(), array( 'order' => $order ) )
		);
	}

	public function test_consent_confirmation_field_untouched_for_other_field(): void {
		$this->magazine( 10, 'paid', 5 );
		$order = $this->order_with_items( new WC_Product( array(), 99, 'publish', 'Tampere 2026' ) );
		$field = array( 'id' => 'rytkoset/participant_1_name' );

		$this->assertTrue(
			rytkoset_theme_filter_digital_magazine_consent_order_confirmation_field( true, $field, array(), array( 'order' => $order ) )
		);
	}

	public function test_is_digital_magazine_consent_admin_field_matches_plain_id(): void {
		$this->assertTrue(
			rytkoset_theme_is_digital_magazine_consent_admin_field(
				rytkoset_theme_get_digital_magazine_consent_field_id(),
				array()
			)
		);
	}

	public function test_is_digital_magazine_consent_admin_field_matches_prefixed_key(): void {
		$this->assertTrue(
			rytkoset_theme_is_digital_magazine_consent_admin_field(
				'_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id(),
				array()
			)
		);
	}

	public function test_is_digital_magazine_consent_admin_field_rejects_other_field(): void {
		$this->assertFalse(
			rytkoset_theme_is_digital_magazine_consent_admin_field( 'rytkoset/participant_1_name', array() )
		);
	}

	public function test_admin_order_fields_strip_consent_for_unrelated_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order  = $this->order_with_items( new WC_Product( array(), 99, 'publish', 'Tampere 2026' ) );
		$fields = array(
			'_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id() => array(),
			'first_name' => array(),
		);

		$filtered = rytkoset_theme_filter_digital_magazine_consent_admin_order_fields( $fields, $order );

		$this->assertArrayNotHasKey( '_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id(), $filtered );
		$this->assertArrayHasKey( 'first_name', $filtered );
	}

	public function test_admin_order_fields_keep_consent_for_magazine_order(): void {
		$this->magazine( 10, 'paid', 5 );
		$order  = $this->order_with_items( new WC_Product( array(), 5, 'publish', 'Lukuoikeus' ) );
		$fields = array(
			'_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id() => array(),
		);

		$filtered = rytkoset_theme_filter_digital_magazine_consent_admin_order_fields( $fields, $order );

		$this->assertArrayHasKey( '_wc_other/' . rytkoset_theme_get_digital_magazine_consent_field_id(), $filtered );
	}
}
