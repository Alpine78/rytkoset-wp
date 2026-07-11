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
			'rytkoset_membership' => 'user',
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

if ( ! function_exists( 'rytkoset_theme_get_account_membership_endpoint' ) ) {
	/**
	 * Returns the membership My Account endpoint key.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_membership_endpoint() {
		return 'rytkoset_membership';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_membership_endpoint_slug' ) ) {
	/**
	 * Returns the membership My Account endpoint slug.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_membership_endpoint_slug() {
		return 'jasenyys';
	}
}

if ( ! function_exists( 'rytkoset_theme_register_account_membership_endpoint_query_var' ) ) {
	/**
	 * Registers the membership endpoint with WooCommerce.
	 *
	 * @param array<string, string> $vars WooCommerce endpoint query vars.
	 * @return array<string, string>
	 */
	function rytkoset_theme_register_account_membership_endpoint_query_var( $vars ) {
		$vars[ rytkoset_theme_get_account_membership_endpoint() ] = rytkoset_theme_get_account_membership_endpoint_slug();

		return $vars;
	}
}
add_filter( 'woocommerce_get_query_vars', 'rytkoset_theme_register_account_membership_endpoint_query_var' );

if ( ! function_exists( 'rytkoset_theme_get_account_membership_endpoint_rewrite_version' ) ) {
	/**
	 * Returns the guarded rewrite version for the membership endpoint.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_membership_endpoint_rewrite_version() {
		return rytkoset_theme_get_account_membership_endpoint() . ':' . rytkoset_theme_get_account_membership_endpoint_slug() . ':v1';
	}
}

if ( ! function_exists( 'rytkoset_theme_account_membership_endpoint_rewrite_rules_exist' ) ) {
	/**
	 * Checks whether stored rewrite rules contain the membership endpoint.
	 *
	 * @return bool
	 */
	function rytkoset_theme_account_membership_endpoint_rewrite_rules_exist() {
		$rules = get_option( 'rewrite_rules', array() );

		if ( ! is_array( $rules ) ) {
			return false;
		}

		$endpoint_slug = rytkoset_theme_get_account_membership_endpoint_slug();

		foreach ( array_keys( $rules ) as $regex ) {
			if ( false !== strpos( (string) $regex, $endpoint_slug ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'rytkoset_theme_maybe_flush_account_membership_endpoint' ) ) {
	/**
	 * Flushes rewrite rules once when the membership endpoint changes.
	 *
	 * @return void
	 */
	function rytkoset_theme_maybe_flush_account_membership_endpoint() {
		if (
			rytkoset_theme_get_account_membership_endpoint_rewrite_version() === get_option( 'rytkoset_theme_account_membership_endpoint_flushed' )
			&& rytkoset_theme_account_membership_endpoint_rewrite_rules_exist()
		) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'rytkoset_theme_account_membership_endpoint_flushed', rytkoset_theme_get_account_membership_endpoint_rewrite_version() );
	}
}
add_action( 'init', 'rytkoset_theme_maybe_flush_account_membership_endpoint', 99 );

if ( ! function_exists( 'rytkoset_theme_flush_account_membership_endpoint_on_activation' ) ) {
	/**
	 * Flushes membership endpoint rewrite rules when the theme is activated.
	 *
	 * @return void
	 */
	function rytkoset_theme_flush_account_membership_endpoint_on_activation() {
		delete_option( 'rytkoset_theme_account_membership_endpoint_flushed' );
		flush_rewrite_rules( false );
		update_option( 'rytkoset_theme_account_membership_endpoint_flushed', rytkoset_theme_get_account_membership_endpoint_rewrite_version() );
	}
}
add_action( 'after_switch_theme', 'rytkoset_theme_flush_account_membership_endpoint_on_activation' );

if ( ! function_exists( 'rytkoset_theme_add_account_membership_menu_item' ) ) {
	/**
	 * Adds Membership to My Account before account details.
	 *
	 * @param array<string, string> $items WooCommerce My Account menu items.
	 * @return array<string, string>
	 */
	function rytkoset_theme_add_account_membership_menu_item( $items ) {
		$endpoint = rytkoset_theme_get_account_membership_endpoint();
		$label    = __( 'Jäsenyys', 'rytkoset-theme' );

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
add_filter( 'woocommerce_account_menu_items', 'rytkoset_theme_add_account_membership_menu_item' );

if ( ! function_exists( 'rytkoset_theme_get_account_membership_status' ) ) {
	/**
	 * Builds the user-facing status presentation for membership details.
	 *
	 * @param array<string, string> $membership Membership details.
	 * @return array{active:bool,label:string,description:string,variant:string}
	 */
	function rytkoset_theme_get_account_membership_status( $membership ) {
		$type = isset( $membership['type'] ) ? rytkoset_theme_normalize_user_membership_type( (string) $membership['type'] ) : '';

		if ( '' === $type ) {
			return array(
				'active'      => false,
				'label'       => __( 'Ei jäsenyyttä', 'rytkoset-theme' ),
				'description' => __( 'Sinulla ei ole tällä hetkellä voimassa olevaa jäsenyyttä.', 'rytkoset-theme' ),
				'variant'     => 'neutral',
			);
		}

		if ( 'lifetime' === $type ) {
			return array(
				'active'      => true,
				'label'       => __( 'Pysyvä jäsenyys', 'rytkoset-theme' ),
				'description' => __( 'Jäsenetusi ovat voimassa pysyvästi.', 'rytkoset-theme' ),
				'variant'     => 'done',
			);
		}

		$expires         = isset( $membership['expires'] ) ? (string) $membership['expires'] : '';
		$expires_display = rytkoset_theme_get_user_membership_expires_display( $expires );

		if ( rytkoset_theme_user_membership_is_active( $membership ) ) {
			return array(
				'active'      => true,
				'label'       => __( 'Voimassa', 'rytkoset-theme' ),
				'description' => '' !== $expires_display
					? sprintf(
						/* translators: %s: membership expiry date. */
						__( 'Jäsenetusi ovat voimassa %s asti.', 'rytkoset-theme' ),
						$expires_display
					)
					: __( 'Jäsenetusi ovat voimassa.', 'rytkoset-theme' ),
				'variant'     => 'done',
			);
		}

		if ( '' !== $expires_display && $expires < current_datetime()->format( 'Y-m-d' ) ) {
			return array(
				'active'      => false,
				'label'       => __( 'Vanhentunut', 'rytkoset-theme' ),
				'description' => sprintf(
					/* translators: %s: membership expiry date. */
					__( 'Jäsenyytesi päättyi %s.', 'rytkoset-theme' ),
					$expires_display
				),
				'variant'     => 'cancelled',
			);
		}

		return array(
			'active'      => false,
			'label'       => __( 'Ei voimassa', 'rytkoset-theme' ),
			'description' => __( 'Jäsenyyden voimassaoloa ei ole vahvistettu.', 'rytkoset-theme' ),
			'variant'     => 'cancelled',
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_family_member_status' ) ) {
	/**
	 * Builds the account-link status presentation for one family member row.
	 *
	 * @param array<string, mixed> $member Family member row.
	 * @return array{label:string,variant:string}
	 */
	function rytkoset_theme_get_account_family_member_status( $member ) {
		$is_linked = isset( $member['status'], $member['linked_user_id'] )
			&& 'active' === $member['status']
			&& absint( $member['linked_user_id'] ) > 0;

		return $is_linked
			? array(
				'label'   => __( 'Linkitetty käyttäjätiliin', 'rytkoset-theme' ),
				'variant' => 'done',
			)
			: array(
				'label'   => __( 'Odottaa käyttäjätiliä', 'rytkoset-theme' ),
				'variant' => 'pending',
			);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_membership_view_data' ) ) {
	/**
	 * Returns membership data limited to the requested user's own family relationship.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	function rytkoset_theme_get_account_membership_view_data( $user_id ) {
		$user_id              = absint( $user_id );
		$own_membership       = rytkoset_theme_get_user_membership( $user_id );
		$effective_membership = rytkoset_theme_get_effective_user_membership( $user_id );
		$is_inherited         = 'family' === $effective_membership['source'];
		$display_membership   = $is_inherited ? $effective_membership : $own_membership;
		$family_members       = array();

		if ( 'family' === $own_membership['type'] ) {
			foreach ( rytkoset_theme_get_family_members( $user_id ) as $index => $member ) {
				if ( 'removed' !== $member['status'] ) {
					// Preserve the original list index (not a sequential rebuild) so the
					// self-service edit/remove forms (below) can reference a row by its
					// real position in the stored list, matching the admin profile UI's
					// existing index-based field naming convention.
					$family_members[ $index ] = $member;
				}
			}
		}

		$primary_user = $is_inherited ? get_userdata( absint( $effective_membership['primary_user_id'] ) ) : false;

		return array(
			'own_membership'       => $own_membership,
			'effective_membership' => $effective_membership,
			'display_membership'   => $display_membership,
			'status'               => rytkoset_theme_get_account_membership_status( $display_membership ),
			'is_inherited'         => $is_inherited,
			'primary_user'         => $primary_user instanceof WP_User ? $primary_user : null,
			'family_members'       => $family_members,
			'is_family_primary'    => 'family' === $own_membership['type'],
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_membership_url' ) ) {
	/**
	 * Returns the membership endpoint URL.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_account_membership_url() {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( rytkoset_theme_get_account_membership_endpoint() );
		}

		return home_url( '/oma-tili/' . rytkoset_theme_get_account_membership_endpoint_slug() . '/' );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_family_member_nonce_action' ) ) {
	/**
	 * Returns the nonce action for a family member self-service form action.
	 *
	 * @param string $action One of 'add', 'edit', 'remove'.
	 * @return string
	 */
	function rytkoset_theme_get_account_family_member_nonce_action( $action ) {
		return 'rytkoset_account_family_' . sanitize_key( $action );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_membership_editing_row_index' ) ) {
	/**
	 * Returns the family member row index requested for inline editing via the URL, or -1.
	 *
	 * Reading the query arg has no side effect on its own — the rendering code only honors it
	 * for the primary account's own family list, so a crafted URL cannot expose another user's
	 * row indices (there is nothing keyed by index to leak; the index only selects which of the
	 * *viewer's own* rows renders in edit mode).
	 *
	 * @return int
	 */
	function rytkoset_theme_get_account_membership_editing_row_index() {
		if ( ! isset( $_GET['rytkoset_edit_member'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle, no state change.
			return -1;
		}

		return absint( wp_unslash( $_GET['rytkoset_edit_member'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle, no state change.
	}
}

if ( ! function_exists( 'rytkoset_theme_get_account_family_member_max_rows' ) ) {
	/**
	 * Returns the maximum number of family member rows a primary account may add self-service.
	 *
	 * Reuses the same checkout limit (#520, `rytkoset_theme_membership_max_member_rows`, default
	 * 6) that caps `annual_family` checkout member fields, so admins tune both surfaces from one
	 * filter. Checkout row 1 is the buyer's own name/email — the account-side family list excludes
	 * the primary account itself, so the family list cap is one less than the checkout total.
	 *
	 * @return int
	 */
	function rytkoset_theme_get_account_family_member_max_rows() {
		return max( 0, rytkoset_theme_get_membership_max_member_rows() - 1 );
	}
}

if ( ! function_exists( 'rytkoset_theme_apply_account_family_member_action' ) ) {
	/**
	 * Applies a self-service add/edit/remove action to a primary account's family member list.
	 *
	 * Pure decision function: builds the candidate list without touching user meta. The caller
	 * still saves the result through rytkoset_theme_update_family_members(), which re-normalizes
	 * and validates (duplicate email/user, self-link, already-linked-elsewhere) as the final
	 * guard. The pure action creates an unlinked candidate row; the shared save helper resolves
	 * its email to an existing account when possible (#542). If an edit changes the normalized
	 * email of a linked row, the old link is cleared so the shared save helper can revoke inherited
	 * benefits immediately and resolve the new address independently.
	 *
	 * @param array<int, array<string, mixed>> $members     Existing family member rows (all statuses).
	 * @param string                           $action      One of 'add', 'edit', 'remove'.
	 * @param int                              $index       Row index for 'edit'/'remove'.
	 * @param string                           $name        New name for 'add'/'edit'.
	 * @param string                           $email       New email for 'add'/'edit'.
	 * @param int                              $max_members Maximum non-removed rows allowed for 'add'
	 *                                                      (caller resolves this, typically via
	 *                                                      rytkoset_theme_get_account_family_member_max_rows()).
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	function rytkoset_theme_apply_account_family_member_action( $members, $action, $index, $name, $email, $max_members ) {
		$members = is_array( $members ) ? $members : array();
		$index   = absint( $index );

		if ( 'add' === $action ) {
			$name = sanitize_text_field( (string) $name );

			if ( '' === $name ) {
				return new WP_Error( 'rytkoset_family_member_name_required', __( 'Anna perheenjäsenen nimi.', 'rytkoset-theme' ) );
			}

			$email = trim( (string) $email );

			if ( '' !== $email && ! is_email( $email ) ) {
				return new WP_Error( 'rytkoset_family_member_email_invalid', __( 'Anna kelvollinen sähköpostiosoite.', 'rytkoset-theme' ) );
			}

			$email = rytkoset_theme_normalize_family_member_email( $email );

			$active_count = 0;

			foreach ( $members as $existing_member ) {
				if ( is_array( $existing_member ) && 'removed' !== ( $existing_member['status'] ?? '' ) ) {
					++$active_count;
				}
			}

			if ( $active_count >= absint( $max_members ) ) {
				return new WP_Error(
					'rytkoset_family_member_limit_reached',
					sprintf(
						/* translators: %d: maximum number of family members. */
						__( 'Perheenjäseniä voi olla enintään %d.', 'rytkoset-theme' ),
						absint( $max_members )
					)
				);
			}

			$members[] = array(
				'name'  => $name,
				'email' => $email,
			);

			return $members;
		}

		if ( ! in_array( $action, array( 'edit', 'remove' ), true ) ) {
			return new WP_Error( 'rytkoset_family_member_invalid_action', __( 'Tuntematon toiminto.', 'rytkoset-theme' ) );
		}

		if ( ! isset( $members[ $index ] ) || ! is_array( $members[ $index ] ) ) {
			return new WP_Error( 'rytkoset_family_member_not_found', __( 'Perheenjäsentä ei löytynyt.', 'rytkoset-theme' ) );
		}

		if ( 'edit' === $action ) {
			$name = sanitize_text_field( (string) $name );

			if ( '' === $name ) {
				return new WP_Error( 'rytkoset_family_member_name_required', __( 'Anna perheenjäsenen nimi.', 'rytkoset-theme' ) );
			}

			$email = trim( (string) $email );

			if ( '' !== $email && ! is_email( $email ) ) {
				return new WP_Error( 'rytkoset_family_member_email_invalid', __( 'Anna kelvollinen sähköpostiosoite.', 'rytkoset-theme' ) );
			}

			$email          = rytkoset_theme_normalize_family_member_email( $email );
			$previous_email = rytkoset_theme_normalize_family_member_email( (string) ( $members[ $index ]['email'] ?? '' ) );

			$members[ $index ]['name']  = $name;
			$members[ $index ]['email'] = $email;

			// Email identifies which account the row represents. If a linked row starts
			// representing another address, revoke the old link before the shared save helper
			// resolves the new address to an existing account (#542).
			if ( $email !== $previous_email && absint( $members[ $index ]['linked_user_id'] ?? 0 ) > 0 ) {
				$members[ $index ]['linked_user_id'] = 0;
				$members[ $index ]['status']         = 'pending_account';
			}

			return $members;
		}

		// 'remove': soft delete. Keeps the row (and its history) but
		// rytkoset_theme_update_family_members() clears the linked user's reverse meta when a row
		// leaves the active-linked state, so an inherited membership benefit is revoked
		// immediately. Matches the admin profile UI, which exposes 'removed' as an explicit status
		// rather than deleting rows outright.
		$members[ $index ]['status'] = 'removed';

		return $members;
	}
}

if ( ! function_exists( 'rytkoset_theme_handle_account_membership_family_submit' ) ) {
	/**
	 * Handles the Oma tili > Jäsenyys family member add/edit/remove forms.
	 *
	 * Only the family membership primary account may mutate its own list (own membership type
	 * must be 'family' — an inherited/effective family membership from someone else's primary
	 * account does not qualify).
	 *
	 * @return void
	 */
	function rytkoset_theme_handle_account_membership_family_submit() {
		if ( empty( $_POST['rytkoset_account_family_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['rytkoset_account_family_action'] ) );

		if ( ! in_array( $action, array( 'add', 'edit', 'remove' ), true ) ) {
			return;
		}

		$redirect_url = rytkoset_theme_get_account_membership_url();
		$user         = wp_get_current_user();

		if ( ! is_user_logged_in() || ! $user instanceof WP_User
			|| 'family' !== rytkoset_theme_get_user_membership( $user->ID )['type']
		) {
			wc_add_notice( __( 'Perheenjäseniä voi muokata vain perhejäsenyyden päätili.', 'rytkoset-theme' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, rytkoset_theme_get_account_family_member_nonce_action( $action ) ) ) {
			wc_add_notice( __( 'Toimintoa ei voitu vahvistaa. Yritä uudelleen.', 'rytkoset-theme' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$index = isset( $_POST['rytkoset_family_member_index'] ) ? absint( wp_unslash( $_POST['rytkoset_family_member_index'] ) ) : 0;
		$name  = isset( $_POST['rytkoset_family_member_name'] ) ? wp_unslash( $_POST['rytkoset_family_member_name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside rytkoset_theme_apply_account_family_member_action().
		$email = isset( $_POST['rytkoset_family_member_email'] ) ? wp_unslash( $_POST['rytkoset_family_member_email'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside rytkoset_theme_apply_account_family_member_action().

		$members = rytkoset_theme_get_family_members( $user->ID );
		$updated = rytkoset_theme_apply_account_family_member_action( $members, $action, $index, $name, $email, rytkoset_theme_get_account_family_member_max_rows() );

		if ( is_wp_error( $updated ) ) {
			wc_add_notice( $updated->get_error_message(), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$result = rytkoset_theme_update_family_members( $user->ID, $updated );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$messages = array(
			'add'    => __( 'Perheenjäsen lisättiin.', 'rytkoset-theme' ),
			'edit'   => __( 'Perheenjäsenen tiedot päivitettiin.', 'rytkoset-theme' ),
			'remove' => __( 'Perheenjäsen poistettiin.', 'rytkoset-theme' ),
		);

		wc_add_notice( $messages[ $action ], 'success' );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
add_action( 'template_redirect', 'rytkoset_theme_handle_account_membership_family_submit' );

if ( ! function_exists( 'rytkoset_theme_render_account_membership_endpoint' ) ) {
	/**
	 * Renders the My Account membership view, with self-service family member management for the
	 * family membership primary account.
	 *
	 * @return void
	 */
	function rytkoset_theme_render_account_membership_endpoint() {
		$user = wp_get_current_user();

		echo '<h2 class="rytkoset-account-h2">' . esc_html__( 'Jäsenyys', 'rytkoset-theme' ) . '</h2>';
		echo '<p class="rytkoset-account-lede">' . esc_html__( 'Näet tässä jäsenyytesi ja siihen liittyvät perheenjäsenet.', 'rytkoset-theme' ) . '</p>';

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			wc_print_notice( __( 'Kirjaudu sisään nähdäksesi jäsenyytesi.', 'rytkoset-theme' ), 'error' );
			return;
		}

		$data                = rytkoset_theme_get_account_membership_view_data( $user->ID );
		$membership          = $data['display_membership'];
		$status              = $data['status'];
		$type_label          = rytkoset_theme_get_user_membership_type_label( $membership['type'] );
		$expires_display     = rytkoset_theme_get_user_membership_expires_display( $membership['expires'] );
		$membership_page_url = home_url( '/sukuseura/jasenyys/' );
		?>
		<section class="rytkoset-account-membership" aria-labelledby="rytkoset-membership-summary-title">
			<div class="rytkoset-account-membership__head">
				<div>
					<h3 id="rytkoset-membership-summary-title" class="rytkoset-account-membership__title"><?php echo esc_html( $type_label ); ?></h3>
					<p class="rytkoset-account-membership__description"><?php echo esc_html( $status['description'] ); ?></p>
				</div>
				<span class="rytkoset-status-chip rytkoset-status-chip--<?php echo esc_attr( $status['variant'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
			</div>

			<dl class="rytkoset-account-membership__details">
				<div>
					<dt><?php esc_html_e( 'Jäsenyyden tyyppi', 'rytkoset-theme' ); ?></dt>
					<dd><?php echo esc_html( $type_label ); ?></dd>
				</div>
				<?php if ( '' !== $membership['period'] ) : ?>
					<div>
						<dt><?php esc_html_e( 'Jäsenkausi', 'rytkoset-theme' ); ?></dt>
						<dd><?php echo esc_html( $membership['period'] ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( 'lifetime' === $membership['type'] ) : ?>
					<div>
						<dt><?php esc_html_e( 'Voimassaolo', 'rytkoset-theme' ); ?></dt>
						<dd><?php esc_html_e( 'Pysyvä', 'rytkoset-theme' ); ?></dd>
					</div>
				<?php elseif ( '' !== $expires_display ) : ?>
					<div>
						<dt><?php esc_html_e( 'Voimassa asti', 'rytkoset-theme' ); ?></dt>
						<dd><?php echo esc_html( $expires_display ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>

			<?php if ( $data['is_inherited'] && $data['primary_user'] instanceof WP_User ) : ?>
				<div class="rytkoset-account-membership__family-source" role="note">
					<strong><?php esc_html_e( 'Jäsenedut perhejäsenyyden kautta', 'rytkoset-theme' ); ?></strong>
					<?php
					printf(
						/* translators: 1: primary account name, 2: primary account email. */
						esc_html__( 'Päätili: %1$s (%2$s). Vain päätilin käyttäjä voi hallita koko perhettä.', 'rytkoset-theme' ),
						esc_html( $data['primary_user']->display_name ),
						esc_html( $data['primary_user']->user_email )
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( ! $status['active'] ) : ?>
				<a class="button rytkoset-account-membership__action" href="<?php echo esc_url( $membership_page_url ); ?>"><?php esc_html_e( 'Tutustu jäsenyyteen', 'rytkoset-theme' ); ?></a>
			<?php endif; ?>
		</section>

		<?php if ( $data['is_family_primary'] ) : ?>
			<?php
			$membership_url = rytkoset_theme_get_account_membership_url();
			$editing_index  = rytkoset_theme_get_account_membership_editing_row_index();
			?>
			<section class="rytkoset-account-family" aria-labelledby="rytkoset-family-title">
				<h3 id="rytkoset-family-title" class="rytkoset-account-family__title"><?php esc_html_e( 'Perheenjäsenet', 'rytkoset-theme' ); ?></h3>

				<?php if ( ! empty( $data['family_members'] ) ) : ?>
					<div class="rytkoset-family-rows">
						<?php foreach ( $data['family_members'] as $index => $member ) : ?>
							<?php if ( $index === $editing_index ) : ?>
								<form method="post" action="<?php echo esc_url( $membership_url ); ?>" class="rytkoset-family-row rytkoset-family-row--editing">
									<?php wp_nonce_field( rytkoset_theme_get_account_family_member_nonce_action( 'edit' ) ); ?>
									<input type="hidden" name="rytkoset_account_family_action" value="edit" />
									<input type="hidden" name="rytkoset_family_member_index" value="<?php echo esc_attr( (string) $index ); ?>" />
									<div class="rytkoset-family-row__avatar" aria-hidden="true"><?php echo esc_html( rytkoset_theme_get_account_avatar_initials( $member['name'] ) ); ?></div>
									<div class="rytkoset-family-row__edit-fields">
										<p class="form-row">
											<label for="rytkoset_family_member_name_<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Nimi', 'rytkoset-theme' ); ?></label>
											<input type="text" class="input-text" id="rytkoset_family_member_name_<?php echo esc_attr( (string) $index ); ?>" name="rytkoset_family_member_name" value="<?php echo esc_attr( $member['name'] ); ?>" required />
										</p>
										<p class="form-row">
											<label for="rytkoset_family_member_email_<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Sähköposti (valinnainen)', 'rytkoset-theme' ); ?></label>
											<input type="email" class="input-text" id="rytkoset_family_member_email_<?php echo esc_attr( (string) $index ); ?>" name="rytkoset_family_member_email" value="<?php echo esc_attr( $member['email'] ); ?>" />
										</p>
										<div class="rytkoset-family-row__edit-actions">
											<button type="submit" class="button"><?php esc_html_e( 'Tallenna', 'rytkoset-theme' ); ?></button>
											<a class="button" href="<?php echo esc_url( $membership_url ); ?>"><?php esc_html_e( 'Peruuta', 'rytkoset-theme' ); ?></a>
										</div>
									</div>
								</form>
							<?php else : ?>
								<?php
								$member_status = rytkoset_theme_get_account_family_member_status( $member );

								/* translators: %s: family member name. */
								$edit_aria_label = sprintf( __( 'Muokkaa: %s', 'rytkoset-theme' ), $member['name'] );

								/* translators: %s: family member name. */
								$remove_aria_label = sprintf( __( 'Poista perheestä: %s', 'rytkoset-theme' ), $member['name'] );

								/* translators: %s: family member name. */
								$remove_confirm_message = sprintf( __( 'Poistetaanko %s perheenjäsenistä?', 'rytkoset-theme' ), $member['name'] );
								?>
								<div class="rytkoset-family-row">
									<div class="rytkoset-family-row__avatar <?php echo 'done' === $member_status['variant'] ? 'rytkoset-family-row__avatar--linked' : ''; ?>" aria-hidden="true"><?php echo esc_html( rytkoset_theme_get_account_avatar_initials( $member['name'] ) ); ?></div>
									<div class="rytkoset-family-row__id">
										<p class="rytkoset-family-row__name"><?php echo esc_html( $member['name'] ); ?></p>
										<p class="rytkoset-family-row__email"><?php echo '' !== $member['email'] ? esc_html( $member['email'] ) : esc_html__( 'Ei sähköpostia', 'rytkoset-theme' ); ?></p>
									</div>
									<span class="rytkoset-status-chip rytkoset-status-chip--<?php echo esc_attr( $member_status['variant'] ); ?>"><?php echo esc_html( $member_status['label'] ); ?></span>
									<div class="rytkoset-family-row__actions">
										<a class="rytkoset-icon-button" href="<?php echo esc_url( add_query_arg( 'rytkoset_edit_member', $index, $membership_url ) ); ?>" aria-label="<?php echo esc_attr( $edit_aria_label ); ?>">
											<?php echo rytkoset_theme_inline_icon( 'pencil', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta. ?>
										</a>
										<form method="post" action="<?php echo esc_url( $membership_url ); ?>">
											<?php wp_nonce_field( rytkoset_theme_get_account_family_member_nonce_action( 'remove' ) ); ?>
											<input type="hidden" name="rytkoset_account_family_action" value="remove" />
											<input type="hidden" name="rytkoset_family_member_index" value="<?php echo esc_attr( (string) $index ); ?>" />
											<button
												type="submit"
												class="rytkoset-icon-button rytkoset-icon-button--danger"
												aria-label="<?php echo esc_attr( $remove_aria_label ); ?>"
												onclick="return confirm(<?php echo esc_attr( wp_json_encode( $remove_confirm_message ) ); ?>);"
											>
												<?php echo rytkoset_theme_inline_icon( 'trash', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta. ?>
											</button>
										</form>
									</div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="rytkoset-account-family__empty"><?php esc_html_e( 'Perhejäsenyyteen ei ole vielä lisätty muita perheenjäseniä.', 'rytkoset-theme' ); ?></p>
				<?php endif; ?>

				<?php
				$max_members = rytkoset_theme_get_account_family_member_max_rows();
				$at_limit    = count( $data['family_members'] ) >= $max_members;
				?>
				<?php if ( $at_limit ) : ?>
					<p class="rytkoset-account-family__empty">
						<?php
						printf(
							/* translators: %d: maximum number of family members. */
							esc_html__( 'Perheenjäseniä voi olla enintään %d. Poista ensin joku perheenjäsen, jos haluat lisätä uuden.', 'rytkoset-theme' ),
							(int) $max_members
						);
						?>
					</p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $membership_url ); ?>" class="rytkoset-family-add">
						<?php wp_nonce_field( rytkoset_theme_get_account_family_member_nonce_action( 'add' ) ); ?>
						<input type="hidden" name="rytkoset_account_family_action" value="add" />
						<h4 class="rytkoset-family-add__title"><?php esc_html_e( 'Lisää perheenjäsen', 'rytkoset-theme' ); ?></h4>
						<p class="rytkoset-family-add__hint"><?php esc_html_e( 'Sähköposti on valinnainen — käyttäjätili linkittyy automaattisesti samalla sähköpostiosoitteella.', 'rytkoset-theme' ); ?></p>
						<p class="form-row">
							<label for="rytkoset_family_member_name_new"><?php esc_html_e( 'Nimi', 'rytkoset-theme' ); ?></label>
							<input type="text" class="input-text" id="rytkoset_family_member_name_new" name="rytkoset_family_member_name" required />
						</p>
						<p class="form-row">
							<label for="rytkoset_family_member_email_new"><?php esc_html_e( 'Sähköposti (valinnainen)', 'rytkoset-theme' ); ?></label>
							<input type="email" class="input-text" id="rytkoset_family_member_email_new" name="rytkoset_family_member_email" />
						</p>
						<button type="submit" class="button"><?php esc_html_e( 'Lisää', 'rytkoset-theme' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php
	}
}
add_action( 'woocommerce_account_rytkoset_membership_endpoint', 'rytkoset_theme_render_account_membership_endpoint' );

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
