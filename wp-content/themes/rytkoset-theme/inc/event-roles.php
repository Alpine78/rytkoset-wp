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
	return '2';
}

/**
 * Returns the event CPT capability type.
 *
 * @return array
 */
function rytkoset_theme_get_event_capability_type() {
	return array( 'rytkoset_event', 'rytkoset_events' );
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
			rytkoset_theme_get_event_post_type_capabilities( 'rytkoset_events' ),
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
	$stored_version         = get_option( 'rytkoset_event_roles_version' );

	if (
		$stored_version === $version
		&& rytkoset_theme_role_has_capabilities( $event_organizer, $organizer_capabilities )
		&& rytkoset_theme_role_lacks_capabilities( $event_organizer, $forbidden_capabilities )
		&& rytkoset_theme_role_has_capabilities( $administrator, $organizer_capabilities )
	) {
		return;
	}

	// Migration v1 -> v2: remove old 'events' plural caps that were renamed to 'rytkoset_events'.
	$legacy_capabilities = rytkoset_theme_get_event_post_type_capabilities( 'events' );

	if ( ! $event_organizer ) {
		add_role(
			'event_organizer',
			__( 'Event Organizer', 'rytkoset-theme' ),
			array()
		);

		$event_organizer = get_role( 'event_organizer' );
	}

	if ( $event_organizer ) {
		foreach ( $legacy_capabilities as $capability ) {
			$event_organizer->remove_cap( $capability );
		}

		foreach ( $organizer_capabilities as $capability ) {
			$event_organizer->add_cap( $capability );
		}

		foreach ( $forbidden_capabilities as $capability ) {
			$event_organizer->remove_cap( $capability );
		}
	}

	if ( $administrator ) {
		foreach ( $legacy_capabilities as $capability ) {
			$administrator->remove_cap( $capability );
		}

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
		current_user_can( 'edit_rytkoset_events' )
		|| current_user_can( 'edit_event_registrations' )
	) {
		return false;
	}

	return $prevent_access;
}
add_filter( 'woocommerce_prevent_admin_access', 'rytkoset_theme_allow_event_organizer_admin_access' );

/**
 * Returns the nonce action for the Event Organizer additional-role profile section.
 *
 * @param int $user_id The edited user ID.
 * @return string
 */
function rytkoset_theme_get_event_organizer_role_nonce_action( $user_id ) {
	return 'rytkoset_save_event_organizer_role_' . (int) $user_id;
}

/**
 * Renders the "Tapahtumien järjestäjä" additional-role checkbox on a user profile screen.
 *
 * The checkbox toggles the event_organizer role as a secondary role so a user can be, for
 * example, Päätoimittaja and Event Organizer at the same time. It leaves the WordPress primary
 * role (the normal role dropdown) untouched. Shown only to users who can edit users.
 *
 * @param WP_User $user The user being edited.
 * @return void
 */
function rytkoset_theme_render_user_event_organizer_field( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! $user instanceof WP_User ) {
		return;
	}

	$is_organizer = in_array( 'event_organizer', (array) $user->roles, true );

	wp_nonce_field(
		rytkoset_theme_get_event_organizer_role_nonce_action( $user->ID ),
		'rytkoset_event_organizer_role_nonce'
	);
	?>
	<h2><?php esc_html_e( 'Tapahtumien järjestäjä', 'rytkoset-theme' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Tapahtumaoikeudet', 'rytkoset-theme' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="rytkoset_event_organizer_role" value="1" <?php checked( $is_organizer ); ?> />
					<?php esc_html_e( 'Tapahtumien järjestäjä (Event Organizer)', 'rytkoset-theme' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Antaa käyttäjälle oikeudet hallita tapahtumia ja ilmoittautumisia muuttamatta käyttäjän pääroolia. Voidaan yhdistää mihin tahansa päärooliin, esimerkiksi Päätoimittajaan.', 'rytkoset-theme' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'rytkoset_theme_render_user_event_organizer_field' );
add_action( 'edit_user_profile', 'rytkoset_theme_render_user_event_organizer_field' );

/**
 * Saves the Event Organizer additional-role checkbox from a user profile screen.
 *
 * Adds or removes only the event_organizer role via WP_User::add_role()/remove_role(), so the
 * user's other roles (their primary role, e.g. Päätoimittaja) stay intact.
 *
 * Hooked to `profile_update` rather than `edit_user_profile_update`: when an admin edits another
 * user, WordPress processes the primary-role dropdown in edit_user() → WP_User::set_role(), which
 * replaces the user's entire role set. profile_update fires after that inside wp_update_user(), so
 * the secondary role we add here is not wiped by the dropdown.
 *
 * @param int $user_id The edited user ID.
 * @return void
 */
function rytkoset_theme_save_user_event_organizer_field( $user_id ) {
	$user_id = (int) $user_id;

	if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_event_organizer_role_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rytkoset_event_organizer_role_nonce'] ) ),
			rytkoset_theme_get_event_organizer_role_nonce_action( $user_id )
		)
	) {
		return;
	}

	$user = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return;
	}

	$should_be_organizer = ! empty( $_POST['rytkoset_event_organizer_role'] );
	$is_organizer        = in_array( 'event_organizer', (array) $user->roles, true );

	if ( $should_be_organizer && ! $is_organizer ) {
		$user->add_role( 'event_organizer' );
	} elseif ( ! $should_be_organizer && $is_organizer ) {
		$user->remove_role( 'event_organizer' );
	}
}
add_action( 'profile_update', 'rytkoset_theme_save_user_event_organizer_field' );
