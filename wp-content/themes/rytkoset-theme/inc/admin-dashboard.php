<?php
/**
 * Dashboard widgets and per-role visibility (#694, part of epic #690).
 *
 * Adds "Pikatoiminnot" (the most common tasks as large buttons, filtered by
 * the user's capabilities) and "Tulevat tapahtumat" (upcoming events with the
 * registered headcount), and hides technical WordPress and plugin boxes from
 * everyone except administrators.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the WooCommerce orders screen URL, or an empty string without
 * WooCommerce. HPOS stores use admin.php?page=wc-orders, legacy stores the
 * shop_order list.
 *
 * @return string
 */
function rytkoset_theme_get_dashboard_orders_url() {
	if ( ! function_exists( 'WC' ) ) {
		return '';
	}

	$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	return admin_url( $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' );
}

/**
 * Tells whether the current user can create posts of a registered post type.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function rytkoset_theme_dashboard_user_can_create( $post_type ) {
	$object = get_post_type_object( $post_type );

	return $object && current_user_can( $object->cap->create_posts );
}

/**
 * Returns the quick actions the current user may use, in display order.
 *
 * A button is shown only when the user has the capability its target needs,
 * so an event organizer sees the event tasks and an administrator all six.
 *
 * @return array<int, array{key: string, label: string, url: string, icon: string}>
 */
function rytkoset_theme_get_dashboard_quick_actions() {
	$can_manage_registrations = current_user_can( 'edit_others_event_registrations' );
	$orders_url               = rytkoset_theme_get_dashboard_orders_url();

	$candidates = array(
		array(
			'key'     => 'add_event',
			'label'   => __( 'Lisää tapahtuma', 'rytkoset-theme' ),
			'url'     => admin_url( 'post-new.php?post_type=rytkoset_event' ),
			'icon'    => 'dashicons-calendar-alt',
			'allowed' => rytkoset_theme_dashboard_user_can_create( 'rytkoset_event' ),
		),
		array(
			'key'     => 'participants',
			'label'   => __( 'Osallistujat', 'rytkoset-theme' ),
			'url'     => admin_url( 'edit.php?post_type=rytkoset_event&page=rytkoset-event-participants' ),
			'icon'    => 'dashicons-groups',
			'allowed' => $can_manage_registrations,
		),
		array(
			'key'     => 'messaging',
			'label'   => __( 'Viestintä', 'rytkoset-theme' ),
			'url'     => admin_url( 'edit.php?post_type=rytkoset_event&page=rytkoset-event-messaging' ),
			'icon'    => 'dashicons-email-alt',
			'allowed' => $can_manage_registrations,
		),
		array(
			'key'     => 'add_album',
			'label'   => __( 'Lisää albumi', 'rytkoset-theme' ),
			'url'     => admin_url( 'post-new.php?post_type=gallery_album' ),
			'icon'    => 'dashicons-format-gallery',
			'allowed' => rytkoset_theme_dashboard_user_can_create( 'gallery_album' ),
		),
		array(
			'key'     => 'new_post',
			'label'   => __( 'Uusi blogikirjoitus', 'rytkoset-theme' ),
			'url'     => admin_url( 'post-new.php' ),
			'icon'    => 'dashicons-edit',
			'allowed' => rytkoset_theme_dashboard_user_can_create( 'post' ),
		),
		array(
			'key'     => 'orders',
			'label'   => __( 'Tilaukset', 'rytkoset-theme' ),
			'url'     => $orders_url,
			'icon'    => 'dashicons-cart',
			'allowed' => '' !== $orders_url && current_user_can( 'edit_shop_orders' ),
		),
	);

	$actions = array();

	foreach ( $candidates as $candidate ) {
		if ( $candidate['allowed'] ) {
			unset( $candidate['allowed'] );
			$actions[] = $candidate;
		}
	}

	/**
	 * Filters the dashboard quick actions (#694).
	 *
	 * @param array $actions Actions with key, label, url and icon (a Dashicons class).
	 */
	return (array) apply_filters( 'rytkoset_theme_dashboard_quick_actions', $actions );
}

/**
 * Returns up to $limit upcoming published events with the registered headcount.
 *
 * Uses the same "not past the end of the event day" rule as the rest of the
 * theme. The headcount comes from the cheap registration summary count; null
 * means it is unknown.
 *
 * @param int $limit Maximum number of events.
 * @return array<int, array{id: int, title: string, date: string, registered: int|null}>
 */
function rytkoset_theme_get_dashboard_upcoming_events( $limit = 5 ) {
	$events = array();
	$ids    = get_posts(
		array(
			'post_type'        => 'rytkoset_event',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);

	// fields => ids skips the meta cache; prime it so the loop does not query per event.
	if ( ! empty( $ids ) ) {
		update_meta_cache( 'post', array_map( 'intval', (array) $ids ) );
	}

	foreach ( (array) $ids as $event_id ) {
		$event_id = (int) $event_id;
		$date     = rytkoset_theme_get_event_date_raw( $event_id );

		if ( '' === $date || rytkoset_theme_is_event_date_passed( $event_id ) ) {
			continue;
		}

		$events[] = array(
			'id'   => $event_id,
			'sort' => $date . ' ' . rytkoset_theme_get_event_time_raw( $event_id, 'start_time' ),
		);
	}

	usort(
		$events,
		static function ( $a, $b ) {
			$order = strcmp( $a['sort'], $b['sort'] );

			return 0 !== $order ? $order : $a['id'] <=> $b['id'];
		}
	);

	$rows = array();

	foreach ( array_slice( $events, 0, max( 1, (int) $limit ) ) as $event ) {
		$summary = rytkoset_theme_get_event_registration_summary( $event['id'], 0 );

		$rows[] = array(
			'id'         => $event['id'],
			'title'      => get_the_title( $event['id'] ),
			'date'       => rytkoset_theme_get_event_date_display( $event['id'] ),
			'registered' => isset( $summary['total'] ) ? (int) $summary['total'] : null,
		);
	}

	return $rows;
}

/**
 * Registers the theme's own dashboard widgets.
 *
 * @return void
 */
function rytkoset_theme_register_dashboard_widgets() {
	if ( array() !== rytkoset_theme_get_dashboard_quick_actions() ) {
		wp_add_dashboard_widget( 'rytkoset_quick_actions', __( 'Pikatoiminnot', 'rytkoset-theme' ), 'rytkoset_theme_render_dashboard_quick_actions', null, null, 'normal', 'high' );
	}

	if ( current_user_can( 'edit_others_event_registrations' ) ) {
		wp_add_dashboard_widget( 'rytkoset_upcoming_events', __( 'Tulevat tapahtumat', 'rytkoset-theme' ), 'rytkoset_theme_render_dashboard_upcoming_events', null, null, 'side', 'high' );
	}
}
add_action( 'wp_dashboard_setup', 'rytkoset_theme_register_dashboard_widgets' );

/**
 * Renders the "Pikatoiminnot" widget.
 *
 * @return void
 */
function rytkoset_theme_render_dashboard_quick_actions() {
	echo '<ul class="rytkoset-quick-actions">';

	foreach ( rytkoset_theme_get_dashboard_quick_actions() as $action ) {
		printf(
			'<li><a class="rytkoset-quick-actions__link" href="%1$s"><span class="dashicons %2$s" aria-hidden="true"></span><span>%3$s</span></a></li>',
			esc_url( $action['url'] ),
			esc_attr( $action['icon'] ),
			esc_html( $action['label'] )
		);
	}

	echo '</ul>';
}

/**
 * Renders the "Tulevat tapahtumat" widget.
 *
 * @return void
 */
function rytkoset_theme_render_dashboard_upcoming_events() {
	$events = rytkoset_theme_get_dashboard_upcoming_events();

	if ( array() === $events ) {
		echo '<p>' . esc_html__( 'Ei tulevia tapahtumia.', 'rytkoset-theme' ) . '</p>';

		if ( rytkoset_theme_dashboard_user_can_create( 'rytkoset_event' ) ) {
			echo '<p><a href="' . esc_url( admin_url( 'post-new.php?post_type=rytkoset_event' ) ) . '">' . esc_html__( 'Lisää tapahtuma', 'rytkoset-theme' ) . '</a></p>';
		}

		return;
	}

	echo '<ul class="rytkoset-upcoming-events">';

	foreach ( $events as $event ) {
		$participants_url = add_query_arg(
			array(
				'post_type' => 'rytkoset_event',
				'page'      => 'rytkoset-event-participants',
				'event_id'  => $event['id'],
			),
			admin_url( 'edit.php' )
		);

		$registered = null === $event['registered']
			? __( 'Ilmoittautuneita: –', 'rytkoset-theme' )
			: sprintf(
				/* translators: %d: registered people */
				_n( '%d ilmoittautunut', '%d ilmoittautunutta', $event['registered'], 'rytkoset-theme' ),
				$event['registered']
			);

		echo '<li>';
		echo '<a class="rytkoset-upcoming-events__title" href="' . esc_url( $participants_url ) . '">' . esc_html( $event['title'] ) . '</a>';
		echo '<span class="ra-sub">' . esc_html( $event['date'] ) . ' · ' . esc_html( $registered ) . '</span>';
		echo '</li>';
	}

	echo '</ul>';
}

/**
 * Returns the IDs of the technical dashboard boxes hidden from non-administrators.
 *
 * Activity and WooCommerce's own status box stay, because they concern the
 * association's content and orders.
 *
 * @return string[]
 */
function rytkoset_theme_get_dashboard_technical_widget_ids() {
	$ids = array( 'dashboard_site_health', 'dashboard_php_nag', 'dashboard_browser_nag', 'dashboard_primary', 'rank_math_dashboard_widget' );

	/**
	 * Filters the technical dashboard boxes hidden from non-administrators (#694).
	 *
	 * @param string[] $ids Dashboard widget IDs.
	 */
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'rytkoset_theme_dashboard_technical_widgets', $ids ) ) ) );
}

/**
 * Hides the technical boxes from users who cannot manage options. Runs late
 * so plugin boxes registered on the same hook already exist.
 *
 * @return void
 */
function rytkoset_theme_remove_dashboard_technical_widgets() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	foreach ( rytkoset_theme_get_dashboard_technical_widget_ids() as $widget_id ) {
		foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
			remove_meta_box( $widget_id, 'dashboard', $context );
		}
	}
}
add_action( 'wp_dashboard_setup', 'rytkoset_theme_remove_dashboard_technical_widgets', 999 );

/**
 * Tells whether an admin_notices callback is a plugin setup notice that only
 * store administrators can act on (#694).
 *
 * Only explicitly identified callbacks are listed: Paytrail's test-mode and
 * currency notice (Gateway::admin_notices), which asks to change payment
 * settings that an event organizer or editor cannot open.
 *
 * @param mixed $callback Hook callback.
 * @return bool
 */
function rytkoset_theme_is_store_setup_notice_callback( $callback ) {
	return is_array( $callback )
		&& isset( $callback[0], $callback[1] )
		&& is_object( $callback[0] )
		&& 'Paytrail\WooCommercePaymentGateway\Gateway' === get_class( $callback[0] )
		&& 'admin_notices' === $callback[1];
}

/**
 * Removes the identified store setup notices for users who cannot manage
 * WooCommerce settings. Runs before the notices print (priority 10).
 *
 * @return void
 */
function rytkoset_theme_remove_store_setup_notices() {
	global $wp_filter;

	if ( current_user_can( 'manage_woocommerce' ) || ! isset( $wp_filter['admin_notices'] ) || ! is_object( $wp_filter['admin_notices'] ) ) {
		return;
	}

	foreach ( (array) $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
		foreach ( (array) $callbacks as $callback ) {
			if ( rytkoset_theme_is_store_setup_notice_callback( $callback['function'] ?? null ) ) {
				remove_action( 'admin_notices', $callback['function'], $priority );
			}
		}
	}
}
add_action( 'admin_notices', 'rytkoset_theme_remove_store_setup_notices', 0 );
