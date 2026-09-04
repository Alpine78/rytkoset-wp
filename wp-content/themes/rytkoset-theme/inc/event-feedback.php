<?php
/**
 * Tapahtumakohtainen palautekysely (#666).
 *
 * MVP: per-event feedback survey settings (disabled/manual/automatic), an
 * anonymous public response form/CPT, a `{palautelinkki}` placeholder in the
 * existing throttled event-messaging queue, an idempotent WP-Cron auto-queue
 * sweep, and an admin aggregate view. See docs/event-feedback.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Event-level feedback settings (meta on the `rytkoset_event` CPT).
 * --------------------------------------------------------------------- */

/**
 * Returns meta keys used for an event's feedback survey settings.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_event_feedback_meta_keys() {
	return array(
		'mode'              => '_rytkoset_event_feedback_mode',
		'send_at'           => '_rytkoset_event_feedback_send_at',
		'deadline'          => '_rytkoset_event_feedback_deadline',
		'intro'             => '_rytkoset_event_feedback_intro',
		'notify_organizers' => '_rytkoset_event_feedback_notify_organizers',
		'queued_at'         => '_rytkoset_event_feedback_queued_at',
	);
}

/**
 * Returns supported feedback survey modes and their admin labels.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_event_feedback_modes() {
	return array(
		'disabled'  => __( 'Ei palautekyselyä', 'rytkoset-theme' ),
		'manual'    => __( 'Lähetä käsin', 'rytkoset-theme' ),
		'automatic' => __( 'Lähetä automaattisesti', 'rytkoset-theme' ),
	);
}

/**
 * Returns an event's feedback survey mode. Defaults to 'disabled'.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_mode( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	$mode      = get_post_meta( absint( $event_id ), $meta_keys['mode'], true );
	$modes     = rytkoset_theme_get_event_feedback_modes();

	return isset( $modes[ $mode ] ) ? $mode : 'disabled';
}

/**
 * Normalizes a raw `datetime-local` value into `Y-m-d\TH:i`, site timezone.
 *
 * @param string $raw_value Raw datetime-local value.
 * @return string
 */
function rytkoset_theme_normalize_event_feedback_datetime( $raw_value ) {
	$raw_value = trim( (string) $raw_value );

	if ( '' === $raw_value ) {
		return '';
	}

	$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $raw_value, wp_timezone() );

	if ( ! $datetime ) {
		return '';
	}

	return $datetime->format( 'Y-m-d\TH:i' );
}

/**
 * Returns the stored, validated feedback send datetime.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_send_at_raw( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	$value     = get_post_meta( absint( $event_id ), $meta_keys['send_at'], true );

	return rytkoset_theme_normalize_event_feedback_datetime( is_scalar( $value ) ? (string) $value : '' );
}

/**
 * Returns the feedback send datetime as a Unix timestamp, or 0 when unset/invalid.
 *
 * @param int $event_id Event post ID.
 * @return int
 */
function rytkoset_theme_get_event_feedback_send_at_timestamp( $event_id ) {
	$raw = rytkoset_theme_get_event_feedback_send_at_raw( $event_id );

	if ( '' === $raw ) {
		return 0;
	}

	$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $raw, wp_timezone() );

	return $datetime ? $datetime->getTimestamp() : 0;
}

/**
 * Checks whether a candidate feedback send datetime falls after the event's own day.
 *
 * Reuses the same "end of day" cutoff as event dates/registration deadlines
 * (`rytkoset_theme_get_registration_deadline_cutoff_from_date()`), so a send
 * time on the event's own day still counts as valid only once that day ends.
 *
 * @param int    $event_id    Event post ID.
 * @param string $send_at_raw Raw datetime-local value.
 * @return bool
 */
function rytkoset_theme_event_feedback_send_at_is_after_event( $event_id, $send_at_raw ) {
	$send_at_raw = rytkoset_theme_normalize_event_feedback_datetime( $send_at_raw );

	if ( '' === $send_at_raw ) {
		return false;
	}

	$send_at_datetime = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $send_at_raw, wp_timezone() );

	if ( ! $send_at_datetime ) {
		return false;
	}

	$event_cutoff = rytkoset_theme_get_registration_deadline_cutoff_from_date(
		rytkoset_theme_get_event_date_raw( $event_id )
	);

	if ( ! $event_cutoff instanceof DateTimeImmutable ) {
		return false;
	}

	return $send_at_datetime > $event_cutoff;
}

/**
 * Returns the stored, validated feedback response deadline.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_deadline_raw( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	$value     = get_post_meta( absint( $event_id ), $meta_keys['deadline'], true );

	return rytkoset_theme_normalize_event_registration_deadline_date( is_scalar( $value ) ? (string) $value : '' );
}

/**
 * Returns an event's feedback survey introduction text.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_intro( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	$value     = get_post_meta( absint( $event_id ), $meta_keys['intro'], true );

	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Returns the timestamp a feedback request was queued for an event, if any.
 *
 * Written only by the sending code paths (manual button / auto-queue sweep),
 * never by the settings-save handler, so an already-queued state is never
 * silently lost when an admin edits mode/send_at/deadline/intro afterward.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_queued_at( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	$value     = get_post_meta( absint( $event_id ), $meta_keys['queued_at'], true );

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Checks whether a feedback request has already been queued for an event.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_event_feedback_is_queued( $event_id ) {
	return '' !== rytkoset_theme_get_event_feedback_queued_at( $event_id );
}

/**
 * Marks an event's feedback request as queued.
 *
 * @param int $event_id Event post ID.
 * @return void
 */
function rytkoset_theme_mark_event_feedback_queued( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	update_post_meta( absint( $event_id ), $meta_keys['queued_at'], current_time( 'mysql' ) );
}

/**
 * Checks whether new feedback responses should notify the event organizers.
 *
 * Reuses the event's existing organizer-notification recipients field
 * (`rytkoset_theme_get_event_organizer_notification_recipients()`,
 * inc/events.php) — the same field the paid order and free-registration paths
 * already use — so there is only one recipients list per event to maintain.
 * Opt-in, default off, matching the rest of this feature's disabled-by-default
 * settings.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_event_feedback_notifies_organizers( $event_id ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();

	return 'yes' === get_post_meta( absint( $event_id ), $meta_keys['notify_organizers'], true );
}

/**
 * Checks whether the feedback survey is currently open to the public.
 *
 * Open = mode is not 'disabled' AND the event date has passed AND (no
 * deadline, or the deadline has not passed). Deliberately independent of
 * `queued_at`: a shared link works even before any request email was sent.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function rytkoset_theme_event_feedback_survey_is_open( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) ) {
		return false;
	}

	if ( 'disabled' === rytkoset_theme_get_event_feedback_mode( $event_id ) ) {
		return false;
	}

	if ( ! rytkoset_theme_is_event_date_passed( $event_id ) ) {
		return false;
	}

	$deadline = rytkoset_theme_get_event_feedback_deadline_raw( $event_id );

	if ( '' === $deadline ) {
		return true;
	}

	$cutoff = rytkoset_theme_get_registration_deadline_cutoff_from_date( $deadline );

	if ( ! $cutoff instanceof DateTimeImmutable ) {
		return true;
	}

	return current_datetime() < $cutoff;
}

/**
 * Returns the maximum length accepted for a free-text feedback answer.
 *
 * @return int
 */
function rytkoset_theme_get_event_feedback_text_max_length() {
	return (int) apply_filters( 'rytkoset_theme_event_feedback_text_max_length', 500 );
}

/**
 * Sanitizes and length-caps a free-text feedback answer.
 *
 * @param string $raw_value Raw textarea value.
 * @return string
 */
function rytkoset_theme_sanitize_event_feedback_text( $raw_value ) {
	$value = sanitize_textarea_field( (string) $raw_value );
	$max   = rytkoset_theme_get_event_feedback_text_max_length();

	if ( $max <= 0 ) {
		return $value;
	}

	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
}

/**
 * Adds the event feedback settings metabox.
 */
function rytkoset_theme_register_event_feedback_metabox() {
	add_meta_box(
		'rytkoset_event_feedback',
		__( 'Palautekysely', 'rytkoset-theme' ),
		'rytkoset_theme_render_event_feedback_metabox',
		'rytkoset_event',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_rytkoset_event', 'rytkoset_theme_register_event_feedback_metabox' );

/**
 * Renders the event feedback settings metabox.
 *
 * @param WP_Post $post Event post object.
 */
function rytkoset_theme_render_event_feedback_metabox( $post ) {
	$mode              = rytkoset_theme_get_event_feedback_mode( $post->ID );
	$modes             = rytkoset_theme_get_event_feedback_modes();
	$send_at           = rytkoset_theme_get_event_feedback_send_at_raw( $post->ID );
	$deadline          = rytkoset_theme_get_event_feedback_deadline_raw( $post->ID );
	$intro             = rytkoset_theme_get_event_feedback_intro( $post->ID );
	$notify_organizers = rytkoset_theme_event_feedback_notifies_organizers( $post->ID );
	$organizer_emails  = rytkoset_theme_get_event_organizer_notification_recipients( $post->ID );
	$queued_at         = rytkoset_theme_get_event_feedback_queued_at( $post->ID );

	wp_nonce_field( 'rytkoset_save_event_feedback', 'rytkoset_event_feedback_nonce' );
	?>
	<p>
		<label for="rytkoset_event_feedback_mode"><?php esc_html_e( 'Palautekyselyn tila', 'rytkoset-theme' ); ?></label>
	</p>
	<select id="rytkoset_event_feedback_mode" name="rytkoset_event_feedback_mode" class="widefat">
		<?php foreach ( $modes as $mode_key => $mode_label ) : ?>
			<option value="<?php echo esc_attr( $mode_key ); ?>" <?php selected( $mode, $mode_key ); ?>>
				<?php echo esc_html( $mode_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<p>
		<label for="rytkoset_event_feedback_send_at"><?php esc_html_e( 'Automaattinen lähetysaika', 'rytkoset-theme' ); ?></label>
	</p>
	<input
		type="datetime-local"
		id="rytkoset_event_feedback_send_at"
		name="rytkoset_event_feedback_send_at"
		value="<?php echo esc_attr( $send_at ); ?>"
		class="widefat"
	/>
	<p class="description">
		<?php esc_html_e( 'Pakollinen vain tilassa "Lähetä automaattisesti". Ajan pitää olla tapahtumapäivän jälkeen; muuten tila tallennetaan "Lähetä käsin".', 'rytkoset-theme' ); ?>
	</p>

	<hr />

	<p>
		<label for="rytkoset_event_feedback_deadline"><?php esc_html_e( 'Palautteen määräpäivä', 'rytkoset-theme' ); ?></label>
	</p>
	<input
		type="date"
		id="rytkoset_event_feedback_deadline"
		name="rytkoset_event_feedback_deadline"
		value="<?php echo esc_attr( $deadline ); ?>"
		class="widefat"
	/>
	<p class="description">
		<?php esc_html_e( 'Valinnainen. Tyhjänä kysely pysyy avoinna toistaiseksi.', 'rytkoset-theme' ); ?>
	</p>

	<hr />

	<p>
		<label for="rytkoset_event_feedback_intro"><?php esc_html_e( 'Johdantoteksti', 'rytkoset-theme' ); ?></label>
	</p>
	<textarea
		id="rytkoset_event_feedback_intro"
		name="rytkoset_event_feedback_intro"
		rows="3"
		class="widefat"
		maxlength="<?php echo esc_attr( (string) rytkoset_theme_get_event_feedback_text_max_length() ); ?>"
	><?php echo esc_textarea( $intro ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'Näytetään palautelomakkeella ja käytetään palautepyynnön viestin alussa.', 'rytkoset-theme' ); ?>
	</p>

	<hr />

	<p>
		<label for="rytkoset_event_feedback_notify_organizers">
			<input
				type="checkbox"
				id="rytkoset_event_feedback_notify_organizers"
				name="rytkoset_event_feedback_notify_organizers"
				value="yes"
				<?php checked( $notify_organizers ); ?>
			/>
			<?php esc_html_e( 'Ilmoita järjestäjille uusista vastauksista', 'rytkoset-theme' ); ?>
		</label>
	</p>
	<p class="description">
		<?php
		if ( empty( $organizer_emails ) ) {
			esc_html_e( 'Järjestäjäilmoitukset-kenttä on tyhjä, joten ilmoitusta ei lähetetä, vaikka tämä olisi rastittuna.', 'rytkoset-theme' );
		} else {
			esc_html_e( 'Lähetetään samoille osoitteille kuin tapahtuman muutkin järjestäjäilmoitukset (ks. "Järjestäjäilmoitukset"-laatikko).', 'rytkoset-theme' );
		}
		?>
	</p>

	<?php if ( '' !== $queued_at ) : ?>
		<hr />
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: queued timestamp. */
					__( 'Palautepyyntö on jo lisätty lähetysjonoon: %s', 'rytkoset-theme' ),
					$queued_at
				)
			);
			?>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Saves the event feedback settings.
 *
 * `_rytkoset_event_feedback_queued_at` is deliberately never written here — see
 * `rytkoset_theme_get_event_feedback_queued_at()`.
 *
 * @param int $post_id Event post ID.
 */
function rytkoset_theme_save_event_feedback_settings( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_event_feedback_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_event_feedback_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_event_feedback' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();
	$modes     = rytkoset_theme_get_event_feedback_modes();

	$mode = isset( $_POST['rytkoset_event_feedback_mode'] )
		? sanitize_key( wp_unslash( $_POST['rytkoset_event_feedback_mode'] ) )
		: 'disabled';

	if ( ! isset( $modes[ $mode ] ) ) {
		$mode = 'disabled';
	}

	$send_at = isset( $_POST['rytkoset_event_feedback_send_at'] )
		? rytkoset_theme_normalize_event_feedback_datetime( sanitize_text_field( wp_unslash( $_POST['rytkoset_event_feedback_send_at'] ) ) )
		: '';

	// Automatic mode requires a send time strictly after the event's own day.
	// Silent fallback to "manual" mirrors the discard convention used by the
	// other event metabox save handlers in inc/events.php rather than half
	// saving an unusable automatic schedule.
	if ( 'automatic' === $mode && ( '' === $send_at || ! rytkoset_theme_event_feedback_send_at_is_after_event( $post_id, $send_at ) ) ) {
		$mode = 'manual';
	}

	update_post_meta( $post_id, $meta_keys['mode'], $mode );

	if ( '' === $send_at ) {
		delete_post_meta( $post_id, $meta_keys['send_at'] );
	} else {
		update_post_meta( $post_id, $meta_keys['send_at'], $send_at );
	}

	$deadline = isset( $_POST['rytkoset_event_feedback_deadline'] )
		? rytkoset_theme_normalize_event_registration_deadline_date( sanitize_text_field( wp_unslash( $_POST['rytkoset_event_feedback_deadline'] ) ) )
		: '';

	if ( '' === $deadline ) {
		delete_post_meta( $post_id, $meta_keys['deadline'] );
	} else {
		update_post_meta( $post_id, $meta_keys['deadline'], $deadline );
	}

	$intro = isset( $_POST['rytkoset_event_feedback_intro'] )
		? rytkoset_theme_sanitize_event_feedback_text( wp_unslash( $_POST['rytkoset_event_feedback_intro'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_sanitize_event_feedback_text().
		: '';

	if ( '' === $intro ) {
		delete_post_meta( $post_id, $meta_keys['intro'] );
	} else {
		update_post_meta( $post_id, $meta_keys['intro'], $intro );
	}

	// Checkbox: present means notify organizers on new responses (opt-in, default off).
	if ( isset( $_POST['rytkoset_event_feedback_notify_organizers'] )
		&& 'yes' === sanitize_text_field( wp_unslash( $_POST['rytkoset_event_feedback_notify_organizers'] ) ) ) {
		update_post_meta( $post_id, $meta_keys['notify_organizers'], 'yes' );
	} else {
		delete_post_meta( $post_id, $meta_keys['notify_organizers'] );
	}
}
add_action( 'save_post_rytkoset_event', 'rytkoset_theme_save_event_feedback_settings' );

/* -----------------------------------------------------------------------
 * Feedback response CPT (anonymous by design — see docs/event-feedback.md).
 * --------------------------------------------------------------------- */

/**
 * Returns meta keys used for a feedback response.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_event_feedback_response_meta_keys() {
	return array(
		'event_id' => '_rytkoset_feedback_event_id',
		'rating'   => '_rytkoset_feedback_rating',
		'well'     => '_rytkoset_feedback_well',
		'improve'  => '_rytkoset_feedback_improve',
		'wishes'   => '_rytkoset_feedback_wishes',
	);
}

/**
 * Registers the anonymous feedback response CPT.
 *
 * Deliberately shares its capability_type with `event_registration`
 * (`rytkoset_theme_get_event_registration_capability_type()`), so
 * `event_organizer`/`administrator` already have the required capabilities —
 * no role/capability-version bump needed. `show_ui` is false: there is no
 * generic post-list screen, only the dedicated aggregate admin page below.
 */
function rytkoset_theme_register_event_feedback_response_cpt() {
	$args = array(
		'labels'              => array(
			'name'          => __( 'Tapahtumapalautteet', 'rytkoset-theme' ),
			'singular_name' => __( 'Tapahtumapalaute', 'rytkoset-theme' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => false,
		'query_var'           => false,
		'rewrite'             => false,
		'has_archive'         => false,
		'hierarchical'        => false,
		'supports'            => false,
		'capability_type'     => rytkoset_theme_get_event_registration_capability_type(),
		'map_meta_cap'        => true,
	);

	register_post_type( 'event_feedback', $args );
}
add_action( 'init', 'rytkoset_theme_register_event_feedback_response_cpt' );

/**
 * Checks whether a submitted rating value is within the accepted 1–5 range.
 *
 * @param mixed $rating Raw rating value.
 * @return bool
 */
function rytkoset_theme_is_valid_event_feedback_rating( $rating ) {
	$rating = absint( $rating );

	return $rating >= 1 && $rating <= 5;
}

/* -----------------------------------------------------------------------
 * Recipient eligibility (#666).
 *
 * #665 already built the shared "active participant" audience for exactly
 * this purpose (`rytkoset_theme_filter_active_event_participants()` in
 * inc/event-participants-admin.php, already used by
 * `rytkoset_theme_get_event_messaging_recipients()` — see that function's own
 * comment: "This same trimmed set is the safe base for a future feedback
 * request."). Reused as-is rather than maintaining a second, feedback-specific
 * definition of "active": a cancelled free registration or a cancelled/
 * refunded/failed order is excluded; pending/on-hold orders and non-cancelled
 * free registrations are kept.
 * --------------------------------------------------------------------- */

/**
 * Returns deduplicated feedback-request recipients for an event, with a
 * three-way breakdown for the admin preview (participant rows, unique
 * addresses, rows without a usable address).
 *
 * @param int $event_id Event post ID.
 * @return array{recipients: array<string, array{email: string, name: string, event_title: string}>, participant_row_count: int, no_address_count: int}
 */
function rytkoset_theme_get_event_feedback_recipients( $event_id ) {
	$event_id = absint( $event_id );
	$empty    = array(
		'recipients'            => array(),
		'participant_row_count' => 0,
		'no_address_count'      => 0,
	);

	if ( $event_id <= 0 || ! function_exists( 'rytkoset_theme_get_event_participants' ) ) {
		return $empty;
	}

	$rows = rytkoset_theme_get_event_participants( $event_id, '' );

	if ( function_exists( 'rytkoset_theme_filter_active_event_participants' ) ) {
		$rows = rytkoset_theme_filter_active_event_participants( $rows );
	}

	$result = function_exists( 'rytkoset_theme_get_event_messaging_recipients' )
		? rytkoset_theme_get_event_messaging_recipients( $event_id, '' )
		: array(
			'recipients' => array(),
			'skipped'    => 0,
		);

	return array(
		'recipients'            => $result['recipients'],
		'participant_row_count' => count( $rows ),
		'no_address_count'      => (int) ( $result['skipped'] ?? 0 ),
	);
}

/* -----------------------------------------------------------------------
 * Public route: /palaute/{event_id}/
 * --------------------------------------------------------------------- */

/**
 * Returns the public query variable used by the feedback route.
 *
 * @return string
 */
function rytkoset_theme_get_event_feedback_query_var() {
	return 'rytkoset_event_feedback_id';
}

/**
 * Returns the public feedback route URL slug.
 *
 * @return string
 */
function rytkoset_theme_get_event_feedback_slug() {
	return 'palaute';
}

/**
 * Registers the public feedback query variable.
 *
 * @param array<int, string> $vars Public WordPress query variables.
 * @return array<int, string>
 */
function rytkoset_theme_register_event_feedback_query_var( $vars ) {
	$vars[] = rytkoset_theme_get_event_feedback_query_var();

	return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'rytkoset_theme_register_event_feedback_query_var' );

/**
 * Registers the public `/palaute/{event_id}/` rewrite rule.
 */
function rytkoset_theme_register_event_feedback_rewrite_rule() {
	$slug      = preg_quote( rytkoset_theme_get_event_feedback_slug(), '/' );
	$query_var = rytkoset_theme_get_event_feedback_query_var();

	add_rewrite_rule(
		'^' . $slug . '/([0-9]+)/?$',
		'index.php?' . $query_var . '=$matches[1]',
		'top'
	);
}
add_action( 'init', 'rytkoset_theme_register_event_feedback_rewrite_rule', 20 );

/**
 * Checks whether the current request is the public feedback route.
 *
 * @return bool
 */
function rytkoset_theme_is_event_feedback_request() {
	return absint( get_query_var( rytkoset_theme_get_event_feedback_query_var(), 0 ) ) > 0;
}

/**
 * Rewrite-flush guard value, versioned by query var + slug.
 *
 * @return string
 */
function rytkoset_theme_get_event_feedback_rewrite_version() {
	return rytkoset_theme_get_event_feedback_query_var() . ':' . rytkoset_theme_get_event_feedback_slug() . ':v1';
}

/**
 * Checks whether the feedback route already exists in stored rewrite rules.
 *
 * @return bool
 */
function rytkoset_theme_event_feedback_rewrite_rules_exist() {
	$rules = get_option( 'rewrite_rules', array() );

	if ( ! is_array( $rules ) ) {
		return false;
	}

	$slug = rytkoset_theme_get_event_feedback_slug();

	foreach ( array_keys( $rules ) as $regex ) {
		if ( 0 === strpos( ltrim( (string) $regex, '^' ), $slug . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Flushes rewrite rules once when the feedback endpoint is missing (existing installs).
 */
function rytkoset_theme_maybe_flush_event_feedback_rewrite_rules() {
	if (
		rytkoset_theme_get_event_feedback_rewrite_version() === get_option( 'rytkoset_theme_event_feedback_rewrite_flushed' )
		&& rytkoset_theme_event_feedback_rewrite_rules_exist()
	) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'rytkoset_theme_event_feedback_rewrite_flushed', rytkoset_theme_get_event_feedback_rewrite_version() );
}
add_action( 'init', 'rytkoset_theme_maybe_flush_event_feedback_rewrite_rules', 99 );

/**
 * Flushes rewrite rules on theme activation.
 */
function rytkoset_theme_flush_event_feedback_rewrite_rules_on_activation() {
	delete_option( 'rytkoset_theme_event_feedback_rewrite_flushed' );
	flush_rewrite_rules( false );
}
add_action( 'after_switch_theme', 'rytkoset_theme_flush_event_feedback_rewrite_rules_on_activation' );

/**
 * Prevents indexing of the public feedback route (bare, guessable event ID; no
 * content value to index — see docs/event-feedback.md for the security note).
 *
 * @param array<string, bool> $robots Robots directives.
 * @return array<string, bool>
 */
function rytkoset_theme_event_feedback_robots( $robots ) {
	if ( rytkoset_theme_is_event_feedback_request() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'rytkoset_theme_event_feedback_robots' );

/**
 * Prevents Rank Math from indexing the public feedback route.
 *
 * @param array<string, string> $robots Rank Math robots directives.
 * @return array<string, string>
 */
function rytkoset_theme_event_feedback_rank_math_robots( $robots ) {
	if ( rytkoset_theme_is_event_feedback_request() ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'nofollow';
	}

	return $robots;
}
add_filter( 'rank_math/frontend/robots', 'rytkoset_theme_event_feedback_rank_math_robots' );

/**
 * Returns an event's public feedback survey URL.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_public_url( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return '';
	}

	return home_url( '/' . rytkoset_theme_get_event_feedback_slug() . '/' . $event_id . '/' );
}

/* -----------------------------------------------------------------------
 * Public form: rate limiting, rendering, submission handling.
 * --------------------------------------------------------------------- */

/**
 * Maximum feedback submissions allowed per IP within the rolling window.
 *
 * @return int
 */
function rytkoset_theme_get_event_feedback_rate_limit() {
	return (int) apply_filters( 'rytkoset_theme_event_feedback_rate_limit', 5 );
}

/**
 * Rolling window (seconds) for the per-IP feedback submission rate limit.
 *
 * @return int
 */
function rytkoset_theme_get_event_feedback_rate_limit_window() {
	return (int) apply_filters( 'rytkoset_theme_event_feedback_rate_limit_window', 10 * MINUTE_IN_SECONDS );
}

/**
 * Whether the current client IP has exceeded the feedback submission rate limit.
 *
 * Own transient key/filters, deliberately not shared with the free-registration
 * rate limiter in inc/event-registrations.php (different abuse surface). Reuses
 * that file's `rytkoset_theme_get_event_registration_client_ip()` IP resolver.
 *
 * @return bool True when the submission should be blocked.
 */
function rytkoset_theme_event_feedback_is_rate_limited() {
	$ip = rytkoset_theme_get_event_registration_client_ip();

	if ( '' === $ip ) {
		return false;
	}

	$limit  = rytkoset_theme_get_event_feedback_rate_limit();
	$window = rytkoset_theme_get_event_feedback_rate_limit_window();

	if ( $limit <= 0 || $window <= 0 ) {
		return false;
	}

	$key  = 'rytkoset_evt_fb_rl_' . md5( $ip );
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
 * Returns the nonce action used by the public feedback submission form.
 *
 * @return string
 */
function rytkoset_theme_get_event_feedback_submit_nonce_action() {
	return 'rytkoset_submit_event_feedback';
}

/**
 * Renders the public feedback response form.
 *
 * @param int $event_id Event post ID.
 */
function rytkoset_theme_render_event_feedback_form( $event_id ) {
	$event_id = absint( $event_id );
	$max      = rytkoset_theme_get_event_feedback_text_max_length();
	$intro    = rytkoset_theme_get_event_feedback_intro( $event_id );
	$id_base  = 'event-feedback-' . $event_id;
	?>
	<form method="post" action="<?php echo esc_url( rytkoset_theme_get_event_feedback_public_url( $event_id ) ); ?>" class="event-registration__form">
		<input type="hidden" name="rytkoset_event_feedback_submit" value="1" />
		<input type="text" name="feedback_website" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="display:none" />
		<?php wp_nonce_field( rytkoset_theme_get_event_feedback_submit_nonce_action(), 'rytkoset_event_feedback_submit_nonce' ); ?>

		<?php if ( '' !== $intro ) : ?>
			<p class="event-registration__description"><?php echo esc_html( $intro ); ?></p>
		<?php endif; ?>

		<fieldset class="event-registration__field event-feedback__rating">
			<legend>
				<?php esc_html_e( 'Kokonaisarvio tapahtumasta', 'rytkoset-theme' ); ?>
				<span aria-hidden="true">*</span>
			</legend>
			<div class="event-feedback__rating-options" role="radiogroup" aria-required="true">
				<?php for ( $value = 1; $value <= 5; $value++ ) : ?>
					<label class="event-feedback__rating-option">
						<input type="radio" name="feedback_rating" value="<?php echo esc_attr( (string) $value ); ?>" required />
						<span><?php echo esc_html( (string) $value ); ?></span>
					</label>
				<?php endfor; ?>
			</div>
		</fieldset>

		<div class="event-registration__field">
			<label for="<?php echo esc_attr( $id_base . '-well' ); ?>"><?php esc_html_e( 'Mikä onnistui hyvin?', 'rytkoset-theme' ); ?></label>
			<textarea id="<?php echo esc_attr( $id_base . '-well' ); ?>" name="feedback_well" rows="3" maxlength="<?php echo esc_attr( (string) $max ); ?>"></textarea>
		</div>

		<div class="event-registration__field">
			<label for="<?php echo esc_attr( $id_base . '-improve' ); ?>"><?php esc_html_e( 'Mitä voisimme parantaa?', 'rytkoset-theme' ); ?></label>
			<textarea id="<?php echo esc_attr( $id_base . '-improve' ); ?>" name="feedback_improve" rows="3" maxlength="<?php echo esc_attr( (string) $max ); ?>"></textarea>
		</div>

		<div class="event-registration__field">
			<label for="<?php echo esc_attr( $id_base . '-wishes' ); ?>"><?php esc_html_e( 'Toiveita tuleviin tapahtumiin', 'rytkoset-theme' ); ?></label>
			<textarea id="<?php echo esc_attr( $id_base . '-wishes' ); ?>" name="feedback_wishes" rows="3" maxlength="<?php echo esc_attr( (string) $max ); ?>"></textarea>
		</div>

		<p class="event-registration__description">
			<?php esc_html_e( 'Vastauksesi on anonyymi. Älä kirjoita vastauksiisi omia tai muiden terveystietoja tai muita arkaluonteisia henkilötietoja.', 'rytkoset-theme' ); ?>
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

		<p class="event-registration__required-note"><?php esc_html_e( '* Pakollinen kenttä', 'rytkoset-theme' ); ?></p>

		<button type="submit" class="btn btn--primary event-registration__submit">
			<?php esc_html_e( 'Lähetä palaute', 'rytkoset-theme' ); ?>
		</button>
	</form>
	<?php
}

/**
 * Notifies event organizers about a new feedback response, when enabled.
 *
 * Opt-in via `rytkoset_theme_event_feedback_notifies_organizers()`. Recipients
 * come from the event's existing organizer notification field — an empty
 * field means no email is sent, with deliberately no `admin_email` fallback
 * (same rule as the registration/order organizer notifications). The message
 * carries only the anonymous response content (rating + any free-text
 * answers) plus a link to the admin summary; there is no participant identity
 * to leak. Independent of the main submission flow: a failed or skipped
 * notification must never affect the visitor's redirect.
 *
 * @param int    $event_id Event post ID.
 * @param int    $rating   Submitted 1–5 rating.
 * @param string $well     "Mikä onnistui hyvin?" answer.
 * @param string $improve  "Mitä voisimme parantaa?" answer.
 * @param string $wishes   "Toiveita tuleviin tapahtumiin" answer.
 * @return bool Whether WordPress accepted the email for sending.
 */
function rytkoset_theme_send_event_feedback_organizer_notification( $event_id, $rating, $well, $improve, $wishes ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 || ! rytkoset_theme_event_feedback_notifies_organizers( $event_id ) ) {
		return false;
	}

	$recipients = rytkoset_theme_get_event_organizer_notification_recipients( $event_id );

	if ( empty( $recipients ) ) {
		return false;
	}

	$event_title       = get_the_title( $event_id );
	$event_title       = '' !== $event_title ? $event_title : __( 'Tapahtuma', 'rytkoset-theme' );
	$event_title_plain = wp_specialchars_decode( $event_title, ENT_QUOTES );

	$subject = sprintf(
		/* translators: %s: event title. */
		__( 'Uusi palautevastaus: %s', 'rytkoset-theme' ),
		$event_title_plain
	);

	$lines = array(
		__( 'Tapahtumaan on tullut uusi, anonyymi palautevastaus.', 'rytkoset-theme' ),
		'',
		sprintf(
			/* translators: %s: event title. */
			__( 'Tapahtuma: %s', 'rytkoset-theme' ),
			$event_title_plain
		),
		sprintf(
			/* translators: %d: rating 1-5. */
			__( 'Kokonaisarvio: %d / 5', 'rytkoset-theme' ),
			absint( $rating )
		),
	);

	$texts = array(
		__( 'Mikä onnistui hyvin?', 'rytkoset-theme' )    => trim( (string) $well ),
		__( 'Mitä voisimme parantaa?', 'rytkoset-theme' ) => trim( (string) $improve ),
		__( 'Toiveita tuleviin tapahtumiin', 'rytkoset-theme' ) => trim( (string) $wishes ),
	);

	foreach ( $texts as $label => $value ) {
		if ( '' !== $value ) {
			$lines[] = '';
			$lines[] = $label . ':';
			$lines[] = $value;
		}
	}

	$lines[] = '';
	$lines[] = __( 'Kaikki vastaukset tapahtumalle:', 'rytkoset-theme' );
	$lines[] = add_query_arg(
		array(
			'post_type' => 'rytkoset_event',
			'page'      => 'rytkoset-event-feedback',
			'event_id'  => $event_id,
		),
		admin_url( 'edit.php' )
	);

	return wp_mail(
		$recipients,
		$subject,
		implode( "\n", $lines ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Handles a public feedback form submission and redirects (PRG).
 *
 * Deliberately anonymous: no name, email, user, registration, order or IP
 * meta is ever written for a feedback response.
 *
 * @param int $event_id Event post ID.
 */
function rytkoset_theme_handle_event_feedback_submission( $event_id ) {
	$website = isset( $_POST['feedback_website'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Honeypot is intentionally checked before other validation.
		? trim( sanitize_text_field( wp_unslash( $_POST['feedback_website'] ) ) )
		: '';

	if ( '' !== $website ) {
		wp_safe_redirect( rytkoset_theme_get_event_feedback_public_url( $event_id ) );
		exit;
	}

	if (
		! isset( $_POST['rytkoset_event_feedback_submit_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rytkoset_event_feedback_submit_nonce'] ) ),
			rytkoset_theme_get_event_feedback_submit_nonce_action()
		)
	) {
		wp_safe_redirect( add_query_arg( array( 'palaute_virhe' => 'nonce' ), rytkoset_theme_get_event_feedback_public_url( $event_id ) ) );
		exit;
	}

	if ( ! rytkoset_theme_event_feedback_survey_is_open( $event_id ) ) {
		wp_safe_redirect( add_query_arg( array( 'palaute_virhe' => 'suljettu' ), rytkoset_theme_get_event_feedback_public_url( $event_id ) ) );
		exit;
	}

	// Checked before save: a bot bypassing the honeypot could otherwise submit
	// the public form repeatedly and create unbounded database writes.
	if ( rytkoset_theme_event_feedback_is_rate_limited() ) {
		wp_safe_redirect( add_query_arg( array( 'palaute_virhe' => 'raja' ), rytkoset_theme_get_event_feedback_public_url( $event_id ) ) );
		exit;
	}

	$rating = isset( $_POST['feedback_rating'] ) ? absint( wp_unslash( $_POST['feedback_rating'] ) ) : 0;

	if ( ! rytkoset_theme_is_valid_event_feedback_rating( $rating ) ) {
		wp_safe_redirect( add_query_arg( array( 'palaute_virhe' => 'arvio' ), rytkoset_theme_get_event_feedback_public_url( $event_id ) ) );
		exit;
	}

	$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();
	$well      = isset( $_POST['feedback_well'] ) ? rytkoset_theme_sanitize_event_feedback_text( wp_unslash( $_POST['feedback_well'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_sanitize_event_feedback_text().
	$improve   = isset( $_POST['feedback_improve'] ) ? rytkoset_theme_sanitize_event_feedback_text( wp_unslash( $_POST['feedback_improve'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_sanitize_event_feedback_text().
	$wishes    = isset( $_POST['feedback_wishes'] ) ? rytkoset_theme_sanitize_event_feedback_text( wp_unslash( $_POST['feedback_wishes'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_sanitize_event_feedback_text().

	$meta_input = array(
		$meta_keys['event_id'] => $event_id,
		$meta_keys['rating']   => $rating,
	);

	if ( '' !== $well ) {
		$meta_input[ $meta_keys['well'] ] = $well;
	}

	if ( '' !== $improve ) {
		$meta_input[ $meta_keys['improve'] ] = $improve;
	}

	if ( '' !== $wishes ) {
		$meta_input[ $meta_keys['wishes'] ] = $wishes;
	}

	$response_id = wp_insert_post(
		array(
			'post_type'   => 'event_feedback',
			'post_status' => 'publish',
			'post_title'  => '',
			'post_author' => 0,
			'meta_input'  => $meta_input,
		),
		true
	);

	if ( is_wp_error( $response_id ) || $response_id <= 0 ) {
		wp_safe_redirect( add_query_arg( array( 'palaute_virhe' => 'tallennus' ), rytkoset_theme_get_event_feedback_public_url( $event_id ) ) );
		exit;
	}

	// Independent of the redirect below: a failed or skipped notification must
	// never block the visitor from seeing the thank-you page.
	rytkoset_theme_send_event_feedback_organizer_notification( $event_id, $rating, $well, $improve, $wishes );

	wp_safe_redirect( add_query_arg( array( 'palaute' => 'kiitos' ), rytkoset_theme_get_event_feedback_public_url( $event_id ) ) );
	exit;
}

/**
 * Renders the public feedback route (form, closed notice, or thank-you) and
 * dispatches a POST submission before rendering.
 */
function rytkoset_theme_render_event_feedback_page() {
	if ( ! rytkoset_theme_is_event_feedback_request() ) {
		return;
	}

	$event_id = absint( get_query_var( rytkoset_theme_get_event_feedback_query_var(), 0 ) );

	if ( $event_id <= 0 || 'rytkoset_event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
		status_header( 404 );
		nocache_headers();
		get_header();
		?>
		<main id="primary" class="site-main" tabindex="-1">
			<section class="section">
				<div class="container section__narrow">
					<p><?php esc_html_e( 'Palautelomaketta ei löytynyt.', 'rytkoset-theme' ); ?></p>
				</div>
			</section>
		</main>
		<?php
		get_footer();
		exit;
	}

	if ( isset( $_POST['rytkoset_event_feedback_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified inside rytkoset_theme_handle_event_feedback_submission().
		rytkoset_theme_handle_event_feedback_submission( $event_id );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only receipt/error state set by the nonce-protected submission above.
	$received   = isset( $_GET['palaute'] ) && 'kiitos' === sanitize_key( wp_unslash( $_GET['palaute'] ) );
	$error_code = isset( $_GET['palaute_virhe'] ) ? sanitize_key( wp_unslash( $_GET['palaute_virhe'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$is_open = rytkoset_theme_event_feedback_survey_is_open( $event_id );

	status_header( 200 );
	nocache_headers();
	get_header();
	?>
	<main id="primary" class="site-main" tabindex="-1">
		<section class="section">
			<div class="container section__narrow">
				<article class="article event-feedback">
					<h1 class="article__title">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: event title. */
								__( 'Palaute: %s', 'rytkoset-theme' ),
								get_the_title( $event_id )
							)
						);
						?>
					</h1>
					<div class="article__content">
						<?php if ( $received ) : ?>
							<p class="event-registration__notice event-registration__notice--success" role="status">
								<?php esc_html_e( 'Kiitos palautteestasi!', 'rytkoset-theme' ); ?>
							</p>
						<?php elseif ( ! $is_open ) : ?>
							<p class="event-registration__notice" role="status">
								<?php esc_html_e( 'Palautekysely ei ole tällä hetkellä avoinna tälle tapahtumalle.', 'rytkoset-theme' ); ?>
							</p>
						<?php else : ?>
							<?php if ( '' !== $error_code ) : ?>
								<?php
								$error_messages = array(
									'nonce'     => __( 'Lomakkeen istunto on vanhentunut. Yritä uudelleen.', 'rytkoset-theme' ),
									'suljettu'  => __( 'Palautekysely ei ole avoinna.', 'rytkoset-theme' ),
									'raja'      => __( 'Liian monta lähetystä lyhyessä ajassa. Yritä hetken kuluttua uudelleen.', 'rytkoset-theme' ),
									'arvio'     => __( 'Valitse kokonaisarvio 1–5.', 'rytkoset-theme' ),
									'tallennus' => __( 'Palautetta ei voitu tallentaa. Yritä uudelleen.', 'rytkoset-theme' ),
								);
								?>
								<div class="event-registration__notice event-registration__notice--error" role="alert">
									<?php
									echo esc_html(
										isset( $error_messages[ $error_code ] )
											? $error_messages[ $error_code ]
											: __( 'Palautetta ei voitu tallentaa. Yritä uudelleen.', 'rytkoset-theme' )
									);
									?>
								</div>
							<?php endif; ?>
							<?php rytkoset_theme_render_event_feedback_form( $event_id ); ?>
						<?php endif; ?>
					</div>
				</article>
			</div>
		</section>
	</main>
	<?php
	get_footer();
	exit;
}
add_action( 'template_redirect', 'rytkoset_theme_render_event_feedback_page', 20 );

/* -----------------------------------------------------------------------
 * Sending: default subject/body template + manual "queue now" action.
 * --------------------------------------------------------------------- */

/**
 * Returns the default feedback request email subject for an event.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_request_subject( $event_id ) {
	return sprintf(
		/* translators: %s: event title. */
		__( 'Palautetta tapahtumasta: %s', 'rytkoset-theme' ),
		get_the_title( $event_id )
	);
}

/**
 * Returns the default feedback request email body for an event, with the
 * `{palautelinkki}` placeholder for `rytkoset_theme_personalize_event_message()`.
 *
 * @param int $event_id Event post ID.
 * @return string
 */
function rytkoset_theme_get_event_feedback_request_body( $event_id ) {
	$intro = rytkoset_theme_get_event_feedback_intro( $event_id );
	$lines = array();

	if ( '' !== $intro ) {
		$lines[] = $intro;
		$lines[] = '';
	}

	$lines[] = __( 'Toivomme sinulta hetken palautetta tapahtumasta. Vastaaminen on anonyymia ja kestää vain hetken.', 'rytkoset-theme' );
	$lines[] = '';
	$lines[] = '{palautelinkki}';

	return implode( "\n", $lines );
}

/**
 * Renders the "Palautekysely" queue section on the event messaging admin page.
 *
 * Shown only for a single selected event ("Kaikki tapahtumat" excluded — a
 * feedback link cannot represent several events) with feedback enabled and the
 * event already over.
 *
 * @param int $event_id Selected event post ID (0 = none/"Kaikki tapahtumat").
 */
function rytkoset_theme_render_event_feedback_queue_section( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 || 'disabled' === rytkoset_theme_get_event_feedback_mode( $event_id ) ) {
		return;
	}

	if ( ! rytkoset_theme_is_event_date_passed( $event_id ) ) {
		return;
	}

	$result     = rytkoset_theme_get_event_feedback_recipients( $event_id );
	$recipients = $result['recipients'];
	$queued_at  = rytkoset_theme_get_event_feedback_queued_at( $event_id );
	?>
	<h2><?php esc_html_e( 'Palautekysely', 'rytkoset-theme' ); ?></h2>
	<p class="description">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: eligible participant rows, 2: unique recipient addresses, 3: rows without a usable address. */
				__( 'Osallistujarivejä %1$d, yksilöllisiä sähköpostivastaanottajia %2$d, ilman osoitetta jää %3$d. Peruutetut ilmoittautumiset sekä peruutetut, hyvitetyt ja epäonnistuneet tilaukset on rajattu pois.', 'rytkoset-theme' ),
				$result['participant_row_count'],
				count( $recipients ),
				$result['no_address_count']
			)
		);
		?>
	</p>

	<?php if ( '' !== $queued_at ) : ?>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: queued timestamp. */
					__( 'Palautepyyntö on jo lisätty lähetysjonoon: %s', 'rytkoset-theme' ),
					$queued_at
				)
			);
			?>
		</p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rytkoset_send_event_feedback_request" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>" />
			<?php wp_nonce_field( 'rytkoset_send_event_feedback_request', 'rytkoset_event_feedback_request_nonce' ); ?>
			<?php
			$button_label = empty( $recipients )
				? __( 'Ei vastaanottajia', 'rytkoset-theme' )
				: sprintf(
					/* translators: %d: recipient count. */
					_n(
						'Lisää palautepyyntö jonoon %d vastaanottajalle',
						'Lisää palautepyyntö jonoon %d vastaanottajalle',
						count( $recipients ),
						'rytkoset-theme'
					),
					count( $recipients )
				);
			?>
			<button type="submit" class="button button-primary" <?php disabled( empty( $recipients ) ); ?>>
				<?php echo esc_html( $button_label ); ?>
			</button>
		</form>
	<?php endif; ?>
	<?php
}

/**
 * Handles the manual "Lisää palautepyyntö jonoon" action.
 */
function rytkoset_theme_send_event_feedback_request() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta lähettää palautepyyntöä.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_send_event_feedback_request', 'rytkoset_event_feedback_request_nonce' );

	$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

	$redirect_base = add_query_arg(
		array(
			'post_type' => 'rytkoset_event',
			'page'      => 'rytkoset-event-messaging',
			'event_id'  => $event_id,
		),
		admin_url( 'edit.php' )
	);

	if (
		$event_id <= 0
		|| 'rytkoset_event' !== get_post_type( $event_id )
		|| 'disabled' === rytkoset_theme_get_event_feedback_mode( $event_id )
		|| rytkoset_theme_event_feedback_is_queued( $event_id )
		|| ! rytkoset_theme_is_event_date_passed( $event_id )
	) {
		wp_safe_redirect( add_query_arg( array( 'messaging_notice' => 'error_no_recipients' ), $redirect_base ) );
		exit;
	}

	$result     = rytkoset_theme_get_event_feedback_recipients( $event_id );
	$recipients = $result['recipients'];

	if ( empty( $recipients ) ) {
		wp_safe_redirect( add_query_arg( array( 'messaging_notice' => 'error_no_recipients' ), $redirect_base ) );
		exit;
	}

	$current_user = wp_get_current_user();
	$reply_to     = $current_user && ! empty( $current_user->user_email ) ? $current_user->user_email : '';

	$job_id = rytkoset_theme_enqueue_event_messaging_job(
		array(
			'sender_id'     => (int) get_current_user_id(),
			'sender_name'   => $current_user ? (string) $current_user->display_name : '',
			'reply_to'      => $reply_to,
			'event_id'      => $event_id,
			'event_title'   => get_the_title( $event_id ),
			'status_filter' => '',
			'status_label'  => __( 'Palautekysely', 'rytkoset-theme' ),
			'subject'       => rytkoset_theme_get_event_feedback_request_subject( $event_id ),
			'body'          => rytkoset_theme_get_event_feedback_request_body( $event_id ),
			'recipients'    => $recipients,
			'skipped_count' => $result['no_address_count'],
		)
	);

	if ( '' === $job_id ) {
		wp_safe_redirect( add_query_arg( array( 'messaging_notice' => 'error_no_recipients' ), $redirect_base ) );
		exit;
	}

	rytkoset_theme_mark_event_feedback_queued( $event_id );

	wp_safe_redirect(
		add_query_arg(
			array(
				'messaging_notice' => 'queued',
				'queued'           => count( $recipients ),
				'skipped'          => $result['no_address_count'],
			),
			$redirect_base
		)
	);
	exit;
}
add_action( 'admin_post_rytkoset_send_event_feedback_request', 'rytkoset_theme_send_event_feedback_request' );

/* -----------------------------------------------------------------------
 * Automatic queueing: idempotent WP-Cron sweep.
 * --------------------------------------------------------------------- */

/**
 * Checks whether an automatic-mode event is due to have its feedback request queued.
 *
 * Pure decision: mode is 'automatic', not yet queued, a valid send time is
 * configured, and the reference time has reached it.
 *
 * @param int      $event_id            Event post ID.
 * @param int|null $reference_timestamp Reference Unix timestamp; defaults to now.
 * @return bool
 */
function rytkoset_theme_event_feedback_auto_queue_is_due( $event_id, $reference_timestamp = null ) {
	if ( 'automatic' !== rytkoset_theme_get_event_feedback_mode( $event_id ) ) {
		return false;
	}

	if ( rytkoset_theme_event_feedback_is_queued( $event_id ) ) {
		return false;
	}

	$send_at_timestamp = rytkoset_theme_get_event_feedback_send_at_timestamp( $event_id );

	if ( $send_at_timestamp <= 0 ) {
		return false;
	}

	$reference_timestamp = null === $reference_timestamp ? time() : (int) $reference_timestamp;

	return $reference_timestamp >= $send_at_timestamp;
}

/**
 * Returns automatic-mode event IDs whose feedback request is due to be queued.
 *
 * `queued_at NOT EXISTS` is the idempotency guard, mirroring
 * `rytkoset_theme_get_pending_event_registration_ids()` in
 * inc/event-registration-anonymization.php.
 *
 * @param int|null $reference_timestamp Reference Unix timestamp; defaults to now.
 * @return int[]
 */
function rytkoset_theme_get_due_event_feedback_auto_queue_ids( $reference_timestamp = null ) {
	$meta_keys = rytkoset_theme_get_event_feedback_meta_keys();

	$ids = get_posts(
		array(
			'post_type'              => 'rytkoset_event',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => $meta_keys['mode'],
					'value' => 'automatic',
				),
				array(
					'key'     => $meta_keys['queued_at'],
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	$due = array();

	foreach ( (array) $ids as $event_id ) {
		if ( rytkoset_theme_event_feedback_auto_queue_is_due( (int) $event_id, $reference_timestamp ) ) {
			$due[] = (int) $event_id;
		}
	}

	return $due;
}

/**
 * Returns the cron hook name used for the automatic feedback-request sweep.
 *
 * @return string
 */
function rytkoset_theme_get_event_feedback_auto_queue_cron_hook() {
	return 'rytkoset_process_event_feedback_auto_queue';
}

/**
 * Ensures the recurring auto-queue sweep is scheduled.
 *
 * Reuses the `rytkoset_five_minutes` schedule already registered by
 * inc/event-participants-messaging.php (a global WP filter, so load order
 * between the two files does not matter — both `add_filter()` calls run at
 * file-parse time, long before any `init` callback calls `wp_schedule_event()`).
 */
function rytkoset_theme_ensure_event_feedback_auto_queue_cron_scheduled() {
	$hook = rytkoset_theme_get_event_feedback_auto_queue_cron_hook();

	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'rytkoset_five_minutes', $hook );
	}
}
add_action( 'init', 'rytkoset_theme_ensure_event_feedback_auto_queue_cron_scheduled' );

/**
 * Clears the auto-queue cron hook when the theme is switched.
 */
function rytkoset_theme_clear_event_feedback_auto_queue_cron() {
	wp_clear_scheduled_hook( rytkoset_theme_get_event_feedback_auto_queue_cron_hook() );
}
add_action( 'switch_theme', 'rytkoset_theme_clear_event_feedback_auto_queue_cron' );

/**
 * Processes the automatic feedback-request sweep.
 *
 * A transient lock (mirroring `rytkoset_theme_process_event_messaging_queue()`
 * in inc/event-participants-messaging.php) serializes the sweep so two
 * near-simultaneous WP-Cron requests cannot both queue the same event's
 * feedback request — the `queued_at NOT EXISTS` query alone leaves a race
 * window between two processes reading "not yet queued" before either writes.
 */
function rytkoset_theme_process_event_feedback_auto_queue() {
	$lock_key = 'rytkoset_event_feedback_auto_queue_lock';

	if ( get_transient( $lock_key ) ) {
		return;
	}

	set_transient( $lock_key, 1, 10 * MINUTE_IN_SECONDS );

	try {
		$due_event_ids = rytkoset_theme_get_due_event_feedback_auto_queue_ids();

		foreach ( $due_event_ids as $event_id ) {
			// Re-check inside the lock in case another process queued this event
			// between the query above and this loop iteration.
			if ( rytkoset_theme_event_feedback_is_queued( $event_id ) ) {
				continue;
			}

			$result     = rytkoset_theme_get_event_feedback_recipients( $event_id );
			$recipients = $result['recipients'];

			if ( empty( $recipients ) ) {
				// No eligible recipient yet (e.g. every registration was later
				// cancelled). Mark queued anyway so a past event with no further
				// registrations to come is not re-evaluated on every sweep.
				rytkoset_theme_mark_event_feedback_queued( $event_id );
				continue;
			}

			$job_id = rytkoset_theme_enqueue_event_messaging_job(
				array(
					'sender_id'     => 0,
					'sender_name'   => get_bloginfo( 'name' ),
					'reply_to'      => '',
					'event_id'      => $event_id,
					'event_title'   => get_the_title( $event_id ),
					'status_filter' => '',
					'status_label'  => __( 'Palautekysely (automaattinen)', 'rytkoset-theme' ),
					'subject'       => rytkoset_theme_get_event_feedback_request_subject( $event_id ),
					'body'          => rytkoset_theme_get_event_feedback_request_body( $event_id ),
					'recipients'    => $recipients,
					'skipped_count' => $result['no_address_count'],
				)
			);

			if ( '' !== $job_id ) {
				rytkoset_theme_mark_event_feedback_queued( $event_id );
			}
		}
	} finally {
		delete_transient( $lock_key );
	}
}
add_action( 'rytkoset_process_event_feedback_auto_queue', 'rytkoset_theme_process_event_feedback_auto_queue' );

/* -----------------------------------------------------------------------
 * Admin aggregate view: Tapahtumat > Palaute.
 * --------------------------------------------------------------------- */

/**
 * Returns published feedback responses for an event, newest first.
 *
 * @param int $event_id Event post ID.
 * @return WP_Post[]
 */
function rytkoset_theme_get_event_feedback_responses( $event_id ) {
	$event_id = absint( $event_id );

	if ( $event_id <= 0 ) {
		return array();
	}

	$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();

	return get_posts(
		array(
			'post_type'              => 'event_feedback',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'             => array(
				array(
					'key'   => $meta_keys['event_id'],
					'value' => $event_id,
				),
			),
		)
	);
}

/**
 * Returns the average rating across a set of feedback responses, rounded to
 * one decimal, or null when there are no responses (avoids a divide-by-zero).
 *
 * @param WP_Post[] $responses Feedback response posts.
 * @return float|null
 */
function rytkoset_theme_get_event_feedback_average_rating( $responses ) {
	$count = count( $responses );

	if ( 0 === $count ) {
		return null;
	}

	$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();
	$sum       = 0;

	foreach ( $responses as $response ) {
		$sum += absint( get_post_meta( $response->ID, $meta_keys['rating'], true ) );
	}

	return round( $sum / $count, 1 );
}

/**
 * Registers the "Palaute" admin submenu under the Events CPT.
 */
function rytkoset_theme_register_event_feedback_admin_page() {
	add_submenu_page(
		'edit.php?post_type=rytkoset_event',
		__( 'Palaute', 'rytkoset-theme' ),
		__( 'Palaute', 'rytkoset-theme' ),
		'edit_others_event_registrations',
		'rytkoset-event-feedback',
		'rytkoset_theme_render_event_feedback_admin_page'
	);
}
add_action( 'admin_menu', 'rytkoset_theme_register_event_feedback_admin_page' );

/**
 * Renders the "Palaute" admin aggregate page.
 */
function rytkoset_theme_render_event_feedback_admin_page() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta tarkastella tätä sivua.', 'rytkoset-theme' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin list filters.
	$selected_event = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
	$edit_id        = isset( $_GET['rytkoset_edit_feedback'] ) ? absint( wp_unslash( $_GET['rytkoset_edit_feedback'] ) ) : 0;
	$updated        = isset( $_GET['updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['updated'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $selected_event > 0 && 'rytkoset_event' !== get_post_type( $selected_event ) ) {
		$selected_event = 0;
	}

	$events         = rytkoset_theme_get_event_participants_admin_events();
	$responses      = $selected_event > 0 ? rytkoset_theme_get_event_feedback_responses( $selected_event ) : array();
	$average_rating = rytkoset_theme_get_event_feedback_average_rating( $responses );
	$meta_keys      = rytkoset_theme_get_event_feedback_response_meta_keys();
	$page_base_url  = add_query_arg(
		array(
			'post_type' => 'rytkoset_event',
			'page'      => 'rytkoset-event-feedback',
			'event_id'  => $selected_event,
		),
		admin_url( 'edit.php' )
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tapahtumien palaute', 'rytkoset-theme' ); ?></h1>

		<?php if ( $updated ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Vastaus päivitetty.', 'rytkoset-theme' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="get">
			<input type="hidden" name="post_type" value="rytkoset_event" />
			<input type="hidden" name="page" value="rytkoset-event-feedback" />
			<label for="rytkoset-event-feedback-event"><?php esc_html_e( 'Tapahtuma:', 'rytkoset-theme' ); ?></label>
			<select name="event_id" id="rytkoset-event-feedback-event">
				<option value="0"><?php esc_html_e( 'Valitse tapahtuma', 'rytkoset-theme' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( (string) $event->ID ); ?>" <?php selected( $selected_event, $event->ID ); ?>>
						<?php echo esc_html( get_the_title( $event ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Näytä', 'rytkoset-theme' ), 'secondary', '', false ); ?>
		</form>

		<?php if ( $selected_event > 0 ) : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: response count. */
						_n( 'Vastauksia %d', 'Vastauksia %d', count( $responses ), 'rytkoset-theme' ),
						count( $responses )
					)
				);
				?>
				&mdash;
				<?php
				echo esc_html(
					null === $average_rating
						? __( 'Keskiarvo: –', 'rytkoset-theme' )
						: sprintf(
							/* translators: %s: average rating with one decimal. */
							__( 'Keskiarvo: %s / 5', 'rytkoset-theme' ),
							number_format_i18n( $average_rating, 1 )
						)
				);
				?>
			</p>

			<?php if ( empty( $responses ) ) : ?>
				<p><?php esc_html_e( 'Ei vastauksia vielä.', 'rytkoset-theme' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Arvio', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Mikä onnistui hyvin?', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Mitä voisimme parantaa?', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Toiveita tuleviin tapahtumiin', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Toiminnot', 'rytkoset-theme' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $responses as $response ) : ?>
							<?php
							$rating  = absint( get_post_meta( $response->ID, $meta_keys['rating'], true ) );
							$well    = (string) get_post_meta( $response->ID, $meta_keys['well'], true );
							$improve = (string) get_post_meta( $response->ID, $meta_keys['improve'], true );
							$wishes  = (string) get_post_meta( $response->ID, $meta_keys['wishes'], true );
							?>
							<?php if ( $edit_id === $response->ID ) : ?>
								<tr>
									<td colspan="5">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="rytkoset_edit_event_feedback_response" />
											<input type="hidden" name="response_id" value="<?php echo esc_attr( (string) $response->ID ); ?>" />
											<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $selected_event ); ?>" />
											<?php wp_nonce_field( 'rytkoset_edit_event_feedback_response', 'rytkoset_event_feedback_edit_nonce' ); ?>
											<p>
												<label>
													<?php esc_html_e( 'Mikä onnistui hyvin?', 'rytkoset-theme' ); ?><br />
													<textarea name="feedback_well" rows="2" class="large-text"><?php echo esc_textarea( $well ); ?></textarea>
												</label>
											</p>
											<p>
												<label>
													<?php esc_html_e( 'Mitä voisimme parantaa?', 'rytkoset-theme' ); ?><br />
													<textarea name="feedback_improve" rows="2" class="large-text"><?php echo esc_textarea( $improve ); ?></textarea>
												</label>
											</p>
											<p>
												<label>
													<?php esc_html_e( 'Toiveita tuleviin tapahtumiin', 'rytkoset-theme' ); ?><br />
													<textarea name="feedback_wishes" rows="2" class="large-text"><?php echo esc_textarea( $wishes ); ?></textarea>
												</label>
											</p>
											<?php submit_button( __( 'Tallenna', 'rytkoset-theme' ), 'primary', '', false ); ?>
										</form>
									</td>
								</tr>
							<?php else : ?>
								<tr>
									<td><?php echo esc_html( $rating > 0 ? (string) $rating : '–' ); ?></td>
									<td style="white-space:pre-wrap;"><?php echo esc_html( $well ); ?></td>
									<td style="white-space:pre-wrap;"><?php echo esc_html( $improve ); ?></td>
									<td style="white-space:pre-wrap;"><?php echo esc_html( $wishes ); ?></td>
									<td>
										<a href="<?php echo esc_url( add_query_arg( array( 'rytkoset_edit_feedback' => $response->ID ), $page_base_url ) ); ?>">
											<?php esc_html_e( 'Muokkaa', 'rytkoset-theme' ); ?>
										</a>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Updates a feedback response's free-text fields only. The rating is never
 * editable through this path, keeping the numeric aggregate trustworthy.
 *
 * Used by the admin's manual redaction action (#666: the association decided
 * against automatic deletion of anonymous feedback text — see
 * docs/tietosuoja.md — but staff can still remove accidentally-entered
 * personal data from a free-text answer).
 *
 * @param int    $response_id Feedback response post ID.
 * @param string $well        "Mikä onnistui hyvin?" answer.
 * @param string $improve     "Mitä voisimme parantaa?" answer.
 * @param string $wishes      "Toiveita tuleviin tapahtumiin" answer.
 * @return bool
 */
function rytkoset_theme_update_event_feedback_response_text( $response_id, $well, $improve, $wishes ) {
	$response_id = absint( $response_id );

	if ( $response_id <= 0 || 'event_feedback' !== get_post_type( $response_id ) ) {
		return false;
	}

	$meta_keys = rytkoset_theme_get_event_feedback_response_meta_keys();
	$values    = array(
		$meta_keys['well']    => rytkoset_theme_sanitize_event_feedback_text( $well ),
		$meta_keys['improve'] => rytkoset_theme_sanitize_event_feedback_text( $improve ),
		$meta_keys['wishes']  => rytkoset_theme_sanitize_event_feedback_text( $wishes ),
	);

	foreach ( $values as $meta_key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $response_id, $meta_key );
		} else {
			update_post_meta( $response_id, $meta_key, $value );
		}
	}

	return true;
}

/**
 * Handles the admin's feedback response text edit/redaction form.
 */
function rytkoset_theme_handle_event_feedback_response_edit() {
	if ( ! current_user_can( 'edit_others_event_registrations' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta muokata palautetta.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_edit_event_feedback_response', 'rytkoset_event_feedback_edit_nonce' );

	$response_id = isset( $_POST['response_id'] ) ? absint( wp_unslash( $_POST['response_id'] ) ) : 0;
	$event_id    = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
	$well        = isset( $_POST['feedback_well'] ) ? wp_unslash( $_POST['feedback_well'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_update_event_feedback_response_text().
	$improve     = isset( $_POST['feedback_improve'] ) ? wp_unslash( $_POST['feedback_improve'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_update_event_feedback_response_text().
	$wishes      = isset( $_POST['feedback_wishes'] ) ? wp_unslash( $_POST['feedback_wishes'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in rytkoset_theme_update_event_feedback_response_text().

	rytkoset_theme_update_event_feedback_response_text( $response_id, $well, $improve, $wishes );

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type' => 'rytkoset_event',
				'page'      => 'rytkoset-event-feedback',
				'event_id'  => $event_id,
				'updated'   => '1',
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_rytkoset_edit_event_feedback_response', 'rytkoset_theme_handle_event_feedback_response_edit' );
