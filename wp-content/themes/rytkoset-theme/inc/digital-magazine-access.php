<?php
/**
 * Digilehtien manuaalinen käyttöoikeuden myöntäminen (Malli A).
 *
 * Osa digilehdistä myydään verkkokaupan ulkopuolella (käteinen, tilaisuudet, postimyynti).
 * Tässä moduulissa ylläpitäjä voi myöntää rekisteröidylle käyttäjälle pysyvän pääsyn
 * yksittäisen `paid`- tai `member_and_regular`-digilehden suojattuun sisältöön käyttäjä-
 * profiilista.
 *
 * Malli A: manuaaliset myönnöt tallennetaan käyttäjämetaan (`rytkoset_magazine_access`,
 * ei alaviivaa, vrt. `inc/user-membership.php`); verkkokauppaostoja EI kopioida tänne, vaan
 * ne ratkaistaan live-haulla (#201). Molemmat reitit yhdistyvät saman suodattimen
 * `rytkoset_theme_user_has_purchased_digital_magazine` kautta OR-logiikalla.
 *
 * Pääsystä lähtee sähköposti-ilmoitus jaetun helperin
 * `rytkoset_theme_send_digital_magazine_access_email()` kautta — samaa funktiota kutsuu myös
 * #201 (osto), jotta viesti on identtinen molemmilta reiteiltä.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the user meta key storing manual digital magazine access grants.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_digital_magazine_access_meta_key' ) ) {
	function rytkoset_theme_get_digital_magazine_access_meta_key() {
		return 'rytkoset_magazine_access';
	}
}

/**
 * Returns the manual magazine access grants stored for a user.
 *
 * Only manually granted access is stored here (magazine ID => grant timestamp). WooCommerce
 * purchases are resolved separately by #201 and are not copied into this store.
 *
 * @param int $user_id User ID.
 * @return array<int, string> Map of magazine post ID to MySQL grant timestamp.
 */
if ( ! function_exists( 'rytkoset_theme_get_user_manual_magazine_access' ) ) {
	function rytkoset_theme_get_user_manual_magazine_access( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}

		$stored = get_user_meta( $user_id, rytkoset_theme_get_digital_magazine_access_meta_key(), true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$access = array();

		foreach ( $stored as $magazine_id => $granted_at ) {
			$magazine_id = (int) $magazine_id;

			if ( $magazine_id > 0 ) {
				$access[ $magazine_id ] = (string) $granted_at;
			}
		}

		return $access;
	}
}

/**
 * Returns true when a user has a manual access grant for a magazine or article.
 *
 * Articles normalize to their parent magazine, matching the shared read-access model.
 *
 * @param int      $magazine_id Magazine or article post ID.
 * @param int|null $user_id     User ID. Defaults to the current user.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_user_has_manual_magazine_access' ) ) {
	function rytkoset_theme_user_has_manual_magazine_access( $magazine_id, $user_id = null ) {
		$user_id = ( null === $user_id ) ? get_current_user_id() : (int) $user_id;

		if ( $user_id <= 0 ) {
			return false;
		}

		$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );

		if ( $magazine_id <= 0 ) {
			return false;
		}

		$access = rytkoset_theme_get_user_manual_magazine_access( $user_id );

		return isset( $access[ $magazine_id ] );
	}
}

/**
 * Returns the manual grant timestamp for a magazine, or '' when none.
 *
 * @param int $user_id     User ID.
 * @param int $magazine_id Magazine or article post ID.
 * @return string MySQL timestamp, or empty string.
 */
if ( ! function_exists( 'rytkoset_theme_get_manual_magazine_access_date' ) ) {
	function rytkoset_theme_get_manual_magazine_access_date( $user_id, $magazine_id ) {
		$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );
		$access      = rytkoset_theme_get_user_manual_magazine_access( $user_id );

		return isset( $access[ $magazine_id ] ) ? $access[ $magazine_id ] : '';
	}
}

/**
 * Grants manual access to a magazine for a user.
 *
 * Returns true only on a no-access → access transition, so callers can send the access email
 * exactly once. Returns false when access already existed or the input is invalid.
 *
 * @param int $user_id     User ID.
 * @param int $magazine_id Magazine or article post ID (normalized to the parent magazine).
 * @return bool True when access was newly granted.
 */
if ( ! function_exists( 'rytkoset_theme_grant_manual_magazine_access' ) ) {
	function rytkoset_theme_grant_manual_magazine_access( $user_id, $magazine_id ) {
		$user_id     = (int) $user_id;
		$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );

		if ( $user_id <= 0 || $magazine_id <= 0 ) {
			return false;
		}

		$access = rytkoset_theme_get_user_manual_magazine_access( $user_id );

		if ( isset( $access[ $magazine_id ] ) ) {
			return false;
		}

		$access[ $magazine_id ] = current_time( 'mysql' );

		update_user_meta( $user_id, rytkoset_theme_get_digital_magazine_access_meta_key(), $access );

		return true;
	}
}

/**
 * Revokes manual access to a magazine for a user.
 *
 * @param int $user_id     User ID.
 * @param int $magazine_id Magazine or article post ID (normalized to the parent magazine).
 * @return bool True when an existing grant was removed.
 */
if ( ! function_exists( 'rytkoset_theme_revoke_manual_magazine_access' ) ) {
	function rytkoset_theme_revoke_manual_magazine_access( $user_id, $magazine_id ) {
		$user_id     = (int) $user_id;
		$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );

		if ( $user_id <= 0 || $magazine_id <= 0 ) {
			return false;
		}

		$access = rytkoset_theme_get_user_manual_magazine_access( $user_id );

		if ( ! isset( $access[ $magazine_id ] ) ) {
			return false;
		}

		unset( $access[ $magazine_id ] );

		if ( empty( $access ) ) {
			delete_user_meta( $user_id, rytkoset_theme_get_digital_magazine_access_meta_key() );
		} else {
			update_user_meta( $user_id, rytkoset_theme_get_digital_magazine_access_meta_key(), $access );
		}

		return true;
	}
}

/**
 * Merges manual grants into the shared magazine purchase/access filter (Model A, OR-logic).
 *
 * #201 adds a separate callback to the same filter for the live WooCommerce purchase lookup;
 * the two results combine naturally because each callback only ever flips false to true.
 *
 * @param bool $has_access  Whether access has been granted by an earlier callback.
 * @param int  $magazine_id Parent magazine post ID.
 * @param int  $user_id     User ID, or 0 for anonymous users.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_filter_manual_magazine_access' ) ) {
	function rytkoset_theme_filter_manual_magazine_access( $has_access, $magazine_id, $user_id ) {
		if ( $has_access || (int) $user_id <= 0 ) {
			return $has_access;
		}

		return rytkoset_theme_user_has_manual_magazine_access( $magazine_id, $user_id );
	}
}
add_filter( 'rytkoset_theme_user_has_purchased_digital_magazine', 'rytkoset_theme_filter_manual_magazine_access', 10, 3 );

/**
 * Sends the digital magazine access email to a user (shared by manual grants and #201 purchases).
 *
 * The message names the magazine, links to it, and reminds the reader that the protected content
 * requires logging in on the same account. The sender comes from inc/email.php.
 *
 * @param int $user_id     User ID.
 * @param int $magazine_id Magazine or article post ID (normalized to the parent magazine).
 * @return bool Whether wp_mail() accepted the message for delivery.
 */
if ( ! function_exists( 'rytkoset_theme_send_digital_magazine_access_email' ) ) {
	function rytkoset_theme_send_digital_magazine_access_email( $user_id, $magazine_id ) {
		$user        = get_userdata( (int) $user_id );
		$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( (int) $magazine_id );

		if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
			return false;
		}

		$magazine = get_post( $magazine_id );

		if ( ! $magazine instanceof WP_Post || 'digital_magazine' !== $magazine->post_type ) {
			return false;
		}

		$title        = wp_specialchars_decode( get_the_title( $magazine_id ), ENT_QUOTES );
		$permalink    = get_permalink( $magazine_id );
		$display_name = trim( (string) $user->display_name );

		$subject = sprintf(
			/* translators: %s: digital magazine title. */
			__( 'Sait lukuoikeuden digilehteen: %s', 'rytkoset-theme' ),
			$title
		);

		$lines = array(
			'' !== $display_name
				? sprintf(
					/* translators: %s: recipient name. */
					__( 'Hei %s,', 'rytkoset-theme' ),
					$display_name
				)
				: __( 'Hei,', 'rytkoset-theme' ),
			'',
			sprintf(
				/* translators: %s: digital magazine title. */
				__( 'Sait lukuoikeuden digilehteen ”%s”.', 'rytkoset-theme' ),
				$title
			),
			'',
			__( 'Pääset lukemaan lehden tästä:', 'rytkoset-theme' ),
			esc_url_raw( $permalink ),
			'',
			__( 'Huomaa: digilehden sisältö avautuu vain, kun olet kirjautuneena samalle käyttäjätilille, jolle pääsy myönnettiin.', 'rytkoset-theme' ),
			'',
			__( 'Terveisin', 'rytkoset-theme' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);

		return wp_mail(
			$user->user_email,
			$subject,
			implode( "\n", $lines ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}

/**
 * Returns the top-level magazines whose access can be granted manually.
 *
 * Only paid and member-and-regular magazines are grantable; free and members-only magazines
 * are gated by other rules and need no per-user grant.
 *
 * @return WP_Post[]
 */
if ( ! function_exists( 'rytkoset_theme_get_grantable_digital_magazines' ) ) {
	function rytkoset_theme_get_grantable_digital_magazines() {
		return get_posts(
			array(
				'post_type'      => 'digital_magazine',
				'post_status'    => 'publish',
				'post_parent'    => 0,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only profile screen, run once per edited user.
				'meta_query'     => array(
					array(
						'key'     => rytkoset_theme_get_digital_magazine_access_mode_meta_key(),
						'value'   => array( 'paid', 'member_and_regular' ),
						'compare' => 'IN',
					),
				),
			)
		);
	}
}

/**
 * Returns the nonce action for the magazine access profile section.
 *
 * @param int $user_id The edited user ID.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_digital_magazine_access_nonce_action' ) ) {
	function rytkoset_theme_get_digital_magazine_access_nonce_action( $user_id ) {
		return 'rytkoset_save_magazine_access_' . (int) $user_id;
	}
}

/**
 * Renders the "Digilehtien käyttöoikeudet" section on a user profile screen.
 *
 * @param WP_User $user The user being edited.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_render_user_magazine_access_fields' ) ) {
	function rytkoset_theme_render_user_magazine_access_fields( $user ) {
		if ( ! current_user_can( 'edit_users' ) || ! $user instanceof WP_User ) {
			return;
		}

		$magazines = rytkoset_theme_get_grantable_digital_magazines();

		wp_nonce_field(
			rytkoset_theme_get_digital_magazine_access_nonce_action( $user->ID ),
			'rytkoset_magazine_access_nonce'
		);
		?>
		<h2><?php esc_html_e( 'Digilehtien käyttöoikeudet', 'rytkoset-theme' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Myönnä pääsy verkkokaupan ulkopuolella myytyihin digilehtiin. Vain maksulliset ja jäsenhinta + normaalihinta -lehdet näkyvät tässä; pääsy on pysyvä ja vaatii kirjautumisen. Käyttäjä saa uudesta myönnöstä sähköposti-ilmoituksen.', 'rytkoset-theme' ); ?>
		</p>
		<?php if ( empty( $magazines ) ) : ?>
			<p><?php esc_html_e( 'Ei manuaalisesti myönnettäviä digilehtiä.', 'rytkoset-theme' ); ?></p>
		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Myönnetyt digilehdet', 'rytkoset-theme' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Digilehtien käyttöoikeudet', 'rytkoset-theme' ); ?></span>
							</legend>
							<?php
							foreach ( $magazines as $magazine ) :
								$granted = rytkoset_theme_user_has_manual_magazine_access( $magazine->ID, $user->ID );
								$date    = $granted ? rytkoset_theme_get_manual_magazine_access_date( $user->ID, $magazine->ID ) : '';
								?>
								<label style="display:block; margin-bottom:.25em;">
									<input type="checkbox" name="rytkoset_magazine_access[]" value="<?php echo esc_attr( (string) $magazine->ID ); ?>" <?php checked( $granted ); ?> />
									<?php echo esc_html( get_the_title( $magazine ) ); ?>
									<?php if ( $granted && '' !== $date ) : ?>
										<span class="description">
											<?php
											printf(
												/* translators: %s: grant date. */
												esc_html__( '(myönnetty %s)', 'rytkoset-theme' ),
												esc_html( mysql2date( 'j.n.Y', $date ) )
											);
											?>
										</span>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Rastita lehdet, joihin käyttäjällä on pääsy. Rastin poisto peruu pääsyn (ei lähetä ilmoitusta).', 'rytkoset-theme' ); ?>
						</p>
					</td>
				</tr>
			</table>
		<?php endif; ?>
		<?php
	}
}
add_action( 'show_user_profile', 'rytkoset_theme_render_user_magazine_access_fields' );
add_action( 'edit_user_profile', 'rytkoset_theme_render_user_magazine_access_fields' );

/**
 * Saves the magazine access section from a user profile screen.
 *
 * Only acts on grantable magazines (allowlist), so a tampered request cannot grant access to
 * other posts. New grants send the access email exactly once; revocations are silent.
 *
 * @param int $user_id The edited user ID.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_save_user_magazine_access_fields' ) ) {
	function rytkoset_theme_save_user_magazine_access_fields( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['rytkoset_magazine_access_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['rytkoset_magazine_access_nonce'] ) ),
				rytkoset_theme_get_digital_magazine_access_nonce_action( $user_id )
			)
		) {
			return;
		}

		$submitted = ( isset( $_POST['rytkoset_magazine_access'] ) && is_array( $_POST['rytkoset_magazine_access'] ) )
			? array_filter( array_map( 'absint', wp_unslash( $_POST['rytkoset_magazine_access'] ) ) )
			: array();

		foreach ( rytkoset_theme_get_grantable_digital_magazines() as $magazine ) {
			$magazine_id = (int) $magazine->ID;
			$should_have = in_array( $magazine_id, $submitted, true );
			$has_access  = rytkoset_theme_user_has_manual_magazine_access( $magazine_id, $user_id );

			if ( $should_have && ! $has_access ) {
				if ( rytkoset_theme_grant_manual_magazine_access( $user_id, $magazine_id ) ) {
					rytkoset_theme_send_digital_magazine_access_email( $user_id, $magazine_id );
				}
			} elseif ( ! $should_have && $has_access ) {
				rytkoset_theme_revoke_manual_magazine_access( $user_id, $magazine_id );
			}
		}
	}
}
add_action( 'personal_options_update', 'rytkoset_theme_save_user_magazine_access_fields' );
add_action( 'edit_user_profile_update', 'rytkoset_theme_save_user_magazine_access_fields' );
