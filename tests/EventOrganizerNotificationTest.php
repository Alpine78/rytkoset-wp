<?php
/**
 * Tests for the paid event organizer notification in inc/woocommerce-tampere-2026.php:
 * data minimization and the registration summary block (#643).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventOrganizerNotificationTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Registers a Tampere 2026 registration product.
	 *
	 * @param int    $id   Product ID.
	 * @param string $type Participant type attribute value.
	 * @return WC_Product
	 */
	private function registration_product( int $id, string $type = 'Aikuinen' ): WC_Product {
		return rytkoset_test_register_product(
			$id,
			'publish',
			'Osallistumismaksu',
			array(
				'_rytkoset_registration_mode' => 'tampere_2026',
				'osallistujatyyppi'           => $type,
			)
		);
	}

	/**
	 * Registers an event linked to a WooCommerce product.
	 *
	 * @param int $id         Event post ID.
	 * @param int $product_id Linked product ID.
	 * @return void
	 */
	private function event( int $id, int $product_id ): void {
		rytkoset_test_register_post( $id, 'rytkoset_event', 'Sukujuhla Tampereella', 0 );

		update_post_meta( $id, rytkoset_theme_get_event_date_meta_key(), '2026-08-29' );
		update_post_meta( $id, rytkoset_theme_get_event_details_meta_keys()['location'], 'Tampere' );
		update_post_meta( $id, rytkoset_theme_get_event_product_meta_key(), (string) $product_id );
	}

	/**
	 * Builds a paid Tampere 2026 order with two participants.
	 *
	 * @param WC_Product $product  Registration product.
	 * @param int        $quantity Ordered units.
	 * @return WC_Order
	 */
	private function order( WC_Product $product, int $quantity = 2 ): WC_Order {
		$order                       = new WC_Order();
		$order->id                   = 4321;
		$order->status               = 'processing';
		$order->date_created         = new DateTimeImmutable( '2026-07-01 12:00:00' );
		$order->billing_first_name   = 'Matti';
		$order->billing_last_name    = 'Meikäläinen';
		$order->billing_email        = 'matti@example.test';
		$order->billing_phone        = '040 1234567';
		$order->payment_method_title = 'Paytrail';
		$order->customer_note        = 'Saavumme perjantaina, tarvitsemme pyörätuolipaikan.';
		$order->items[]              = new Rytkoset_Test_Order_Item( $product, 'Osallistumismaksu', $quantity );

		$order->meta['_wc_other/rytkoset/participant_1_name']          = 'Matti Meikäläinen';
		$order->meta['_wc_other/rytkoset/participant_1_diet']          = 'Gluteeniton, pähkinäallergia';
		$order->meta['_wc_other/rytkoset/participant_1_friday_buffet'] = '1';
		$order->meta['_wc_other/rytkoset/participant_2_name']          = 'Liisa Meikäläinen';
		$order->meta['_wc_other/rytkoset/participant_2_diet']          = 'Laktoositon';

		return $order;
	}

	// --- data minimization ---------------------------------------------------

	public function test_message_omits_diet_details_and_the_customer_note(): void {
		$product = $this->registration_product( 900 );
		$this->event( 10, 900 );

		$message = rytkoset_theme_get_event_organizer_notification_message( $this->order( $product ), 10 );

		$this->assertStringNotContainsString( 'Gluteeniton', $message );
		$this->assertStringNotContainsString( 'pähkinäallergia', $message );
		$this->assertStringNotContainsString( 'Laktoositon', $message );
		$this->assertStringNotContainsString( 'ruokarajoitteet / allergiat', $message );
		$this->assertStringNotContainsString( 'pyörätuolipaikan', $message );
	}

	public function test_message_still_lists_participant_names_and_buffet_choice(): void {
		$product = $this->registration_product( 900 );
		$this->event( 10, 900 );

		$message = rytkoset_theme_get_event_organizer_notification_message( $this->order( $product ), 10 );

		$this->assertStringContainsString( '1. Matti Meikäläinen', $message );
		$this->assertStringContainsString( '2. Liisa Meikäläinen', $message );
		$this->assertStringContainsString( 'perjantain buffet: kyllä', $message );
	}

	public function test_message_keeps_the_billing_contact_once_without_per_participant_contacts(): void {
		$product = rytkoset_test_register_product( 900, 'publish', 'Etelä-Suomen tapaaminen' );
		$this->event( 10, 900 );

		$order                     = new WC_Order();
		$order->id                 = 4321;
		$order->billing_first_name = 'Matti';
		$order->billing_last_name  = 'Meikäläinen';
		$order->billing_email      = 'matti@example.test';
		$order->billing_phone      = '040 1234567';
		$order->items[]            = new Rytkoset_Test_Order_Item( $product, 'Osallistumismaksu', 2 );

		$message = rytkoset_theme_get_event_organizer_notification_message( $order, 10 );

		// The address and phone appear once, in the contact block only.
		$this->assertSame( 1, substr_count( $message, 'matti@example.test' ) );
		$this->assertSame( 1, substr_count( $message, '040 1234567' ) );
	}

	public function test_message_links_to_the_order_and_the_participants_view(): void {
		$product = $this->registration_product( 900 );
		$this->event( 10, 900 );

		$message = rytkoset_theme_get_event_organizer_notification_message( $this->order( $product ), 10 );

		$this->assertStringContainsString( 'post.php?post=4321&action=edit', $message );
		$this->assertStringContainsString( 'page=rytkoset-event-participants', $message );
		$this->assertStringContainsString( 'event_id=10', $message );
		$this->assertStringContainsString( 'löytyvät vain ylläpidosta', $message );
	}

	// --- registration summary -------------------------------------------------

	public function test_message_includes_the_registration_summary(): void {
		$product = $this->registration_product( 900 );
		$this->event( 10, 900 );

		$GLOBALS['wpdb']->get_var_result = '11';

		$message = rytkoset_theme_get_event_organizer_notification_message( $this->order( $product ), 10 );

		$this->assertStringContainsString( 'Ilmoittautumistilanne:', $message );
		$this->assertStringContainsString( 'Tämän ilmoittautumisen henkilömäärä: 2', $message );
		$this->assertStringContainsString( 'Ilmoittautuneita yhteensä: 11', $message );
	}

	public function test_summary_reports_remaining_capacity_for_a_stock_managed_product(): void {
		$product = rytkoset_test_register_product(
			900,
			'publish',
			'Bussikyyti',
			array(
				'_rytkoset_registration_mode' => 'tampere_2026',
				'_manage_stock'               => 'yes',
				'_stock'                      => 14,
			)
		);
		$this->event( 10, 900 );

		$GLOBALS['wpdb']->get_var_result = '6';

		$message = rytkoset_theme_get_event_organizer_notification_message( $this->order( $product ), 10 );

		$this->assertStringContainsString( 'Paikkoja jäljellä: 14', $message );
	}

	public function test_message_omits_the_total_when_the_paid_count_is_unavailable(): void {
		$product = $this->registration_product( 900 );
		$this->event( 10, 900 );

		$GLOBALS['wpdb']->last_error = 'Table is missing';

		$message = rytkoset_theme_get_event_organizer_notification_message( $this->order( $product ), 10 );

		$this->assertStringContainsString( 'Ilmoittautumistilanne:', $message );
		$this->assertStringNotContainsString( 'Ilmoittautuneita yhteensä', $message );
	}

	// --- WooCommerce admin email ---------------------------------------------

	public function test_diet_fields_are_hidden_from_the_admin_order_email(): void {
		$fields = array( 'rytkoset/participant_1_diet' => array( 'label' => 'Ruokarajoitteet' ) );

		$this->assertFalse(
			rytkoset_theme_hide_tampere_2026_diet_fields_from_admin_email(
				true,
				$fields['rytkoset/participant_1_diet'],
				$fields,
				array(
					'caller'        => 'WC_Email::additional_checkout_fields',
					'sent_to_admin' => true,
				)
			)
		);
	}

	public function test_diet_fields_stay_in_the_customer_confirmation(): void {
		$fields = array( 'rytkoset/participant_1_diet' => array( 'label' => 'Ruokarajoitteet' ) );

		$this->assertTrue(
			rytkoset_theme_hide_tampere_2026_diet_fields_from_admin_email(
				true,
				$fields['rytkoset/participant_1_diet'],
				$fields,
				array(
					'caller'        => 'WC_Email::additional_checkout_fields',
					'sent_to_admin' => false,
				)
			)
		);

		// The thank-you page and confirmation blocks pass no sent_to_admin flag at all.
		$this->assertTrue(
			rytkoset_theme_hide_tampere_2026_diet_fields_from_admin_email(
				true,
				$fields['rytkoset/participant_1_diet'],
				$fields,
				array( 'caller' => 'CheckoutFieldsFrontend::render_order_other_fields' )
			)
		);
	}

	public function test_other_participant_fields_stay_in_the_admin_order_email(): void {
		$fields = array( 'rytkoset/participant_1_name' => array( 'label' => 'Nimi' ) );

		$this->assertTrue(
			rytkoset_theme_hide_tampere_2026_diet_fields_from_admin_email(
				true,
				$fields['rytkoset/participant_1_name'],
				$fields,
				array( 'sent_to_admin' => true )
			)
		);
	}

	public function test_already_hidden_fields_are_not_re_enabled(): void {
		$fields = array( 'rytkoset/participant_9_name' => array( 'label' => 'Nimi' ) );

		$this->assertFalse(
			rytkoset_theme_hide_tampere_2026_diet_fields_from_admin_email(
				false,
				$fields['rytkoset/participant_9_name'],
				$fields,
				array( 'sent_to_admin' => true )
			)
		);
	}
}
