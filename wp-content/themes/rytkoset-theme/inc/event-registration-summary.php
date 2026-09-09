<?php
/**
 * Ilmoittautumisyhteenveto järjestäjäilmoituksiin.
 *
 * Shared by the free registration notification (inc/event-registrations.php) and
 * the paid order notification (inc/woocommerce-tampere-2026.php). Both emails are
 * sent from latency-sensitive paths (a public form POST and a WooCommerce order
 * status transition), so the counting here deliberately avoids
 * `rytkoset_theme_get_event_participants()`, which hydrates every supported-status
 * WooCommerce order (see #376). Free registrations are counted with a bounded
 * meta query and paid units with one indexed order-items query.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the WooCommerce order statuses that count as an active registration.
 *
 * Mirrors the active-participant audience used by the messaging and feedback
 * tools: a cancelled, refunded or failed order is not a registration, and an
 * abandoned Checkout Block draft never was one.
 *
 * @return string[] Unprefixed order status slugs.
 */
function rytkoset_theme_get_event_summary_active_order_statuses() {
	if ( ! function_exists( 'wc_get_order_statuses' ) ) {
		return array();
	}

	$inactive = function_exists( 'rytkoset_theme_get_event_feedback_inactive_order_statuses' )
		? rytkoset_theme_get_event_feedback_inactive_order_statuses()
		: array( 'cancelled', 'refunded', 'failed' );

	$inactive = array_merge( (array) $inactive, array( 'checkout-draft', 'trash' ) );

	return array_values( array_diff( rytkoset_theme_get_supported_order_statuses(), $inactive ) );
}

/**
 * Counts the people registered through the free registration form.
 *
 * Cancelled registrations are skipped. When the event collects a quantity,
 * each row counts as its saved quantity, the same rule the participants
 * admin summary box uses.
 *
 * @param int $event_id Event post ID.
 * @return int
 */
function rytkoset_theme_count_event_free_registrations( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return 0;
	}

	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

	$registration_ids = get_posts(
		array(
			'post_type'              => 'event_registration',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'             => array(
				array(
					'key'   => $meta_keys['event_id'],
					'value' => $event_id,
				),
			),
		)
	);

	if ( ! is_array( $registration_ids ) || empty( $registration_ids ) ) {
		return 0;
	}

	// get_posts() with fields => ids skips the meta cache, so prime it once
	// instead of letting every getter below issue its own query.
	update_meta_cache( 'post', $registration_ids );

	$active_statuses  = rytkoset_theme_get_active_event_registration_statuses();
	$counts_quantity  = rytkoset_theme_event_collects_quantity( $event_id );
	$registered_total = 0;

	foreach ( $registration_ids as $registration_id ) {
		$status = rytkoset_theme_get_event_registration_meta( $registration_id, 'status' );

		if ( '' === $status ) {
			$status = 'pending';
		}

		if ( ! in_array( $status, $active_statuses, true ) ) {
			continue;
		}

		$registered_total += $counts_quantity
			? max( 1, absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'quantity' ) ) )
			: 1;
	}

	return $registered_total;
}

/**
 * Returns how many people a single free registration brings.
 *
 * Events that do not collect a quantity always count as one person.
 *
 * @param int $registration_id Registration post ID.
 * @return int
 */
function rytkoset_theme_get_event_registration_participant_count( $registration_id ) {
	$registration_id = absint( $registration_id );

	if ( $registration_id <= 0 ) {
		return 0;
	}

	$event_id = absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'event_id' ) );

	if ( ! rytkoset_theme_event_collects_quantity( $event_id ) ) {
		return 1;
	}

	return max( 1, absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'quantity' ) ) );
}

/**
 * Counts the paid registration units ordered for an event.
 *
 * One ordered unit of the linked product is one participant, which matches
 * both the Tampere 2026 participant rows and the generic per-quantity rows in
 * `rytkoset_theme_get_event_paid_participants()`. A single indexed order-items
 * query is used on purpose: this runs during a WooCommerce order status
 * transition, where loading and hydrating every order is not acceptable.
 *
 * @param int $event_id Event post ID.
 * @return int|null Ordered units, or null when the count is unavailable.
 */
function rytkoset_theme_count_event_paid_registrations( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return 0;
	}

	/**
	 * Short-circuits the paid registration count.
	 *
	 * Returning a non-null value skips the database query entirely.
	 *
	 * @param int|null $count    Pre-resolved count, or null to run the query.
	 * @param int      $event_id Event post ID.
	 */
	$pre_count = apply_filters( 'rytkoset_theme_pre_event_paid_registration_count', null, $event_id );

	if ( null !== $pre_count ) {
		return max( 0, (int) $pre_count );
	}

	$product_id = absint( get_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), true ) );

	if ( $product_id <= 0 ) {
		return 0;
	}

	$statuses = rytkoset_theme_get_event_summary_active_order_statuses();

	if ( empty( $statuses ) ) {
		return null;
	}

	global $wpdb;

	if ( ! is_object( $wpdb ) ) {
		return null;
	}

	$order_items    = $wpdb->prefix . 'woocommerce_order_items';
	$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

	// Both HPOS (wc_orders.status) and the legacy post storage
	// (posts.post_status) store the `wc-` prefixed status slug.
	$prefixed_statuses = array_map(
		static function ( $status ) {
			return 'wc-' . $status;
		},
		$statuses
	);

	$status_placeholders = implode( ', ', array_fill( 0, count( $prefixed_statuses ), '%s' ) );

	if ( rytkoset_theme_event_summary_uses_hpos_orders() ) {
		$order_join = "INNER JOIN {$wpdb->prefix}wc_orders AS orders ON orders.id = items.order_id";
		$status_sql = "orders.status IN ( {$status_placeholders} )";
	} else {
		$order_join = "INNER JOIN {$wpdb->posts} AS orders ON orders.ID = items.order_id";
		$status_sql = "orders.post_status IN ( {$status_placeholders} )";
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and placeholder lists are built from trusted values; every value is passed through $wpdb->prepare().
	$sql = "SELECT SUM( quantity.meta_value )
		FROM {$order_items} AS items
		INNER JOIN {$order_itemmeta} AS quantity
			ON quantity.order_item_id = items.order_item_id AND quantity.meta_key = '_qty'
		{$order_join}
		WHERE items.order_item_type = 'line_item'
			AND {$status_sql}
			AND items.order_item_id IN (
				SELECT reference.order_item_id
				FROM {$order_itemmeta} AS reference
				WHERE reference.meta_key IN ( '_product_id', '_variation_id' )
					AND reference.meta_value = %d
			)";

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Intentional single indexed query; the participants admin aggregation is far too heavy for this path. $sql above is built from trusted table names plus %s/%d placeholders and every value goes through $wpdb->prepare(), which the sniff cannot follow across the variable.
	$total = $wpdb->get_var( $wpdb->prepare( $sql, array_merge( $prefixed_statuses, array( $product_id ) ) ) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( '' !== (string) $wpdb->last_error ) {
		return null;
	}

	return null === $total ? 0 : max( 0, (int) $total );
}

/**
 * Checks whether WooCommerce stores orders in the HPOS tables.
 *
 * @return bool
 */
function rytkoset_theme_event_summary_uses_hpos_orders() {
	if (
		! function_exists( 'wc_get_container' )
		|| ! class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
	) {
		return false;
	}

	$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );

	return is_object( $controller )
		&& method_exists( $controller, 'custom_orders_table_usage_is_enabled' )
		&& $controller->custom_orders_table_usage_is_enabled();
}

/**
 * Returns the remaining capacity of the event's linked product.
 *
 * Only meaningful when the product manages stock; otherwise the event has no
 * capacity limit the site knows about.
 *
 * @param int $event_id Event post ID.
 * @return int|null Remaining units, or null when capacity is not tracked.
 */
function rytkoset_theme_get_event_remaining_capacity( $event_id ) {
	$product = rytkoset_theme_get_event_linked_product( $event_id );

	if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
		return null;
	}

	if ( ! method_exists( $product, 'managing_stock' ) || ! $product->managing_stock() ) {
		return null;
	}

	$stock = $product->get_stock_quantity();

	return null === $stock ? null : (int) $stock;
}

/**
 * Returns the whole days left until a registration deadline.
 *
 * The deadline day itself is inclusive, so the deadline day returns 0 and a
 * passed deadline returns a negative number.
 *
 * @param string $deadline Deadline date (YYYY-MM-DD).
 * @return int|null Days left, or null when the deadline is unusable.
 */
function rytkoset_theme_get_event_registration_deadline_days_left( $deadline ) {
	$deadline = rytkoset_theme_normalize_event_registration_deadline_date( $deadline );

	if ( '' === $deadline ) {
		return null;
	}

	$deadline_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline, wp_timezone() );

	if ( ! $deadline_date instanceof DateTimeImmutable ) {
		return null;
	}

	$difference = current_datetime()->setTime( 0, 0, 0 )->diff( $deadline_date );

	return (int) $difference->days * ( $difference->invert ? -1 : 1 );
}

/**
 * Builds the registration situation summary shown in organizer notifications.
 *
 * @param int $event_id         Event post ID.
 * @param int $new_participants People brought by the registration being announced.
 * @return array{total:int|null,free:int,paid:int|null,new_participants:int,capacity_remaining:int|null,deadline:string,deadline_days_left:int|null}
 */
function rytkoset_theme_get_event_registration_summary( $event_id, $new_participants = 0 ) {
	$event_id = absint( $event_id );

	$free_count = rytkoset_theme_count_event_free_registrations( $event_id );
	$paid_count = rytkoset_theme_count_event_paid_registrations( $event_id );
	$deadline   = rytkoset_theme_get_event_registration_deadline_raw( $event_id );

	return array(
		'total'              => null === $paid_count ? null : $free_count + $paid_count,
		'free'               => $free_count,
		'paid'               => $paid_count,
		'new_participants'   => max( 0, (int) $new_participants ),
		'capacity_remaining' => rytkoset_theme_get_event_remaining_capacity( $event_id ),
		'deadline'           => (string) $deadline,
		'deadline_days_left' => rytkoset_theme_get_event_registration_deadline_days_left( $deadline ),
	);
}

/**
 * Formats a registration summary into plain-text email lines.
 *
 * Pure: takes the summary array and returns the lines, so both notification
 * paths render an identical block. Unknown values are omitted rather than
 * printed as a guess.
 *
 * @param array $summary Summary array from rytkoset_theme_get_event_registration_summary().
 * @return string[] Lines, or an empty array when there is nothing to report.
 */
function rytkoset_theme_format_event_registration_summary_lines( $summary ) {
	if ( ! is_array( $summary ) ) {
		return array();
	}

	$lines = array();

	if ( isset( $summary['new_participants'] ) && (int) $summary['new_participants'] > 0 ) {
		$lines[] = sprintf(
			/* translators: %d: number of people in the new registration. */
			__( 'Tämän ilmoittautumisen henkilömäärä: %d', 'rytkoset-theme' ),
			(int) $summary['new_participants']
		);
	}

	if ( isset( $summary['total'] ) && null !== $summary['total'] ) {
		$lines[] = sprintf(
			/* translators: %d: total number of registered people. */
			__( 'Ilmoittautuneita yhteensä: %d', 'rytkoset-theme' ),
			(int) $summary['total']
		);
	}

	if ( isset( $summary['capacity_remaining'] ) && null !== $summary['capacity_remaining'] ) {
		$lines[] = sprintf(
			/* translators: %d: remaining capacity of the linked product. */
			__( 'Paikkoja jäljellä: %d', 'rytkoset-theme' ),
			(int) $summary['capacity_remaining']
		);
	}

	$deadline_line = rytkoset_theme_format_event_registration_summary_deadline_line( $summary );

	if ( '' !== $deadline_line ) {
		$lines[] = $deadline_line;
	}

	if ( empty( $lines ) ) {
		return array();
	}

	array_unshift( $lines, __( 'Ilmoittautumistilanne:', 'rytkoset-theme' ) );

	return $lines;
}

/**
 * Formats the deadline line of a registration summary.
 *
 * @param array $summary Summary array.
 * @return string Line text, or an empty string when there is no usable deadline.
 */
function rytkoset_theme_format_event_registration_summary_deadline_line( $summary ) {
	$deadline = isset( $summary['deadline'] ) ? (string) $summary['deadline'] : '';

	if ( '' === $deadline ) {
		return '';
	}

	$deadline_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline, wp_timezone() );

	if ( ! $deadline_date instanceof DateTimeImmutable ) {
		return '';
	}

	$line = sprintf(
		/* translators: %s: registration deadline date. */
		__( 'Ilmoittautuminen päättyy: %s', 'rytkoset-theme' ),
		wp_date( get_option( 'date_format' ), $deadline_date->getTimestamp() )
	);

	$days_left = isset( $summary['deadline_days_left'] ) ? $summary['deadline_days_left'] : null;

	if ( null === $days_left ) {
		return $line;
	}

	$days_left = (int) $days_left;

	if ( $days_left < 0 ) {
		return $line . ' ' . __( '(määräaika on ohitettu)', 'rytkoset-theme' );
	}

	if ( 0 === $days_left ) {
		return $line . ' ' . __( '(viimeinen ilmoittautumispäivä)', 'rytkoset-theme' );
	}

	if ( 1 === $days_left ) {
		return $line . ' ' . __( '(1 päivä jäljellä)', 'rytkoset-theme' );
	}

	return $line . ' ' . sprintf(
		/* translators: %d: whole days left until the registration deadline. */
		__( '(%d päivää jäljellä)', 'rytkoset-theme' ),
		$days_left
	);
}
