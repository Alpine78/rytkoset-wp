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
		$this->assertSame( 'mail', rytkoset_theme_get_account_menu_item_icon( 'rytkoset_newsletter' ) );
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

	public function test_newsletter_endpoint_query_var_uses_finnish_slug(): void {
		$vars = rytkoset_theme_register_account_newsletter_endpoint_query_var( array() );

		$this->assertSame( 'uutiskirje', $vars['rytkoset_newsletter'] );
	}

	public function test_newsletter_menu_item_inserted_before_account_details(): void {
		$items = rytkoset_theme_add_account_newsletter_menu_item(
			array(
				'dashboard'       => 'Hallintapaneeli',
				'orders'          => 'Tilaukset',
				'edit-account'    => 'Tilin tiedot',
				'customer-logout' => 'Kirjaudu ulos',
			)
		);

		$this->assertSame(
			array( 'dashboard', 'orders', 'rytkoset_newsletter', 'edit-account', 'customer-logout' ),
			array_keys( $items )
		);
		$this->assertSame( 'Uutiskirje', $items['rytkoset_newsletter'] );
	}

	public function test_newsletter_menu_item_falls_back_before_logout(): void {
		$items = rytkoset_theme_add_account_newsletter_menu_item(
			array(
				'dashboard'       => 'Hallintapaneeli',
				'customer-logout' => 'Kirjaudu ulos',
			)
		);

		$this->assertSame(
			array( 'dashboard', 'rytkoset_newsletter', 'customer-logout' ),
			array_keys( $items )
		);
	}

	public function test_newsletter_status_invalid_without_logged_in_user(): void {
		$this->assertSame( 'invalid_user', rytkoset_theme_get_account_newsletter_status( 0, array( 1 ) ) );
	}

	public function test_newsletter_status_missing_list_without_target_lists(): void {
		rytkoset_test_register_user( 7, 'ilkka@example.test', 'Ilkka Rytkönen' );

		$this->assertSame( 'missing_list', rytkoset_theme_get_account_newsletter_status( 7 ) );
	}

	public function test_newsletter_status_checks_subscription_table_result(): void {
		rytkoset_test_register_user( 7, 'ilkka@example.test', 'Ilkka Rytkönen' );

		$GLOBALS['wpdb']->get_var_result = 1;

		$this->assertSame( 'subscribed', rytkoset_theme_get_account_newsletter_status( 7, array( 3 ) ) );

		$GLOBALS['wpdb']->get_var_result = 0;

		$this->assertSame( 'not_subscribed', rytkoset_theme_get_account_newsletter_status( 7, array( 3 ) ) );
	}

	public function test_newsletter_rewrite_rules_exist_when_slug_is_present(): void {
		update_option(
			'rewrite_rules',
			array(
				'^tili/uutiskirje/?$' => 'index.php?pagename=tili&rytkoset_newsletter=1',
			)
		);

		$this->assertTrue( rytkoset_theme_account_newsletter_endpoint_rewrite_rules_exist() );
	}
}
