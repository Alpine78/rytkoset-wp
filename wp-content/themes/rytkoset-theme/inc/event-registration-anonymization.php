<?php
/**
 * Scheduled anonymization of free event registrations (#580).
 *
 * The privacy statement promises that event registration data is deleted or
 * anonymized at the latest 12 months after the event. #250 built the manual
 * per-event mass anonymization and Privacy Tools paths in
 * inc/event-registration-privacy.php; this module adds a daily WP-Cron run that
 * enforces the 12-month deadline automatically, reusing the same anonymization
 * path so the manual tool and Privacy Tools keep working unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the option name storing the anonymization run state.
 *
 * @return string
 */
function rytkoset_theme_get_event_registration_anonymization_option_name() {
	return 'rytkoset_event_registration_anonymization';
}

/**
 * Returns the daily anonymization cron hook.
 *
 * @return string
 */
function rytkoset_theme_get_event_registration_anonymization_cron_hook() {
	return 'rytkoset_theme_event_registration_anonymization';
}

/**
 * Returns the retention window, in months, before a free registration is anonymized.
 *
 * Filterable via `rytkoset_theme_event_registration_anonymization_months`. The
 * default of 12 matches the published privacy statement. Values below zero are
 * clamped to zero.
 *
 * @return int
 */
function rytkoset_theme_get_event_registration_anonymization_months() {
	$months = (int) apply_filters( 'rytkoset_theme_event_registration_anonymization_months', 12 );

	return max( 0, $months );
}

/**
 * Determines whether a free event's registrations are due for anonymization.
 *
 * Pure decision helper: the retention window starts at the end of the event day
 * (the same cutoff the public registration form uses) and runs for `$months`
 * months. Returns false for an invalid event date or reference so callers fail
 * closed and never anonymize based on missing data.
 *
 * @param string            $event_date Event date in YYYY-MM-DD format.
 * @param DateTimeImmutable $reference  Reference "now" in the site timezone.
 * @param int               $months     Retention window in months.
 * @return bool
 */
function rytkoset_theme_event_registration_anonymization_is_due( $event_date, $reference, $months ) {
	if ( ! $reference instanceof DateTimeImmutable ) {
		return false;
	}

	if ( ! is_string( $event_date ) || ! rytkoset_theme_is_valid_event_date( $event_date ) ) {
		return false;
	}

	$event_day = DateTimeImmutable::createFromFormat( '!Y-m-d', $event_date, wp_timezone() );

	if ( ! $event_day instanceof DateTimeImmutable ) {
		return false;
	}

	$months = max( 0, (int) $months );

	// End of the event day, then the retention window.
	$cutoff = $event_day->modify( '+1 day' )->setTime( 0, 0, 0 )->modify( '+' . $months . ' months' );

	return $reference >= $cutoff;
}

/**
 * Returns the default anonymization run state.
 *
 * @return array
 */
function rytkoset_theme_get_default_event_registration_anonymization_state() {
	return array(
		'last_run_at'            => '',
		'last_anonymized'        => 0,
		'total_anonymized'       => 0,
		'missing_date_event_ids' => array(),
	);
}

/**
 * Normalizes a stored anonymization run state.
 *
 * Pure helper: never stores personal data, only counts, a timestamp and the IDs
 * of events that still need a valid date.
 *
 * @param mixed $state Raw stored state.
 * @return array
 */
function rytkoset_theme_normalize_event_registration_anonymization_state( $state ) {
	$defaults = rytkoset_theme_get_default_event_registration_anonymization_state();

	if ( ! is_array( $state ) ) {
		return $defaults;
	}

	$missing = array();

	if ( isset( $state['missing_date_event_ids'] ) && is_array( $state['missing_date_event_ids'] ) ) {
		foreach ( $state['missing_date_event_ids'] as $event_id ) {
			$event_id = absint( $event_id );

			if ( $event_id > 0 ) {
				$missing[ $event_id ] = $event_id;
			}
		}
	}

	return array(
		'last_run_at'            => isset( $state['last_run_at'] ) && is_string( $state['last_run_at'] ) ? $state['last_run_at'] : '',
		'last_anonymized'        => isset( $state['last_anonymized'] ) ? max( 0, (int) $state['last_anonymized'] ) : 0,
		'total_anonymized'       => isset( $state['total_anonymized'] ) ? max( 0, (int) $state['total_anonymized'] ) : 0,
		'missing_date_event_ids' => array_values( $missing ),
	);
}

/**
 * Returns the current anonymization run state.
 *
 * @return array
 */
function rytkoset_theme_get_event_registration_anonymization_state() {
	return rytkoset_theme_normalize_event_registration_anonymization_state(
		get_option( rytkoset_theme_get_event_registration_anonymization_option_name(), array() )
	);
}

/**
 * Records the outcome of an anonymization run.
 *
 * @param int   $anonymized             Number of registrations anonymized in this run.
 * @param int[] $missing_date_event_ids Event IDs skipped for lack of a valid date.
 * @return array Saved state.
 */
function rytkoset_theme_record_event_registration_anonymization_run( $anonymized, $missing_date_event_ids ) {
	$previous   = rytkoset_theme_get_event_registration_anonymization_state();
	$anonymized = max( 0, (int) $anonymized );

	$state = rytkoset_theme_normalize_event_registration_anonymization_state(
		array(
			'last_run_at'            => current_time( 'mysql' ),
			'last_anonymized'        => $anonymized,
			'total_anonymized'       => $previous['total_anonymized'] + $anonymized,
			'missing_date_event_ids' => (array) $missing_date_event_ids,
		)
	);

	update_option( rytkoset_theme_get_event_registration_anonymization_option_name(), $state, false );

	return $state;
}

/**
 * Returns distinct event IDs that still have non-anonymized free registrations.
 *
 * @return int[]
 */
function rytkoset_theme_get_event_ids_with_pending_registration_anonymization() {
	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

	$registration_ids = get_posts(
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
				array(
					'key'     => $meta_keys['anonymized_at'],
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	$event_ids = array();

	foreach ( $registration_ids as $registration_id ) {
		$event_id = absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'event_id' ) );

		if ( $event_id > 0 ) {
			$event_ids[ $event_id ] = $event_id;
		}
	}

	return array_values( $event_ids );
}

/**
 * Runs the scheduled event registration anonymization.
 *
 * Idempotent: rows already carrying an anonymization timestamp are excluded by
 * the query, so re-running never touches them and never blocks or repeats a
 * data-subject request. Events lacking a valid date are skipped and reported to
 * the admin via the run state.
 *
 * @return array {
 *     @type int   $anonymized             Number of registrations anonymized.
 *     @type int[] $missing_date_event_ids Event IDs skipped for lack of a valid date.
 * }
 */
function rytkoset_theme_run_event_registration_anonymization() {
	$months                 = rytkoset_theme_get_event_registration_anonymization_months();
	$reference              = current_datetime();
	$event_ids              = rytkoset_theme_get_event_ids_with_pending_registration_anonymization();
	$anonymized             = 0;
	$missing_date_event_ids = array();

	foreach ( $event_ids as $event_id ) {
		// Skip registrations whose event no longer exists; nothing an admin can fix.
		if ( 'rytkoset_event' !== get_post_type( $event_id ) ) {
			continue;
		}

		$event_date = rytkoset_theme_get_event_date_raw( $event_id );

		if ( '' === $event_date ) {
			$missing_date_event_ids[] = $event_id;
			continue;
		}

		if ( ! rytkoset_theme_event_registration_anonymization_is_due( $event_date, $reference, $months ) ) {
			continue;
		}

		foreach ( rytkoset_theme_get_event_registration_ids_for_event_anonymization( $event_id ) as $registration_id ) {
			if ( rytkoset_theme_anonymize_event_registration( $registration_id ) ) {
				++$anonymized;
			}
		}
	}

	rytkoset_theme_record_event_registration_anonymization_run( $anonymized, $missing_date_event_ids );

	return array(
		'anonymized'             => $anonymized,
		'missing_date_event_ids' => $missing_date_event_ids,
	);
}
add_action( 'rytkoset_theme_event_registration_anonymization', 'rytkoset_theme_run_event_registration_anonymization' );

/**
 * Ensures the daily anonymization cron event exists.
 *
 * @return void
 */
function rytkoset_theme_ensure_event_registration_anonymization_cron_scheduled() {
	$hook = rytkoset_theme_get_event_registration_anonymization_cron_hook();

	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
	}
}
add_action( 'init', 'rytkoset_theme_ensure_event_registration_anonymization_cron_scheduled' );

/**
 * Clears the anonymization cron event when switching themes.
 *
 * @return void
 */
function rytkoset_theme_clear_event_registration_anonymization_cron() {
	wp_clear_scheduled_hook( rytkoset_theme_get_event_registration_anonymization_cron_hook() );
}
add_action( 'switch_theme', 'rytkoset_theme_clear_event_registration_anonymization_cron' );

/**
 * Shows an admin notice listing events that have registrations but no valid date.
 *
 * These events cannot be anonymized on schedule until an admin adds the date, so
 * the run reports them here for the users who manage registrations.
 *
 * @return void
 */
function rytkoset_theme_event_registration_anonymization_admin_notice() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		return;
	}

	$state = rytkoset_theme_get_event_registration_anonymization_state();

	if ( empty( $state['missing_date_event_ids'] ) ) {
		return;
	}

	$links = array();

	foreach ( $state['missing_date_event_ids'] as $event_id ) {
		if ( 'rytkoset_event' !== get_post_type( $event_id ) ) {
			continue;
		}

		$title     = get_the_title( $event_id );
		$title     = '' !== $title ? $title : sprintf( '#%d', $event_id );
		$edit_link = get_edit_post_link( $event_id );

		if ( $edit_link ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html( $title ) );
		} else {
			$links[] = esc_html( $title );
		}
	}

	if ( empty( $links ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p><p>%s</p></div>',
		esc_html__( 'Näiltä tapahtumilta puuttuu kelvollinen päivämäärä, joten niiden ilmoittautumisia ei voida anonymisoida automaattisesti. Lisää tapahtuman päivämäärä.', 'rytkoset-theme' ),
		wp_kses( implode( ', ', $links ), array( 'a' => array( 'href' => array() ) ) )
	);
}
add_action( 'admin_notices', 'rytkoset_theme_event_registration_anonymization_admin_notice' );
