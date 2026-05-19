<?php
/**
 * Tapahtumien ilmoittautumiset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns meta keys used for event registrations.
 *
 * @return array
 */
function rytkoset_theme_get_event_registration_meta_keys() {
	return array(
		'event_id'     => '_rytkoset_registration_event_id',
		'name'         => '_rytkoset_registration_name',
		'email'        => '_rytkoset_registration_email',
		'diet'         => '_rytkoset_registration_diet',
		'notes'        => '_rytkoset_registration_notes',
		'status'       => '_rytkoset_registration_status',
		'gdpr_consent' => '_rytkoset_registration_gdpr_consent',
	);
}

/**
 * Returns supported registration statuses.
 *
 * @return array
 */
function rytkoset_theme_get_event_registration_statuses() {
	return array(
		'pending'   => __( 'Odottaa vahvistusta', 'rytkoset-theme' ),
		'confirmed' => __( 'Vahvistettu', 'rytkoset-theme' ),
		'cancelled' => __( 'Peruttu', 'rytkoset-theme' ),
	);
}

/**
 * Registers the event registration CPT.
 */
function rytkoset_theme_register_event_registration_cpt() {
	$labels = array(
		'name'               => __( 'Ilmoittautumiset', 'rytkoset-theme' ),
		'singular_name'      => __( 'Ilmoittautuminen', 'rytkoset-theme' ),
		'menu_name'          => __( 'Ilmoittautumiset', 'rytkoset-theme' ),
		'name_admin_bar'     => __( 'Ilmoittautuminen', 'rytkoset-theme' ),
		'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
		'add_new_item'       => __( 'Lisää uusi ilmoittautuminen', 'rytkoset-theme' ),
		'new_item'           => __( 'Uusi ilmoittautuminen', 'rytkoset-theme' ),
		'edit_item'          => __( 'Muokkaa ilmoittautumista', 'rytkoset-theme' ),
		'view_item'          => __( 'Näytä ilmoittautuminen', 'rytkoset-theme' ),
		'all_items'          => __( 'Ilmoittautumiset', 'rytkoset-theme' ),
		'search_items'       => __( 'Etsi ilmoittautumisia', 'rytkoset-theme' ),
		'not_found'          => __( 'Ilmoittautumisia ei löytynyt.', 'rytkoset-theme' ),
		'not_found_in_trash' => __( 'Roskakorissa ei ole ilmoittautumisia.', 'rytkoset-theme' ),
	);

	$args = array(
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=event',
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => false,
		'query_var'           => false,
		'rewrite'             => false,
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_icon'           => 'dashicons-id',
		'supports'            => false,
		'capability_type'     => rytkoset_theme_get_event_registration_capability_type(),
		'map_meta_cap'        => true,
	);

	register_post_type( 'event_registration', $args );
}
add_action( 'init', 'rytkoset_theme_register_event_registration_cpt' );

/**
 * Returns a registration meta value.
 *
 * @param int    $registration_id Registration post ID.
 * @param string $key             Meta key alias.
 * @return string
 */
function rytkoset_theme_get_event_registration_meta( $registration_id, $key ) {
	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

	if ( ! isset( $meta_keys[ $key ] ) ) {
		return '';
	}

	$value = get_post_meta( $registration_id, $meta_keys[ $key ], true );

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Checks whether the event ID points to an event post.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_is_valid_registration_event_id( $event_id ) {
	$event_id = absint( $event_id );

	return $event_id > 0 && 'event' === get_post_type( $event_id ) && 'trash' !== get_post_status( $event_id );
}

/**
 * Returns event options for the registration metabox.
 *
 * @return WP_Post[]
 */
function rytkoset_theme_get_event_registration_event_options() {
	return get_posts(
		array(
			'post_type'              => 'event',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
}

/**
 * Adds the registration details metabox.
 */
function rytkoset_theme_register_event_registration_metabox() {
	add_meta_box(
		'rytkoset_event_registration_details',
		__( 'Ilmoittautumisen tiedot', 'rytkoset-theme' ),
		'rytkoset_theme_render_event_registration_metabox',
		'event_registration',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_event_registration', 'rytkoset_theme_register_event_registration_metabox' );

/**
 * Renders the registration details metabox.
 *
 * @param WP_Post $post Registration post object.
 */
function rytkoset_theme_render_event_registration_metabox( $post ) {
	$event_id = absint( rytkoset_theme_get_event_registration_meta( $post->ID, 'event_id' ) );
	$name     = rytkoset_theme_get_event_registration_meta( $post->ID, 'name' );
	$email    = rytkoset_theme_get_event_registration_meta( $post->ID, 'email' );
	$diet     = rytkoset_theme_get_event_registration_meta( $post->ID, 'diet' );
	$notes    = rytkoset_theme_get_event_registration_meta( $post->ID, 'notes' );
	$status   = rytkoset_theme_get_event_registration_meta( $post->ID, 'status' );
	$events   = rytkoset_theme_get_event_registration_event_options();
	$statuses = rytkoset_theme_get_event_registration_statuses();

	if ( ! isset( $statuses[ $status ] ) ) {
		$status = 'pending';
	}

	wp_nonce_field( 'rytkoset_save_event_registration', 'rytkoset_event_registration_nonce' );
	?>
	<p>
		<label for="rytkoset_registration_event_id"><strong><?php esc_html_e( 'Tapahtuma', 'rytkoset-theme' ); ?></strong></label>
	</p>
	<select id="rytkoset_registration_event_id" name="rytkoset_registration_event_id" class="widefat">
		<option value=""><?php esc_html_e( 'Valitse tapahtuma', 'rytkoset-theme' ); ?></option>
		<?php foreach ( $events as $event ) : ?>
			<option value="<?php echo esc_attr( (string) $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>>
				<?php echo esc_html( get_the_title( $event ) ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<p>
		<label for="rytkoset_registration_name"><strong><?php esc_html_e( 'Osallistujan nimi', 'rytkoset-theme' ); ?></strong></label>
		<input type="text" id="rytkoset_registration_name" name="rytkoset_registration_name" class="widefat" value="<?php echo esc_attr( $name ); ?>" />
	</p>

	<p>
		<label for="rytkoset_registration_email"><strong><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></strong></label>
		<input type="email" id="rytkoset_registration_email" name="rytkoset_registration_email" class="widefat" value="<?php echo esc_attr( $email ); ?>" />
	</p>

	<p>
		<label for="rytkoset_registration_diet"><strong><?php esc_html_e( 'Ruokarajoitteet ja allergiat', 'rytkoset-theme' ); ?></strong></label>
		<textarea id="rytkoset_registration_diet" name="rytkoset_registration_diet" class="widefat" rows="3"><?php echo esc_textarea( $diet ); ?></textarea>
	</p>

	<p>
		<label for="rytkoset_registration_notes"><strong><?php esc_html_e( 'Lisätieto', 'rytkoset-theme' ); ?></strong></label>
		<textarea id="rytkoset_registration_notes" name="rytkoset_registration_notes" class="widefat" rows="4"><?php echo esc_textarea( $notes ); ?></textarea>
	</p>

	<p>
		<label for="rytkoset_registration_status"><strong><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></strong></label>
	</p>
	<select id="rytkoset_registration_status" name="rytkoset_registration_status" class="widefat">
		<?php foreach ( $statuses as $status_key => $status_label ) : ?>
			<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>>
				<?php echo esc_html( $status_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Builds the admin title for an event registration.
 *
 * @param string $name     Participant name.
 * @param int    $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_build_event_registration_title( $name, $event_id ) {
	$name        = trim( $name );
	$event_title = rytkoset_theme_is_valid_registration_event_id( $event_id ) ? get_the_title( $event_id ) : '';

	if ( '' === $name ) {
		$name = __( 'Nimetön osallistuja', 'rytkoset-theme' );
	}

	if ( '' === $event_title ) {
		return $name;
	}

	return sprintf(
		/* translators: 1: participant name, 2: event title. */
		__( '%1$s - %2$s', 'rytkoset-theme' ),
		$name,
		$event_title
	);
}

/**
 * Saves event registration meta.
 *
 * @param int $post_id Registration post ID.
 */
function rytkoset_theme_save_event_registration( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_event_registration_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_event_registration_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_event_registration' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$meta_keys      = rytkoset_theme_get_event_registration_meta_keys();
	$status_options = rytkoset_theme_get_event_registration_statuses();
	$event_id       = isset( $_POST['rytkoset_registration_event_id'] ) ? absint( wp_unslash( $_POST['rytkoset_registration_event_id'] ) ) : 0;
	$name           = isset( $_POST['rytkoset_registration_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rytkoset_registration_name'] ) ) : '';
	$email          = isset( $_POST['rytkoset_registration_email'] ) ? sanitize_email( wp_unslash( $_POST['rytkoset_registration_email'] ) ) : '';
	$diet           = isset( $_POST['rytkoset_registration_diet'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rytkoset_registration_diet'] ) ) : '';
	$notes          = isset( $_POST['rytkoset_registration_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rytkoset_registration_notes'] ) ) : '';
	$status         = isset( $_POST['rytkoset_registration_status'] ) ? sanitize_key( wp_unslash( $_POST['rytkoset_registration_status'] ) ) : 'pending';

	if ( ! rytkoset_theme_is_valid_registration_event_id( $event_id ) ) {
		$event_id = 0;
	}

	if ( '' !== $email && ! is_email( $email ) ) {
		$email = '';
	}

	if ( ! isset( $status_options[ $status ] ) ) {
		$status = 'pending';
	}

	$values = array(
		'event_id' => $event_id,
		'name'     => $name,
		'email'    => $email,
		'diet'     => $diet,
		'notes'    => $notes,
		'status'   => $status,
	);

	foreach ( $values as $key => $value ) {
		if ( '' === $value || 0 === $value ) {
			delete_post_meta( $post_id, $meta_keys[ $key ] );
			continue;
		}

		update_post_meta( $post_id, $meta_keys[ $key ], $value );
	}

	$title = rytkoset_theme_build_event_registration_title( $name, $event_id );

	if ( get_the_title( $post_id ) !== $title ) {
		remove_action( 'save_post_event_registration', 'rytkoset_theme_save_event_registration' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);
		add_action( 'save_post_event_registration', 'rytkoset_theme_save_event_registration' );
	}
}
add_action( 'save_post_event_registration', 'rytkoset_theme_save_event_registration' );

/**
 * Defines admin columns for event registrations.
 *
 * @param array $columns Admin columns.
 * @return array
 */
function rytkoset_theme_event_registration_columns( $columns ) {
	return array(
		'cb'                           => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'rytkoset_registration_name'   => __( 'Osallistuja', 'rytkoset-theme' ),
		'rytkoset_registration_event'  => __( 'Tapahtuma', 'rytkoset-theme' ),
		'rytkoset_registration_email'  => __( 'Sähköposti', 'rytkoset-theme' ),
		'rytkoset_registration_status' => __( 'Tila', 'rytkoset-theme' ),
		'date'                         => __( 'Päivämäärä', 'rytkoset-theme' ),
	);
}
add_filter( 'manage_event_registration_posts_columns', 'rytkoset_theme_event_registration_columns' );

/**
 * Renders admin column content for event registrations.
 *
 * @param string $column  Column key.
 * @param int    $post_id Registration post ID.
 */
function rytkoset_theme_event_registration_column_content( $column, $post_id ) {
	$statuses = rytkoset_theme_get_event_registration_statuses();

	if ( 'rytkoset_registration_name' === $column ) {
		$name = rytkoset_theme_get_event_registration_meta( $post_id, 'name' );
		$url  = get_edit_post_link( $post_id );

		if ( '' === $name ) {
			$name = __( 'Nimetön osallistuja', 'rytkoset-theme' );
		}

		if ( $url ) {
			echo '<a class="row-title" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
			return;
		}

		echo esc_html( $name );
		return;
	}

	if ( 'rytkoset_registration_event' === $column ) {
		$event_id = absint( rytkoset_theme_get_event_registration_meta( $post_id, 'event_id' ) );

		if ( ! rytkoset_theme_is_valid_registration_event_id( $event_id ) ) {
			echo '&mdash;';
			return;
		}

		$url = get_edit_post_link( $event_id );

		if ( $url ) {
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $event_id ) ) . '</a>';
			return;
		}

		echo esc_html( get_the_title( $event_id ) );
		return;
	}

	if ( 'rytkoset_registration_email' === $column ) {
		$email = rytkoset_theme_get_event_registration_meta( $post_id, 'email' );

		if ( '' === $email ) {
			echo '&mdash;';
			return;
		}

		echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		return;
	}

	if ( 'rytkoset_registration_status' === $column ) {
		$status = rytkoset_theme_get_event_registration_meta( $post_id, 'status' );

		echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['pending'] );
	}
}
add_action( 'manage_event_registration_posts_custom_column', 'rytkoset_theme_event_registration_column_content', 10, 2 );

/**
 * Returns a safe redirect URL for frontend registration submissions.
 *
 * @param int    $event_id Event post ID.
 * @param string $status   Submission status.
 * @param string $error    Optional error code.
 * @return string
 */
function rytkoset_theme_get_event_registration_redirect_url( $event_id, $status, $error = '' ) {
	$event_id = absint( $event_id );
	$url      = $event_id > 0 ? get_permalink( $event_id ) : '';

	if ( ! is_string( $url ) || '' === $url ) {
		$url = home_url( '/tapahtumat/' );
	}

	$args = array(
		'registration_status' => $status,
	);

	if ( '' !== $error ) {
		$args['registration_error'] = $error;
	}

	$redirect_url = add_query_arg( $args, $url );

	if ( $event_id > 0 ) {
		if ( 'success' === $status ) {
			$redirect_url .= '#event-registration-confirmed-' . $event_id;
		} elseif ( 'error' === $status ) {
			$redirect_url .= '#event-registration-form-' . $event_id;
		}
	}

	return $redirect_url;
}

/**
 * Checks whether an event can show the free registration form.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_event_can_show_free_registration_form( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 || 'event' !== get_post_type( $event_id ) ) {
		return false;
	}

	if ( 'free' !== rytkoset_theme_get_event_fee_type( $event_id ) ) {
		return false;
	}

	if ( absint( get_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), true ) ) > 0 ) {
		return false;
	}

	if ( '' !== rytkoset_theme_get_event_product_url( $event_id ) ) {
		return false;
	}

	return true;
}

/**
 * Redirects back to the event page with a frontend registration error.
 *
 * @param int    $event_id Event post ID.
 * @param string $error    Error code.
 */
function rytkoset_theme_handle_event_registration_error( $event_id, $error ) {
	wp_safe_redirect( rytkoset_theme_get_event_registration_redirect_url( $event_id, 'error', $error ) );
	exit;
}

/**
 * Handles free event registration form submissions.
 */
function rytkoset_theme_handle_event_registration_submission() {
	$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

	if (
		! isset( $_POST['rytkoset_event_registration_submit_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rytkoset_event_registration_submit_nonce'] ) ),
			'rytkoset_submit_event_registration'
		)
	) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_nonce' );
	}

	if ( $event_id <= 0 || 'event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_event' );
	}

	if ( ! rytkoset_theme_event_can_show_free_registration_form( $event_id ) ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_event' );
	}

	$name  = isset( $_POST['registration_name'] ) ? sanitize_text_field( wp_unslash( $_POST['registration_name'] ) ) : '';
	$email = isset( $_POST['registration_email'] ) ? sanitize_email( wp_unslash( $_POST['registration_email'] ) ) : '';
	$diet  = isset( $_POST['registration_diet'] ) ? sanitize_textarea_field( wp_unslash( $_POST['registration_diet'] ) ) : '';
	$notes = isset( $_POST['registration_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['registration_notes'] ) ) : '';

	if ( '' === $name ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'missing_name' );
	}

	if ( '' === $email || ! is_email( $email ) ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_email' );
	}

	$gdpr_consent = isset( $_POST['registration_gdpr_consent'] ) && '1' === wp_unslash( $_POST['registration_gdpr_consent'] );

	if ( ! $gdpr_consent ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'missing_consent' );
	}

	$meta_keys       = rytkoset_theme_get_event_registration_meta_keys();
	$registration_id = wp_insert_post(
		array(
			'post_type'   => 'event_registration',
			'post_status' => 'publish',
			'post_title'  => rytkoset_theme_build_event_registration_title( $name, $event_id ),
			'meta_input'  => array(
				$meta_keys['event_id']     => $event_id,
				$meta_keys['name']         => $name,
				$meta_keys['email']        => $email,
				$meta_keys['diet']         => $diet,
				$meta_keys['notes']        => $notes,
				$meta_keys['status']       => 'pending',
				$meta_keys['gdpr_consent'] => time(),
			),
		),
		true
	);

	if ( is_wp_error( $registration_id ) || $registration_id <= 0 ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'save_failed' );
	}

	wp_safe_redirect( rytkoset_theme_get_event_registration_redirect_url( $event_id, 'success' ) );
	exit;
}
add_action( 'admin_post_rytkoset_submit_event_registration', 'rytkoset_theme_handle_event_registration_submission' );
add_action( 'admin_post_nopriv_rytkoset_submit_event_registration', 'rytkoset_theme_handle_event_registration_submission' );

/**
 * Returns frontend registration feedback based on query parameters.
 *
 * @return array
 */
function rytkoset_theme_get_event_registration_feedback() {
	$status = isset( $_GET['registration_status'] ) ? sanitize_key( wp_unslash( $_GET['registration_status'] ) ) : '';

	if ( 'success' === $status ) {
		return array(
			'type'    => 'success',
			'message' => __( 'Ilmoittautuminen vastaanotettu. Kiitos!', 'rytkoset-theme' ),
		);
	}

	if ( 'error' !== $status ) {
		return array();
	}

	$error    = isset( $_GET['registration_error'] ) ? sanitize_key( wp_unslash( $_GET['registration_error'] ) ) : '';
	$messages = array(
		'missing_name'    => __( 'Tarkista ilmoittautumisen tiedot. Nimi on pakollinen.', 'rytkoset-theme' ),
		'invalid_email'   => __( 'Tarkista ilmoittautumisen tiedot. Sähköpostiosoite ei ole kelvollinen.', 'rytkoset-theme' ),
		'missing_consent' => __( 'Hyväksy tietosuojakäytäntö ennen lomakkeen lähettämistä.', 'rytkoset-theme' ),
	);

	return array(
		'type'    => 'error',
		'message' => isset( $messages[ $error ] )
			? $messages[ $error ]
			: __( 'Ilmoittautumista ei voitu tallentaa. Tarkista tiedot ja yritä uudelleen.', 'rytkoset-theme' ),
	);
}

/**
 * Renders the confirmation view shown after a successful registration.
 *
 * @param int $event_id Event post ID.
 */
function rytkoset_theme_render_event_registration_confirmation( $event_id ) {
	$event_id   = absint( $event_id );
	$section_id = 'event-registration-confirmed-' . $event_id;
	$title      = get_the_title( $event_id );
	$date       = rytkoset_theme_get_event_date_display( $event_id );
	$time       = rytkoset_theme_get_event_time_display( $event_id );
	$location   = rytkoset_theme_get_event_location( $event_id );
	?>
	<section class="event-registration event-registration--confirmed" aria-labelledby="<?php echo esc_attr( $section_id ); ?>">
		<h2 id="<?php echo esc_attr( $section_id ); ?>" class="event-registration__title">
			<?php esc_html_e( 'Ilmoittautuminen vastaanotettu!', 'rytkoset-theme' ); ?>
		</h2>
		<div class="event-registration__confirmation">
			<p class="event-registration__confirmation-lead">
				<?php esc_html_e( 'Ilmoittautumisesi on vastaanotettu. Otamme tarvittaessa yhteyttä sähköpostitse.', 'rytkoset-theme' ); ?>
			</p>
			<dl class="event-registration__confirmation-details">
				<?php if ( '' !== $title ) : ?>
					<div class="event-registration__confirmation-row">
						<dt><?php esc_html_e( 'Tapahtuma', 'rytkoset-theme' ); ?></dt>
						<dd><?php echo esc_html( $title ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $date ) : ?>
					<div class="event-registration__confirmation-row">
						<dt><?php esc_html_e( 'Päivämäärä', 'rytkoset-theme' ); ?></dt>
						<dd><?php echo esc_html( $date ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $time ) : ?>
					<div class="event-registration__confirmation-row">
						<dt><?php esc_html_e( 'Kellonaika', 'rytkoset-theme' ); ?></dt>
						<dd><?php echo esc_html( $time ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $location ) : ?>
					<div class="event-registration__confirmation-row">
						<dt><?php esc_html_e( 'Paikka', 'rytkoset-theme' ); ?></dt>
						<dd><?php echo esc_html( $location ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</div>
	</section>
	<?php
}

/**
 * Renders the free event registration form UI.
 *
 * @param int $event_id Event post ID.
 */
function rytkoset_theme_render_free_event_registration_form( $event_id ) {
	$event_id = absint( $event_id );

	if ( ! rytkoset_theme_event_can_show_free_registration_form( $event_id ) ) {
		return;
	}

	$form_id        = 'event-registration-form-' . $event_id;
	$description_id = 'event-registration-description-' . $event_id;
	$feedback       = rytkoset_theme_get_event_registration_feedback();

	if ( ! empty( $feedback ) && 'success' === $feedback['type'] ) {
		rytkoset_theme_render_event_registration_confirmation( $event_id );
		return;
	}
	?>
	<section class="event-registration" aria-labelledby="<?php echo esc_attr( $form_id . '-title' ); ?>">
		<h2 id="<?php echo esc_attr( $form_id . '-title' ); ?>" class="event-registration__title">
			<?php esc_html_e( 'Ilmoittaudu tapahtumaan', 'rytkoset-theme' ); ?>
		</h2>
		<p id="<?php echo esc_attr( $description_id ); ?>" class="event-registration__description">
			<?php esc_html_e( 'Tällä lomakkeella voit ilmoittautua maksuttomaan tapahtumaan.', 'rytkoset-theme' ); ?>
		</p>

		<?php if ( ! empty( $feedback ) && 'error' === $feedback['type'] ) : ?>
			<div class="event-registration__notice event-registration__notice--error" role="alert">
				<?php echo esc_html( $feedback['message'] ); ?>
			</div>
		<?php endif; ?>

		<form id="<?php echo esc_attr( $form_id ); ?>" class="event-registration__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>">
			<input type="hidden" name="action" value="rytkoset_submit_event_registration" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>" />
			<?php wp_nonce_field( 'rytkoset_submit_event_registration', 'rytkoset_event_registration_submit_nonce' ); ?>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-name' ); ?>">
					<?php esc_html_e( 'Osallistujan nimi', 'rytkoset-theme' ); ?>
					<span aria-hidden="true">*</span>
				</label>
				<input id="<?php echo esc_attr( $form_id . '-name' ); ?>" name="registration_name" type="text" autocomplete="name" required />
			</div>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-email' ); ?>">
					<?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?>
					<span aria-hidden="true">*</span>
				</label>
				<input id="<?php echo esc_attr( $form_id . '-email' ); ?>" name="registration_email" type="email" autocomplete="email" required />
			</div>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-diet' ); ?>">
					<?php esc_html_e( 'Ruokarajoitteet tai allergiat', 'rytkoset-theme' ); ?>
				</label>
				<textarea id="<?php echo esc_attr( $form_id . '-diet' ); ?>" name="registration_diet" rows="3"></textarea>
			</div>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-notes' ); ?>">
					<?php esc_html_e( 'Lisätieto', 'rytkoset-theme' ); ?>
				</label>
				<textarea id="<?php echo esc_attr( $form_id . '-notes' ); ?>" name="registration_notes" rows="4"></textarea>
			</div>

			<div class="event-registration__gdpr">
				<p class="event-registration__gdpr-notice">
					<?php esc_html_e( 'Ilmoittautumisen yhteydessä kerättyjä henkilötietoja (nimi, sähköpostiosoite, ruokarajoitteet ja lisätiedot) käytetään tapahtuman järjestämistä varten. Tietoja ei luovuteta ulkopuolisille.', 'rytkoset-theme' ); ?>
				</p>
				<label class="event-registration__gdpr-label" for="<?php echo esc_attr( $form_id . '-gdpr' ); ?>">
					<input id="<?php echo esc_attr( $form_id . '-gdpr' ); ?>" type="checkbox" name="registration_gdpr_consent" value="1" required aria-required="true" />
					<?php esc_html_e( 'Hyväksyn henkilötietojeni käsittelyn tapahtumaan ilmoittautumista varten.', 'rytkoset-theme' ); ?>
					<span aria-hidden="true">*</span>
				</label>
			</div>

			<p class="event-registration__required-note">
				<?php esc_html_e( '* Pakollinen kenttä', 'rytkoset-theme' ); ?>
			</p>

			<button type="submit" class="btn btn--primary event-registration__submit">
				<?php esc_html_e( 'Lähetä ilmoittautuminen', 'rytkoset-theme' ); ?>
			</button>
		</form>
	</section>
	<?php
}
