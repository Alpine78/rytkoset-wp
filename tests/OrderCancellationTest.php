<?php
/**
 * Tests for inc/woocommerce-cancellation.php — itsepalvelu-peruutuksen
 * päätöslogiikka (#427): 14 vrk:n aikaikkuna, sallitut tilat, manuaalisen
 * käsittelyn tunnistus ja jo pyydetyn peruutuksen estäminen.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class OrderCancellationTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Builds a stub order with the fields the decision logic reads.
	 *
	 * @param string $status     Order status without the wc- prefix.
	 * @param int    $created_ts Creation timestamp.
	 * @return WC_Order
	 */
	private function make_order( string $status, int $created_ts ): WC_Order {
		$order               = new WC_Order();
		$order->id           = 123;
		$order->status       = $status;
		$order->date_created = ( new DateTimeImmutable() )->setTimestamp( $created_ts );

		return $order;
	}

	// --- Eligibility: status + window ----------------------------------------

	public function test_pending_order_within_window_is_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'pending', $now - DAY_IN_SECONDS );

		$this->assertTrue( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_processing_order_within_window_is_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'processing', $now - ( 13 * DAY_IN_SECONDS ) );

		$this->assertTrue( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_on_hold_order_within_window_is_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'on-hold', $now - 3600 );

		$this->assertTrue( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_completed_order_is_not_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'completed', $now - 3600 );

		$this->assertFalse( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_order_outside_window_is_not_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'processing', $now - ( 15 * DAY_IN_SECONDS ) );

		$this->assertFalse( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_window_boundary_exactly_14_days_is_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'pending', $now - ( 14 * DAY_IN_SECONDS ) );

		$this->assertTrue( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_future_dated_order_is_not_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'pending', $now + 3600 );

		$this->assertFalse( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_missing_creation_date_is_not_cancellable(): void {
		$order         = new WC_Order();
		$order->status = 'pending';

		$this->assertFalse( rytkoset_theme_order_is_cancellable( $order, 1_700_000_000 ) );
	}

	// --- Eligibility: already requested --------------------------------------

	public function test_order_with_pending_request_is_not_cancellable(): void {
		$now   = 1_700_000_000;
		$order = $this->make_order( 'processing', $now - 3600 );
		$order->update_meta_data( rytkoset_theme_get_order_cancellation_requested_meta_key(), '2026-06-20 10:00:00' );

		$this->assertFalse( rytkoset_theme_order_is_cancellable( $order, $now ) );
	}

	public function test_non_order_argument_is_not_cancellable(): void {
		$this->assertFalse( rytkoset_theme_order_is_cancellable( null ) );
		$this->assertFalse( rytkoset_theme_order_is_cancellable( 'not-an-order' ) );
	}

	// --- Custom window filter ------------------------------------------------

	public function test_custom_window_filter_is_respected(): void {
		$now    = 1_700_000_000;
		$order  = $this->make_order( 'pending', $now - ( 20 * DAY_IN_SECONDS ) );
		$filter = static function () {
			return 30;
		};

		add_filter( 'rytkoset_theme_order_cancellation_window_days', $filter );
		$result = rytkoset_theme_order_is_cancellable( $order, $now );
		remove_filter( 'rytkoset_theme_order_cancellation_window_days', $filter );

		$this->assertTrue( $result );
	}

	// --- Endpoint rewrite guard ---------------------------------------------

	public function test_endpoint_query_var_uses_public_finnish_slug(): void {
		$this->assertSame(
			array( 'rytkoset_cancel_order' => 'peruuta-tilaus' ),
			rytkoset_theme_register_cancellation_endpoint_query_var( array() )
		);
	}

	public function test_rewrite_rule_detection_requires_cancellation_slug(): void {
		update_option(
			'rewrite_rules',
			array(
				'oma-tili/(?:orders)/?$' => 'index.php?pagename=oma-tili&orders=1',
			)
		);

		$this->assertFalse( rytkoset_theme_cancellation_endpoint_rewrite_rules_exist() );

		update_option(
			'rewrite_rules',
			array(
				'oma-tili/(?:peruuta-tilaus)(?:/([^/]+))?/?$' => 'index.php?pagename=oma-tili&peruuta-tilaus=$matches[1]',
			)
		);

		$this->assertTrue( rytkoset_theme_cancellation_endpoint_rewrite_rules_exist() );
	}

	// --- Manual handling detection -------------------------------------------

	public function test_processing_order_requires_manual_handling(): void {
		$order = $this->make_order( 'processing', 1_700_000_000 );

		$this->assertTrue( rytkoset_theme_order_cancellation_requires_manual_handling( $order ) );
	}

	public function test_pending_order_does_not_require_manual_handling(): void {
		$order = $this->make_order( 'pending', 1_700_000_000 );

		$this->assertFalse( rytkoset_theme_order_cancellation_requires_manual_handling( $order ) );
	}

	public function test_on_hold_order_does_not_require_manual_handling(): void {
		$order = $this->make_order( 'on-hold', 1_700_000_000 );

		$this->assertFalse( rytkoset_theme_order_cancellation_requires_manual_handling( $order ) );
	}

	// --- Email notifications -------------------------------------------------

	public function test_customer_email_contains_timestamp_and_order_details(): void {
		$product              = new WC_Product( array(), 456, 'publish', 'Testituote' );
		$order                = $this->make_order( 'processing', 1_700_000_000 );
		$order->billing_email = 'asiakas@example.test';
		$order->items[]       = new Rytkoset_Test_Order_Item( $product, 'Testituote', 2 );

		$this->assertTrue( rytkoset_theme_send_order_cancellation_customer_email( $order, true, 1_782_413_200 ) );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );

		$mail = $GLOBALS['rytkoset_test_mails'][0];

		$this->assertSame( 'asiakas@example.test', $mail['to'] );
		$this->assertStringContainsString( 'Peruutuspyyntösi tilaukselle 123 on vastaanotettu', $mail['subject'] );
		$this->assertStringContainsString( 'Aikaleima:', $mail['message'] );
		$this->assertStringContainsString( 'Testituote × 2', $mail['message'] );
		$this->assertStringContainsString( 'Käsittelemme mahdollisen palautuksen manuaalisesti', $mail['message'] );
	}

	public function test_admin_email_uses_filterable_recipient_and_edit_link(): void {
		$order                 = $this->make_order( 'processing', 1_700_000_000 );
		$order->edit_order_url = 'https://rytkoset.test/wp-admin/post.php?post=123&action=edit';
		$filter                = static function () {
			return 'talous@example.test';
		};

		add_filter( 'rytkoset_theme_order_cancellation_admin_recipient', $filter );
		$result = rytkoset_theme_send_order_cancellation_admin_email( $order, 1_782_413_200 );
		remove_filter( 'rytkoset_theme_order_cancellation_admin_recipient', $filter );

		$this->assertTrue( $result );
		$this->assertCount( 1, $GLOBALS['rytkoset_test_mails'] );

		$mail = $GLOBALS['rytkoset_test_mails'][0];

		$this->assertSame( 'talous@example.test', $mail['to'] );
		$this->assertStringContainsString( 'Peruutuspyyntö: tilaus 123 vaatii palautuksen', $mail['subject'] );
		$this->assertStringContainsString( 'Pyynnön aikaleima:', $mail['message'] );
		$this->assertStringContainsString( 'Avaa tilaus: https://rytkoset.test/wp-admin/post.php?post=123&action=edit', $mail['message'] );
	}
}
