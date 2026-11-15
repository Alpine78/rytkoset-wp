<?php
/**
 * Proof of email control for user accounts (#744).
 *
 * Membership benefits that are matched to an account purely by email address
 * (pending manual memberships, awaiting guest membership orders and family
 * member rows) must only reach an account whose holder has proven control of
 * that address. Creating an account is not such proof: WooCommerce checkout,
 * the My Account registration form and the order confirmation account form
 * all create an account for any address and log the visitor in immediately,
 * and the My Account details form changes the address without confirmation.
 *
 * The proof is stored as the verified address itself, so it invalidates
 * automatically when the account email changes. It is recorded only by
 * events that require a link mailed to the account's current address:
 * a completed password reset (WordPress and WooCommerce set/reset password
 * links, including the link of a new wp-login.php registration) and
 * WooCommerce's own customer email verification link. WooCommerce fires the
 * same verified action when a shop manager ticks the verification box on a
 * user's profile; that is accepted as a deliberate attestation by a trusted
 * role (`manage_woocommerce`), which can set memberships by hand anyway.
 *
 * Accounts without a stored proof are unverified. Benefits they already hold
 * are untouched; only new email-based matching waits for the proof.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_get_verified_email_meta_key' ) ) {
	/**
	 * Returns the user meta key storing the address the user has proven control of.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_verified_email_meta_key() {
		return 'rytkoset_verified_email';
	}
}

if ( ! function_exists( 'rytkoset_theme_normalize_verified_email' ) ) {
	/**
	 * Normalizes an email address for the verified-address comparison.
	 *
	 * @param string $email Email address.
	 * @return string Lower-cased address, or an empty string when invalid.
	 */
	function rytkoset_theme_normalize_verified_email( $email ) {
		$email = strtolower( trim( (string) $email ) );

		return is_email( $email ) ? $email : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_user_email_is_verified' ) ) {
	/**
	 * Returns true when the user has proven control of their current account email.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	function rytkoset_theme_user_email_is_verified( $user_id ) {
		$user = get_userdata( absint( $user_id ) );

		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$current  = rytkoset_theme_normalize_verified_email( $user->user_email );
		$verified = get_user_meta( $user->ID, rytkoset_theme_get_verified_email_meta_key(), true );

		return '' !== $current && is_string( $verified ) && $current === $verified;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_verified_user_by_email' ) ) {
	/**
	 * Returns the account for an email address only when its holder has proven the address.
	 *
	 * Use this instead of get_user_by( 'email' ) wherever a benefit is attached
	 * to an account because of its email address alone.
	 *
	 * @param string $email Email address.
	 * @return WP_User|false
	 */
	function rytkoset_theme_get_verified_user_by_email( $email ) {
		$email = trim( (string) $email );

		if ( '' === $email ) {
			return false;
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user instanceof WP_User || ! rytkoset_theme_user_email_is_verified( $user->ID ) ) {
			return false;
		}

		return $user;
	}
}

if ( ! function_exists( 'rytkoset_theme_mark_user_email_verified' ) ) {
	/**
	 * Records that the user has proven control of their current account email.
	 *
	 * Must only be called from events that required a link mailed to the
	 * account's current address, or from a trusted administrator's explicit
	 * attestation. Fires `rytkoset_theme_user_email_verified`
	 * once per unverified to verified transition so pending email-matched
	 * benefits can be applied.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when the account became verified by this call.
	 */
	function rytkoset_theme_mark_user_email_verified( $user_id ) {
		$user = get_userdata( absint( $user_id ) );

		if ( ! $user instanceof WP_User || rytkoset_theme_user_email_is_verified( $user->ID ) ) {
			return false;
		}

		$email = rytkoset_theme_normalize_verified_email( $user->user_email );

		if ( '' === $email ) {
			return false;
		}

		update_user_meta( $user->ID, rytkoset_theme_get_verified_email_meta_key(), $email );

		/**
		 * Fires after a user has proven control of their current account email.
		 *
		 * @param int $user_id User ID.
		 */
		do_action( 'rytkoset_theme_user_email_verified', (int) $user->ID );

		return true;
	}
}

if ( ! function_exists( 'rytkoset_theme_clear_stale_verified_email' ) ) {
	/**
	 * Drops the stored proof when it no longer matches the account email.
	 *
	 * The comparison in rytkoset_theme_user_email_is_verified() already ignores a
	 * stale proof. Deleting it also keeps an old proof from becoming valid again
	 * when the account later returns to the earlier address: that return must be
	 * proven anew so the verified event fires and pending benefits are applied.
	 *
	 * @param int|mixed $user_id Updated user ID.
	 * @return bool True when a stale proof was removed.
	 */
	function rytkoset_theme_clear_stale_verified_email( $user_id ) {
		$user = get_userdata( absint( $user_id ) );

		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$meta_key = rytkoset_theme_get_verified_email_meta_key();
		$verified = get_user_meta( $user->ID, $meta_key, true );

		if ( ! is_string( $verified ) || '' === $verified || rytkoset_theme_user_email_is_verified( $user->ID ) ) {
			return false;
		}

		delete_user_meta( $user->ID, $meta_key );

		return true;
	}
	add_action( 'profile_update', 'rytkoset_theme_clear_stale_verified_email' );
}

if ( ! function_exists( 'rytkoset_theme_mark_user_email_verified_after_password_reset' ) ) {
	/**
	 * Treats a completed emailed password reset as proof of email control.
	 *
	 * `after_password_reset` fires only after a reset key mailed to the account
	 * address was presented (wp-login.php and WooCommerce set/reset password
	 * forms). WordPress clears an issued key when the account email changes, so
	 * a key mailed to an earlier address cannot verify a later one. A password
	 * change on the account details form does not fire this action.
	 *
	 * @param WP_User|mixed $user User whose password was reset.
	 * @return void
	 */
	function rytkoset_theme_mark_user_email_verified_after_password_reset( $user ) {
		if ( $user instanceof WP_User ) {
			rytkoset_theme_mark_user_email_verified( (int) $user->ID );
		}
	}
	add_action( 'after_password_reset', 'rytkoset_theme_mark_user_email_verified_after_password_reset' );
}

if ( ! function_exists( 'rytkoset_theme_mark_user_email_verified_from_woocommerce' ) ) {
	/**
	 * Mirrors WooCommerce's customer email verification.
	 *
	 * WooCommerce fires the action for its emailed verify link and when a shop
	 * manager confirms the address on the user's profile screen. The second is an
	 * intentional, accepted administrator attestation.
	 *
	 * @param int|mixed $user_id Verified customer user ID.
	 * @return void
	 */
	function rytkoset_theme_mark_user_email_verified_from_woocommerce( $user_id ) {
		if ( is_numeric( $user_id ) ) {
			rytkoset_theme_mark_user_email_verified( (int) $user_id );
		}
	}
	add_action( 'woocommerce_customer_email_verified', 'rytkoset_theme_mark_user_email_verified_from_woocommerce' );
}

if ( ! function_exists( 'rytkoset_theme_get_email_verification_instruction_lines' ) ) {
	/**
	 * Returns the shared plain-text instructions for activating an email-matched benefit.
	 *
	 * The wording covers both an address without an account and an address whose
	 * account has not proven the address yet, so a message never has to reveal
	 * which one applies.
	 *
	 * @param string $email Recipient email address.
	 * @return string[]
	 */
	function rytkoset_theme_get_email_verification_instruction_lines( $email ) {
		return array(
			sprintf(
				/* translators: %s: recipient email address. */
				__( 'Jos sinulla ei vielä ole käyttäjätiliä, luo tili tällä samalla sähköpostiosoitteella (%s) ja aseta tilille salasana sähköpostiisi saapuvan linkin kautta:', 'rytkoset-theme' ),
				$email
			),
			wp_registration_url(),
			'',
			__( 'Jos sinulla on jo tili tällä sähköpostiosoitteella, vahvista osoite tilaamalla salasanan vaihtolinkki ja asettamalla salasana sen kautta:', 'rytkoset-theme' ),
			wp_lostpassword_url(),
			'',
			__( 'Edut kytkeytyvät tiliisi automaattisesti, kun olet asettanut salasanan sähköpostiin saapuneen linkin kautta. Näin varmistamme, että sähköpostiosoite on sinun hallinnassasi.', 'rytkoset-theme' ),
		);
	}
}
