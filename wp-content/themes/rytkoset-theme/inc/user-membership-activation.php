<?php
/**
 * Manual membership activation tool for existing (paper-register) members (#525).
 *
 * Adds a Users > Jäsenten aktivointi admin screen where an administrator enters
 * one or more member email addresses and selects the membership product whose
 * details are applied as a snapshot. Addresses with an existing WordPress account
 * get their membership updated immediately (never shortening an existing longer/lifetime membership,
 * same rule as the WooCommerce order path in inc/woocommerce-membership.php) and
 * receive the shared #390 confirmation email on a non-active → active
 * transition. Addresses without an account are stored as pending manual
 * memberships in a wp_options map and receive a #518-style "create an account
 * with this same email" invite; a later registration with the same email
 * applies the pending membership automatically via user_register.
 *
 * GDPR scope: only the email address and the chosen membership details are
 * processed; the processing log stores the address, the outcome, the acting
 * administrator and a timestamp, never message contents. Re-sending the invite
 * requires an explicit admin opt-in so a page refresh or repeated submission
 * cannot spam a recipient. The tool never subscribes anyone to the newsletter.
 * Requires WooCommerce membership products configured by inc/woocommerce-membership.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_pending_manual_memberships_option_name' ) ) {
	/**
	 * Returns the option name storing pending manual memberships.
	 *
	 * The value is a map keyed by normalized email address:
	 * email => { type, period, expires, created_at, invite_sent_at, added_by }.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_pending_manual_memberships_option_name() {
		return 'rytkoset_pending_manual_memberships';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_log_option_name' ) ) {
	/**
	 * Returns the option name storing the membership activation processing log.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_membership_activation_log_option_name() {
		return 'rytkoset_membership_activation_log';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_log_max_entries' ) ) {
	/**
	 * Returns the maximum number of retained log entries (newest first).
	 *
	 * @return int
	 */
	function rytkoset_theme_get_membership_activation_log_max_entries() {
		return (int) apply_filters( 'rytkoset_theme_membership_activation_log_max_entries', 200 );
	}
}

if ( ! function_exists( 'rytkoset_theme_membership_activation_parse_emails' ) ) {
	/**
	 * Parses, normalizes and deduplicates a raw multi-line email list.
	 *
	 * @param string $raw Raw textarea input, one address per line (commas/semicolons also split).
	 * @return array{emails: string[], invalid: string[]} Normalized unique addresses and rejected input lines.
	 */
	function rytkoset_theme_membership_activation_parse_emails( $raw ) {
		$emails  = array();
		$invalid = array();

		foreach ( preg_split( '/[\r\n,;]+/', (string) $raw ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$email = rytkoset_theme_normalize_family_member_email( $line );

			if ( '' === $email ) {
				$invalid[] = $line;
				continue;
			}

			if ( ! in_array( $email, $emails, true ) ) {
				$emails[] = $email;
			}
		}

		return array(
			'emails'  => $emails,
			'invalid' => $invalid,
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_products' ) ) {
	/**
	 * Returns membership products offered by the manual activation tool.
	 *
	 * Catalog-hidden products remain available because an administrator may need
	 * to activate an older membership period. Draft and trashed products are not
	 * activation sources.
	 *
	 * @return array<int, WC_Product> Products keyed by product ID.
	 */
	function rytkoset_theme_get_membership_activation_products() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'  => array( 'publish', 'private' ),
				'limit'   => -1,
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
		$options  = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product || ! rytkoset_theme_is_membership_product( $product ) ) {
				continue;
			}

			$options[ $product->get_id() ] = $product;
		}

		return $options;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_product_label' ) ) {
	/**
	 * Builds an informative option label for one membership product.
	 *
	 * @param WC_Product $product Membership product.
	 * @return string
	 */
	function rytkoset_theme_get_membership_activation_product_label( $product ) {
		$name    = $product instanceof WC_Product ? trim( (string) $product->get_name() ) : '';
		$type    = rytkoset_theme_get_membership_product_type( $product );
		$period  = rytkoset_theme_get_membership_product_period( $product );
		$expires = rytkoset_theme_get_membership_product_expiry_date( $product );
		$details = array();

		if ( '' !== $type ) {
			$details[] = rytkoset_theme_get_membership_type_label( $type );
		}

		if ( in_array( $type, array( 'annual_individual', 'annual_family' ), true ) ) {
			$details[] = '' !== $period ? $period : __( 'jäsenkausi puuttuu', 'rytkoset-theme' );

			$details[] = '' !== $expires
				? sprintf(
					/* translators: %s: membership expiry date. */
					__( 'voimassa %s asti', 'rytkoset-theme' ),
					rytkoset_theme_get_user_membership_expires_display( $expires )
				)
				: __( 'voimassaolopäivä puuttuu', 'rytkoset-theme' );
		}

		if ( empty( $details ) ) {
			$details[] = __( 'puutteelliset jäsenyysasetukset', 'rytkoset-theme' );
		}

		return sprintf( '%1$s — %2$s', $name, implode( ', ', $details ) );
	}
}

if ( ! function_exists( 'rytkoset_theme_membership_activation_resolve_product' ) ) {
	/**
	 * Resolves and validates a membership snapshot from a selected product.
	 *
	 * @param int $product_id Selected WooCommerce product ID.
	 * @return array{type:string,period:string,expires:string}|WP_Error
	 */
	function rytkoset_theme_membership_activation_resolve_product( $product_id ) {
		$product_id = absint( $product_id );
		$product    = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;

		if ( ! $product instanceof WC_Product
			|| ! in_array( $product->get_status(), array( 'publish', 'private' ), true )
			|| ! rytkoset_theme_is_membership_product( $product )
		) {
			return new WP_Error(
				'rytkoset_invalid_membership_product',
				__( 'Valitse julkaistu jäsenmaksutuote ennen käsittelyä.', 'rytkoset-theme' )
			);
		}

		$product_type = rytkoset_theme_get_membership_product_type( $product );
		$type         = rytkoset_theme_map_product_to_user_membership_type( $product_type );

		if ( '' === $type ) {
			return new WP_Error(
				'rytkoset_invalid_membership_product_type',
				__( 'Valitulta tuotteelta puuttuu kelvollinen jäsenmaksun tyyppi.', 'rytkoset-theme' )
			);
		}

		if ( 'lifetime' === $type ) {
			return array(
				'type'    => 'lifetime',
				'period'  => '',
				'expires' => '',
			);
		}

		$period  = rytkoset_theme_get_membership_product_period( $product );
		$expires = rytkoset_theme_get_membership_product_expiry_date( $product );

		if ( '' === $period || '' === $expires ) {
			return new WP_Error(
				'rytkoset_incomplete_membership_product',
				__( 'Valitulle vuosi- tai perhejäsenmaksutuotteelle pitää asettaa jäsenkausi ja Jäsenyys voimassa asti -päivä.', 'rytkoset-theme' )
			);
		}

		return array(
			'type'    => $type,
			'period'  => rytkoset_theme_sanitize_user_membership_period( $period ),
			'expires' => $expires,
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_membership_current_covers_manual_update' ) ) {
	/**
	 * Returns true when the user's current own membership already covers the update.
	 *
	 * Mirrors the never-shorten rule of the WooCommerce order path: a lifetime
	 * update always applies; otherwise an existing lifetime membership, or an
	 * active membership expiring on or after the new expiry date, blocks the
	 * update so a manual entry cannot reduce a member's standing.
	 *
	 * @param array<string, string> $current           Stored membership details.
	 * @param bool                  $current_is_active Whether the current membership is active.
	 * @param array<string, string> $new_membership    Sanitized membership details to apply.
	 * @return bool
	 */
	function rytkoset_theme_membership_current_covers_manual_update( $current, $current_is_active, $new_membership ) {
		if ( 'lifetime' === ( $new_membership['type'] ?? '' ) ) {
			return false;
		}

		if ( 'lifetime' === ( $current['type'] ?? '' ) ) {
			return true;
		}

		return $current_is_active
			&& '' !== (string) ( $current['expires'] ?? '' )
			&& '' !== (string) ( $new_membership['expires'] ?? '' )
			&& $current['expires'] >= $new_membership['expires'];
	}
}

if ( ! function_exists( 'rytkoset_theme_apply_manual_membership' ) ) {
	/**
	 * Applies admin-chosen membership details to an existing user.
	 *
	 * Sends the shared #390 confirmation email on a non-active → active
	 * transition, exactly like the profile and order paths.
	 *
	 * @param int                   $user_id    User ID.
	 * @param array<string, string> $membership Sanitized membership details.
	 * @return string Result code: 'applied' or 'skipped_covered'.
	 */
	function rytkoset_theme_apply_manual_membership( $user_id, $membership ) {
		$user_id    = absint( $user_id );
		$current    = rytkoset_theme_get_user_membership( $user_id );
		$was_active = rytkoset_theme_user_has_own_active_membership( $user_id );

		if ( rytkoset_theme_membership_current_covers_manual_update( $current, $was_active, $membership ) ) {
			return 'skipped_covered';
		}

		update_user_meta( $user_id, rytkoset_theme_get_user_membership_type_meta_key(), $membership['type'] );

		if ( 'lifetime' === $membership['type'] ) {
			delete_user_meta( $user_id, rytkoset_theme_get_user_membership_period_meta_key() );
			delete_user_meta( $user_id, rytkoset_theme_get_user_membership_expires_meta_key() );
		} else {
			update_user_meta( $user_id, rytkoset_theme_get_user_membership_period_meta_key(), $membership['period'] );
			update_user_meta( $user_id, rytkoset_theme_get_user_membership_expires_meta_key(), $membership['expires'] );
		}

		if ( ! $was_active && rytkoset_theme_user_has_own_active_membership( $user_id ) ) {
			rytkoset_theme_send_membership_confirmation_email( $user_id );
		}

		return 'applied';
	}
}

if ( ! function_exists( 'rytkoset_theme_normalize_pending_manual_membership_entry' ) ) {
	/**
	 * Normalizes one stored pending manual membership entry.
	 *
	 * @param mixed $entry Raw stored entry.
	 * @return array{type:string,period:string,expires:string,created_at:string,invite_sent_at:string,added_by:int}|array<never>
	 */
	function rytkoset_theme_normalize_pending_manual_membership_entry( $entry ) {
		if ( ! is_array( $entry ) ) {
			return array();
		}

		$type = rytkoset_theme_normalize_user_membership_type( (string) ( $entry['type'] ?? '' ) );

		if ( '' === $type ) {
			return array();
		}

		return array(
			'type'           => $type,
			'period'         => rytkoset_theme_sanitize_user_membership_period( (string) ( $entry['period'] ?? '' ) ),
			'expires'        => rytkoset_theme_sanitize_user_membership_expires( (string) ( $entry['expires'] ?? '' ) ),
			'created_at'     => sanitize_text_field( (string) ( $entry['created_at'] ?? '' ) ),
			'invite_sent_at' => sanitize_text_field( (string) ( $entry['invite_sent_at'] ?? '' ) ),
			'added_by'       => absint( $entry['added_by'] ?? 0 ),
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_pending_manual_memberships' ) ) {
	/**
	 * Returns the pending manual memberships map keyed by normalized email.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rytkoset_theme_get_pending_manual_memberships() {
		$stored = get_option( rytkoset_theme_get_pending_manual_memberships_option_name(), array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$pending = array();

		foreach ( $stored as $email => $entry ) {
			$email = rytkoset_theme_normalize_family_member_email( (string) $email );
			$entry = rytkoset_theme_normalize_pending_manual_membership_entry( $entry );

			if ( '' === $email || empty( $entry ) ) {
				continue;
			}

			$pending[ $email ] = $entry;
		}

		return $pending;
	}
}

if ( ! function_exists( 'rytkoset_theme_save_pending_manual_memberships' ) ) {
	/**
	 * Persists the pending manual memberships map.
	 *
	 * @param array<string, array<string, mixed>> $pending Pending map keyed by email.
	 * @return void
	 */
	function rytkoset_theme_save_pending_manual_memberships( $pending ) {
		$option_name = rytkoset_theme_get_pending_manual_memberships_option_name();

		if ( empty( $pending ) ) {
			delete_option( $option_name );
			return;
		}

		update_option( $option_name, $pending, false );
	}
}

if ( ! function_exists( 'rytkoset_theme_remove_pending_manual_membership' ) ) {
	/**
	 * Removes one pending manual membership entry.
	 *
	 * @param string $email Email address (any casing).
	 * @return bool True when an entry was removed.
	 */
	function rytkoset_theme_remove_pending_manual_membership( $email ) {
		$email   = rytkoset_theme_normalize_family_member_email( (string) $email );
		$pending = rytkoset_theme_get_pending_manual_memberships();

		if ( '' === $email || ! isset( $pending[ $email ] ) ) {
			return false;
		}

		unset( $pending[ $email ] );
		rytkoset_theme_save_pending_manual_memberships( $pending );

		return true;
	}
}

if ( ! function_exists( 'rytkoset_theme_send_membership_activation_invite_email' ) ) {
	/**
	 * Sends the "create an account with this same email" invite (#518 model).
	 *
	 * Plain-text Finnish message that states why the recipient gets it, that the
	 * address comes from the association's member register, who the controller
	 * is and where the privacy statement lives, per the #525 GDPR requirements.
	 *
	 * @param string $email Normalized recipient email address.
	 * @return bool Whether wp_mail() accepted the message for delivery.
	 */
	function rytkoset_theme_send_membership_activation_invite_email( $email ) {
		if ( ! is_email( $email ) ) {
			return false;
		}

		$subject = __( 'Jäsenetusi odottavat käyttäjätiliä – Rytkösten sukuseura ry', 'rytkoset-theme' );

		$lines = array(
			__( 'Hei,', 'rytkoset-theme' ),
			'',
			__( 'olet Rytkösten sukuseura ry:n jäsenrekisterin mukaan seuran jäsen. Tämä viesti on lähetetty, jotta saat seuran verkkosivuston jäsenedut käyttöösi.', 'rytkoset-theme' ),
			'',
			__( 'Jäsenedut sivustolla (jäsenille rajatut sivut, jäsenhinnat ja digilehdet) vaativat käyttäjätilin. Jäsenyytesi aktivointi odottaa, että sinulla on tili sivustolla.', 'rytkoset-theme' ),
			'',
			sprintf(
				/* translators: %s: recipient email address. */
				__( 'Luo tili tällä samalla sähköpostiosoitteella (%s), niin jäsenyytesi aktivoituu automaattisesti:', 'rytkoset-theme' ),
				$email
			),
			wp_registration_url(),
			'',
			__( 'Sähköpostiosoitteesi on peräisin sukuseuran jäsenrekisteristä, ja sitä käytetään vain jäsenyyden hoitamiseen ja jäsenetujen käyttöönottoon. Rekisterinpitäjä on Rytkösten sukuseura ry.', 'rytkoset-theme' ),
		);

		$privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		if ( '' !== $privacy_url ) {
			$lines[] = sprintf(
				/* translators: %s: privacy statement URL. */
				__( 'Tietosuojaseloste: %s', 'rytkoset-theme' ),
				$privacy_url
			);
		}

		$contact_email = rytkoset_theme_get_contact_email();

		$lines[] = '';

		if ( is_email( $contact_email ) ) {
			$lines[] = sprintf(
				/* translators: %s: association contact email address. */
				__( 'Jos et halua viestejä jäsenetujen käyttöönotosta tai tiedoissasi on virhe, ota yhteyttä: %s', 'rytkoset-theme' ),
				$contact_email
			);
			$lines[] = '';
		}

		$lines[] = __( 'Terveisin', 'rytkoset-theme' );
		$lines[] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		return wp_mail(
			$email,
			$subject,
			implode( "\n", $lines ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_membership_activation_append_log' ) ) {
	/**
	 * Appends one processing entry to the activation log (newest first, capped).
	 *
	 * @param string $email    Processed email address.
	 * @param string $result   Result code.
	 * @param int    $admin_id Acting administrator user ID (0 = automatic on registration).
	 * @return void
	 */
	function rytkoset_theme_membership_activation_append_log( $email, $result, $admin_id ) {
		$option_name = rytkoset_theme_get_membership_activation_log_option_name();
		$log         = get_option( $option_name, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'     => current_time( 'mysql' ),
				'email'    => sanitize_email( (string) $email ),
				'result'   => sanitize_key( (string) $result ),
				'admin_id' => absint( $admin_id ),
			)
		);

		update_option( $option_name, array_slice( $log, 0, rytkoset_theme_get_membership_activation_log_max_entries() ), false );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_log' ) ) {
	/**
	 * Returns the activation processing log entries, newest first.
	 *
	 * @return array<int, array{time:string,email:string,result:string,admin_id:int}>
	 */
	function rytkoset_theme_get_membership_activation_log() {
		$log = get_option( rytkoset_theme_get_membership_activation_log_option_name(), array() );

		return is_array( $log ) ? array_values( array_filter( $log, 'is_array' ) ) : array();
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_result_label' ) ) {
	/**
	 * Returns the human-readable Finnish label for a processing result code.
	 *
	 * @param string $result Result code.
	 * @return string
	 */
	function rytkoset_theme_get_membership_activation_result_label( $result ) {
		$labels = array(
			'applied'             => __( 'Tili löytyi, jäsenyys päivitetty.', 'rytkoset-theme' ),
			'applied_on_register' => __( 'Odottava jäsenyys aktivoitu rekisteröitymisen yhteydessä.', 'rytkoset-theme' ),
			'skipped_covered'     => __( 'Ohitettu: käyttäjällä on jo vähintään yhtä pitkään voimassa oleva jäsenyys.', 'rytkoset-theme' ),
			'invited'             => __( 'Tiliä ei löytynyt: jäsenyys odottaa tiliä ja kutsuviesti lähetettiin.', 'rytkoset-theme' ),
			'pending_updated'     => __( 'Tiliä ei löytynyt: odottavat jäsenyystiedot päivitettiin, kutsua ei lähetetty uudelleen.', 'rytkoset-theme' ),
			'invite_failed'       => __( 'Kutsuviestin lähetys epäonnistui: jäsenyys jää odottamaan, yritä lähetystä uudelleen.', 'rytkoset-theme' ),
			'pending_removed'     => __( 'Odottava jäsenyys poistettu.', 'rytkoset-theme' ),
		);

		return isset( $labels[ $result ] ) ? $labels[ $result ] : $result;
	}
}

if ( ! function_exists( 'rytkoset_theme_membership_activation_process_email' ) ) {
	/**
	 * Processes one normalized email address with the chosen membership details.
	 *
	 * @param string                $email      Normalized email address.
	 * @param array<string, string> $membership Sanitized membership details.
	 * @param bool                  $resend     Whether the admin explicitly allowed re-sending the invite.
	 * @param int                   $admin_id   Acting administrator user ID.
	 * @return string Result code.
	 */
	function rytkoset_theme_membership_activation_process_email( $email, $membership, $resend, $admin_id ) {
		$user = get_user_by( 'email', $email );

		if ( $user instanceof WP_User && $user->ID > 0 ) {
			$result = rytkoset_theme_apply_manual_membership( (int) $user->ID, $membership );

			// A previously stored pending entry is now superseded by the direct update.
			rytkoset_theme_remove_pending_manual_membership( $email );
			rytkoset_theme_membership_activation_append_log( $email, $result, $admin_id );

			return $result;
		}

		$pending  = rytkoset_theme_get_pending_manual_memberships();
		$existing = isset( $pending[ $email ] ) ? $pending[ $email ] : array();

		$entry = array(
			'type'           => $membership['type'],
			'period'         => $membership['period'],
			'expires'        => $membership['expires'],
			'created_at'     => '' !== (string) ( $existing['created_at'] ?? '' ) ? $existing['created_at'] : current_time( 'mysql' ),
			'invite_sent_at' => (string) ( $existing['invite_sent_at'] ?? '' ),
			'added_by'       => $admin_id,
		);

		$already_invited = '' !== $entry['invite_sent_at'];

		if ( $already_invited && ! $resend ) {
			$result = 'pending_updated';
		} elseif ( rytkoset_theme_send_membership_activation_invite_email( $email ) ) {
			$entry['invite_sent_at'] = current_time( 'mysql' );
			$result                  = 'invited';
		} else {
			$result = 'invite_failed';
		}

		$pending[ $email ] = $entry;
		rytkoset_theme_save_pending_manual_memberships( $pending );
		rytkoset_theme_membership_activation_append_log( $email, $result, $admin_id );

		return $result;
	}
}

if ( ! function_exists( 'rytkoset_theme_membership_activation_process_submission' ) ) {
	/**
	 * Processes a full admin submission: email list + membership product.
	 *
	 * @param string $raw_emails Raw multi-line email input.
	 * @param int    $product_id Selected membership product ID.
	 * @param bool   $resend     Whether re-sending already-sent invites is allowed.
	 * @param int    $admin_id   Acting administrator user ID.
	 * @return array{results: array<int, array{email:string,result:string}>, invalid: string[], error: string}
	 */
	function rytkoset_theme_membership_activation_process_submission( $raw_emails, $product_id, $resend, $admin_id ) {
		$response = array(
			'results' => array(),
			'invalid' => array(),
			'error'   => '',
		);

		$membership = rytkoset_theme_membership_activation_resolve_product( $product_id );

		if ( is_wp_error( $membership ) ) {
			$response['error'] = $membership->get_error_message();
			return $response;
		}

		$parsed              = rytkoset_theme_membership_activation_parse_emails( $raw_emails );
		$response['invalid'] = $parsed['invalid'];

		if ( empty( $parsed['emails'] ) && empty( $parsed['invalid'] ) ) {
			$response['error'] = __( 'Syötä vähintään yksi sähköpostiosoite.', 'rytkoset-theme' );
			return $response;
		}

		foreach ( $parsed['emails'] as $email ) {
			$response['results'][] = array(
				'email'  => $email,
				'result' => rytkoset_theme_membership_activation_process_email( $email, $membership, $resend, $admin_id ),
			);
		}

		return $response;
	}
}

if ( ! function_exists( 'rytkoset_theme_apply_pending_manual_membership_on_user_register' ) ) {
	/**
	 * Applies a pending manual membership when an account is registered with the same email.
	 *
	 * WordPress registration verifies control of the email address via the
	 * password set link, so the membership cannot be claimed by the wrong
	 * person, same trust model as the #518 order path.
	 *
	 * @param int $user_id Newly registered user ID.
	 * @return void
	 */
	function rytkoset_theme_apply_pending_manual_membership_on_user_register( $user_id ) {
		$user = get_userdata( (int) $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$email   = rytkoset_theme_normalize_family_member_email( (string) $user->user_email );
		$pending = rytkoset_theme_get_pending_manual_memberships();

		if ( '' === $email || ! isset( $pending[ $email ] ) ) {
			return;
		}

		$membership = array(
			'type'    => $pending[ $email ]['type'],
			'period'  => $pending[ $email ]['period'],
			'expires' => $pending[ $email ]['expires'],
		);

		$result = rytkoset_theme_apply_manual_membership( (int) $user->ID, $membership );

		unset( $pending[ $email ] );
		rytkoset_theme_save_pending_manual_memberships( $pending );
		rytkoset_theme_membership_activation_append_log(
			$email,
			'applied' === $result ? 'applied_on_register' : $result,
			0
		);
	}
	add_action( 'user_register', 'rytkoset_theme_apply_pending_manual_membership_on_user_register' );
}

// ---------------------------------------------------------------------------
// Admin screen: Users > Jäsenten aktivointi
// ---------------------------------------------------------------------------

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_admin_page_slug' ) ) {
	/**
	 * Returns the admin page slug of the activation tool.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_membership_activation_admin_page_slug() {
		return 'rytkoset-membership-activation';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_admin_url' ) ) {
	/**
	 * Returns the admin URL of the activation tool page.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_membership_activation_admin_url() {
		return admin_url( 'users.php?page=' . rytkoset_theme_get_membership_activation_admin_page_slug() );
	}
}

if ( ! function_exists( 'rytkoset_theme_register_membership_activation_admin_page' ) ) {
	/**
	 * Registers the Users > Jäsenten aktivointi admin page.
	 *
	 * Uses the edit_users capability: the same right that allows setting a
	 * user's membership on the profile screen.
	 *
	 * @return void
	 */
	function rytkoset_theme_register_membership_activation_admin_page() {
		add_users_page(
			__( 'Jäsenten aktivointi', 'rytkoset-theme' ),
			__( 'Jäsenten aktivointi', 'rytkoset-theme' ),
			'edit_users',
			rytkoset_theme_get_membership_activation_admin_page_slug(),
			'rytkoset_theme_render_membership_activation_admin_page'
		);
	}
	add_action( 'admin_menu', 'rytkoset_theme_register_membership_activation_admin_page' );
}

if ( ! function_exists( 'rytkoset_theme_get_membership_activation_notice_transient' ) ) {
	/**
	 * Returns the per-admin transient key carrying results over the PRG redirect.
	 *
	 * @param int $admin_id Acting administrator user ID.
	 * @return string
	 */
	function rytkoset_theme_get_membership_activation_notice_transient( $admin_id ) {
		return 'rytkoset_membership_activation_notice_' . absint( $admin_id );
	}
}

if ( ! function_exists( 'rytkoset_theme_handle_membership_activation_submit' ) ) {
	/**
	 * Handles the activation tool POST actions (process list / remove pending).
	 *
	 * Runs on admin_init and redirects back to the tool page (POST-redirect-GET)
	 * so a page refresh can never re-process addresses or re-send invites.
	 *
	 * @return void
	 */
	function rytkoset_theme_handle_membership_activation_submit() {
		if ( ! isset( $_POST['rytkoset_membership_activation_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! isset( $_POST['rytkoset_membership_activation_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['rytkoset_membership_activation_nonce'] ) ),
				'rytkoset_membership_activation'
			)
		) {
			return;
		}

		$action   = sanitize_key( wp_unslash( $_POST['rytkoset_membership_activation_action'] ) );
		$admin_id = get_current_user_id();
		$notice   = array(
			'results' => array(),
			'invalid' => array(),
			'error'   => '',
		);

		if ( 'process' === $action ) {
			$notice = rytkoset_theme_membership_activation_process_submission(
				isset( $_POST['rytkoset_activation_emails'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rytkoset_activation_emails'] ) ) : '',
				isset( $_POST['rytkoset_activation_product_id'] ) ? absint( wp_unslash( $_POST['rytkoset_activation_product_id'] ) ) : 0,
				! empty( $_POST['rytkoset_activation_resend'] ),
				$admin_id
			);
		} elseif ( 'remove_pending' === $action ) {
			$email = isset( $_POST['rytkoset_activation_pending_email'] )
				? rytkoset_theme_normalize_family_member_email( sanitize_text_field( wp_unslash( $_POST['rytkoset_activation_pending_email'] ) ) )
				: '';

			if ( '' !== $email && rytkoset_theme_remove_pending_manual_membership( $email ) ) {
				rytkoset_theme_membership_activation_append_log( $email, 'pending_removed', $admin_id );
				$notice['results'][] = array(
					'email'  => $email,
					'result' => 'pending_removed',
				);
			} else {
				$notice['error'] = __( 'Odottavaa jäsenyyttä ei löytynyt poistettavaksi.', 'rytkoset-theme' );
			}
		} else {
			return;
		}

		set_transient( rytkoset_theme_get_membership_activation_notice_transient( $admin_id ), $notice, MINUTE_IN_SECONDS );

		wp_safe_redirect( rytkoset_theme_get_membership_activation_admin_url() );
		exit;
	}
	add_action( 'admin_init', 'rytkoset_theme_handle_membership_activation_submit' );
}

if ( ! function_exists( 'rytkoset_theme_render_membership_activation_admin_page' ) ) {
	/**
	 * Renders the Users > Jäsenten aktivointi admin page.
	 *
	 * @return void
	 */
	function rytkoset_theme_render_membership_activation_admin_page() {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$admin_id      = get_current_user_id();
		$transient_key = rytkoset_theme_get_membership_activation_notice_transient( $admin_id );
		$notice        = get_transient( $transient_key );

		if ( false !== $notice ) {
			delete_transient( $transient_key );
		}

		$pending             = rytkoset_theme_get_pending_manual_memberships();
		$log                 = array_slice( rytkoset_theme_get_membership_activation_log(), 0, 20 );
		$membership_products = rytkoset_theme_get_membership_activation_products();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Jäsenten aktivointi', 'rytkoset-theme' ); ?></h1>
			<p>
				<?php esc_html_e( 'Syötä olemassa olevien jäsenten sähköpostiosoitteita jäsenrekisterin perusteella. Jos osoitteella on käyttäjätili, jäsenyys päivitetään heti ja jäsen saa vahvistusviestin. Jos tiliä ei ole, osoitteelle lähetetään kutsu luoda tili samalla sähköpostilla, ja jäsenyys aktivoituu automaattisesti rekisteröitymisen yhteydessä.', 'rytkoset-theme' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Työkalu on tarkoitettu vain jäsenyyden hoitamiseen ja jäsenetujen käyttöönottoon. Se ei tilaa uutiskirjettä eikä lisää osoitteita muuhun viestintään. Olemassa olevaa pidempää tai ainaisjäsenyyttä ei koskaan heikennetä.', 'rytkoset-theme' ); ?>
			</p>

			<?php if ( is_array( $notice ) ) : ?>
				<?php if ( '' !== (string) $notice['error'] ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $notice['error'] ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $notice['invalid'] ) ) : ?>
					<div class="notice notice-warning">
						<p><?php esc_html_e( 'Seuraavia rivejä ei tunnistettu sähköpostiosoitteiksi, eikä niitä käsitelty:', 'rytkoset-theme' ); ?></p>
						<ul>
							<?php foreach ( $notice['invalid'] as $invalid_line ) : ?>
								<li><code><?php echo esc_html( $invalid_line ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $notice['results'] ) ) : ?>
					<div class="notice notice-success">
						<p><?php esc_html_e( 'Käsittelyn tulokset:', 'rytkoset-theme' ); ?></p>
						<ul>
							<?php foreach ( $notice['results'] as $row ) : ?>
								<li>
									<strong><?php echo esc_html( $row['email'] ); ?></strong>
									&#8211; <?php echo esc_html( rytkoset_theme_get_membership_activation_result_label( $row['result'] ) ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Käsittele sähköpostiosoitteet', 'rytkoset-theme' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'rytkoset_membership_activation', 'rytkoset_membership_activation_nonce' ); ?>
				<input type="hidden" name="rytkoset_membership_activation_action" value="process" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="rytkoset_activation_emails"><?php esc_html_e( 'Sähköpostiosoitteet', 'rytkoset-theme' ); ?></label>
						</th>
						<td>
							<textarea name="rytkoset_activation_emails" id="rytkoset_activation_emails" class="large-text" rows="6" required></textarea>
							<p class="description"><?php esc_html_e( 'Yksi osoite per rivi. Osoitteet siistitään ja kaksoiskappaleet ohitetaan automaattisesti.', 'rytkoset-theme' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="rytkoset_activation_product_id"><?php esc_html_e( 'Jäsenyys', 'rytkoset-theme' ); ?></label>
						</th>
						<td>
							<select name="rytkoset_activation_product_id" id="rytkoset_activation_product_id" required>
								<option value=""><?php esc_html_e( '— Valitse jäsenmaksutuote —', 'rytkoset-theme' ); ?></option>
								<?php foreach ( $membership_products as $product_id => $membership_product ) : ?>
									<option value="<?php echo esc_attr( (string) $product_id ); ?>">
										<?php echo esc_html( rytkoset_theme_get_membership_activation_product_label( $membership_product ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( empty( $membership_products ) ) : ?>
								<p class="description"><?php esc_html_e( 'Julkaistuja jäsenmaksutuotteita ei löytynyt. Määritä jäsenmaksutuote ennen aktivointia.', 'rytkoset-theme' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Jäsenyyden tyyppi, kausi ja voimassaolo luetaan tuotteelta ja tallennetaan jäsenelle kopiona. Perhejäsenmaksulla jokaisesta syötetystä osoitteesta tulee oma perhejäsenyyden päätili.', 'rytkoset-theme' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kutsun uudelleenlähetys', 'rytkoset-theme' ); ?></th>
						<td>
							<label for="rytkoset_activation_resend">
								<input type="checkbox" name="rytkoset_activation_resend" id="rytkoset_activation_resend" value="1" />
								<?php esc_html_e( 'Lähetä kutsuviesti uudelleen myös osoitteille, joille se on jo lähetetty.', 'rytkoset-theme' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Ilman tätä valintaa jo kutsutun osoitteen jäsenyystiedot päivitetään, mutta viestiä ei lähetetä uudelleen.', 'rytkoset-theme' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Käsittele osoitteet', 'rytkoset-theme' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Tiliä odottavat jäsenyydet', 'rytkoset-theme' ); ?></h2>
			<?php if ( empty( $pending ) ) : ?>
				<p><?php esc_html_e( 'Ei odottavia jäsenyyksiä.', 'rytkoset-theme' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Jäsenyys', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Voimassa asti', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Kutsu lähetetty', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Lisääjä', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Toiminnot', 'rytkoset-theme' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending as $pending_email => $entry ) : ?>
							<?php $added_by = $entry['added_by'] > 0 ? get_userdata( $entry['added_by'] ) : false; ?>
							<tr>
								<td><?php echo esc_html( $pending_email ); ?></td>
								<td><?php echo esc_html( rytkoset_theme_get_user_membership_type_label( $entry['type'] ) ); ?></td>
								<td><?php echo esc_html( rytkoset_theme_get_user_membership_expires_display( $entry['expires'] ) ); ?></td>
								<td><?php echo esc_html( '' !== $entry['invite_sent_at'] ? $entry['invite_sent_at'] : __( 'Ei lähetetty', 'rytkoset-theme' ) ); ?></td>
								<td><?php echo esc_html( $added_by instanceof WP_User ? $added_by->user_login : (string) $entry['added_by'] ); ?></td>
								<td>
									<form method="post">
										<?php wp_nonce_field( 'rytkoset_membership_activation', 'rytkoset_membership_activation_nonce' ); ?>
										<input type="hidden" name="rytkoset_membership_activation_action" value="remove_pending" />
										<input type="hidden" name="rytkoset_activation_pending_email" value="<?php echo esc_attr( $pending_email ); ?>" />
										<?php submit_button( __( 'Poista', 'rytkoset-theme' ), 'small', 'submit', false ); ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">
					<?php esc_html_e( 'Poisto lopettaa odottavan jäsenyyden: rekisteröityminen samalla sähköpostilla ei enää aktivoi jäsenyyttä automaattisesti.', 'rytkoset-theme' ); ?>
				</p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Viimeisimmät käsittelyt', 'rytkoset-theme' ); ?></h2>
			<?php if ( empty( $log ) ) : ?>
				<p><?php esc_html_e( 'Ei käsittelyjä.', 'rytkoset-theme' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Aika', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Tulos', 'rytkoset-theme' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Käsittelijä', 'rytkoset-theme' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) : ?>
							<?php
							$entry_admin = absint( $entry['admin_id'] ?? 0 );
							$admin_user  = $entry_admin > 0 ? get_userdata( $entry_admin ) : false;
							?>
							<tr>
								<td><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['email'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( rytkoset_theme_get_membership_activation_result_label( (string) ( $entry['result'] ?? '' ) ) ); ?></td>
								<td>
									<?php
									if ( $admin_user instanceof WP_User ) {
										echo esc_html( $admin_user->user_login );
									} elseif ( 0 === $entry_admin ) {
										esc_html_e( 'Automaattinen (rekisteröityminen)', 'rytkoset-theme' );
									} else {
										echo esc_html( (string) $entry_admin );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
