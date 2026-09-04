<?php
/**
 * Massaviestintä tapahtuman osallistujille (admin-sivu + lähetyshandler).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns deduplicated recipient list for a given event/status filter.
 *
 * Each row in the participants list is normalized to a single email recipient.
 * Falls back to contact_email when the participant's own email is empty.
 * Returns also a count of rows that were skipped because no usable address was found.
 *
 * @param int    $event_id      Event ID or 0 for all events.
 * @param string $status_filter Optional status filter.
 * @return array{recipients: array<string, array{name: string, event_title: string}>, skipped: int}
 */
function rytkoset_theme_get_event_messaging_recipients( $event_id, $status_filter = '' ) {
	$event_id = absint( $event_id );

	$rows = $event_id > 0
		? rytkoset_theme_get_event_participants( $event_id, $status_filter )
		: rytkoset_theme_get_all_events_participants( $status_filter );

	// Never message a cancelled/refunded/failed order, and never message a
	// cancelled free registration unless the admin explicitly picked the
	// "Peruttu" filter. This same trimmed set is the safe base for a future
	// feedback request.
	$rows = rytkoset_theme_filter_active_event_participants( $rows, $status_filter );

	$recipients = array();
	$skipped    = 0;

	foreach ( $rows as $row ) {
		$email = trim( (string) ( $row['email'] ?? '' ) );

		if ( '' === $email ) {
			$email = trim( (string) ( $row['contact_email'] ?? '' ) );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			++$skipped;
			continue;
		}

		$email_key = strtolower( $email );

		if ( isset( $recipients[ $email_key ] ) ) {
			continue;
		}

		$name = trim( (string) ( $row['name'] ?? '' ) );

		if ( '' === $name ) {
			$name = trim( (string) ( $row['contact_name'] ?? '' ) );
		}

		$recipients[ $email_key ] = array(
			'email'       => $email,
			'name'        => $name,
			'event_title' => (string) ( $row['event_title'] ?? '' ),
		);
	}

	return array(
		'recipients' => $recipients,
		'skipped'    => $skipped,
	);
}

/**
 * Replaces placeholders in a message body.
 *
 * `{palautelinkki}` resolves to a single event's public feedback link (#666).
 * It is computed once per job (not per recipient) since the link carries no
 * per-recipient token, and resolves to an empty string for a "kaikki
 * tapahtumat" broadcast (`$feedback_link` left blank by the caller) — a single
 * link cannot represent multiple events.
 *
 * @param string $body          Message body.
 * @param string $name          Recipient name.
 * @param string $event_title   Event title.
 * @param string $feedback_link Optional resolved feedback survey URL for the job's event.
 * @return string
 */
function rytkoset_theme_personalize_event_message( $body, $name, $event_title, $feedback_link = '' ) {
	return str_replace(
		array( '{nimi}', '{tapahtuma}', '{palautelinkki}' ),
		array( $name, $event_title, $feedback_link ),
		(string) $body
	);
}

/**
 * Returns the option key used for the messaging log.
 *
 * @return string
 */
function rytkoset_theme_get_event_messaging_log_option_key() {
	return 'rytkoset_event_messaging_log';
}

/**
 * Appends an entry to the messaging log option (FIFO, max 50 entries).
 *
 * @param array $entry Log entry payload.
 * @return void
 */
function rytkoset_theme_append_event_messaging_log( $entry ) {
	$key = rytkoset_theme_get_event_messaging_log_option_key();
	$log = get_option( $key, array() );

	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$log[] = $entry;

	if ( count( $log ) > 50 ) {
		$log = array_slice( $log, -50 );
	}

	update_option( $key, $log, false );
}

/**
 * Returns the latest messaging log entries (newest first).
 *
 * @param int $limit Maximum number of entries to return.
 * @return array
 */
function rytkoset_theme_get_event_messaging_log( $limit = 20 ) {
	$log = get_option( rytkoset_theme_get_event_messaging_log_option_key(), array() );

	if ( ! is_array( $log ) || empty( $log ) ) {
		return array();
	}

	$log = array_reverse( $log );

	if ( $limit > 0 && count( $log ) > $limit ) {
		$log = array_slice( $log, 0, $limit );
	}

	return $log;
}

/**
 * Returns the hourly send limit for event participant messaging.
 *
 * @return int
 */
function rytkoset_theme_get_event_messaging_hourly_limit() {
	return 18;
}

/**
 * Returns the option key used for the messaging queue.
 *
 * @return string
 */
function rytkoset_theme_get_event_messaging_queue_option_key() {
	return 'rytkoset_event_messaging_queue';
}

/**
 * Returns the option key used for rolling send attempt timestamps.
 *
 * @return string
 */
function rytkoset_theme_get_event_messaging_send_attempts_option_key() {
	return 'rytkoset_event_messaging_send_attempts';
}

/**
 * Returns the cron hook name used for processing the messaging queue.
 *
 * @return string
 */
function rytkoset_theme_get_event_messaging_cron_hook() {
	return 'rytkoset_process_event_messaging_queue';
}

/**
 * Returns the event messaging queue.
 *
 * @return array
 */
function rytkoset_theme_get_event_messaging_queue() {
	$queue = get_option( rytkoset_theme_get_event_messaging_queue_option_key(), array() );

	if ( ! is_array( $queue ) ) {
		return array();
	}

	return array_values( $queue );
}

/**
 * Stores the event messaging queue as a non-autoloaded option.
 *
 * @param array $queue Queue entries.
 * @return void
 */
function rytkoset_theme_update_event_messaging_queue( $queue ) {
	update_option( rytkoset_theme_get_event_messaging_queue_option_key(), array_values( $queue ), false );
}

/**
 * Returns pruned send attempt timestamps from the last rolling hour.
 *
 * @param int|null $now Current Unix timestamp.
 * @return int[]
 */
function rytkoset_theme_get_event_messaging_send_attempts( $now = null ) {
	$now      = null === $now ? time() : absint( $now );
	$cutoff   = $now - HOUR_IN_SECONDS;
	$attempts = get_option( rytkoset_theme_get_event_messaging_send_attempts_option_key(), array() );

	if ( ! is_array( $attempts ) ) {
		return array();
	}

	$recent_attempts = array();

	foreach ( $attempts as $timestamp ) {
		$timestamp = absint( $timestamp );

		if ( $timestamp > $cutoff && $timestamp <= $now ) {
			$recent_attempts[] = $timestamp;
		}
	}

	return $recent_attempts;
}

/**
 * Stores send attempt timestamps as a non-autoloaded option.
 *
 * @param int[] $attempts Unix timestamps.
 * @return void
 */
function rytkoset_theme_update_event_messaging_send_attempts( $attempts ) {
	$clean_attempts = array();

	foreach ( $attempts as $timestamp ) {
		$timestamp = absint( $timestamp );

		if ( $timestamp > 0 ) {
			$clean_attempts[] = $timestamp;
		}
	}

	update_option( rytkoset_theme_get_event_messaging_send_attempts_option_key(), $clean_attempts, false );
}

/**
 * Counts pending recipients for a queued messaging job.
 *
 * @param array $job Queue job.
 * @return int
 */
function rytkoset_theme_get_event_messaging_pending_recipient_count( $job ) {
	$recipients = isset( $job['recipients'] ) && is_array( $job['recipients'] ) ? $job['recipients'] : array();
	$count      = 0;

	foreach ( $recipients as $recipient ) {
		if ( 'pending' === (string) ( $recipient['status'] ?? 'pending' ) ) {
			++$count;
		}
	}

	return $count;
}

/**
 * Returns a human-readable status for a queued messaging job.
 *
 * @param array $job Queue job.
 * @return string
 */
function rytkoset_theme_get_event_messaging_job_status_label( $job ) {
	$pending = rytkoset_theme_get_event_messaging_pending_recipient_count( $job );

	if ( 0 === $pending ) {
		return __( 'Valmis', 'rytkoset-theme' );
	}

	if ( (int) ( $job['sent_count'] ?? 0 ) > 0 || (int) ( $job['failed_count'] ?? 0 ) > 0 ) {
		return __( 'Käsittelyssä', 'rytkoset-theme' );
	}

	return __( 'Jonossa', 'rytkoset-theme' );
}

/**
 * Queues an event participant message for cron-based sending.
 *
 * @param array $args Job arguments.
 * @return string Queued job ID, or empty string on failure.
 */
function rytkoset_theme_enqueue_event_messaging_job( $args ) {
	$raw_recipients = isset( $args['recipients'] ) && is_array( $args['recipients'] ) ? $args['recipients'] : array();
	$recipients     = array();

	foreach ( $raw_recipients as $recipient ) {
		$email = sanitize_email( (string) ( $recipient['email'] ?? '' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			continue;
		}

		$recipients[] = array(
			'email'       => $email,
			'name'        => sanitize_text_field( (string) ( $recipient['name'] ?? '' ) ),
			'event_title' => sanitize_text_field( (string) ( $recipient['event_title'] ?? '' ) ),
			'status'      => 'pending',
			'sent_at'     => '',
			'failed_at'   => '',
		);
	}

	if ( empty( $recipients ) ) {
		return '';
	}

	$body    = trim( (string) ( $args['body'] ?? '' ) );
	$subject = sanitize_text_field( (string) ( $args['subject'] ?? '' ) );

	if ( '' === $subject || '' === $body ) {
		return '';
	}

	$job = array(
		'id'            => uniqid( 'msg_', true ),
		'created_at'    => current_time( 'mysql' ),
		'sender_id'     => absint( $args['sender_id'] ?? 0 ),
		'sender_name'   => sanitize_text_field( (string) ( $args['sender_name'] ?? '' ) ),
		'reply_to'      => sanitize_email( (string) ( $args['reply_to'] ?? '' ) ),
		'event_id'      => absint( $args['event_id'] ?? 0 ),
		'event_title'   => sanitize_text_field( (string) ( $args['event_title'] ?? '' ) ),
		'status_filter' => sanitize_key( (string) ( $args['status_filter'] ?? '' ) ),
		'status_label'  => sanitize_text_field( (string) ( $args['status_label'] ?? '' ) ),
		'subject'       => $subject,
		'body'          => $body,
		'body_preview'  => function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 200 ) : substr( $body, 0, 200 ),
		'total_count'   => count( $recipients ),
		'sent_count'    => 0,
		'failed_count'  => 0,
		'skipped_count' => absint( $args['skipped_count'] ?? 0 ),
		'last_sent_at'  => '',
		'completed_at'  => '',
		'recipients'    => $recipients,
	);

	$queue   = rytkoset_theme_get_event_messaging_queue();
	$queue[] = $job;

	rytkoset_theme_update_event_messaging_queue( $queue );
	rytkoset_theme_ensure_event_messaging_cron_scheduled();

	return $job['id'];
}

/**
 * Adds a five-minute cron schedule for event message queue processing.
 *
 * @param array $schedules Registered schedules.
 * @return array
 */
function rytkoset_theme_add_event_messaging_cron_schedule( $schedules ) {
	if ( ! isset( $schedules['rytkoset_five_minutes'] ) ) {
		$schedules['rytkoset_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'rytkoset-theme' ),
		);
	}

	return $schedules;
}
add_filter( 'cron_schedules', 'rytkoset_theme_add_event_messaging_cron_schedule' );

/**
 * Ensures the recurring event messaging queue processor is scheduled.
 *
 * @return void
 */
function rytkoset_theme_ensure_event_messaging_cron_scheduled() {
	$hook = rytkoset_theme_get_event_messaging_cron_hook();

	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'rytkoset_five_minutes', $hook );
	}
}
add_action( 'init', 'rytkoset_theme_ensure_event_messaging_cron_scheduled' );

/**
 * Clears the event messaging cron hook when the theme is switched.
 *
 * @return void
 */
function rytkoset_theme_clear_event_messaging_cron() {
	wp_clear_scheduled_hook( rytkoset_theme_get_event_messaging_cron_hook() );
}
add_action( 'switch_theme', 'rytkoset_theme_clear_event_messaging_cron' );

/**
 * Adds a completed queued job to the existing aggregate messaging log.
 *
 * @param array $job Queue job.
 * @return void
 */
function rytkoset_theme_append_completed_event_messaging_job_to_log( $job ) {
	$completed_at = (string) ( $job['completed_at'] ?? '' );

	rytkoset_theme_append_event_messaging_log(
		array(
			'id'            => (string) ( $job['id'] ?? uniqid( 'msg_', true ) ),
			'timestamp'     => '' !== $completed_at ? $completed_at : current_time( 'mysql' ),
			'queued_at'     => (string) ( $job['created_at'] ?? '' ),
			'sender_id'     => (int) ( $job['sender_id'] ?? 0 ),
			'sender_name'   => (string) ( $job['sender_name'] ?? '' ),
			'event_id'      => (int) ( $job['event_id'] ?? 0 ),
			'event_title'   => (string) ( $job['event_title'] ?? '' ),
			'status_filter' => (string) ( $job['status_filter'] ?? '' ),
			'subject'       => (string) ( $job['subject'] ?? '' ),
			'body_preview'  => (string) ( $job['body_preview'] ?? '' ),
			'sent_count'    => (int) ( $job['sent_count'] ?? 0 ),
			'failed_count'  => (int) ( $job['failed_count'] ?? 0 ),
			'skipped_count' => (int) ( $job['skipped_count'] ?? 0 ),
		)
	);
}

/**
 * Processes pending event participant messages within the rolling hourly limit.
 *
 * @return void
 */
function rytkoset_theme_process_event_messaging_queue() {
	$lock_key = 'rytkoset_event_messaging_queue_lock';

	if ( get_transient( $lock_key ) ) {
		return;
	}

	set_transient( $lock_key, 1, 10 * MINUTE_IN_SECONDS );

	try {
		$now       = time();
		$attempts  = rytkoset_theme_get_event_messaging_send_attempts( $now );
		$available = max( 0, rytkoset_theme_get_event_messaging_hourly_limit() - count( $attempts ) );

		if ( $available <= 0 ) {
			rytkoset_theme_update_event_messaging_send_attempts( $attempts );
			return;
		}

		$queue = rytkoset_theme_get_event_messaging_queue();

		if ( empty( $queue ) ) {
			rytkoset_theme_update_event_messaging_send_attempts( $attempts );
			return;
		}

		$next_queue = array();

		foreach ( $queue as $job ) {
			$recipients = isset( $job['recipients'] ) && is_array( $job['recipients'] ) ? $job['recipients'] : array();

			if ( $available > 0 ) {
				$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );
				$reply_to = sanitize_email( (string) ( $job['reply_to'] ?? '' ) );

				if ( '' !== $reply_to && is_email( $reply_to ) ) {
					$headers[] = 'Reply-To: ' . $reply_to;
				}

				// Resolved once per job, not per recipient: the link carries no
				// per-recipient token (#666). Empty for a "kaikki tapahtumat"
				// broadcast (event_id 0), since one link cannot represent several events.
				$feedback_link = function_exists( 'rytkoset_theme_get_event_feedback_public_url' )
					? rytkoset_theme_get_event_feedback_public_url( (int) ( $job['event_id'] ?? 0 ) )
					: '';

				foreach ( $recipients as $index => $recipient ) {
					if ( $available <= 0 ) {
						break;
					}

					if ( 'pending' !== (string) ( $recipient['status'] ?? 'pending' ) ) {
						continue;
					}

					$email = sanitize_email( (string) ( $recipient['email'] ?? '' ) );

					if ( '' === $email || ! is_email( $email ) ) {
						$recipients[ $index ]['status']    = 'failed';
						$recipients[ $index ]['failed_at'] = current_time( 'mysql' );
						$job['failed_count']               = (int) ( $job['failed_count'] ?? 0 ) + 1;
						continue;
					}

					$message = rytkoset_theme_personalize_event_message(
						(string) ( $job['body'] ?? '' ),
						(string) ( $recipient['name'] ?? '' ),
						(string) ( $recipient['event_title'] ?? '' ),
						$feedback_link
					);

					$attempt_time = time();
					$attempts[]   = $attempt_time;
					$ok           = wp_mail( $email, (string) ( $job['subject'] ?? '' ), $message, $headers );
					$sent_at      = current_time( 'mysql' );

					if ( $ok ) {
						$recipients[ $index ]['status']  = 'sent';
						$recipients[ $index ]['sent_at'] = $sent_at;
						$job['sent_count']               = (int) ( $job['sent_count'] ?? 0 ) + 1;
					} else {
						$recipients[ $index ]['status']    = 'failed';
						$recipients[ $index ]['failed_at'] = $sent_at;
						$job['failed_count']               = (int) ( $job['failed_count'] ?? 0 ) + 1;
					}

					$job['last_sent_at'] = $sent_at;
					--$available;
				}
			}

			$job['recipients'] = $recipients;

			if ( 0 === rytkoset_theme_get_event_messaging_pending_recipient_count( $job ) ) {
				$job['completed_at'] = current_time( 'mysql' );
				rytkoset_theme_append_completed_event_messaging_job_to_log( $job );
			} else {
				$next_queue[] = $job;
			}
		}

		rytkoset_theme_update_event_messaging_send_attempts( $attempts );
		rytkoset_theme_update_event_messaging_queue( $next_queue );
	} finally {
		delete_transient( $lock_key );
	}
}
add_action( 'rytkoset_process_event_messaging_queue', 'rytkoset_theme_process_event_messaging_queue' );

/**
 * Registers the bulk messaging submenu under the Events CPT.
 */
function rytkoset_theme_register_event_messaging_admin_page() {
	add_submenu_page(
		'edit.php?post_type=rytkoset_event',
		__( 'Viestintä', 'rytkoset-theme' ),
		__( 'Viestintä', 'rytkoset-theme' ),
		'edit_others_event_registrations',
		'rytkoset-event-messaging',
		'rytkoset_theme_render_event_messaging_admin_page'
	);
}
add_action( 'admin_menu', 'rytkoset_theme_register_event_messaging_admin_page' );

/**
 * Renders the bulk messaging admin page.
 */
function rytkoset_theme_render_event_messaging_admin_page() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta tarkastella tätä sivua.', 'rytkoset-theme' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list filters.
	$selected_event  = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
	$selected_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$status_options = rytkoset_theme_get_event_participants_admin_status_options();

	if ( ! isset( $status_options[ $selected_status ] ) ) {
		$selected_status = '';
	}

	$events = rytkoset_theme_get_event_participants_admin_events();

	if ( $selected_event > 0 && 'rytkoset_event' !== get_post_type( $selected_event ) ) {
		$selected_event = 0;
	}

	$result          = rytkoset_theme_get_event_messaging_recipients( $selected_event, $selected_status );
	$recipients      = $result['recipients'];
	$skipped_count   = $result['skipped'];
	$recipient_count = count( $recipients );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect feedback set by nonce-protected messaging actions.
	$notice = isset( $_GET['messaging_notice'] ) ? sanitize_key( wp_unslash( $_GET['messaging_notice'] ) ) : '';
	$sent   = isset( $_GET['sent'] ) ? absint( wp_unslash( $_GET['sent'] ) ) : 0;
	$failed = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0;
	$skip   = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;
	$queued = isset( $_GET['queued'] ) ? absint( wp_unslash( $_GET['queued'] ) ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$queue_entries     = rytkoset_theme_get_event_messaging_queue();
	$recent_attempts   = rytkoset_theme_get_event_messaging_send_attempts();
	$hourly_limit      = rytkoset_theme_get_event_messaging_hourly_limit();
	$available_attempt = max( 0, $hourly_limit - count( $recent_attempts ) );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tapahtumien viestintä', 'rytkoset-theme' ); ?></h1>
		<p><?php esc_html_e( 'Lisää sähköpostiviesti tapahtuman osallistujien lähetysjonoon. Suodata ensin vastaanottajat, kirjoita viesti ja vahvista jonotus.', 'rytkoset-theme' ); ?></p>

		<?php if ( 'queued' === $notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: queued recipients, 2: skipped recipients, 3: hourly limit */
							__( 'Viesti lisättiin lähetysjonoon. Vastaanottajia %1$d, ohitettuja (ei osoitetta) %2$d. Jono lähettää enintään %3$d viestiä tunnissa.', 'rytkoset-theme' ),
							$queued,
							$skip,
							$hourly_limit
						)
					);
					?>
				</p>
			</div>
		<?php elseif ( 'sent' === $notice ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: sent, 2: failed, 3: skipped */
							__( 'Viesti lähetetty. Onnistuneita %1$d, epäonnistuneita %2$d, ohitettuja (ei osoitetta) %3$d.', 'rytkoset-theme' ),
							$sent,
							$failed,
							$skip
						)
					);
					?>
				</p>
			</div>
		<?php elseif ( 'error_empty' === $notice ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'Aihe ja viesti ovat pakollisia.', 'rytkoset-theme' ); ?></p>
			</div>
		<?php elseif ( 'error_no_recipients' === $notice ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'Valitulla suodatuksella ei ole yhtään vastaanottajaa.', 'rytkoset-theme' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="tablenav top">
			<div class="alignleft actions" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
				<form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
					<input type="hidden" name="post_type" value="rytkoset_event" />
					<input type="hidden" name="page" value="rytkoset-event-messaging" />

					<label for="rytkoset-event-messaging-event">
						<?php esc_html_e( 'Tapahtuma:', 'rytkoset-theme' ); ?>
					</label>
					<select name="event_id" id="rytkoset-event-messaging-event">
						<option value="0"><?php esc_html_e( 'Kaikki tapahtumat', 'rytkoset-theme' ); ?></option>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$event_date   = rytkoset_theme_get_event_date_display( $event->ID );
							$option_label = $event->post_title;

							if ( '' !== $event_date ) {
								$option_label .= ' (' . $event_date . ')';
							}
							?>
							<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $selected_event, $event->ID ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="rytkoset-event-messaging-status">
						<?php esc_html_e( 'Status:', 'rytkoset-theme' ); ?>
					</label>
					<select name="status" id="rytkoset-event-messaging-status">
						<?php foreach ( $status_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_status, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<?php submit_button( __( 'Päivitä vastaanottajat', 'rytkoset-theme' ), 'secondary', '', false ); ?>
				</form>

				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: recipient count, 2: skipped count */
							_n(
								'Viesti lisätään jonoon %1$d vastaanottajalle (osoitteita puuttuu %2$d).',
								'Viesti lisätään jonoon %1$d vastaanottajalle (osoitteita puuttuu %2$d).',
								$recipient_count,
								'rytkoset-theme'
							),
							$recipient_count,
							$skipped_count
						)
					);
					?>
				</p>
			</div>
			<br class="clear" />
		</div>

		<?php if ( function_exists( 'rytkoset_theme_render_event_feedback_queue_section' ) ) : ?>
			<?php rytkoset_theme_render_event_feedback_queue_section( $selected_event ); ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Viesti', 'rytkoset-theme' ); ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 760px;">
			<input type="hidden" name="action" value="rytkoset_send_event_participants_message" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $selected_event ); ?>" />
			<input type="hidden" name="status" value="<?php echo esc_attr( $selected_status ); ?>" />
			<?php wp_nonce_field( 'rytkoset_send_event_participants_message', 'rytkoset_event_messaging_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="rytkoset-event-messaging-subject"><?php esc_html_e( 'Aihe', 'rytkoset-theme' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								name="subject"
								id="rytkoset-event-messaging-subject"
								class="regular-text"
								required
								maxlength="200"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="rytkoset-event-messaging-body"><?php esc_html_e( 'Viesti', 'rytkoset-theme' ); ?></label>
						</th>
						<td>
							<textarea
								name="body"
								id="rytkoset-event-messaging-body"
								rows="10"
								class="large-text"
								required
							></textarea>
							<p class="description">
								<?php
								echo wp_kses(
									__( 'Voit käyttää placeholdereita: <code>{nimi}</code> korvautuu osallistujan nimellä, <code>{tapahtuma}</code> tapahtuman otsikolla ja <code>{palautelinkki}</code> valitun tapahtuman palautelomakkeen osoitteella (toimii vain kun yksi tapahtuma on valittuna). Viesti lähetetään tekstimuotoisena.', 'rytkoset-theme' ),
									array( 'code' => array() )
								);
								?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			$button_label = 0 === $recipient_count
				? __( 'Ei vastaanottajia', 'rytkoset-theme' )
				: sprintf(
					/* translators: %d: recipient count */
					_n(
						'Lisää jonoon %d vastaanottajalle',
						'Lisää jonoon %d vastaanottajalle',
						$recipient_count,
						'rytkoset-theme'
					),
					$recipient_count
				);

			$confirm_message = sprintf(
				/* translators: 1: recipient count, 2: hourly limit */
				__( 'Lisätäänkö viesti jonoon %1$d vastaanottajalle? Jono lähettää enintään %2$d viestiä tunnissa.', 'rytkoset-theme' ),
				$recipient_count,
				$hourly_limit
			);
			?>
			<p class="submit">
				<button
					type="submit"
					class="button button-primary"
					<?php disabled( 0 === $recipient_count ); ?>
					onclick="return confirm(<?php echo wp_json_encode( $confirm_message ); ?>);"
				>
					<?php echo esc_html( $button_label ); ?>
				</button>
			</p>
		</form>

		<h2><?php esc_html_e( 'Lähetysjono', 'rytkoset-theme' ); ?></h2>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: hourly limit, 2: available send attempts */
					__( 'Jono lähettää enintään %1$d viestiä 60 minuutin aikana. Vapaita lähetyksiä tällä hetkellä: %2$d.', 'rytkoset-theme' ),
					$hourly_limit,
					$available_attempt
				)
			);
			?>
		</p>

		<?php if ( empty( $queue_entries ) ) : ?>
			<p><?php esc_html_e( 'Ei jonossa olevia viestejä.', 'rytkoset-theme' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Luotu', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lähettäjä', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Tapahtuma', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Aihe', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Jonossa', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lähetetty', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Epäonnistunut', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Ohitettu', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Viimeksi lähetetty', 'rytkoset-theme' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $queue_entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $entry['created_at'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['sender_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['event_title'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['subject'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( rytkoset_theme_get_event_messaging_job_status_label( $entry ) ); ?></td>
							<td><?php echo esc_html( (string) rytkoset_theme_get_event_messaging_pending_recipient_count( $entry ) ); ?></td>
							<td><?php echo esc_html( (string) ( (int) ( $entry['sent_count'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( (string) ( (int) ( $entry['failed_count'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( (string) ( (int) ( $entry['skipped_count'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['last_sent_at'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Lähetysloki', 'rytkoset-theme' ); ?></h2>
		<?php $log_entries = rytkoset_theme_get_event_messaging_log( 20 ); ?>

		<?php if ( empty( $log_entries ) ) : ?>
			<p><?php esc_html_e( 'Ei lähetettyjä viestejä.', 'rytkoset-theme' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Aika', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lähettäjä', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Tapahtuma', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Aihe', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lähetetty', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Epäonnistunut', 'rytkoset-theme' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Ohitettu', 'rytkoset-theme' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log_entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $entry['timestamp'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['sender_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['event_title'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['subject'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( (int) ( $entry['sent_count'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( (string) ( (int) ( $entry['failed_count'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( (string) ( (int) ( $entry['skipped_count'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Handles the bulk message form: validates input, queues recipients,
 * and redirects back to the admin page with a notice.
 *
 * @return void
 */
function rytkoset_theme_send_event_participants_message() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta lähettää viestejä.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_send_event_participants_message', 'rytkoset_event_messaging_nonce' );

	$event_id        = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
	$selected_status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$subject         = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$body_raw        = isset( $_POST['body'] ) ? (string) wp_unslash( $_POST['body'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wp_strip_all_tags() on the next line.
	$body            = trim( wp_strip_all_tags( $body_raw ) );

	$status_options = rytkoset_theme_get_event_participants_admin_status_options();

	if ( ! isset( $status_options[ $selected_status ] ) ) {
		$selected_status = '';
	}

	if ( $event_id > 0 && 'rytkoset_event' !== get_post_type( $event_id ) ) {
		$event_id = 0;
	}

	$redirect_base = add_query_arg(
		array(
			'post_type' => 'rytkoset_event',
			'page'      => 'rytkoset-event-messaging',
			'event_id'  => $event_id,
			'status'    => $selected_status,
		),
		admin_url( 'edit.php' )
	);

	if ( '' === $subject || '' === $body ) {
		wp_safe_redirect( add_query_arg( 'messaging_notice', 'error_empty', $redirect_base ) );
		exit;
	}

	$result        = rytkoset_theme_get_event_messaging_recipients( $event_id, $selected_status );
	$recipients    = $result['recipients'];
	$skipped_count = (int) $result['skipped'];

	if ( empty( $recipients ) ) {
		wp_safe_redirect( add_query_arg( 'messaging_notice', 'error_no_recipients', $redirect_base ) );
		exit;
	}

	$current_user = wp_get_current_user();
	$reply_to     = $current_user && ! empty( $current_user->user_email ) ? $current_user->user_email : '';

	$event_title_for_log = $event_id > 0
		? get_the_title( $event_id )
		: __( 'Kaikki tapahtumat', 'rytkoset-theme' );

	$job_id = rytkoset_theme_enqueue_event_messaging_job(
		array(
			'sender_id'     => (int) get_current_user_id(),
			'sender_name'   => $current_user ? (string) $current_user->display_name : '',
			'reply_to'      => $reply_to,
			'event_id'      => $event_id,
			'event_title'   => (string) $event_title_for_log,
			'status_filter' => $selected_status,
			'status_label'  => (string) ( $status_options[ $selected_status ] ?? '' ),
			'subject'       => $subject,
			'body'          => $body,
			'recipients'    => $recipients,
			'skipped_count' => $skipped_count,
		)
	);

	if ( '' === $job_id ) {
		wp_safe_redirect( add_query_arg( 'messaging_notice', 'error_no_recipients', $redirect_base ) );
		exit;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'messaging_notice' => 'queued',
				'queued'           => count( $recipients ),
				'skipped'          => $skipped_count,
			),
			$redirect_base
		)
	);
	exit;
}
add_action( 'admin_post_rytkoset_send_event_participants_message', 'rytkoset_theme_send_event_participants_message' );
