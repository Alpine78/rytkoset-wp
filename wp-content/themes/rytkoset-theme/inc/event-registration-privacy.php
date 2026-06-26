<?php
/**
 * GDPR export and anonymization helpers for event registrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the display name used for anonymized event registrations.
 *
 * @return string
 */
function rytkoset_theme_get_anonymized_event_registration_name() {
	return __( 'Anonymisoitu osallistuja', 'rytkoset-theme' );
}

/**
 * Returns registration IDs for a given participant email.
 *
 * @param string $email    Participant email address.
 * @param int    $page     Result page.
 * @param int    $per_page Results per page.
 * @return int[]
 */
function rytkoset_theme_get_event_registration_ids_by_email( $email, $page = 1, $per_page = 50 ) {
	$email    = sanitize_email( $email );
	$page     = max( 1, absint( $page ) );
	$per_page = max( 1, absint( $per_page ) );

	if ( '' === $email || ! is_email( $email ) ) {
		return array();
	}

	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

	return get_posts(
		array(
			'post_type'              => 'event_registration',
			'post_status'            => 'any',
			'posts_per_page'         => $per_page,
			'offset'                 => ( $page - 1 ) * $per_page,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						array(
							'key'   => $meta_keys['email'],
							'value' => $email,
						),
					),
		)
	);
}

/**
 * Returns non-anonymized free registration IDs for an event.
 *
 * @param int $event_id Event post ID.
 * @return int[]
 */
function rytkoset_theme_get_event_registration_ids_for_event_anonymization( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return array();
	}

	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

	return get_posts(
		array(
			'post_type'              => 'event_registration',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => $meta_keys['event_id'],
					'value' => $event_id,
				),
				array(
					'key'     => $meta_keys['anonymized_at'],
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}

/**
 * Anonymizes a single event registration while keeping event and status metadata.
 *
 * @param int $registration_id Registration post ID.
 * @return bool
 */
function rytkoset_theme_anonymize_event_registration( $registration_id ) {
	$registration_id = absint( $registration_id );

	if ( $registration_id <= 0 || 'event_registration' !== get_post_type( $registration_id ) ) {
		return false;
	}

	$meta_keys       = rytkoset_theme_get_event_registration_meta_keys();
	$event_id        = absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'event_id' ) );
	$anonymized_name = rytkoset_theme_get_anonymized_event_registration_name();
	$title           = rytkoset_theme_build_event_registration_title( $anonymized_name, $event_id );
	$updated         = wp_update_post(
		array(
			'ID'         => $registration_id,
			'post_title' => $title,
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		return false;
	}

	update_post_meta( $registration_id, $meta_keys['name'], $anonymized_name );
	delete_post_meta( $registration_id, $meta_keys['email'] );
	delete_post_meta( $registration_id, $meta_keys['diet'] );
	delete_post_meta( $registration_id, $meta_keys['notes'] );
	update_post_meta( $registration_id, $meta_keys['anonymized_at'], current_time( 'mysql' ) );

	return true;
}

/**
 * Formats a stored GDPR consent timestamp for exports.
 *
 * @param string $timestamp Raw timestamp.
 * @return string
 */
function rytkoset_theme_format_event_registration_consent_timestamp( $timestamp ) {
	$timestamp = absint( $timestamp );

	if ( $timestamp <= 0 ) {
		return '';
	}

	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Registers the event registration personal data exporter.
 *
 * @param array $exporters Registered exporters.
 * @return array
 */
function rytkoset_theme_register_event_registration_personal_data_exporter( $exporters ) {
	$exporters['rytkoset-event-registrations'] = array(
		'exporter_friendly_name' => __( 'Tapahtumailmoittautumiset', 'rytkoset-theme' ),
		'callback'               => 'rytkoset_theme_export_event_registration_personal_data',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'rytkoset_theme_register_event_registration_personal_data_exporter' );

/**
 * Exports event registration personal data for a participant email.
 *
 * @param string $email_address Email address.
 * @param int    $page          Result page.
 * @return array
 */
function rytkoset_theme_export_event_registration_personal_data( $email_address, $page = 1 ) {
	$per_page         = 50;
	$registration_ids = rytkoset_theme_get_event_registration_ids_by_email( $email_address, $page, $per_page );
	$statuses         = rytkoset_theme_get_event_registration_statuses();
	$items            = array();

	foreach ( $registration_ids as $registration_id ) {
		$event_id      = absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'event_id' ) );
		$status        = rytkoset_theme_get_event_registration_meta( $registration_id, 'status' );
		$consent       = rytkoset_theme_get_event_registration_meta( $registration_id, 'gdpr_consent' );
		$consent_label = rytkoset_theme_format_event_registration_consent_timestamp( $consent );
		$data          = array(
			array(
				'name'  => __( 'Tapahtuma', 'rytkoset-theme' ),
				'value' => $event_id > 0 ? get_the_title( $event_id ) : '',
			),
			array(
				'name'  => __( 'Nimi', 'rytkoset-theme' ),
				'value' => rytkoset_theme_get_event_registration_meta( $registration_id, 'name' ),
			),
			array(
				'name'  => __( 'Sähköposti', 'rytkoset-theme' ),
				'value' => rytkoset_theme_get_event_registration_meta( $registration_id, 'email' ),
			),
			array(
				'name'  => __( 'Ruokarajoitteet ja allergiat', 'rytkoset-theme' ),
				'value' => rytkoset_theme_get_event_registration_meta( $registration_id, 'diet' ),
			),
			array(
				'name'  => __( 'Lisätieto', 'rytkoset-theme' ),
				'value' => rytkoset_theme_get_event_registration_meta( $registration_id, 'notes' ),
			),
			array(
				'name'  => __( 'Tila', 'rytkoset-theme' ),
				'value' => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status,
			),
			array(
				'name'  => __( 'Ilmoittautumispäivä', 'rytkoset-theme' ),
				'value' => get_the_date( '', $registration_id ) . ' ' . get_the_time( '', $registration_id ),
			),
			array(
				'name'  => __( 'Tietosuojasuostumus', 'rytkoset-theme' ),
				'value' => $consent_label,
			),
		);

		$pickup_point    = rytkoset_theme_get_event_registration_meta( $registration_id, 'pickup_point' );
		$passenger_count = rytkoset_theme_get_event_registration_meta( $registration_id, 'passenger_count' );

		if ( '' !== $pickup_point ) {
			$data[] = array(
				'name'  => __( 'Lähtöpaikka', 'rytkoset-theme' ),
				'value' => $pickup_point,
			);
		}

		if ( '' !== $passenger_count ) {
			$data[] = array(
				'name'  => __( 'Matkustajien määrä', 'rytkoset-theme' ),
				'value' => $passenger_count,
			);
		}

		$items[] = array(
			'group_id'    => 'rytkoset-event-registrations',
			'group_label' => __( 'Tapahtumailmoittautumiset', 'rytkoset-theme' ),
			'item_id'     => 'event-registration-' . $registration_id,
			'data'        => $data,
		);
	}

	return array(
		'data' => $items,
		'done' => count( $registration_ids ) < $per_page,
	);
}

/**
 * Registers the event registration personal data eraser.
 *
 * @param array $erasers Registered erasers.
 * @return array
 */
function rytkoset_theme_register_event_registration_personal_data_eraser( $erasers ) {
	$erasers['rytkoset-event-registrations'] = array(
		'eraser_friendly_name' => __( 'Tapahtumailmoittautumiset', 'rytkoset-theme' ),
		'callback'             => 'rytkoset_theme_erase_event_registration_personal_data',
	);

	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'rytkoset_theme_register_event_registration_personal_data_eraser' );

/**
 * Erases event registration personal data for a participant email by anonymizing rows.
 *
 * @param string $email_address Email address.
 * @param int    $page          Result page. Intentionally ignored to avoid skipped rows after anonymization.
 * @return array
 */
function rytkoset_theme_erase_event_registration_personal_data( $email_address, $page = 1 ) {
	unset( $page );

	$per_page         = 50;
	$registration_ids = rytkoset_theme_get_event_registration_ids_by_email( $email_address, 1, $per_page );
	$removed          = 0;
	$retained         = 0;
	$messages         = array();

	foreach ( $registration_ids as $registration_id ) {
		if ( rytkoset_theme_anonymize_event_registration( $registration_id ) ) {
			++$removed;
			continue;
		}

		++$retained;
	}

	if ( $removed > 0 ) {
		$messages[] = sprintf(
			/* translators: %d: anonymized registration count */
			_n( '%d tapahtumailmoittautuminen anonymisoitiin.', '%d tapahtumailmoittautumista anonymisoitiin.', $removed, 'rytkoset-theme' ),
			$removed
		);
	}

	if ( $retained > 0 ) {
		$messages[] = 1 === $retained
			? __( 'Yhtä tapahtumailmoittautumista ei voitu anonymisoida.', 'rytkoset-theme' )
			: sprintf(
				/* translators: %d: retained registration count */
				__( '%d tapahtumailmoittautumista ei voitu anonymisoida.', 'rytkoset-theme' ),
				$retained
			);
	}

	return array(
		'items_removed'  => $removed > 0,
		'items_retained' => $retained > 0,
		'messages'       => $messages,
		'done'           => count( $registration_ids ) < $per_page,
	);
}

/**
 * Handles event-specific free registration mass anonymization from the participants admin page.
 *
 * @return void
 */
function rytkoset_theme_handle_event_free_registrations_anonymization() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta anonymisoida ilmoittautumisia.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_anonymize_event_free_registrations', 'rytkoset_event_anonymize_nonce' );

	$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
	$confirm  = isset( $_POST['confirm_anonymization'] ) && '1' === wp_unslash( $_POST['confirm_anonymization'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact allowlisted comparison.
	$notice   = 'error';
	$count    = 0;

	if ( $event_id > 0 && 'rytkoset_event' === get_post_type( $event_id ) && $confirm ) {
		$registration_ids = rytkoset_theme_get_event_registration_ids_for_event_anonymization( $event_id );

		foreach ( $registration_ids as $registration_id ) {
			if ( rytkoset_theme_anonymize_event_registration( $registration_id ) ) {
				++$count;
			}
		}

		$notice = 'success';
	} elseif ( ! $confirm ) {
		$notice = 'missing_confirmation';
	}

	$redirect_url = add_query_arg(
		array(
			'post_type'                 => 'rytkoset_event',
			'page'                      => 'rytkoset-event-participants',
			'event_id'                  => $event_id,
			'rytkoset_anonymize_notice' => $notice,
			'rytkoset_anonymize_count'  => $count,
		),
		admin_url( 'edit.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_rytkoset_anonymize_event_free_registrations', 'rytkoset_theme_handle_event_free_registrations_anonymization' );
