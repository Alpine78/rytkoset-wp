<?php
/**
 * Oma tili (WooCommerce My Account) -uudistuksen apufunktiot (#496).
 *
 * Sisältää sivupalkin tilivalikon ikonimäppäyksen, avatar-nimikirjaimet,
 * tilaustaulukon tilachipit ja hallintapaneelin pikakorttien tietohaut.
 * Ulkoasu: assets/css/account.css; merkkaus: woocommerce/myaccount/-templatet.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_account_login_redirect_excluded_endpoints' ) ) {
	/**
	 * Palauttaa My Account -endpointit, joilta kirjautumatonta ei ohjata wp-loginiin.
	 *
	 * lost-password: WooCommercen salasanan palautus (myös reset-lomake
	 * show-reset-form-parametrilla) elää tällä endpointilla ilman kirjautumista —
	 * ohjaus katkaisisi sähköpostin palautuslinkin. customer-logout: WooCommerce
	 * hoitaa kirjautumattoman uloskirjautujan ohjauksen itse.
	 *
	 * @return string[] Endpoint-avaimet.
	 */
	function rytkoset_theme_get_account_login_redirect_excluded_endpoints() {
		return apply_filters(
			'rytkoset_theme_account_login_redirect_excluded_endpoints',
			array( 'lost-password', 'customer-logout' )
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_account_needs_login_redirect' ) ) {
	/**
	 * Päättää, ohjataanko kävijä Oma tili -sivulta wp-login-kirjautumissivulle (#512).
	 *
	 * @param bool     $is_logged_in       Onko käyttäjä kirjautunut.
	 * @param bool     $is_account_page    Onko nykyinen sivu My Account -sivu.
	 * @param string   $current_endpoint   Nykyinen My Account -endpoint-avain, tai '' jos ei endpointtia.
	 * @param string[] $excluded_endpoints Endpointit, joilta ei ohjata.
	 * @return bool
	 */
	function rytkoset_theme_account_needs_login_redirect( $is_logged_in, $is_account_page, $current_endpoint, $excluded_endpoints ) {
		if ( $is_logged_in || ! $is_account_page ) {
			return false;
		}

		if ( '' === (string) $current_endpoint ) {
			return true;
		}

		return ! in_array( (string) $current_endpoint, array_map( 'strval', (array) $excluded_endpoints ), true );
	}
}

if ( ! function_exists( 'rytkoset_theme_redirect_logged_out_account_page' ) ) {
	/**
	 * Ohjaa kirjautumattoman Oma tili -kävijän uudistetulle kirjautumissivulle (#512).
	 *
	 * WooCommercen tyylittelemättömän Kirjaudu/Rekisteröidy-lomakkeen sijaan
	 * kävijä näkee teeman wp-login-uudistuksen (inc/login.php). Alkuperäinen
	 * osoite (myös syvälinkit, esim. /oma-tili/tilaukset/) kulkee redirect_to-
	 * parametrina, joten kirjautumisen jälkeen matka jatkuu oikeasta paikasta.
	 */
	function rytkoset_theme_redirect_logged_out_account_page() {
		if ( ! function_exists( 'is_account_page' ) || ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}

		$excluded_endpoints = rytkoset_theme_get_account_login_redirect_excluded_endpoints();
		$current_endpoint   = '';

		foreach ( (array) $excluded_endpoints as $endpoint ) {
			if ( is_wc_endpoint_url( $endpoint ) ) {
				$current_endpoint = (string) $endpoint;
				break;
			}
		}

		if ( ! rytkoset_theme_account_needs_login_redirect( is_user_logged_in(), is_account_page(), $current_endpoint, $excluded_endpoints ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$redirect    = '' !== $request_uri
			? home_url( $request_uri )
			: ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ) );

		wp_safe_redirect( wp_login_url( $redirect ) );
		exit;
	}
}
add_action( 'template_redirect', 'rytkoset_theme_redirect_logged_out_account_page' );

if ( ! function_exists( 'rytkoset_theme_get_account_menu_item_icon' ) ) {
	/**
	 * Palauttaa My Account -valikkokohdan UI-ikonin nimen.
	 *
	 * @param string $endpoint WooCommercen endpoint-avain (esim. 'orders').
	 * @return string Ikonin tiedostonimi assets/icons/ui/-kansiossa, tai '' jos ei ikonia.
	 */
	function rytkoset_theme_get_account_menu_item_icon( $endpoint ) {
		$icons = array(
			'dashboard'           => 'home',
			'orders'              => 'package',
			'downloads'           => 'download',
			'edit-address'        => 'map-pin',
			'rytkoset_newsletter' => 'mail',
			'edit-account'        => 'user',
			'payment-methods'     => 'credit-card',
			'customer-logout'     => 'log-out',
		);

		return isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_avatar_initials' ) ) {
	/**
	 * Muodostaa avatar-nimikirjaimet näyttönimestä (esim. "Ilkka Rytkönen" → "IR").
	 *
	 * @param string $display_name Käyttäjän näyttönimi.
	 * @return string Enintään kaksi isoa alkukirjainta, tai '' jos nimi on tyhjä.
	 */
	function rytkoset_theme_get_account_avatar_initials( $display_name ) {
		$words    = preg_split( '/\s+/u', trim( (string) $display_name ), -1, PREG_SPLIT_NO_EMPTY );
		$initials = '';

		if ( ! is_array( $words ) ) {
			return '';
		}

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		}

		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials ) : strtoupper( $initials );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_avatar_image_url' ) ) {
	/**
	 * Palauttaa käyttäjän Gravatar-kuvan URL:n, jos kuva on oikeasti olemassa.
	 *
	 * Gravatar palauttaa oletuskuvan myös tuntemattomille osoitteille, joten
	 * olemassaolo tarkistetaan d=404-parametrilla: HEAD-pyyntö palauttaa 200
	 * vain, jos osoitteelle on ladattu oma kuva. Tulos välimuistitetaan
	 * transientiin, jotta tarkistus ei toistu jokaisella sivulatauksella.
	 * Nimikirjaimet (rytkoset_theme_get_account_avatar_initials()) toimivat
	 * fallbackina, kun kuvaa ei ole.
	 *
	 * @param WP_User $user Käyttäjä.
	 * @return string Gravatar-URL, tai '' jos kuvaa ei ole tai avatarit on kytketty pois.
	 */
	function rytkoset_theme_get_account_avatar_image_url( $user ) {
		if ( ! ( $user instanceof WP_User ) || empty( $user->user_email ) ) {
			return '';
		}

		if ( ! get_option( 'show_avatars' ) || ! function_exists( 'get_avatar_url' ) ) {
			return '';
		}

		$avatar_url = get_avatar_url(
			$user->ID,
			array(
				'size'    => 96,
				'default' => '404',
			)
		);

		if ( ! is_string( $avatar_url ) || '' === $avatar_url ) {
			return '';
		}

		$cache_key = 'rytkoset_gravatar_' . md5( strtolower( trim( $user->user_email ) ) );
		$cached    = get_transient( $cache_key );

		if ( 'yes' === $cached ) {
			return $avatar_url;
		}

		if ( 'no' === $cached ) {
			return '';
		}

		$response     = wp_remote_head( $avatar_url, array( 'timeout' => 3 ) );
		$status       = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$has_gravatar = 200 === $status;

		set_transient( $cache_key, $has_gravatar ? 'yes' : 'no', DAY_IN_SECONDS );

		return $has_gravatar ? $avatar_url : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_order_status_chip_variant' ) ) {
	/**
	 * Mäppää WooCommerce-tilaustilan tilachipin värivarianttiin.
	 *
	 * @param string $status Tilauksen tila ilman wc--etuliitettä (esim. 'completed').
	 * @return string Varianttiluokan pääte: done | pending | cancelled | neutral.
	 */
	function rytkoset_theme_get_order_status_chip_variant( $status ) {
		$variants = array(
			'completed'  => 'done',
			'processing' => 'pending',
			'pending'    => 'pending',
			'on-hold'    => 'pending',
			'cancelled'  => 'cancelled',
			'failed'     => 'cancelled',
			'refunded'   => 'neutral',
		);

		return isset( $variants[ $status ] ) ? $variants[ $status ] : 'neutral';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_order_status_chip' ) ) {
	/**
	 * Palauttaa tilauksen tilan chippinä (span-merkkaus).
	 *
	 * @param WC_Order $order Tilaus.
	 * @return string Chipin HTML, tai '' jos syöte ei ole tilaus.
	 */
	function rytkoset_theme_get_order_status_chip( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) {
			return '';
		}

		$status = (string) $order->get_status();
		$label  = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : ucfirst( $status );

		return sprintf(
			'<span class="rytkoset-status-chip rytkoset-status-chip--%s">%s</span>',
			esc_attr( rytkoset_theme_get_order_status_chip_variant( $status ) ),
			esc_html( $label )
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_render_order_status_chip_column' ) ) {
	/**
	 * Tulostaa tilaustaulukon Tila-sarakkeen sisällön chippinä.
	 *
	 * WooCommercen orders.php käyttää tätä hookia oman tekstitulosteensa
	 * sijasta, kun hookilla on callback (has_action-tarkistus templatessa).
	 *
	 * @param WC_Order $order Tilaus.
	 */
	function rytkoset_theme_render_order_status_chip_column( $order ) {
		echo wp_kses_post( rytkoset_theme_get_order_status_chip( $order ) );
	}
}
add_action( 'woocommerce_my_account_my_orders_column_order-status', 'rytkoset_theme_render_order_status_chip_column' );

if ( ! function_exists( 'rytkoset_theme_render_account_username_note' ) ) {
	/**
	 * Tulostaa käyttäjätunnuksen vain luku -tietona Tilin tiedot -lomakkeen alkuun.
	 *
	 * Foorumilta perityillä käyttäjillä käyttäjätunnus voi poiketa täysin
	 * näyttönimestä, eikä sitä muuten näe ilman wp-adminia. Tunnus näkyy
	 * vain käyttäjälle itselleen kirjautuneena.
	 */
	function rytkoset_theme_render_account_username_note() {
		$user = wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		echo '<p class="rytkoset-account-username">';
		printf(
			/* translators: %s: käyttäjätunnus (user_login). */
			wp_kses( __( 'Käyttäjätunnus: <strong>%s</strong>. Tunnusta ei voi vaihtaa. Voit kirjautua myös sähköpostiosoitteellasi.', 'rytkoset-theme' ), array( 'strong' => array() ) ),
			esc_html( $user->user_login )
		);
		echo '</p>';
	}
}
add_action( 'woocommerce_edit_account_form_start', 'rytkoset_theme_render_account_username_note' );

if ( ! function_exists( 'rytkoset_theme_get_account_newsletter_endpoint' ) ) {
	/**
	 * Palauttaa uutiskirjeen hallinnan My Account -endpoint-avaimen.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_newsletter_endpoint() {
		return 'rytkoset_newsletter';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_newsletter_endpoint_slug' ) ) {
	/**
	 * Palauttaa uutiskirjeen hallinnan URL-slugin.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_newsletter_endpoint_slug() {
		return 'uutiskirje';
	}
}

if ( ! function_exists( 'rytkoset_theme_register_account_newsletter_endpoint_query_var' ) ) {
	/**
	 * Rekisteröi uutiskirjeen hallinnan WooCommerce My Account -endpointiksi.
	 *
	 * @param array<string, string> $vars WooCommerce-endpointtien query var -muuttujat.
	 * @return array<string, string>
	 */
	function rytkoset_theme_register_account_newsletter_endpoint_query_var( $vars ) {
		$vars[ rytkoset_theme_get_account_newsletter_endpoint() ] = rytkoset_theme_get_account_newsletter_endpoint_slug();

		return $vars;
	}
}
add_filter( 'woocommerce_get_query_vars', 'rytkoset_theme_register_account_newsletter_endpoint_query_var' );

if ( ! function_exists( 'rytkoset_theme_get_account_newsletter_endpoint_rewrite_version' ) ) {
	/**
	 * Rewrite-flushin vartioarvo uutiskirje-endpointille.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_newsletter_endpoint_rewrite_version() {
		return rytkoset_theme_get_account_newsletter_endpoint() . ':' . rytkoset_theme_get_account_newsletter_endpoint_slug() . ':v1';
	}
}

if ( ! function_exists( 'rytkoset_theme_account_newsletter_endpoint_rewrite_rules_exist' ) ) {
	/**
	 * Tarkistaa, löytyykö uutiskirje-endpoint jo tallennetuista rewrite-säännöistä.
	 *
	 * @return bool
	 */
	function rytkoset_theme_account_newsletter_endpoint_rewrite_rules_exist() {
		$rules = get_option( 'rewrite_rules', array() );

		if ( ! is_array( $rules ) ) {
			return false;
		}

		$endpoint_slug = rytkoset_theme_get_account_newsletter_endpoint_slug();

		foreach ( array_keys( $rules ) as $regex ) {
			if ( false !== strpos( (string) $regex, $endpoint_slug ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'rytkoset_theme_maybe_flush_account_newsletter_endpoint' ) ) {
	/**
	 * Flushaa rewrite-säännöt kerran, kun uutiskirje-endpoint on lisätty.
	 *
	 * @return void
	 */
	function rytkoset_theme_maybe_flush_account_newsletter_endpoint() {
		if (
			rytkoset_theme_get_account_newsletter_endpoint_rewrite_version() === get_option( 'rytkoset_theme_account_newsletter_endpoint_flushed' )
			&& rytkoset_theme_account_newsletter_endpoint_rewrite_rules_exist()
		) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'rytkoset_theme_account_newsletter_endpoint_flushed', rytkoset_theme_get_account_newsletter_endpoint_rewrite_version() );
	}
}
add_action( 'init', 'rytkoset_theme_maybe_flush_account_newsletter_endpoint', 99 );

if ( ! function_exists( 'rytkoset_theme_flush_account_newsletter_endpoint_on_activation' ) ) {
	/**
	 * Flushaa uutiskirje-endpointin rewrite-säännöt teeman aktivoinnissa.
	 *
	 * @return void
	 */
	function rytkoset_theme_flush_account_newsletter_endpoint_on_activation() {
		delete_option( 'rytkoset_theme_account_newsletter_endpoint_flushed' );
		flush_rewrite_rules( false );
		update_option( 'rytkoset_theme_account_newsletter_endpoint_flushed', rytkoset_theme_get_account_newsletter_endpoint_rewrite_version() );
	}
}
add_action( 'after_switch_theme', 'rytkoset_theme_flush_account_newsletter_endpoint_on_activation' );

if ( ! function_exists( 'rytkoset_theme_add_account_newsletter_menu_item' ) ) {
	/**
	 * Lisää Uutiskirje-kohdan Oma tili -valikkoon ennen Tilin tietoja.
	 *
	 * @param array<string, string> $items WooCommerce My Account -valikkokohdat.
	 * @return array<string, string>
	 */
	function rytkoset_theme_add_account_newsletter_menu_item( $items ) {
		$endpoint = rytkoset_theme_get_account_newsletter_endpoint();
		$label    = __( 'Uutiskirje', 'rytkoset-theme' );

		unset( $items[ $endpoint ] );

		$inserted = false;
		$updated  = array();

		foreach ( $items as $key => $item_label ) {
			if ( ! $inserted && in_array( $key, array( 'edit-account', 'customer-logout' ), true ) ) {
				$updated[ $endpoint ] = $label;
				$inserted             = true;
			}

			$updated[ $key ] = $item_label;
		}

		if ( ! $inserted ) {
			$updated[ $endpoint ] = $label;
		}

		return $updated;
	}
}
add_filter( 'woocommerce_account_menu_items', 'rytkoset_theme_add_account_newsletter_menu_item' );

if ( ! function_exists( 'rytkoset_theme_get_account_newsletter_status' ) ) {
	/**
	 * Palauttaa kirjautuneen käyttäjän uutiskirjetilan endpointin käyttöön.
	 *
	 * @param int   $user_id  WordPress-käyttäjän ID. Oletus: nykyinen käyttäjä.
	 * @param int[] $list_ids AcyMailing-lista-ID:t. Oletus: footer-lomakkeen listat.
	 * @return string subscribed|not_subscribed|missing_list|invalid_user|invalid_email
	 */
	function rytkoset_theme_get_account_newsletter_status( $user_id = 0, $list_ids = array() ) {
		$user_id = absint( $user_id );
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( 0 === $user_id ) {
			return 'invalid_user';
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
			return 'invalid_email';
		}

		if ( empty( $list_ids ) && function_exists( 'rytkoset_theme_get_newsletter_list_ids' ) ) {
			$list_ids = rytkoset_theme_get_newsletter_list_ids();
		}

		$list_ids = function_exists( 'rytkoset_theme_normalize_newsletter_list_ids' )
			? rytkoset_theme_normalize_newsletter_list_ids( $list_ids )
			: array_values( array_unique( array_filter( array_map( 'absint', (array) $list_ids ) ) ) );

		if ( empty( $list_ids ) ) {
			return 'missing_list';
		}

		return function_exists( 'rytkoset_theme_user_has_newsletter_subscription' ) && rytkoset_theme_user_has_newsletter_subscription( $user_id, $list_ids )
			? 'subscribed'
			: 'not_subscribed';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_newsletter_url' ) ) {
	/**
	 * Palauttaa uutiskirje-endpointin URL:n.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_newsletter_url() {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( rytkoset_theme_get_account_newsletter_endpoint() );
		}

		return home_url( '/oma-tili/' . rytkoset_theme_get_account_newsletter_endpoint_slug() . '/' );
	}
}

if ( ! function_exists( 'rytkoset_theme_handle_account_newsletter_submit' ) ) {
	/**
	 * Käsittelee Oma tili > Uutiskirje -lomakkeen lähetyksen.
	 *
	 * @return void
	 */
	function rytkoset_theme_handle_account_newsletter_submit() {
		if ( empty( $_POST['rytkoset_account_newsletter_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['rytkoset_account_newsletter_action'] ) );

		if ( ! in_array( $action, array( 'subscribe', 'unsubscribe' ), true ) ) {
			wc_add_notice( __( 'Uutiskirjeen tilausta ei voitu muuttaa.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'rytkoset_account_newsletter_' . $action ) ) {
			wc_add_notice( __( 'Uutiskirjeen tilausta ei voitu vahvistaa. Yritä uudelleen.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$user  = wp_get_current_user();
		$email = $user instanceof WP_User ? sanitize_email( $user->user_email ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			wc_add_notice( __( 'Tililläsi ei ole kelvollista sähköpostiosoitetta.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$result = 'subscribe' === $action
			? rytkoset_theme_subscribe_email_to_newsletter( $email, 'my_account', get_current_user_id() )
			: rytkoset_theme_unsubscribe_email_from_newsletter( $email, 'my_account' );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		} elseif ( 'subscribe' === $action ) {
			wc_add_notice( __( 'Uutiskirjeen tilaus on nyt voimassa.', 'rytkoset-theme' ), 'success' );
		} else {
			wc_add_notice( __( 'Uutiskirjeen tilaus on peruttu.', 'rytkoset-theme' ), 'success' );
		}

		wp_safe_redirect( rytkoset_theme_get_account_newsletter_url() );
		exit;
	}
}
add_action( 'template_redirect', 'rytkoset_theme_handle_account_newsletter_submit' );

if ( ! function_exists( 'rytkoset_theme_render_account_newsletter_endpoint' ) ) {
	/**
	 * Renderöi Oma tili > Uutiskirje -endpointin sisällön.
	 *
	 * @return void
	 */
	function rytkoset_theme_render_account_newsletter_endpoint() {
		$user = wp_get_current_user();

		echo '<h2 class="rytkoset-account-h2">' . esc_html__( 'Uutiskirje', 'rytkoset-theme' ) . '</h2>';
		echo '<p class="rytkoset-account-lede">' . esc_html__( 'Hallitse sukuseuran uutiskirjeen tilausta. Tilaus koskee vain omaa sähköpostiosoitettasi.', 'rytkoset-theme' ) . '</p>';

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			wc_print_notice( __( 'Kirjaudu sisään hallitaksesi uutiskirjeen tilausta.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$email = sanitize_email( $user->user_email );

		if ( '' === $email || ! is_email( $email ) ) {
			wc_print_notice( __( 'Tililläsi ei ole kelvollista sähköpostiosoitetta.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$list_ids = function_exists( 'rytkoset_theme_get_newsletter_list_ids' ) ? rytkoset_theme_get_newsletter_list_ids() : array();
		$status   = rytkoset_theme_get_account_newsletter_status( $user->ID, $list_ids );

		if ( 'missing_list' === $status ) {
			?>
			<div class="rytkoset-account-newsletter-card">
				<div class="rytkoset-account-newsletter-card__head">
					<span class="rytkoset-account-newsletter-card__icon" aria-hidden="true">
						<?php echo rytkoset_theme_inline_icon( 'mail', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta. ?>
					</span>
					<div>
						<h3 class="rytkoset-account-newsletter-card__title"><?php esc_html_e( 'Uutiskirje ei ole vielä käytettävissä', 'rytkoset-theme' ); ?></h3>
						<p class="rytkoset-account-newsletter-card__email"><?php echo esc_html( $email ); ?></p>
					</div>
				</div>
				<div class="rytkoset-account-newsletter-card__body">
					<p><?php esc_html_e( 'Uutiskirjeen kohdelistaa ei ole määritetty, joten tilausta ei voi muuttaa tästä näkymästä juuri nyt.', 'rytkoset-theme' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$is_subscribed = 'subscribed' === $status;
		$action        = $is_subscribed ? 'unsubscribe' : 'subscribe';
		$icon          = $is_subscribed ? 'check' : 'mail';
		$title         = $is_subscribed
			? __( 'Uutiskirje on tilattu', 'rytkoset-theme' )
			: __( 'Et ole tilannut uutiskirjettä', 'rytkoset-theme' );
		$description   = $is_subscribed
			? __( 'Saat sukuseuran uutiskirjeen tähän osoitteeseen. Voit perua tilauksen milloin tahansa. Peruminen koskee vain yleistä uutiskirjettä.', 'rytkoset-theme' )
			: __( 'Uutiskirjeessä kerromme sukukokouksista, tapahtumista ja julkaisuista muutaman kerran vuodessa.', 'rytkoset-theme' );
		$button_label  = $is_subscribed
			? __( 'Peru tilaus', 'rytkoset-theme' )
			: __( 'Tilaa uutiskirje', 'rytkoset-theme' );
		?>
		<div class="rytkoset-account-newsletter-card rytkoset-account-newsletter-card--<?php echo $is_subscribed ? 'subscribed' : 'unsubscribed'; ?>">
			<div class="rytkoset-account-newsletter-card__head">
				<span class="rytkoset-account-newsletter-card__icon <?php echo $is_subscribed ? 'rytkoset-account-newsletter-card__icon--success' : ''; ?>" aria-hidden="true">
					<?php echo rytkoset_theme_inline_icon( $icon, 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta. ?>
				</span>
				<div>
					<h3 class="rytkoset-account-newsletter-card__title"><?php echo esc_html( $title ); ?></h3>
					<p class="rytkoset-account-newsletter-card__email"><?php echo esc_html( $email ); ?></p>
				</div>
			</div>
			<div class="rytkoset-account-newsletter-card__body">
				<p><?php echo esc_html( $description ); ?></p>
				<form method="post" action="<?php echo esc_url( rytkoset_theme_get_account_newsletter_url() ); ?>" class="rytkoset-account-newsletter-card__form">
					<?php wp_nonce_field( 'rytkoset_account_newsletter_' . $action ); ?>
					<input type="hidden" name="rytkoset_account_newsletter_action" value="<?php echo esc_attr( $action ); ?>" />
					<button type="submit" class="button rytkoset-account-newsletter-card__button rytkoset-account-newsletter-card__button--<?php echo esc_attr( $action ); ?>">
						<?php echo esc_html( $button_label ); ?>
					</button>
				</form>
				<p class="rytkoset-account-newsletter-card__note"><?php esc_html_e( 'Muutos tallentuu heti ja näet vahvistuksen tällä sivulla.', 'rytkoset-theme' ); ?></p>
			</div>
		</div>
		<?php
	}
}
add_action( 'woocommerce_account_rytkoset_newsletter_endpoint', 'rytkoset_theme_render_account_newsletter_endpoint' );

if ( ! function_exists( 'rytkoset_theme_get_account_dashboard_orders_summary' ) ) {
	/**
	 * Hakee kirjautuneen käyttäjän tilausten yhteenvedon hallintapaneelin korttiin.
	 *
	 * @return array{count:int, latest:string} Tilausten määrä ja uusimman tilauksen
	 *                                         päiväys muotoiltuna, tai tyhjät arvot.
	 */
	function rytkoset_theme_get_account_dashboard_orders_summary() {
		$summary = array(
			'count'  => 0,
			'latest' => '',
		);

		if ( ! function_exists( 'wc_get_orders' ) || ! is_user_logged_in() ) {
			return $summary;
		}

		$orders = wc_get_orders(
			array(
				'customer' => get_current_user_id(),
				'limit'    => 1,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'paginate' => true,
				// WooCommerce Blocks lisää checkout-draft-luonnokset tilalistaan;
				// ilman rajausta ne näkyisivät asiakkaan tilauksina.
				'status'   => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ),
			)
		);

		if ( ! is_object( $orders ) || empty( $orders->orders ) ) {
			return $summary;
		}

		$summary['count'] = (int) $orders->total;

		$latest_order = $orders->orders[0];
		$date_created = is_object( $latest_order ) && method_exists( $latest_order, 'get_date_created' ) ? $latest_order->get_date_created() : null;

		if ( $date_created && function_exists( 'wc_format_datetime' ) ) {
			$summary['latest'] = wc_format_datetime( $date_created );
		}

		return $summary;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_recent_orders' ) ) {
	/**
	 * Hakee kirjautuneen käyttäjän tuoreimmat tilaukset hallintapaneelin listaan.
	 *
	 * @param int $limit Tilausten enimmäismäärä.
	 * @return array WC_Order-oliot uusimmasta vanhimpaan, tai tyhjä taulukko.
	 */
	function rytkoset_theme_get_account_recent_orders( $limit = 3 ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! is_user_logged_in() ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer' => get_current_user_id(),
				'limit'    => max( 1, (int) $limit ),
				'orderby'  => 'date',
				'order'    => 'DESC',
				// WooCommerce Blocks lisää checkout-draft-luonnokset tilalistaan;
				// ilman rajausta ne näkyisivät asiakkaan tilauksina.
				'status'   => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ),
			)
		);

		return is_array( $orders ) ? $orders : array();
	}
}
