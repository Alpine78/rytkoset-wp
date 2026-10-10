<?php
/**
 * Tests for inc/admin-menu.php: menu groups and per-role cleanup (#699).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class AdminMenuTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Runs core's separator clean-up from wp-admin/includes/menu.php on an
	 * ordered slug list: adjacent separators keep the first one, and a last
	 * separator is dropped only when its class is exactly "wp-menu-separator".
	 * Then the theme's own trailing clean-up runs, as on add_menu_classes.
	 *
	 * @param string[] $order Ordered slugs.
	 * @return string[] Slugs left, separators shown as their group or "---".
	 */
	private function render( array $order ): array {
		$menu = array();

		foreach ( $order as $slug ) {
			$class  = 0 === strpos( $slug, 'separator-rytkoset-' ) ? 'wp-menu-separator ra-sep' : ( 0 === strpos( $slug, 'separator' ) ? 'wp-menu-separator' : 'menu-top' );
			$menu[] = array( '', 'read', $slug, '', $class );
		}

		$previous_separator = false;
		foreach ( $menu as $id => $item ) {
			if ( false === stristr( $item[4], 'wp-menu-separator' ) ) {
				$previous_separator = false;
				continue;
			}

			if ( $previous_separator ) {
				unset( $menu[ $id ] );
				continue;
			}

			$previous_separator = true;
		}

		$last = array_key_last( $menu );
		if ( null !== $last && 'wp-menu-separator' === $menu[ $last ][4] ) {
			unset( $menu[ $last ] );
		}

		$menu = rytkoset_theme_remove_trailing_admin_menu_separators( $menu );

		return array_values(
			array_map(
				static function ( $item ) {
					if ( 0 === strpos( $item[2], 'separator-rytkoset-' ) ) {
						return '--' . substr( $item[2], strlen( 'separator-rytkoset-' ) ) . '--';
					}

					return 0 === strpos( $item[2], 'separator' ) ? '---' : $item[2];
				},
				$menu
			)
		);
	}

	private function separators(): array {
		return array( 'separator-rytkoset-toiminta', 'separator-rytkoset-sisalto', 'separator-rytkoset-kauppa', 'separator-rytkoset-yllapito' );
	}

	public function test_administrator_menu_is_grouped(): void {
		$core = array_merge(
			array( 'index.php', 'separator1', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'edit-comments.php', 'edit.php?post_type=gallery_album', 'edit.php?post_type=rytkoset_event', 'edit.php?post_type=digital_magazine', 'acymailing_dashboard', 'rank-math', 'separator-woocommerce', 'woocommerce', 'edit.php?post_type=product', 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', 'wc-admin&path=/analytics/overview', 'wc-admin&path=/marketing', 'separator2', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'ai1wm_export', 'options-general.php', 'edit.php?post_type=acf-field-group', 'some-new-plugin', 'separator-last' ),
			$this->separators()
		);

		$this->assertSame(
			array(
				'index.php',
				'--toiminta--',
				'edit.php?post_type=rytkoset_event',
				'acymailing_dashboard',
				'--sisalto--',
				'edit.php?post_type=page',
				'edit.php',
				'edit.php?post_type=gallery_album',
				'edit.php?post_type=digital_magazine',
				'upload.php',
				'edit-comments.php',
				'--kauppa--',
				'woocommerce',
				'edit.php?post_type=product',
				'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM',
				'wc-admin&path=/analytics/overview',
				'wc-admin&path=/marketing',
				'--yllapito--',
				'users.php',
				'themes.php',
				'plugins.php',
				'tools.php',
				'options-general.php',
				'rank-math',
				'edit.php?post_type=acf-field-group',
				'ai1wm_export',
				'some-new-plugin',
			),
			$this->render( rytkoset_theme_order_admin_menu( $core ) )
		);
	}

	public function test_empty_groups_lose_their_label(): void {
		// Event organizer: no content beyond media, no shop.
		$organizer = array_merge( array( 'index.php', 'separator1', 'upload.php', 'edit.php?post_type=rytkoset_event', 'separator2', 'profile.php', 'separator-last' ), $this->separators() );

		$this->assertSame(
			array( 'index.php', '--toiminta--', 'edit.php?post_type=rytkoset_event', '--sisalto--', 'upload.php', '--yllapito--', 'profile.php' ),
			$this->render( rytkoset_theme_order_admin_menu( $organizer ) )
		);

		// First group empty: the label of the first visible group is the one kept.
		$editor = array_merge( array( 'index.php', 'edit.php', 'profile.php' ), $this->separators() );
		$this->assertSame(
			array( 'index.php', '--sisalto--', 'edit.php', '--yllapito--', 'profile.php' ),
			$this->render( rytkoset_theme_order_admin_menu( $editor ) )
		);

		// Nothing but the dashboard: no label is left behind.
		$this->assertSame( array( 'index.php' ), $this->render( rytkoset_theme_order_admin_menu( array_merge( array( 'index.php', 'separator1' ), $this->separators() ) ) ) );
	}

	public function test_reparented_items_keep_their_group(): void {
		// The editor's Tuotteet points at product reviews; back-compat entries must not move edit.php.
		$real_parents = array(
			'edit.php?post_type=product' => 'product-reviews',
			'post.php'                   => 'edit.php',
			'users.php'                  => 'profile.php',
		);
		$editor       = array_merge( array( 'index.php', 'edit.php', 'product-reviews', 'profile.php' ), $this->separators() );

		$this->assertSame(
			array( 'index.php', '--sisalto--', 'edit.php', '--kauppa--', 'product-reviews', '--yllapito--', 'profile.php' ),
			$this->render( rytkoset_theme_order_admin_menu( $editor, $real_parents ) )
		);
	}

	public function test_woocommerce_prefixes_go_to_the_shop_group(): void {
		$this->assertSame( 'kauppa', rytkoset_theme_get_admin_menu_group( 'wc-reports' ) );
		$this->assertSame( 'kauppa', rytkoset_theme_get_admin_menu_group( 'edit.php?post_type=shop_coupon' ) );
		$this->assertSame( 'yllapito', rytkoset_theme_get_admin_menu_group( 'unknown-plugin' ) );
	}

	public function test_newsletter_is_renamed(): void {
		$GLOBALS['menu'] = array(
			5  => array( 'AcyMailing', 'read', 'acymailing_dashboard', 'AcyMailing', 'menu-top' ),
			10 => array( 'Sivut', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top' ),
		);

		try {
			rytkoset_theme_rename_newsletter_admin_menu();
			$this->assertSame( 'Uutiskirje', $GLOBALS['menu'][5][0] );
			$this->assertSame( 'Sivut', $GLOBALS['menu'][10][0] );
		} finally {
			unset( $GLOBALS['menu'] );
		}
	}

	public function test_admin_bar_is_trimmed_for_non_administrators_only(): void {
		$bar = new class() {
			/** @var string[] */
			public $removed = array();

			public function remove_node( $id ) {
				$this->removed[] = $id;
			}
		};

		rytkoset_theme_trim_admin_bar( $bar );
		$this->assertSame( array( 'rank-math', 'comments' ), $bar->removed );

		$bar->removed = array();
		$GLOBALS['rytkoset_test_caps']['manage_options'] = true;
		rytkoset_theme_trim_admin_bar( $bar );
		$this->assertSame( array(), $bar->removed );
	}

	public function test_role_names_are_finnish_only_in_the_user_role_context(): void {
		$this->assertSame( 'Avainmestari', rytkoset_theme_translate_role_name( 'Keymaster', 'Keymaster', 'User role' ) );
		$this->assertSame( 'Tapahtumien järjestäjä', rytkoset_theme_translate_role_name( 'Event Organizer', 'Event Organizer', 'User role' ) );
		$this->assertSame( 'Moderator', rytkoset_theme_translate_role_name( 'Moderator', 'Moderator', 'noun' ) );
		// An existing translation wins.
		$this->assertSame( 'Valvoja (oma)', rytkoset_theme_translate_role_name( 'Valvoja (oma)', 'Moderator', 'User role' ) );
	}

	public function test_color_scheme_is_pinned_and_saved_choice_kept(): void {
		$this->assertSame( 'modern', rytkoset_theme_pin_admin_color_scheme( 'ectoplasm' ) );

		update_user_meta( 7, 'admin_color', 'ectoplasm' );
		$user              = new stdClass();
		$user->ID          = 7;
		$user->admin_color = 'modern';
		rytkoset_theme_keep_saved_admin_color( new WP_Error(), true, $user );
		$this->assertSame( 'ectoplasm', $user->admin_color );

		$off = static function () {
			return false;
		};
		add_filter( 'rytkoset_theme_enable_admin_appearance', $off );
		try {
			$this->assertSame( 'ectoplasm', rytkoset_theme_pin_admin_color_scheme( 'ectoplasm' ) );
		} finally {
			remove_filter( 'rytkoset_theme_enable_admin_appearance', $off );
		}
	}

	private function save_organizer_toggle( int $user_id, bool $checked ): void {
		$_POST['rytkoset_event_organizer_role_nonce'] = wp_create_nonce( rytkoset_theme_get_event_organizer_role_nonce_action( $user_id ) );

		if ( $checked ) {
			$_POST['rytkoset_event_organizer_role'] = '1';
		}

		try {
			rytkoset_theme_save_user_event_organizer_field( $user_id );
		} finally {
			unset( $_POST['rytkoset_event_organizer_role'], $_POST['rytkoset_event_organizer_role_nonce'] );
		}
	}

	public function test_event_organizer_toggle_needs_both_promote_capabilities(): void {
		$user        = rytkoset_test_register_user( 5, 'asiakas@example.test' );
		$user->roles = array( 'customer' );

		// A shop manager can edit customers but must not grant event access.
		$GLOBALS['rytkoset_test_caps'] = array(
			'edit_users' => true,
			'edit_user'  => true,
		);

		ob_start();
		rytkoset_theme_render_user_event_organizer_field( $user );
		$this->assertSame( '', (string) ob_get_clean() );

		$this->save_organizer_toggle( 5, true );
		$this->assertSame( array( 'customer' ), $user->roles );

		// Either promote capability alone is not enough.
		$GLOBALS['rytkoset_test_caps'] = array( 'promote_users' => true );
		$this->save_organizer_toggle( 5, true );
		$GLOBALS['rytkoset_test_caps'] = array( 'promote_user' => true );
		$this->save_organizer_toggle( 5, true );
		$this->assertSame( array( 'customer' ), $user->roles );
	}

	public function test_administrator_can_add_and_remove_the_event_organizer_role(): void {
		$user        = rytkoset_test_register_user( 5, 'toimittaja@example.test' );
		$user->roles = array( 'editor' );

		$GLOBALS['rytkoset_test_caps'] = array(
			'promote_users' => true,
			'promote_user'  => true,
		);

		ob_start();
		rytkoset_theme_render_user_event_organizer_field( $user );
		$this->assertStringContainsString( 'name="rytkoset_event_organizer_role"', (string) ob_get_clean() );

		$this->save_organizer_toggle( 5, true );
		$this->assertSame( array( 'editor', 'event_organizer' ), $user->roles );

		$this->save_organizer_toggle( 5, false );
		$this->assertSame( array( 'editor' ), $user->roles );
	}

	public function test_group_headings_follow_the_appearance_switch(): void {
		ob_start();
		rytkoset_theme_print_admin_menu_group_headings();
		$script = (string) ob_get_clean();
		$this->assertStringContainsString( '"toiminta":"Toiminta"', $script );
		$this->assertStringContainsString( 'removeAttribute("aria-hidden")', $script );

		$off = static function () {
			return false;
		};
		add_filter( 'rytkoset_theme_enable_admin_appearance', $off );
		try {
			ob_start();
			rytkoset_theme_print_admin_menu_group_headings();
			$this->assertSame( '', (string) ob_get_clean() );
		} finally {
			remove_filter( 'rytkoset_theme_enable_admin_appearance', $off );
		}
	}
}
