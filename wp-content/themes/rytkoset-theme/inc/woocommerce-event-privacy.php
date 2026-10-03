<?php
/** Privacy Tools support for paid event participant addresses. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Finds orders with an explicitly supplied participant address. */
function rytkoset_theme_get_paid_event_orders_by_participant_email( $email, $page = 1, $per_page = 50 ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$meta_query = array( 'relation' => 'OR' );
	for ( $index = 1; $index <= rytkoset_theme_get_paid_event_max_participants(); $index++ ) {
		$meta_query[] = array(
			'key'   => sprintf( '_wc_other/rytkoset/participant_%d_email', $index ),
			'value' => $email,
		);
	}
	return wc_get_orders(
		array(
			'limit'      => max( 1, absint( $per_page ) ),
			'page'       => max( 1, absint( $page ) ),
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
}

/** Returns only the requested person's rows, without exposing the buyer or other participants. */
function rytkoset_theme_get_paid_event_matching_participants( $order, $email ) {
	$matches = array();
	foreach ( rytkoset_theme_get_paid_event_order_participant_contexts( $order ) as $position => $context ) {
		$index  = $position + 1;
		$stored = rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/participant_%d_email', $index ) );
		if ( '' === $stored || 0 !== strcasecmp( $stored, $email ) ) {
			continue;
		}
		$choice_field      = 'tampere_2026' === $context['mode'] ? 'friday_buffet' : 'choice';
		$choice            = '' !== $context['choice_label']
			? rytkoset_theme_format_paid_event_choice(
				$context['choice_label'],
				rytkoset_theme_get_order_additional_checkout_field_bool( $order, sprintf( 'rytkoset/participant_%d_%s', $index, $choice_field ) )
			)
			: '';
		$matches[ $index ] = array(
			'name'   => rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/participant_%d_name', $index ) ),
			'email'  => $stored,
			'diet'   => rytkoset_theme_get_order_additional_checkout_field_value( $order, sprintf( 'rytkoset/participant_%d_diet', $index ) ),
			'choice' => $choice,
		);
	}
	return $matches;
}

/** Registers the participant-only exporter beside WooCommerce's buyer exporter. */
function rytkoset_theme_register_paid_event_privacy_exporter( $exporters ) {
	$exporters['rytkoset-paid-event-participants'] = array(
		'exporter_friendly_name' => __( 'Maksullisten tapahtumien osallistujat', 'rytkoset-theme' ),
		'callback'               => 'rytkoset_theme_export_paid_event_participant_data',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'rytkoset_theme_register_paid_event_privacy_exporter' );

/** Exports only participant fields belonging to the requested address. */
function rytkoset_theme_export_paid_event_participant_data( $email, $page = 1 ) {
	$orders = rytkoset_theme_get_paid_event_orders_by_participant_email( $email, $page );
	$data   = array();
	foreach ( $orders as $order ) {
		foreach ( rytkoset_theme_get_paid_event_matching_participants( $order, $email ) as $index => $participant ) {
			$data[] = array(
				'group_id'    => 'rytkoset-paid-event-participants',
				'group_label' => __( 'Maksullisen tapahtuman osallistuminen', 'rytkoset-theme' ),
				'item_id'     => 'paid-event-participant-' . $order->get_id() . '-' . $index,
				'data'        => array(
					array(
						'name'  => __( 'Nimi', 'rytkoset-theme' ),
						'value' => $participant['name'],
					),
					array(
						'name'  => __( 'Sähköposti', 'rytkoset-theme' ),
						'value' => $participant['email'],
					),
					array(
						'name'  => __( 'Ruokarajoitteet ja allergiat', 'rytkoset-theme' ),
						'value' => $participant['diet'],
					),
					array(
						'name'  => __( 'Lisävalinta', 'rytkoset-theme' ),
						'value' => $participant['choice'],
					),
				),
			);
		}
	}
	return array(
		'data' => $data,
		'done' => count( $orders ) < 50,
	);
}

/** Registers erasure of the requested participant's fields only. */
function rytkoset_theme_register_paid_event_privacy_eraser( $erasers ) {
	$erasers['rytkoset-paid-event-participants'] = array(
		'eraser_friendly_name' => __( 'Maksullisten tapahtumien osallistujat', 'rytkoset-theme' ),
		'callback'             => 'rytkoset_theme_erase_paid_event_participant_data',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'rytkoset_theme_register_paid_event_privacy_eraser' );

/** Anonymizes only matching participant fields; the order and buyer data remain intact. */
function rytkoset_theme_erase_paid_event_participant_data( $email, $page = 1 ) {
	unset( $page );
	$orders  = rytkoset_theme_get_paid_event_orders_by_participant_email( $email );
	$removed = false;
	foreach ( $orders as $order ) {
		$matches = rytkoset_theme_get_paid_event_matching_participants( $order, $email );
		foreach ( array_keys( $matches ) as $index ) {
			$order->update_meta_data( sprintf( '_wc_other/rytkoset/participant_%d_name', $index ), __( 'Anonymisoitu osallistuja', 'rytkoset-theme' ) );
			$order->delete_meta_data( sprintf( '_wc_other/rytkoset/participant_%d_email', $index ) );
			$order->delete_meta_data( sprintf( '_wc_other/rytkoset/participant_%d_diet', $index ) );
			$order->delete_meta_data( sprintf( '_wc_other/rytkoset/participant_%d_choice', $index ) );
			$order->delete_meta_data( sprintf( '_wc_other/rytkoset/participant_%d_friday_buffet', $index ) );
			$removed = true;
		}
		if ( $matches ) {
			$order->save();
		}
	}
	return array(
		'items_removed'  => $removed,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => count( $orders ) < 50,
	);
}
