<?php
/**
 * Synchronization helpers for the members-only AcyMailing list (#535).
 *
 * The members list is separate from the consent-based general newsletter.
 * These helpers deliberately target one explicitly configured list ID and
 * preserve both list-level opt-outs and subscriber-level deactivations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes the configured members-only AcyMailing list ID.
 *
 * The general newsletter lists are rejected so a configuration mistake cannot
 * turn the voluntary newsletter into an automatically synchronized member list.
 *
 * @param mixed $value Raw Customizer value.
 * @return int
 */
function rytkoset_theme_sanitize_member_newsletter_list_id( $value ) {
	$list_id = absint( $value );

	if ( 0 === $list_id ) {
		return 0;
	}

	return in_array( $list_id, rytkoset_theme_get_newsletter_list_ids(), true ) ? 0 : $list_id;
}

/**
 * Returns the separately configured members-only AcyMailing list ID.
 *
 * @return int
 */
function rytkoset_theme_get_member_newsletter_list_id() {
	return rytkoset_theme_sanitize_member_newsletter_list_id(
		get_theme_mod( 'rytkoset_theme_member_newsletter_list_id', 0 )
	);
}

/**
 * Adds the members-only list setting to the existing newsletter section.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function rytkoset_theme_register_member_newsletter_customizer( $wp_customize ) {
	$wp_customize->add_setting(
		'rytkoset_theme_member_newsletter_list_id',
		array(
			'default'           => 0,
			'sanitize_callback' => 'rytkoset_theme_sanitize_member_newsletter_list_id',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		'rytkoset_theme_member_newsletter_list_id',
		array(
			'label'       => __( 'Jäsenviestinnän AcyMailing-listan ID', 'rytkoset-theme' ),
			'description' => __( 'Erillinen jäsenviestinnän lista. Yleisen uutiskirjelomakkeen listaa ei hyväksytä tähän.', 'rytkoset-theme' ),
			'section'     => 'rytkoset_theme_newsletter',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 1,
				'step' => 1,
			),
		)
	);
}
add_action( 'customize_register', 'rytkoset_theme_register_member_newsletter_customizer', 20 );

/**
 * Returns whether a WordPress user belongs on the members-only list.
 *
 * Only account-holding users with a valid email and an effective active
 * membership qualify. Effective membership covers both an active own source
 * and an active linked family source. Pending email-only records do not.
 *
 * @param int $user_id WordPress user ID.
 * @return bool
 */
function rytkoset_theme_user_is_member_newsletter_recipient( $user_id ) {
	$user_id = absint( $user_id );
	$user    = get_userdata( $user_id );

	return $user instanceof WP_User
		&& is_email( $user->user_email )
		&& rytkoset_theme_user_is_active_member( $user_id );
}

/**
 * Normalizes the relevant AcyMailing subscriber and list state.
 *
 * @param mixed $state Raw state array or object.
 * @return array{subscriber_id:int,active:int,confirmed:int,list_status:int|null}
 */
function rytkoset_theme_normalize_member_newsletter_state( $state ) {
	$state = is_object( $state ) ? get_object_vars( $state ) : (array) $state;

	return array(
		'subscriber_id' => absint( $state['subscriber_id'] ?? 0 ),
		'active'        => isset( $state['active'] ) ? (int) $state['active'] : 1,
		'confirmed'     => isset( $state['confirmed'] ) ? (int) $state['confirmed'] : 0,
		'list_status'   => isset( $state['list_status'] ) ? (int) $state['list_status'] : null,
	);
}

/**
 * Reads one email address's state for the configured member list.
 *
 * @param string $email   Email address.
 * @param int    $list_id AcyMailing list ID.
 * @return array{subscriber_id:int,active:int,confirmed:int,list_status:int|null}
 */
function rytkoset_theme_get_member_newsletter_state( $email, $list_id ) {
	$email   = strtolower( sanitize_email( $email ) );
	$list_id = absint( $list_id );

	if ( '' === $email || ! is_email( $email ) || 0 === $list_id ) {
		return rytkoset_theme_normalize_member_newsletter_state( array() );
	}

	global $wpdb;

	$user_table = $wpdb->prefix . 'acym_user';
	$list_table = $wpdb->prefix . 'acym_user_has_list';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use the WordPress DB prefix; values use placeholders.
	$state = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT acym_user.id AS subscriber_id,
					acym_user.active,
					acym_user.confirmed,
					acym_list.status AS list_status
			 FROM {$user_table} AS acym_user
			 LEFT JOIN {$list_table} AS acym_list
				ON acym_list.user_id = acym_user.id
				AND acym_list.list_id = %d
			 WHERE acym_user.email = %s
			 LIMIT 1",
			$list_id,
			$email
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return rytkoset_theme_normalize_member_newsletter_state( $state );
}

/**
 * Decides the idempotent operation for one member-list state.
 *
 * A list-level status of 0 is an intentional opt-out and subscriber active=0
 * is a global block. Neither may be overridden by automatic synchronization.
 *
 * @param array<string, mixed> $state             Normalized or raw AcyMailing state.
 * @param bool                 $should_be_member Whether the email currently qualifies.
 * @return string add|remove|none|protected_opt_out|protected_inactive
 */
function rytkoset_theme_get_member_newsletter_sync_action( $state, $should_be_member ) {
	$state = rytkoset_theme_normalize_member_newsletter_state( $state );

	if ( $should_be_member ) {
		if ( $state['subscriber_id'] > 0 && 0 === $state['active'] ) {
			return 'protected_inactive';
		}

		if ( 0 === $state['list_status'] ) {
			return 'protected_opt_out';
		}

		return 1 === $state['list_status'] ? 'none' : 'add';
	}

	return 1 === $state['list_status'] ? 'remove' : 'none';
}

/**
 * Synchronizes one email address to or from the members-only list.
 *
 * Adding and removing are silent: AcyMailing triggers, confirmation messages,
 * welcome messages and unsubscribe messages are disabled. Expiry removes only
 * the list relation, leaving the subscriber and all unrelated lists untouched.
 *
 * @param string $email             Email address.
 * @param bool   $should_be_member Whether the email currently qualifies.
 * @param int    $cms_user_id       Optional WordPress user ID.
 * @return string|WP_Error Result code or error.
 */
function rytkoset_theme_sync_member_newsletter_email( $email, $should_be_member, $cms_user_id = 0 ) {
	$email       = strtolower( sanitize_email( $email ) );
	$cms_user_id = absint( $cms_user_id );
	$list_id     = rytkoset_theme_get_member_newsletter_list_id();

	if ( '' === $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', __( 'Jäsenviestinnän synkronointi ohitettiin virheellisen sähköpostiosoitteen takia.', 'rytkoset-theme' ) );
	}

	if ( 0 === $list_id ) {
		return new WP_Error( 'missing_member_newsletter_list', __( 'Jäsenviestinnän AcyMailing-listaa ei ole määritetty.', 'rytkoset-theme' ) );
	}

	$state  = rytkoset_theme_get_member_newsletter_state( $email, $list_id );
	$action = rytkoset_theme_get_member_newsletter_sync_action( $state, (bool) $should_be_member );

	if ( in_array( $action, array( 'none', 'protected_opt_out', 'protected_inactive' ), true ) ) {
		return $action;
	}

	if ( ! class_exists( '\\AcyMailing\\Classes\\UserClass' ) ) {
		return new WP_Error( 'acymailing_missing', __( 'AcyMailing ei ole käytettävissä.', 'rytkoset-theme' ) );
	}

	$user_class                       = new \AcyMailing\Classes\UserClass();
	$user_class->checkVisitor         = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- AcyMailing public API property.
	$user_class->triggers             = false;
	$user_class->sendConf             = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- AcyMailing public API property.
	$user_class->sendWelcomeEmail     = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- AcyMailing public API property.
	$user_class->sendUnsubscribeEmail = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- AcyMailing public API property.

	$subscriber_id = $state['subscriber_id'];

	if ( 'add' === $action ) {
		if ( 0 === $subscriber_id ) {
			$subscriber         = new stdClass();
			$subscriber->email  = $email;
			$subscriber->source = 'member_sync';

			if ( $cms_user_id > 0 ) {
				$subscriber->cms_id = $cms_user_id;
			}

			$subscriber_id = absint( $user_class->save( $subscriber ) );

			if ( 0 === $subscriber_id ) {
				return new WP_Error( 'acymailing_save_failed', rytkoset_theme_get_newsletter_error_message( $user_class->errors, __( 'Jäsenviestinnän tilaajan tallennus epäonnistui.', 'rytkoset-theme' ) ) );
			}
		}

		$subscribed = $user_class->subscribe( array( $subscriber_id ), array( $list_id ), false );

		if ( ! $subscribed ) {
			return new WP_Error( 'acymailing_subscribe_failed', rytkoset_theme_get_newsletter_error_message( $user_class->errors, __( 'Jäsenviestinnän listalle lisääminen epäonnistui.', 'rytkoset-theme' ) ) );
		}

		return 'added';
	}

	$user_class->removeSubscription( array( $subscriber_id ), array( $list_id ) );

	return 'removed';
}

/**
 * Synchronizes one WordPress user's current effective membership state.
 *
 * @param int $user_id WordPress user ID.
 * @return string|WP_Error Result code or error.
 */
function rytkoset_theme_sync_member_newsletter_user( $user_id ) {
	$user_id = absint( $user_id );
	$user    = get_userdata( $user_id );

	if ( ! $user instanceof WP_User ) {
		return new WP_Error( 'member_user_missing', __( 'Jäsenviestinnän synkronoinnin käyttäjää ei löytynyt.', 'rytkoset-theme' ) );
	}

	return rytkoset_theme_sync_member_newsletter_email(
		$user->user_email,
		rytkoset_theme_user_is_member_newsletter_recipient( $user_id ),
		$user_id
	);
}

/**
 * Returns users affected by a membership-meta change.
 *
 * A primary account's own membership changes also affect every currently
 * linked family user. Reverse-link changes are handled on the linked user.
 *
 * @param int    $user_id  User whose meta changed.
 * @param string $meta_key Changed user-meta key.
 * @return int[]
 */
function rytkoset_theme_get_member_newsletter_affected_user_ids( $user_id, $meta_key ) {
	$user_id  = absint( $user_id );
	$meta_key = (string) $meta_key;
	$user_ids = array( $user_id );
	$own_keys = array(
		rytkoset_theme_get_user_membership_type_meta_key(),
		rytkoset_theme_get_user_membership_period_meta_key(),
		rytkoset_theme_get_user_membership_expires_meta_key(),
		rytkoset_theme_get_family_members_meta_key(),
	);

	if ( in_array( $meta_key, $own_keys, true ) ) {
		$user_ids = array_merge(
			$user_ids,
			rytkoset_theme_get_active_linked_family_user_ids(
				rytkoset_theme_get_family_members( $user_id )
			)
		);
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
}

/**
 * Reacts to membership-related user-meta changes.
 *
 * @param int    $meta_id    User-meta row ID.
 * @param int    $user_id    WordPress user ID.
 * @param string $meta_key   Changed user-meta key.
 * @param mixed  $meta_value Changed value.
 * @return void
 */
function rytkoset_theme_sync_member_newsletter_on_user_meta_change( $meta_id, $user_id, $meta_key, $meta_value ) {
	$membership_keys = array(
		rytkoset_theme_get_user_membership_type_meta_key(),
		rytkoset_theme_get_user_membership_period_meta_key(),
		rytkoset_theme_get_user_membership_expires_meta_key(),
		rytkoset_theme_get_family_members_meta_key(),
		rytkoset_theme_get_family_primary_user_meta_key(),
	);

	if ( ! in_array( (string) $meta_key, $membership_keys, true ) ) {
		return;
	}

	foreach ( rytkoset_theme_get_member_newsletter_affected_user_ids( $user_id, $meta_key ) as $affected_user_id ) {
		$result = rytkoset_theme_sync_member_newsletter_user( $affected_user_id );

		if ( is_wp_error( $result ) ) {
			rytkoset_theme_log_newsletter_error( 'member_sync', implode( '; ', $result->get_error_codes() ) );
		}
	}
}
add_action( 'added_user_meta', 'rytkoset_theme_sync_member_newsletter_on_user_meta_change', 10, 4 );
add_action( 'updated_user_meta', 'rytkoset_theme_sync_member_newsletter_on_user_meta_change', 10, 4 );
add_action( 'deleted_user_meta', 'rytkoset_theme_sync_member_newsletter_on_user_meta_change', 10, 4 );
