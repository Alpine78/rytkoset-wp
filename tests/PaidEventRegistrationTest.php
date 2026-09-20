<?php
/**
 * Tests for inc/woocommerce-event-registration.php — participant field-id helpers, participant counting
 * and the wp_mail failure formatter.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class PaidEventRegistrationTest extends Rytkoset_Theme_Test_Case {

	private function registration_product(): WC_Product {
		return new WC_Product( array( '_rytkoset_registration_mode' => 'tampere_2026' ) );
	}

	private function plain_product(): WC_Product {
		return new WC_Product( array() );
	}

	public function test_legacy_sku_and_parent_mode_remain_supported(): void {
		$sku_product = new WC_Product( array( '_sku' => 'tampere-2026-osallistumismaksu' ) );
		$parent      = rytkoset_test_register_product( 81, 'publish', 'Legacy event', array( '_rytkoset_registration_mode' => 'tampere_2026' ) );
		$variation   = new WC_Product( array( '_parent_id' => 81 ), 82 );

		$this->assertTrue( rytkoset_theme_is_paid_event_registration_product( $sku_product ) );
		$this->assertTrue( rytkoset_theme_is_paid_event_registration_product( $variation ) );
		$this->assertSame( $parent, rytkoset_theme_get_paid_event_registration_parent_product( $variation ) );
		$this->assertFalse( rytkoset_theme_is_paid_event_registration_product( $this->plain_product() ) );
	}

	public function test_legacy_order_participants_are_read_without_migration(): void {
		$order          = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $this->registration_product(), 'Osallistuja', 1 );
		$order->meta    = array(
			'_wc_other/rytkoset/participant_1_name'          => 'Testi Osallistuja',
			'_wc_other/rytkoset/participant_1_diet'          => 'Gluteeniton',
			'_wc_other/rytkoset/participant_1_friday_buffet' => '1',
		);
		$original_meta  = $order->meta;

		$this->assertSame(
			array(
				array(
					'name'             => 'Testi Osallistuja',
					'diet'             => 'Gluteeniton',
					'participant_type' => '',
					'friday_buffet'    => true,
				),
			),
			rytkoset_theme_get_paid_event_order_participants( $order )
		);
		$this->assertSame( $original_meta, $order->meta );
	}

	public function test_checkout_conditions_use_the_generic_cart_namespace(): void {
		$namespace = rytkoset_theme_get_paid_event_store_api_namespace();
		$schema    = rytkoset_theme_get_paid_event_participant_active_schema( 2 );
		$extension = $schema['properties']['cart']['properties']['extensions'];

		$this->assertSame( 'rytkoset_event_registration', $namespace );
		$this->assertSame( array( $namespace ), $extension['required'] );
		$this->assertSame( 2, $extension['properties'][ $namespace ]['properties']['participant_count']['minimum'] );
	}

	// --- participant field-id helpers --------------------------------------

	public function test_field_id_prefix_is_stripped(): void {
		$this->assertSame(
			'rytkoset/participant_2_name',
			rytkoset_theme_normalize_paid_event_participant_field_id( '_wc_other/rytkoset/participant_2_name' )
		);
	}

	public function test_field_id_without_prefix_is_unchanged(): void {
		$this->assertSame(
			'rytkoset/participant_2_diet',
			rytkoset_theme_normalize_paid_event_participant_field_id( 'rytkoset/participant_2_diet' )
		);
	}

	public function test_index_parsed_from_field_id(): void {
		$this->assertSame( 3, rytkoset_theme_get_paid_event_participant_index_from_field_id( 'rytkoset/participant_3_diet' ) );
		$this->assertSame( 5, rytkoset_theme_get_paid_event_participant_index_from_field_id( '_wc_other/rytkoset/participant_5_friday_buffet' ) );
	}

	public function test_index_zero_for_non_participant_field(): void {
		$this->assertSame( 0, rytkoset_theme_get_paid_event_participant_index_from_field_id( 'rytkoset/something_else' ) );
	}

	public function test_field_ids_built_for_index(): void {
		$this->assertSame(
			array(
				'rytkoset/participant_2_name',
				'rytkoset/participant_2_diet',
				'rytkoset/participant_2_friday_buffet',
			),
			rytkoset_theme_get_paid_event_participant_field_ids( 2 )
		);
	}

	public function test_max_participants_is_ten(): void {
		$this->assertSame( 10, rytkoset_theme_get_paid_event_max_participants() );
	}

	public function test_cart_participant_lines_expand_registration_quantities_in_cart_order(): void {
		$adult = new WC_Product(
			array(
				'_rytkoset_registration_mode' => 'tampere_2026',
				'_price'                      => '49',
				'osallistujatyyppi'           => 'aikuinen',
			)
		);
		$child = new WC_Product(
			array(
				'_rytkoset_registration_mode' => 'tampere_2026',
				'_price'                      => '24.5',
				'osallistujatyyppi'           => 'lapsi-3-12-vuotta',
			)
		);

		$lines = rytkoset_theme_build_paid_event_cart_participant_lines(
			array(
				array( 'data' => $adult, 'quantity' => 2 ),
				array( 'data' => $this->plain_product(), 'quantity' => 4 ),
				array( 'data' => $child, 'quantity' => 1 ),
			)
		);

		$this->assertSame(
			array(
				array( 'type' => 'aikuinen', 'price' => '49,00 €' ),
				array( 'type' => 'aikuinen', 'price' => '49,00 €' ),
				array( 'type' => 'lapsi 3 12 vuotta', 'price' => '24,50 €' ),
			),
			$lines
		);
	}

	public function test_store_api_schema_includes_participant_card_data(): void {
		$schema = rytkoset_theme_get_paid_event_store_api_cart_schema();

		$this->assertSame( 'array', $schema['participants']['type'] );
		$this->assertSame( 'string', $schema['participants']['items']['properties']['type']['type'] );
		$this->assertSame( 'string', $schema['participants']['items']['properties']['price']['type'] );
	}

	// --- participant counting ----------------------------------------------

	public function test_participant_quantity_sums_registration_items_only(): void {
		$order          = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $this->registration_product(), 'Osallistuja', 2 );
		$order->items[] = new Rytkoset_Test_Order_Item( $this->plain_product(), 'Kannatustuote', 5 );

		$this->assertSame( 2, rytkoset_theme_get_paid_event_order_participant_quantity( $order ) );
	}

	public function test_visible_field_limit_matches_quantity_for_registration_order(): void {
		$order          = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $this->registration_product(), 'Osallistuja', 3 );

		$this->assertSame( 3, rytkoset_theme_get_paid_event_visible_participant_field_limit( $order ) );
	}

	public function test_visible_field_limit_zero_for_non_registration_order(): void {
		$order          = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $this->plain_product(), 'Kannatustuote', 4 );

		$this->assertSame( 0, rytkoset_theme_get_paid_event_visible_participant_field_limit( $order ) );
	}

	// --- format_wp_mail_failure --------------------------------------------

	public function test_mail_failure_formats_wp_error_codes(): void {
		$error = new WP_Error( 'wp_mail_failed', 'SMTP yhteys katkesi.' );

		$this->assertSame( 'wp_mail_failed: SMTP yhteys katkesi.', rytkoset_theme_format_wp_mail_failure( $error ) );
	}

	public function test_mail_failure_fallback_for_non_error(): void {
		$this->assertSame(
			'Tarkempaa virhesyyta ei saatu wp_mail-kutsusta.',
			rytkoset_theme_format_wp_mail_failure( 'not-an-error' )
		);
	}
}
