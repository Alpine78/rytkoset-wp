<?php
/**
 * Tapahtumien roolit ja oikeudet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the event role capability schema version.
 *
 * @return string
 */
function rytkoset_theme_get_event_roles_version() {
	return '1';
}

/**
 * Returns the event CPT capability type.
 *
 * @return array
 */
function rytkoset_theme_get_event_capability_type() {
	return array( 'event', 'events' );
}

/**
 * Returns the event registration CPT capability type.
 *
 * @return array
 */
function rytkoset_theme_get_event_registration_capability_type() {
	return array( 'event_registration', 'event_registrations' );
}

/**
 * Returns primitive capabilities for an event-related CPT.
 *
 * @param string $plural Plural capability base.
 * @return array
 */
function rytkoset_theme_get_event_post_type_capabilities( $plural ) {
	return array(
		'edit_' . $plural,
		'edit_others_' . $plural,
		'publish_' . $plural,
		'read_private_' . $plural,
		'delete_' . $plural,
		'delete_private_' . $plural,
		'delete_published_' . $plural,
		'delete_others_' . $plural,
		'edit_private_' . $plural,
		'edit_published_' . $plural,
	);
}

/**
 * Returns capabilities granted to Event Organizers.
 *
 * @return array
 */
function rytkoset_theme_get_event_organizer_capabilities() {
	return array_unique(
		array_merge(
			array(
				'read',
				'upload_files',
			),
			rytkoset_theme_get_event_post_type_capabilities( 'events' ),
			rytkoset_theme_get_event_post_type_capabilities( 'event_registrations' )
		)
	);
}

/**
 * Returns capabilities that Event Organizers must not receive.
 *
 * @return array
 */
function rytkoset_theme_get_event_organizer_forbidden_capabilities() {
	return array(
		'manage_options',
		'manage_woocommerce',
		'edit_products',
		'publish_products',
		'delete_products',
		'edit_shop_orders',
		'edit_others_shop_orders',
	);
}

/**
 * Checks whether a role has every expected capability.
 *
 * @param WP_Role|null $role         Role object.
 * @param array        $capabilities Capability names.
 * @return bool
 */
function rytkoset_theme_role_has_capabilities( $role, $capabilities ) {
	if ( ! $role instanceof WP_Role ) {
		return false;
	}

	foreach ( $capabilities as $capability ) {
		if ( ! $role->has_cap( $capability ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Checks whether a role lacks every listed capability.
 *
 * @param WP_Role|null $role         Role object.
 * @param array        $capabilities Capability names.
 * @return bool
 */
function rytkoset_theme_role_lacks_capabilities( $role, $capabilities ) {
	if ( ! $role instanceof WP_Role ) {
		return false;
	}

	foreach ( $capabilities as $capability ) {
		if ( $role->has_cap( $capability ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Creates and updates event-specific roles and capabilities.
 */
function rytkoset_theme_sync_event_roles() {
	$version                = rytkoset_theme_get_event_roles_version();
	$organizer_capabilities = rytkoset_theme_get_event_organizer_capabilities();
	$forbidden_capabilities = rytkoset_theme_get_event_organizer_forbidden_capabilities();
	$event_organizer        = get_role( 'event_organizer' );
	$administrator          = get_role( 'administrator' );

	if (
		get_option( 'rytkoset_event_roles_version' ) === $version
		&& rytkoset_theme_role_has_capabilities( $event_organizer, $organizer_capabilities )
		&& rytkoset_theme_role_lacks_capabilities( $event_organizer, $forbidden_capabilities )
		&& rytkoset_theme_role_has_capabilities( $administrator, $organizer_capabilities )
	) {
		return;
	}

	if ( ! $event_organizer ) {
		add_role(
			'event_organizer',
			__( 'Event Organizer', 'rytkoset-theme' ),
			array()
		);

		$event_organizer = get_role( 'event_organizer' );
	}

	if ( $event_organizer ) {
		foreach ( $organizer_capabilities as $capability ) {
			$event_organizer->add_cap( $capability );
		}

		foreach ( $forbidden_capabilities as $capability ) {
			$event_organizer->remove_cap( $capability );
		}
	}

	if ( $administrator ) {
		foreach ( $organizer_capabilities as $capability ) {
			$administrator->add_cap( $capability );
		}
	}

	update_option( 'rytkoset_event_roles_version', $version );
}
add_action( 'init', 'rytkoset_theme_sync_event_roles', 20 );

/**
 * Allows Event Organizers into wp-admin without granting generic post or WooCommerce caps.
 *
 * WooCommerce redirects users without edit_posts, manage_woocommerce, or
 * view_admin_dashboard away from wp-admin. Event Organizers intentionally use
 * custom CPT capabilities instead of edit_posts.
 *
 * @param bool $prevent_access Whether WooCommerce should block admin access.
 * @return bool
 */
function rytkoset_theme_allow_event_organizer_admin_access( $prevent_access ) {
	if (
		current_user_can( 'edit_events' )
		|| current_user_can( 'edit_event_registrations' )
	) {
		return false;
	}

	return $prevent_access;
}
add_filter( 'woocommerce_prevent_admin_access', 'rytkoset_theme_allow_event_organizer_admin_access' );
