<?php
/**
 * Yhdistetty osallistujalistan hallinta adminille.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns normalized rows for free event registrations.
 *
 * @param int    $event_id      Event post ID.
 * @param string $status_filter Optional registration status filter.
 * @return array
 */
function rytkoset_theme_get_event_free_participants( $event_id, $status_filter = '' ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return array();
	}

	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
	$statuses  = rytkoset_theme_get_event_registration_statuses();

	$query_args = array(
		'post_type'      => 'event_registration',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array(
			array(
				'key'   => $meta_keys['event_id'],
				'value' => $event_id,
			),
		),
	);

	$registrations = get_posts( $query_args );
	$rows          = array();

	foreach ( $registrations as $registration ) {
		$status = rytkoset_theme_get_event_registration_meta( $registration->ID, 'status' );

		if ( '' === $status ) {
			$status = 'pending';
		}

		if ( '' !== $status_filter && $status_filter !== $status ) {
			continue;
		}

		$name = rytkoset_theme_get_event_registration_meta( $registration->ID, 'name' );

		$rows[] = array(
			'name'             => '' !== $name ? $name : __( 'Nimetön osallistuja', 'rytkoset-theme' ),
			'email'            => rytkoset_theme_get_event_registration_meta( $registration->ID, 'email' ),
			'phone'            => '',
			'diet'             => rytkoset_theme_get_event_registration_meta( $registration->ID, 'diet' ),
			'notes'            => rytkoset_theme_get_event_registration_meta( $registration->ID, 'notes' ),
			'participant_type' => '',
			'friday_buffet'    => false,
			'status'           => $status,
			'status_label'     => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['pending'],
			'source'           => 'free',
			'created'          => get_the_date( '', $registration ),
			'contact_name'     => '' !== $name ? $name : '',
			'contact_email'    => rytkoset_theme_get_event_registration_meta( $registration->ID, 'email' ),
			'edit_url'         => (string) get_edit_post_link( $registration->ID, '' ),
			'order_id'         => null,
			'order_number'     => null,
			'event_id'         => $event_id,
			'event_title'      => get_the_title( $event_id ),
		);
	}

	return $rows;
}

/**
 * Returns normalized rows for paid event participants.
 *
 * Looks up orders that contain the WooCommerce product linked to the event.
 * For Tampere 2026 events the per-order multi-participant schema is honored;
 * for other paid events the order's billing contact is used as a single
 * participant per ordered quantity.
 *
 * @param int $event_id Event post ID.
 * @return array
 */
function rytkoset_theme_get_event_paid_participants( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$product_id = (int) get_post_meta( $event_id, '_rytkoset_event_product_id', true );

	if ( $product_id <= 0 ) {
		return array();
	}

	$is_tampere_2026 = rytkoset_theme_is_tampere_2026_registration_product( wc_get_product( $product_id ) );
	$statuses        = rytkoset_theme_get_supported_order_statuses();

	$orders = wc_get_orders(
		array(
			'limit'   => -1,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
			'status'  => $statuses,
		)
	);

	$rows = array();

	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}

		$order_has_product = false;
		$quantity          = 0;

		foreach ( $order->get_items() as $item ) {
			$item_product = $item->get_product();

			if ( ! $item_product instanceof WC_Product ) {
				continue;
			}

			if ( (int) $item_product->get_id() === $product_id || (int) $item_product->get_parent_id() === $product_id ) {
				$order_has_product = true;
				$quantity         += (int) $item->get_quantity();
			}
		}

		if ( ! $order_has_product ) {
			continue;
		}

		$contact_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		if ( '' === $contact_name ) {
			$contact_name = __( 'Nimi puuttuu', 'rytkoset-theme' );
		}

		$contact_email = (string) $order->get_billing_email();
		$contact_phone = (string) $order->get_billing_phone();
		$order_status  = $order->get_status();
		$status_label  = wc_get_order_status_name( $order_status );
		$created       = wp_date( get_option( 'date_format' ), $order->get_date_created() ? $order->get_date_created()->getTimestamp() : null );
		$edit_url      = (string) admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );

		if ( $is_tampere_2026 ) {
			$participants = rytkoset_theme_get_tampere_2026_order_participants( $order );

			if ( empty( $participants ) ) {
				continue;
			}

			foreach ( $participants as $participant ) {
				$participant_name = isset( $participant['name'] ) && '' !== $participant['name']
					? $participant['name']
					: __( 'Nimi puuttuu', 'rytkoset-theme' );

				$rows[] = array(
					'name'             => $participant_name,
					'email'            => '',
					'phone'            => '',
					'diet'             => isset( $participant['diet'] ) ? (string) $participant['diet'] : '',
					'notes'            => '',
					'participant_type' => isset( $participant['participant_type'] ) ? (string) $participant['participant_type'] : '',
					'friday_buffet'    => ! empty( $participant['friday_buffet'] ),
					'status'           => 'paid',
					'status_label'     => $status_label,
					'source'           => 'paid',
					'created'          => $created,
					'contact_name'     => $contact_name,
					'contact_email'    => $contact_email,
					'edit_url'         => $edit_url,
					'order_id'         => $order->get_id(),
					'order_number'     => $order->get_order_number(),
					'event_id'         => $event_id,
					'event_title'      => get_the_title( $event_id ),
				);
			}

			continue;
		}

		$units = max( 1, $quantity );

		for ( $i = 0; $i < $units; $i++ ) {
			$rows[] = array(
				'name'             => $contact_name,
				'email'            => $contact_email,
				'phone'            => $contact_phone,
				'diet'             => '',
				'notes'            => (string) $order->get_customer_note(),
				'participant_type' => '',
				'friday_buffet'    => false,
				'status'           => 'paid',
				'status_label'     => $status_label,
				'source'           => 'paid',
				'created'          => $created,
				'contact_name'     => $contact_name,
				'contact_email'    => $contact_email,
				'edit_url'         => $edit_url,
				'order_id'         => $order->get_id(),
				'order_number'     => $order->get_order_number(),
				'event_id'         => $event_id,
				'event_title'      => get_the_title( $event_id ),
			);
		}
	}

	return $rows;
}

/**
 * Returns unified participant rows for an event.
 *
 * @param int    $event_id      Event post ID.
 * @param string $status_filter Optional status filter.
 *                              Accepts a free registration status, 'paid' or '' for all.
 * @return array
 */
function rytkoset_theme_get_event_participants( $event_id, $status_filter = '' ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return array();
	}

	$rows = array();

	if ( 'paid' !== $status_filter ) {
		$free_filter = '' === $status_filter ? '' : $status_filter;
		$rows        = array_merge( $rows, rytkoset_theme_get_event_free_participants( $event_id, $free_filter ) );
	}

	$statuses = rytkoset_theme_get_event_registration_statuses();

	if ( '' === $status_filter || 'paid' === $status_filter ) {
		$rows = array_merge( $rows, rytkoset_theme_get_event_paid_participants( $event_id ) );
	}

	if ( '' !== $status_filter && isset( $statuses[ $status_filter ] ) ) {
		$rows = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $status_filter ) {
					return isset( $row['status'] ) && $status_filter === $row['status'];
				}
			)
		);
	}

	return $rows;
}

/**
 * Returns aggregated participant rows across all events.
 *
 * @param string $status_filter Optional status filter.
 * @return array
 */
function rytkoset_theme_get_all_events_participants( $status_filter = '' ) {
	$events = get_posts(
		array(
			'post_type'      => 'rytkoset_event',
			'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$rows = array();

	if ( ! is_array( $events ) ) {
		return $rows;
	}

	foreach ( $events as $event_id ) {
		$rows = array_merge( $rows, rytkoset_theme_get_event_participants( (int) $event_id, $status_filter ) );
	}

	return $rows;
}

/**
 * Registers the unified event participants submenu under the Events CPT.
 */
function rytkoset_theme_register_event_participants_admin_page() {
	add_submenu_page(
		'edit.php?post_type=rytkoset_event',
		__( 'Osallistujat', 'rytkoset-theme' ),
		__( 'Osallistujat', 'rytkoset-theme' ),
		'edit_others_event_registrations',
		'rytkoset-event-participants',
		'rytkoset_theme_render_event_participants_admin_page'
	);
}
add_action( 'admin_menu', 'rytkoset_theme_register_event_participants_admin_page' );

/**
 * Returns published events for the admin event picker, sorted by event date DESC.
 *
 * @return WP_Post[]
 */
function rytkoset_theme_get_event_participants_admin_events() {
	$events = get_posts(
		array(
			'post_type'      => 'rytkoset_event',
			'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'       => '_rytkoset_event_date',
			'order'          => 'DESC',
		)
	);

	return is_array( $events ) ? $events : array();
}

/**
 * Returns status filter options for the participants admin page.
 *
 * @return array<string,string>
 */
function rytkoset_theme_get_event_participants_admin_status_options() {
	$options = array(
		'' => __( 'Kaikki', 'rytkoset-theme' ),
	);

	foreach ( rytkoset_theme_get_event_registration_statuses() as $key => $label ) {
		$options[ $key ] = sprintf(
			/* translators: %s: free registration status label */
			__( 'Maksuton: %s', 'rytkoset-theme' ),
			$label
		);
	}

	$options['paid'] = __( 'Maksulliset', 'rytkoset-theme' );

	return $options;
}

/**
 * Renders the CSV export form for the unified event participants admin page.
 *
 * @param int    $selected_event  Selected event ID (0 for all events).
 * @param string $selected_status Selected status filter.
 * @return void
 */
function rytkoset_theme_render_event_participants_export_form( $selected_event, $selected_status ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="alignleft actions">
		<input type="hidden" name="action" value="rytkoset_export_event_participants_csv" />
		<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) absint( $selected_event ) ); ?>" />
		<input type="hidden" name="status" value="<?php echo esc_attr( $selected_status ); ?>" />
		<?php wp_nonce_field( 'rytkoset_export_event_participants_csv', 'rytkoset_event_participants_csv_nonce' ); ?>
		<?php submit_button( __( 'Vie CSV', 'rytkoset-theme' ), 'secondary', '', false ); ?>
	</form>
	<?php
}

/**
 * Renders feedback for event registration mass anonymization.
 *
 * @return void
 */
function rytkoset_theme_render_event_participants_anonymization_notice() {
	$notice = isset( $_GET['rytkoset_anonymize_notice'] ) ? sanitize_key( wp_unslash( $_GET['rytkoset_anonymize_notice'] ) ) : '';

	if ( '' === $notice ) {
		return;
	}

	$count = isset( $_GET['rytkoset_anonymize_count'] ) ? absint( wp_unslash( $_GET['rytkoset_anonymize_count'] ) ) : 0;

	if ( 'success' === $notice ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: anonymized registration count */
					_n( '%d maksuton ilmoittautuminen anonymisoitiin.', '%d maksutonta ilmoittautumista anonymisoitiin.', $count, 'rytkoset-theme' ),
					$count
				)
			)
		);
		return;
	}

	if ( 'missing_confirmation' === $notice ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Anonymisointia ei tehty, koska vahvistus puuttui.', 'rytkoset-theme' ) . '</p></div>';
		return;
	}

	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Ilmoittautumisten anonymisointi epäonnistui.', 'rytkoset-theme' ) . '</p></div>';
}

/**
 * Renders the event-specific free registration anonymization form.
 *
 * @param int $selected_event Selected event ID.
 * @return void
 */
function rytkoset_theme_render_event_participants_anonymization_form( $selected_event ) {
	$selected_event = absint( $selected_event );

	if ( $selected_event <= 0 || 'rytkoset_event' !== get_post_type( $selected_event ) ) {
		return;
	}

	$count = function_exists( 'rytkoset_theme_get_event_registration_ids_for_event_anonymization' )
		? count( rytkoset_theme_get_event_registration_ids_for_event_anonymization( $selected_event ) )
		: 0;
	?>
	<div class="postbox" style="margin-top:16px; max-width:760px;">
		<div class="inside">
			<h2><?php esc_html_e( 'GDPR: maksuttomien ilmoittautumisten anonymisointi', 'rytkoset-theme' ); ?></h2>
			<p>
				<?php esc_html_e( 'Toiminto anonymisoi valitun tapahtuman maksuttomat ilmoittautumiset. Se poistaa nimet, sähköpostiosoitteet, ruokarajoitteet ja lisätiedot, mutta säilyttää ilmoittautumisrivit, tapahtuman ja statuksen raportointia varten.', 'rytkoset-theme' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Huom:', 'rytkoset-theme' ); ?></strong>
				<?php esc_html_e( 'Toiminto ei koske WooCommerce-tilauksia tai Tampere 2026 -osallistujakenttiä.', 'rytkoset-theme' ); ?>
			</p>
			<?php if ( $count > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rytkoset_anonymize_event_free_registrations" />
					<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $selected_event ); ?>" />
					<?php wp_nonce_field( 'rytkoset_anonymize_event_free_registrations', 'rytkoset_event_anonymize_nonce' ); ?>
					<p>
						<label>
							<input type="checkbox" name="confirm_anonymization" value="1" required />
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: anonymizable registration count */
									_n( 'Vahvistan, että haluan anonymisoida %d maksuttoman ilmoittautumisen.', 'Vahvistan, että haluan anonymisoida %d maksutonta ilmoittautumista.', $count, 'rytkoset-theme' ),
									$count
								)
							);
							?>
						</label>
					</p>
					<?php submit_button( __( 'Anonymisoi maksuttomat ilmoittautumiset', 'rytkoset-theme' ), 'delete', '', false ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Tällä tapahtumalla ei ole anonymisoimattomia maksuttomia ilmoittautumisia. Mahdolliset alla näkyvät maksuttomat rivit on jo anonymisoitu.', 'rytkoset-theme' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Builds a filename for the participants CSV export.
 *
 * @param int    $event_id        Event ID or 0 for all events.
 * @param string $selected_status Status filter value.
 * @return string
 */
function rytkoset_theme_get_event_participants_csv_filename( $event_id, $selected_status ) {
	$event_id = absint( $event_id );
	$slug     = 'kaikki-tapahtumat';

	if ( $event_id > 0 ) {
		$post = get_post( $event_id );

		if ( $post instanceof WP_Post && '' !== $post->post_name ) {
			$slug = $post->post_name;
		} elseif ( $post instanceof WP_Post ) {
			$slug = sanitize_title( $post->post_title );
		}
	}

	$parts = array( 'osallistujat', $slug );

	if ( '' !== $selected_status ) {
		$parts[] = $selected_status;
	}

	return sanitize_file_name( implode( '-', $parts ) . '.csv' );
}

/**
 * Sends the unified event participant list as a CSV download.
 *
 * @return void
 */
function rytkoset_theme_export_event_participants_csv() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta viedä osallistujalistaa.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_export_event_participants_csv', 'rytkoset_event_participants_csv_nonce' );

	$event_id        = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
	$selected_status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

	$status_options = rytkoset_theme_get_event_participants_admin_status_options();

	if ( ! isset( $status_options[ $selected_status ] ) ) {
		$selected_status = '';
	}

	if ( $event_id > 0 && 'rytkoset_event' !== get_post_type( $event_id ) ) {
		$event_id = 0;
	}

	$rows = $event_id > 0
		? rytkoset_theme_get_event_participants( $event_id, $selected_status )
		: rytkoset_theme_get_all_events_participants( $selected_status );

	$filename = rytkoset_theme_get_event_participants_csv_filename( $event_id, $selected_status );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	$output = fopen( 'php://output', 'w' );

	if ( false === $output ) {
		wp_die( esc_html__( 'CSV-viennin alustaminen epäonnistui.', 'rytkoset-theme' ) );
	}

	// UTF-8 BOM jotta Excel tunnistaa koodauksen oikein.
	echo "\xEF\xBB\xBF";

	fputcsv(
		$output,
		array(
			__( 'Tapahtuma', 'rytkoset-theme' ),
			__( 'Nimi', 'rytkoset-theme' ),
			__( 'Osallistujatyyppi', 'rytkoset-theme' ),
			__( 'Perjantain buffet', 'rytkoset-theme' ),
			__( 'Sähköposti', 'rytkoset-theme' ),
			__( 'Puhelin', 'rytkoset-theme' ),
			__( 'Ruokavalio / huomiot', 'rytkoset-theme' ),
			__( 'Lähde', 'rytkoset-theme' ),
			__( 'Status', 'rytkoset-theme' ),
			__( 'Ilmoittautunut', 'rytkoset-theme' ),
			__( 'Yhteyshenkilö', 'rytkoset-theme' ),
			__( 'Yhteyshenkilön sähköposti', 'rytkoset-theme' ),
			__( 'Tilausnumero', 'rytkoset-theme' ),
		),
		';'
	);

	foreach ( $rows as $row ) {
		$details = trim( (string) ( $row['diet'] ?? '' ) );
		$notes   = trim( (string) ( $row['notes'] ?? '' ) );

		if ( '' !== $notes ) {
			$details = '' !== $details ? $details . "\n" . $notes : $notes;
		}

		$source_label = isset( $row['source'] ) && 'paid' === $row['source']
			? __( 'Maksullinen', 'rytkoset-theme' )
			: __( 'Maksuton', 'rytkoset-theme' );

		fputcsv(
			$output,
			array(
				(string) ( $row['event_title'] ?? '' ),
				(string) ( $row['name'] ?? '' ),
				(string) ( $row['participant_type'] ?? '' ),
				! empty( $row['friday_buffet'] ) ? __( 'Kyllä', 'rytkoset-theme' ) : __( 'Ei', 'rytkoset-theme' ),
				(string) ( $row['email'] ?? '' ),
				(string) ( $row['phone'] ?? '' ),
				$details,
				$source_label,
				(string) ( $row['status_label'] ?? '' ),
				(string) ( $row['created'] ?? '' ),
				(string) ( $row['contact_name'] ?? '' ),
				(string) ( $row['contact_email'] ?? '' ),
				isset( $row['order_number'] ) && null !== $row['order_number'] ? (string) $row['order_number'] : '',
			),
			';'
		);
	}

	fclose( $output );
	exit;
}
add_action( 'admin_post_rytkoset_export_event_participants_csv', 'rytkoset_theme_export_event_participants_csv' );

/**
 * Renders the unified event participants admin page.
 */
function rytkoset_theme_render_event_participants_admin_page() {
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

	if ( $selected_event > 0 && 'rytkoset_event' !== get_post_type( $selected_event ) ) {
		$selected_event = 0;
	}

	$rows = $selected_event > 0
		? rytkoset_theme_get_event_participants( $selected_event, $selected_status )
		: rytkoset_theme_get_all_events_participants( $selected_status );

	$total_count = count( $rows );
	$free_count  = 0;
	$paid_count  = 0;

	foreach ( $rows as $row ) {
		if ( isset( $row['source'] ) && 'paid' === $row['source'] ) {
			$paid_count++;
		} else {
			$free_count++;
		}
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tapahtumien osallistujat', 'rytkoset-theme' ); ?></h1>
		<p><?php esc_html_e( 'Valitse tapahtuma nähdäksesi sekä maksuttomat että maksulliset ilmoittautumiset yhtenäisenä listana.', 'rytkoset-theme' ); ?></p>
		<?php rytkoset_theme_render_event_participants_anonymization_notice(); ?>

		<div class="tablenav top">
			<div class="alignleft actions" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
				<form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
					<input type="hidden" name="post_type" value="rytkoset_event" />
					<input type="hidden" name="page" value="rytkoset-event-participants" />

					<label for="rytkoset-event-participants-event">
						<?php esc_html_e( 'Tapahtuma:', 'rytkoset-theme' ); ?>
					</label>
					<select name="event_id" id="rytkoset-event-participants-event">
						<option value="0"><?php esc_html_e( 'Kaikki tapahtumat', 'rytkoset-theme' ); ?></option>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$event_date    = rytkoset_theme_get_event_date_display( $event->ID );
							$option_label  = $event->post_title;

							if ( '' !== $event_date ) {
								$option_label .= ' (' . $event_date . ')';
							}
							?>
							<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $selected_event, $event->ID ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="rytkoset-event-participants-status">
						<?php esc_html_e( 'Status:', 'rytkoset-theme' ); ?>
					</label>
					<select name="status" id="rytkoset-event-participants-status">
						<?php foreach ( $status_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_status, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<?php submit_button( __( 'Suodata', 'rytkoset-theme' ), 'secondary', '', false ); ?>
				</form>

				<?php rytkoset_theme_render_event_participants_export_form( $selected_event, $selected_status ); ?>

				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: total, 2: free count, 3: paid count */
							__( 'Yhteensä %1$d osallistujaa (maksuttomat %2$d, maksulliset %3$d).', 'rytkoset-theme' ),
							$total_count,
							$free_count,
							$paid_count
						)
					);
					?>
				</p>
			</div>
			<br class="clear" />
		</div>

		<?php rytkoset_theme_render_event_participants_anonymization_form( $selected_event ); ?>

		<?php $show_event_column = 0 === $selected_event; ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Nimi', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Osallistujatyyppi', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Perjantain buffet', 'rytkoset-theme' ); ?></th>
					<?php if ( $show_event_column ) : ?>
						<th scope="col"><?php esc_html_e( 'Tapahtuma', 'rytkoset-theme' ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Puhelin', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Ruokavalio / huomiot', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Lähde', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Ilmoittautunut', 'rytkoset-theme' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Toiminnot', 'rytkoset-theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="<?php echo $show_event_column ? 11 : 10; ?>"><?php esc_html_e( 'Ei osallistujia valitulla suodatuksella.', 'rytkoset-theme' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row['name'] ); ?></td>
							<td><?php echo '' !== (string) $row['participant_type'] ? esc_html( (string) $row['participant_type'] ) : '&mdash;'; ?></td>
							<td>
								<?php
								echo ! empty( $row['friday_buffet'] )
									? esc_html__( 'Kyllä', 'rytkoset-theme' )
									: esc_html__( 'Ei', 'rytkoset-theme' );
								?>
							</td>
							<?php if ( $show_event_column ) : ?>
								<td>
									<?php
									$event_id_for_row = isset( $row['event_id'] ) ? (int) $row['event_id'] : 0;

									if ( $event_id_for_row > 0 ) {
										$event_link = add_query_arg(
											array(
												'post_type' => 'rytkoset_event',
												'page'      => 'rytkoset-event-participants',
												'event_id'  => $event_id_for_row,
												'status'    => $selected_status,
											),
											admin_url( 'edit.php' )
										);
										echo '<a href="' . esc_url( $event_link ) . '">' . esc_html( (string) $row['event_title'] ) . '</a>';
									} else {
										echo '&mdash;';
									}
									?>
								</td>
							<?php endif; ?>
								<td>
									<?php if ( '' !== (string) $row['email'] ) : ?>
										<a href="mailto:<?php echo esc_attr( (string) $row['email'] ); ?>"><?php echo esc_html( (string) $row['email'] ); ?></a>
									<?php elseif ( '' !== (string) $row['contact_email'] ) : ?>
										<a href="mailto:<?php echo esc_attr( (string) $row['contact_email'] ); ?>"><?php echo esc_html( (string) $row['contact_email'] ); ?></a>
										<span class="description">(<?php esc_html_e( 'tilaaja', 'rytkoset-theme' ); ?>)</span>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td>
									<?php if ( '' !== (string) $row['phone'] ) : ?>
										<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', (string) $row['phone'] ) ); ?>"><?php echo esc_html( (string) $row['phone'] ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td>
									<?php
									$details = trim( (string) $row['diet'] );
									if ( '' !== (string) $row['notes'] ) {
										$details = trim( $details . "\n" . (string) $row['notes'] );
									}
									echo '' !== $details ? esc_html( $details ) : '&mdash;';
									?>
								</td>
								<td>
									<?php
									echo 'paid' === $row['source']
										? esc_html__( 'Maksullinen', 'rytkoset-theme' )
										: esc_html__( 'Maksuton', 'rytkoset-theme' );
									?>
								</td>
								<td><?php echo esc_html( (string) $row['status_label'] ); ?></td>
								<td><?php echo esc_html( (string) $row['created'] ); ?></td>
								<td>
									<?php if ( '' !== (string) $row['edit_url'] ) : ?>
										<a href="<?php echo esc_url( (string) $row['edit_url'] ); ?>">
											<?php
											echo 'paid' === $row['source']
												? esc_html( '#' . (string) $row['order_number'] )
												: esc_html__( 'Muokkaa', 'rytkoset-theme' );
											?>
										</a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
	</div>
	<?php
}
