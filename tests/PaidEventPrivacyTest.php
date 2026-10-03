<?php
/** Tests that a paid participant's privacy request remains scoped to their row. */

declare( strict_types=1 );

final class PaidEventPrivacyTest extends Rytkoset_Theme_Test_Case {
	public function test_participant_export_and_erasure_leave_buyer_and_other_participant_intact(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ), 901 );
		$order = new WC_Order();
		$order->id = 42;
		$order->billing_email = 'ostaja@example.test';
		$order->billing_first_name = 'Ostaja';
		$order->items[] = new Rytkoset_Test_Order_Item( $product, 'Tapahtuma', 2 );
		$order->meta = array(
			'_wc_other/rytkoset/participant_1_name' => 'Ensimmäinen',
			'_wc_other/rytkoset/participant_1_email' => 'oma@example.test',
			'_wc_other/rytkoset/participant_1_diet' => 'Gluteeniton',
			'_wc_other/rytkoset/participant_2_name' => 'Toinen',
			'_wc_other/rytkoset/participant_2_email' => 'muu@example.test',
		);
		$GLOBALS['rytkoset_test_orders'][42] = $order;

		$export = rytkoset_theme_export_paid_event_participant_data( 'oma@example.test' );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'paid-event-participant-42-1', $export['data'][0]['item_id'] );
		$this->assertStringNotContainsString( 'Toinen', serialize( $export ) );
		$this->assertStringNotContainsString( 'ostaja@example.test', serialize( $export ) );

		$erasure = rytkoset_theme_erase_paid_event_participant_data( 'oma@example.test' );
		$this->assertTrue( $erasure['items_removed'] );
		$this->assertSame( 'Anonymisoitu osallistuja', $order->get_meta( '_wc_other/rytkoset/participant_1_name' ) );
		$this->assertSame( '', $order->get_meta( '_wc_other/rytkoset/participant_1_email' ) );
		$this->assertSame( '', $order->get_meta( '_wc_other/rytkoset/participant_1_diet' ) );
		$this->assertSame( 'muu@example.test', $order->get_meta( '_wc_other/rytkoset/participant_2_email' ) );
		$this->assertSame( 'Toinen', $order->get_meta( '_wc_other/rytkoset/participant_2_name' ) );
		$this->assertSame( 'ostaja@example.test', $order->get_billing_email() );
	}
}
