<?php
/**
 * Massaviestintä tapahtuman osallistujille (admin-sivu + lähetyshandler).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_event_messaging_recipients' ) ) {
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

		$recipients = array();
		$skipped    = 0;

		foreach ( $rows as $row ) {
			$email = trim( (string) ( $row['email'] ?? '' ) );

			if ( '' === $email ) {
				$email = trim( (string) ( $row['contact_email'] ?? '' ) );
			}

			if ( '' === $email || ! is_email( $email ) ) {
				$skipped++;
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
}

if ( ! function_exists( 'rytkoset_theme_personalize_event_message' ) ) {
	/**
	 * Replaces placeholders in a message body.
	 *
	 * @param string $body        Message body.
	 * @param string $name        Recipient name.
	 * @param string $event_title Event title.
	 * @return string
	 */
	function rytkoset_theme_personalize_event_message( $body, $name, $event_title ) {
		return str_replace(
			array( '{nimi}', '{tapahtuma}' ),
			array( $name, $event_title ),
			(string) $body
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_messaging_log_option_key' ) ) {
	/**
	 * Returns the option key used for the messaging log.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_event_messaging_log_option_key() {
		return 'rytkoset_event_messaging_log';
	}
}

if ( ! function_exists( 'rytkoset_theme_append_event_messaging_log' ) ) {
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
}

if ( ! function_exists( 'rytkoset_theme_get_event_messaging_log' ) ) {
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
}

if ( ! function_exists( 'rytkoset_theme_register_event_messaging_admin_page' ) ) {
	/**
	 * Registers the bulk messaging submenu under the Events CPT.
	 */
	function rytkoset_theme_register_event_messaging_admin_page() {
		add_submenu_page(
			'edit.php?post_type=event',
			__( 'Viestintä', 'rytkoset-theme' ),
			__( 'Viestintä', 'rytkoset-theme' ),
			'edit_others_event_registrations',
			'rytkoset-event-messaging',
			'rytkoset_theme_render_event_messaging_admin_page'
		);
	}
}
add_action( 'admin_menu', 'rytkoset_theme_register_event_messaging_admin_page' );

if ( ! function_exists( 'rytkoset_theme_render_event_messaging_admin_page' ) ) {
	/**
	 * Renders the bulk messaging admin page.
	 */
	function rytkoset_theme_render_event_messaging_admin_page() {
		if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
			wp_die( esc_html__( 'Sinulla ei ole oikeutta tarkastella tätä sivua.', 'rytkoset-theme' ) );
		}

		$selected_event  = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
		$selected_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		$status_options = rytkoset_theme_get_event_participants_admin_status_options();

		if ( ! isset( $status_options[ $selected_status ] ) ) {
			$selected_status = '';
		}

		$events = rytkoset_theme_get_event_participants_admin_events();

		if ( $selected_event > 0 && 'event' !== get_post_type( $selected_event ) ) {
			$selected_event = 0;
		}

		$result          = rytkoset_theme_get_event_messaging_recipients( $selected_event, $selected_status );
		$recipients      = $result['recipients'];
		$skipped_count   = $result['skipped'];
		$recipient_count = count( $recipients );

		$notice = isset( $_GET['messaging_notice'] ) ? sanitize_key( wp_unslash( $_GET['messaging_notice'] ) ) : '';
		$sent   = isset( $_GET['sent'] ) ? absint( wp_unslash( $_GET['sent'] ) ) : 0;
		$failed = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0;
		$skip   = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tapahtumien viestintä', 'rytkoset-theme' ); ?></h1>
			<p><?php esc_html_e( 'Lähetä sähköpostiviesti tapahtuman osallistujille. Suodata ensin vastaanottajat, kirjoita viesti ja vahvista lähetys.', 'rytkoset-theme' ); ?></p>

			<?php if ( 'sent' === $notice ) : ?>
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
						<input type="hidden" name="post_type" value="event" />
						<input type="hidden" name="page" value="rytkoset-event-messaging" />

						<label for="rytkoset-event-messaging-event">
							<?php esc_html_e( 'Tapahtuma:', 'rytkoset-theme' ); ?>
						</label>
						<select name="event_id" id="rytkoset-event-messaging-event">
							<option value="0"><?php esc_html_e( 'Kaikki tapahtumat', 'rytkoset-theme' ); ?></option>
							<?php foreach ( $events as $event ) : ?>
								<?php
								$event_date   = function_exists( 'rytkoset_theme_get_event_date_display' )
									? rytkoset_theme_get_event_date_display( $event->ID )
									: '';
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
									'Viesti lähetetään %1$d vastaanottajalle (osoitteita puuttuu %2$d).',
									'Viesti lähetetään %1$d vastaanottajalle (osoitteita puuttuu %2$d).',
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
										__( 'Voit käyttää placeholdereita: <code>{nimi}</code> korvautuu osallistujan nimellä ja <code>{tapahtuma}</code> tapahtuman otsikolla. Viesti lähetetään tekstimuotoisena.', 'rytkoset-theme' ),
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
							'Lähetä viesti %d vastaanottajalle',
							'Lähetä viesti %d vastaanottajalle',
							$recipient_count,
							'rytkoset-theme'
						),
						$recipient_count
					);

				$confirm_message = sprintf(
					/* translators: %d: recipient count */
					__( 'Lähetetäänkö viesti %d vastaanottajalle? Tätä toimintoa ei voi peruuttaa.', 'rytkoset-theme' ),
					$recipient_count
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
}

if ( ! function_exists( 'rytkoset_theme_send_event_participants_message' ) ) {
	/**
	 * Handles the bulk message send: validates input, sends per-recipient emails,
	 * appends a log entry, and redirects back to the admin page with a notice.
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
		$body_raw        = isset( $_POST['body'] ) ? (string) wp_unslash( $_POST['body'] ) : '';
		$body            = trim( wp_strip_all_tags( $body_raw ) );

		$status_options = rytkoset_theme_get_event_participants_admin_status_options();

		if ( ! isset( $status_options[ $selected_status ] ) ) {
			$selected_status = '';
		}

		if ( $event_id > 0 && 'event' !== get_post_type( $event_id ) ) {
			$event_id = 0;
		}

		$redirect_base = add_query_arg(
			array(
				'post_type' => 'event',
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

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$sent_count   = 0;
		$failed_count = 0;

		foreach ( $recipients as $recipient ) {
			$personalized = rytkoset_theme_personalize_event_message(
				$body,
				(string) ( $recipient['name'] ?? '' ),
				(string) ( $recipient['event_title'] ?? '' )
			);

			$ok = wp_mail( $recipient['email'], $subject, $personalized, $headers );

			if ( $ok ) {
				$sent_count++;
			} else {
				$failed_count++;
			}
		}

		$event_title_for_log = $event_id > 0
			? get_the_title( $event_id )
			: __( 'Kaikki tapahtumat', 'rytkoset-theme' );

		rytkoset_theme_append_event_messaging_log(
			array(
				'id'            => uniqid( 'msg_', true ),
				'timestamp'     => current_time( 'mysql' ),
				'sender_id'     => (int) get_current_user_id(),
				'sender_name'   => $current_user ? (string) $current_user->display_name : '',
				'event_id'      => $event_id,
				'event_title'   => (string) $event_title_for_log,
				'status_filter' => $selected_status,
				'subject'       => $subject,
				'body_preview'  => function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 200 ) : substr( $body, 0, 200 ),
				'sent_count'    => $sent_count,
				'failed_count'  => $failed_count,
				'skipped_count' => $skipped_count,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'messaging_notice' => 'sent',
					'sent'             => $sent_count,
					'failed'           => $failed_count,
					'skipped'          => $skipped_count,
				),
				$redirect_base
			)
		);
		exit;
	}
}
add_action( 'admin_post_rytkoset_send_event_participants_message', 'rytkoset_theme_send_event_participants_message' );
