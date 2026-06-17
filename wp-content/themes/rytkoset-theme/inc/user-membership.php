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
 * Returns true when the user has an active membership.
 *
 * Lifetime memberships are always active. Time-bound memberships (annual/family) are active
 * only when an expiry date is stored and has not passed in the site timezone. Any uncertain
 * state — no user, no membership type, or a time-bound membership without a valid future
 * expiry — is treated as "not a member" (fail closed), per docs/digilehdet.md.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_is_active_member( $user_id = null ) {
	$membership = rytkoset_theme_get_user_membership( $user_id );

	if ( '' === $membership['type'] ) {
		return false;
	}

	if ( 'lifetime' === $membership['type'] ) {
		return true;
	}

	if ( ! rytkoset_theme_user_membership_type_is_time_bound( $membership['type'] ) ) {
		return false;
	}

	$expires = $membership['expires'];

	if ( '' === $expires ) {
		return false;
	}

	$today = current_datetime()->format( 'Y-m-d' );

	// Y-m-d strings compare correctly lexically; membership is active through the expiry date.
	return $expires >= $today;
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
 * Returns the nonce action used for the user membership profile fields.
 *
 * @param int $user_id The edited user ID.
 * @return string
 */
function rytkoset_theme_get_user_membership_nonce_action( $user_id ) {
	return 'rytkoset_save_user_membership_' . (int) $user_id;
}

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
		return;
	}

	update_user_meta( $user_id, $type_field, $type );

	if ( 'lifetime' === $type ) {
		delete_user_meta( $user_id, $period_field );
		delete_user_meta( $user_id, $expires_field );
		return;
	}

	$period  = isset( $_POST[ $period_field ] )
		? rytkoset_theme_sanitize_user_membership_period( wp_unslash( $_POST[ $period_field ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizer validates and returns a normalized period string.
		: '';
	$expires = isset( $_POST[ $expires_field ] )
		? rytkoset_theme_sanitize_user_membership_expires( wp_unslash( $_POST[ $expires_field ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizer validates and returns a normalized date string.
		: '';

	update_user_meta( $user_id, $period_field, $period );
	update_user_meta( $user_id, $expires_field, $expires );
}
add_action( 'personal_options_update', 'rytkoset_theme_save_user_membership_fields' );
add_action( 'edit_user_profile_update', 'rytkoset_theme_save_user_membership_fields' );
