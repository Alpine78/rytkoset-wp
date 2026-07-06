<?php
/**
 * Tests for inc/woocommerce-my-account.php (#496).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MyAccountTest extends Rytkoset_Theme_Test_Case {

	public function test_avatar_initials_from_two_word_name(): void {
		$this->assertSame( 'IR', rytkoset_theme_get_account_avatar_initials( 'Ilkka Rytkönen' ) );
	}

	public function test_avatar_initials_from_single_word_name(): void {
		$this->assertSame( 'I', rytkoset_theme_get_account_avatar_initials( 'Ilkka' ) );
	}

	public function test_avatar_initials_use_only_first_two_words(): void {
		$this->assertSame( 'MK', rytkoset_theme_get_account_avatar_initials( 'Maija Kaarina Virtanen' ) );
	}

	public function test_avatar_initials_uppercase_unicode(): void {
		$this->assertSame( 'ÄÖ', rytkoset_theme_get_account_avatar_initials( 'äiti öberg' ) );
	}

	public function test_avatar_initials_empty_name(): void {
		$this->assertSame( '', rytkoset_theme_get_account_avatar_initials( '' ) );
		$this->assertSame( '', rytkoset_theme_get_account_avatar_initials( '   ' ) );
	}

	public function test_status_chip_variant_mapping(): void {
		$this->assertSame( 'done', rytkoset_theme_get_order_status_chip_variant( 'completed' ) );
		$this->assertSame( 'pending', rytkoset_theme_get_order_status_chip_variant( 'processing' ) );
		$this->assertSame( 'pending', rytkoset_theme_get_order_status_chip_variant( 'pending' ) );
		$this->assertSame( 'pending', rytkoset_theme_get_order_status_chip_variant( 'on-hold' ) );
		$this->assertSame( 'cancelled', rytkoset_theme_get_order_status_chip_variant( 'cancelled' ) );
		$this->assertSame( 'cancelled', rytkoset_theme_get_order_status_chip_variant( 'failed' ) );
		$this->assertSame( 'neutral', rytkoset_theme_get_order_status_chip_variant( 'refunded' ) );
	}

	public function test_status_chip_variant_defaults_to_neutral(): void {
		$this->assertSame( 'neutral', rytkoset_theme_get_order_status_chip_variant( 'checkout-draft' ) );
		$this->assertSame( 'neutral', rytkoset_theme_get_order_status_chip_variant( '' ) );
	}

	public function test_status_chip_markup_contains_variant_class_and_label(): void {
		$order         = new WC_Order();
		$order->status = 'completed';

		$chip = rytkoset_theme_get_order_status_chip( $order );

		$this->assertStringContainsString( 'rytkoset-status-chip--done', $chip );
		$this->assertStringContainsString( 'Completed', $chip );
	}

	public function test_status_chip_rejects_non_order(): void {
		$this->assertSame( '', rytkoset_theme_get_order_status_chip( 'not-an-order' ) );
		$this->assertSame( '', rytkoset_theme_get_order_status_chip( null ) );
	}

	public function test_account_menu_item_icon_mapping(): void {
		$this->assertSame( 'home', rytkoset_theme_get_account_menu_item_icon( 'dashboard' ) );
		$this->assertSame( 'package', rytkoset_theme_get_account_menu_item_icon( 'orders' ) );
		$this->assertSame( 'download', rytkoset_theme_get_account_menu_item_icon( 'downloads' ) );
		$this->assertSame( 'map-pin', rytkoset_theme_get_account_menu_item_icon( 'edit-address' ) );
		$this->assertSame( 'user', rytkoset_theme_get_account_menu_item_icon( 'edit-account' ) );
		$this->assertSame( 'credit-card', rytkoset_theme_get_account_menu_item_icon( 'payment-methods' ) );
		$this->assertSame( 'log-out', rytkoset_theme_get_account_menu_item_icon( 'customer-logout' ) );
	}

	public function test_account_menu_item_icon_unknown_endpoint_gives_no_icon(): void {
		$this->assertSame( '', rytkoset_theme_get_account_menu_item_icon( 'some-plugin-endpoint' ) );
	}

	public function test_avatar_image_url_rejects_non_user(): void {
		$this->assertSame( '', rytkoset_theme_get_account_avatar_image_url( null ) );
		$this->assertSame( '', rytkoset_theme_get_account_avatar_image_url( 'ilkka@example.test' ) );
	}

	public function test_avatar_image_url_empty_when_avatars_disabled(): void {
		$user             = new WP_User();
		$user->ID         = 1;
		$user->user_email = 'ilkka@example.test';

		// show_avatars-optiota ei ole asetettu stub-storeen → get_option()
		// palauttaa false ja helperin pitää palauttaa tyhjä (ei HTTP-kutsua).
		$this->assertSame( '', rytkoset_theme_get_account_avatar_image_url( $user ) );
	}
}
