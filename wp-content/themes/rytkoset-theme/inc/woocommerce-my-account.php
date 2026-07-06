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

if ( ! function_exists( 'rytkoset_theme_get_account_menu_item_icon' ) ) {
	/**
	 * Palauttaa My Account -valikkokohdan UI-ikonin nimen.
	 *
	 * @param string $endpoint WooCommercen endpoint-avain (esim. 'orders').
	 * @return string Ikonin tiedostonimi assets/icons/ui/-kansiossa, tai '' jos ei ikonia.
	 */
	function rytkoset_theme_get_account_menu_item_icon( $endpoint ) {
		$icons = array(
			'dashboard'       => 'home',
			'orders'          => 'package',
			'downloads'       => 'download',
			'edit-address'    => 'map-pin',
			'edit-account'    => 'user',
			'payment-methods' => 'credit-card',
			'customer-logout' => 'log-out',
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
