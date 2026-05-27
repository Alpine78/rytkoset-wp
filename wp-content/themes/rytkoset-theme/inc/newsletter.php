<?php
/**
 * Newsletter signup helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_newsletter_shortcode' ) ) {
	/**
	 * Returns the Customizer-defined AcyMailing newsletter shortcode.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_newsletter_shortcode() {
		$shortcode = get_theme_mod( 'rytkoset_theme_newsletter_shortcode', '' );

		return is_string( $shortcode ) ? trim( $shortcode ) : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_sanitize_newsletter_shortcode' ) ) {
	/**
	 * Sanitizes the newsletter shortcode Customizer value.
	 *
	 * Only the AcyMailing subscription form shortcode is accepted for the footer.
	 *
	 * @param string $value Customizer value.
	 * @return string
	 */
	function rytkoset_theme_sanitize_newsletter_shortcode( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		$value = sanitize_text_field( $value );

		if ( ! has_shortcode( $value, 'acymailing_form_shortcode' ) ) {
			return '';
		}

		return $value;
	}
}

if ( ! function_exists( 'rytkoset_theme_register_newsletter_customizer' ) ) {
	/**
	 * Registers the newsletter Customizer settings.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	function rytkoset_theme_register_newsletter_customizer( $wp_customize ) {
		$wp_customize->add_section(
			'rytkoset_theme_newsletter',
			array(
				'title'       => __( 'Uutiskirje', 'rytkoset-theme' ),
				'description' => __( 'Footerin uutiskirjelomake käyttää AcyMailingin subscription form -shortcodea.', 'rytkoset-theme' ),
				'priority'    => 85,
			)
		);

		$wp_customize->add_setting(
			'rytkoset_theme_newsletter_shortcode',
			array(
				'default'           => '',
				'sanitize_callback' => 'rytkoset_theme_sanitize_newsletter_shortcode',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'rytkoset_theme_newsletter_shortcode',
			array(
				'label'       => __( 'AcyMailing-lomakkeen shortcode', 'rytkoset-theme' ),
				'description' => __( 'Esimerkiksi [acymailing_form_shortcode id="1"]. Lomake näytetään footerissa vain, jos AcyMailing on aktiivinen ja shortcode renderöityy.', 'rytkoset-theme' ),
				'section'     => 'rytkoset_theme_newsletter',
				'type'        => 'text',
			)
		);
	}
}
add_action( 'customize_register', 'rytkoset_theme_register_newsletter_customizer' );

if ( ! function_exists( 'rytkoset_theme_get_newsletter_form_id' ) ) {
	/**
	 * Extracts the AcyMailing form ID from the configured shortcode.
	 *
	 * @param string $shortcode Newsletter shortcode.
	 * @return int
	 */
	function rytkoset_theme_get_newsletter_form_id( $shortcode ) {
		if ( ! is_string( $shortcode ) || '' === trim( $shortcode ) ) {
			return 0;
		}

		if ( ! preg_match( '/\[acymailing_form_shortcode\b([^\]]*)\]/', $shortcode, $matches ) ) {
			return 0;
		}

		$atts = shortcode_parse_atts( $matches[1] );

		return isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_newsletter_form_list_ids' ) ) {
	/**
	 * Returns the target list IDs from an AcyMailing form.
	 *
	 * @param int $form_id AcyMailing form ID.
	 * @return int[]
	 */
	function rytkoset_theme_get_newsletter_form_list_ids( $form_id ) {
		$form_id = absint( $form_id );

		if ( 0 === $form_id ) {
			return array();
		}

		global $wpdb;

		$form_table = $wpdb->prefix . 'acym_form';
		$settings   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT settings FROM {$form_table} WHERE id = %d AND active = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the WordPress DB prefix.
				$form_id
			)
		);

		if ( ! is_string( $settings ) || '' === $settings ) {
			return array();
		}

		$settings = json_decode( $settings, true );

		if ( ! is_array( $settings ) || empty( $settings['lists'] ) || ! is_array( $settings['lists'] ) ) {
			return array();
		}

		$list_ids = array();

		foreach ( array( 'automatic_subscribe', 'checked', 'displayed' ) as $key ) {
			if ( empty( $settings['lists'][ $key ] ) || ! is_array( $settings['lists'][ $key ] ) ) {
				continue;
			}

			$list_ids = array_merge( $list_ids, array_map( 'absint', $settings['lists'][ $key ] ) );
		}

		return array_values( array_unique( array_filter( $list_ids ) ) );
	}
}

if ( ! function_exists( 'rytkoset_theme_current_user_has_newsletter_subscription' ) ) {
	/**
	 * Checks whether the current logged-in user is already subscribed to the form target list.
	 *
	 * @param int[] $list_ids AcyMailing list IDs.
	 * @return bool
	 */
	function rytkoset_theme_current_user_has_newsletter_subscription( $list_ids ) {
		if ( ! is_user_logged_in() || empty( $list_ids ) ) {
			return false;
		}

		$list_ids = array_values( array_unique( array_filter( array_map( 'absint', $list_ids ) ) ) );

		if ( empty( $list_ids ) ) {
			return false;
		}

		global $wpdb;

		$user_table = $wpdb->prefix . 'acym_user';
		$list_table = $wpdb->prefix . 'acym_user_has_list';
		$cms_user   = wp_get_current_user();
		$email      = is_a( $cms_user, 'WP_User' ) ? sanitize_email( $cms_user->user_email ) : '';

		if ( '' === $email ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		$query_args   = array_merge( array( get_current_user_id(), $email ), $list_ids );
		$query        = $wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$user_table} AS acym_user
			 INNER JOIN {$list_table} AS acym_list
				ON acym_list.user_id = acym_user.id
			 WHERE (acym_user.cms_id = %d OR acym_user.email = %s)
				AND acym_list.status = 1
				AND acym_list.list_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and placeholders are generated internally.
			$query_args
		);

		return (int) $wpdb->get_var( $query ) > 0;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_logged_in_newsletter_button_markup' ) ) {
	/**
	 * Builds a compact AcyMailing subscribe form for logged-in users.
	 *
	 * @param int   $form_id  AcyMailing form ID.
	 * @param int[] $list_ids AcyMailing list IDs.
	 * @return string
	 */
	function rytkoset_theme_get_logged_in_newsletter_button_markup( $form_id, $list_ids ) {
		if ( ! is_user_logged_in() || empty( $list_ids ) || ! function_exists( 'acym_frontendLink' ) ) {
			return '';
		}

		$user = wp_get_current_user();

		if ( ! is_a( $user, 'WP_User' ) || '' === sanitize_email( $user->user_email ) ) {
			return '';
		}

		$form_id        = absint( $form_id );
		$list_ids       = array_values( array_unique( array_filter( array_map( 'absint', $list_ids ) ) ) );
		$form_name      = 'formAcymFooterLoggedIn' . $form_id;
		$frontend_link  = htmlspecialchars_decode( acym_frontendLink( 'frontusers' ) );
		$component_name = defined( 'ACYM_COMPONENT' ) ? ACYM_COMPONENT : 'acymailing';

		if ( function_exists( 'acym_initModule' ) ) {
			acym_initModule( null, array( 'loadJsInModule' => true ) );
		}

		ob_start();
		?>
		<form
			action="<?php echo esc_url( $frontend_link ); ?>"
			id="<?php echo esc_attr( $form_name ); ?>"
			name="<?php echo esc_attr( $form_name ); ?>"
			class="site-footer__newsletter-action"
			method="post"
			data-success-message="<?php esc_attr_e( 'Olet jo uutiskirjeen tilaaja.', 'rytkoset-theme' ); ?>"
			data-loading-message="<?php esc_attr_e( 'Tilataan...', 'rytkoset-theme' ); ?>"
			data-error-message="<?php esc_attr_e( 'Tilaus ei onnistunut. Yritä hetken päästä uudelleen.', 'rytkoset-theme' ); ?>"
			onsubmit="return rytkosetThemeSubmitNewsletterButton(this)"
		>
			<input type="hidden" name="user[email]" value="<?php echo esc_attr( sanitize_email( $user->user_email ) ); ?>">
			<input type="hidden" name="hiddenlists" value="<?php echo esc_attr( implode( ',', $list_ids ) ); ?>">
			<input type="hidden" name="ctrl" value="frontusers">
			<input type="hidden" name="task" value="notask">
			<input type="hidden" name="page" value="acymailing_front">
			<input type="hidden" name="option" value="<?php echo esc_attr( $component_name ); ?>">
			<input type="hidden" name="acy_source" value="<?php echo esc_attr( 'Footer form ID ' . $form_id ); ?>">
			<input type="hidden" name="acyformname" value="<?php echo esc_attr( $form_name ); ?>">
			<input type="hidden" name="acymformtype" value="shortcode">
			<input type="hidden" name="acysubmode" value="form_acym">
			<input type="hidden" name="redirect" value="">
			<input type="hidden" name="ajax" value="1">
			<input type="hidden" name="confirmation_message" value="">
			<button type="submit" class="site-footer__newsletter-button">
				<?php esc_html_e( 'Tilaa uutiskirje', 'rytkoset-theme' ); ?>
			</button>
			<p class="site-footer__newsletter-status" role="status" aria-live="polite"></p>
		</form>
		<script>
			window.rytkosetThemeSubmitNewsletterButton = window.rytkosetThemeSubmitNewsletterButton || function(form) {
				if (!window.fetch || !window.FormData) {
					return true;
				}

				var button = form.querySelector('button[type="submit"]');
				var status = form.querySelector('.site-footer__newsletter-status');
				var successMessage = form.getAttribute('data-success-message') || 'Olet jo uutiskirjeen tilaaja.';
				var loadingMessage = form.getAttribute('data-loading-message') || 'Tilataan...';
				var errorMessage = form.getAttribute('data-error-message') || 'Tilaus ei onnistunut. Yritä hetken päästä uudelleen.';

				if (button) {
					button.disabled = true;
					button.textContent = loadingMessage;
				}

				if (status) {
					status.textContent = loadingMessage;
				}

				var formData = new FormData(form);
				formData.set('task', 'subscribe');

				fetch(form.action, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
					.then(function(response) {
						return response.text();
					})
					.then(function(text) {
						var data = {};

						try {
							data = JSON.parse(text);
						} catch (error) {
							throw new Error('Invalid AcyMailing response');
						}

						if (data.type === 'error') {
							throw new Error(data.message || errorMessage);
						}

						var message = document.createElement('p');
						message.className = 'site-footer__newsletter-text';
						message.textContent = successMessage;
						form.replaceWith(message);
					})
					.catch(function(error) {
						if (button) {
							button.disabled = false;
							button.textContent = 'Tilaa uutiskirje';
						}

						if (status) {
							status.textContent = error && error.message ? error.message : errorMessage;
						}
					});

				return false;
			};
		</script>
		<?php

		return trim( ob_get_clean() );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_footer_newsletter_markup' ) ) {
	/**
	 * Builds the footer newsletter signup markup.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_footer_newsletter_markup() {
		$shortcode = rytkoset_theme_get_newsletter_shortcode();

		if ( '' === $shortcode || ! shortcode_exists( 'acymailing_form_shortcode' ) ) {
			return '';
		}

		$form_id  = rytkoset_theme_get_newsletter_form_id( $shortcode );
		$list_ids = rytkoset_theme_get_newsletter_form_list_ids( $form_id );

		if ( rytkoset_theme_current_user_has_newsletter_subscription( $list_ids ) ) {
			ob_start();
			?>
			<section class="site-footer__newsletter" aria-labelledby="site-footer-newsletter-title">
				<h2 id="site-footer-newsletter-title" class="site-footer__newsletter-title">
					<?php esc_html_e( 'Tilaa uutiskirje', 'rytkoset-theme' ); ?>
				</h2>
				<p class="site-footer__newsletter-text">
					<?php esc_html_e( 'Olet jo uutiskirjeen tilaaja.', 'rytkoset-theme' ); ?>
				</p>
			</section>
			<?php

			return trim( ob_get_clean() );
		}

		$logged_in_button_markup = rytkoset_theme_get_logged_in_newsletter_button_markup( $form_id, $list_ids );

		if ( '' !== $logged_in_button_markup ) {
			ob_start();
			?>
			<section class="site-footer__newsletter" aria-labelledby="site-footer-newsletter-title">
				<h2 id="site-footer-newsletter-title" class="site-footer__newsletter-title">
					<?php esc_html_e( 'Tilaa uutiskirje', 'rytkoset-theme' ); ?>
				</h2>
				<p class="site-footer__newsletter-text">
					<?php esc_html_e( 'Saat ajankohtaiset tiedot sukuseuran uutisista ja tapahtumista sähköpostiisi.', 'rytkoset-theme' ); ?>
				</p>
				<div class="site-footer__newsletter-form">
					<?php echo $logged_in_button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is generated and escaped above. ?>
				</div>
			</section>
			<?php

			return trim( ob_get_clean() );
		}

		$form_markup = trim( do_shortcode( $shortcode ) );

		if ( '' === $form_markup || $form_markup === $shortcode ) {
			return '';
		}

		$form_markup = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $form_markup );
		$form_markup = is_string( $form_markup ) ? trim( $form_markup ) : '';

		if ( '' === $form_markup ) {
			return '';
		}

		ob_start();
		?>
		<section class="site-footer__newsletter" aria-labelledby="site-footer-newsletter-title">
			<h2 id="site-footer-newsletter-title" class="site-footer__newsletter-title">
				<?php esc_html_e( 'Tilaa uutiskirje', 'rytkoset-theme' ); ?>
			</h2>
			<p class="site-footer__newsletter-text">
				<?php esc_html_e( 'Saat ajankohtaiset tiedot sukuseuran uutisista ja tapahtumista sähköpostiisi.', 'rytkoset-theme' ); ?>
			</p>
			<div class="site-footer__newsletter-form">
				<?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AcyMailing renders the subscription form markup. ?>
			</div>
		</section>
		<?php

		return trim( ob_get_clean() );
	}
}
