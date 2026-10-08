<?php
/**
 * Tests for inc/admin-dashboard.php and the chat widget status (#694).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class AdminDashboardTest extends Rytkoset_Theme_Test_Case {

	/** @var callable|null */
	private $paid_count = null;

	protected function tearDown(): void {
		if ( null !== $this->paid_count ) {
			remove_filter( 'rytkoset_theme_pre_event_paid_registration_count', $this->paid_count );
			$this->paid_count = null;
		}

		parent::tearDown();
	}

	private function event( int $id, string $title, string $date, string $start = '', string $status = 'publish' ): void {
		$post                                  = new WP_Post( $id, 'rytkoset_event', $title );
		$post->post_status                     = $status;
		$GLOBALS['rytkoset_test_posts'][ $id ] = $post;
		update_post_meta( $id, '_rytkoset_event_date', $date );

		if ( '' !== $start ) {
			update_post_meta( $id, '_rytkoset_event_start_time', $start );
		}
	}

	private function action_keys(): array {
		return array_column( rytkoset_theme_get_dashboard_quick_actions(), 'key' );
	}

	private function as_role( array $caps ): array {
		$GLOBALS['rytkoset_test_caps'] = array_fill_keys( $caps, true );
		return $this->action_keys();
	}

	public function test_quick_actions_follow_capabilities(): void {
		// Events use their own capability type; albums and posts use edit_posts.
		$GLOBALS['rytkoset_test_post_type_create_caps']['rytkoset_event'] = 'edit_rytkoset_events';

		$this->assertSame( array(), $this->as_role( array() ) );

		$organizer = array( 'edit_rytkoset_events', 'edit_others_event_registrations' );
		$this->assertSame( array( 'add_event', 'participants', 'messaging' ), $this->as_role( $organizer ) );

		$shop_manager = array( 'edit_posts', 'edit_shop_orders' );
		$this->assertSame( array( 'add_album', 'new_post', 'orders' ), $this->as_role( $shop_manager ) );

		$editor_and_organizer = array_merge( $organizer, array( 'edit_posts' ) );
		$this->assertSame( array( 'add_event', 'participants', 'messaging', 'add_album', 'new_post' ), $this->as_role( $editor_and_organizer ) );

		$administrator = array_merge( $editor_and_organizer, array( 'edit_shop_orders' ) );
		$this->assertSame( array( 'add_event', 'participants', 'messaging', 'add_album', 'new_post', 'orders' ), $this->as_role( $administrator ) );

		$orders = rytkoset_theme_get_dashboard_quick_actions()[5];
		$this->assertStringContainsString( 'edit.php?post_type=shop_order', $orders['url'] );
		$this->assertSame( 'dashicons-cart', $orders['icon'] );
	}

	public function test_widgets_register_only_with_access(): void {
		rytkoset_theme_register_dashboard_widgets();
		$this->assertSame( array(), $GLOBALS['rytkoset_test_dashboard_widgets'] );

		$GLOBALS['rytkoset_test_caps']['edit_others_event_registrations'] = true;
		rytkoset_theme_register_dashboard_widgets();
		$this->assertSame( array( 'rytkoset_quick_actions', 'rytkoset_upcoming_events' ), array_keys( $GLOBALS['rytkoset_test_dashboard_widgets'] ) );
	}

	public function test_upcoming_events_are_future_published_and_sorted(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-10-08 12:00:00';
		$this->paid_count             = static function () {
			return 3;
		};
		add_filter( 'rytkoset_theme_pre_event_paid_registration_count', $this->paid_count );

		$this->event( 10, 'Syysretki', '2026-11-01', '10:00' );
		$this->event( 11, 'Aamukahvit', '2026-11-01', '08:30' );
		$this->event( 12, 'Tänään', '2026-10-08' );
		$this->event( 13, 'Mennyt', '2026-10-07' );
		$this->event( 14, 'Luonnos', '2026-10-20', '', 'draft' );
		$this->event( 15, 'Ilman päivää', '' );

		$events = rytkoset_theme_get_dashboard_upcoming_events();

		// The event day still counts until it ends.
		$this->assertSame( array( 12, 11, 10 ), array_column( $events, 'id' ) );
		$this->assertSame( 3, $events[0]['registered'] );
		$this->assertSame( array( 12 ), array_column( rytkoset_theme_get_dashboard_upcoming_events( 1 ), 'id' ) );
	}

	public function test_technical_boxes_are_hidden_from_non_administrators_only(): void {
		rytkoset_theme_remove_dashboard_technical_widgets();
		$this->assertContains( 'dashboard:side:dashboard_primary', $GLOBALS['rytkoset_test_removed_meta_boxes'] );
		$this->assertContains( 'dashboard:normal:rank_math_dashboard_widget', $GLOBALS['rytkoset_test_removed_meta_boxes'] );
		$this->assertNotContains( 'dashboard:normal:dashboard_activity', $GLOBALS['rytkoset_test_removed_meta_boxes'] );

		$GLOBALS['rytkoset_test_removed_meta_boxes'] = array();
		$GLOBALS['rytkoset_test_caps']['manage_options'] = true;
		rytkoset_theme_remove_dashboard_technical_widgets();
		$this->assertSame( array(), $GLOBALS['rytkoset_test_removed_meta_boxes'] );
	}

	public function test_chat_status_badge(): void {
		$this->assertSame( 'neutral', rytkoset_theme_chat_get_dashboard_status( false, true )['variant'] );
		$this->assertSame( 'warning', rytkoset_theme_chat_get_dashboard_status( true, false )['variant'] );

		$on = rytkoset_theme_chat_get_dashboard_status( true, true );
		$this->assertSame( array( 'Käytössä', 'success', '' ), array( $on['label'], $on['variant'], $on['hint'] ) );
	}

	public function test_only_the_identified_store_setup_notice_is_matched(): void {
		if ( ! class_exists( 'Paytrail\\WooCommercePaymentGateway\\Gateway' ) ) {
			// Stand-in for the plugin's gateway class; only its name matters here.
			eval( 'namespace Paytrail\\WooCommercePaymentGateway; final class Gateway {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test-only class stand-in.
		}

		$gateway = new \Paytrail\WooCommercePaymentGateway\Gateway();

		$this->assertTrue( rytkoset_theme_is_store_setup_notice_callback( array( $gateway, 'admin_notices' ) ) );
		$this->assertFalse( rytkoset_theme_is_store_setup_notice_callback( array( $gateway, 'process_payment' ) ) );
		$this->assertFalse( rytkoset_theme_is_store_setup_notice_callback( array( new \stdClass(), 'admin_notices' ) ) );
		$this->assertFalse( rytkoset_theme_is_store_setup_notice_callback( 'rytkoset_theme_remove_store_setup_notices' ) );
	}
}
