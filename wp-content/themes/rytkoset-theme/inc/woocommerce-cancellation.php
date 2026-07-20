<?php
/**
 * Lainmukainen tilauksen peruutuspainike (EU:n kuluttajansuoja, voimaan 19.6.2026).
 *
 * Adds self-service cancellation for both account and guest orders: an account
 * action, an order-key-protected guest link in the order confirmation email,
 * a separate confirmation view, an immediate timestamped customer email and an
 * administrator notification for paid orders that require a manual refund.
 *
 * Tietovirta:
 *  - Painike (woocommerce_my_account_my_orders_actions) -> /tili/peruuta-tilaus/?order_id=..&_wpnonce=..
 *  - Vahvistussivu (woocommerce_account_rytkoset_cancel_order_endpoint) näyttää tilaustiedot
 *  - Lomakkeen lähetys käsitellään template_redirectissä:
 *      pending/on-hold -> status 'cancelled' heti, ellei tilaus sisällä poikkeustuotteita
 *      processing/completed/sekatilaus -> tilausmuistiinpano + admin-sähköposti, status ennallaan
 *  - Asiakas saa aina vahvistussähköpostin aikaleimalla.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Peruutusoikeuden aikaikkuna vuorokausina tilauksen luontihetkestä.
 *
 * @return int
 */
if ( ! function_exists( 'rytkoset_theme_get_order_cancellation_window_days' ) ) {
	function rytkoset_theme_get_order_cancellation_window_days() {
		/**
		 * Suodattaa peruutusoikeuden aikaikkunan (vrk).
		 *
		 * @param int $days Vuorokausien määrä.
		 */
		return (int) apply_filters( 'rytkoset_theme_order_cancellation_window_days', 14 );
	}
}

/**
 * Tilaustilat, joista asiakas voi pyytää peruutusta itsepalveluna.
 *
 * @return array<int, string>
 */
if ( ! function_exists( 'rytkoset_theme_get_cancellable_order_statuses' ) ) {
	function rytkoset_theme_get_cancellable_order_statuses() {
		/**
		 * Suodattaa peruutettavat tilaustilat (ilman wc- etuliitettä).
		 *
		 * @param array<int, string> $statuses Tilaustilat.
		 */
		return (array) apply_filters(
			'rytkoset_theme_cancellable_order_statuses',
			array( 'pending', 'on-hold', 'processing', 'completed' )
		);
	}
}

/**
 * Order statuses from which a physical-product return request can be submitted.
 *
 * Completed orders are included because the statutory return window starts when the customer
 * receives the goods, and the store does not record that receipt date automatically.
 *
 * @return array<int, string>
 */
if ( ! function_exists( 'rytkoset_theme_get_physical_product_return_request_statuses' ) ) {
	function rytkoset_theme_get_physical_product_return_request_statuses() {
		/**
		 * Filters statuses that allow a physical-product return request.
		 *
		 * @param array<int, string> $statuses Order statuses without the wc- prefix.
		 */
		return (array) apply_filters(
			'rytkoset_theme_physical_product_return_request_statuses',
			array( 'pending', 'on-hold', 'processing', 'completed' )
		);
	}
}

/**
 * Tilaustilat, joissa peruutus muuttaa statuksen heti 'cancelled'.
 *
 * Maksetut (processing) tilaukset käsitellään manuaalisesti, joten ne eivät ole tässä.
 *
 * @return array<int, string>
 */
if ( ! function_exists( 'rytkoset_theme_get_immediately_cancellable_order_statuses' ) ) {
	function rytkoset_theme_get_immediately_cancellable_order_statuses() {
		/**
		 * Suodattaa tilat, jotka peruuntuvat heti ilman manuaalista käsittelyä.
		 *
		 * @param array<int, string> $statuses Tilaustilat.
		 */
		return (array) apply_filters(
			'rytkoset_theme_immediately_cancellable_order_statuses',
			array( 'pending', 'on-hold' )
		);
	}
}

/**
 * Tilausmetan avain peruutuspyynnön aikaleimalle (maksetut tilaukset).
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_order_cancellation_requested_meta_key' ) ) {
	function rytkoset_theme_get_order_cancellation_requested_meta_key() {
		return '_rytkoset_cancellation_requested_at';
	}
}

/**
 * Peruutusvahvistussivun My Account -endpointin query var -avain.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_order_cancellation_endpoint' ) ) {
	function rytkoset_theme_get_order_cancellation_endpoint() {
		return 'rytkoset_cancel_order';
	}
}

/**
 * Peruutusvahvistussivun URL-slug.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_order_cancellation_endpoint_slug' ) ) {
	function rytkoset_theme_get_order_cancellation_endpoint_slug() {
		return 'peruuta-tilaus';
	}
}

/**
 * Returns the public query variable used by the guest cancellation route.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_guest_order_cancellation_query_var' ) ) {
	function rytkoset_theme_get_guest_order_cancellation_query_var() {
		return 'rytkoset_guest_cancel_order';
	}
}

/**
 * Returns the public guest cancellation path slug.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_guest_order_cancellation_slug' ) ) {
	function rytkoset_theme_get_guest_order_cancellation_slug() {
		return 'peruuta-tilaus';
	}
}

/**
 * Checks whether an order contains at least one product that requires shipping.
 *
 * @param WC_Order $order Order object.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_has_physical_products' ) ) {
	function rytkoset_theme_order_has_physical_products( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! is_callable( array( $item, 'get_product' ) ) ) {
				continue;
			}

			$product = $item->get_product();

			if ( $product instanceof WC_Product && $product->needs_shipping() ) {
				return true;
			}
		}

		return false;
	}
}

/**
 * Checks whether an order can be cancelled or submitted for manual return review.
 *
 * Physical-product requests have no automatic order-date cutoff because the store does not
 * record the delivery date. The administrator verifies the 14-day window from receipt manually.
 * Other orders retain the existing 14-day window from order creation.
 *
 * @param WC_Order $order               Order object.
 * @param int|null $reference_timestamp Reference timestamp; defaults to the current time.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_is_cancellable' ) ) {
	function rytkoset_theme_order_is_cancellable( $order, $reference_timestamp = null ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		// Fail closed when the shared product-level withdrawal classification is available.
		if (
			function_exists( 'rytkoset_theme_order_has_withdrawal_right_products' )
			&& ! rytkoset_theme_order_has_withdrawal_right_products( $order )
		) {
			return false;
		}

		// Maksetun tilauksen peruutus on jo pyydetty -> ei näytetä painiketta uudelleen.
		if ( '' !== (string) $order->get_meta( rytkoset_theme_get_order_cancellation_requested_meta_key() ) ) {
			return false;
		}

		$has_physical_products = rytkoset_theme_order_has_physical_products( $order );
		$allowed_statuses      = $has_physical_products
			? rytkoset_theme_get_physical_product_return_request_statuses()
			: rytkoset_theme_get_cancellable_order_statuses();

		if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
			return false;
		}

		$created = $order->get_date_created();

		if ( ! is_object( $created ) || ! is_callable( array( $created, 'getTimestamp' ) ) ) {
			return false;
		}

		$created_ts          = (int) $created->getTimestamp();
		$reference_timestamp = null === $reference_timestamp ? time() : (int) $reference_timestamp;
		$window_seconds      = rytkoset_theme_get_order_cancellation_window_days() * DAY_IN_SECONDS;

		// Reject invalid future-dated orders for both cancellation paths.
		if ( $reference_timestamp < $created_ts ) {
			return false;
		}

		if ( $has_physical_products ) {
			return true;
		}

		return ( $reference_timestamp - $created_ts ) <= $window_seconds;
	}
}

/**
 * Checks whether an order contains a product without a withdrawal right.
 *
 * @param WC_Order $order Order object.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_has_nonwithdrawal_right_products' ) ) {
	function rytkoset_theme_order_has_nonwithdrawal_right_products( $order ) {
		if ( ! $order instanceof WC_Order || ! function_exists( 'rytkoset_theme_product_has_withdrawal_right' ) ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

			if ( ! ( $product instanceof WC_Product ) || ! rytkoset_theme_product_has_withdrawal_right( $product, $order ) ) {
				return true;
			}
		}

		return false;
	}
}

/**
 * Checks whether a cancellation request needs administrator review.
 *
 * Paid orders and mixed orders containing products without a withdrawal right
 * are never cancelled automatically.
 *
 * @param WC_Order $order Order object.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_cancellation_requires_manual_handling' ) ) {
	function rytkoset_theme_order_cancellation_requires_manual_handling( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		return rytkoset_theme_order_has_nonwithdrawal_right_products( $order ) || ! in_array(
			$order->get_status(),
			rytkoset_theme_get_immediately_cancellable_order_statuses(),
			true
		);
	}
}

/**
 * Sisältääkö tilaus tuotteita, joihin ei sovelleta peruuttamisoikeutta?
 *
 * Käytetään vahvistussivun ja peruutusviestin selitteeseen. Rajaus koskee vain
 * määrättynä ajankohtana suoritettavia tapahtumapalveluja (KSL 6:16 §:n 1
 * momentin 11 kohta): Tampere 2026 -osallistumismaksu ja saman tapahtuman
 * bussikyyti. Jäsenmaksuja kohdellaan tavallisen 14 päivän peruuttamisoikeuden
 * piiriin kuuluvina, yhdenmukaisesti tilausvahvistuksen peruuttamisohjeen
 * (`inc/woocommerce-withdrawal-information.php`, #573) kanssa. Ei estä
 * peruutuspyyntöä — maksetut tilaukset käsitellään joka tapauksessa manuaalisesti.
 *
 * @param WC_Order $order Tilausobjekti.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_has_cancellation_exception_products' ) ) {
	function rytkoset_theme_order_has_cancellation_exception_products( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$has_exception = false;

		foreach ( $order->get_items() as $item ) {
			if ( ! is_callable( array( $item, 'get_product' ) ) ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if (
				( function_exists( 'rytkoset_theme_is_tampere_2026_registration_product' ) && rytkoset_theme_is_tampere_2026_registration_product( $product ) )
				|| ( function_exists( 'rytkoset_theme_is_bus_transport_product' ) && rytkoset_theme_is_bus_transport_product( $product ) )
			) {
				$has_exception = true;
				break;
			}
		}

		/**
		 * Suodattaa, sisältääkö tilaus peruutusoikeuden poikkeustuotteita.
		 *
		 * @param bool     $has_exception Tulos.
		 * @param WC_Order $order         Tilausobjekti.
		 */
		return (bool) apply_filters( 'rytkoset_theme_order_has_cancellation_exception_products', $has_exception, $order );
	}
}

/**
 * Rakentaa peruutusvahvistussivun URL:n nonce-suojattuna.
 *
 * @param WC_Order $order Tilausobjekti.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_order_cancellation_url' ) ) {
	function rytkoset_theme_get_order_cancellation_url( $order ) {
		if ( ! $order instanceof WC_Order || ! function_exists( 'wc_get_account_endpoint_url' ) ) {
			return '';
		}

		$order_id   = $order->get_id();
		$base_url   = wc_get_account_endpoint_url( rytkoset_theme_get_order_cancellation_endpoint() );
		$cancel_url = add_query_arg( 'order_id', $order_id, $base_url );

		return wp_nonce_url( $cancel_url, 'rytkoset_cancel_order_' . $order_id );
	}
}

/**
 * Builds the long-lived order-key URL used by guest customers.
 *
 * The order key is WooCommerce's bearer credential for guest order links. A
 * WordPress nonce is intentionally not included here because email links must
 * remain usable throughout the withdrawal period. The confirmation form adds
 * a fresh nonce before any state change is accepted.
 *
 * @param WC_Order $order Order object.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_guest_order_cancellation_url' ) ) {
	function rytkoset_theme_get_guest_order_cancellation_url( $order ) {
		if ( ! $order instanceof WC_Order || ! is_callable( array( $order, 'get_order_key' ) ) ) {
			return '';
		}

		$order_id  = (int) $order->get_id();
		$order_key = (string) $order->get_order_key();

		if ( $order_id <= 0 || '' === $order_key ) {
			return '';
		}

		$base_url = home_url(
			'/' . rytkoset_theme_get_guest_order_cancellation_slug() . '/' . $order_id . '/'
		);

		return add_query_arg( array( 'key' => $order_key ), $base_url );
	}
}

/**
 * Lisää "Peruuta tilaus" -painikkeen tilauslistan toimintoihin.
 *
 * @param array<string, array<string, string>> $actions Tilauskohtaiset toiminnot.
 * @param WC_Order                             $order   Tilausobjekti.
 * @return array<string, array<string, string>>
 */
if ( ! function_exists( 'rytkoset_theme_add_cancel_order_action' ) ) {
	function rytkoset_theme_add_cancel_order_action( $actions, $order ) {
		if ( ! $order instanceof WC_Order || ! rytkoset_theme_order_is_cancellable( $order ) ) {
			return $actions;
		}

		$url = rytkoset_theme_get_order_cancellation_url( $order );

		if ( '' === $url ) {
			return $actions;
		}

		// WooCommerce näyttää oman natiivin "Peruuta"-toiminnon pending/failed-tilauksille
		// (suora peruutus ilman vahvistussivua tai sähköpostia). Poistetaan se, kun oma
		// vahvistettava peruutus on käytettävissä, jottei näytetä kahta peruutuspainiketta.
		unset( $actions['cancel'] );

		$actions['rytkoset-cancel-order'] = array(
			'url'  => $url,
			'name' => __( 'Peruuta tilaus', 'rytkoset-theme' ),
		);

		return $actions;
	}
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'rytkoset_theme_add_cancel_order_action', 10, 2 );

/**
 * Rekisteröi peruutusvahvistussivun WooCommerce My Account -endpointiksi.
 *
 * Avain 'rytkoset_cancel_order' (query var), URL-slug 'peruuta-tilaus'. WooCommerce
 * rekisteröi rewrite-endpointin omien query var -muuttujiensa mukana.
 *
 * @param array<string, string> $vars WooCommerce-endpointtien query var -muuttujat.
 * @return array<string, string>
 */
if ( ! function_exists( 'rytkoset_theme_register_cancellation_endpoint_query_var' ) ) {
	function rytkoset_theme_register_cancellation_endpoint_query_var( $vars ) {
		$vars[ rytkoset_theme_get_order_cancellation_endpoint() ] = rytkoset_theme_get_order_cancellation_endpoint_slug();

		return $vars;
	}
}
add_filter( 'woocommerce_get_query_vars', 'rytkoset_theme_register_cancellation_endpoint_query_var' );

/**
 * Registers the public guest cancellation query variable.
 *
 * @param array<int, string> $vars Public WordPress query variables.
 * @return array<int, string>
 */
if ( ! function_exists( 'rytkoset_theme_register_guest_cancellation_query_var' ) ) {
	function rytkoset_theme_register_guest_cancellation_query_var( $vars ) {
		$vars[] = rytkoset_theme_get_guest_order_cancellation_query_var();

		return array_values( array_unique( $vars ) );
	}
}
add_filter( 'query_vars', 'rytkoset_theme_register_guest_cancellation_query_var' );

/**
 * Registers the public order-key cancellation route.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_register_guest_cancellation_rewrite_rule' ) ) {
	function rytkoset_theme_register_guest_cancellation_rewrite_rule() {
		$slug      = preg_quote( rytkoset_theme_get_guest_order_cancellation_slug(), '/' );
		$query_var = rytkoset_theme_get_guest_order_cancellation_query_var();

		add_rewrite_rule(
			'^' . $slug . '/([0-9]+)/?$',
			'index.php?' . $query_var . '=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'rytkoset_theme_register_guest_cancellation_rewrite_rule', 20 );

/**
 * Rewrite-flushin vartioarvo.
 *
 * Versioidaan endpointin avaimella ja slugilla, jotta vanha "1"-arvo ei voi
 * estää flushia, jos säännöt eivät ole syntyneet oikein.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_cancellation_endpoint_rewrite_version' ) ) {
	function rytkoset_theme_get_cancellation_endpoint_rewrite_version() {
		return rytkoset_theme_get_order_cancellation_endpoint() . ':' . rytkoset_theme_get_order_cancellation_endpoint_slug() . ':v2';
	}
}

/**
 * Tarkistaa, löytyykö peruutus-endpoint jo tallennetuista rewrite-säännöistä.
 *
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_cancellation_endpoint_rewrite_rules_exist' ) ) {
	function rytkoset_theme_cancellation_endpoint_rewrite_rules_exist() {
		$rules = get_option( 'rewrite_rules', array() );

		if ( ! is_array( $rules ) ) {
			return false;
		}

		$endpoint_slug    = rytkoset_theme_get_order_cancellation_endpoint_slug();
		$guest_slug       = rytkoset_theme_get_guest_order_cancellation_slug();
		$has_account_rule = false;
		$has_guest_rule   = false;

		foreach ( array_keys( $rules ) as $regex ) {
			$is_guest_rule = 0 === strpos( ltrim( (string) $regex, '^' ), $guest_slug . '/' );

			if ( $is_guest_rule ) {
				$has_guest_rule = true;
			} elseif ( false !== strpos( (string) $regex, $endpoint_slug ) ) {
				$has_account_rule = true;
			}
		}

		return $has_account_rule && $has_guest_rule;
	}
}

/**
 * Flushaa rewrite-säännöt kerran, kun endpoint on lisätty (olemassa olevat asennukset).
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_maybe_flush_cancellation_endpoint' ) ) {
	function rytkoset_theme_maybe_flush_cancellation_endpoint() {
		if (
			rytkoset_theme_get_cancellation_endpoint_rewrite_version() === get_option( 'rytkoset_theme_cancellation_endpoint_flushed' )
			&& rytkoset_theme_cancellation_endpoint_rewrite_rules_exist()
		) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'rytkoset_theme_cancellation_endpoint_flushed', rytkoset_theme_get_cancellation_endpoint_rewrite_version() );
	}
}
add_action( 'init', 'rytkoset_theme_maybe_flush_cancellation_endpoint', 99 );

/**
 * Flushaa rewrite-säännöt teeman aktivoinnissa.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_flush_cancellation_endpoint_on_activation' ) ) {
	function rytkoset_theme_flush_cancellation_endpoint_on_activation() {
		delete_option( 'rytkoset_theme_cancellation_endpoint_flushed' );
		flush_rewrite_rules( false );
		update_option( 'rytkoset_theme_cancellation_endpoint_flushed', rytkoset_theme_get_cancellation_endpoint_rewrite_version() );
	}
}
add_action( 'after_switch_theme', 'rytkoset_theme_flush_cancellation_endpoint_on_activation' );

/**
 * Authenticates access to an order for an account owner or a guest order key.
 *
 * Registered orders continue to require their owning account. An order key is
 * accepted without a login only for an order that has no customer account.
 *
 * @param int    $order_id Order ID.
 * @param string $order_key Order key supplied by a guest link.
 * @return WC_Order|WP_Error
 */
if ( ! function_exists( 'rytkoset_theme_get_authenticated_cancellation_order' ) ) {
	function rytkoset_theme_get_authenticated_cancellation_order( $order_id, $order_key = '' ) {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return new WP_Error( 'rytkoset_cancel_invalid', __( 'Tilausta ei löytynyt.', 'rytkoset-theme' ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'rytkoset_cancel_forbidden', __( 'Tätä tilausta ei voi peruuttaa.', 'rytkoset-theme' ) );
		}

		$is_account_owner = is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id();
		$is_guest_order   = 0 === (int) $order->get_user_id();
		$stored_key       = is_callable( array( $order, 'get_order_key' ) ) ? (string) $order->get_order_key() : '';
		$has_guest_key    = $is_guest_order
			&& '' !== $stored_key
			&& '' !== (string) $order_key
			&& hash_equals( $stored_key, (string) $order_key );

		if ( ! $is_account_owner && ! $has_guest_key ) {
			return new WP_Error( 'rytkoset_cancel_forbidden', __( 'Tätä tilausta ei voi peruuttaa.', 'rytkoset-theme' ) );
		}

		return $order;
	}
}

/**
 * Authenticates an order and checks its current withdrawal eligibility.
 *
 * @param int    $order_id  Order ID.
 * @param string $context   Validation context, retained for compatibility.
 * @param string $order_key Order key supplied by a guest link.
 * @return WC_Order|WP_Error
 */
if ( ! function_exists( 'rytkoset_theme_get_validated_cancellation_order' ) ) {
	function rytkoset_theme_get_validated_cancellation_order( $order_id, $context = 'view', $order_key = '' ) {
		$order = rytkoset_theme_get_authenticated_cancellation_order( $order_id, $order_key );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! rytkoset_theme_order_is_cancellable( $order ) ) {
			return new WP_Error(
				'rytkoset_cancel_not_eligible',
				__( 'Tämän tilauksen peruutusaika on päättynyt tai tilaus on jo käsitelty.', 'rytkoset-theme' )
			);
		}

		return $order;
	}
}

/**
 * Käsittelee peruutuksen vahvistuksen (lomakkeen lähetys) ennen sivun renderöintiä.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_handle_order_cancellation_submit' ) ) {
	function rytkoset_theme_handle_order_cancellation_submit() {
		if ( ! isset( $_POST['rytkoset_confirm_cancellation'] ) ) {
			return;
		}

		$order_id  = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$nonce     = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'rytkoset_confirm_cancel_order_' . $order_id ) ) {
			wc_add_notice( __( 'Peruutusta ei voitu vahvistaa. Yritä uudelleen.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$order = rytkoset_theme_get_validated_cancellation_order( $order_id, 'process', $order_key );

		if ( is_wp_error( $order ) ) {
			wc_add_notice( $order->get_error_message(), 'error' );
			return;
		}

		$requires_manual = rytkoset_theme_order_cancellation_requires_manual_handling( $order );
		$has_physical    = rytkoset_theme_order_has_physical_products( $order );
		$timestamp       = time();

		if ( $requires_manual ) {
			// Keep paid and mixed orders unchanged and route the request to an administrator.
			$order->update_meta_data( rytkoset_theme_get_order_cancellation_requested_meta_key(), gmdate( 'Y-m-d H:i:s', $timestamp ) );
			$order->add_order_note(
				$has_physical
					? __( 'Asiakas pyysi fyysisen tuotteen palautusta itsepalveluna. Tarkista vastaanottopäivä, 14 vuorokauden määräaika ja tuotteen palautuskelpoisuus ennen hyvitystä.', 'rytkoset-theme' )
					: __( 'Asiakas pyysi tilauksen peruutusta itsepalveluna. Tilaus on maksettu — käsittele palautus ja lopullinen peruutus manuaalisesti.', 'rytkoset-theme' ),
				false
			);
			$order->save();

			rytkoset_theme_send_order_cancellation_admin_email( $order, $timestamp );
		} else {
			// Maksamaton tilaus (pending/on-hold): peruutetaan heti.
			$order->update_status(
				'cancelled',
				__( 'Asiakas peruutti tilauksen itsepalveluna.', 'rytkoset-theme' )
			);
		}

		rytkoset_theme_send_order_cancellation_customer_email( $order, $requires_manual, $timestamp );

		$success = $requires_manual
			? __( 'Peruutuspyyntösi on vastaanotettu. Käsittelemme palautuksen ja olemme tarvittaessa yhteydessä. Saat vahvistuksen sähköpostiisi.', 'rytkoset-theme' )
			: __( 'Tilauksesi on peruutettu. Saat vahvistuksen sähköpostiisi.', 'rytkoset-theme' );

		wc_add_notice( $success, 'success' );

		if ( 0 === (int) $order->get_user_id() && '' !== $order_key ) {
			$guest_url = rytkoset_theme_get_guest_order_cancellation_url( $order );

			if ( '' !== $guest_url ) {
				wp_safe_redirect( add_query_arg( array( 'cancellation_received' => '1' ), $guest_url ) );
				exit;
			}
		}

		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}
	}
}
add_action( 'template_redirect', 'rytkoset_theme_handle_order_cancellation_submit' );

/**
 * Renders the shared cancellation confirmation form.
 *
 * @param WC_Order $order      Authenticated and eligible order.
 * @param string   $return_url URL used by the secondary action.
 * @param string   $order_key  Guest order key; empty for account orders.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_render_order_cancellation_confirmation' ) ) {
	function rytkoset_theme_render_order_cancellation_confirmation( $order, $return_url, $order_key = '' ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$requires_manual    = rytkoset_theme_order_cancellation_requires_manual_handling( $order );
		$has_physical       = rytkoset_theme_order_has_physical_products( $order );
		$customer_name      = is_callable( array( $order, 'get_formatted_billing_full_name' ) )
			? (string) $order->get_formatted_billing_full_name()
			: '';
		$confirmation_email = (string) $order->get_billing_email();
		?>
		<div class="rytkoset-cancel-order-confirm">
			<h2><?php esc_html_e( 'Vahvista tilauksen peruutus', 'rytkoset-theme' ); ?></h2>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: order number. */
						__( 'Olet peruuttamassa tilausta numero %s. Tarkista tiedot ja vahvista peruutus.', 'rytkoset-theme' ),
						$order->get_order_number()
					)
				);
				?>
			</p>

			<table class="shop_table rytkoset-cancel-order-summary">
				<tbody>
					<?php if ( '' !== $customer_name ) : ?>
						<tr>
							<th><?php esc_html_e( 'Peruuttaja', 'rytkoset-theme' ); ?></th>
							<td><?php echo esc_html( $customer_name ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th><?php esc_html_e( 'Tilausnumero', 'rytkoset-theme' ); ?></th>
						<td><?php echo esc_html( $order->get_order_number() ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tilauspäivä', 'rytkoset-theme' ); ?></th>
						<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tuotteet', 'rytkoset-theme' ); ?></th>
						<td>
							<?php
							$item_names = array();
							foreach ( $order->get_items() as $item ) {
								$item_names[] = sprintf( '%s × %d', $item->get_name(), (int) $item->get_quantity() );
							}
							echo esc_html( implode( ', ', $item_names ) );
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Summa', 'rytkoset-theme' ); ?></th>
						<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
					</tr>
					<?php if ( is_email( $confirmation_email ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Vahvistus lähetetään', 'rytkoset-theme' ); ?></th>
							<td><?php echo esc_html( $confirmation_email ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $requires_manual ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Tilausta ei peruuteta automaattisesti. Peruuttamisilmoituksesi välitetään käsiteltäväksi, ja olemme tarvittaessa sinuun yhteydessä.', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $requires_manual && $has_physical ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Fyysisen tuotteen 14 vuorokauden palautusaika lasketaan tuotteen vastaanottamisesta. Ylläpitäjä tarkistaa määräajan ja palautuskelpoisuuden pyyntösi käsittelyn yhteydessä.', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( rytkoset_theme_order_has_cancellation_exception_products( $order ) ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Huomaa: tapahtumamaksuihin ei sovelleta peruuttamisoikeutta, kun palvelu suoritetaan määrättynä ajankohtana. Tarkistamme tilanteen pyyntösi yhteydessä.', 'rytkoset-theme' ); ?>
				</div>
			<?php elseif ( rytkoset_theme_order_has_nonwithdrawal_right_products( $order ) ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Tilaus sisältää myös tuotteen, johon peruuttamisoikeutta ei sovelleta. Käsittelemme peruuttamisoikeuden piiriin kuuluvat tuotteet erikseen.', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" class="rytkoset-cancel-order-form">
				<?php wp_nonce_field( 'rytkoset_confirm_cancel_order_' . $order->get_id() ); ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
				<?php if ( '' !== $order_key ) : ?>
					<input type="hidden" name="order_key" value="<?php echo esc_attr( $order_key ); ?>" />
				<?php endif; ?>
				<button type="submit" name="rytkoset_confirm_cancellation" value="1" class="button rytkoset-cancel-order">
					<?php esc_html_e( 'Vahvista peruuttamisilmoituksen lähettäminen', 'rytkoset-theme' ); ?>
				</button>
				<a class="button rytkoset-cancel-order-back" href="<?php echo esc_url( $return_url ); ?>">
					<?php esc_html_e( 'Palaa takaisin', 'rytkoset-theme' ); ?>
				</a>
			</form>
		</div>
		<?php
	}
}

/**
 * Renderöi peruutusvahvistussivun My Account -endpointissa.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_render_order_cancellation_endpoint' ) ) {
	function rytkoset_theme_render_order_cancellation_endpoint() {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		$orders_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : home_url( '/' );

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'rytkoset_cancel_order_' . $order_id ) ) {
			wc_print_notice( __( 'Peruutuslinkki on virheellinen tai vanhentunut.', 'rytkoset-theme' ), 'error' );
			printf(
				'<p><a class="button" href="%s">%s</a></p>',
				esc_url( $orders_url ),
				esc_html__( 'Palaa tilauksiin', 'rytkoset-theme' )
			);
			return;
		}

		$order = rytkoset_theme_get_validated_cancellation_order( $order_id, 'view' );

		if ( is_wp_error( $order ) ) {
			wc_print_notice( $order->get_error_message(), 'error' );
			printf(
				'<p><a class="button" href="%s">%s</a></p>',
				esc_url( $orders_url ),
				esc_html__( 'Palaa tilauksiin', 'rytkoset-theme' )
			);
			return;
		}

		rytkoset_theme_render_order_cancellation_confirmation( $order, $orders_url );
	}
}
add_action( 'woocommerce_account_rytkoset_cancel_order_endpoint', 'rytkoset_theme_render_order_cancellation_endpoint' );

/**
 * Checks whether the current request is the public guest cancellation route.
 *
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_is_guest_order_cancellation_request' ) ) {
	function rytkoset_theme_is_guest_order_cancellation_request() {
		return absint( get_query_var( rytkoset_theme_get_guest_order_cancellation_query_var(), 0 ) ) > 0;
	}
}

/**
 * Adds page classes so the public route inherits the existing WooCommerce UI.
 *
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_body_classes' ) ) {
	function rytkoset_theme_guest_cancellation_body_classes( $classes ) {
		if ( rytkoset_theme_is_guest_order_cancellation_request() ) {
			$classes[] = 'woocommerce-page';
			$classes[] = 'rytkoset-order-cancellation';
		}

		return $classes;
	}
}
add_filter( 'body_class', 'rytkoset_theme_guest_cancellation_body_classes' );

/**
 * Sets the document title for the public guest cancellation view.
 *
 * @param string $title Existing title.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_document_title' ) ) {
	function rytkoset_theme_guest_cancellation_document_title( $title ) {
		return rytkoset_theme_is_guest_order_cancellation_request()
			? __( 'Peruuta tilaus', 'rytkoset-theme' )
			: $title;
	}
}
add_filter( 'pre_get_document_title', 'rytkoset_theme_guest_cancellation_document_title' );

/**
 * Sets the Rank Math title for the public guest cancellation view.
 *
 * @param string $title Existing Rank Math title.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_rank_math_title' ) ) {
	function rytkoset_theme_guest_cancellation_rank_math_title( $title ) {
		return rytkoset_theme_is_guest_order_cancellation_request()
			? sprintf(
				/* translators: %s: site name. */
				__( 'Peruuta tilaus - %s', 'rytkoset-theme' ),
				get_bloginfo( 'name' )
			)
			: $title;
	}
}
add_filter( 'rank_math/frontend/title', 'rytkoset_theme_guest_cancellation_rank_math_title' );

/**
 * Prevents indexing of order-key URLs.
 *
 * @param array<string, bool> $robots Robots directives.
 * @return array<string, bool>
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_robots' ) ) {
	function rytkoset_theme_guest_cancellation_robots( $robots ) {
		if ( rytkoset_theme_is_guest_order_cancellation_request() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}
}
add_filter( 'wp_robots', 'rytkoset_theme_guest_cancellation_robots' );

/**
 * Prevents Rank Math from indexing the order-key route.
 *
 * @param array<string, string> $robots Rank Math robots directives.
 * @return array<string, string>
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_rank_math_robots' ) ) {
	function rytkoset_theme_guest_cancellation_rank_math_robots( $robots ) {
		if ( rytkoset_theme_is_guest_order_cancellation_request() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';
		}

		return $robots;
	}
}
add_filter( 'rank_math/frontend/robots', 'rytkoset_theme_guest_cancellation_rank_math_robots' );

/**
 * Adds defense-in-depth response headers to the order-key route.
 *
 * @param array<string, string> $headers Response headers.
 * @return array<string, string>
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_response_headers' ) ) {
	function rytkoset_theme_guest_cancellation_response_headers( $headers ) {
		if ( rytkoset_theme_is_guest_order_cancellation_request() ) {
			$headers['X-Robots-Tag'] = 'noindex, nofollow';
		}

		return $headers;
	}
}
add_filter( 'wp_headers', 'rytkoset_theme_guest_cancellation_response_headers' );

/**
 * Overrides the theme-wide referrer policy for the sensitive order-key route.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_send_guest_cancellation_referrer_header' ) ) {
	function rytkoset_theme_send_guest_cancellation_referrer_header() {
		if ( rytkoset_theme_is_guest_order_cancellation_request() && ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
		}
	}
}
add_action( 'send_headers', 'rytkoset_theme_send_guest_cancellation_referrer_header', 20 );

/**
 * Prevents a guest order key from being sent as a referrer.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_guest_cancellation_referrer_policy' ) ) {
	function rytkoset_theme_guest_cancellation_referrer_policy() {
		if ( rytkoset_theme_is_guest_order_cancellation_request() ) {
			echo '<meta name="referrer" content="no-referrer">' . "\n";
		}
	}
}
add_action( 'wp_head', 'rytkoset_theme_guest_cancellation_referrer_policy', 1 );

/**
 * Renders the public order-key cancellation route for guest orders.
 *
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_render_guest_order_cancellation_page' ) ) {
	function rytkoset_theme_render_guest_order_cancellation_page() {
		if ( ! rytkoset_theme_is_guest_order_cancellation_request() ) {
			return;
		}

		$order_id = absint( get_query_var( rytkoset_theme_get_guest_order_cancellation_query_var(), 0 ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only route credentials and receipt state; submission uses a nonce.
		$order_key  = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$return_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/kauppa/' );
		$received   = isset( $_GET['cancellation_received'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cancellation_received'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		status_header( 200 );
		nocache_headers();
		get_header();
		?>
		<main id="primary" class="site-main" tabindex="-1">
			<section class="section">
				<div class="container section__narrow">
					<article class="article woocommerce">
						<h1 class="article__title"><?php esc_html_e( 'Peruuta tilaus', 'rytkoset-theme' ); ?></h1>
						<div class="article__content">
							<?php
							if ( $received ) {
								$order       = rytkoset_theme_get_authenticated_cancellation_order( $order_id, $order_key );
								$is_recorded = $order instanceof WC_Order
									&& (
										'cancelled' === $order->get_status()
										|| '' !== (string) $order->get_meta( rytkoset_theme_get_order_cancellation_requested_meta_key() )
									);

								if ( is_wp_error( $order ) || ! $is_recorded ) {
									wc_print_notice( __( 'Peruutuslinkki on virheellinen.', 'rytkoset-theme' ), 'error' );
								} else {
									wc_print_notice( __( 'Peruuttamisilmoituksesi on vastaanotettu. Vahvistus on lähetetty tilauksen sähköpostiosoitteeseen.', 'rytkoset-theme' ), 'success' );
								}
							} else {
								$order = rytkoset_theme_get_validated_cancellation_order( $order_id, 'view', $order_key );

								if ( is_wp_error( $order ) ) {
									wc_print_notice( __( 'Tilausta ei löytynyt, peruutusaika on päättynyt tai tilaus on jo käsitelty.', 'rytkoset-theme' ), 'error' );
								} else {
									rytkoset_theme_render_order_cancellation_confirmation( $order, $return_url, $order_key );
								}
							}
							?>
							<p><a class="button" href="<?php echo esc_url( $return_url ); ?>"><?php esc_html_e( 'Palaa kauppaan', 'rytkoset-theme' ); ?></a></p>
						</div>
					</article>
				</div>
			</section>
		</main>
		<?php
		get_footer();
		exit;
	}
}
add_action( 'template_redirect', 'rytkoset_theme_render_guest_order_cancellation_page', 20 );

/**
 * Returns customer email types that carry the persistent guest cancellation link.
 *
 * @return array<int, string>
 */
if ( ! function_exists( 'rytkoset_theme_get_guest_cancellation_email_ids' ) ) {
	function rytkoset_theme_get_guest_cancellation_email_ids() {
		/**
		 * Filters customer email types that include the guest cancellation link.
		 *
		 * @param array<int, string> $email_ids WooCommerce customer email IDs.
		 */
		return (array) apply_filters(
			'rytkoset_theme_guest_cancellation_email_ids',
			array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' )
		);
	}
}

/**
 * Adds an order-key cancellation link to eligible guest order emails.
 *
 * @param WC_Order     $order         Order object.
 * @param bool         $sent_to_admin Whether the message is sent to an administrator.
 * @param bool         $plain_text    Whether the message uses plain text.
 * @param WC_Email|null $email        WooCommerce email object.
 * @return void
 */
if ( ! function_exists( 'rytkoset_theme_render_guest_cancellation_link_in_email' ) ) {
	function rytkoset_theme_render_guest_cancellation_link_in_email( $order, $sent_to_admin, $plain_text, $email = null ) {
		$email_id = is_object( $email ) && isset( $email->id ) ? (string) $email->id : '';

		if (
			$sent_to_admin
			|| ! $order instanceof WC_Order
			|| 0 !== (int) $order->get_user_id()
			|| ! in_array( $email_id, rytkoset_theme_get_guest_cancellation_email_ids(), true )
			|| ! rytkoset_theme_order_is_cancellable( $order )
		) {
			return;
		}

		$url = rytkoset_theme_get_guest_order_cancellation_url( $order );

		if ( '' === $url ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'PERUUTA TILAUS', 'rytkoset-theme' ) . "\n";
			echo esc_html__( 'Voit tarkistaa tilauksen tiedot ja lähettää peruuttamisilmoituksen seuraavasta henkilökohtaisesta linkistä:', 'rytkoset-theme' ) . "\n";
			echo esc_url( $url ) . "\n";
			return;
		}

		echo '<div class="rytkoset-guest-cancellation-link">';
		echo '<h2>' . esc_html__( 'Peruuta tilaus', 'rytkoset-theme' ) . '</h2>';
		echo '<p>' . esc_html__( 'Voit tarkistaa tilauksen tiedot ja lähettää peruuttamisilmoituksen seuraavasta henkilökohtaisesta linkistä:', 'rytkoset-theme' ) . '</p>';
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Peruuta tilaus', 'rytkoset-theme' ) . '</a></p>';
		echo '</div>';
	}
}
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_render_guest_cancellation_link_in_email', 21, 4 );

/**
 * Lähettää asiakkaalle välittömän vahvistussähköpostin aikaleimalla.
 *
 * @param WC_Order $order           Tilausobjekti.
 * @param bool     $requires_manual Onko kyseessä maksettu tilaus (manuaalinen käsittely).
 * @param int      $timestamp       Peruutuksen aikaleima (unix).
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_send_order_cancellation_customer_email' ) ) {
	function rytkoset_theme_send_order_cancellation_customer_email( $order, $requires_manual, $timestamp ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$email = $order->get_billing_email();

		if ( ! is_email( $email ) ) {
			return false;
		}

		$order_number   = $order->get_order_number();
		$datetime_label = wp_date( 'j.n.Y \k\l\o H:i', $timestamp );

		$subject = $requires_manual
			? sprintf(
				/* translators: %s: order number. */
				__( 'Peruutuspyyntösi tilaukselle %s on vastaanotettu', 'rytkoset-theme' ),
				$order_number
			)
			: sprintf(
				/* translators: %s: order number. */
				__( 'Tilauksesi %s on peruutettu', 'rytkoset-theme' ),
				$order_number
			);

		$lines = array(
			__( 'Hei,', 'rytkoset-theme' ),
			'',
			$requires_manual
				? __( 'Olemme vastaanottaneet peruutuspyyntösi seuraavalle tilaukselle:', 'rytkoset-theme' )
				: __( 'Tilauksesi on peruutettu. Tässä vahvistus:', 'rytkoset-theme' ),
			'',
			sprintf(
				/* translators: %s: order number. */
				__( 'Tilausnumero: %s', 'rytkoset-theme' ),
				$order_number
			),
			sprintf(
				/* translators: %s: cancellation date and time. */
				__( 'Aikaleima: %s', 'rytkoset-theme' ),
				$datetime_label
			),
		);

		$item_names = array();
		foreach ( $order->get_items() as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

			if (
				function_exists( 'rytkoset_theme_product_has_withdrawal_right' )
				&& ( ! ( $product instanceof WC_Product ) || ! rytkoset_theme_product_has_withdrawal_right( $product, $order ) )
			) {
				continue;
			}

			$item_names[] = sprintf( '%s × %d', $item->get_name(), (int) $item->get_quantity() );
		}

		if ( ! empty( $item_names ) ) {
			$lines[] = sprintf(
				/* translators: %s: comma-separated list of ordered products. */
				__( 'Peruttavat tuotteet: %s', 'rytkoset-theme' ),
				implode( ', ', $item_names )
			);
		}

		$lines[] = '';

		if ( $requires_manual ) {
			$lines[] = __( 'Tilausta ei peruutettu automaattisesti. Käsittelemme peruuttamisilmoituksen manuaalisesti ja olemme tarvittaessa sinuun yhteydessä.', 'rytkoset-theme' );

			if ( rytkoset_theme_order_has_cancellation_exception_products( $order ) ) {
				$lines[] = __( 'Tapahtumamaksuihin ei sovelleta peruuttamisoikeutta, kun palvelu suoritetaan määrättynä ajankohtana.', 'rytkoset-theme' );
			}

			if ( rytkoset_theme_order_has_physical_products( $order ) ) {
				$lines[] = __( 'Fyysisen tuotteen 14 vuorokauden palautusaika lasketaan tuotteen vastaanottamisesta. Tarkistamme määräajan ja palautuskelpoisuuden käsittelyn yhteydessä.', 'rytkoset-theme' );
			}
		} else {
			$lines[] = __( 'Tilausta ei ollut vielä maksettu, joten erillistä palautusta ei tarvita.', 'rytkoset-theme' );
		}

		$contact_email = rytkoset_theme_get_contact_email();

		$lines[] = '';

		if ( is_email( $contact_email ) ) {
			$lines[] = sprintf(
				/* translators: %s: association contact email address. */
				__( 'Kysymyksissä voit olla yhteydessä: %s', 'rytkoset-theme' ),
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

/**
 * Lähettää adminille ilmoituksen maksetun tilauksen peruutuspyynnöstä.
 *
 * @param WC_Order $order     Tilausobjekti.
 * @param int      $timestamp Peruutuspyynnön aikaleima (unix).
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_send_order_cancellation_admin_email' ) ) {
	function rytkoset_theme_send_order_cancellation_admin_email( $order, $timestamp ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		/**
		 * Suodattaa peruutusilmoituksen vastaanottajan.
		 *
		 * @param string   $recipient Sähköpostiosoite.
		 * @param WC_Order $order     Tilausobjekti.
		 */
		$recipient = apply_filters(
			'rytkoset_theme_order_cancellation_admin_recipient',
			rytkoset_theme_get_contact_email(),
			$order
		);

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$order_number   = $order->get_order_number();
		$datetime_label = wp_date( 'j.n.Y \k\l\o H:i', $timestamp );

		$subject = sprintf(
			/* translators: %s: order number. */
			__( 'Peruutuspyyntö: tilaus %s vaatii käsittelyn', 'rytkoset-theme' ),
			$order_number
		);

		$lines = array(
			sprintf(
				/* translators: %s: order number. */
				__( 'Asiakas on lähettänyt tilausta %s koskevan peruuttamisilmoituksen.', 'rytkoset-theme' ),
				$order_number
			),
			sprintf(
				/* translators: %s: request date and time. */
				__( 'Pyynnön aikaleima: %s', 'rytkoset-theme' ),
				$datetime_label
			),
			'',
			__( 'Tilauksen status on jätetty ennalleen. Käsittele peruuttamisoikeuden piiriin kuuluvat tuotteet, mahdollinen palautus ja lopullinen tilamuutos manuaalisesti WooCommercessa. Huomioi tuotteet, joihin peruuttamisoikeutta ei sovelleta.', 'rytkoset-theme' ),
		);

		if ( rytkoset_theme_order_has_physical_products( $order ) ) {
			$lines[] = __( 'Tilaus sisältää fyysisen tuotteen. Tarkista tuotteen vastaanottopäivä, 14 vuorokauden määräaika ja palautuskelpoisuus ennen hyvitystä.', 'rytkoset-theme' );
		}

		$edit_url = $order->get_edit_order_url();

		if ( $edit_url ) {
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: %s: order edit URL. */
				__( 'Avaa tilaus: %s', 'rytkoset-theme' ),
				$edit_url
			);
		}

		return wp_mail(
			$recipient,
			$subject,
			implode( "\n", $lines ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}
