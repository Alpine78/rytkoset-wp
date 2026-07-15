<?php
/**
 * Käyttäjän jäsenyystila: käyttäjäprofiilin Jäsenyys-osio ja aktiivisen jäsenyyden helper.
 *
 * Jäsenyyden lähdetieto on käyttäjän metatietoa (ei WordPress-rooli), jotta määräaikainen
 * ja ainaisjäsenyys voidaan käsitellä samalla mallilla ja käyttäjän muu rooli säilyy.
 * Vrt. WooCommerce-jäsenmaksutuotteet (`inc/woocommerce-membership.php`), jotka käyttävät
 * alaviivalla alkavia tuotemeta-avaimia (`_rytkoset_membership_*`); nämä käyttäjämeta-avaimet
 * eivät ala alaviivalla.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the user meta key storing the membership type.
 *
 * @return string
 */
function rytkoset_theme_get_user_membership_type_meta_key() {
	return 'rytkoset_membership_type';
}

/**
 * Returns the user meta key storing the membership period (e.g. 2026-2029).
 *
 * @return string
 */
function rytkoset_theme_get_user_membership_period_meta_key() {
	return 'rytkoset_membership_period';
}

/**
 * Returns the user meta key storing the membership expiry date (YYYY-MM-DD).
 *
 * @return string
 */
function rytkoset_theme_get_user_membership_expires_meta_key() {
	return 'rytkoset_membership_expires';
}

/**
 * Returns the supported user membership types.
 *
 * Empty string ('') means "not a member" and is offered as the default option in the UI.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_user_membership_type_options() {
	return array(
		'annual'   => __( 'Vuosijäsen', 'rytkoset-theme' ),
		'family'   => __( 'Perhejäsen', 'rytkoset-theme' ),
		'lifetime' => __( 'Ainaisjäsen', 'rytkoset-theme' ),
	);
}

/**
 * Returns true when a membership type is time-bound (requires an expiry date to be active).
 *
 * @param string $type Membership type key.
 * @return bool
 */
function rytkoset_theme_user_membership_type_is_time_bound( $type ) {
	return in_array( $type, array( 'annual', 'family' ), true );
}

/**
 * Normalizes a user membership type to an allowlisted value.
 *
 * @param string $type Raw membership type.
 * @return string Allowlisted type, or '' for "not a member"/unknown.
 */
function rytkoset_theme_normalize_user_membership_type( $type ) {
	$type    = sanitize_key( $type );
	$options = rytkoset_theme_get_user_membership_type_options();

	return isset( $options[ $type ] ) ? $type : '';
}

/**
 * Returns the human-readable label for a user membership type.
 *
 * @param string $type Membership type key.
 * @return string
 */
function rytkoset_theme_get_user_membership_type_label( $type ) {
	$options = rytkoset_theme_get_user_membership_type_options();

	return isset( $options[ $type ] ) ? $options[ $type ] : __( 'Ei jäsen', 'rytkoset-theme' );
}

/**
 * Returns the stored membership details for a user.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return array{type:string,period:string,expires:string}
 */
function rytkoset_theme_get_user_membership( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	$empty = array(
		'type'    => '',
		'period'  => '',
		'expires' => '',
	);

	if ( ! $user_id ) {
		return $empty;
	}

	$type = rytkoset_theme_normalize_user_membership_type(
		(string) get_user_meta( $user_id, rytkoset_theme_get_user_membership_type_meta_key(), true )
	);

	if ( '' === $type ) {
		return $empty;
	}

	return array(
		'type'    => $type,
		'period'  => (string) get_user_meta( $user_id, rytkoset_theme_get_user_membership_period_meta_key(), true ),
		'expires' => (string) get_user_meta( $user_id, rytkoset_theme_get_user_membership_expires_meta_key(), true ),
	);
}

/**
 * Returns true when a stored membership detail array is currently active.
 *
 * Lifetime memberships are always active. Time-bound memberships (annual/family) are active
 * only when an expiry date is stored and has not passed in the site timezone. Any uncertain
 * state — no membership type, or a time-bound membership without a valid future expiry — is
 * treated as "not a member" (fail closed), per docs/digital-magazines.md.
 *
 * @param array<string, string> $membership Stored membership details.
 * @return bool
 */
function rytkoset_theme_user_membership_is_active( $membership ) {
	if ( ! is_array( $membership ) || empty( $membership['type'] ) ) {
		return false;
	}

	$type = rytkoset_theme_normalize_user_membership_type( (string) $membership['type'] );

	if ( '' === $type ) {
		return false;
	}

	if ( 'lifetime' === $type ) {
		return true;
	}

	if ( ! rytkoset_theme_user_membership_type_is_time_bound( $type ) ) {
		return false;
	}

	$expires = isset( $membership['expires'] ) ? (string) $membership['expires'] : '';

	if ( '' === $expires ) {
		return false;
	}

	$today = current_datetime()->format( 'Y-m-d' );

	// Y-m-d strings compare correctly lexically; membership is active through the expiry date.
	return $expires >= $today;
}

/**
 * Returns true when the user has an active membership stored directly on their own profile.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_has_own_active_membership( $user_id = null ) {
	$membership = rytkoset_theme_get_user_membership( $user_id );

	return rytkoset_theme_user_membership_is_active( $membership );
}

/**
 * Returns true when the user's own membership is an active family membership.
 *
 * Family-member benefit inheritance must only flow from a primary account whose own membership
 * type is `family`; a lifetime or annual membership on the primary account is not enough.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_has_own_active_family_membership( $user_id = null ) {
	$membership = rytkoset_theme_get_user_membership( $user_id );

	return 'family' === $membership['type'] && rytkoset_theme_user_membership_is_active( $membership );
}

/**
 * Returns the user meta key storing a primary account's family member rows.
 *
 * @return string
 */
function rytkoset_theme_get_family_members_meta_key() {
	return 'rytkoset_family_members';
}

/**
 * Returns the user meta key pointing a linked family member back to their primary account.
 *
 * @return string
 */
function rytkoset_theme_get_family_primary_user_meta_key() {
	return 'rytkoset_family_primary_user_id';
}

/**
 * Returns supported family member row statuses.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_family_member_status_options() {
	return array(
		'active'          => __( 'Aktiivinen', 'rytkoset-theme' ),
		'pending_account' => __( 'Odottaa käyttäjätiliä', 'rytkoset-theme' ),
		'removed'         => __( 'Poistettu', 'rytkoset-theme' ),
	);
}

/**
 * Normalizes a family member row status.
 *
 * An empty status is returned as-is so the row normalizer can apply its own default (`active`
 * for a linked user, `pending_account` otherwise). Unknown non-empty statuses fall back to
 * `pending_account` (fail closed): a drifted or corrupted stored status must never turn a row
 * into one that grants member benefits.
 *
 * @param string $status Raw status.
 * @return string
 */
function rytkoset_theme_normalize_family_member_status( $status ) {
	$status = sanitize_key( $status );

	if ( '' === $status ) {
		return '';
	}

	$options = rytkoset_theme_get_family_member_status_options();

	return isset( $options[ $status ] ) ? $status : 'pending_account';
}

/**
 * Returns a normalized email address for family member matching.
 *
 * @param string $email Raw email.
 * @return string
 */
function rytkoset_theme_normalize_family_member_email( $email ) {
	$email = strtolower( sanitize_email( $email ) );

	return is_email( $email ) ? $email : '';
}

/**
 * Normalizes one family member row for storage.
 *
 * @param array<string, mixed> $member Raw member row.
 * @return array{name:string,email:string,linked_user_id:int,status:string,source_order_id:int,updated_at:string}|array<never>
 */
function rytkoset_theme_normalize_family_member( $member ) {
	if ( ! is_array( $member ) ) {
		return array();
	}

	$name           = isset( $member['name'] ) ? sanitize_text_field( (string) $member['name'] ) : '';
	$email          = isset( $member['email'] ) ? rytkoset_theme_normalize_family_member_email( (string) $member['email'] ) : '';
	$linked_user_id = isset( $member['linked_user_id'] ) ? absint( $member['linked_user_id'] ) : 0;
	$status         = isset( $member['status'] )
		? rytkoset_theme_normalize_family_member_status( (string) $member['status'] )
		: '';

	if ( $linked_user_id > 0 && ! get_userdata( $linked_user_id ) instanceof WP_User ) {
		$linked_user_id = 0;
	}

	if ( '' === $status ) {
		$status = $linked_user_id > 0 ? 'active' : 'pending_account';
	}

	$source_order_id = isset( $member['source_order_id'] ) ? absint( $member['source_order_id'] ) : 0;
	$updated_at      = isset( $member['updated_at'] ) ? sanitize_text_field( (string) $member['updated_at'] ) : '';

	if ( '' === $name && '' === $email && 0 === $linked_user_id ) {
		return array();
	}

	if ( '' === $updated_at ) {
		$updated_at = current_time( 'mysql' );
	}

	return array(
		'name'            => $name,
		'email'           => $email,
		'linked_user_id'  => $linked_user_id,
		'status'          => $status,
		'source_order_id' => $source_order_id,
		'updated_at'      => $updated_at,
	);
}

/**
 * Normalizes a family member row list for storage.
 *
 * @param array<int|string, mixed> $members Raw member rows.
 * @return array<int, array{name:string,email:string,linked_user_id:int,status:string,source_order_id:int,updated_at:string}>
 */
function rytkoset_theme_normalize_family_members( $members ) {
	if ( ! is_array( $members ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $members as $member ) {
		$row = rytkoset_theme_normalize_family_member( is_array( $member ) ? $member : array() );

		if ( empty( $row ) ) {
			continue;
		}

		$normalized[] = $row;
	}

	return $normalized;
}

/**
 * Drops removed rows whose email is reused by a non-removed row (#541).
 *
 * Removal is a soft delete, so a re-added family member would otherwise fail
 * duplicate-email validation against their own historical `removed` row. A
 * removed row whose email is not reused stays untouched as history.
 *
 * @param array<int, array<string, mixed>> $members Normalized family member rows.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_supersede_removed_family_member_rows( $members ) {
	$reused_emails = array();

	foreach ( $members as $member ) {
		$email  = isset( $member['email'] ) ? (string) $member['email'] : '';
		$status = isset( $member['status'] ) ? (string) $member['status'] : '';

		if ( '' !== $email && 'removed' !== $status ) {
			$reused_emails[ $email ] = true;
		}
	}

	if ( empty( $reused_emails ) ) {
		return $members;
	}

	$kept = array();

	foreach ( $members as $member ) {
		$email  = isset( $member['email'] ) ? (string) $member['email'] : '';
		$status = isset( $member['status'] ) ? (string) $member['status'] : '';

		if ( 'removed' === $status && '' !== $email && isset( $reused_emails[ $email ] ) ) {
			continue;
		}

		$kept[] = $member;
	}

	return array_values( $kept );
}

/**
 * Resolves pending family rows to existing WordPress accounts by email (#542).
 *
 * This runs only on writes. Reading stored rows must remain side-effect free so a
 * link is never exposed without its matching reverse meta being persisted.
 *
 * @param array<int, array<string, mixed>> $members          Normalized family member rows.
 * @param int                              $excluded_user_id Optional. Account that must not be
 *                                                           email-linked because it is being
 *                                                           deleted (#544). Default 0.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_resolve_family_member_accounts( $members, $excluded_user_id = 0 ) {
	$excluded_user_id = absint( $excluded_user_id );

	foreach ( $members as $index => $member ) {
		$email          = isset( $member['email'] ) ? (string) $member['email'] : '';
		$linked_user_id = isset( $member['linked_user_id'] ) ? absint( $member['linked_user_id'] ) : 0;
		$status         = isset( $member['status'] ) ? (string) $member['status'] : '';

		if ( '' === $email || $linked_user_id > 0 || 'pending_account' !== $status ) {
			continue;
		}

		$linked_user = get_user_by( 'email', $email );

		if ( ! $linked_user instanceof WP_User ) {
			continue;
		}

		if ( $excluded_user_id > 0 && absint( $linked_user->ID ) === $excluded_user_id ) {
			continue;
		}

		$members[ $index ]['linked_user_id'] = absint( $linked_user->ID );
		$members[ $index ]['status']         = 'active';
		$members[ $index ]['updated_at']     = current_time( 'mysql' );
	}

	return $members;
}

/**
 * Returns the stored family member rows for a primary account.
 *
 * @param int $primary_user_id Primary account user ID.
 * @return array<int, array{name:string,email:string,linked_user_id:int,status:string,source_order_id:int,updated_at:string}>
 */
function rytkoset_theme_get_family_members( $primary_user_id ) {
	$primary_user_id = absint( $primary_user_id );

	if ( $primary_user_id <= 0 ) {
		return array();
	}

	$members = get_user_meta( $primary_user_id, rytkoset_theme_get_family_members_meta_key(), true );

	return rytkoset_theme_normalize_family_members( is_array( $members ) ? $members : array() );
}

/**
 * Returns the primary account ID stored on a linked family member.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return int
 */
function rytkoset_theme_get_family_primary_user_id( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return 0;
	}

	return absint( get_user_meta( $user_id, rytkoset_theme_get_family_primary_user_meta_key(), true ) );
}

/**
 * Returns active linked user IDs from normalized family member rows.
 *
 * @param array<int, array<string, mixed>> $members Family member rows.
 * @return int[]
 */
function rytkoset_theme_get_active_linked_family_user_ids( $members ) {
	$user_ids = array();

	foreach ( $members as $member ) {
		$linked_user_id = isset( $member['linked_user_id'] ) ? absint( $member['linked_user_id'] ) : 0;
		$status         = isset( $member['status'] ) ? (string) $member['status'] : '';

		if ( $linked_user_id > 0 && 'active' === $status ) {
			$user_ids[] = $linked_user_id;
		}
	}

	return array_values( array_unique( $user_ids ) );
}

/**
 * Validates normalized family member rows before saving.
 *
 * @param int                                $primary_user_id Primary account user ID.
 * @param array<int, array<string, mixed>>   $members         Normalized family member rows.
 * @return true|WP_Error
 */
function rytkoset_theme_validate_family_members( $primary_user_id, $members ) {
	$primary_user_id = absint( $primary_user_id );

	if ( $primary_user_id <= 0 || ! get_userdata( $primary_user_id ) instanceof WP_User ) {
		return new WP_Error(
			'rytkoset_invalid_family_primary',
			__( 'Perhejäsenyyden päätiliä ei löytynyt.', 'rytkoset-theme' )
		);
	}

	$seen_emails = array();
	$seen_users  = array();

	foreach ( $members as $member ) {
		$email          = isset( $member['email'] ) ? (string) $member['email'] : '';
		$linked_user_id = isset( $member['linked_user_id'] ) ? absint( $member['linked_user_id'] ) : 0;
		$status         = isset( $member['status'] ) ? (string) $member['status'] : '';

		if ( '' !== $email ) {
			if ( isset( $seen_emails[ $email ] ) ) {
				return new WP_Error(
					'rytkoset_duplicate_family_member_email',
					__( 'Sama sähköpostiosoite voi olla perhejäsenlistassa vain kerran.', 'rytkoset-theme' )
				);
			}

			$seen_emails[ $email ] = true;
		}

		if ( $linked_user_id <= 0 ) {
			continue;
		}

		if ( $linked_user_id === $primary_user_id ) {
			return new WP_Error(
				'rytkoset_family_member_is_primary',
				__( 'Päätiliä ei voi linkittää oman perhejäsenyytensä jäseneksi.', 'rytkoset-theme' )
			);
		}

		if ( isset( $seen_users[ $linked_user_id ] ) ) {
			return new WP_Error(
				'rytkoset_duplicate_family_member_user',
				__( 'Sama käyttäjätili voi olla perhejäsenlistassa vain kerran.', 'rytkoset-theme' )
			);
		}

		// A historical `removed` row must not block saving this list when the user has since
		// been linked to another primary account; only rows that could grant benefits conflict.
		// Reverse meta pointing at a primary account that no longer exists is stale (the
		// primary was deleted before #544's cleanup hook existed) and must not block a new
		// link; saving this list overwrites the stale pointer with the new primary.
		if ( 'removed' !== $status ) {
			$existing_primary = rytkoset_theme_get_family_primary_user_id( $linked_user_id );

			if (
				$existing_primary > 0
				&& $existing_primary !== $primary_user_id
				&& get_userdata( $existing_primary ) instanceof WP_User
			) {
				return new WP_Error(
					'rytkoset_family_member_already_linked',
					__( 'Käyttäjätili kuuluu jo toiseen perhejäsenyyteen. Poista vanha linkitys ennen uuden lisäämistä.', 'rytkoset-theme' )
				);
			}
		}

		$seen_users[ $linked_user_id ] = true;
	}

	return true;
}

/**
 * Saves a primary account's family member rows and keeps reverse meta in sync.
 *
 * All code paths that mutate the family list must use this helper so the forward list and
 * `rytkoset_family_primary_user_id` reverse meta do not drift apart.
 *
 * @param int                              $primary_user_id  Primary account user ID.
 * @param array<int|string, array<string, mixed>> $members   Raw or normalized member rows.
 * @param int                              $excluded_user_id Optional. Account that must not be
 *                                                           email-linked because it is being
 *                                                           deleted (#544). Default 0.
 * @return true|WP_Error
 */
function rytkoset_theme_update_family_members( $primary_user_id, $members, $excluded_user_id = 0 ) {
	$primary_user_id = absint( $primary_user_id );
	$normalized      = rytkoset_theme_normalize_family_members( $members );
	$normalized      = rytkoset_theme_supersede_removed_family_member_rows( $normalized );
	$normalized      = rytkoset_theme_resolve_family_member_accounts( $normalized, $excluded_user_id );
	$valid           = rytkoset_theme_validate_family_members( $primary_user_id, $normalized );

	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$previous_linked = rytkoset_theme_get_active_linked_family_user_ids(
		rytkoset_theme_get_family_members( $primary_user_id )
	);
	$new_linked      = rytkoset_theme_get_active_linked_family_user_ids( $normalized );
	$list_key        = rytkoset_theme_get_family_members_meta_key();
	$primary_key     = rytkoset_theme_get_family_primary_user_meta_key();

	if ( empty( $normalized ) ) {
		delete_user_meta( $primary_user_id, $list_key );
	} else {
		update_user_meta( $primary_user_id, $list_key, $normalized );
	}

	foreach ( array_diff( $previous_linked, $new_linked ) as $removed_user_id ) {
		if ( rytkoset_theme_get_family_primary_user_id( $removed_user_id ) === $primary_user_id ) {
			delete_user_meta( $removed_user_id, $primary_key );
		}
	}

	foreach ( $new_linked as $linked_user_id ) {
		update_user_meta( $linked_user_id, $primary_key, $primary_user_id );
	}

	return true;
}

/**
 * Returns primary accounts with a pending family row matching an email (#542).
 *
 * The serialized-meta LIKE query narrows the candidate set. Stored rows are then
 * checked exactly so partial matches or unrelated serialized values cannot link an
 * account.
 *
 * @param string $email Family member email address.
 * @return int[]
 */
function rytkoset_theme_get_pending_family_primary_user_ids_for_email( $email ) {
	$email = rytkoset_theme_normalize_family_member_email( (string) $email );

	if ( '' === $email || ! function_exists( 'get_users' ) ) {
		return array();
	}

	$candidate_ids = get_users(
		array(
			'fields'       => 'ids',
			'meta_key'     => rytkoset_theme_get_family_members_meta_key(),
			'meta_value'   => '"' . $email . '"',
			'meta_compare' => 'LIKE',
			'number'       => -1,
			'orderby'      => 'ID',
			'order'        => 'ASC',
		)
	);

	if ( ! is_array( $candidate_ids ) ) {
		return array();
	}

	$primary_user_ids = array();

	foreach ( $candidate_ids as $candidate_id ) {
		$candidate_id = absint( $candidate_id );

		foreach ( rytkoset_theme_get_family_members( $candidate_id ) as $member ) {
			if ( $email === $member['email'] && 'pending_account' === $member['status'] ) {
				$primary_user_ids[] = $candidate_id;
				break;
			}
		}
	}

	return array_values( array_unique( $primary_user_ids ) );
}

/**
 * Links a user account to a primary account's matching pending family row (#542).
 *
 * @param int $primary_user_id Primary account user ID.
 * @param int $user_id         Family member user ID.
 * @return true|false|WP_Error True when linked/already linked, false when not applicable.
 */
function rytkoset_theme_link_family_member_account_from_primary( $primary_user_id, $user_id ) {
	$primary_user_id = absint( $primary_user_id );
	$user            = get_userdata( absint( $user_id ) );

	if ( $primary_user_id <= 0 || ! $user instanceof WP_User || absint( $user->ID ) === $primary_user_id ) {
		return false;
	}

	$email = rytkoset_theme_normalize_family_member_email( (string) $user->user_email );

	if ( '' === $email ) {
		return false;
	}

	$members = rytkoset_theme_get_family_members( $primary_user_id );

	foreach ( $members as $index => $member ) {
		if ( $email !== $member['email'] || 'removed' === $member['status'] ) {
			continue;
		}

		if ( absint( $member['linked_user_id'] ) === absint( $user->ID ) && 'active' === $member['status'] ) {
			return true;
		}

		if ( 'pending_account' !== $member['status'] ) {
			return false;
		}

		$members[ $index ]['linked_user_id'] = absint( $user->ID );
		$members[ $index ]['status']         = 'active';
		$members[ $index ]['updated_at']     = current_time( 'mysql' );

		return rytkoset_theme_update_family_members( $primary_user_id, $members );
	}

	return false;
}

/**
 * Returns true when the primary account list has an active link for the user.
 *
 * @param int $user_id         Linked family member user ID.
 * @param int $primary_user_id Primary account user ID.
 * @return bool
 */
function rytkoset_theme_family_primary_has_active_linked_user( $user_id, $primary_user_id ) {
	$user_id         = absint( $user_id );
	$primary_user_id = absint( $primary_user_id );

	if ( $user_id <= 0 || $primary_user_id <= 0 || $user_id === $primary_user_id ) {
		return false;
	}

	foreach ( rytkoset_theme_get_family_members( $primary_user_id ) as $member ) {
		if (
			isset( $member['linked_user_id'], $member['status'] )
			&& absint( $member['linked_user_id'] ) === $user_id
			&& 'active' === $member['status']
		) {
			return true;
		}
	}

	return false;
}

/**
 * Cleans up family membership links when a user account is deleted (#544).
 *
 * Runs on `delete_user`, before WordPress removes the user row and user meta, so both the
 * primary's family list and a linked member's reverse meta are still readable:
 *
 * - Deleted primary account: linked members' reverse meta pointing at the primary is removed,
 *   so the stale pointer cannot later block linking those users to a new family membership.
 *   The forward list itself is deleted with the primary's own user meta.
 * - Deleted linked member: the primary's matching row is detached (`linked_user_id` 0, status
 *   `pending_account`) through the shared save helper, so the admin view does not show a link
 *   to a nonexistent account and a future account with the same email can be re-linked. The
 *   account being deleted is excluded from email resolution, which would otherwise re-link
 *   the row to it while it still exists during this hook.
 *
 * @param int $user_id User ID being deleted.
 * @return void
 */
function rytkoset_theme_cleanup_family_links_on_user_delete( $user_id ) {
	$user_id = absint( $user_id );

	if ( $user_id <= 0 ) {
		return;
	}

	$primary_meta_key = rytkoset_theme_get_family_primary_user_meta_key();

	foreach ( rytkoset_theme_get_family_members( $user_id ) as $member ) {
		$linked_user_id = absint( $member['linked_user_id'] );

		if ( $linked_user_id > 0 && rytkoset_theme_get_family_primary_user_id( $linked_user_id ) === $user_id ) {
			delete_user_meta( $linked_user_id, $primary_meta_key );
		}
	}

	$primary_user_id = rytkoset_theme_get_family_primary_user_id( $user_id );

	if ( $primary_user_id <= 0 ) {
		return;
	}

	$members = rytkoset_theme_get_family_members( $primary_user_id );
	$changed = false;

	foreach ( $members as $index => $member ) {
		if ( absint( $member['linked_user_id'] ) !== $user_id ) {
			continue;
		}

		$members[ $index ]['linked_user_id'] = 0;
		$members[ $index ]['updated_at']     = current_time( 'mysql' );

		// A historical removed row keeps its status; a row that granted benefits waits for a
		// possible future account with the same email.
		if ( 'removed' !== $member['status'] ) {
			$members[ $index ]['status'] = 'pending_account';
		}

		$changed = true;
	}

	if ( $changed ) {
		rytkoset_theme_update_family_members( $primary_user_id, $members, $user_id );
	}
}
add_action( 'delete_user', 'rytkoset_theme_cleanup_family_links_on_user_delete' );

/**
 * Returns effective membership details for the user.
 *
 * Own active membership wins. If the user has no active own membership, a linked family row can
 * provide benefits only while the primary account has an active `family` membership.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return array{type:string,period:string,expires:string,source:string,primary_user_id:int}
 */
function rytkoset_theme_get_effective_user_membership( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$empty   = array(
		'type'            => '',
		'period'          => '',
		'expires'         => '',
		'source'          => 'none',
		'primary_user_id' => 0,
	);

	if ( $user_id <= 0 ) {
		return $empty;
	}

	$own_membership = rytkoset_theme_get_user_membership( $user_id );

	if ( rytkoset_theme_user_membership_is_active( $own_membership ) ) {
		return $own_membership + array(
			'source'          => 'own',
			'primary_user_id' => 0,
		);
	}

	$primary_user_id = rytkoset_theme_get_family_primary_user_id( $user_id );

	if (
		$primary_user_id <= 0
		|| ! rytkoset_theme_family_primary_has_active_linked_user( $user_id, $primary_user_id )
		|| ! rytkoset_theme_user_has_own_active_family_membership( $primary_user_id )
	) {
		return $empty;
	}

	$primary_membership = rytkoset_theme_get_user_membership( $primary_user_id );

	return $primary_membership + array(
		'source'          => 'family',
		'primary_user_id' => $primary_user_id,
	);
}

/**
 * Returns true when the user has active member benefits.
 *
 * This is the public membership gate used by members-only pages, digital magazines, coupons and
 * member-price validation. It accepts either the user's own active membership or an active linked
 * family membership inherited from a primary account.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_is_active_member( $user_id = null ) {
	return '' !== rytkoset_theme_get_effective_user_membership( $user_id )['type'];
}

/**
 * Validates and normalizes a membership period string (e.g. 2026-2029).
 *
 * @param string $period Raw period string.
 * @return string Normalized period, or '' when the format is not recognized.
 */
function rytkoset_theme_sanitize_user_membership_period( $period ) {
	$period = sanitize_text_field( $period );

	return preg_match( '/^\d{4}-\d{4}$/', $period ) ? $period : '';
}

/**
 * Validates and normalizes a membership expiry date to YYYY-MM-DD for storage.
 *
 * Accepts the Finnish input format pp.kk.vvvv (e.g. 31.12.2029, day first) and, for
 * round-tripping of stored values, the ISO format YYYY-MM-DD. Storage is always ISO so the
 * active-membership helper can compare dates lexically.
 *
 * @param string $expires Raw date string.
 * @return string Normalized ISO date, or '' when the value is not a valid calendar date.
 */
function rytkoset_theme_sanitize_user_membership_expires( $expires ) {
	$expires = trim( sanitize_text_field( $expires ) );

	if ( '' === $expires ) {
		return '';
	}

	if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $expires, $matches ) ) {
		$day   = (int) $matches[1];
		$month = (int) $matches[2];
		$year  = (int) $matches[3];
	} elseif ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $expires, $matches ) ) {
		$year  = (int) $matches[1];
		$month = (int) $matches[2];
		$day   = (int) $matches[3];
	} else {
		return '';
	}

	if ( ! checkdate( $month, $day, $year ) ) {
		return '';
	}

	return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * Formats a stored ISO expiry date (YYYY-MM-DD) as Finnish pp.kk.vvvv for display.
 *
 * @param string $iso Stored ISO date.
 * @return string Finnish-formatted date, or '' when the value is not a valid calendar date.
 */
function rytkoset_theme_get_user_membership_expires_display( $iso ) {
	$iso = (string) $iso;

	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $matches ) ) {
		return '';
	}

	if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
		return '';
	}

	return sprintf( '%02d.%02d.%04d', (int) $matches[3], (int) $matches[2], (int) $matches[1] );
}

/**
 * Sends the membership confirmation email to a user.
 *
 * Shared by the manual profile path (#390) and the automatic WooCommerce order path (#302), so
 * both routes produce an identical message. The body states the membership type and validity:
 * the period and expiry date for annual/family memberships, or permanent validity for lifetime
 * memberships. The sender comes from inc/email.php. Callers are responsible for detecting the
 * non-active → active transition so the confirmation is sent only once.
 *
 * @param int $user_id User ID.
 * @return bool Whether wp_mail() accepted the message for delivery.
 */
function rytkoset_theme_send_membership_confirmation_email( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
		return false;
	}

	$membership = rytkoset_theme_get_user_membership( $user->ID );

	if ( '' === $membership['type'] ) {
		return false;
	}

	$display_name = trim( (string) $user->display_name );
	$type_label   = rytkoset_theme_get_user_membership_type_label( $membership['type'] );

	$subject = __( 'Jäsenyytesi Rytkösten sukuseurassa on voimassa', 'rytkoset-theme' );

	$lines = array(
		'' !== $display_name
			? sprintf(
				/* translators: %s: recipient name. */
				__( 'Hei %s,', 'rytkoset-theme' ),
				$display_name
			)
			: __( 'Hei,', 'rytkoset-theme' ),
		'',
		__( 'Kiitos, että olet Rytkösten sukuseura ry:n jäsen.', 'rytkoset-theme' ),
		'',
		sprintf(
			/* translators: %s: membership type label (e.g. Vuosijäsen). */
			__( 'Jäsenyyden tyyppi: %s', 'rytkoset-theme' ),
			$type_label
		),
	);

	if ( 'lifetime' === $membership['type'] ) {
		$lines[] = __( 'Jäsenyytesi on voimassa pysyvästi ilman päättymispäivää.', 'rytkoset-theme' );
	} else {
		if ( '' !== $membership['period'] ) {
			$lines[] = sprintf(
				/* translators: %s: membership period (e.g. 2026-2029). */
				__( 'Jäsenkausi: %s', 'rytkoset-theme' ),
				$membership['period']
			);
		}

		$expires_display = rytkoset_theme_get_user_membership_expires_display( $membership['expires'] );

		if ( '' !== $expires_display ) {
			$lines[] = sprintf(
				/* translators: %s: expiry date in d.m.Y format. */
				__( 'Voimassa asti: %s', 'rytkoset-theme' ),
				$expires_display
			);
		}
	}

	$contact_email = rytkoset_theme_get_contact_email();

	$lines[] = '';

	if ( is_email( $contact_email ) ) {
		$lines[] = sprintf(
			/* translators: %s: association contact email address. */
			__( 'Kysymyksissä voit olla yhteydessä: %s', 'rytkoset-theme' ),
			$contact_email
		);
		$lines[] = '';
	}

	$lines[] = __( 'Terveisin', 'rytkoset-theme' );
	$lines[] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	return wp_mail(
		$user->user_email,
		$subject,
		implode( "\n", $lines ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Returns the nonce action used for the user membership profile fields.
 *
 * @param int $user_id The edited user ID.
 * @return string
 */
function rytkoset_theme_get_user_membership_nonce_action( $user_id ) {
	return 'rytkoset_save_user_membership_' . (int) $user_id;
}

/**
 * Returns the number of editable family member rows shown in the profile UI.
 *
 * @return int
 */
function rytkoset_theme_get_family_members_profile_row_count() {
	return 6;
}

/**
 * Returns a profile form field name for family member rows.
 *
 * @param int    $index Row index.
 * @param string $field Row field key.
 * @return string
 */
function rytkoset_theme_get_family_member_profile_field_name( $index, $field ) {
	return sprintf( 'rytkoset_family_members[%d][%s]', absint( $index ), sanitize_key( $field ) );
}

/**
 * Sanitizes family member rows from the user profile form.
 *
 * @param mixed $input Raw profile input.
 * @return array<int, array{name:string,email:string,linked_user_id:int,status:string,source_order_id:int}>
 */
function rytkoset_theme_sanitize_family_members_profile_input( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}

	$rows = array();

	foreach ( $input as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$rows[] = array(
			'name'            => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '',
			'email'           => isset( $row['email'] ) ? sanitize_text_field( $row['email'] ) : '',
			'linked_user_id'  => isset( $row['linked_user_id'] ) ? absint( $row['linked_user_id'] ) : 0,
			'status'          => isset( $row['status'] ) ? sanitize_key( $row['status'] ) : '',
			'source_order_id' => isset( $row['source_order_id'] ) ? absint( $row['source_order_id'] ) : 0,
		);
	}

	return $rows;
}

/**
 * Validates profile-submitted family member rows before WordPress saves the user.
 *
 * The save callback still calls the same update helper as the final guard. This validation hook
 * makes duplicate/conflicting rows visible to admins as a normal profile error instead of failing
 * silently.
 *
 * @param WP_Error $errors Error object shown by WordPress.
 * @param bool     $update Whether this is an existing user update.
 * @param WP_User  $user   User object being edited.
 * @return void
 */
function rytkoset_theme_validate_user_membership_profile_fields( $errors, $update, $user ) {
	if ( ! $errors instanceof WP_Error || ! $user instanceof WP_User ) {
		return;
	}

	$user_id = absint( $user->ID );

	if ( $user_id <= 0 || ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_user_membership_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rytkoset_user_membership_nonce'] ) ),
			rytkoset_theme_get_user_membership_nonce_action( $user_id )
		)
	) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_family_members'] ) ) {
		return;
	}

	$family_members = rytkoset_theme_sanitize_family_members_profile_input(
		wp_unslash( $_POST['rytkoset_family_members'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rows are sanitized field-by-field in the profile input helper.
	);
	$family_members = rytkoset_theme_normalize_family_members( $family_members );
	$family_members = rytkoset_theme_supersede_removed_family_member_rows( $family_members );
	$family_members = rytkoset_theme_resolve_family_member_accounts( $family_members );
	$result         = rytkoset_theme_validate_family_members( $user_id, $family_members );

	if ( ! is_wp_error( $result ) ) {
		return;
	}

	foreach ( $result->get_error_codes() as $code ) {
		$errors->add( $code, $result->get_error_message( $code ) );
	}
}
add_action( 'user_profile_update_errors', 'rytkoset_theme_validate_user_membership_profile_fields', 10, 3 );

/**
 * Renders the membership section on a user profile screen.
 *
 * Only shown to administrators who can manage users; the membership status is set against the
 * association's external (paper) register, not chosen by the member themselves.
 *
 * @param WP_User $user The user being edited.
 * @return void
 */
function rytkoset_theme_render_user_membership_fields( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! $user instanceof WP_User ) {
		return;
	}

	$type = rytkoset_theme_normalize_user_membership_type(
		(string) get_user_meta( $user->ID, rytkoset_theme_get_user_membership_type_meta_key(), true )
	);

	$period          = (string) get_user_meta( $user->ID, rytkoset_theme_get_user_membership_period_meta_key(), true );
	$expires         = (string) get_user_meta( $user->ID, rytkoset_theme_get_user_membership_expires_meta_key(), true );
	$expires_display = rytkoset_theme_get_user_membership_expires_display( $expires );

	$type_field    = rytkoset_theme_get_user_membership_type_meta_key();
	$period_field  = rytkoset_theme_get_user_membership_period_meta_key();
	$expires_field = rytkoset_theme_get_user_membership_expires_meta_key();

	wp_nonce_field(
		rytkoset_theme_get_user_membership_nonce_action( $user->ID ),
		'rytkoset_user_membership_nonce'
	);
	?>
	<h2><?php esc_html_e( 'Jäsenyys', 'rytkoset-theme' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Aseta jäsenyyden tila sukuseuran jäsenrekisterin perusteella. Vuosi- ja perhejäsen on aktiivinen vain voimassaolopäivään asti; ainaisjäsen on aina aktiivinen.', 'rytkoset-theme' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th>
				<label for="<?php echo esc_attr( $type_field ); ?>"><?php esc_html_e( 'Jäsenyyden tyyppi', 'rytkoset-theme' ); ?></label>
			</th>
			<td>
				<select name="<?php echo esc_attr( $type_field ); ?>" id="<?php echo esc_attr( $type_field ); ?>">
					<option value="" <?php selected( '', $type ); ?>><?php esc_html_e( 'Ei jäsen', 'rytkoset-theme' ); ?></option>
					<?php foreach ( rytkoset_theme_get_user_membership_type_options() as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, $type ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th>
				<label for="<?php echo esc_attr( $period_field ); ?>"><?php esc_html_e( 'Jäsenkausi', 'rytkoset-theme' ); ?></label>
			</th>
			<td>
				<input type="text" name="<?php echo esc_attr( $period_field ); ?>" id="<?php echo esc_attr( $period_field ); ?>"
					value="<?php echo esc_attr( $period ); ?>" class="regular-text" placeholder="2026-2029" pattern="\d{4}-\d{4}" />
				<p class="description"><?php esc_html_e( 'Vuosi- ja perhejäsenelle, muotoa 2026-2029. Ainaisjäsenelle voi jättää tyhjäksi.', 'rytkoset-theme' ); ?></p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="<?php echo esc_attr( $expires_field ); ?>"><?php esc_html_e( 'Voimassa asti', 'rytkoset-theme' ); ?></label>
			</th>
			<td>
				<input type="text" name="<?php echo esc_attr( $expires_field ); ?>" id="<?php echo esc_attr( $expires_field ); ?>"
					value="<?php echo esc_attr( $expires_display ); ?>" class="regular-text" placeholder="pp.kk.vvvv" inputmode="numeric" autocomplete="off" />
				<p class="description"><?php esc_html_e( 'Vuosi- ja perhejäsenen jäsenyys on aktiivinen tähän päivään asti. Muotoa pp.kk.vvvv, esim. 31.12.2029. Ilman päivää jäsenyyttä ei tulkita aktiiviseksi. Ainaisjäsenelle kenttää ei tarvita.', 'rytkoset-theme' ); ?></p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Perhejäsenet', 'rytkoset-theme' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Perhejäsenlistaa käytetään vain, kun päätilillä on aktiivinen perhejäsenyys. Linkitetty käyttäjä saa jäsenedut päätilin kautta vain, kun rivin tila on aktiivinen.', 'rytkoset-theme' ); ?>
	</p>
	<table class="widefat striped" role="presentation">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Nimi', 'rytkoset-theme' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Käyttäjä-ID', 'rytkoset-theme' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tilaus-ID', 'rytkoset-theme' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$family_members = rytkoset_theme_get_family_members( $user->ID );
			$row_count      = max( rytkoset_theme_get_family_members_profile_row_count(), count( $family_members ) + 1 );
			$status_options = rytkoset_theme_get_family_member_status_options();

			for ( $index = 0; $index < $row_count; $index++ ) :
				$member = isset( $family_members[ $index ] )
					? $family_members[ $index ]
					: array(
						'name'            => '',
						'email'           => '',
						'linked_user_id'  => 0,
						'status'          => 'pending_account',
						'source_order_id' => 0,
					);
				?>
				<tr>
					<td>
						<input type="text" class="regular-text" name="<?php echo esc_attr( rytkoset_theme_get_family_member_profile_field_name( $index, 'name' ) ); ?>"
							value="<?php echo esc_attr( $member['name'] ); ?>" autocomplete="off" />
					</td>
					<td>
						<input type="email" class="regular-text" name="<?php echo esc_attr( rytkoset_theme_get_family_member_profile_field_name( $index, 'email' ) ); ?>"
							value="<?php echo esc_attr( $member['email'] ); ?>" autocomplete="off" />
					</td>
					<td>
						<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( rytkoset_theme_get_family_member_profile_field_name( $index, 'linked_user_id' ) ); ?>"
							value="<?php echo esc_attr( (string) $member['linked_user_id'] ); ?>" />
					</td>
					<td>
						<select name="<?php echo esc_attr( rytkoset_theme_get_family_member_profile_field_name( $index, 'status' ) ); ?>">
							<?php foreach ( $status_options as $status_value => $status_label ) : ?>
								<option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $status_value, $member['status'] ); ?>>
									<?php echo esc_html( $status_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( rytkoset_theme_get_family_member_profile_field_name( $index, 'source_order_id' ) ); ?>"
							value="<?php echo esc_attr( (string) $member['source_order_id'] ); ?>" />
					</td>
				</tr>
			<?php endfor; ?>
		</tbody>
	</table>
	<p class="description">
		<?php esc_html_e( 'Tyhjät rivit ohitetaan. Jos linkitetty käyttäjä kuuluu jo toiseen perhejäsenyyteen, tallennusapuri estää uuden linkityksen.', 'rytkoset-theme' ); ?>
	</p>
	<?php
}
add_action( 'show_user_profile', 'rytkoset_theme_render_user_membership_fields' );
add_action( 'edit_user_profile', 'rytkoset_theme_render_user_membership_fields' );

/**
 * Saves the membership section from a user profile screen.
 *
 * @param int $user_id The edited user ID.
 * @return void
 */
function rytkoset_theme_save_user_membership_fields( $user_id ) {
	$user_id = (int) $user_id;

	if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_user_membership_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rytkoset_user_membership_nonce'] ) ),
			rytkoset_theme_get_user_membership_nonce_action( $user_id )
		)
	) {
		return;
	}

	// Capture the active state before changes so the confirmation email is sent only on a
	// non-active → active transition (#390). #302 reuses the same send helper on the order path.
	$was_active = rytkoset_theme_user_has_own_active_membership( $user_id );

	$type_field    = rytkoset_theme_get_user_membership_type_meta_key();
	$period_field  = rytkoset_theme_get_user_membership_period_meta_key();
	$expires_field = rytkoset_theme_get_user_membership_expires_meta_key();

	$type = isset( $_POST[ $type_field ] )
		? rytkoset_theme_normalize_user_membership_type( wp_unslash( $_POST[ $type_field ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalizer returns only allowlisted membership types.
		: '';

	if ( '' === $type ) {
		delete_user_meta( $user_id, $type_field );
		delete_user_meta( $user_id, $period_field );
		delete_user_meta( $user_id, $expires_field );
	} else {
		update_user_meta( $user_id, $type_field, $type );

		if ( 'lifetime' === $type ) {
			delete_user_meta( $user_id, $period_field );
			delete_user_meta( $user_id, $expires_field );
		} else {
			$period  = isset( $_POST[ $period_field ] )
				? rytkoset_theme_sanitize_user_membership_period( wp_unslash( $_POST[ $period_field ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizer validates and returns a normalized period string.
				: '';
			$expires = isset( $_POST[ $expires_field ] )
				? rytkoset_theme_sanitize_user_membership_expires( wp_unslash( $_POST[ $expires_field ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizer validates and returns a normalized date string.
				: '';

			update_user_meta( $user_id, $period_field, $period );
			update_user_meta( $user_id, $expires_field, $expires );
		}
	}

	if ( isset( $_POST['rytkoset_family_members'] ) ) {
		$family_members = rytkoset_theme_sanitize_family_members_profile_input(
			wp_unslash( $_POST['rytkoset_family_members'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rows are sanitized field-by-field in the profile input helper.
		);

		rytkoset_theme_update_family_members( $user_id, $family_members );
	}

	// Notify the member only when the membership just became active; re-saving an already active
	// (or still inactive) membership must not resend the confirmation (#390).
	if ( ! $was_active && rytkoset_theme_user_has_own_active_membership( $user_id ) ) {
		rytkoset_theme_send_membership_confirmation_email( $user_id );
	}
}
add_action( 'personal_options_update', 'rytkoset_theme_save_user_membership_fields' );
add_action( 'edit_user_profile_update', 'rytkoset_theme_save_user_membership_fields' );
