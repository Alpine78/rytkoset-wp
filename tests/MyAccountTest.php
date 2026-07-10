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
		$this->assertSame( 'user', rytkoset_theme_get_account_menu_item_icon( 'rytkoset_membership' ) );
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

	public function test_membership_endpoint_query_var_uses_finnish_slug(): void {
		$vars = rytkoset_theme_register_account_membership_endpoint_query_var( array() );

		$this->assertSame( 'jasenyys', $vars['rytkoset_membership'] );
	}

	public function test_membership_menu_item_inserted_before_account_details(): void {
		$items = rytkoset_theme_add_account_membership_menu_item(
			array(
				'dashboard'       => 'Hallintapaneeli',
				'orders'          => 'Tilaukset',
				'edit-account'    => 'Tilin tiedot',
				'customer-logout' => 'Kirjaudu ulos',
			)
		);

		$this->assertSame(
			array( 'dashboard', 'orders', 'rytkoset_membership', 'edit-account', 'customer-logout' ),
			array_keys( $items )
		);
		$this->assertSame( 'Jäsenyys', $items['rytkoset_membership'] );
	}

	public function test_membership_status_distinguishes_active_lifetime_expired_and_missing(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';

		$active   = rytkoset_theme_get_account_membership_status(
			array(
				'type'    => 'annual',
				'period'  => '2026-2029',
				'expires' => '2029-12-31',
			)
		);
		$lifetime = rytkoset_theme_get_account_membership_status(
			array(
				'type'    => 'lifetime',
				'period'  => '',
				'expires' => '',
			)
		);
		$expired  = rytkoset_theme_get_account_membership_status(
			array(
				'type'    => 'family',
				'period'  => '2023-2026',
				'expires' => '2026-06-30',
			)
		);
		$missing  = rytkoset_theme_get_account_membership_status(
			array(
				'type'    => '',
				'period'  => '',
				'expires' => '',
			)
		);

		$this->assertTrue( $active['active'] );
		$this->assertSame( 'Voimassa', $active['label'] );
		$this->assertStringContainsString( '31.12.2029', $active['description'] );
		$this->assertTrue( $lifetime['active'] );
		$this->assertSame( 'Pysyvä jäsenyys', $lifetime['label'] );
		$this->assertFalse( $expired['active'] );
		$this->assertSame( 'Vanhentunut', $expired['label'] );
		$this->assertSame( 'cancelled', $expired['variant'] );
		$this->assertFalse( $missing['active'] );
		$this->assertSame( 'Ei jäsenyyttä', $missing['label'] );
	}

	public function test_family_member_account_status_requires_active_linked_user(): void {
		$this->assertSame(
			array(
				'label'   => 'Linkitetty käyttäjätiliin',
				'variant' => 'done',
			),
			rytkoset_theme_get_account_family_member_status(
				array(
					'linked_user_id' => 20,
					'status'         => 'active',
				)
			)
		);
		$this->assertSame(
			'pending',
			rytkoset_theme_get_account_family_member_status(
				array(
					'linked_user_id' => 0,
					'status'         => 'pending_account',
				)
			)['variant']
		);
	}

	public function test_linked_family_member_view_uses_effective_membership_without_family_list(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-10 12:00:00';
		rytkoset_test_register_user( 10, 'primary@example.test', 'Perheen Päätili' );
		rytkoset_test_register_user( 20, 'member@example.test', 'Perheen Jäsen' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Perheen Jäsen',
					'email'          => 'member@example.test',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$data = rytkoset_theme_get_account_membership_view_data( 20 );

		$this->assertTrue( $data['is_inherited'] );
		$this->assertFalse( $data['is_family_primary'] );
		$this->assertSame( 'family', $data['display_membership']['type'] );
		$this->assertSame( 10, $data['primary_user']->ID );
		$this->assertSame( array(), $data['family_members'] );
	}

	public function test_family_primary_view_hides_removed_rows(): void {
		rytkoset_test_register_user( 10, 'primary@example.test', 'Perheen Päätili' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'   => 'Odottaa tiliä',
					'email'  => 'pending@example.test',
					'status' => 'pending_account',
				),
				array(
					'name'   => 'Poistettu jäsen',
					'email'  => 'removed@example.test',
					'status' => 'removed',
				),
			)
		);

		$data = rytkoset_theme_get_account_membership_view_data( 10 );

		$this->assertTrue( $data['is_family_primary'] );
		$this->assertCount( 1, $data['family_members'] );
		$this->assertSame( 'Odottaa tiliä', $data['family_members'][0]['name'] );
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

	public function test_login_redirect_excluded_endpoints_cover_lost_password_and_logout(): void {
		$this->assertSame(
			array( 'lost-password', 'customer-logout' ),
			rytkoset_theme_get_account_login_redirect_excluded_endpoints()
		);
	}

	public function test_login_redirect_needed_for_logged_out_visitor_on_account_page(): void {
		$this->assertTrue(
			rytkoset_theme_account_needs_login_redirect( false, true, '', array( 'lost-password', 'customer-logout' ) )
		);
	}

	public function test_login_redirect_needed_on_deep_endpoint_not_in_exclusions(): void {
		$this->assertTrue(
			rytkoset_theme_account_needs_login_redirect( false, true, 'orders', array( 'lost-password', 'customer-logout' ) )
		);
	}

	public function test_login_redirect_not_needed_when_logged_in(): void {
		$this->assertFalse(
			rytkoset_theme_account_needs_login_redirect( true, true, '', array( 'lost-password', 'customer-logout' ) )
		);
	}

	public function test_login_redirect_not_needed_outside_account_page(): void {
		$this->assertFalse(
			rytkoset_theme_account_needs_login_redirect( false, false, '', array( 'lost-password', 'customer-logout' ) )
		);
	}

	public function test_login_redirect_not_needed_on_excluded_endpoints(): void {
		$excluded = array( 'lost-password', 'customer-logout' );

		$this->assertFalse(
			rytkoset_theme_account_needs_login_redirect( false, true, 'lost-password', $excluded )
		);
		$this->assertFalse(
			rytkoset_theme_account_needs_login_redirect( false, true, 'customer-logout', $excluded )
		);
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

	public function test_membership_rewrite_rules_exist_when_slug_is_present(): void {
		update_option(
			'rewrite_rules',
			array(
				'^oma-tili/jasenyys/?$' => 'index.php?pagename=oma-tili&rytkoset_membership=1',
			)
		);

		$this->assertTrue( rytkoset_theme_account_membership_endpoint_rewrite_rules_exist() );
	}
}
