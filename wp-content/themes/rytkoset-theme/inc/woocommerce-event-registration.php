<?php
/**
 * Paid event registration: products, checkout participants, order views and notifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the historical SKU used only for legacy product compatibility.
 *
 * @return string
 */
function rytkoset_theme_get_legacy_event_registration_sku() {
	return 'tampere-2026-osallistumismaksu';
}

/**
 * Returns the registration mode inherited from a parent product.
 *
 * @param WC_Product|null $product Product or variation.
 * @return string
 */
function rytkoset_theme_get_paid_event_registration_mode( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	// Existing variations may carry the legacy marker directly.
	if ( 'tampere_2026' === $product->get_meta( '_rytkoset_registration_mode', true ) || rytkoset_theme_get_legacy_event_registration_sku() === (string) $product->get_sku() ) {
		return 'tampere_2026';
	}

	if ( $product->get_parent_id() > 0 ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent instanceof WC_Product ) {
			$product = $parent;
		}
	}

	$mode = (string) $product->get_meta( '_rytkoset_registration_mode', true );
	if ( in_array( $mode, array( 'event_participants', 'tampere_2026' ), true ) ) {
		return $mode;
	}

	return rytkoset_theme_get_legacy_event_registration_sku() === (string) $product->get_sku() ? 'tampere_2026' : '';
}

/**
 * Returns whether a product collects paid event participants.
 *
 * @param WC_Product|null $product Product or variation.
 * @return bool
 */
function rytkoset_theme_is_paid_event_registration_product( $product ) {
	return '' !== rytkoset_theme_get_paid_event_registration_mode( $product );
}

/**
 * Returns the parent registration product for a paid event product or variation.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return WC_Product|null
 */
function rytkoset_theme_get_paid_event_registration_parent_product( $product ) {
	if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return null;
	}

	if ( $product instanceof WC_Product && $product->get_parent_id() > 0 ) {
		$parent = wc_get_product( $product->get_parent_id() );

		if ( $parent instanceof WC_Product ) {
			return $parent;
		}
	}

	return $product instanceof WC_Product ? $product : null;
}

/**
 * Returns the product meta key used for the paid event registration deadline.
 *
 * @return string
 */
function rytkoset_theme_get_paid_event_registration_deadline_meta_key() {
	return '_rytkoset_registration_deadline';
}

/**
 * Returns the default deadline for the legacy registration product.
 *
 * @return string
 */
function rytkoset_theme_get_legacy_event_registration_default_deadline() {
	return '2026-07-30';
}

/**
 * Normalizes a registration deadline date into Y-m-d format.
 *
 * @param string $raw_date Raw date value.
 * @return string
 */
function rytkoset_theme_normalize_registration_deadline_date( $raw_date ) {
	$raw_date = trim( (string) $raw_date );

	if ( '' === $raw_date ) {
		return '';
	}

	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw_date, wp_timezone() );

	if ( ! $date ) {
		return '';
	}

	return $date->format( 'Y-m-d' ) === $raw_date ? $raw_date : '';
}

/**
 * Returns the registration deadline date for the paid event product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_paid_event_registration_deadline( $product ) {
	if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return '';
	}

	$deadline_product = rytkoset_theme_get_paid_event_registration_parent_product( $product );
	$stored_deadline  = $deadline_product instanceof WC_Product
		? $deadline_product->get_meta( rytkoset_theme_get_paid_event_registration_deadline_meta_key(), true )
		: $product->get_meta( rytkoset_theme_get_paid_event_registration_deadline_meta_key(), true );
	$deadline         = rytkoset_theme_normalize_registration_deadline_date( (string) $stored_deadline );

	if ( '' !== $deadline ) {
		return $deadline;
	}

	return 'tampere_2026' === rytkoset_theme_get_paid_event_registration_mode( $product )
		? rytkoset_theme_get_legacy_event_registration_default_deadline()
		: '';
}

/**
 * Returns the deadline cutoff timestamp for the paid event product.
 *
 * The product remains purchasable until the end of the configured day.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return DateTimeImmutable|null
 */
function rytkoset_theme_get_paid_event_registration_deadline_cutoff( $product ) {
	$deadline = rytkoset_theme_get_paid_event_registration_deadline( $product );

	if ( '' === $deadline ) {
		return null;
	}

	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline, wp_timezone() );

	if ( ! $date ) {
		return null;
	}

	return $date->modify( '+1 day' )->setTime( 0, 0, 0 );
}

/**
 * Returns true when the paid event registration deadline has passed.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_paid_event_registration_deadline_passed( $product ) {
	$cutoff = rytkoset_theme_get_paid_event_registration_deadline_cutoff( $product );

	if ( ! $cutoff instanceof \DateTimeImmutable ) {
		return false;
	}

	return current_datetime() >= $cutoff;
}

/**
 * Returns true when the paid event registration product is full.
 *
 * Capacity is based on WooCommerce stock quantity.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_paid_event_registration_full( $product ) {
	if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return false;
	}

	if ( $product->backorders_allowed() ) {
		return false;
	}

	return ! $product->is_in_stock();
}

/**
 * Returns the current unavailability reason for the paid event product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_paid_event_registration_unavailability_reason( $product ) {
	if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return '';
	}

	if ( rytkoset_theme_is_paid_event_registration_deadline_passed( $product ) ) {
		return 'deadline';
	}

	if ( rytkoset_theme_is_paid_event_registration_full( $product ) ) {
		return 'full';
	}

	return '';
}

/**
 * Returns the customer-facing unavailability message for the paid event product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_paid_event_registration_unavailability_message( $product ) {
	$reason = rytkoset_theme_get_paid_event_registration_unavailability_reason( $product );

	if ( 'deadline' === $reason ) {
		return __( 'Ilmoittautuminen on päättynyt.', 'rytkoset-theme' );
	}

	if ( 'full' === $reason ) {
		return __( 'Ilmoittautuminen on täynnä.', 'rytkoset-theme' );
	}

	return '';
}

/**
 * Renders paid event settings in the product inventory panel.
 *
 * @return void
 */
function rytkoset_theme_render_paid_event_product_management_fields() {
	global $product_object;

	if ( ! $product_object instanceof WC_Product ) {
		return;
	}
	$product = $product_object;
	$legacy  = 'tampere_2026' === rytkoset_theme_get_paid_event_registration_mode( $product );

	echo '<div class="options_group">';
	// Presence marker prevents unrelated product saves from resetting settings.
	echo '<input type="hidden" name="rytkoset_paid_event_settings" value="1">';
	woocommerce_wp_checkbox(
		array(
			'id'                => '_rytkoset_event_participants_enabled',
			'label'             => __( 'Tapahtuman osallistujat', 'rytkoset-theme' ),
			'description'       => $legacy
				? __( 'Vanhan tapahtumatuotteen osallistujakentät säilytetään automaattisesti.', 'rytkoset-theme' )
				: __( 'Kerää kassalla jokaisen osallistujan nimi ja mahdolliset ruokarajoitteet. Yksi tuotekappale vastaa yhtä osallistujaa.', 'rytkoset-theme' ),
			'value'             => rytkoset_theme_is_paid_event_registration_product( $product ) ? 'yes' : 'no',
			'custom_attributes' => $legacy ? array( 'disabled' => 'disabled' ) : array(),
		)
	);
	woocommerce_wp_text_input(
		array(
			'id'          => rytkoset_theme_get_paid_event_registration_deadline_meta_key(),
			'label'       => __( 'Ilmoittautumisen määräpäivä', 'rytkoset-theme' ),
			'description' => __( 'Tyhjä = ei määräpäivää uusille tapahtumatuotteille. Kapasiteetti asetetaan tuotteen varastosaldolla; pidä jälkitoimitukset pois päältä.', 'rytkoset-theme' ),
			'type'        => 'date',
			'value'       => rytkoset_theme_get_paid_event_registration_deadline( $product ),
		),
		$product
	);
	woocommerce_wp_text_input(
		array(
			'id'                => '_rytkoset_registration_max_participants',
			'label'             => __( 'Osallistujia enintään / tilaus', 'rytkoset-theme' ),
			'description'       => __( 'Tuotteen ja sen variaatioiden yhteinen raja (1–10). Yhdellä tilauksella voi olla yhteensä enintään 10 tapahtumaosallistujaa.', 'rytkoset-theme' ),
			'type'              => 'number',
			'value'             => rytkoset_theme_get_paid_event_product_participant_limit( $product ),
			'custom_attributes' => array(
				'min'  => 1,
				'max'  => 10,
				'step' => 1,
			),
		),
		$product
	);
	woocommerce_wp_text_input(
		array(
			'id'                => '_rytkoset_registration_choice_label',
			'label'             => __( 'Osallistujan kyllä/ei-lisävalinta', 'rytkoset-theme' ),
			'description'       => __( 'Tyhjä = valintaa ei kysytä. Esimerkiksi: Osallistun yhteiselle illalliselle. Valinta ei muuta hintaa. Älä kysy tässä terveystietoja.', 'rytkoset-theme' ),
			'value'             => rytkoset_theme_get_paid_event_choice_label( $product ),
			'custom_attributes' => $legacy ? array( 'disabled' => 'disabled' ) : array( 'maxlength' => 200 ),
		),
		$product
	);
	echo '</div>';
}
add_action( 'woocommerce_product_options_inventory_product_data', 'rytkoset_theme_render_paid_event_product_management_fields' );

/**
 * Returns an optional yes/no question inherited from the parent product.
 *
 * @param WC_Product|null $product Product or variation.
 * @return string
 */
function rytkoset_theme_get_paid_event_choice_label( $product ) {
	$product = rytkoset_theme_get_paid_event_registration_parent_product( $product );
	if ( ! $product instanceof WC_Product ) {
		return '';
	}
	if ( 'tampere_2026' === rytkoset_theme_get_paid_event_registration_mode( $product ) ) {
		return __( 'Perjantain buffet', 'rytkoset-theme' );
	}
	return mb_substr( sanitize_text_field( (string) $product->get_meta( '_rytkoset_registration_choice_label', true ) ), 0, 200 );
}

/**
 * Formats a question and its answer without confusing an absent field with No.
 *
 * @param string    $label Question text saved at checkout.
 * @param bool|null $value Answer, or null when the question was not asked.
 * @return string
 */
function rytkoset_theme_format_paid_event_choice( $label, $value ) {
	return '' === $label || null === $value ? '' : $label . ': ' . ( $value ? __( 'Kyllä', 'rytkoset-theme' ) : __( 'Ei', 'rytkoset-theme' ) );
}

/**
 * Returns the per-product limit, bounded by the checkout field ceiling.
 *
 * @param WC_Product|null $product Product or variation.
 * @return int
 */
function rytkoset_theme_get_paid_event_product_participant_limit( $product ) {
	if ( $product instanceof WC_Product && $product->get_parent_id() > 0 ) {
		$product = wc_get_product( $product->get_parent_id() );
	}
	$stored = $product instanceof WC_Product ? $product->get_meta( '_rytkoset_registration_max_participants', true ) : '';
	return '' === $stored ? 10 : max( 1, min( 10, (int) $stored ) );
}

/**
 * Saves settings after WooCommerce has checked product permissions and nonce.
 *
 * @param WC_Product $product Product being saved.
 * @return void
 */
function rytkoset_theme_save_paid_event_product_management_fields( $product ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product edit nonce and capability before this hook.
	if ( ! $product instanceof WC_Product || ! isset( $_POST['rytkoset_paid_event_settings'] ) ) {
		return;
	}
	$legacy = 'tampere_2026' === rytkoset_theme_get_paid_event_registration_mode( $product );
	if ( ! $legacy ) {
		$product->update_meta_data( '_rytkoset_registration_mode', isset( $_POST['_rytkoset_event_participants_enabled'] ) ? 'event_participants' : '' );
	}
	$raw_deadline = isset( $_POST['_rytkoset_registration_deadline'] ) && is_string( $_POST['_rytkoset_registration_deadline'] )
		? sanitize_text_field( wp_unslash( $_POST['_rytkoset_registration_deadline'] ) ) : '';
	$raw_limit    = isset( $_POST['_rytkoset_registration_max_participants'] ) && is_scalar( $_POST['_rytkoset_registration_max_participants'] )
		? (int) $_POST['_rytkoset_registration_max_participants'] : 10;
	$choice_label = isset( $_POST['_rytkoset_registration_choice_label'] ) && is_string( $_POST['_rytkoset_registration_choice_label'] )
		? mb_substr( sanitize_text_field( wp_unslash( $_POST['_rytkoset_registration_choice_label'] ) ), 0, 200 ) : '';
	if ( ! $legacy ) {
		$product->update_meta_data( '_rytkoset_registration_choice_label', $choice_label );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	$deadline = rytkoset_theme_normalize_registration_deadline_date( $raw_deadline );
	if ( '' !== $raw_deadline && '' === $deadline ) {
		WC_Admin_Meta_Boxes::add_error( __( 'Ilmoittautumisen määräpäivä ei ole kelvollinen päivämäärä. Aiempi määräpäivä säilytettiin.', 'rytkoset-theme' ) );
	} else {
		$product->update_meta_data( '_rytkoset_registration_deadline', '' === $deadline && $legacy ? rytkoset_theme_get_legacy_event_registration_default_deadline() : $deadline );
	}
	$product->update_meta_data( '_rytkoset_registration_max_participants', max( 1, min( 10, $raw_limit ) ) );
}
add_action( 'woocommerce_admin_process_product_object', 'rytkoset_theme_save_paid_event_product_management_fields' );

/**
 * Returns the paid event participant count from the current cart.
 *
 * @return int
 */
function rytkoset_theme_get_paid_event_participant_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	$participant_count = 0;

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
			continue;
		}

		$participant_count += isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
	}

	return max( 0, $participant_count );
}

/**
 * Returns true when the current cart contains paid event registrations.
 *
 * @return bool
 */
function rytkoset_theme_cart_has_paid_event_registration() {
	return rytkoset_theme_get_paid_event_participant_count() > 0;
}

/**
 * Builds the checkout notice shown for paid event registrations.
 *
 * @return string
 */
function rytkoset_theme_get_paid_event_checkout_notice_markup() {
	$notice_text = html_entity_decode(
		__( '<strong>Tapahtuman osallistujat:</strong> Täytä jokaiselle osallistujalle nimi ja mahdolliset ruokarajoitteet tai allergiat kohdassa Tilauksen lisätiedot.', 'rytkoset-theme' ),
		ENT_QUOTES,
		'UTF-8'
	);

	return sprintf(
		'<div class="rytkoset-checkout-note" role="note"><p>%s</p></div>',
		wp_kses_post( $notice_text )
	);
}

/**
 * Filters purchasability for the paid event registration product.
 *
 * @param bool            $is_purchasable Current purchasable state.
 * @param WC_Product|null $product        WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_filter_paid_event_product_purchasability( $is_purchasable, $product ) {
	if ( ! $is_purchasable || ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return $is_purchasable;
	}

	return '' === rytkoset_theme_get_paid_event_registration_unavailability_reason( $product );
}
add_filter( 'woocommerce_is_purchasable', 'rytkoset_theme_filter_paid_event_product_purchasability', 10, 2 );

/**
 * Filters availability text for the paid event registration product.
 *
 * @param string          $availability Availability text.
 * @param WC_Product|null $product      WooCommerce product object.
 * @return string
 */
function rytkoset_theme_filter_paid_event_product_availability_text( $availability, $product ) {
	if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return $availability;
	}

	$message = rytkoset_theme_get_paid_event_registration_unavailability_message( $product );

	return '' !== $message ? $message : $availability;
}
add_filter( 'woocommerce_get_availability_text', 'rytkoset_theme_filter_paid_event_product_availability_text', 10, 2 );

/**
 * Renders an explicit status message on the paid event product page when needed.
 *
 * @return void
 */
function rytkoset_theme_render_paid_event_product_page_notice() {
	if ( ! is_product() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$message = rytkoset_theme_get_paid_event_registration_unavailability_message( $product );

	if ( '' === $message ) {
		return;
	}

	printf(
		'<div class="woocommerce-info rytkoset-tampere-2026-product-notice">%s</div>',
		esc_html( $message )
	);
}
add_action( 'woocommerce_single_product_summary', 'rytkoset_theme_render_paid_event_product_page_notice', 25 );

/**
 * Prevents adding unavailable paid event registrations to the cart.
 *
 * @param bool $passed     Whether add to cart should proceed.
 * @param int  $product_id Product ID.
 * @return bool
 */
function rytkoset_theme_validate_paid_event_add_to_cart( $passed, $product_id ) {
	$product = wc_get_product( $product_id );

	if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
		return $passed;
	}

	$message = rytkoset_theme_get_paid_event_registration_unavailability_message( $product );

	if ( '' === $message ) {
		return $passed;
	}

	if ( ! wc_has_notice( $message, 'error' ) ) {
		wc_add_notice( $message, 'error' );
	}

	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'rytkoset_theme_validate_paid_event_add_to_cart', 10, 2 );

/**
 * Validates paid event registrations already present in cart or checkout.
 *
 * @return void
 */
function rytkoset_theme_validate_paid_event_cart_items() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$messages = array_fill_keys( rytkoset_theme_get_paid_event_cart_limit_errors( WC()->cart->get_cart() ), true );

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
			continue;
		}

		$message = rytkoset_theme_get_paid_event_registration_unavailability_message( $product );

		if ( '' !== $message ) {
			$messages[ $message ] = true;
		}
	}

	foreach ( array_keys( $messages ) as $message ) {
		if ( ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}
}
add_action( 'woocommerce_check_cart_items', 'rytkoset_theme_validate_paid_event_cart_items' );

/**
 * Validates aggregate limits across all variations of each event product.
 *
 * @param array $cart_items Cart items.
 * @return string[]
 */
function rytkoset_theme_get_paid_event_cart_limit_errors( $cart_items ) {
	$counts   = array();
	$products = array();
	$errors   = array();
	foreach ( $cart_items as $cart_item ) {
		$product = rytkoset_theme_get_paid_event_registration_parent_product( $cart_item['data'] ?? null );
		if ( ! $product instanceof WC_Product ) {
			continue;
		}
		$id              = $product->get_id();
		$counts[ $id ]   = ( $counts[ $id ] ?? 0 ) + max( 0, (int) ( $cart_item['quantity'] ?? 0 ) );
		$products[ $id ] = $product;
	}
	foreach ( $counts as $id => $quantity ) {
		$limit = rytkoset_theme_get_paid_event_product_participant_limit( $products[ $id ] );
		if ( $quantity > $limit ) {
			$errors[] = sprintf(
				/* translators: 1: product name, 2: maximum participants per order. */
				__( '%1$s: yhdellä tilauksella voi ilmoittaa enintään %2$d osallistujaa. Pienennä määrää ostoskorissa.', 'rytkoset-theme' ),
				$products[ $id ]->get_name(),
				$limit
			);
		}
	}
	if ( array_sum( $counts ) > rytkoset_theme_get_paid_event_max_participants() ) {
		$errors[] = __( 'Yhdellä tilauksella voi ilmoittaa yhteensä enintään 10 tapahtumaosallistujaa. Pienennä määrää ostoskorissa.', 'rytkoset-theme' );
	}
	return $errors;
}

/**
 * Rejects excessive participant quantities in the Store API as well.
 *
 * @param WP_Error $errors Cart validation errors.
 * @param WC_Cart  $cart   Current cart.
 * @return void
 */
function rytkoset_theme_validate_paid_event_store_api_cart( $errors, $cart ) {
	foreach ( rytkoset_theme_get_paid_event_cart_limit_errors( $cart->get_cart() ) as $message ) {
		$errors->add( 'rytkoset_participant_limit_' . md5( $message ), $message );
	}
}
add_action( 'woocommerce_store_api_cart_errors', 'rytkoset_theme_validate_paid_event_store_api_cart', 10, 2 );

/**
 * Returns the one-based participant positions with the requested checkbox.
 *
 * @param array $cart_items     Cart items.
 * @param bool  $generic_choice Select generic choices instead of legacy buffet fields.
 * @return int[]
 */
function rytkoset_theme_get_paid_event_checkbox_indices( $cart_items, $generic_choice = false ) {
	$indices = array();
	$index   = 0;
	foreach ( $cart_items as $cart_item ) {
		$mode = rytkoset_theme_get_paid_event_registration_mode( $cart_item['data'] ?? null );
		if ( '' === $mode ) {
			continue;
		}
		$quantity = max( 0, (int) ( $cart_item['quantity'] ?? 0 ) );
		for ( $i = 0; $i < $quantity && $index < rytkoset_theme_get_paid_event_max_participants(); $i++ ) {
			++$index;
			$enabled = $generic_choice
				? ( 'event_participants' === $mode && '' !== rytkoset_theme_get_paid_event_choice_label( $cart_item['data'] ) )
				: 'tampere_2026' === $mode;
			if ( $enabled ) {
				$indices[] = $index;
			}
		}
	}
	return $indices;
}

/**
 * Matches cart positions with an enabled generic choice or legacy buffet field.
 *
 * @param int  $index          One-based participant position.
 * @param bool $generic_choice Select generic choices instead of legacy buffet fields.
 * @return array
 */
function rytkoset_theme_get_paid_event_checkbox_schema( $index, $generic_choice = false ) {
	$schema                          = rytkoset_theme_get_paid_event_participant_active_schema( $index );
	$namespace                       = rytkoset_theme_get_paid_event_store_api_namespace();
	$extension                       = &$schema['properties']['cart']['properties']['extensions']['properties'][ $namespace ];
	$key                             = $generic_choice ? 'choice_indices' : 'legacy_buffet_indices';
	$extension['properties'][ $key ] = array(
		'type'     => 'array',
		'contains' => array( 'const' => (int) $index ),
	);
	$extension['required'][]         = $key;
	return $schema;
}

/**
 * Saves the registration mode on each purchased line, independently of later product edits.
 *
 * @param WC_Order $order Checkout order.
 * @return void
 */
function rytkoset_theme_snapshot_paid_event_order_items( $order ) {
	foreach ( $order->get_items() as $item ) {
		$mode = rytkoset_theme_get_paid_event_registration_mode( $item->get_product() );
		if ( '' !== $mode ) {
			$item->update_meta_data( '_rytkoset_registration_mode', $mode );
			$item->update_meta_data( '_rytkoset_registration_choice_label', rytkoset_theme_get_paid_event_choice_label( $item->get_product() ) );
			$item->update_meta_data( '_rytkoset_participant_type', rytkoset_theme_get_paid_event_participant_type_label( $item->get_product() ) );
			$item->save();
		}
	}
}
add_action( 'woocommerce_store_api_checkout_update_order_meta', 'rytkoset_theme_snapshot_paid_event_order_items' );

/**
 * Expands saved order lines in the same order as the checkout participant fields.
 *
 * @param WC_Order $order Order to read.
 * @return array
 */
function rytkoset_theme_get_paid_event_order_participant_contexts( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}
	$contexts = array();
	foreach ( $order->get_items() as $item ) {
		$mode = (string) $item->get_meta( '_rytkoset_registration_mode', true );
		if ( '' === $mode ) {
			$mode = rytkoset_theme_get_paid_event_registration_mode( $item->get_product() );
		}
		if ( ! in_array( $mode, array( 'event_participants', 'tampere_2026' ), true ) ) {
			continue;
		}
		$type = (string) $item->get_meta( '_rytkoset_participant_type', true );
		if ( '' === $type ) {
			$type = rytkoset_theme_get_paid_event_participant_type_label( $item->get_product() );
		}
		$context = array(
			'mode'         => $mode,
			'type'         => $type,
			'choice_label' => 'tampere_2026' === $mode ? __( 'Perjantain buffet', 'rytkoset-theme' ) : (string) $item->get_meta( '_rytkoset_registration_choice_label', true ),
			'product_ids'  => rytkoset_theme_get_order_item_product_reference_ids( $item ),
		);
		for ( $i = 0; $i < (int) $item->get_quantity(); $i++ ) {
			$contexts[] = $context;
		}
	}
	return $contexts;
}

/**
 * Checks whether an order contains participant fields for a specific event product.
 *
 * @param WC_Order $order      Order to read.
 * @param int      $product_id Linked event product.
 * @return bool
 */
function rytkoset_theme_order_has_paid_event_participant_product( $order, $product_id ) {
	foreach ( rytkoset_theme_get_paid_event_order_participant_contexts( $order ) as $context ) {
		if ( in_array( (int) $product_id, $context['product_ids'], true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Returns the maximum number of paid event participants supported in one order.
 *
 * @return int
 */
function rytkoset_theme_get_paid_event_max_participants() {
	return 10;
}

/**
 * Returns the Store API extension namespace for paid event cart data.
 *
 * @return string
 */
function rytkoset_theme_get_paid_event_store_api_namespace() {
	return 'rytkoset_event_registration';
}

/**
 * Builds paid event participant lines (type + unit price) from cart items (#520).
 *
 * One line per participant in the same order the participant checkout fields
 * are indexed (cart item order, quantity expanded), so line N describes
 * participant N. Used by the checkout participant card UI.
 *
 * @param array<int|string, array<string, mixed>> $cart_items WooCommerce cart items.
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_build_paid_event_cart_participant_lines( $cart_items ) {
	$lines = array();

	foreach ( $cart_items as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_paid_event_registration_product( $product ) ) {
			continue;
		}

		$type_label = rytkoset_theme_get_paid_event_participant_type_label( $product );
		if ( 'event_participants' === rytkoset_theme_get_paid_event_registration_mode( $product ) ) {
			$parent     = rytkoset_theme_get_paid_event_registration_parent_product( $product );
			$type_label = $parent->get_name() . ( '' !== $type_label ? ' — ' . $type_label : '' );
		}
		$price    = html_entity_decode(
			wp_strip_all_tags( wc_price( wc_get_price_including_tax( $product ) ) ),
			ENT_QUOTES,
			'UTF-8'
		);
		$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;

		for ( $i = 0; $i < $quantity && count( $lines ) < rytkoset_theme_get_paid_event_max_participants(); $i++ ) {
			$lines[] = array(
				'type'         => $type_label,
				'price'        => $price,
				'choice_label' => 'event_participants' === rytkoset_theme_get_paid_event_registration_mode( $product ) ? rytkoset_theme_get_paid_event_choice_label( $product ) : '',
			);
		}
	}

	return $lines;
}

/**
 * Returns paid event participant lines for the current cart (#520).
 *
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_get_paid_event_cart_participant_lines() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	return rytkoset_theme_build_paid_event_cart_participant_lines( WC()->cart->get_cart() );
}

/**
 * Returns paid event cart data for the WooCommerce Store API.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_paid_event_store_api_cart_data() {
	return array(
		'participant_count'     => rytkoset_theme_get_paid_event_participant_count(),
		'legacy_buffet_indices' => rytkoset_theme_get_paid_event_checkbox_indices( function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart() : array() ),
		'participants'          => rytkoset_theme_get_paid_event_cart_participant_lines(),
		'choice_indices'        => rytkoset_theme_get_paid_event_checkbox_indices( function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart() : array(), true ),
	);
}

/**
 * Returns the schema for paid event Store API cart data.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_paid_event_store_api_cart_schema() {
	return array(
		'legacy_buffet_indices' => array(
			'type'     => 'array',
			'readonly' => true,
			'items'    => array( 'type' => 'integer' ),
		),
		'choice_indices'        => array(
			'type'     => 'array',
			'readonly' => true,
			'items'    => array( 'type' => 'integer' ),
		),
		'participant_count'     => array(
			'description' => __( 'Tapahtumaosallistujien määrä ostoskorissa.', 'rytkoset-theme' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'readonly'    => true,
		),
		'participants'          => array(
			'description' => __( 'Tapahtuman osallistujarivit (osallistujatyyppi ja hinta) ostoskorissa.', 'rytkoset-theme' ),
			'type'        => 'array',
			'readonly'    => true,
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'type'         => array( 'type' => 'string' ),
					'price'        => array( 'type' => 'string' ),
					'choice_label' => array( 'type' => 'string' ),
				),
			),
		),
	);
}

/**
 * Registers paid event cart data for Checkout Block conditions.
 *
 * @return void
 */
function rytkoset_theme_register_paid_event_store_api_cart_data() {
	if (
		! function_exists( 'woocommerce_store_api_register_endpoint_data' )
		|| ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' )
	) {
		return;
	}

	woocommerce_store_api_register_endpoint_data(
		array(
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace'       => rytkoset_theme_get_paid_event_store_api_namespace(),
			'data_callback'   => 'rytkoset_theme_get_paid_event_store_api_cart_data',
			'schema_callback' => 'rytkoset_theme_get_paid_event_store_api_cart_schema',
			'schema_type'     => ARRAY_A,
		)
	);
}

/**
 * Registers Store API data after WooCommerce Blocks is available.
 */
if ( did_action( 'woocommerce_blocks_loaded' ) ) {
	rytkoset_theme_register_paid_event_store_api_cart_data();
} else {
	add_action( 'woocommerce_blocks_loaded', 'rytkoset_theme_register_paid_event_store_api_cart_data' );
}

/**
 * Enqueues the participant card header UI for the paid event checkout (#520).
 *
 * Injects a numbered header with the participant type and unit price above
 * each participant's field group, using the participant lines published in
 * the Store API cart extension.
 *
 * @return void
 */
function rytkoset_theme_enqueue_paid_event_checkout_participants() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	if ( ! rytkoset_theme_cart_has_paid_event_registration() ) {
		return;
	}

	$script_path = '/assets/js/event-checkout-participants.js';

	wp_enqueue_script(
		'rytkoset-event-checkout-participants',
		get_template_directory_uri() . $script_path,
		array( 'wp-data' ),
		rytkoset_theme_get_asset_version( get_template_directory() . $script_path ),
		true
	);

	$config = array(
		'namespace' => rytkoset_theme_get_paid_event_store_api_namespace(),
		'i18n'      => array(
			/* translators: %d: participant number. */
			'title'   => __( 'Osallistuja %d', 'rytkoset-theme' ),
			/* translators: 1: participant number, 2: optional yes/no question. */
			'choice'  => __( 'Osallistuja %1$d: %2$s (valinnainen)', 'rytkoset-theme' ),
			'heading' => __( 'Tapahtuman osallistujat', 'rytkoset-theme' ),
			'intro'   => __( 'Täytä jokaiselle osallistujalle nimi ja mahdolliset ruokarajoitteet tai allergiat.', 'rytkoset-theme' ),
		),
	);

	wp_add_inline_script(
		'rytkoset-event-checkout-participants',
		'window.rytkosetEventParticipants = ' . wp_json_encode( $config ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_enqueue_paid_event_checkout_participants' );

/**
 * Returns a JSON Schema fragment that matches an active participant field.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_paid_event_participant_active_schema( $index ) {
	$namespace = rytkoset_theme_get_paid_event_store_api_namespace();

	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'type'       => 'object',
				'properties' => array(
					'extensions' => array(
						'type'       => 'object',
						'properties' => array(
							$namespace => array(
								'type'       => 'object',
								'properties' => array(
									'participant_count' => array(
										'type'    => 'integer',
										'minimum' => max( 1, (int) $index ),
									),
								),
								'required'   => array( 'participant_count' ),
							),
						),
						'required'   => array( $namespace ),
					),
				),
				'required'   => array( 'extensions' ),
			),
		),
		'required'   => array( 'cart' ),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be required.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_paid_event_participant_required_schema( $index ) {
	return rytkoset_theme_get_paid_event_participant_active_schema( $index );
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be hidden.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_paid_event_participant_hidden_schema( $index ) {
	return array(
		'not' => rytkoset_theme_get_paid_event_participant_active_schema( $index ),
	);
}

/**
 * Registers participant fields for the paid event registration checkout flow.
 *
 * Uses WooCommerce Blocks' additional checkout fields API so the fields are
 * always registered for Store API submissions and then conditionally shown.
 *
 * @return void
 */
function rytkoset_theme_register_paid_event_checkout_fields() {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	for ( $index = 1; $index <= rytkoset_theme_get_paid_event_max_participants(); $index++ ) {
		$name_field_id   = sprintf( 'rytkoset/participant_%d_name', $index );
		$diet_field_id   = sprintf( 'rytkoset/participant_%d_diet', $index );
		$buffet_field_id = sprintf( 'rytkoset/participant_%d_friday_buffet', $index );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $name_field_id,
				/* translators: %d: participant number. */
				'label'             => sprintf( __( 'Osallistuja %d: nimi', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => rytkoset_theme_get_paid_event_participant_required_schema( $index ),
				'hidden'            => rytkoset_theme_get_paid_event_participant_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-participant-%d-name new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $diet_field_id,
				/* translators: %d: participant number. */
				'label'             => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat', 'rytkoset-theme' ), $index ),
				/* translators: %d: participant number. */
				'optionalLabel'     => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat (valinnainen)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'hidden'            => rytkoset_theme_get_paid_event_participant_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-participant-%d-diet new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $buffet_field_id,
				/* translators: %d: participant number. */
				'label'             => sprintf( __( 'Osallistuja %d: osallistuu perjantain 28.8. buffet-illalliselle (n. 30 €, maksu paikan päällä)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'checkbox',
				'required'          => false,
				'hidden'            => array( 'not' => rytkoset_theme_get_paid_event_checkbox_schema( $index ) ),
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => sprintf( 'rytkoset/participant_%d_choice', $index ),
				/* translators: %d: participant number. */
				'label'                      => sprintf( __( 'Osallistuja %d: lisävalinta', 'rytkoset-theme' ), $index ),
				'location'                   => 'order',
				'type'                       => 'checkbox',
				'required'                   => false,
				'hidden'                     => array( 'not' => rytkoset_theme_get_paid_event_checkbox_schema( $index, true ) ),
				// A separate summary pairs answers with their saved question text.
				'show_in_order_confirmation' => false,
				'sanitize_callback'          => 'rest_sanitize_boolean',
			)
		);

	}
}
add_action( 'woocommerce_init', 'rytkoset_theme_register_paid_event_checkout_fields' );

/**
 * Reads an additional checkout field value from an order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param string   $field_id Additional checkout field ID.
 * @return string
 */
function rytkoset_theme_get_order_additional_checkout_field_value( $order, $field_id ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	if (
		class_exists( '\Automattic\WooCommerce\Blocks\Package' )
		&& class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' )
	) {
		$checkout_fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
			\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
		);

		if ( is_object( $checkout_fields ) && method_exists( $checkout_fields, 'get_all_fields_from_object' ) ) {
			$fields = $checkout_fields->get_all_fields_from_object( $order, 'other', true );

			if ( isset( $fields[ $field_id ] ) ) {
				return (string) $fields[ $field_id ];
			}
		}
	}

	return (string) $order->get_meta( '_wc_other/' . $field_id, true );
}

/**
 * Returns true when an additional checkout checkbox field was checked.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param string   $field_id Additional checkout field ID.
 * @return bool
 */
function rytkoset_theme_get_order_additional_checkout_field_bool( $order, $field_id ) {
	$value = rytkoset_theme_get_order_additional_checkout_field_value( $order, $field_id );
	$value = strtolower( trim( (string) $value ) );

	return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Returns the participant type label for a paid event variation.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return string
 */
function rytkoset_theme_get_paid_event_participant_type_label( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$raw_value = '';

	if ( $product instanceof WC_Product_Variation ) {
		$variation_attributes = $product->get_variation_attributes();

		foreach ( $variation_attributes as $attribute_name => $attribute_value ) {
			if ( false !== strpos( (string) $attribute_name, 'osallistujatyyppi' ) ) {
				$raw_value = (string) $attribute_value;
				break;
			}
		}
	}

	if ( '' === $raw_value ) {
		$raw_value = (string) $product->get_attribute( 'osallistujatyyppi' );
	}

	if ( '' === $raw_value ) {
		$raw_value = (string) $product->get_attribute( 'pa_osallistujatyyppi' );
	}

	if ( '' === $raw_value ) {
		return '';
	}

	$taxonomy = taxonomy_exists( 'pa_osallistujatyyppi' ) ? 'pa_osallistujatyyppi' : '';

	if ( '' !== $taxonomy ) {
		$term = get_term_by( 'slug', $raw_value, $taxonomy );

		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->name;
		}
	}

	if ( false !== strpos( $raw_value, ' ' ) ) {
		return wc_clean( $raw_value );
	}

	return wc_clean( str_replace( '-', ' ', $raw_value ) );
}

/**
 * Returns participant type labels in the same order as order-level participant fields.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, string>
 */
function rytkoset_theme_get_paid_event_order_participant_type_sequence( $order ) {
	return array_column( rytkoset_theme_get_paid_event_order_participant_contexts( $order ), 'type' );
}

/**
 * Returns paid event participant data saved on an order.
 *
 * @param WC_Order $order      WooCommerce order object.
 * @param int      $product_id Optional event product filter; field indices remain order-wide.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_get_paid_event_order_participants( $order, $product_id = 0 ) {
	$contexts     = rytkoset_theme_get_paid_event_order_participant_contexts( $order );
	$participants = array();

	foreach ( $contexts as $position => $context ) {
		$index = $position + 1;
		if ( $product_id > 0 && ! in_array( (int) $product_id, $context['product_ids'], true ) ) {
			continue;
		}
		$name   = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_name', $index )
			)
		);
		$diet   = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_diet', $index )
			)
		);
		$buffet = rytkoset_theme_get_order_additional_checkout_field_bool(
			$order,
			sprintf( 'rytkoset/participant_%d_friday_buffet', $index )
		);
		$type   = $context['type'];
		if ( 'tampere_2026' !== $context['mode'] ) {
			$buffet = null;
		}

		$choice = null;
		if ( '' !== $context['choice_label'] ) {
			$choice = 'tampere_2026' === $context['mode'] ? $buffet : rytkoset_theme_get_order_additional_checkout_field_bool( $order, sprintf( 'rytkoset/participant_%d_choice', $index ) );
		}

		if ( '' === $name && '' === $diet && ! $buffet ) {
			continue;
		}

		$participants[] = array(
			'name'             => $name,
			'diet'             => $diet,
			'participant_type' => $type,
			'friday_buffet'    => $buffet,
			'choice_label'     => $context['choice_label'],
			'choice'           => $choice,
		);
	}

	return $participants;
}

/**
 * Returns generic choice summaries with the question text saved on the order.
 *
 * Legacy buffet fields already have a meaningful label in WooCommerce output.
 *
 * @param WC_Order $order Order to display.
 * @return string[]
 */
function rytkoset_theme_get_paid_event_order_choice_summary( $order ) {
	$lines = array();
	foreach ( rytkoset_theme_get_paid_event_order_participants( $order ) as $participant ) {
		if ( null !== $participant['friday_buffet'] ) {
			continue;
		}
		$text = rytkoset_theme_format_paid_event_choice( $participant['choice_label'], $participant['choice'] );
		if ( '' !== $text ) {
			$lines[] = $participant['name'] . ' — ' . $text;
		}
	}
	return $lines;
}

/**
 * Renders generic choices in order details and WooCommerce email summaries.
 *
 * @param WC_Order $order         Order to display.
 * @param bool     $sent_to_admin Whether the recipient is an administrator.
 * @param bool     $plain_text    Whether this is a plain-text email.
 * @return void
 */
function rytkoset_theme_render_paid_event_order_choices( $order, $sent_to_admin = false, $plain_text = false ) {
	$lines = rytkoset_theme_get_paid_event_order_choice_summary( $order );
	if ( empty( $lines ) ) {
		return;
	}
	if ( $plain_text ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email; strip tags instead of introducing HTML entities.
		echo "\n" . wp_strip_all_tags( __( 'Osallistujien lisävalinnat', 'rytkoset-theme' ) . "\n" . implode( "\n", $lines ) ) . "\n";
		return;
	}
	echo '<h2>' . esc_html__( 'Osallistujien lisävalinnat', 'rytkoset-theme' ) . '</h2><ul>';
	foreach ( $lines as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul>';
}
add_action( 'woocommerce_order_details_after_order_table', 'rytkoset_theme_render_paid_event_order_choices', 25 );
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_render_paid_event_order_choices', 25, 3 );

/**
 * Returns true when an order contains paid event registrations.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_is_paid_event_registration_order( $order ) {
	return ! empty( rytkoset_theme_get_paid_event_order_participant_contexts( $order ) );
}

/**
 * Returns the participant quantity purchased on a paid event order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return int
 */
function rytkoset_theme_get_paid_event_order_participant_quantity( $order ) {
	return count( rytkoset_theme_get_paid_event_order_participant_contexts( $order ) );
}

/**
 * Returns the highest paid event participant field index that should be shown for an order.
 *
 * Non-event orders can still contain stale hidden Store API checkbox meta. Those
 * participant fields are not relevant for the order and should never be displayed.
 *
 * @param WC_Order|null $order WooCommerce order object.
 * @return int
 */
function rytkoset_theme_get_paid_event_visible_participant_field_limit( $order ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_paid_event_registration_order( $order ) ) {
		return 0;
	}

	return rytkoset_theme_get_paid_event_order_participant_quantity( $order );
}

/**
 * Returns the paid event participant field IDs for a participant index.
 *
 * @param int $index Participant index.
 * @return array<int, string>
 */
function rytkoset_theme_get_paid_event_participant_field_ids( $index ) {
	$index = absint( $index );

	return array(
		sprintf( 'rytkoset/participant_%d_name', $index ),
		sprintf( 'rytkoset/participant_%d_diet', $index ),
		sprintf( 'rytkoset/participant_%d_friday_buffet', $index ),
		sprintf( 'rytkoset/participant_%d_choice', $index ),
	);
}

/**
 * Removes WooCommerce's order meta prefix from an additional checkout field ID.
 *
 * @param string $field_id Field ID or order meta key.
 * @return string
 */
function rytkoset_theme_normalize_paid_event_participant_field_id( $field_id ) {
	$field_id = (string) $field_id;
	$prefix   = '_wc_other/';

	if ( 0 === strpos( $field_id, $prefix ) ) {
		return substr( $field_id, strlen( $prefix ) );
	}

	return $field_id;
}

/**
 * Parses a paid event participant index from an additional checkout field ID.
 *
 * @param string $field_id Field ID or order meta key.
 * @return int Participant index, or 0 when the field is not a paid event participant field.
 */
function rytkoset_theme_get_paid_event_participant_index_from_field_id( $field_id ) {
	$field_id = rytkoset_theme_normalize_paid_event_participant_field_id( $field_id );

	if ( ! preg_match( '/^rytkoset\/participant_(\d+)_(?:name|diet|friday_buffet|choice)$/', $field_id, $matches ) ) {
		return 0;
	}

	return absint( $matches[1] );
}

/**
 * Resolves the original checkout field ID from WooCommerce order confirmation field data.
 *
 * @param array<string, mixed> $field Field data.
 * @param array<string, mixed> $fields All fields in the current confirmation context.
 * @return string
 */
function rytkoset_theme_get_order_confirmation_checkout_field_id( $field, $fields ) {
	if ( isset( $field['id'] ) && is_string( $field['id'] ) ) {
		return $field['id'];
	}

	foreach ( $fields as $field_id => $candidate_field ) {
		if ( $candidate_field === $field ) {
			return (string) $field_id;
		}
	}

	return '';
}

/**
 * Hides extra paid event participant fields from order confirmation views and emails.
 *
 * WooCommerce Blocks stores unchecked hidden checkboxes as false, which would otherwise
 * render as "Ei" for participants that were not actually purchased.
 *
 * @param bool                 $show Whether WooCommerce would show the field.
 * @param array<string, mixed> $field Field data.
 * @param array<string, mixed> $fields All fields in the current confirmation context.
 * @param array<string, mixed> $context Confirmation context.
 * @return bool
 */
function rytkoset_theme_filter_paid_event_order_confirmation_fields( $show, $field, $fields, $context ) {
	$field_id = rytkoset_theme_get_order_confirmation_checkout_field_id( $field, $fields );
	$index    = rytkoset_theme_get_paid_event_participant_index_from_field_id( $field_id );

	if ( $index < 1 ) {
		return $show;
	}

	$order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;

	$contexts = rytkoset_theme_get_paid_event_order_participant_contexts( $order );
	if ( ! isset( $contexts[ $index - 1 ] ) ) {
		return false;
	}
	if ( str_ends_with( $field_id, '_friday_buffet' ) && 'tampere_2026' !== $contexts[ $index - 1 ]['mode'] ) {
		return false;
	}
	if ( str_ends_with( $field_id, '_choice' ) && ( '' === $contexts[ $index - 1 ]['choice_label'] || 'tampere_2026' === $contexts[ $index - 1 ]['mode'] ) ) {
		return false;
	}

	return $show;
}
add_filter( 'woocommerce_filter_fields_for_order_confirmation', 'rytkoset_theme_filter_paid_event_order_confirmation_fields', 10, 4 );

/**
 * Hides paid event diet fields from WooCommerce's own admin order emails.
 *
 * WooCommerce renders the Store API additional fields into every order
 * confirmation surface, including the admin "New order" email. Diet
 * restrictions and allergies are health-related data that must not travel by
 * email to the organizer inbox (#643); they stay on the order screen and in
 * `Tapahtumat > Osallistujat`.
 *
 * `sent_to_admin` is only present in the two WC_Email callers, so the
 * customer's own confirmation email, the thank-you page and the order
 * confirmation blocks are left untouched.
 *
 * @param bool                 $show    Whether WooCommerce would show the field.
 * @param array<string, mixed> $field   Field data.
 * @param array<string, mixed> $fields  All fields in the current confirmation context.
 * @param array<string, mixed> $context Confirmation context.
 * @return bool
 */
function rytkoset_theme_hide_paid_event_diet_fields_from_admin_email( $show, $field, $fields, $context ) {
	if ( ! $show || empty( $context['sent_to_admin'] ) ) {
		return $show;
	}

	$field_id = rytkoset_theme_normalize_paid_event_participant_field_id(
		rytkoset_theme_get_order_confirmation_checkout_field_id( $field, $fields )
	);

	return 1 !== preg_match( '/^rytkoset\/participant_\d+_diet$/', $field_id );
}
add_filter( 'woocommerce_filter_fields_for_order_confirmation', 'rytkoset_theme_hide_paid_event_diet_fields_from_admin_email', 10, 4 );

/**
 * Hides free-text event order notes while rendering an admin email table.
 *
 * WooCommerce prints customer_note separately from additional checkout fields,
 * so the diet-field filter cannot protect sensitive text entered there.
 * The getter filter only changes the displayed value, never the saved order.
 *
 * @param string   $note  Customer note.
 * @param WC_Order $order Order being rendered.
 * @return string
 */
function rytkoset_theme_hide_event_customer_note_from_admin_email( $note, $order ) {
	if ( '' === $note || ! $order instanceof WC_Order ) {
		return $note;
	}

	return rytkoset_theme_is_paid_event_registration_order( $order ) || ! empty( rytkoset_theme_get_order_paid_event_ids( $order ) )
		? ''
		: $note;
}

/**
 * Enables note minimization for WooCommerce admin email order tables.
 *
 * @param WC_Order $order         Order being rendered.
 * @param bool     $sent_to_admin Whether the email is for the store admin.
 * @return void
 */
function rytkoset_theme_begin_event_admin_email_note_minimization( $order, $sent_to_admin ) {
	if ( $sent_to_admin ) {
		add_filter( 'woocommerce_order_get_customer_note', 'rytkoset_theme_hide_event_customer_note_from_admin_email', 10, 2 );
	}
}
add_action( 'woocommerce_email_before_order_table', 'rytkoset_theme_begin_event_admin_email_note_minimization', 10, 2 );

/**
 * Restores note display before subsequent customer emails or admin views.
 *
 * @param WC_Order $order         Order being rendered.
 * @param bool     $sent_to_admin Whether the email is for the store admin.
 * @return void
 */
function rytkoset_theme_end_event_admin_email_note_minimization( $order, $sent_to_admin ) {
	if ( $sent_to_admin ) {
		remove_filter( 'woocommerce_order_get_customer_note', 'rytkoset_theme_hide_event_customer_note_from_admin_email', 10 );
	}
}
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_end_event_admin_email_note_minimization', 10, 2 );

/**
 * Removes extra paid event participant fields from WooCommerce admin order fields.
 *
 * @param array<string, mixed> $fields Admin field definitions.
 * @param WC_Order|null        $order Order object.
 * @return array<string, mixed>
 */
function rytkoset_theme_filter_paid_event_admin_order_fields( $fields, $order = null ) {
	if ( ! $order instanceof WC_Order ) {
		return $fields;
	}

	foreach ( $fields as $field_key => $field ) {
		$field_id = is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : (string) $field_key;
		if ( ! rytkoset_theme_filter_paid_event_order_confirmation_fields( true, array( 'id' => $field_id ), array(), array( 'order' => $order ) ) ) {
			unset( $fields[ $field_key ] );
		}
	}

	return $fields;
}
add_filter( 'woocommerce_admin_shipping_fields', 'rytkoset_theme_filter_paid_event_admin_order_fields', 20, 2 );

/**
 * Deletes hidden extra paid event participant meta from new Store API orders.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_cleanup_paid_event_extra_participant_order_meta( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$max_participants = rytkoset_theme_get_paid_event_max_participants();
	$deleted_meta     = false;

	for ( $index = 1; $index <= $max_participants; $index++ ) {
		$fields = array();
		foreach ( rytkoset_theme_get_paid_event_participant_field_ids( $index ) as $field_id ) {
			if ( ! rytkoset_theme_filter_paid_event_order_confirmation_fields( true, array( 'id' => $field_id ), array(), array( 'order' => $order ) ) ) {
				$fields[] = $field_id;
			}
		}
		foreach ( $fields as $field_id ) {
			$meta_key = '_wc_other/' . $field_id;

			if ( ! $order->meta_exists( $meta_key ) ) {
				continue;
			}

			$order->delete_meta_data( $meta_key );
			$deleted_meta = true;
		}
	}

	if ( $deleted_meta ) {
		$order->save();
	}
}
add_action( 'woocommerce_store_api_checkout_order_processed', 'rytkoset_theme_cleanup_paid_event_extra_participant_order_meta', 20 );

/**
 * Registers the paid event participants metabox for order admin screens.
 *
 * Uses a dedicated metabox so the participant list is visible in both legacy
 * and HPOS order editors.
 *
 * @return void
 */
function rytkoset_theme_register_paid_event_order_metabox() {
	if ( ! function_exists( 'wc_get_page_screen_id' ) || ! function_exists( 'wc_get_container' ) ) {
		add_meta_box(
			'rytkoset-tampere-2026-participants',
			__( 'Tapahtuman osallistujat', 'rytkoset-theme' ),
			'rytkoset_theme_render_paid_event_order_participants_metabox',
			'shop_order',
			'side',
			'default'
		);

		return;
	}

	$screen = 'shop_order';

	if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
		$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
		$screen     = $controller->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	add_meta_box(
		'rytkoset-tampere-2026-participants',
		__( 'Tapahtuman osallistujat', 'rytkoset-theme' ),
		'rytkoset_theme_render_paid_event_order_participants_metabox',
		$screen,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'rytkoset_theme_register_paid_event_order_metabox' );

/**
 * Renders participant details inside the order admin metabox.
 *
 * @param mixed $post_or_order_object Either a WP_Post or WC_Order.
 * @return void
 */
function rytkoset_theme_render_paid_event_order_participants_metabox( $post_or_order_object ) {
	$order = rytkoset_theme_get_order_from_admin_screen_object( $post_or_order_object );

	if ( ! $order instanceof WC_Order ) {
		echo '<p>' . esc_html__( 'Tilausta ei voitu lukea.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	$participants = rytkoset_theme_get_paid_event_order_participants( $order );

	if ( empty( $participants ) ) {
		echo '<p>' . esc_html__( 'Tälle tilaukselle ei löytynyt osallistujatietoja.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	echo '<p><strong>' . esc_html__( 'Osallistujia:', 'rytkoset-theme' ) . '</strong> ' . esc_html( (string) count( $participants ) ) . '</p>';
	echo '<ol>';

	foreach ( $participants as $participant ) {
		echo '<li>';
		echo '<strong>' . esc_html( $participant['name'] ) . '</strong>';

		if ( ! empty( $participant['participant_type'] ) ) {
			echo '<br>';
			echo esc_html__( 'Osallistujatyyppi:', 'rytkoset-theme' ) . ' ' . esc_html( $participant['participant_type'] );
		}

		if ( '' !== $participant['diet'] ) {
			echo '<br>';
			echo esc_html__( 'Ruokarajoitteet / allergiat:', 'rytkoset-theme' ) . ' ' . esc_html( $participant['diet'] );
		}

		$choice_text = rytkoset_theme_format_paid_event_choice( $participant['choice_label'], $participant['choice'] );
		if ( '' !== $choice_text ) {
			echo '<br>' . esc_html( $choice_text );
		}

		echo '</li>';
	}

	echo '</ol>';
}

/**
 * Adds the paid event column to WooCommerce order lists.
 *
 * @param array<string, mixed> $columns Existing order list columns.
 * @return array<string, mixed>
 */
function rytkoset_theme_add_paid_event_orders_column( $columns ) {
	$new_columns = array();

	foreach ( $columns as $column_name => $column_label ) {
		$new_columns[ $column_name ] = $column_label;

		if ( 'order_status' === $column_name ) {
			$new_columns['rytkoset_tampere_2026'] = __( 'Tapahtumaosallistujat', 'rytkoset-theme' );
		}
	}

	if ( ! isset( $new_columns['rytkoset_tampere_2026'] ) ) {
		$new_columns['rytkoset_tampere_2026'] = __( 'Tapahtumaosallistujat', 'rytkoset-theme' );
	}

	return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'rytkoset_theme_add_paid_event_orders_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'rytkoset_theme_add_paid_event_orders_column' );

/**
 * Renders the paid event order list column value.
 *
 * @param string   $column_name Column name.
 * @param WC_Order $order       WooCommerce order object.
 * @return void
 */
function rytkoset_theme_render_paid_event_orders_column( $column_name, $order ) {
	if ( 'rytkoset_tampere_2026' !== $column_name ) {
		return;
	}

	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_paid_event_registration_order( $order ) ) {
		echo '&mdash;';
		return;
	}

	$participant_quantity = rytkoset_theme_get_paid_event_order_participant_quantity( $order );

	if ( $participant_quantity < 1 ) {
		echo '&mdash;';
		return;
	}

	echo esc_html(
		sprintf(
			/* translators: %d: participant count. */
			_n( '%d osallistuja', '%d osallistujaa', $participant_quantity, 'rytkoset-theme' ),
			$participant_quantity
		)
	);
}

/**
 * Legacy renderer wrapper for the paid event order list column.
 *
 * @param string $column_name Column name.
 * @return void
 */
function rytkoset_theme_render_paid_event_orders_column_legacy( $column_name ) {
	global $the_order;

	if ( ! $the_order instanceof WC_Order ) {
		return;
	}

	rytkoset_theme_render_paid_event_orders_column( $column_name, $the_order );
}
add_action( 'manage_shop_order_posts_custom_column', 'rytkoset_theme_render_paid_event_orders_column_legacy', 25, 1 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'rytkoset_theme_render_paid_event_orders_column', 25, 2 );

/**
 * Returns true when the current site runs in a local or development environment.
 *
 * @return bool
 */
function rytkoset_theme_is_local_or_dev_environment() {
	if ( function_exists( 'wp_get_environment_type' ) ) {
		$environment_type = wp_get_environment_type();

		if ( in_array( $environment_type, array( 'local', 'development' ), true ) ) {
			return true;
		}
	}

	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	if ( ! is_string( $host ) || '' === $host ) {
		return false;
	}

	return in_array(
		strtolower( $host ),
		array( 'localhost', '127.0.0.1', 'dev.rytkoset.net' ),
		true
	);
}

/**
 * Captures the latest wp_mail failure for debugging.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_capture_wp_mail_failure( $error ) {
	$GLOBALS['rytkoset_theme_last_wp_mail_failure'] = $error;
}

/**
 * Formats a wp_mail failure into a readable string.
 *
 * @param WP_Error|null $error wp_mail failure object.
 * @return string
 */
function rytkoset_theme_format_wp_mail_failure( $error ) {
	if ( ! $error instanceof WP_Error ) {
		return __( 'Tarkempaa virhesyyta ei saatu wp_mail-kutsusta.', 'rytkoset-theme' );
	}

	$parts = array();

	foreach ( $error->get_error_codes() as $code ) {
		$messages = $error->get_error_messages( $code );
		$message  = ! empty( $messages ) ? implode( ' | ', $messages ) : __( 'Ei virheviestia.', 'rytkoset-theme' );

		$parts[] = sprintf( '%s: %s', $code, $message );
	}

	return implode( ' || ', $parts );
}

/**
 * Adds a local/dev-only debug note for organizer notification failures.
 *
 * @param WC_Order $order         WooCommerce order object.
 * @param int      $event_id      Event post ID.
 * @param string   $subject       Email subject.
 * @param string   $message       Email body.
 * @param string   $error_summary Formatted wp_mail failure summary.
 * @return void
 */
function rytkoset_theme_add_event_organizer_notification_debug_note( $order, $event_id, $subject, $message, $error_summary ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$event_title = $event_id > 0 ? get_the_title( $event_id ) : '';

	$note_lines = array(
		__( 'Tapahtuman järjestäjäilmoituksen debug (local/dev).', 'rytkoset-theme' ),
		__( 'Tapahtuma:', 'rytkoset-theme' ) . ' ' . ( '' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		__( 'Virhe:', 'rytkoset-theme' ) . ' ' . $error_summary,
		'',
		__( 'Aihe:', 'rytkoset-theme' ) . ' ' . $subject,
		'',
		__( 'Lähetyksen viestisisältö:', 'rytkoset-theme' ),
		$message,
	);

	$order->add_order_note( implode( PHP_EOL, $note_lines ), false );
}

/**
 * Adds a local/dev-only debug note when wp_mail fails for event organizer notifications.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_maybe_log_event_organizer_notification_mail_failure( $error ) {
	if ( ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$context = isset( $GLOBALS['rytkoset_theme_current_event_organizer_notification'] ) && is_array( $GLOBALS['rytkoset_theme_current_event_organizer_notification'] )
		? $GLOBALS['rytkoset_theme_current_event_organizer_notification']
		: null;

	if ( ! is_array( $context ) ) {
		return;
	}

	$order_id = isset( $context['order_id'] ) ? absint( $context['order_id'] ) : 0;
	$event_id = isset( $context['event_id'] ) ? absint( $context['event_id'] ) : 0;
	$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$subject       = isset( $context['subject'] ) ? (string) $context['subject'] : '';
	$message       = isset( $context['message'] ) ? (string) $context['message'] : '';
	$error_summary = rytkoset_theme_format_wp_mail_failure( $error );

	rytkoset_theme_add_event_organizer_notification_debug_note( $order, $event_id, $subject, $message, $error_summary );
}
add_action( 'wp_mail_failed', 'rytkoset_theme_maybe_log_event_organizer_notification_mail_failure' );

/**
 * Returns the edit URL for an order in the current admin configuration.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_order_admin_edit_url( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
		$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );

		if ( is_object( $controller ) && method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) && $controller->custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );
		}
	}

	return admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
}

/**
 * Returns the order meta key used to deduplicate organizer notifications per event.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_sent_at_meta_key( $event_id ) {
	return '_rytkoset_event_organizer_notification_sent_at_' . absint( $event_id );
}

/**
 * Returns the order meta key used to store the recipients used per event.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_recipients_order_meta_key( $event_id ) {
	return '_rytkoset_event_organizer_notification_recipients_' . absint( $event_id );
}

/**
 * Returns product IDs that identify an order item, including variations and parents.
 *
 * @param mixed $item WooCommerce order item.
 * @return array<int, int>
 */
function rytkoset_theme_get_order_item_product_reference_ids( $item ) {
	$ids = array();

	if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
		$ids[] = absint( $item->get_product_id() );
	}

	if ( is_object( $item ) && method_exists( $item, 'get_variation_id' ) ) {
		$ids[] = absint( $item->get_variation_id() );
	}

	$product = is_object( $item ) && method_exists( $item, 'get_product' ) ? $item->get_product() : null;

	if ( $product instanceof WC_Product ) {
		$ids[] = absint( $product->get_id() );
		$ids[] = absint( $product->get_parent_id() );
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Returns product IDs represented in an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, int>
 */
function rytkoset_theme_get_order_product_reference_ids( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$product_ids = array();

	foreach ( $order->get_items() as $item ) {
		$product_ids = array_merge( $product_ids, rytkoset_theme_get_order_item_product_reference_ids( $item ) );
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
}

/**
 * Returns paid events linked to products in an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, int>
 */
function rytkoset_theme_get_order_paid_event_ids( $order ) {
	$product_ids = rytkoset_theme_get_order_product_reference_ids( $order );

	if ( empty( $product_ids ) ) {
		return array();
	}

	$event_ids = get_posts(
		array(
			'post_type'          => 'rytkoset_event',
			'post_status'        => array( 'publish', 'private' ),
			'posts_per_page'     => -1,
			'fields'             => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'     => rytkoset_theme_get_event_product_meta_key(),
							'value'   => $product_ids,
							'compare' => 'IN',
							'type'    => 'NUMERIC',
						),
					),
		)
	);

	return array_values( array_unique( array_map( 'absint', $event_ids ) ) );
}

/**
 * Returns the ordered quantity for the WooCommerce product linked to an event.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return int
 */
function rytkoset_theme_get_order_event_product_quantity( $order, $event_id ) {
	if ( ! $order instanceof WC_Order ) {
		return 0;
	}

	$product_id = absint( get_post_meta( absint( $event_id ), rytkoset_theme_get_event_product_meta_key(), true ) );

	if ( $product_id <= 0 ) {
		return 0;
	}

	$quantity = 0;

	foreach ( $order->get_items() as $item ) {
		if ( ! in_array( $product_id, rytkoset_theme_get_order_item_product_reference_ids( $item ), true ) ) {
			continue;
		}

		$quantity += is_object( $item ) && method_exists( $item, 'get_quantity' ) ? absint( $item->get_quantity() ) : 0;
	}

	return $quantity;
}

/**
 * Returns participant rows for an event organizer notification.
 *
 * Only the fields the notification renders are assembled. Paid event rows come
 * from the shared participant reader and still carry a `diet` value used by the
 * admin views; the notification never prints it (#643).
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_get_event_order_notification_participants( $order, $event_id ) {
	$product_id = (int) get_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), true );

	if (
		function_exists( 'rytkoset_theme_is_paid_event_registration_product' )
		&& function_exists( 'rytkoset_theme_get_paid_event_order_participants' )
		&& rytkoset_theme_order_has_paid_event_participant_product( $order, $product_id )
	) {
		return rytkoset_theme_get_paid_event_order_participants( $order, $product_id );
	}

	$quantity     = max( 1, rytkoset_theme_get_order_event_product_quantity( $order, $event_id ) );
	$contact_name = trim( $order->get_formatted_billing_full_name() );
	$participants = array();

	if ( '' === $contact_name ) {
		$contact_name = __( 'Nimi puuttuu', 'rytkoset-theme' );
	}

	// Contact email and phone are deliberately not repeated per participant: the
	// billing contact is already printed once in the message (#643).
	for ( $index = 0; $index < $quantity; $index++ ) {
		$participants[] = array(
			'name'             => $contact_name,
			'participant_type' => '',
			'friday_buffet'    => null,
		);
	}

	return $participants;
}

/**
 * Builds the organizer notification email subject for an event order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_subject( $order, $event_id ) {
	$event_title = wp_strip_all_tags( get_the_title( $event_id ) );

	if ( '' === $event_title ) {
		$event_title = __( 'Tapahtuma', 'rytkoset-theme' );
	}

	return sprintf(
		/* translators: 1: event title, 2: order number. */
		__( 'Tapahtumailmoittautuminen: %1$s / tilaus #%2$s', 'rytkoset-theme' ),
		$event_title,
		$order->get_order_number()
	);
}

/**
 * Builds the organizer notification email body for an event order.
 *
 * Data minimization (#643): the message carries the order basics, the billing
 * contact, the participant names and the non-personal registration summary.
 * Diet restrictions and allergies, per-participant contact details and the
 * customer's free-text note are deliberately left out and reached through the
 * admin links instead, following the model of the free registration
 * notification in inc/event-registrations.php.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_notification_message( $order, $event_id ) {
	$participants   = rytkoset_theme_get_event_order_notification_participants( $order, $event_id );
	$admin_edit_url = rytkoset_theme_get_order_admin_edit_url( $order );
	$event_title    = wp_strip_all_tags( get_the_title( $event_id ) );
	$event_date     = rytkoset_theme_get_event_date_display( $event_id );
	$event_location = rytkoset_theme_get_event_location( $event_id );
	$contact_name   = trim( $order->get_formatted_billing_full_name() );
	$email          = trim( (string) $order->get_billing_email() );
	$phone          = trim( (string) $order->get_billing_phone() );
	$payment_method = trim( (string) $order->get_payment_method_title() );
	$created_at     = $order->get_date_created();
	$created_text   = $created_at ? wp_date( 'j.n.Y H:i', $created_at->getTimestamp(), wp_timezone() ) : __( 'Ei tiedossa', 'rytkoset-theme' );
	$status_name    = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();
	$lines          = array(
		'Rytkösten sukuseura / tapahtumailmoittautuminen',
		'',
		'tapahtuma: ' . ( '' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		'tapahtumapäivä: ' . ( '' !== $event_date ? $event_date : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		'paikka: ' . ( '' !== $event_location ? $event_location : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
		'',
		'tilausnumero: #' . $order->get_order_number(),
		'päiväys: ' . $created_text,
		'tila: ' . $status_name,
		'maksutapa: ' . ( '' !== $payment_method ? $payment_method : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
	);

	if ( '' !== $admin_edit_url ) {
		$lines[] = 'tilauksen hallinta: ' . $admin_edit_url;
	}

	$lines[] = '';
	$lines[] = 'Yhteyshenkilö:';
	$lines[] = 'nimi: ' . ( '' !== $contact_name ? $contact_name : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = 'sähköposti: ' . ( '' !== $email ? $email : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = 'puhelin: ' . ( '' !== $phone ? $phone : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = '';
	$lines[] = 'Osallistujat:';

	if ( empty( $participants ) ) {
		$lines[] = '- Osallistujatietoja ei löytynyt tilaukselta.';
	} else {
		foreach ( $participants as $index => $participant ) {
			if ( ! is_array( $participant ) ) {
				continue;
			}

			$participant_name = isset( $participant['name'] ) && '' !== $participant['name'] ? (string) $participant['name'] : __( 'Nimi puuttuu', 'rytkoset-theme' );
			$lines[]          = sprintf( '%d. %s', $index + 1, $participant_name );

			if ( ! empty( $participant['participant_type'] ) ) {
				$lines[] = '   osallistujatyyppi: ' . $participant['participant_type'];
			}

			$choice_text = rytkoset_theme_format_paid_event_choice( $participant['choice_label'] ?? '', $participant['choice'] ?? null );
			if ( '' !== $choice_text ) {
				$lines[] = '   ' . $choice_text;
			}
		}
	}

	$summary_lines = rytkoset_theme_format_event_registration_summary_lines(
		rytkoset_theme_get_event_registration_summary( $event_id, count( $participants ) )
	);

	if ( ! empty( $summary_lines ) ) {
		$lines[] = '';
		$lines   = array_merge( $lines, $summary_lines );
	}

	// The order URL is repeated here on purpose so the "where are the details"
	// block is actionable on its own without scrolling back up.
	$lines[] = '';
	$lines[] = __( 'Ruokarajoitteet, allergiat ja muut osallistujien tarkat tiedot löytyvät vain ylläpidosta:', 'rytkoset-theme' );

	if ( '' !== $admin_edit_url ) {
		$lines[] = 'tilaus: ' . $admin_edit_url;
	}

	$lines[] = 'osallistujalista: ' . add_query_arg(
		array(
			'post_type' => 'rytkoset_event',
			'page'      => 'rytkoset-event-participants',
			'event_id'  => $event_id,
		),
		admin_url( 'edit.php' )
	);

	return implode( PHP_EOL, $lines );
}

/**
 * Sends the organizer notification for a paid event order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param int      $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_send_event_organizer_notification( $order, $event_id ) {
	$event_id = absint( $event_id );

	if ( ! $order instanceof WC_Order || $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) ) {
		return false;
	}

	$sent_at_meta_key = rytkoset_theme_get_event_organizer_notification_sent_at_meta_key( $event_id );
	$sent_at          = (string) $order->get_meta( $sent_at_meta_key, true );

	if ( '' !== $sent_at ) {
		return false;
	}

	$recipients  = rytkoset_theme_get_event_organizer_notification_recipients( $event_id );
	$event_title = wp_strip_all_tags( get_the_title( $event_id ) );

	if ( empty( $recipients ) ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: event title. */
				__( 'Tapahtuman järjestäjäilmoitusta ei lähetetty tapahtumalle "%s", koska tapahtumalle ei ole asetettu vastaanottajia.', 'rytkoset-theme' ),
				'' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' )
			),
			false
		);
		return false;
	}

	$subject = rytkoset_theme_get_event_organizer_notification_subject( $order, $event_id );
	$message = rytkoset_theme_get_event_organizer_notification_message( $order, $event_id );

	$GLOBALS['rytkoset_theme_current_event_organizer_notification'] = array(
		'order_id' => $order->get_id(),
		'event_id' => $event_id,
		'subject'  => $subject,
		'message'  => $message,
	);

	$sent = wp_mail( $recipients, $subject, $message );

	unset( $GLOBALS['rytkoset_theme_current_event_organizer_notification'] );

	if ( ! $sent ) {
		$order->add_order_note(
			sprintf(
				/* translators: 1: event title, 2: recipient email list. */
				__( 'Tapahtuman järjestäjäilmoituksen lähetys epäonnistui tapahtumalle "%1$s". Vastaanottajat: %2$s', 'rytkoset-theme' ),
				'' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ),
				implode( ', ', $recipients )
			),
			false
		);
		return false;
	}

	$order->update_meta_data( $sent_at_meta_key, current_time( 'mysql' ) );
	$order->update_meta_data( rytkoset_theme_get_event_organizer_notification_recipients_order_meta_key( $event_id ), implode( ', ', $recipients ) );
	$order->save();

	$order->add_order_note(
		sprintf(
			/* translators: 1: event title, 2: recipient email list. */
			__( 'Tapahtuman järjestäjäilmoitus lähetettiin tapahtumalle "%1$s" osoitteisiin: %2$s', 'rytkoset-theme' ),
			'' !== $event_title ? $event_title : __( 'Ei tiedossa', 'rytkoset-theme' ),
			implode( ', ', $recipients )
		),
		false
	);

	return true;
}

/**
 * Sends paid event organizer notifications when an order reaches an active status.
 *
 * @param int      $order_id WooCommerce order ID.
 * @param WC_Order $order    WooCommerce order object.
 * @return void
 */
function rytkoset_theme_maybe_send_event_organizer_notifications( $order_id, $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	foreach ( rytkoset_theme_get_order_paid_event_ids( $order ) as $event_id ) {
		rytkoset_theme_send_event_organizer_notification( $order, $event_id );
	}
}
add_action( 'woocommerce_order_status_on-hold', 'rytkoset_theme_maybe_send_event_organizer_notifications', 10, 2 );
add_action( 'woocommerce_order_status_processing', 'rytkoset_theme_maybe_send_event_organizer_notifications', 10, 2 );
add_action( 'woocommerce_order_status_completed', 'rytkoset_theme_maybe_send_event_organizer_notifications', 10, 2 );
