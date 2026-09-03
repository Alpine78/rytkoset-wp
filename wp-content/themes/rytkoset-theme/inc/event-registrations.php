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
		'event_id'             => '_rytkoset_registration_event_id',
		'name'                 => '_rytkoset_registration_name',
		'email'                => '_rytkoset_registration_email',
		'diet'                 => '_rytkoset_registration_diet',
		'notes'                => '_rytkoset_registration_notes',
		'status'               => '_rytkoset_registration_status',
		'choice'               => '_rytkoset_registration_choice',
		'quantity'             => '_rytkoset_registration_quantity',
		'gdpr_consent'         => '_rytkoset_registration_gdpr_consent',
		'anonymized_at'        => '_rytkoset_registration_anonymized_at',
		'source'               => '_rytkoset_registration_source',
		'personal_data_source' => '_rytkoset_registration_personal_data_source',
		'informed_status'      => '_rytkoset_registration_informed_status',
		'informed_at'          => '_rytkoset_registration_informed_at',
	);
}

/**
 * Returns the maximum quantity a single registration may request.
 *
 * @return int
 */
function rytkoset_theme_get_event_registration_max_quantity() {
	return (int) apply_filters( 'rytkoset_theme_event_registration_max_quantity', 10 );
}

/**
 * Normalizes a raw quantity into a positive integer within range.
 *
 * @param mixed $raw_value Raw quantity.
 * @return int
 */
function rytkoset_theme_normalize_event_registration_quantity( $raw_value ) {
	$count = absint( $raw_value );

	if ( $count < 1 ) {
		$count = 1;
	}

	$max = rytkoset_theme_get_event_registration_max_quantity();

	if ( $max > 0 && $count > $max ) {
		$count = $max;
	}

	return $count;
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
 * Returns the registration statuses that count as an active participation.
 *
 * A cancelled registration keeps its row (for history and undo) but is excluded
 * from the active headcount, event messaging and any feedback-request audience.
 *
 * @return string[]
 */
function rytkoset_theme_get_active_event_registration_statuses() {
	return array( 'pending', 'confirmed' );
}

/**
 * Returns supported registration source values and their admin labels.
 *
 * `web_form` is the public front-end form; `manual` is an organizer adding a
 * person who signed up off-site (phone, email, in person). A manual record is
 * never marked as paid and never carries a WooCommerce order.
 *
 * @return array<string,string>
 */
function rytkoset_theme_get_event_registration_source_labels() {
	return array(
		'web_form' => __( 'Verkkolomake', 'rytkoset-theme' ),
		'manual'   => __( 'Käsin lisätty', 'rytkoset-theme' ),
	);
}

/**
 * Normalizes a raw source value to a supported key.
 *
 * Anything unrecognized (including an empty value on a legacy record created
 * before this field existed) resolves to `web_form`.
 *
 * @param mixed $raw_source Raw source value.
 * @return string
 */
function rytkoset_theme_normalize_event_registration_source( $raw_source ) {
	$raw_source = sanitize_key( (string) $raw_source );
	$labels     = rytkoset_theme_get_event_registration_source_labels();

	return isset( $labels[ $raw_source ] ) ? $raw_source : 'web_form';
}

/**
 * Returns the admin label for a registration's stored source value.
 *
 * @param int $registration_id Registration post ID.
 * @return string
 */
function rytkoset_theme_get_event_registration_source_label( $registration_id ) {
	$source = rytkoset_theme_normalize_event_registration_source(
		rytkoset_theme_get_event_registration_meta( $registration_id, 'source' )
	);
	$labels = rytkoset_theme_get_event_registration_source_labels();

	return isset( $labels[ $source ] ) ? $labels[ $source ] : $labels['web_form'];
}

/**
 * Returns informing-state values and labels for manually added registrations.
 *
 * GDPR art. 14: when personal data is not obtained from the data subject, the
 * controller must inform them. This tracks whether that has happened for an
 * off-site sign-up. It is deliberately NOT a consent record — an organizer
 * action is never stored as the data subject's consent.
 *
 * @return array<string,string>
 */
function rytkoset_theme_get_event_registration_informed_status_labels() {
	return array(
		'not_informed' => __( 'Ei informoitu', 'rytkoset-theme' ),
		'informed'     => __( 'Informoitu', 'rytkoset-theme' ),
	);
}

/**
 * Normalizes a raw informing-status value to a supported key.
 *
 * @param mixed $raw_status Raw informing-status value.
 * @return string
 */
function rytkoset_theme_normalize_event_registration_informed_status( $raw_status ) {
	$raw_status = sanitize_key( (string) $raw_status );
	$labels     = rytkoset_theme_get_event_registration_informed_status_labels();

	return isset( $labels[ $raw_status ] ) ? $raw_status : 'not_informed';
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
		'show_in_menu'        => 'edit.php?post_type=rytkoset_event',
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

	return $event_id > 0 && 'rytkoset_event' === get_post_type( $event_id ) && 'trash' !== get_post_status( $event_id );
}

/**
 * Returns event options for the registration metabox.
 *
 * @return WP_Post[]
 */
function rytkoset_theme_get_event_registration_event_options() {
	return get_posts(
		array(
			'post_type'              => 'rytkoset_event',
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
	$is_new   = 'auto-draft' === $post->post_status;
	$event_id = absint( rytkoset_theme_get_event_registration_meta( $post->ID, 'event_id' ) );

	// On a fresh "Lisää uusi ilmoittautuminen" screen opened from the participants
	// page the event is passed in the URL so the event-specific choice/quantity
	// fields render immediately, without a two-step save.
	if ( $is_new && $event_id <= 0 ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pre-select of a dropdown; the save itself is nonce-protected.
		$preselected = isset( $_GET['rytkoset_event_id'] ) ? absint( wp_unslash( $_GET['rytkoset_event_id'] ) ) : 0;

		if ( rytkoset_theme_is_valid_registration_event_id( $preselected ) ) {
			$event_id = $preselected;
		}
	}

	$name             = rytkoset_theme_get_event_registration_meta( $post->ID, 'name' );
	$email            = rytkoset_theme_get_event_registration_meta( $post->ID, 'email' );
	$diet             = rytkoset_theme_get_event_registration_meta( $post->ID, 'diet' );
	$notes            = rytkoset_theme_get_event_registration_meta( $post->ID, 'notes' );
	$status           = rytkoset_theme_get_event_registration_meta( $post->ID, 'status' );
	$events           = rytkoset_theme_get_event_registration_event_options();
	$statuses         = rytkoset_theme_get_event_registration_statuses();
	$source_labels    = rytkoset_theme_get_event_registration_source_labels();
	$informed_labels  = rytkoset_theme_get_event_registration_informed_status_labels();
	$has_choice       = $event_id > 0 && rytkoset_theme_event_has_choice_field( $event_id );
	$choice_options   = $has_choice ? rytkoset_theme_get_event_choice_options( $event_id ) : array();
	$choice_label     = $event_id > 0 ? rytkoset_theme_get_event_choice_field_label( $event_id ) : '';
	$collect_quantity = $event_id > 0 && rytkoset_theme_event_collects_quantity( $event_id );
	$quantity_label   = $event_id > 0 ? rytkoset_theme_get_event_quantity_field_label( $event_id ) : '';
	$choice_value     = rytkoset_theme_get_event_registration_meta( $post->ID, 'choice' );
	$quantity         = absint( rytkoset_theme_get_event_registration_meta( $post->ID, 'quantity' ) );
	$max_quantity     = rytkoset_theme_get_event_registration_max_quantity();

	$stored_source        = rytkoset_theme_get_event_registration_meta( $post->ID, 'source' );
	$source               = $is_new && '' === $stored_source
		? 'manual'
		: rytkoset_theme_normalize_event_registration_source( $stored_source );
	$personal_data_source = rytkoset_theme_get_event_registration_meta( $post->ID, 'personal_data_source' );
	$informed_status      = rytkoset_theme_normalize_event_registration_informed_status(
		rytkoset_theme_get_event_registration_meta( $post->ID, 'informed_status' )
	);
	$informed_at          = rytkoset_theme_get_event_registration_meta( $post->ID, 'informed_at' );

	if ( ! isset( $statuses[ $status ] ) ) {
		$status = $is_new ? 'confirmed' : 'pending';
	}

	wp_nonce_field( 'rytkoset_save_event_registration', 'rytkoset_event_registration_nonce' );
	?>
	<p class="description">
		<?php esc_html_e( 'Tällä näkymällä lisätään sivuston ulkopuolella (esimerkiksi puhelimitse tai sähköpostitse) ilmoittautunut osallistuja tapahtumaan. Tallennus ei lähetä automaattista kuittia osallistujalle eikä järjestäjäilmoitusta.', 'rytkoset-theme' ); ?>
	</p>
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
		<label for="rytkoset_registration_source"><strong><?php esc_html_e( 'Ilmoittautumisen lähde', 'rytkoset-theme' ); ?></strong></label>
	</p>
	<select id="rytkoset_registration_source" name="rytkoset_registration_source" class="widefat">
		<?php foreach ( $source_labels as $source_key => $source_label ) : ?>
			<option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( $source, $source_key ); ?>>
				<?php echo esc_html( $source_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php esc_html_e( 'Käsin lisättyä osallistujaa ei merkitä maksaneeksi eikä WooCommerce-osallistujaksi.', 'rytkoset-theme' ); ?>
	</p>

	<p>
		<label for="rytkoset_registration_name"><strong><?php esc_html_e( 'Osallistujan nimi', 'rytkoset-theme' ); ?></strong></label>
		<input type="text" id="rytkoset_registration_name" name="rytkoset_registration_name" class="widefat" value="<?php echo esc_attr( $name ); ?>" />
	</p>

	<p>
		<label for="rytkoset_registration_email"><strong><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></strong></label>
		<input type="email" id="rytkoset_registration_email" name="rytkoset_registration_email" class="widefat" value="<?php echo esc_attr( $email ); ?>" />
	</p>

	<?php if ( $has_choice ) : ?>
		<p>
			<label for="rytkoset_registration_choice"><strong><?php echo esc_html( $choice_label ); ?></strong></label>
		</p>
		<select id="rytkoset_registration_choice" name="rytkoset_registration_choice" class="widefat">
			<option value=""><?php esc_html_e( 'Ei valintaa', 'rytkoset-theme' ); ?></option>
			<?php
			$choice_select_options = $choice_options;

			if ( '' !== $choice_value && ! in_array( $choice_value, $choice_select_options, true ) ) {
				$choice_select_options[] = $choice_value;
			}

			foreach ( $choice_select_options as $point ) :
				?>
				<option value="<?php echo esc_attr( $point ); ?>" <?php selected( $choice_value, $point ); ?>>
					<?php echo esc_html( $point ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	<?php endif; ?>

	<?php if ( $collect_quantity ) : ?>
		<p>
			<label for="rytkoset_registration_quantity"><strong><?php echo esc_html( $quantity_label ); ?></strong></label>
			<input type="number" id="rytkoset_registration_quantity" name="rytkoset_registration_quantity" class="widefat" min="1" max="<?php echo esc_attr( (string) $max_quantity ); ?>" step="1" value="<?php echo esc_attr( $quantity > 0 ? (string) $quantity : '1' ); ?>" />
		</p>
	<?php endif; ?>

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

	<hr />

	<p>
		<label for="rytkoset_registration_personal_data_source"><strong><?php esc_html_e( 'Henkilötiedon lähde', 'rytkoset-theme' ); ?></strong></label>
		<input type="text" id="rytkoset_registration_personal_data_source" name="rytkoset_registration_personal_data_source" class="widefat" value="<?php echo esc_attr( $personal_data_source ); ?>" maxlength="200" />
	</p>
	<p class="description">
		<?php esc_html_e( 'Käytä vain kun tiedot on saatu muualta kuin rekisteröidyltä itseltään, esimerkiksi "ilmoittautuja itse puhelimitse" tai "huoltaja ilmoitti". Älä tuo vanhoja yhteystietolistoja.', 'rytkoset-theme' ); ?>
	</p>

	<p>
		<label for="rytkoset_registration_informed_status"><strong><?php esc_html_e( 'Informoinnin tila', 'rytkoset-theme' ); ?></strong></label>
	</p>
	<select id="rytkoset_registration_informed_status" name="rytkoset_registration_informed_status" class="widefat">
		<?php foreach ( $informed_labels as $informed_key => $informed_label ) : ?>
			<option value="<?php echo esc_attr( $informed_key ); ?>" <?php selected( $informed_status, $informed_key ); ?>>
				<?php echo esc_html( $informed_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php
		if ( '' !== $informed_at ) {
			echo esc_html(
				sprintf(
					/* translators: %s: formatted date and time. */
					__( 'Merkitty informoiduksi: %s', 'rytkoset-theme' ),
					rytkoset_theme_format_event_registration_consent_timestamp( $informed_at )
				)
			);
		} else {
			esc_html_e( 'Kun tiedot on saatu muualta kuin rekisteröidyltä, informointi tehdään dokumentoidusti, viimeistään ensimmäisen henkilökohtaisen tapahtumaviestin yhteydessä. Tämä ei ole suostumusmerkintä.', 'rytkoset-theme' );
		}
		?>
	</p>

	<hr />

	<p>
		<label>
			<input type="checkbox" name="rytkoset_registration_allow_duplicate" value="1" />
			<?php esc_html_e( 'Salli tietoinen kaksoiskappale (sama tapahtuma ja sähköposti)', 'rytkoset-theme' ); ?>
		</label>
	</p>
	<p class="description">
		<?php esc_html_e( 'Ilman tätä valintaa tallennus, jossa on jo aktiivinen ilmoittautuminen samalla tapahtumalla ja sähköpostilla, tallennetaan Peruttu-tilassa vahingossa syntyvän kaksoiskappaleen estämiseksi.', 'rytkoset-theme' ); ?>
	</p>
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

	$source          = rytkoset_theme_normalize_event_registration_source(
		isset( $_POST['rytkoset_registration_source'] ) ? wp_unslash( $_POST['rytkoset_registration_source'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalized against an allowlist below.
	);
	$personal_source = isset( $_POST['rytkoset_registration_personal_data_source'] )
		? sanitize_text_field( wp_unslash( $_POST['rytkoset_registration_personal_data_source'] ) )
		: '';
	$informed_status = rytkoset_theme_normalize_event_registration_informed_status(
		isset( $_POST['rytkoset_registration_informed_status'] ) ? wp_unslash( $_POST['rytkoset_registration_informed_status'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalized against an allowlist below.
	);
	$allow_duplicate = ! empty( $_POST['rytkoset_registration_allow_duplicate'] );

	if ( ! rytkoset_theme_is_valid_registration_event_id( $event_id ) ) {
		$event_id = 0;
	}

	if ( '' !== $email && ! is_email( $email ) ) {
		$email = '';
	}

	if ( ! isset( $status_options[ $status ] ) ) {
		$status = 'pending';
	}

	// Accidental-duplicate guard: if the same event + email already has an active
	// registration and the organizer did not explicitly allow a duplicate, store
	// the row as cancelled so it does not silently enter the active list. The row
	// is kept and can be restored, and a re-save with "salli kaksoiskappale"
	// ticked keeps the chosen status.
	$forced_cancel_for_duplicate = false;

	if (
		$event_id > 0
		&& '' !== $email
		&& ! $allow_duplicate
		&& in_array( $status, rytkoset_theme_get_active_event_registration_statuses(), true )
		&& rytkoset_theme_event_has_active_registration_for_email( $event_id, $email, $post_id )
	) {
		$status                      = 'cancelled';
		$forced_cancel_for_duplicate = true;
	}

	$values = array(
		'event_id' => $event_id,
		'name'     => $name,
		'email'    => $email,
		'diet'     => $diet,
		'notes'    => $notes,
		'status'   => $status,
		'source'   => $source,
	);

	foreach ( $values as $key => $value ) {
		if ( '' === $value || 0 === $value ) {
			delete_post_meta( $post_id, $meta_keys[ $key ] );
			continue;
		}

		update_post_meta( $post_id, $meta_keys[ $key ], $value );
	}

	if ( $event_id > 0 && rytkoset_theme_event_has_choice_field( $event_id ) ) {
		$raw_choice   = isset( $_POST['rytkoset_registration_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['rytkoset_registration_choice'] ) ) : '';
		$choice_value = rytkoset_theme_resolve_event_choice_value( $event_id, $raw_choice );

		if ( '' === $choice_value ) {
			delete_post_meta( $post_id, $meta_keys['choice'] );
		} else {
			update_post_meta( $post_id, $meta_keys['choice'], $choice_value );
		}
	}

	if ( $event_id > 0 && rytkoset_theme_event_collects_quantity( $event_id ) ) {
		$raw_quantity = isset( $_POST['rytkoset_registration_quantity'] ) ? absint( wp_unslash( $_POST['rytkoset_registration_quantity'] ) ) : 0;

		if ( $raw_quantity > 0 ) {
			update_post_meta( $post_id, $meta_keys['quantity'], rytkoset_theme_normalize_event_registration_quantity( $raw_quantity ) );
		} else {
			delete_post_meta( $post_id, $meta_keys['quantity'] );
		}
	}

	if ( '' === $personal_source ) {
		delete_post_meta( $post_id, $meta_keys['personal_data_source'] );
	} else {
		update_post_meta( $post_id, $meta_keys['personal_data_source'], $personal_source );
	}

	update_post_meta( $post_id, $meta_keys['informed_status'], $informed_status );

	// The informing timestamp is derived, never entered by hand: it is stamped
	// once when the row first becomes "Informoitu" and cleared if it is set back.
	if ( 'informed' === $informed_status ) {
		if ( '' === rytkoset_theme_get_event_registration_meta( $post_id, 'informed_at' ) ) {
			update_post_meta( $post_id, $meta_keys['informed_at'], time() );
		}
	} else {
		delete_post_meta( $post_id, $meta_keys['informed_at'] );
	}

	if ( $forced_cancel_for_duplicate ) {
		set_transient( 'rytkoset_evt_reg_dupe_notice_' . get_current_user_id(), absint( $post_id ), MINUTE_IN_SECONDS );
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
 * Shows a warning after a registration was stored cancelled as an accidental duplicate.
 */
function rytkoset_theme_event_registration_duplicate_admin_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen instanceof WP_Screen || 'event_registration' !== $screen->post_type ) {
		return;
	}

	$transient_key = 'rytkoset_evt_reg_dupe_notice_' . get_current_user_id();
	$flagged_id    = absint( get_transient( $transient_key ) );

	if ( $flagged_id <= 0 ) {
		return;
	}

	delete_transient( $transient_key );

	echo '<div class="notice notice-warning is-dismissible"><p>';
	echo esc_html__( 'Samalla tapahtumalla ja sähköpostilla on jo aktiivinen ilmoittautuminen. Tietue tallennettiin Peruttu-tilassa. Jos kaksoiskappale on tarkoituksellinen, rastita "Salli tietoinen kaksoiskappale" ja tallenna uudelleen.', 'rytkoset-theme' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'rytkoset_theme_event_registration_duplicate_admin_notice' );

/**
 * Sets the status of an event registration and keeps the admin title in sync.
 *
 * Used by the participants-list cancel/restore action. Only touches the status
 * meta and the post title; personal data and the row itself are untouched, so a
 * cancellation is reversible and the sign-up history is preserved.
 *
 * @param int    $registration_id Registration post ID.
 * @param string $status          Target status key.
 * @return bool True on success.
 */
function rytkoset_theme_set_event_registration_status( $registration_id, $status ) {
	$registration_id = absint( $registration_id );
	$status          = sanitize_key( $status );
	$statuses        = rytkoset_theme_get_event_registration_statuses();

	if ( $registration_id <= 0 || 'event_registration' !== get_post_type( $registration_id ) ) {
		return false;
	}

	if ( ! isset( $statuses[ $status ] ) ) {
		return false;
	}

	$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

	update_post_meta( $registration_id, $meta_keys['status'], $status );

	$name     = rytkoset_theme_get_event_registration_meta( $registration_id, 'name' );
	$event_id = absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'event_id' ) );
	$title    = rytkoset_theme_build_event_registration_title( $name, $event_id );

	if ( get_the_title( $registration_id ) !== $title ) {
		remove_action( 'save_post_event_registration', 'rytkoset_theme_save_event_registration' );
		wp_update_post(
			array(
				'ID'         => $registration_id,
				'post_title' => $title,
			)
		);
		add_action( 'save_post_event_registration', 'rytkoset_theme_save_event_registration' );
	}

	return true;
}

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

	if ( $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) ) {
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

	if (
		function_exists( 'rytkoset_theme_is_event_registration_deadline_passed' )
		&& rytkoset_theme_is_event_registration_deadline_passed( $event_id )
	) {
		return false;
	}

	return true;
}

/**
 * Checks whether an active registration already exists for an event email.
 *
 * Cancelled registrations are intentionally ignored so a cancelled participant
 * can register again.
 *
 * @param int    $event_id   Event post ID.
 * @param string $email      Registration email.
 * @param int    $exclude_id Optional registration post ID to exclude (the row being edited).
 * @return bool
 */
function rytkoset_theme_event_has_active_registration_for_email( $event_id, $email, $exclude_id = 0 ) {
	$event_id   = absint( $event_id );
	$email      = sanitize_email( $email );
	$exclude_id = absint( $exclude_id );

	if ( $event_id <= 0 || '' === $email ) {
		return false;
	}

	$meta_keys  = rytkoset_theme_get_event_registration_meta_keys();
	$query_args = array(
		'post_type'              => 'event_registration',
		'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'posts_per_page'         => 1,
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
				'key'   => $meta_keys['email'],
				'value' => $email,
			),
			array(
				'key'     => $meta_keys['status'],
				'value'   => rytkoset_theme_get_active_event_registration_statuses(),
				'compare' => 'IN',
			),
		),
	);

	if ( $exclude_id > 0 ) {
		$query_args['post__not_in'] = array( $exclude_id );
	}

	$existing = get_posts( $query_args );

	return ! empty( $existing );
}

/**
 * Sends a lightweight receipt email after a successful free event registration.
 *
 * The email confirms receipt only. Registration status remains pending until
 * an organizer handles it in the admin.
 *
 * @param int    $event_id        Event post ID.
 * @param string $name            Participant name.
 * @param string $email           Participant email.
 * @param string $choice_value    Optional choice value.
 * @param int    $quantity        Optional quantity.
 * @return bool Whether WordPress accepted the email for sending.
 */
function rytkoset_theme_send_event_registration_receipt_email( $event_id, $name, $email, $choice_value = '', $quantity = 0 ) {
	$event_id     = absint( $event_id );
	$name         = trim( (string) $name );
	$email        = sanitize_email( $email );
	$choice_value = trim( (string) $choice_value );
	$quantity     = absint( $quantity );

	if ( $event_id <= 0 || '' === $email || ! is_email( $email ) ) {
		return false;
	}

	$event_title = get_the_title( $event_id );

	if ( '' === $event_title ) {
		$event_title = __( 'Tapahtuma', 'rytkoset-theme' );
	}

	$event_title_plain = wp_specialchars_decode( $event_title, ENT_QUOTES );
	$subject           = sprintf(
		/* translators: %s: event title. */
		__( 'Ilmoittautuminen vastaanotettu: %s', 'rytkoset-theme' ),
		$event_title_plain
	);

	$lines = array(
		'' !== $name
			? sprintf(
				/* translators: %s: participant name. */
				__( 'Hei %s,', 'rytkoset-theme' ),
				$name
			)
			: __( 'Hei,', 'rytkoset-theme' ),
		'',
		__( 'Kiitos ilmoittautumisesta. Ilmoittautumisesi on vastaanotettu.', 'rytkoset-theme' ),
		__( 'Järjestäjä ottaa tarvittaessa yhteyttä sähköpostitse.', 'rytkoset-theme' ),
		'',
		__( 'Tapahtuman tiedot:', 'rytkoset-theme' ),
		sprintf(
			/* translators: %s: event title. */
			__( 'Tapahtuma: %s', 'rytkoset-theme' ),
			$event_title_plain
		),
	);

	$date     = rytkoset_theme_get_event_date_display( $event_id );
	$time     = rytkoset_theme_get_event_time_display( $event_id );
	$location = rytkoset_theme_get_event_location( $event_id );

	if ( '' !== $date ) {
		$lines[] = sprintf(
			/* translators: %s: event date. */
			__( 'Päivämäärä: %s', 'rytkoset-theme' ),
			$date
		);
	}

	if ( '' !== $time ) {
		$lines[] = sprintf(
			/* translators: %s: event time. */
			__( 'Aika: %s', 'rytkoset-theme' ),
			$time
		);
	}

	if ( '' !== $location ) {
		$lines[] = sprintf(
			/* translators: %s: event location. */
			__( 'Paikka: %s', 'rytkoset-theme' ),
			$location
		);
	}

	if ( '' !== $choice_value ) {
		// Label is admin-defined per event, so it is concatenated, not a format string.
		$lines[] = rytkoset_theme_get_event_choice_field_label( $event_id ) . ': ' . $choice_value;
	}

	if ( $quantity > 0 ) {
		$lines[] = rytkoset_theme_get_event_quantity_field_label( $event_id ) . ': ' . $quantity;
	}

	$lines[] = '';
	$lines[] = __( 'Terveisin', 'rytkoset-theme' );
	$lines[] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	return wp_mail(
		$email,
		$subject,
		implode( "\n", $lines ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Notifies the event organizers about a new free event registration.
 *
 * Recipients come from the event's own organizer notification field — the same
 * field the paid WooCommerce order path uses. An empty field means no email is
 * sent; there is deliberately no admin-email fallback, so participant details
 * never reach an address nobody configured for this purpose.
 *
 * The message carries only the event basics plus the participant's name and
 * email. Diet restrictions, notes, the extra choice and the quantity stay in
 * the admin views linked from the message.
 *
 * @param int $registration_id Registration post ID.
 * @return bool Whether WordPress accepted the email for sending.
 */
function rytkoset_theme_send_event_registration_organizer_notification( $registration_id ) {
	$registration_id = absint( $registration_id );

	if ( $registration_id <= 0 || 'event_registration' !== get_post_type( $registration_id ) ) {
		return false;
	}

	$event_id = absint( rytkoset_theme_get_event_registration_meta( $registration_id, 'event_id' ) );

	if ( $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) ) {
		return false;
	}

	$recipients = rytkoset_theme_get_event_organizer_notification_recipients( $event_id );

	if ( empty( $recipients ) ) {
		return false;
	}

	$name  = trim( rytkoset_theme_get_event_registration_meta( $registration_id, 'name' ) );
	$email = sanitize_email( rytkoset_theme_get_event_registration_meta( $registration_id, 'email' ) );

	$event_title = get_the_title( $event_id );

	if ( '' === $event_title ) {
		$event_title = __( 'Tapahtuma', 'rytkoset-theme' );
	}

	$event_title_plain = wp_specialchars_decode( $event_title, ENT_QUOTES );
	$subject           = sprintf(
		/* translators: %s: event title. */
		__( 'Uusi ilmoittautuminen: %s', 'rytkoset-theme' ),
		$event_title_plain
	);

	$lines = array(
		__( 'Tapahtumaan on tullut uusi ilmoittautuminen maksuttomalla lomakkeella.', 'rytkoset-theme' ),
		__( 'Ilmoittautuminen odottaa käsittelyä, kunnes järjestäjä muuttaa sen tilan ylläpidossa.', 'rytkoset-theme' ),
		'',
		__( 'Tapahtuman tiedot:', 'rytkoset-theme' ),
		sprintf(
			/* translators: %s: event title. */
			__( 'Tapahtuma: %s', 'rytkoset-theme' ),
			$event_title_plain
		),
	);

	$date     = rytkoset_theme_get_event_date_display( $event_id );
	$time     = rytkoset_theme_get_event_time_display( $event_id );
	$location = rytkoset_theme_get_event_location( $event_id );

	if ( '' !== $date ) {
		$lines[] = sprintf(
			/* translators: %s: event date. */
			__( 'Päivämäärä: %s', 'rytkoset-theme' ),
			$date
		);
	}

	if ( '' !== $time ) {
		$lines[] = sprintf(
			/* translators: %s: event time. */
			__( 'Aika: %s', 'rytkoset-theme' ),
			$time
		);
	}

	if ( '' !== $location ) {
		$lines[] = sprintf(
			/* translators: %s: event location. */
			__( 'Paikka: %s', 'rytkoset-theme' ),
			$location
		);
	}

	$not_given = __( 'Ei annettu', 'rytkoset-theme' );

	$lines[] = '';
	$lines[] = __( 'Ilmoittautujan tiedot:', 'rytkoset-theme' );
	$lines[] = sprintf(
		/* translators: %s: participant name. */
		__( 'Nimi: %s', 'rytkoset-theme' ),
		'' !== $name ? $name : $not_given
	);
	$lines[] = sprintf(
		/* translators: %s: participant email address. */
		__( 'Sähköposti: %s', 'rytkoset-theme' ),
		'' !== $email ? $email : $not_given
	);

	$lines[] = '';
	$lines[] = __( 'Katso loput tiedot ylläpidosta:', 'rytkoset-theme' );
	// get_edit_post_link() is intentionally avoided: it bails on an edit_post
	// capability check, and this email is sent from an anonymous admin-post
	// request, so the link would silently disappear.
	$lines[] = admin_url( 'post.php?post=' . $registration_id . '&action=edit' );
	$lines[] = add_query_arg(
		array(
			'post_type' => 'rytkoset_event',
			'page'      => 'rytkoset-event-participants',
			'event_id'  => $event_id,
		),
		admin_url( 'edit.php' )
	);

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	if ( '' !== $email && is_email( $email ) ) {
		$headers[] = 'Reply-To: ' . $email;
	}

	return wp_mail(
		$recipients,
		$subject,
		implode( "\n", $lines ),
		$headers
	);
}

/**
 * Returns the client IP for the current request.
 *
 * Uses only `REMOTE_ADDR`; forwarded headers (`X-Forwarded-For` etc.) are not
 * trusted because they are client-spoofable. Behind a reverse proxy this may be
 * the proxy address, which weakens the per-IP throttle — that is an ops concern,
 * not a correctness one.
 *
 * @return string Validated IP, or empty string when unavailable.
 */
function rytkoset_theme_get_event_registration_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with FILTER_VALIDATE_IP below.

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Maximum free-event registration submissions allowed per IP within the window.
 *
 * @return int
 */
function rytkoset_theme_get_event_registration_rate_limit() {
	return (int) apply_filters( 'rytkoset_theme_event_registration_rate_limit', 5 );
}

/**
 * Rolling window (seconds) for the per-IP submission rate limit.
 *
 * @return int
 */
function rytkoset_theme_get_event_registration_rate_limit_window() {
	return (int) apply_filters( 'rytkoset_theme_event_registration_rate_limit_window', 10 * MINUTE_IN_SECONDS );
}

/**
 * Whether the current client IP has exceeded the submission rate limit.
 *
 * Lightweight abuse control: a bot bypassing the honeypot could otherwise submit
 * the public form repeatedly and trigger unbounded receipt emails (mail abuse,
 * burning the documented ~18 emails/hour host limit) and database writes. A
 * rolling window of attempt timestamps is stored in a per-IP transient. A
 * legitimate one-off registration never hits the limit. IP rotation can evade
 * this, so it is a mitigation, not a hard guarantee. When no IP is available
 * (e.g. CLI) the request is not blocked. Records the attempt when not limited.
 *
 * @return bool True when the submission should be blocked.
 */
function rytkoset_theme_event_registration_is_rate_limited() {
	$ip = rytkoset_theme_get_event_registration_client_ip();

	if ( '' === $ip ) {
		return false;
	}

	$limit  = rytkoset_theme_get_event_registration_rate_limit();
	$window = rytkoset_theme_get_event_registration_rate_limit_window();

	if ( $limit <= 0 || $window <= 0 ) {
		return false;
	}

	$key  = 'rytkoset_evt_reg_rl_' . md5( $ip );
	$now  = time();
	$hits = get_transient( $key );
	$hits = is_array( $hits )
		? array_values(
			array_filter(
				$hits,
				static function ( $timestamp ) use ( $now, $window ) {
								return ( $now - (int) $timestamp ) < $window;
				}
			)
		)
		: array();

	if ( count( $hits ) >= $limit ) {
		return true;
	}

	$hits[] = $now;
	set_transient( $key, $hits, $window );

	return false;
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
	$website  = isset( $_POST['website'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Honeypot is intentionally checked before other validation.
		? trim( sanitize_text_field( wp_unslash( $_POST['website'] ) ) )
		: '';

	if ( '' !== $website ) {
		$redirect_url = $event_id > 0 ? get_permalink( $event_id ) : '';

		if ( ! is_string( $redirect_url ) || '' === $redirect_url ) {
			$redirect_url = home_url( '/tapahtumat/' );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	if (
		! isset( $_POST['rytkoset_event_registration_submit_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rytkoset_event_registration_submit_nonce'] ) ),
			'rytkoset_submit_event_registration'
		)
	) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_nonce' );
	}

	if ( $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_event' );
	}

	if ( ! rytkoset_theme_event_can_show_free_registration_form( $event_id ) ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_event' );
	}

	// Lightweight per-IP throttle so repeated submissions cannot trigger
	// unbounded receipt emails or database writes. Checked before save + email.
	if ( rytkoset_theme_event_registration_is_rate_limited() ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'rate_limited' );
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

	$gdpr_consent = isset( $_POST['registration_gdpr_consent'] ) && '1' === wp_unslash( $_POST['registration_gdpr_consent'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact allowlisted comparison.

	if ( ! $gdpr_consent ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'missing_consent' );
	}

	if ( rytkoset_theme_event_has_active_registration_for_email( $event_id, $email ) ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'already_registered' );
	}

	$choice_value = '';
	$quantity     = 0;

	if ( rytkoset_theme_event_has_choice_field( $event_id ) ) {
		$choice_options = rytkoset_theme_get_event_choice_options( $event_id );

		if ( ! empty( $choice_options ) ) {
			$raw_choice   = isset( $_POST['registration_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['registration_choice'] ) ) : '';
			$choice_value = rytkoset_theme_resolve_event_choice_value( $event_id, $raw_choice );

			if ( '' === $choice_value ) {
				rytkoset_theme_handle_event_registration_error( $event_id, 'invalid_choice' );
			}
		}
	}

	if ( rytkoset_theme_event_collects_quantity( $event_id ) ) {
		$raw_quantity = isset( $_POST['registration_quantity'] ) ? absint( wp_unslash( $_POST['registration_quantity'] ) ) : 0;
		$quantity     = rytkoset_theme_normalize_event_registration_quantity( $raw_quantity );
	}

	$meta_keys  = rytkoset_theme_get_event_registration_meta_keys();
	$meta_input = array(
		$meta_keys['event_id']     => $event_id,
		$meta_keys['name']         => $name,
		$meta_keys['email']        => $email,
		$meta_keys['diet']         => $diet,
		$meta_keys['notes']        => $notes,
		$meta_keys['status']       => 'pending',
		$meta_keys['gdpr_consent'] => time(),
		$meta_keys['source']       => 'web_form',
	);

	if ( '' !== $choice_value ) {
		$meta_input[ $meta_keys['choice'] ] = $choice_value;
	}

	if ( $quantity > 0 ) {
		$meta_input[ $meta_keys['quantity'] ] = $quantity;
	}

	$registration_id = wp_insert_post(
		array(
			'post_type'   => 'event_registration',
			'post_status' => 'publish',
			'post_title'  => rytkoset_theme_build_event_registration_title( $name, $event_id ),
			'meta_input'  => $meta_input,
		),
		true
	);

	if ( is_wp_error( $registration_id ) || $registration_id <= 0 ) {
		rytkoset_theme_handle_event_registration_error( $event_id, 'save_failed' );
	}

	rytkoset_theme_send_event_registration_receipt_email( $event_id, $name, $email, $choice_value, $quantity );

	// Independent of the receipt above: a failed participant email must not
	// suppress the organizer notification, or vice versa.
	rytkoset_theme_send_event_registration_organizer_notification( $registration_id );

	if ( ! empty( $_POST['registration_newsletter_opt_in'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['registration_newsletter_opt_in'] ) ) && function_exists( 'rytkoset_theme_subscribe_email_to_newsletter' ) ) {
		$newsletter_result = rytkoset_theme_subscribe_email_to_newsletter( $email, 'event_registration', get_current_user_id() );

		if ( is_wp_error( $newsletter_result ) && function_exists( 'rytkoset_theme_log_newsletter_error' ) ) {
			rytkoset_theme_log_newsletter_error( 'event_registration', $newsletter_result->get_error_message() );
		}
	}

	wp_safe_redirect( rytkoset_theme_get_event_registration_redirect_url( $event_id, 'success' ) );
	exit;
}
add_action( 'admin_post_rytkoset_submit_event_registration', 'rytkoset_theme_handle_event_registration_submission' );
add_action( 'admin_post_nopriv_rytkoset_submit_event_registration', 'rytkoset_theme_handle_event_registration_submission' );

/**
 * Maps a registration error code to the form field it concerns.
 *
 * Used to wire up aria-invalid + aria-describedby on the failing field after a
 * redirect-based submission.
 *
 * @param string $error_code Error code from the redirect query string.
 * @return string Field key (name|email|gdpr) or empty string when no specific field applies.
 */
function rytkoset_theme_get_event_registration_error_field( $error_code ) {
	$map = array(
		'missing_name'       => 'name',
		'invalid_email'      => 'email',
		'already_registered' => 'email',
		'missing_consent'    => 'gdpr',
		'invalid_choice'     => 'choice',
	);

	return isset( $map[ $error_code ] ) ? $map[ $error_code ] : '';
}

/**
 * Returns the privacy notice shown on the free event registration form.
 *
 * The listed data follows the event-specific diet field configuration so the
 * notice never claims that diet information is collected when the field is
 * disabled.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_registration_privacy_notice( $event_id ) {
	if ( rytkoset_theme_event_collects_diet( $event_id ) ) {
		return __( 'Ilmoittautumisen yhteydessä kerättyjä henkilötietoja (nimi, sähköpostiosoite, ruokarajoitteet ja lisätiedot) käytetään tapahtuman järjestämistä varten. Tietoja ei luovuteta ulkopuolisille.', 'rytkoset-theme' );
	}

	return __( 'Ilmoittautumisen yhteydessä kerättyjä henkilötietoja (nimi, sähköpostiosoite ja lisätiedot) käytetään tapahtuman järjestämistä varten. Tietoja ei luovuteta ulkopuolisille.', 'rytkoset-theme' );
}

/**
 * Returns frontend registration feedback based on query parameters.
 *
 * @return array
 */
function rytkoset_theme_get_event_registration_feedback() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect feedback set by the nonce-protected registration action.
	$status = isset( $_GET['registration_status'] ) ? sanitize_key( wp_unslash( $_GET['registration_status'] ) ) : '';

	if ( 'success' === $status ) {
		return array(
			'type'    => 'success',
			'code'    => '',
			'field'   => '',
			'message' => __( 'Ilmoittautuminen vastaanotettu. Kiitos!', 'rytkoset-theme' ),
		);
	}

	if ( 'error' !== $status ) {
		return array();
	}

	$error = isset( $_GET['registration_error'] ) ? sanitize_key( wp_unslash( $_GET['registration_error'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$messages = array(
		'missing_name'       => __( 'Tarkista ilmoittautumisen tiedot. Nimi on pakollinen.', 'rytkoset-theme' ),
		'invalid_email'      => __( 'Tarkista ilmoittautumisen tiedot. Sähköpostiosoite ei ole kelvollinen.', 'rytkoset-theme' ),
		'missing_consent'    => __( 'Hyväksy tietosuojakäytäntö ennen lomakkeen lähettämistä.', 'rytkoset-theme' ),
		'already_registered' => __( 'Tällä sähköpostiosoitteella on jo aktiivinen ilmoittautuminen tähän tapahtumaan.', 'rytkoset-theme' ),
		'invalid_choice'     => __( 'Valitse vaihtoehto.', 'rytkoset-theme' ),
		'rate_limited'       => __( 'Liian monta ilmoittautumisyritystä lyhyessä ajassa. Odota hetki ja yritä uudelleen.', 'rytkoset-theme' ),
	);

	return array(
		'type'    => 'error',
		'code'    => $error,
		'field'   => rytkoset_theme_get_event_registration_error_field( $error ),
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
	$notice_id      = 'event-registration-notice-' . $event_id;
	$feedback       = rytkoset_theme_get_event_registration_feedback();

	if ( ! empty( $feedback ) && 'success' === $feedback['type'] ) {
		rytkoset_theme_render_event_registration_confirmation( $event_id );
		return;
	}

	$invalid_field    = ! empty( $feedback ) && 'error' === $feedback['type'] && ! empty( $feedback['field'] )
		? $feedback['field']
		: '';
	$invalid_attrs    = ' aria-invalid="true" aria-describedby="' . esc_attr( $notice_id ) . '"';
	$name_invalid     = 'name' === $invalid_field ? $invalid_attrs : '';
	$email_invalid    = 'email' === $invalid_field ? $invalid_attrs : '';
	$gdpr_invalid     = 'gdpr' === $invalid_field ? $invalid_attrs : '';
	$choice_invalid   = 'choice' === $invalid_field ? $invalid_attrs : '';
	$has_choice       = rytkoset_theme_event_has_choice_field( $event_id );
	$choice_options   = $has_choice ? rytkoset_theme_get_event_choice_options( $event_id ) : array();
	$choice_label     = rytkoset_theme_get_event_choice_field_label( $event_id );
	$collect_quantity = rytkoset_theme_event_collects_quantity( $event_id );
	$collect_diet     = rytkoset_theme_event_collects_diet( $event_id );
	$quantity_label   = rytkoset_theme_get_event_quantity_field_label( $event_id );
	$max_quantity     = rytkoset_theme_get_event_registration_max_quantity();
	?>
	<section class="event-registration" aria-labelledby="<?php echo esc_attr( $form_id . '-title' ); ?>">
		<h2 id="<?php echo esc_attr( $form_id . '-title' ); ?>" class="event-registration__title">
			<?php esc_html_e( 'Ilmoittaudu tapahtumaan', 'rytkoset-theme' ); ?>
		</h2>
		<p id="<?php echo esc_attr( $description_id ); ?>" class="event-registration__description">
			<?php esc_html_e( 'Tällä lomakkeella voit ilmoittautua maksuttomaan tapahtumaan.', 'rytkoset-theme' ); ?>
		</p>

		<?php if ( ! empty( $feedback ) && 'error' === $feedback['type'] ) : ?>
			<div id="<?php echo esc_attr( $notice_id ); ?>" class="event-registration__notice event-registration__notice--error" role="alert">
				<?php echo esc_html( $feedback['message'] ); ?>
			</div>
		<?php endif; ?>

		<form id="<?php echo esc_attr( $form_id ); ?>" class="event-registration__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>" data-invalid-field="<?php echo esc_attr( $invalid_field ); ?>">
			<input type="hidden" name="action" value="rytkoset_submit_event_registration" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>" />
			<input type="text" name="website" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="display:none" />
			<?php wp_nonce_field( 'rytkoset_submit_event_registration', 'rytkoset_event_registration_submit_nonce' ); ?>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-name' ); ?>">
					<?php esc_html_e( 'Osallistujan nimi', 'rytkoset-theme' ); ?>
					<span aria-hidden="true">*</span>
				</label>
				<input id="<?php echo esc_attr( $form_id . '-name' ); ?>" name="registration_name" type="text" autocomplete="name" required aria-required="true"<?php echo $name_invalid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			</div>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-email' ); ?>">
					<?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?>
					<span aria-hidden="true">*</span>
				</label>
				<input id="<?php echo esc_attr( $form_id . '-email' ); ?>" name="registration_email" type="email" autocomplete="email" required aria-required="true"<?php echo $email_invalid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			</div>

			<?php if ( $has_choice && ! empty( $choice_options ) ) : ?>
				<div class="event-registration__field">
					<label for="<?php echo esc_attr( $form_id . '-choice' ); ?>">
						<?php echo esc_html( $choice_label ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<select id="<?php echo esc_attr( $form_id . '-choice' ); ?>" name="registration_choice" required aria-required="true"<?php echo $choice_invalid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<option value=""><?php esc_html_e( 'Valitse…', 'rytkoset-theme' ); ?></option>
						<?php foreach ( $choice_options as $choice_option ) : ?>
							<option value="<?php echo esc_attr( $choice_option ); ?>"><?php echo esc_html( $choice_option ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( $collect_quantity ) : ?>
				<div class="event-registration__field">
					<label for="<?php echo esc_attr( $form_id . '-quantity' ); ?>">
						<?php echo esc_html( $quantity_label ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input id="<?php echo esc_attr( $form_id . '-quantity' ); ?>" name="registration_quantity" type="number" inputmode="numeric" min="1" max="<?php echo esc_attr( (string) $max_quantity ); ?>" step="1" value="1" required aria-required="true" />
				</div>
			<?php endif; ?>

			<?php if ( $collect_diet ) : ?>
				<div class="event-registration__field">
					<label for="<?php echo esc_attr( $form_id . '-diet' ); ?>">
						<?php esc_html_e( 'Ruokarajoitteet tai allergiat', 'rytkoset-theme' ); ?>
					</label>
					<textarea id="<?php echo esc_attr( $form_id . '-diet' ); ?>" name="registration_diet" rows="3"></textarea>
				</div>
			<?php endif; ?>

			<div class="event-registration__field">
				<label for="<?php echo esc_attr( $form_id . '-notes' ); ?>">
					<?php esc_html_e( 'Lisätieto', 'rytkoset-theme' ); ?>
				</label>
				<textarea id="<?php echo esc_attr( $form_id . '-notes' ); ?>" name="registration_notes" rows="4"></textarea>
			</div>

			<div class="event-registration__gdpr">
				<p class="event-registration__gdpr-notice">
					<?php echo esc_html( rytkoset_theme_get_event_registration_privacy_notice( $event_id ) ); ?>
					<?php
					$privacy_url = get_privacy_policy_url();
					if ( $privacy_url ) {
						printf(
							' ' . wp_kses(
								/* translators: %s: link to the privacy policy page */
								__( 'Lue lisää <a href="%s">tietosuojaselosteesta</a>.', 'rytkoset-theme' ),
								array( 'a' => array( 'href' => true ) )
							),
							esc_url( $privacy_url )
						);
					}
					?>
				</p>
				<label class="event-registration__gdpr-label" for="<?php echo esc_attr( $form_id . '-gdpr' ); ?>">
					<input id="<?php echo esc_attr( $form_id . '-gdpr' ); ?>" type="checkbox" name="registration_gdpr_consent" value="1" required aria-required="true"<?php echo $gdpr_invalid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
					<?php esc_html_e( 'Hyväksyn henkilötietojeni käsittelyn tapahtumaan ilmoittautumista varten.', 'rytkoset-theme' ); ?>
					<span aria-hidden="true">*</span>
				</label>
			</div>

			<?php if ( function_exists( 'rytkoset_theme_should_show_newsletter_opt_in' ) && function_exists( 'rytkoset_theme_render_newsletter_opt_in_checkbox' ) && rytkoset_theme_should_show_newsletter_opt_in() ) : ?>
				<?php
				rytkoset_theme_render_newsletter_opt_in_checkbox(
					$form_id . '-newsletter',
					'registration_newsletter_opt_in',
					'event-registration__newsletter'
				);
				?>
			<?php endif; ?>

			<p class="event-registration__required-note">
				<?php esc_html_e( '* Pakollinen kenttä', 'rytkoset-theme' ); ?>
			</p>

			<button type="submit" class="btn btn--primary event-registration__submit">
				<?php esc_html_e( 'Lähetä ilmoittautuminen', 'rytkoset-theme' ); ?>
			</button>
		</form>
	</section>
	<?php if ( '' !== $invalid_field ) : ?>
		<script>
			(function () {
				var form = document.getElementById(<?php echo wp_json_encode( $form_id ); ?>);
				if (!form) { return; }
				var invalidInput = form.querySelector('[aria-invalid="true"]');
				if (!invalidInput) { return; }
				try { invalidInput.focus({ preventScroll: false }); } catch (e) { invalidInput.focus(); }
			})();
		</script>
		<?php
	endif;
	?>
	<?php
}
