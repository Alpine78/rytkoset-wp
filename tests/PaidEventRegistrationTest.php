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
		rytkoset_test_register_product( 83, 'publish', 'Legacy parent' );
		$this->assertTrue( rytkoset_theme_is_paid_event_registration_product( new WC_Product( array( '_parent_id' => 83, '_rytkoset_registration_mode' => 'tampere_2026' ), 84 ) ) );
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
					'choice_label' => 'Perjantain buffet',
					'choice' => true,
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

	public function test_generic_product_inherits_mode_and_deadline_from_parent_without_legacy_sku(): void {
		$parent = rytkoset_test_register_product( 901, 'publish', 'Tuleva tapahtuma', array( '_rytkoset_registration_mode' => 'event_participants' ) );
		$child  = new WC_Product( array( '_parent_id' => 901 ), 902 );
		$this->assertTrue( rytkoset_theme_is_paid_event_registration_product( $child ) );
		$this->assertSame( '', rytkoset_theme_get_paid_event_registration_deadline( $child ) );
		$parent->update_meta_data( '_rytkoset_registration_deadline', '2029-08-10' );
		$this->assertSame( '2029-08-10', rytkoset_theme_get_paid_event_registration_deadline( $child ) );
		$this->assertSame( '2026-07-30', rytkoset_theme_get_paid_event_registration_deadline( $this->registration_product() ) );
	}

	public function test_admin_opt_in_and_disable_preserve_settings_on_unrelated_saves(): void {
		$product = $this->plain_product();
		$_POST = array(
			'rytkoset_paid_event_settings' => '1',
			'_rytkoset_event_participants_enabled' => 'yes',
			'_rytkoset_registration_deadline' => '2029-08-10',
			'_rytkoset_registration_max_participants' => '3',
		);
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( 'event_participants', rytkoset_theme_get_paid_event_registration_mode( $product ) );
		$this->assertSame( 3, rytkoset_theme_get_paid_event_product_participant_limit( $product ) );
		$_POST = array();
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( '2029-08-10', rytkoset_theme_get_paid_event_registration_deadline( $product ) );
		$_POST = array( 'rytkoset_paid_event_settings' => '1' );
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertFalse( rytkoset_theme_is_paid_event_registration_product( $product ) );
	}

	public function test_admin_invalid_date_preserves_previous_date_and_clamps_limit(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants', '_rytkoset_registration_deadline' => '2029-08-10' ) );
		$_POST = array(
			'rytkoset_paid_event_settings' => '1',
			'_rytkoset_event_participants_enabled' => 'yes',
			'_rytkoset_registration_deadline' => '2029-02-31',
			'_rytkoset_registration_max_participants' => '999',
		);
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( '2029-08-10', rytkoset_theme_get_paid_event_registration_deadline( $product ) );
		$this->assertSame( 10, rytkoset_theme_get_paid_event_product_participant_limit( $product ) );
		$this->assertNotEmpty( WC_Admin_Meta_Boxes::$errors );
	}

	public function test_legacy_mode_cannot_be_removed_by_new_admin_checkbox(): void {
		$product = $this->registration_product();
		$_POST = array( 'rytkoset_paid_event_settings' => '1' );
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( 'tampere_2026', rytkoset_theme_get_paid_event_registration_mode( $product ) );
		$this->assertSame( '2026-07-30', rytkoset_theme_get_paid_event_registration_deadline( $product ) );
	}

	public function test_limits_sum_variations_and_ignore_non_event_products(): void {
		rytkoset_test_register_product( 901, 'publish', 'Tuleva tapahtuma', array( '_rytkoset_registration_mode' => 'event_participants', '_rytkoset_registration_max_participants' => 3 ) );
		$cart = array(
			array( 'data' => new WC_Product( array( '_parent_id' => 901 ), 902 ), 'quantity' => 2 ),
			array( 'data' => new WC_Product( array( '_parent_id' => 901 ), 903 ), 'quantity' => 2 ),
			array( 'data' => $this->plain_product(), 'quantity' => 99 ),
		);
		$errors = rytkoset_theme_get_paid_event_cart_limit_errors( $cart );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( '3 osallistujaa', $errors[0] );
		$cart[1]['quantity'] = 1;
		$this->assertSame( array(), rytkoset_theme_get_paid_event_cart_limit_errors( $cart ) );
	}

	public function test_global_limit_is_enforced_by_store_api(): void {
		$cart = new Rytkoset_Test_Cart();
		$cart->items = array(
			array( 'data' => new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ), 901 ), 'quantity' => 6 ),
			array( 'data' => new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ), 902 ), 'quantity' => 5 ),
		);
		$errors = new WP_Error();
		rytkoset_theme_validate_paid_event_store_api_cart( $errors, $cart );
		$this->assertNotEmpty( $errors->get_error_codes() );
		$this->assertStringContainsString( 'yhteensä enintään 10', $errors->get_error_message() );
	}

	public function test_order_snapshot_survives_product_opt_out(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ), 901 );
		$order = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $product, 'Tuleva tapahtuma', 1 );
		$order->meta['_wc_other/rytkoset/participant_1_name'] = 'Testi Osallistuja';
		rytkoset_theme_snapshot_paid_event_order_items( $order );
		$product->update_meta_data( '_rytkoset_registration_mode', '' );
		$this->assertTrue( rytkoset_theme_is_paid_event_registration_order( $order ) );
		$this->assertTrue( rytkoset_theme_order_has_paid_event_participant_product( $order, 901 ) );
		$rows = rytkoset_theme_get_paid_event_order_participants( $order, 901 );
		$this->assertSame( 'Testi Osallistuja', $rows[0]['name'] );
		$this->assertNull( $rows[0]['friday_buffet'] );
	}

	public function test_mixed_events_keep_order_field_positions_and_legacy_choice(): void {
		$generic = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ), 901 );
		$legacy = new WC_Product( array( '_rytkoset_registration_mode' => 'tampere_2026' ), 902 );
		$order = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $generic, 'Tuleva', 1 );
		$order->items[] = new Rytkoset_Test_Order_Item( $legacy, 'Vanha', 1 );
		$order->meta['_wc_other/rytkoset/participant_1_name'] = 'Ensimmäinen';
		$order->meta['_wc_other/rytkoset/participant_2_name'] = 'Toinen';
		$order->meta['_wc_other/rytkoset/participant_2_friday_buffet'] = '1';
		$this->assertSame( 'Toinen', rytkoset_theme_get_paid_event_order_participants( $order, 902 )[0]['name'] );
		$this->assertTrue( rytkoset_theme_get_paid_event_order_participants( $order, 902 )[0]['friday_buffet'] );
		$this->assertSame( array( 2 ), rytkoset_theme_get_paid_event_checkbox_indices( array(
			array( 'data' => $generic, 'quantity' => 1 ), array( 'data' => $legacy, 'quantity' => 1 ),
		) ) );
		$this->assertFalse( rytkoset_theme_filter_paid_event_order_confirmation_fields( true, array( 'id' => 'rytkoset/participant_1_friday_buffet' ), array(), array( 'order' => $order ) ) );
		$this->assertTrue( rytkoset_theme_filter_paid_event_order_confirmation_fields( true, array( 'id' => 'rytkoset/participant_2_friday_buffet' ), array(), array( 'order' => $order ) ) );
	}

	public function test_cleanup_keeps_names_and_diets_but_removes_inapplicable_checkbox_meta(): void {
		$order = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ), 901 ), 'Uusi tapahtuma', 1 );
		$order->meta = array(
			'_wc_other/rytkoset/participant_1_name' => 'Testi',
			'_wc_other/rytkoset/participant_1_diet' => 'Ruokavalio',
			'_wc_other/rytkoset/participant_1_friday_buffet' => '1',
			'_wc_other/rytkoset/participant_2_name' => 'Ylimääräinen',
		);
		rytkoset_theme_cleanup_paid_event_extra_participant_order_meta( $order );
		$this->assertSame( array(
			'_wc_other/rytkoset/participant_1_name' => 'Testi',
			'_wc_other/rytkoset/participant_1_diet' => 'Ruokavalio',
		), $order->meta );
	}

	public function test_choice_is_opt_in_and_inherited_from_parent(): void {
		$parent = rytkoset_test_register_product( 901, 'publish', 'Tuleva', array( '_rytkoset_registration_mode' => 'event_participants' ) );
		$child = new WC_Product( array( '_parent_id' => 901 ), 902 );
		$this->assertSame( '', rytkoset_theme_get_paid_event_choice_label( $child ) );
		$parent->update_meta_data( '_rytkoset_registration_choice_label', 'Yhteinen illallinen' );
		$this->assertSame( 'Yhteinen illallinen', rytkoset_theme_get_paid_event_choice_label( $child ) );
		$parent->update_meta_data( '_rytkoset_registration_mode', '' );
		$this->assertSame( '', rytkoset_theme_get_paid_event_choice_label( $child ) );
		$this->assertSame( 'Perjantain buffet', rytkoset_theme_get_paid_event_choice_label( $this->registration_product() ) );
	}

	public function test_admin_sanitizes_choice_label_and_does_not_change_legacy_question(): void {
		$product = $this->plain_product();
		$_POST = array(
			'rytkoset_paid_event_settings' => '1',
			'_rytkoset_event_participants_enabled' => 'yes',
			'_rytkoset_registration_choice_label' => '<b>Illallinen</b>',
		);
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( 'Illallinen', rytkoset_theme_get_paid_event_choice_label( $product ) );
		$_POST['_rytkoset_registration_choice_label'] = str_repeat( 'ä', 201 );
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( 200, mb_strlen( rytkoset_theme_get_paid_event_choice_label( $product ) ) );
		$_POST['_rytkoset_registration_choice_label'] = array( 'invalid' );
		rytkoset_theme_save_paid_event_product_management_fields( $product );
		$this->assertSame( '', rytkoset_theme_get_paid_event_choice_label( $product ) );
		$legacy = $this->registration_product();
		rytkoset_theme_save_paid_event_product_management_fields( $legacy );
		$this->assertSame( 'Perjantain buffet', rytkoset_theme_get_paid_event_choice_label( $legacy ) );
	}

	public function test_mixed_cart_positions_separate_generic_choice_from_legacy_buffet(): void {
		$enabled = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants', '_rytkoset_registration_choice_label' => 'Illallinen' ) );
		$disabled = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ) );
		$cart = array(
			array( 'data' => $this->plain_product(), 'quantity' => 3 ),
			array( 'data' => $disabled, 'quantity' => 1 ),
			array( 'data' => $enabled, 'quantity' => 2 ),
			array( 'data' => $this->registration_product(), 'quantity' => 1 ),
		);
		$this->assertSame( array( 2, 3 ), rytkoset_theme_get_paid_event_checkbox_indices( $cart, true ) );
		$this->assertSame( array( 4 ), rytkoset_theme_get_paid_event_checkbox_indices( $cart ) );
	}

	public function test_order_choice_preserves_question_and_both_answers_after_product_changes(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants', '_rytkoset_registration_choice_label' => 'Illallinen' ), 901 );
		$order = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $product, 'Tuleva', 2 );
		$order->meta = array(
			'_wc_other/rytkoset/participant_1_name' => 'Yksi',
			'_wc_other/rytkoset/participant_1_choice' => '1',
			'_wc_other/rytkoset/participant_2_name' => 'Kaksi',
			'_wc_other/rytkoset/participant_2_choice' => '0',
		);
		rytkoset_theme_snapshot_paid_event_order_items( $order );
		$product->update_meta_data( '_rytkoset_registration_choice_label', 'Bussikyyti' );
		$product->update_meta_data( '_rytkoset_registration_mode', '' );
		$rows = rytkoset_theme_get_paid_event_order_participants( $order, 901 );
		$this->assertSame( 'Illallinen', $rows[0]['choice_label'] );
		$this->assertTrue( $rows[0]['choice'] );
		$this->assertFalse( $rows[1]['choice'] );
		$this->assertNull( $rows[0]['friday_buffet'] );
		$this->assertSame( array( 'Yksi — Illallinen: Kyllä', 'Kaksi — Illallinen: Ei' ), rytkoset_theme_get_paid_event_order_choice_summary( $order ) );
		$this->assertSame( array(), rytkoset_theme_get_paid_event_order_participants( $order, 902 ) );
		rytkoset_theme_cleanup_paid_event_extra_participant_order_meta( $order );
		$this->assertSame( '1', $order->meta['_wc_other/rytkoset/participant_1_choice'] );
	}

	public function test_enabling_choice_does_not_invent_answers_on_older_orders(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants' ) );
		$order = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $product, 'Tuleva', 1 );
		$order->meta['_wc_other/rytkoset/participant_1_name'] = 'Yksi';
		$product->update_meta_data( '_rytkoset_registration_choice_label', 'Illallinen' );
		$rows = rytkoset_theme_get_paid_event_order_participants( $order );
		$this->assertSame( '', $rows[0]['choice_label'] );
		$this->assertNull( $rows[0]['choice'] );
		$this->assertSame( array(), rytkoset_theme_get_paid_event_order_choice_summary( $order ) );
		// A forged hidden checkbox must not survive cleanup either.
		$order->meta['_wc_other/rytkoset/participant_1_choice'] = '1';
		rytkoset_theme_cleanup_paid_event_extra_participant_order_meta( $order );
		$this->assertArrayNotHasKey( '_wc_other/rytkoset/participant_1_choice', $order->meta );
	}

	public function test_choice_summary_escapes_html_and_omits_legacy_duplicates(): void {
		$product = new WC_Product( array( '_rytkoset_registration_mode' => 'event_participants', '_rytkoset_registration_choice_label' => 'A & B' ) );
		$order = new WC_Order();
		$order->items[] = new Rytkoset_Test_Order_Item( $product, 'Tuleva', 1 );
		$order->items[] = new Rytkoset_Test_Order_Item( $this->registration_product(), 'Vanha', 1 );
		$order->meta = array(
			'_wc_other/rytkoset/participant_1_name' => '<b>Yksi</b>',
			'_wc_other/rytkoset/participant_1_choice' => '1',
			'_wc_other/rytkoset/participant_2_name' => 'Kaksi',
			'_wc_other/rytkoset/participant_2_friday_buffet' => '1',
		);
		rytkoset_theme_snapshot_paid_event_order_items( $order );
		ob_start();
		rytkoset_theme_render_paid_event_order_choices( $order );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'A &amp; B: Kyllä', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'Perjantain buffet', $html );
		ob_start();
		rytkoset_theme_render_paid_event_order_choices( $order, false, true );
		$plain = ob_get_clean();
		$this->assertStringContainsString( 'Yksi — A & B: Kyllä', $plain );
		$this->assertStringNotContainsString( '<b>', $plain );
		$this->assertSame( '', rytkoset_theme_format_paid_event_choice( '', false ) );
		$this->assertSame( '', rytkoset_theme_format_paid_event_choice( 'Question', null ) );
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
				'rytkoset/participant_2_choice',
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
				array( 'type' => 'aikuinen', 'price' => '49,00 €', 'choice_label' => '' ),
				array( 'type' => 'aikuinen', 'price' => '49,00 €', 'choice_label' => '' ),
				array( 'type' => 'lapsi 3 12 vuotta', 'price' => '24,50 €', 'choice_label' => '' ),
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
