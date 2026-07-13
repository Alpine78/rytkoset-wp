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

		$this->assertSame( 'jasentiedot', $vars['rytkoset_membership'] );
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

	public function test_family_primary_view_preserves_original_row_index_after_filtering_removed(): void {
		rytkoset_test_register_user( 10, 'primary@example.test', 'Perheen Päätili' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'   => 'Poistettu jäsen',
					'email'  => 'removed@example.test',
					'status' => 'removed',
				),
				array(
					'name'   => 'Toinen jäsen',
					'email'  => 'toinen@example.test',
					'status' => 'pending_account',
				),
			)
		);

		$data = rytkoset_theme_get_account_membership_view_data( 10 );

		// The first stored row is removed and hidden; the surviving row must keep its original
		// index (1), not be renumbered to 0, so an edit/remove form submitted for row 1 still
		// targets the correct entry in the full stored list.
		$this->assertSame( array( 1 ), array_keys( $data['family_members'] ) );
		$this->assertSame( 'Toinen jäsen', $data['family_members'][1]['name'] );
	}

	public function test_account_membership_url_uses_finnish_slug(): void {
		$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', rytkoset_theme_get_account_membership_url() );
	}

	public function test_family_member_nonce_action_is_scoped_per_action(): void {
		$this->assertSame( 'rytkoset_account_family_add', rytkoset_theme_get_account_family_member_nonce_action( 'add' ) );
		$this->assertSame( 'rytkoset_account_family_remove', rytkoset_theme_get_account_family_member_nonce_action( 'remove' ) );
	}

	public function test_account_family_member_max_rows_is_one_less_than_the_shared_checkout_limit(): void {
		// Default checkout limit is 6 (row 1 = the buyer); the account-side family list excludes
		// the primary account itself, so it gets one fewer slot.
		$this->assertSame( 5, rytkoset_theme_get_account_family_member_max_rows() );

		$filter = static function () {
			return 3;
		};

		add_filter( 'rytkoset_theme_membership_max_member_rows', $filter );
		$this->assertSame( 2, rytkoset_theme_get_account_family_member_max_rows() );
		remove_filter( 'rytkoset_theme_membership_max_member_rows', $filter );
	}

	// --- rytkoset_theme_apply_account_family_member_action() (pure) --------

	public function test_apply_family_member_action_adds_a_new_row(): void {
		$result = rytkoset_theme_apply_account_family_member_action( array(), 'add', 0, ' Uusi Jäsen ', 'uusi@example.test', 5 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'Uusi Jäsen', $result[0]['name'] );
		$this->assertSame( 'uusi@example.test', $result[0]['email'] );
	}

	public function test_apply_family_member_action_add_requires_a_name(): void {
		$result = rytkoset_theme_apply_account_family_member_action( array(), 'add', 0, '   ', '', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_name_required', $result->get_error_codes()[0] );
	}

	public function test_apply_family_member_action_add_rejects_invalid_email(): void {
		$result = rytkoset_theme_apply_account_family_member_action( array(), 'add', 0, 'Uusi Jäsen', 'ei-sahkoposti', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_email_invalid', $result->get_error_codes()[0] );
	}

	public function test_apply_family_member_action_add_rejects_when_at_the_row_limit(): void {
		$members = array(
			array( 'name' => 'Jäsen 1', 'email' => '' ),
			array( 'name' => 'Jäsen 2', 'email' => '' ),
			// A removed row does not count toward the limit.
			array( 'name' => 'Poistettu', 'email' => '', 'status' => 'removed' ),
		);

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'add', 0, 'Uusi', '', 2 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_limit_reached', $result->get_error_codes()[0] );
		$this->assertStringContainsString( '2', $result->get_error_message() );
	}

	public function test_apply_family_member_action_add_allowed_below_the_row_limit(): void {
		$members = array(
			array( 'name' => 'Jäsen 1', 'email' => '' ),
			array( 'name' => 'Poistettu', 'email' => '', 'status' => 'removed' ),
		);

		// One active row + one removed row: the removed row leaves room under a limit of 2.
		$result = rytkoset_theme_apply_account_family_member_action( $members, 'add', 0, 'Uusi', '', 2 );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	public function test_apply_family_member_action_edits_existing_row_by_index(): void {
		$members = array(
			array( 'name' => 'Vanha Nimi', 'email' => 'vanha@example.test' ),
			array( 'name' => 'Toinen', 'email' => 'toinen@example.test' ),
		);

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'edit', 0, 'Uusi Nimi', 'uusi@example.test', 5 );

		$this->assertIsArray( $result );
		$this->assertSame( 'Uusi Nimi', $result[0]['name'] );
		$this->assertSame( 'uusi@example.test', $result[0]['email'] );
		// The other row is untouched.
		$this->assertSame( 'Toinen', $result[1]['name'] );
	}

	public function test_apply_family_member_action_email_change_unlinks_existing_account(): void {
		$members = array(
			array(
				'name'           => 'Linkitetty jäsen',
				'email'          => 'vanha@example.test',
				'linked_user_id' => 20,
				'status'         => 'active',
			),
		);

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'edit', 0, 'Uusi henkilö', 'uusi@example.test', 5 );

		$this->assertIsArray( $result );
		$this->assertSame( 'uusi@example.test', $result[0]['email'] );
		$this->assertSame( 0, $result[0]['linked_user_id'] );
		$this->assertSame( 'pending_account', $result[0]['status'] );
	}

	public function test_apply_family_member_action_name_change_preserves_existing_link(): void {
		$members = array(
			array(
				'name'           => 'Vanha nimi',
				'email'          => 'jasen@example.test',
				'linked_user_id' => 20,
				'status'         => 'active',
			),
		);

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'edit', 0, 'Korjattu nimi', 'JASEN@example.test', 5 );

		$this->assertIsArray( $result );
		$this->assertSame( 'jasen@example.test', $result[0]['email'] );
		$this->assertSame( 20, $result[0]['linked_user_id'] );
		$this->assertSame( 'active', $result[0]['status'] );
	}

	public function test_apply_family_member_action_edit_rejects_invalid_email(): void {
		$members = array( array( 'name' => 'Vanha nimi', 'email' => 'vanha@example.test' ) );

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'edit', 0, 'Uusi nimi', 'rikki@', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_email_invalid', $result->get_error_codes()[0] );
	}

	public function test_apply_family_member_action_edit_requires_a_name(): void {
		$members = array( array( 'name' => 'Vanha Nimi', 'email' => '' ) );

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'edit', 0, '', '', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_name_required', $result->get_error_codes()[0] );
	}

	public function test_apply_family_member_action_edit_unknown_index_fails(): void {
		$result = rytkoset_theme_apply_account_family_member_action( array(), 'edit', 0, 'Nimi', '', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_not_found', $result->get_error_codes()[0] );
	}

	public function test_apply_family_member_action_remove_marks_row_removed_without_deleting_it(): void {
		$members = array(
			array(
				'name'           => 'Linkitetty Jäsen',
				'email'          => 'linkki@example.test',
				'linked_user_id' => 20,
				'status'         => 'active',
			),
		);

		$result = rytkoset_theme_apply_account_family_member_action( $members, 'remove', 0, '', '', 5 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'removed', $result[0]['status'] );
		// Name/email/linked_user_id survive the soft delete for the admin record.
		$this->assertSame( 'Linkitetty Jäsen', $result[0]['name'] );
		$this->assertSame( 20, $result[0]['linked_user_id'] );
	}

	public function test_apply_family_member_action_rejects_unknown_action(): void {
		$result = rytkoset_theme_apply_account_family_member_action( array(), 'delete_everything', 0, '', '', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_family_member_invalid_action', $result->get_error_codes()[0] );
	}

	// --- Submit handler: rytkoset_theme_handle_account_membership_family_submit() ---

	private function set_up_family_primary( int $primary_id ): void {
		rytkoset_test_register_user( $primary_id, 'primary@example.test', 'Perheen Päätili' );
		update_user_meta( $primary_id, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( $primary_id, rytkoset_theme_get_user_membership_period_meta_key(), '2026-2029' );
		update_user_meta( $primary_id, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		$GLOBALS['rytkoset_test_current_user'] = $primary_id;
	}

	public function test_family_submit_handler_adds_a_member_and_redirects(): void {
		$this->set_up_family_primary( 10 );

		$_POST = array(
			'rytkoset_account_family_action' => 'add',
			'_wpnonce'                       => 'rytkoset_account_family_add',
			'rytkoset_family_member_name'    => 'Uusi Jäsen',
			'rytkoset_family_member_email'   => 'uusi@example.test',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after success.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$members = rytkoset_theme_get_family_members( 10 );
		$this->assertCount( 1, $members );
		$this->assertSame( 'Uusi Jäsen', $members[0]['name'] );
		$this->assertTrue( wc_has_notice( 'Perheenjäsen lisättiin.', 'success' ) );
	}

	public function test_family_submit_handler_links_an_existing_account_by_email(): void {
		$this->set_up_family_primary( 10 );
		rytkoset_test_register_user( 20, 'uusi@example.test', 'Uusi Jäsen' );

		$_POST = array(
			'rytkoset_account_family_action' => 'add',
			'_wpnonce'                       => 'rytkoset_account_family_add',
			'rytkoset_family_member_name'    => 'Uusi Jäsen',
			'rytkoset_family_member_email'   => 'UUSI@example.test',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after success.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$member = rytkoset_theme_get_family_members( 10 )[0];

		$this->assertSame( 20, $member['linked_user_id'] );
		$this->assertSame( 'active', $member['status'] );
		$this->assertSame( 10, rytkoset_theme_get_family_primary_user_id( 20 ) );
	}

	public function test_family_submit_handler_re_adds_a_previously_removed_member(): void {
		$this->set_up_family_primary( 10 );
		update_user_meta(
			10,
			rytkoset_theme_get_family_members_meta_key(),
			array(
				array(
					'name'            => 'Liisa Vanha',
					'email'           => 'liisa@example.test',
					'linked_user_id'  => 0,
					'status'          => 'removed',
					'source_order_id' => 0,
					'updated_at'      => '2026-01-01 00:00:00',
				),
			)
		);

		$_POST = array(
			'rytkoset_account_family_action' => 'add',
			'_wpnonce'                       => 'rytkoset_account_family_add',
			'rytkoset_family_member_name'    => 'Liisa Uusi',
			'rytkoset_family_member_email'   => 'liisa@example.test',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after success.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$members = rytkoset_theme_get_family_members( 10 );

		$this->assertCount( 1, $members );
		$this->assertSame( 'Liisa Uusi', $members[0]['name'] );
		$this->assertSame( 'pending_account', $members[0]['status'] );
		$this->assertTrue( wc_has_notice( 'Perheenjäsen lisättiin.', 'success' ) );
	}

	public function test_family_submit_handler_rejects_add_at_the_shared_checkout_row_limit(): void {
		$this->set_up_family_primary( 10 );

		// Default shared checkout limit is 6 total rows (row 1 = the buyer), so the account-side
		// family list allows 5 additional members before the limit blocks a 6th.
		rytkoset_theme_update_family_members(
			10,
			array(
				array( 'name' => 'Jäsen 1', 'email' => '' ),
				array( 'name' => 'Jäsen 2', 'email' => '' ),
				array( 'name' => 'Jäsen 3', 'email' => '' ),
				array( 'name' => 'Jäsen 4', 'email' => '' ),
				array( 'name' => 'Jäsen 5', 'email' => '' ),
			)
		);

		$_POST = array(
			'rytkoset_account_family_action' => 'add',
			'_wpnonce'                       => 'rytkoset_account_family_add',
			'rytkoset_family_member_name'    => 'Ylimääräinen',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after the limit rejects the request.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$members = rytkoset_theme_get_family_members( 10 );
		$this->assertCount( 5, $members );
		$this->assertTrue( wc_has_notice( 'Perheenjäseniä voi olla enintään 5.', 'error' ) );
	}

	public function test_family_submit_handler_edits_a_member(): void {
		$this->set_up_family_primary( 10 );
		rytkoset_theme_update_family_members(
			10,
			array( array( 'name' => 'Vanha Nimi', 'email' => 'vanha@example.test' ) )
		);

		$_POST = array(
			'rytkoset_account_family_action' => 'edit',
			'_wpnonce'                       => 'rytkoset_account_family_edit',
			'rytkoset_family_member_index'   => '0',
			'rytkoset_family_member_name'    => 'Uusi Nimi',
			'rytkoset_family_member_email'   => 'uusi@example.test',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after success.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$members = rytkoset_theme_get_family_members( 10 );
		$this->assertSame( 'Uusi Nimi', $members[0]['name'] );
		$this->assertTrue( wc_has_notice( 'Perheenjäsenen tiedot päivitettiin.', 'success' ) );
	}

	public function test_family_submit_handler_email_change_revokes_old_inherited_membership(): void {
		$this->set_up_family_primary( 10 );
		rytkoset_test_register_user( 20, 'member@example.test', 'Perheen Jäsen' );
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

		$_POST = array(
			'rytkoset_account_family_action' => 'edit',
			'_wpnonce'                       => 'rytkoset_account_family_edit',
			'rytkoset_family_member_index'   => '0',
			'rytkoset_family_member_name'    => 'Uusi henkilö',
			'rytkoset_family_member_email'   => 'uusi@example.test',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after success.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$member = rytkoset_theme_get_family_members( 10 )[0];

		$this->assertSame( 0, $member['linked_user_id'] );
		$this->assertSame( 'pending_account', $member['status'] );
		$this->assertSame( 0, rytkoset_theme_get_family_primary_user_id( 20 ) );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 20 ) );
	}

	public function test_family_submit_handler_rejects_invalid_email_with_visible_error(): void {
		$this->set_up_family_primary( 10 );
		rytkoset_theme_update_family_members(
			10,
			array( array( 'name' => 'Vanha nimi', 'email' => 'vanha@example.test' ) )
		);

		$_POST = array(
			'rytkoset_account_family_action' => 'edit',
			'_wpnonce'                       => 'rytkoset_account_family_edit',
			'rytkoset_family_member_index'   => '0',
			'rytkoset_family_member_name'    => 'Uusi nimi',
			'rytkoset_family_member_email'   => 'rikki@',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after validation fails.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$member = rytkoset_theme_get_family_members( 10 )[0];

		$this->assertSame( 'Vanha nimi', $member['name'] );
		$this->assertSame( 'vanha@example.test', $member['email'] );
		$this->assertTrue( wc_has_notice( 'Anna kelvollinen sähköpostiosoite.', 'error' ) );
	}

	public function test_family_submit_handler_removes_a_member_and_clears_linked_reverse_meta(): void {
		$this->set_up_family_primary( 10 );
		rytkoset_test_register_user( 20, 'member@example.test', 'Perheen Jäsen' );
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

		$_POST = array(
			'rytkoset_account_family_action' => 'remove',
			'_wpnonce'                       => 'rytkoset_account_family_remove',
			'rytkoset_family_member_index'   => '0',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after success.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$this->assertSame( 0, rytkoset_theme_get_family_primary_user_id( 20 ) );
		$this->assertFalse( rytkoset_theme_user_is_active_member( 20 ) );
		$this->assertTrue( wc_has_notice( 'Perheenjäsen poistettiin.', 'success' ) );
	}

	public function test_family_submit_handler_rejects_non_primary_user(): void {
		rytkoset_test_register_user( 20, 'member@example.test', 'Perheen Jäsen' );
		$GLOBALS['rytkoset_test_current_user'] = 20;

		$_POST = array(
			'rytkoset_account_family_action' => 'add',
			'_wpnonce'                       => 'rytkoset_account_family_add',
			'rytkoset_family_member_name'    => 'Ei Sallittu',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after the guard rejects the request.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$this->assertSame( array(), rytkoset_theme_get_family_members( 20 ) );
		$this->assertTrue( wc_has_notice( 'Perheenjäseniä voi muokata vain perhejäsenyyden päätili.', 'error' ) );
	}

	public function test_family_submit_handler_rejects_invalid_nonce(): void {
		$this->set_up_family_primary( 10 );

		$_POST = array(
			'rytkoset_account_family_action' => 'add',
			'_wpnonce'                       => 'not-the-right-nonce',
			'rytkoset_family_member_name'    => 'Uusi Jäsen',
		);

		try {
			rytkoset_theme_handle_account_membership_family_submit();
			$this->fail( 'Expected the submit handler to redirect after the nonce check fails.' );
		} catch ( Rytkoset_Test_Redirect_Exception $redirect ) {
			$this->assertSame( 'https://rytkoset.test/tili/jasentiedot/', $redirect->location );
		}

		$this->assertSame( array(), rytkoset_theme_get_family_members( 10 ) );
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
				'^oma-tili/jasentiedot/?$' => 'index.php?pagename=oma-tili&rytkoset_membership=1',
			)
		);

		$this->assertTrue( rytkoset_theme_account_membership_endpoint_rewrite_rules_exist() );
	}
}
