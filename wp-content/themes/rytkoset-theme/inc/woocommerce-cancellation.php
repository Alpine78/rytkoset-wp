<?php
/**
 * Lainmukainen tilauksen peruutuspainike (EU:n kuluttajansuoja, voimaan 19.6.2026).
 *
 * Lisää asiakkaan itsepalvelu-peruutuksen "Oma tili > Tilaukset" -näkymään:
 * peruutuspainike, erillinen vahvistussivu (WooCommerce My Account -endpoint),
 * välitön aikaleimavahvistus asiakkaalle sähköpostilla ja admin-ilmoitus
 * maksetuista tilauksista, jotka vaativat manuaalisen palautuksen.
 *
 * Tietovirta:
 *  - Painike (woocommerce_my_account_my_orders_actions) -> /tili/peruuta-tilaus/?order_id=..&_wpnonce=..
 *  - Vahvistussivu (woocommerce_account_rytkoset_cancel_order_endpoint) näyttää tilaustiedot
 *  - Lomakkeen lähetys käsitellään template_redirectissä:
 *      pending/on-hold -> status 'cancelled' heti
 *      processing      -> tilausmuistiinpano + admin-sähköposti, status ennallaan (palautus käsin)
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
			array( 'pending', 'on-hold', 'processing' )
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
 * Vaatiiko tilauksen peruutus manuaalisen käsittelyn (maksettu tilaus)?
 *
 * @param WC_Order $order Tilausobjekti.
 * @return bool
 */
if ( ! function_exists( 'rytkoset_theme_order_cancellation_requires_manual_handling' ) ) {
	function rytkoset_theme_order_cancellation_requires_manual_handling( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		return ! in_array(
			$order->get_status(),
			rytkoset_theme_get_immediately_cancellable_order_statuses(),
			true
		);
	}
}

/**
 * Sisältääkö tilaus tuotteita, joiden peruutusoikeus voi olla rajoitettu?
 *
 * Käytetään vahvistussivun selitteeseen (ainaisjäsenyys, tapahtumat). Ei estä
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
				( function_exists( 'rytkoset_theme_is_membership_product' ) && rytkoset_theme_is_membership_product( $product ) )
				|| ( function_exists( 'rytkoset_theme_is_tampere_2026_registration_product' ) && rytkoset_theme_is_tampere_2026_registration_product( $product ) )
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
 * Rewrite-flushin vartioarvo.
 *
 * Versioidaan endpointin avaimella ja slugilla, jotta vanha "1"-arvo ei voi
 * estää flushia, jos säännöt eivät ole syntyneet oikein.
 *
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_get_cancellation_endpoint_rewrite_version' ) ) {
	function rytkoset_theme_get_cancellation_endpoint_rewrite_version() {
		return rytkoset_theme_get_order_cancellation_endpoint() . ':' . rytkoset_theme_get_order_cancellation_endpoint_slug() . ':v1';
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

		$endpoint_slug = rytkoset_theme_get_order_cancellation_endpoint_slug();

		foreach ( array_keys( $rules ) as $regex ) {
			if ( false !== strpos( (string) $regex, $endpoint_slug ) ) {
				return true;
			}
		}

		return false;
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
 * Hakee ja validoi vahvistussivun tilauksen nykyiselle käyttäjälle.
 *
 * @param int    $order_id Tilauksen tunnus.
 * @param string $context  'view' (GET-nonce) tai 'process' (POST-nonce).
 * @return WC_Order|WP_Error
 */
if ( ! function_exists( 'rytkoset_theme_get_validated_cancellation_order' ) ) {
	function rytkoset_theme_get_validated_cancellation_order( $order_id, $context = 'view' ) {
		$order_id = absint( $order_id );

		if ( ! $order_id || ! is_user_logged_in() ) {
			return new WP_Error( 'rytkoset_cancel_invalid', __( 'Tilausta ei löytynyt.', 'rytkoset-theme' ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || (int) $order->get_user_id() !== get_current_user_id() ) {
			return new WP_Error( 'rytkoset_cancel_forbidden', __( 'Tätä tilausta ei voi peruuttaa.', 'rytkoset-theme' ) );
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

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$nonce    = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'rytkoset_confirm_cancel_order_' . $order_id ) ) {
			wc_add_notice( __( 'Peruutusta ei voitu vahvistaa. Yritä uudelleen.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$order = rytkoset_theme_get_validated_cancellation_order( $order_id, 'process' );

		if ( is_wp_error( $order ) ) {
			wc_add_notice( $order->get_error_message(), 'error' );
			return;
		}

		$requires_manual = rytkoset_theme_order_cancellation_requires_manual_handling( $order );
		$has_physical    = rytkoset_theme_order_has_physical_products( $order );
		$timestamp       = time();

		if ( $requires_manual ) {
			// Keep paid orders unchanged and route the request to an administrator.
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

		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}
	}
}
add_action( 'template_redirect', 'rytkoset_theme_handle_order_cancellation_submit' );

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

		$requires_manual = rytkoset_theme_order_cancellation_requires_manual_handling( $order );
		$has_physical    = rytkoset_theme_order_has_physical_products( $order );
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
				</tbody>
			</table>

			<?php if ( $requires_manual ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Tilaus on jo maksettu. Peruutuspyyntösi välitetään käsiteltäväksi, ja mahdollinen palautus hoidetaan manuaalisesti. Olemme tarvittaessa sinuun yhteydessä.', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $requires_manual && $has_physical ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Fyysisen tuotteen 14 vuorokauden palautusaika lasketaan tuotteen vastaanottamisesta. Ylläpitäjä tarkistaa määräajan ja palautuskelpoisuuden pyyntösi käsittelyn yhteydessä.', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( rytkoset_theme_order_has_cancellation_exception_products( $order ) ) : ?>
				<div class="woocommerce-info">
					<?php esc_html_e( 'Huomaa: jäsenyyksien ja tapahtumamaksujen peruutusoikeus voi olla rajoitettu, jos palvelu on jo alkanut. Tarkistamme tilanteen pyyntösi yhteydessä.', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" class="rytkoset-cancel-order-form">
				<?php wp_nonce_field( 'rytkoset_confirm_cancel_order_' . $order->get_id() ); ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
				<button type="submit" name="rytkoset_confirm_cancellation" value="1" class="button rytkoset-cancel-order">
					<?php esc_html_e( 'Vahvista peruutus', 'rytkoset-theme' ); ?>
				</button>
				<a class="button rytkoset-cancel-order-back" href="<?php echo esc_url( $orders_url ); ?>">
					<?php esc_html_e( 'Palaa takaisin', 'rytkoset-theme' ); ?>
				</a>
			</form>
		</div>
		<?php
	}
}
add_action( 'woocommerce_account_rytkoset_cancel_order_endpoint', 'rytkoset_theme_render_order_cancellation_endpoint' );

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
			$lines[] = __( 'Tilaus on jo maksettu. Käsittelemme mahdollisen palautuksen manuaalisesti ja olemme tarvittaessa sinuun yhteydessä. Jäsenyyksien ja tapahtumamaksujen peruutusoikeus voi olla rajoitettu, jos palvelu on jo alkanut.', 'rytkoset-theme' );

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
			__( 'Peruutuspyyntö: tilaus %s vaatii palautuksen', 'rytkoset-theme' ),
			$order_number
		);

		$lines = array(
			sprintf(
				/* translators: %s: order number. */
				__( 'Asiakas on pyytänyt maksetun tilauksen %s peruutusta.', 'rytkoset-theme' ),
				$order_number
			),
			sprintf(
				/* translators: %s: request date and time. */
				__( 'Pyynnön aikaleima: %s', 'rytkoset-theme' ),
				$datetime_label
			),
			'',
			__( 'Tilauksen status on jätetty ennalleen. Käsittele palautus ja lopullinen peruutus manuaalisesti WooCommercessa. Huomioi mahdolliset peruutusoikeuden poikkeukset (jäsenyydet, tapahtumat).', 'rytkoset-theme' ),
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
