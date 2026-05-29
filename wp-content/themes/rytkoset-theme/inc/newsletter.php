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

if ( ! function_exists( 'rytkoset_theme_get_newsletter_list_ids' ) ) {
	/**
	 * Returns the newsletter target list IDs from the configured footer form.
	 *
	 * @return int[]
	 */
	function rytkoset_theme_get_newsletter_list_ids() {
		$shortcode = rytkoset_theme_get_newsletter_shortcode();

		if ( '' === $shortcode || ! shortcode_exists( 'acymailing_form_shortcode' ) ) {
			return array();
		}

		$form_id = rytkoset_theme_get_newsletter_form_id( $shortcode );

		return rytkoset_theme_get_newsletter_form_list_ids( $form_id );
	}
}

if ( ! function_exists( 'rytkoset_theme_email_has_newsletter_subscription' ) ) {
	/**
	 * Checks whether an email address is already actively subscribed to the newsletter list.
	 *
	 * @param string $email    Email address.
	 * @param int[]  $list_ids Optional AcyMailing list IDs. Defaults to the configured newsletter lists.
	 * @return bool
	 */
	function rytkoset_theme_email_has_newsletter_subscription( $email, $list_ids = array() ) {
		$email = sanitize_email( $email );

		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		if ( empty( $list_ids ) ) {
			$list_ids = rytkoset_theme_get_newsletter_list_ids();
		}

		$list_ids = array_values( array_unique( array_filter( array_map( 'absint', $list_ids ) ) ) );

		if ( empty( $list_ids ) ) {
			return false;
		}

		global $wpdb;

		$user_table   = $wpdb->prefix . 'acym_user';
		$list_table   = $wpdb->prefix . 'acym_user_has_list';
		$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		$query_args   = array_merge( array( $email ), $list_ids );
		$query        = $wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$user_table} AS acym_user
			 INNER JOIN {$list_table} AS acym_list
				ON acym_list.user_id = acym_user.id
			 WHERE acym_user.email = %s
				AND acym_list.status = 1
				AND acym_list.list_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and placeholders are generated internally.
			$query_args
		);

		return (int) $wpdb->get_var( $query ) > 0;
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

if ( ! function_exists( 'rytkoset_theme_user_has_newsletter_subscription' ) ) {
	/**
	 * Checks whether a WordPress user is already subscribed to the newsletter list.
	 *
	 * @param int   $user_id  WordPress user ID.
	 * @param int[] $list_ids Optional AcyMailing list IDs. Defaults to the configured newsletter lists.
	 * @return bool
	 */
	function rytkoset_theme_user_has_newsletter_subscription( $user_id, $list_ids = array() ) {
		$user_id = absint( $user_id );

		if ( 0 === $user_id ) {
			return false;
		}

		if ( empty( $list_ids ) ) {
			$list_ids = rytkoset_theme_get_newsletter_list_ids();
		}

		$list_ids = array_values( array_unique( array_filter( array_map( 'absint', $list_ids ) ) ) );

		if ( empty( $list_ids ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
			return false;
		}

		global $wpdb;

		$user_table   = $wpdb->prefix . 'acym_user';
		$list_table   = $wpdb->prefix . 'acym_user_has_list';
		$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		$query_args   = array_merge( array( $user_id, sanitize_email( $user->user_email ) ), $list_ids );
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

if ( ! function_exists( 'rytkoset_theme_log_newsletter_error' ) ) {
	/**
	 * Logs newsletter integration errors without personal data.
	 *
	 * @param string $source Source workflow.
	 * @param string $message Error message.
	 * @return void
	 */
	function rytkoset_theme_log_newsletter_error( $source, $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$message = (string) preg_replace(
				'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
				'[email redacted]',
				(string) $message
			);

			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[rytkoset-newsletter] source=%s message=%s',
					sanitize_key( $source ),
					sanitize_text_field( $message )
				)
			);
		}
	}
}

if ( ! function_exists( 'rytkoset_theme_get_newsletter_error_message' ) ) {
	/**
	 * Builds a safe AcyMailing error message for return values and logging.
	 *
	 * @param mixed  $errors   AcyMailing error data.
	 * @param string $fallback Fallback message.
	 * @return string
	 */
	function rytkoset_theme_get_newsletter_error_message( $errors, $fallback ) {
		if ( empty( $errors ) ) {
			return $fallback;
		}

		$message = implode( '; ', array_map( 'wp_strip_all_tags', (array) $errors ) );

		return (string) preg_replace(
			'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
			'[email redacted]',
			$message
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_subscribe_email_to_newsletter' ) ) {
	/**
	 * Subscribes an email address to the configured AcyMailing newsletter list.
	 *
	 * @param string $email       Email address.
	 * @param string $source      Source workflow.
	 * @param int    $cms_user_id Optional WordPress user ID.
	 * @return true|WP_Error
	 */
	function rytkoset_theme_subscribe_email_to_newsletter( $email, $source, $cms_user_id = 0 ) {
		$email       = sanitize_email( $email );
		$source      = sanitize_key( $source );
		$cms_user_id = absint( $cms_user_id );
		$list_ids    = rytkoset_theme_get_newsletter_list_ids();

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Uutiskirjeen tilausta ei voitu tehdä virheellisen sähköpostiosoitteen takia.', 'rytkoset-theme' ) );
		}

		if ( empty( $list_ids ) ) {
			return new WP_Error( 'missing_newsletter_list', __( 'Uutiskirjeen kohdelistaa ei ole määritetty.', 'rytkoset-theme' ) );
		}

		if ( rytkoset_theme_email_has_newsletter_subscription( $email, $list_ids ) ) {
			return true;
		}

		if ( ! class_exists( '\AcyMailing\Classes\UserClass' ) ) {
			return new WP_Error( 'acymailing_missing', __( 'AcyMailing ei ole käytettävissä.', 'rytkoset-theme' ) );
		}

		$user_class = new \AcyMailing\Classes\UserClass();
		$user_class->checkVisitor = false;

		if ( function_exists( 'acym_setVar' ) ) {
			acym_setVar( 'acy_source', $source );
		}

		$subscriber = $user_class->getOneByEmail( $email );

		if ( empty( $subscriber ) ) {
			$subscriber = new stdClass();
			$subscriber->email = $email;
			$subscriber->source = $source;

			if ( $cms_user_id > 0 ) {
				$subscriber->cms_id = $cms_user_id;
			}

			$subscriber_id = $user_class->save( $subscriber );

			if ( empty( $subscriber_id ) ) {
				$message = rytkoset_theme_get_newsletter_error_message( $user_class->errors, __( 'Tilaajan tallennus epäonnistui.', 'rytkoset-theme' ) );
				rytkoset_theme_log_newsletter_error( $source, $message );

				return new WP_Error( 'acymailing_save_failed', $message );
			}
		} else {
			$subscriber_id = absint( $subscriber->id );
		}

		if ( $cms_user_id > 0 && ! empty( $subscriber_id ) && empty( $subscriber->cms_id ) ) {
			global $wpdb;

			$wpdb->update(
				$wpdb->prefix . 'acym_user',
				array( 'cms_id' => $cms_user_id ),
				array( 'id' => $subscriber_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$subscribed = $user_class->subscribe( array( $subscriber_id ), $list_ids );

		if ( ! $subscribed && ! rytkoset_theme_email_has_newsletter_subscription( $email, $list_ids ) ) {
			$message = rytkoset_theme_get_newsletter_error_message( $user_class->errors, __( 'Listalle lisääminen epäonnistui.', 'rytkoset-theme' ) );
			rytkoset_theme_log_newsletter_error( $source, $message );

			return new WP_Error( 'acymailing_subscribe_failed', $message );
		}

		return true;
	}
}

if ( ! function_exists( 'rytkoset_theme_should_show_newsletter_opt_in' ) ) {
	/**
	 * Returns whether a generic newsletter opt-in should be shown.
	 *
	 * @return bool
	 */
	function rytkoset_theme_should_show_newsletter_opt_in() {
		$list_ids = rytkoset_theme_get_newsletter_list_ids();

		if ( empty( $list_ids ) ) {
			return false;
		}

		if ( is_user_logged_in() && rytkoset_theme_current_user_has_newsletter_subscription( $list_ids ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'rytkoset_theme_render_newsletter_opt_in_checkbox' ) ) {
	/**
	 * Renders a reusable newsletter opt-in checkbox.
	 *
	 * @param string $field_id Field ID.
	 * @param string $field_name Field name.
	 * @param string $class_name Wrapper class.
	 * @return void
	 */
	function rytkoset_theme_render_newsletter_opt_in_checkbox( $field_id, $field_name, $class_name ) {
		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<label for="<?php echo esc_attr( $field_id ); ?>">
				<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" value="1" />
				<?php esc_html_e( 'Tilaa uutiskirje', 'rytkoset-theme' ); ?>
			</label>
		</div>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_render_registration_newsletter_opt_in' ) ) {
	/**
	 * Renders newsletter opt-in on the WordPress registration form.
	 *
	 * @return void
	 */
	function rytkoset_theme_render_registration_newsletter_opt_in() {
		if ( empty( rytkoset_theme_get_newsletter_list_ids() ) ) {
			return;
		}

		$mail_icon  = function_exists( 'rytkoset_theme_inline_icon' ) ? rytkoset_theme_inline_icon( 'mail', 'ui' ) : '';
		$check_icon = function_exists( 'rytkoset_theme_inline_icon' ) ? rytkoset_theme_inline_icon( 'check', 'ui' ) : '';
		?>
		<div class="rytkoset-login-newsletter-opt-in">
			<label class="newsletter" for="rytkoset-newsletter-opt-in">
				<input id="rytkoset-newsletter-opt-in" type="checkbox" name="rytkoset_newsletter_opt_in" value="1" />
				<span class="newsletter__icon"><?php echo $mail_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted inline SVG from theme icon set. ?></span>
				<span class="newsletter__text">
					<span class="newsletter__head">
						<span class="newsletter__title"><?php esc_html_e( 'Tilaa sukuseuran uutiskirje', 'rytkoset-theme' ); ?></span>
						<span class="newsletter__opt"><?php esc_html_e( 'Vapaaehtoinen', 'rytkoset-theme' ); ?></span>
					</span>
					<span class="newsletter__desc"><?php esc_html_e( 'Saat ajankohtaiset uutiset, tapahtumat ja sukukokouskutsut suoraan sähköpostiisi. Voit perua tilauksen koska tahansa.', 'rytkoset-theme' ); ?></span>
				</span>
				<span class="newsletter__check"><?php echo $check_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted inline SVG from theme icon set. ?></span>
			</label>
		</div>
		<?php
	}
}
add_action( 'register_form', 'rytkoset_theme_render_registration_newsletter_opt_in' );

if ( ! function_exists( 'rytkoset_theme_handle_registration_newsletter_opt_in' ) ) {
	/**
	 * Subscribes a newly registered user when they selected the opt-in checkbox.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	function rytkoset_theme_handle_registration_newsletter_opt_in( $user_id ) {
		if ( empty( $_POST['rytkoset_newsletter_opt_in'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['rytkoset_newsletter_opt_in'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Handled by WordPress registration flow.
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$result = rytkoset_theme_subscribe_email_to_newsletter( $user->user_email, 'registration', $user_id );

		if ( is_wp_error( $result ) ) {
			rytkoset_theme_log_newsletter_error( 'registration', $result->get_error_message() );
		}
	}
}
add_action( 'user_register', 'rytkoset_theme_handle_registration_newsletter_opt_in' );

if ( ! function_exists( 'rytkoset_theme_register_checkout_newsletter_opt_in' ) ) {
	/**
	 * Registers newsletter opt-in for WooCommerce Checkout Block.
	 *
	 * @return void
	 */
	function rytkoset_theme_register_checkout_newsletter_opt_in() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! rytkoset_theme_should_show_newsletter_opt_in() ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'rytkoset/newsletter_opt_in',
				'label'             => __( 'Tilaa uutiskirje', 'rytkoset-theme' ),
				'location'          => 'order',
				'type'              => 'checkbox',
				'required'          => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}
}
add_action( 'woocommerce_init', 'rytkoset_theme_register_checkout_newsletter_opt_in' );

if ( ! function_exists( 'rytkoset_theme_handle_checkout_newsletter_opt_in' ) ) {
	/**
	 * Handles WooCommerce Checkout Block newsletter opt-in after order processing.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	function rytkoset_theme_handle_checkout_newsletter_opt_in( $order ) {
		if ( ! $order instanceof WC_Order || ! function_exists( 'rytkoset_theme_get_order_additional_checkout_field_bool' ) ) {
			return;
		}

		if ( ! rytkoset_theme_get_order_additional_checkout_field_bool( $order, 'rytkoset/newsletter_opt_in' ) ) {
			return;
		}

		$result = rytkoset_theme_subscribe_email_to_newsletter(
			$order->get_billing_email(),
			'woocommerce_checkout',
			$order->get_user_id()
		);

		if ( is_wp_error( $result ) ) {
			rytkoset_theme_log_newsletter_error( 'woocommerce_checkout', $result->get_error_message() );
		}
	}
}
add_action( 'woocommerce_store_api_checkout_order_processed', 'rytkoset_theme_handle_checkout_newsletter_opt_in', 30 );

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
		<p class="site-prefooter__disclaimer">
			<?php esc_html_e( 'Tilaus tehdään tilillesi · voit perua koska vain', 'rytkoset-theme' ); ?>
		</p>
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

if ( ! function_exists( 'rytkoset_theme_get_footer_newsletter_form' ) ) {
	/**
	 * Builds the inner newsletter form markup for the pre-footer.
	 *
	 * Returns only the form element (no wrapping section/heading), so the
	 * pre-footer partials can place it inside the large card or the compact row.
	 * Returns an empty string when there is nothing to show — newsletter not
	 * configured, or the current logged-in user is already an active subscriber
	 * (the pre-footer band is hidden entirely in that case).
	 *
	 * @return string
	 */
	function rytkoset_theme_get_footer_newsletter_form() {
		$shortcode = rytkoset_theme_get_newsletter_shortcode();

		if ( '' === $shortcode || ! shortcode_exists( 'acymailing_form_shortcode' ) ) {
			return '';
		}

		$form_id  = rytkoset_theme_get_newsletter_form_id( $shortcode );
		$list_ids = rytkoset_theme_get_newsletter_form_list_ids( $form_id );

		// Active subscriber: no form, so the pre-footer band is omitted.
		if ( rytkoset_theme_current_user_has_newsletter_subscription( $list_ids ) ) {
			return '';
		}

		// Logged-in non-subscriber: single subscribe button.
		$logged_in_button_markup = rytkoset_theme_get_logged_in_newsletter_button_markup( $form_id, $list_ids );

		if ( '' !== $logged_in_button_markup ) {
			return $logged_in_button_markup;
		}

		// Guest: full AcyMailing subscription form (email + consent + button).
		$form_markup = trim( do_shortcode( $shortcode ) );

		if ( '' === $form_markup || $form_markup === $shortcode ) {
			return '';
		}

		$form_markup = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $form_markup );
		$form_markup = is_string( $form_markup ) ? trim( $form_markup ) : '';

		return $form_markup;
	}
}
