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

	if ( $state['subscriber_id'] > 0 && 0 === $state['active'] ) {
		return 'protected_inactive';
	}

	if ( $should_be_member ) {
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
		rytkoset_theme_get_family_membership_period_meta_key(),
		rytkoset_theme_get_family_membership_expires_meta_key(),
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
		rytkoset_theme_get_family_membership_period_meta_key(),
		rytkoset_theme_get_family_membership_expires_meta_key(),
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

/**
 * Returns the reconciliation state option name.
 *
 * @return string
 */
function rytkoset_theme_get_member_newsletter_reconciliation_option_name() {
	return 'rytkoset_member_newsletter_reconciliation';
}

/**
 * Returns the daily reconciliation cron hook.
 *
 * @return string
 */
function rytkoset_theme_get_member_newsletter_daily_cron_hook() {
	return 'rytkoset_theme_member_newsletter_daily_reconciliation';
}

/**
 * Returns the reconciliation continuation hook.
 *
 * @return string
 */
function rytkoset_theme_get_member_newsletter_batch_cron_hook() {
	return 'rytkoset_theme_member_newsletter_reconciliation_batch';
}

/**
 * Returns the transient lock name preventing overlapping batches.
 *
 * @return string
 */
function rytkoset_theme_get_member_newsletter_reconciliation_lock_name() {
	return 'rytkoset_member_newsletter_reconciliation_lock';
}

/**
 * Returns the maximum number of records processed in one cron invocation.
 *
 * @return int
 */
function rytkoset_theme_get_member_newsletter_batch_size() {
	return min( 200, max( 1, absint( apply_filters( 'rytkoset_theme_member_newsletter_batch_size', 50 ) ) ) );
}

/**
 * Returns an empty reconciliation state.
 *
 * @return array<string, int|string|bool>
 */
function rytkoset_theme_get_default_member_newsletter_reconciliation_state() {
	return array(
		'running'           => false,
		'phase'             => 'idle',
		'cursor'            => 0,
		'started_at'        => '',
		'last_completed_at' => '',
		'last_success_at'   => '',
		'outcome'           => 'never',
		'processed'         => 0,
		'added'             => 0,
		'removed'           => 0,
		'unchanged'         => 0,
		'protected'         => 0,
		'errors'            => 0,
		'last_error_code'   => '',
		'last_error_at'     => '',
	);
}

/**
 * Normalizes stored reconciliation state without accepting personal data.
 *
 * @param mixed $state Raw option value.
 * @return array<string, int|string|bool>
 */
function rytkoset_theme_normalize_member_newsletter_reconciliation_state( $state ) {
	$state      = is_array( $state ) ? $state : array();
	$normalized = rytkoset_theme_get_default_member_newsletter_reconciliation_state();
	$phase      = sanitize_key( (string) ( $state['phase'] ?? '' ) );
	$outcome    = sanitize_key( (string) ( $state['outcome'] ?? '' ) );

	$normalized['running'] = ! empty( $state['running'] );
	$normalized['phase']   = in_array( $phase, array( 'idle', 'users', 'list' ), true ) ? $phase : 'idle';
	$normalized['outcome'] = in_array( $outcome, array( 'never', 'running', 'success', 'errors', 'configuration_error' ), true ) ? $outcome : 'never';

	foreach ( array( 'cursor', 'processed', 'added', 'removed', 'unchanged', 'protected', 'errors' ) as $integer_key ) {
		$normalized[ $integer_key ] = absint( $state[ $integer_key ] ?? 0 );
	}

	foreach ( array( 'started_at', 'last_completed_at', 'last_success_at', 'last_error_at' ) as $time_key ) {
		$normalized[ $time_key ] = sanitize_text_field( (string) ( $state[ $time_key ] ?? '' ) );
	}

	$normalized['last_error_code'] = sanitize_key( (string) ( $state['last_error_code'] ?? '' ) );

	return $normalized;
}

/**
 * Returns the persisted reconciliation state.
 *
 * @return array<string, int|string|bool>
 */
function rytkoset_theme_get_member_newsletter_reconciliation_state() {
	return rytkoset_theme_normalize_member_newsletter_reconciliation_state(
		get_option( rytkoset_theme_get_member_newsletter_reconciliation_option_name(), array() )
	);
}

/**
 * Persists reconciliation state with autoload disabled.
 *
 * @param array<string, mixed> $state Reconciliation state.
 * @return void
 */
function rytkoset_theme_save_member_newsletter_reconciliation_state( $state ) {
	update_option(
		rytkoset_theme_get_member_newsletter_reconciliation_option_name(),
		rytkoset_theme_normalize_member_newsletter_reconciliation_state( $state ),
		false
	);
}

/**
 * Applies one synchronization result to aggregate counters.
 *
 * @param array<string, mixed> $state  Reconciliation state.
 * @param string|WP_Error      $result Synchronization result.
 * @return array<string, int|string|bool>
 */
function rytkoset_theme_record_member_newsletter_reconciliation_result( $state, $result ) {
	$state               = rytkoset_theme_normalize_member_newsletter_reconciliation_state( $state );
	$state['processed'] += 1;

	if ( is_wp_error( $result ) ) {
		$error_codes              = $result->get_error_codes();
		$state['errors']         += 1;
		$state['last_error_code'] = sanitize_key( (string) ( $error_codes[0] ?? 'unknown_error' ) );
		$state['last_error_at']   = current_time( 'mysql' );

		return $state;
	}

	if ( 'added' === $result ) {
		$state['added'] += 1;
	} elseif ( 'removed' === $result ) {
		$state['removed'] += 1;
	} elseif ( in_array( $result, array( 'protected_opt_out', 'protected_inactive' ), true ) ) {
		$state['protected'] += 1;
	} elseif ( 'none' === $result ) {
		$state['unchanged'] += 1;
	} else {
		$state['errors']         += 1;
		$state['last_error_code'] = 'unexpected_result';
		$state['last_error_at']   = current_time( 'mysql' );
	}

	return $state;
}

/**
 * Starts a fresh reconciliation while retaining previous completion times.
 *
 * @return array<string, int|string|bool>|WP_Error
 */
function rytkoset_theme_start_member_newsletter_reconciliation() {
	if ( 0 === rytkoset_theme_get_member_newsletter_list_id() ) {
		return new WP_Error( 'missing_member_newsletter_list', __( 'Jäsenviestinnän AcyMailing-listaa ei ole määritetty.', 'rytkoset-theme' ) );
	}

	if ( ! class_exists( '\\AcyMailing\\Classes\\UserClass' ) ) {
		return new WP_Error( 'acymailing_missing', __( 'AcyMailing ei ole käytettävissä.', 'rytkoset-theme' ) );
	}

	$previous = rytkoset_theme_get_member_newsletter_reconciliation_state();
	$state    = rytkoset_theme_get_default_member_newsletter_reconciliation_state();

	$state['running']           = true;
	$state['phase']             = 'users';
	$state['started_at']        = current_time( 'mysql' );
	$state['outcome']           = 'running';
	$state['last_completed_at'] = $previous['last_completed_at'];
	$state['last_success_at']   = $previous['last_success_at'];

	rytkoset_theme_save_member_newsletter_reconciliation_state( $state );

	return $state;
}

/**
 * Completes the current reconciliation and records its aggregate outcome.
 *
 * @param array<string, mixed> $state Reconciliation state.
 * @return array<string, int|string|bool>
 */
function rytkoset_theme_complete_member_newsletter_reconciliation( $state ) {
	$state                      = rytkoset_theme_normalize_member_newsletter_reconciliation_state( $state );
	$state['running']           = false;
	$state['phase']             = 'idle';
	$state['cursor']            = 0;
	$state['last_completed_at'] = current_time( 'mysql' );
	$state['outcome']           = $state['errors'] > 0 ? 'errors' : 'success';

	if ( 0 === $state['errors'] ) {
		$state['last_success_at'] = $state['last_completed_at'];
	}

	return $state;
}

/**
 * Records a reconciliation-level configuration error without personal data.
 *
 * @param WP_Error $error Configuration error.
 * @return void
 */
function rytkoset_theme_record_member_newsletter_configuration_error( $error ) {
	$previous                   = rytkoset_theme_get_member_newsletter_reconciliation_state();
	$error_codes                = $error->get_error_codes();
	$state                      = rytkoset_theme_get_default_member_newsletter_reconciliation_state();
	$state['started_at']        = current_time( 'mysql' );
	$state['last_completed_at'] = $state['started_at'];
	$state['last_success_at']   = $previous['last_success_at'];
	$state['outcome']           = 'configuration_error';
	$state['errors']            = 1;
	$state['last_error_code']   = sanitize_key( (string) ( $error_codes[0] ?? 'configuration_error' ) );
	$state['last_error_at']     = $state['started_at'];

	rytkoset_theme_save_member_newsletter_reconciliation_state( $state );
}

/**
 * Fetches the next WordPress user IDs using a stable ID cursor.
 *
 * @param int $cursor     Last processed user ID.
 * @param int $batch_size Maximum rows.
 * @return int[]|WP_Error
 */
function rytkoset_theme_get_member_newsletter_user_batch( $cursor, $batch_size ) {
	global $wpdb;

	$cursor     = absint( $cursor );
	$batch_size = max( 1, absint( $batch_size ) );
	$user_ids   = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID
			 FROM {$wpdb->users}
			 WHERE ID > %d
			 ORDER BY ID ASC
			 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core users table name; values use placeholders.
			$cursor,
			$batch_size
		)
	);

	if ( ! is_array( $user_ids ) || '' !== (string) $wpdb->last_error ) {
		return new WP_Error( 'member_newsletter_user_query_failed', __( 'Jäsenviestinnän käyttäjäerän haku epäonnistui.', 'rytkoset-theme' ) );
	}

	return array_values( array_filter( array_map( 'absint', $user_ids ) ) );
}

/**
 * Fetches the next active AcyMailing member-list relations by subscriber ID.
 *
 * @param int $cursor     Last processed AcyMailing subscriber ID.
 * @param int $batch_size Maximum rows.
 * @return array<int, array{subscriber_id:int,email:string}>|WP_Error
 */
function rytkoset_theme_get_member_newsletter_list_batch( $cursor, $batch_size ) {
	global $wpdb;

	$cursor     = absint( $cursor );
	$batch_size = max( 1, absint( $batch_size ) );
	$list_id    = rytkoset_theme_get_member_newsletter_list_id();
	$user_table = $wpdb->prefix . 'acym_user';
	$list_table = $wpdb->prefix . 'acym_user_has_list';
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- AcyMailing table names use the WordPress DB prefix; values use placeholders.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT acym_user.id AS subscriber_id, acym_user.email
			 FROM {$user_table} AS acym_user
			 INNER JOIN {$list_table} AS acym_list
				ON acym_list.user_id = acym_user.id
			 WHERE acym_list.list_id = %d
				AND acym_list.status = 1
				AND acym_user.id > %d
			 ORDER BY acym_user.id ASC
			 LIMIT %d",
			$list_id,
			$cursor,
			$batch_size
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
		return new WP_Error( 'member_newsletter_list_query_failed', __( 'Jäsenviestinnän AcyMailing-erän haku epäonnistui.', 'rytkoset-theme' ) );
	}

	$batch = array();

	foreach ( $rows as $row ) {
		$row   = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
		$id    = absint( $row['subscriber_id'] ?? 0 );
		$email = strtolower( sanitize_email( (string) ( $row['email'] ?? '' ) ) );

		if ( $id > 0 ) {
			$batch[] = array(
				'subscriber_id' => $id,
				'email'         => $email,
			);
		}
	}

	return $batch;
}

/**
 * Schedules the next small reconciliation batch.
 *
 * @return void
 */
function rytkoset_theme_schedule_member_newsletter_reconciliation_batch() {
	$hook = rytkoset_theme_get_member_newsletter_batch_cron_hook();

	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, $hook );
	}
}

/**
 * Processes one user or AcyMailing-list batch.
 *
 * @return void
 */
function rytkoset_theme_process_member_newsletter_reconciliation_batch() {
	$lock_name = rytkoset_theme_get_member_newsletter_reconciliation_lock_name();

	if ( get_transient( $lock_name ) ) {
		rytkoset_theme_schedule_member_newsletter_reconciliation_batch();
		return;
	}

	set_transient( $lock_name, 1, 5 * MINUTE_IN_SECONDS );

	try {
		$state = rytkoset_theme_get_member_newsletter_reconciliation_state();

		if ( ! $state['running'] ) {
			return;
		}

		if ( 0 === rytkoset_theme_get_member_newsletter_list_id() || ! class_exists( '\\AcyMailing\\Classes\\UserClass' ) ) {
			$error = 0 === rytkoset_theme_get_member_newsletter_list_id()
				? new WP_Error( 'missing_member_newsletter_list' )
				: new WP_Error( 'acymailing_missing' );
			rytkoset_theme_record_member_newsletter_configuration_error( $error );
			return;
		}

		$batch_size = rytkoset_theme_get_member_newsletter_batch_size();

		if ( 'users' === $state['phase'] ) {
			$user_ids = rytkoset_theme_get_member_newsletter_user_batch( $state['cursor'], $batch_size );

			if ( is_wp_error( $user_ids ) ) {
				$state = rytkoset_theme_record_member_newsletter_reconciliation_result( $state, $user_ids );
				$state = rytkoset_theme_complete_member_newsletter_reconciliation( $state );
			} else {
				if ( ! empty( $user_ids ) ) {
					update_meta_cache( 'user', $user_ids );
				}

				foreach ( $user_ids as $user_id ) {
					$state['cursor'] = $user_id;
					$user            = get_userdata( $user_id );
					$result          = $user instanceof WP_User
						? rytkoset_theme_sync_member_newsletter_user( $user_id )
						: 'none';
					$state           = rytkoset_theme_record_member_newsletter_reconciliation_result( $state, $result );
				}

				if ( count( $user_ids ) < $batch_size ) {
					$state['phase']  = 'list';
					$state['cursor'] = 0;
				}
			}
		} elseif ( 'list' === $state['phase'] ) {
			$rows = rytkoset_theme_get_member_newsletter_list_batch( $state['cursor'], $batch_size );

			if ( is_wp_error( $rows ) ) {
				$state = rytkoset_theme_record_member_newsletter_reconciliation_result( $state, $rows );
				$state = rytkoset_theme_complete_member_newsletter_reconciliation( $state );
			} else {
				foreach ( $rows as $row ) {
					$state['cursor'] = $row['subscriber_id'];
					$user            = get_user_by( 'email', $row['email'] );
					$should_receive  = $user instanceof WP_User
						&& rytkoset_theme_user_is_member_newsletter_recipient( $user->ID );
					$result          = rytkoset_theme_sync_member_newsletter_email(
						$row['email'],
						$should_receive,
						$user instanceof WP_User ? $user->ID : 0
					);
					$state           = rytkoset_theme_record_member_newsletter_reconciliation_result( $state, $result );
				}

				if ( count( $rows ) < $batch_size ) {
					$state = rytkoset_theme_complete_member_newsletter_reconciliation( $state );
				}
			}
		} else {
			$state = rytkoset_theme_complete_member_newsletter_reconciliation(
				rytkoset_theme_record_member_newsletter_reconciliation_result(
					$state,
					new WP_Error( 'invalid_reconciliation_phase' )
				)
			);
		}

		rytkoset_theme_save_member_newsletter_reconciliation_state( $state );

		if ( $state['running'] ) {
			rytkoset_theme_schedule_member_newsletter_reconciliation_batch();
		}
	} finally {
		delete_transient( $lock_name );
	}
}
add_action( 'rytkoset_theme_member_newsletter_reconciliation_batch', 'rytkoset_theme_process_member_newsletter_reconciliation_batch' );

/**
 * Starts or resumes the daily reconciliation.
 *
 * @return void
 */
function rytkoset_theme_run_member_newsletter_daily_reconciliation() {
	$state = rytkoset_theme_get_member_newsletter_reconciliation_state();

	if ( ! $state['running'] ) {
		$state = rytkoset_theme_start_member_newsletter_reconciliation();

		if ( is_wp_error( $state ) ) {
			rytkoset_theme_record_member_newsletter_configuration_error( $state );
			return;
		}
	}

	rytkoset_theme_process_member_newsletter_reconciliation_batch();
}
add_action( 'rytkoset_theme_member_newsletter_daily_reconciliation', 'rytkoset_theme_run_member_newsletter_daily_reconciliation' );

/**
 * Ensures the daily reconciliation event exists.
 *
 * @return void
 */
function rytkoset_theme_ensure_member_newsletter_daily_cron_scheduled() {
	$hook = rytkoset_theme_get_member_newsletter_daily_cron_hook();

	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
	}
}
add_action( 'init', 'rytkoset_theme_ensure_member_newsletter_daily_cron_scheduled' );

/**
 * Clears member-newsletter cron events when switching themes.
 *
 * @return void
 */
function rytkoset_theme_clear_member_newsletter_cron() {
	wp_clear_scheduled_hook( rytkoset_theme_get_member_newsletter_daily_cron_hook() );
	wp_clear_scheduled_hook( rytkoset_theme_get_member_newsletter_batch_cron_hook() );
}
add_action( 'switch_theme', 'rytkoset_theme_clear_member_newsletter_cron' );

/**
 * Returns a Finnish reconciliation outcome label.
 *
 * @param string $outcome Outcome key.
 * @return string
 */
function rytkoset_theme_get_member_newsletter_reconciliation_outcome_label( $outcome ) {
	$labels = array(
		'never'               => __( 'Ei vielä ajettu', 'rytkoset-theme' ),
		'running'             => __( 'Käynnissä', 'rytkoset-theme' ),
		'success'             => __( 'Onnistui', 'rytkoset-theme' ),
		'errors'              => __( 'Valmistui virhein', 'rytkoset-theme' ),
		'configuration_error' => __( 'Asetusvirhe', 'rytkoset-theme' ),
	);

	return $labels[ $outcome ] ?? $labels['never'];
}

/**
 * Registers the member-newsletter status Dashboard widget.
 *
 * @return void
 */
function rytkoset_theme_register_member_newsletter_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'rytkoset_member_newsletter_status',
		__( 'Jäsenviestinnän synkronointi', 'rytkoset-theme' ),
		'rytkoset_theme_render_member_newsletter_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'rytkoset_theme_register_member_newsletter_dashboard_widget' );

/**
 * Renders aggregate synchronization status without personal data.
 *
 * @return void
 */
function rytkoset_theme_render_member_newsletter_dashboard_widget() {
	$state   = rytkoset_theme_get_member_newsletter_reconciliation_state();
	$list_id = rytkoset_theme_get_member_newsletter_list_id();
	if ( 0 === $list_id ) {
		$status = __( 'Ei käytössä — jäsenviestinnän lista-ID puuttuu.', 'rytkoset-theme' );
	} elseif ( ! class_exists( '\\AcyMailing\\Classes\\UserClass' ) ) {
		$status = __( 'Ei käytössä — AcyMailing ei ole saatavilla.', 'rytkoset-theme' );
	} else {
		$status = sprintf(
			/* translators: %d: AcyMailing list ID. */
			__( 'Käytössä, AcyMailing-lista %d.', 'rytkoset-theme' ),
			$list_id
		);
	}

	echo '<p><strong>' . esc_html__( 'Tila:', 'rytkoset-theme' ) . '</strong> ' . esc_html( $status ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Viimeisin ajo:', 'rytkoset-theme' ) . '</strong> ' . esc_html( rytkoset_theme_get_member_newsletter_reconciliation_outcome_label( (string) $state['outcome'] ) ) . '</p>';
	echo '<ul>';
	echo '<li>' . esc_html__( 'Aloitettu:', 'rytkoset-theme' ) . ' <strong>' . esc_html( '' !== $state['started_at'] ? $state['started_at'] : '—' ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Viimeksi valmistunut:', 'rytkoset-theme' ) . ' <strong>' . esc_html( '' !== $state['last_completed_at'] ? $state['last_completed_at'] : '—' ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Viimeisin onnistunut ajo:', 'rytkoset-theme' ) . ' <strong>' . esc_html( '' !== $state['last_success_at'] ? $state['last_success_at'] : '—' ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Käsitelty:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $state['processed'] ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Lisätty / poistettu / ennallaan:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $state['added'] ) . ' / ' . number_format_i18n( $state['removed'] ) . ' / ' . number_format_i18n( $state['unchanged'] ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Suojattu peruutus tai esto:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $state['protected'] ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Virheitä:', 'rytkoset-theme' ) . ' <strong>' . esc_html( number_format_i18n( $state['errors'] ) ) . '</strong>';

	if ( '' !== $state['last_error_code'] ) {
		echo ' &ndash; ' . esc_html__( 'viimeisin koodi', 'rytkoset-theme' ) . ': <code>' . esc_html( $state['last_error_code'] ) . '</code>';
	}

	echo '</li>';
	echo '</ul>';
	echo '<p class="description">' . esc_html__( 'Kooste ei sisällä nimiä, sähköpostiosoitteita eikä viestisisältöjä.', 'rytkoset-theme' ) . '</p>';
}
