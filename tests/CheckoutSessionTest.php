<?php
/**
 * Regression tests for checkout background writes after cart clearing.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class CheckoutSessionTest extends Rytkoset_Theme_Test_Case {
	private mixed $request_started;

	protected function setUp(): void {
		parent::setUp();
		$this->request_started = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
		$_SERVER['REQUEST_TIME_FLOAT'] = 100.0;
		WC()->session = new class() {
			public string $id = 'customer-one';
			public int $writes = 0;
			public function get_customer_id(): string { return $this->id; }
			public function save_data(): void { ++$this->writes; }
		};
		add_action( 'shutdown', array( WC()->session, 'save_data' ), 20 );
	}

	protected function tearDown(): void {
		if ( null === $this->request_started ) {
			unset( $_SERVER['REQUEST_TIME_FLOAT'] );
		} else {
			$_SERVER['REQUEST_TIME_FLOAT'] = $this->request_started;
		}
		parent::tearDown();
	}

	public function test_late_background_write_cannot_replace_a_newer_session(): void {
		set_transient( rytkoset_theme_get_cart_clear_marker_key(), 101.0 );
		rytkoset_theme_discard_stale_checkout_session_update();
		do_action( 'shutdown' );
		$this->assertSame( 0, WC()->session->writes );
	}

	public function test_new_purchase_can_save_after_an_earlier_cart_clear(): void {
		set_transient( rytkoset_theme_get_cart_clear_marker_key(), 99.0 );
		rytkoset_theme_discard_stale_checkout_session_update();
		do_action( 'shutdown' );
		$this->assertSame( 1, WC()->session->writes );
	}

	public function test_normal_checkout_keeps_its_session_write_without_a_clear(): void {
		rytkoset_theme_discard_stale_checkout_session_update();
		do_action( 'shutdown' );
		$this->assertSame( 1, WC()->session->writes );
	}

	public function test_another_customers_clear_does_not_discard_this_cart(): void {
		set_transient( rytkoset_theme_get_cart_clear_marker_key(), 101.0 );
		WC()->session->id = 'customer-two';
		rytkoset_theme_discard_stale_checkout_session_update();
		do_action( 'shutdown' );
		$this->assertSame( 1, WC()->session->writes );
	}

	public function test_guard_targets_only_checkout_put_and_patch(): void {
		foreach ( array(
			array( 'PUT', '/wc/store/v1/checkout', true ),
			array( 'PATCH', '/wc/store/v1/checkout', true ),
			array( 'POST', '/wc/store/v1/checkout', false ),
			array( 'GET', '/wc/store/v1/checkout', false ),
			array( 'POST', '/wc/store/v1/cart/add-item', false ),
			array( 'PUT', '/wc/store/v1/cart/update-item', false ),
			array( 'PUT', '/wc/store/v1/checkout/123', false ),
		) as [$method, $route, $expected] ) {
			$request = new WP_REST_Request( $method, $route );
			$response = new WP_REST_Response( array( 'unchanged' => true ) );
			$this->assertSame( $response, apply_filters( 'rest_request_before_callbacks', $response, array(), $request ) );
			$this->assertSame( $expected, remove_action( 'shutdown', 'rytkoset_theme_discard_stale_checkout_session_update', 19 ) );
		}
	}

	public function test_clear_marker_is_published_after_session_persistence(): void {
		$key = rytkoset_theme_get_cart_clear_marker_key();
		rytkoset_theme_schedule_cart_clear_marker();
		$this->assertFalse( get_transient( $key ) );
		$observed_writes = null;
		add_action( 'shutdown', static function () use ( &$observed_writes, $key ) {
			$observed_writes = array( WC()->session->writes, get_transient( $key ) );
		}, 22 );
		do_action( 'shutdown' );
		$this->assertSame( 1, $observed_writes[0] );
		$this->assertGreaterThan( 0, $observed_writes[1] );
		$this->assertStringNotContainsString( 'customer-one', $key );
	}

	public function test_missing_start_time_does_not_discard_a_request(): void {
		set_transient( rytkoset_theme_get_cart_clear_marker_key(), 101.0 );
		unset( $_SERVER['REQUEST_TIME_FLOAT'] );
		rytkoset_theme_discard_stale_checkout_session_update();
		do_action( 'shutdown' );
		$this->assertSame( 1, WC()->session->writes );
	}

	public function test_missing_session_does_not_create_a_shared_marker(): void {
		WC()->session = null;
		rytkoset_theme_record_cart_clear_marker();
		rytkoset_theme_discard_stale_checkout_session_update();
		$this->assertSame( '', rytkoset_theme_get_cart_clear_marker_key() );
		$this->assertSame( array(), $GLOBALS['rytkoset_test_transients'] );
	}
}
