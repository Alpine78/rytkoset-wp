<?php
/**
 * Mollie WooCommerce -integraation suomennokset, RF-viitteiden normalisointi ja maksuohjeiden output-bufferointi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kääntää valitut Mollie WooCommerce -tekstit suomeksi.
 *
 * @param string $translated Alkuperäinen käännös.
 * @param string $original   Lähdeteksti.
 * @param string $domain     Tekstidomain.
 * @return string
 */
function rytkoset_theme_mollie_finnish_strings( $translated, $original, $domain ) {
	if ( ! in_array( $domain, array( 'mollie-payments-for-woocommerce', 'woocommerce', 'woocommerce-payments' ), true ) ) {
		return $translated;
	}

	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	if ( 0 !== strpos( (string) $locale, 'fi' ) ) {
		return $translated;
	}

	$map = array(
		', payment pending.' => ', maksu odottaa vahvistusta.',
		'Your payment was not successful. Please complete your order with a different payment method.' => 'Maksu ei onnistunut. Viimeistele tilaus valitsemalla toinen maksutapa.',
		'Please complete your payment by transferring the total amount to the following bank account:' => 'Viimeistele maksu siirtämällä koko summa seuraavalle pankkitilille:',
		'Beneficiary: %s' => 'Saaja: %s',
		'Payment reference: %s' => 'Viite: %s',
		'Please provide the payment reference <strong>%s</strong>' => 'Käytä maksussa viitettä <strong>%s</strong>',
		'The payment will expire on <strong>%s</strong>.' => 'Maksu vanhenee <strong>%s</strong>.',
		'The payment will expire on <strong>%s</strong>. Please make sure you transfer the total amount before this date.' => 'Maksu vanhenee <strong>%s</strong>. Varmista, että siirrät koko summan ennen tätä päivää.',
		'Pay by Bank' => 'Verkkopankkimaksu',
		'Name on card' => 'Kortinhaltijan nimi',
		'Card number' => 'Kortin numero',
		'Expiry date' => 'Voimassaoloaika',
		'Secure payments provided by' => 'Turvallisen maksun tarjoaa',
		'%1$s Secure payments provided by %2$s' => '%1$s Turvallisen maksun tarjoaa %2$s',
		'Payment completed by <strong>%1$s</strong> (IBAN (last 4 digits): %2$s, BIC: %3$s)' => 'Maksu suoritettu tililtä <strong>%1$s</strong> (IBAN, 4 viimeistä merkkiä: %2$s, BIC: %3$s)',
		'%1$s payment pending (%2$s).' => '%1$s: maksu odottaa vahvistusta (%2$s).',
		'%1$s payment still pending (%2$s) but customer already returned to the store. Status should be updated automatically in the future, if it doesn\'t this might indicate a communication issue between the site and Mollie.' => '%1$s: maksu odottaa edelleen vahvistusta (%2$s), vaikka asiakas on jo palannut kauppaan. Tilan pitäisi päivittyä automaattisesti myöhemmin. Jos näin ei käy, sivuston ja Mollien välillä voi olla yhteysongelma.',
		'test mode' => 'testitila',
	);

	if ( isset( $map[ $original ] ) ) {
		return $map[ $original ];
	}

	return $translated;
}
add_filter( 'gettext', 'rytkoset_theme_mollie_finnish_strings', 10, 3 );

/**
 * Keeps selected Mollie gateway titles understandable for Finnish checkout users.
 *
 * @param string $title      Payment gateway title.
 * @param string $gateway_id Payment gateway ID.
 * @return string
 */
function rytkoset_theme_mollie_gateway_title( $title, $gateway_id ) {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	if ( 0 !== strpos( (string) $locale, 'fi' ) ) {
		return $title;
	}

	if ( 'mollie_wc_gateway_paybybank' === $gateway_id ) {
		return __( 'Verkkopankkimaksu', 'rytkoset-theme' );
	}

	return $title;
}
add_filter( 'woocommerce_gateway_title', 'rytkoset_theme_mollie_gateway_title', 10, 2 );

/**
 * Forces Mollie Components to use Finnish locale when WordPress reports plain "fi".
 *
 * Mollie Components accepts "fi_FI", while WordPress can store the Finnish site
 * locale as "fi". Without this adapter the card input iframe falls back to en_US.
 *
 * @return void
 */
function rytkoset_theme_mollie_components_finnish_locale() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	if ( 0 !== strpos( (string) $locale, 'fi' ) ) {
		return;
	}

	$script = <<<'JS'
if (window.mollieServerData && window.mollieServerData.componentData && window.mollieServerData.componentData.options) {
	window.mollieServerData.componentData.options.locale = 'fi_FI';
}
JS;

	wp_add_inline_script( 'mollie_block_index', $script, 'before' );
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_mollie_components_finnish_locale', 20 );

/**
 * Returns true when the order uses a Mollie payment method with RF instructions.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_is_mollie_rf_reference_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	return in_array(
		$order->get_payment_method(),
		array(
			'mollie_wc_gateway_banktransfer',
			'mollie_wc_gateway_paybybank',
		),
		true
	);
}

/**
 * Normalizes RF references shown in Mollie payment instructions.
 *
 * Some bank UIs reject RFC 11649 references when Mollie formats them with separators.
 * Keep the IBAN grouped, but show the payment reference as plain alphanumeric text.
 *
 * @param string $text Instruction text or rendered markup.
 * @return string
 */
function rytkoset_theme_normalize_mollie_rf_references( $text ) {
	if ( ! is_string( $text ) || '' === $text || false === stripos( $text, 'RF' ) ) {
		return $text;
	}

	return preg_replace_callback(
		'/\bRF\d{2}(?:[-\s]?[A-Z0-9]{4})+\b/i',
		static function ( $matches ) {
			return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $matches[0] ) );
		},
		$text
	);
}

/**
 * Stores normalized Mollie instruction text for later admin views.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_normalize_saved_mollie_payment_instructions( $order ) {
	if ( ! rytkoset_theme_is_mollie_rf_reference_order( $order ) ) {
		return;
	}

	$instructions = $order->get_meta( '_mollie_payment_instructions', true );

	if ( ! is_string( $instructions ) || '' === $instructions ) {
		return;
	}

	$normalized = rytkoset_theme_normalize_mollie_rf_references( $instructions );

	if ( $normalized === $instructions ) {
		return;
	}

	$order->update_meta_data( '_mollie_payment_instructions', $normalized );
	$order->save();
}

/**
 * Starts output buffering around Mollie instruction rendering hooks.
 *
 * @param string         $context Unique context key.
 * @param WC_Order|mixed $order   WooCommerce order object.
 * @return void
 */
function rytkoset_theme_start_mollie_instruction_buffer( $context, $order ) {
	global $rytkoset_theme_mollie_instruction_buffers;

	if ( ! rytkoset_theme_is_mollie_rf_reference_order( $order ) ) {
		return;
	}

	if ( ! is_array( $rytkoset_theme_mollie_instruction_buffers ) ) {
		$rytkoset_theme_mollie_instruction_buffers = array();
	}

	$buffer_key = $context . ':' . $order->get_id();

	if ( ! empty( $rytkoset_theme_mollie_instruction_buffers[ $buffer_key ] ) ) {
		return;
	}

	ob_start();
	$rytkoset_theme_mollie_instruction_buffers[ $buffer_key ] = true;
}

/**
 * Ends output buffering around Mollie instruction rendering hooks.
 *
 * @param string         $context Unique context key.
 * @param WC_Order|mixed $order   WooCommerce order object.
 * @return void
 */
function rytkoset_theme_end_mollie_instruction_buffer( $context, $order ) {
	global $rytkoset_theme_mollie_instruction_buffers;

	if ( ! rytkoset_theme_is_mollie_rf_reference_order( $order ) || ! is_array( $rytkoset_theme_mollie_instruction_buffers ) ) {
		return;
	}

	$buffer_key = $context . ':' . $order->get_id();

	if ( empty( $rytkoset_theme_mollie_instruction_buffers[ $buffer_key ] ) ) {
		return;
	}

	unset( $rytkoset_theme_mollie_instruction_buffers[ $buffer_key ] );

	$output = ob_get_clean();

	if ( false === $output ) {
		return;
	}

	echo rytkoset_theme_normalize_mollie_rf_references( $output ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	rytkoset_theme_normalize_saved_mollie_payment_instructions( $order );
}

/**
 * Starts buffering for Mollie thank-you page instructions.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function rytkoset_theme_start_mollie_thankyou_instruction_buffer( $order_id ) {
	rytkoset_theme_start_mollie_instruction_buffer( 'thankyou', wc_get_order( $order_id ) );
}

/**
 * Ends buffering for Mollie thank-you page instructions.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function rytkoset_theme_end_mollie_thankyou_instruction_buffer( $order_id ) {
	rytkoset_theme_end_mollie_instruction_buffer( 'thankyou', wc_get_order( $order_id ) );
}

/**
 * Starts buffering for Mollie order details instructions.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_start_mollie_order_details_instruction_buffer( $order ) {
	rytkoset_theme_start_mollie_instruction_buffer( 'order-details', $order );
}

/**
 * Ends buffering for Mollie order details instructions.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_end_mollie_order_details_instruction_buffer( $order ) {
	rytkoset_theme_end_mollie_instruction_buffer( 'order-details', $order );
}

/**
 * Starts buffering for Mollie email instructions.
 *
 * @param WC_Order|mixed $order         WooCommerce order object.
 * @param bool           $sent_to_admin Whether the email targets admin.
 * @param bool           $plain_text    Whether the email is plain text.
 * @return void
 */
function rytkoset_theme_start_mollie_email_instruction_buffer( $order, $sent_to_admin, $plain_text ) {
	unset( $sent_to_admin, $plain_text );
	rytkoset_theme_start_mollie_instruction_buffer( 'email', $order );
}

/**
 * Ends buffering for Mollie email instructions.
 *
 * @param WC_Order|mixed $order         WooCommerce order object.
 * @param bool           $sent_to_admin Whether the email targets admin.
 * @param bool           $plain_text    Whether the email is plain text.
 * @return void
 */
function rytkoset_theme_end_mollie_email_instruction_buffer( $order, $sent_to_admin, $plain_text ) {
	unset( $sent_to_admin, $plain_text );
	rytkoset_theme_end_mollie_instruction_buffer( 'email', $order );
}

/**
 * Extracts a normalized RF reference from saved Mollie instructions.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_mollie_rf_reference( $order ) {
	if ( ! rytkoset_theme_is_mollie_rf_reference_order( $order ) ) {
		return '';
	}

	$instructions = $order->get_meta( '_mollie_payment_instructions', true );

	if ( ! is_string( $instructions ) || '' === $instructions ) {
		return '';
	}

	$normalized = rytkoset_theme_normalize_mollie_rf_references( $instructions );

	if ( preg_match( '/\bRF\d{2}[A-Z0-9]{5,}\b/i', $normalized, $matches ) ) {
		return strtoupper( $matches[0] );
	}

	return '';
}

/**
 * Renders a Finnish bank transfer note for Mollie RF references.
 *
 * Mollie's own bank transfer email is sent outside WordPress, so show the
 * Finnish bank-specific guidance in WooCommerce-controlled customer views.
 *
 * @param WC_Order|mixed $order      WooCommerce order object.
 * @param string         $context    Render context.
 * @param bool           $plain_text Whether the output is plain text email.
 * @return void
 */
function rytkoset_theme_render_mollie_rf_reference_notice( $order, $context = 'screen', $plain_text = false ) {
	static $rendered = array();

	if ( ! rytkoset_theme_is_mollie_rf_reference_order( $order ) ) {
		return;
	}

	// Screen contexts (thank-you page and order details) render on the same
	// request, so dedupe them with a shared key and keep email separate.
	$scope = ( 'email' === $context ) ? 'email' : 'screen';
	$key   = $scope . ':' . $order->get_id();

	if ( isset( $rendered[ $key ] ) ) {
		return;
	}

	$rendered[ $key ] = true;

	$reference      = rytkoset_theme_get_mollie_rf_reference( $order );
	$example_source = 'RF98-1937-5848-0918';
	$example_target = rytkoset_theme_normalize_mollie_rf_references( $example_source );

	if ( '' !== $reference ) {
		$reference_instruction = sprintf(
			/* translators: %s: normalized RF payment reference. */
			__( 'Käytä pankissa viitettä %s.', 'rytkoset-theme' ),
			$reference
		);
	} else {
		$reference_instruction = sprintf(
			/* translators: 1: formatted RF reference, 2: normalized RF reference. */
			__( 'Esimerkiksi %1$s kirjoitetaan pankkiin muodossa %2$s.', 'rytkoset-theme' ),
			$example_source,
			$example_target
		);
	}

	$message = sprintf(
		/* translators: %s: RF reference instruction. */
		__( 'Maksunvälittäjänä toimii Mollie, ja tilisiirto maksetaan hollantilaiseen pankkiin – tämä on normaalia. Jos Mollien maksusähköpostissa viite näkyy väliviivoilla, poista väliviivat ennen kuin syötät viitteen suomalaiseen verkkopankkiin. %s Syötä RF-viite maksun viitenumeroksi, jos pankki hyväksyy RF-viitteen.', 'rytkoset-theme' ),
		$reference_instruction
	);

	if ( $plain_text ) {
		echo "\n" . esc_html__( 'Tärkeää suomalaisissa pankeissa:', 'rytkoset-theme' ) . "\n";
		echo esc_html( $message ) . "\n";
		return;
	}

	// Avoid WooCommerce's `woocommerce-info` class: its absolutely positioned
	// ::before icon overlaps the bold heading. Use a self-contained style.
	$style = 'margin:16px 0;padding:16px 20px;background:#f6f5f8;border-top:3px solid #8fae1b;color:#1d2327;border-radius:4px;';

	echo '<div class="rytkoset-mollie-rf-notice" style="' . esc_attr( $style ) . '">';
	echo '<strong>' . esc_html__( 'Tärkeää suomalaisissa pankeissa:', 'rytkoset-theme' ) . '</strong> ';
	echo esc_html( $message );
	echo '</div>';
}

/**
 * Renders a notice confirming that a Mollie payment confirmation email was sent.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function rytkoset_theme_render_mollie_thankyou_email_sent_notice( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$billing_email = $order->get_billing_email();
	$style         = 'margin:16px 0;padding:16px 20px;background:#eef6e8;border-top:3px solid #4a7c00;color:#1d2327;border-radius:4px;';

	echo '<div class="rytkoset-mollie-email-sent-notice" style="' . esc_attr( $style ) . '">';

	if ( '' !== $billing_email ) {
		echo esc_html(
			sprintf(
				/* translators: %s: customer billing email address. */
				__( 'Lasku ja maksutiedot on lähetetty sähköpostiisi %s.', 'rytkoset-theme' ),
				$billing_email
			)
		);
	} else {
		echo esc_html__( 'Lasku ja maksutiedot on lähetetty sähköpostiisi.', 'rytkoset-theme' );
	}

	echo ' ';
	echo esc_html__( 'Lähettäjä on Rytkösten sukuseura ry, sähköpostiosoite noreply@mollie.com. Jos viesti ei näy saapuneissa, tarkista roskapostisuodatin.', 'rytkoset-theme' );
	echo '</div>';
}

/**
 * Renders the RF reference notice on Mollie thank-you pages.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function rytkoset_theme_render_mollie_thankyou_rf_reference_notice( $order_id ) {
	rytkoset_theme_render_mollie_rf_reference_notice( wc_get_order( $order_id ), 'thankyou' );
}

/**
 * Renders the RF reference notice on customer order detail pages.
 *
 * @param WC_Order|mixed $order WooCommerce order object.
 * @return void
 */
function rytkoset_theme_render_mollie_order_details_rf_reference_notice( $order ) {
	rytkoset_theme_render_mollie_rf_reference_notice( $order, 'order-details' );
}

/**
 * Renders the RF reference notice in customer emails.
 *
 * @param WC_Order|mixed $order         WooCommerce order object.
 * @param bool           $sent_to_admin Whether the email targets admin.
 * @param bool           $plain_text    Whether the email is plain text.
 * @return void
 */
function rytkoset_theme_render_mollie_email_rf_reference_notice( $order, $sent_to_admin, $plain_text ) {
	if ( $sent_to_admin ) {
		return;
	}

	rytkoset_theme_render_mollie_rf_reference_notice( $order, 'email', $plain_text );
}

add_action( 'woocommerce_thankyou_mollie_wc_gateway_banktransfer', 'rytkoset_theme_render_mollie_thankyou_email_sent_notice', 8 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_banktransfer', 'rytkoset_theme_start_mollie_thankyou_instruction_buffer', 9 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_banktransfer', 'rytkoset_theme_end_mollie_thankyou_instruction_buffer', 11 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_banktransfer', 'rytkoset_theme_render_mollie_thankyou_rf_reference_notice', 12 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_paybybank', 'rytkoset_theme_render_mollie_thankyou_email_sent_notice', 8 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_paybybank', 'rytkoset_theme_start_mollie_thankyou_instruction_buffer', 9 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_paybybank', 'rytkoset_theme_end_mollie_thankyou_instruction_buffer', 11 );
add_action( 'woocommerce_thankyou_mollie_wc_gateway_paybybank', 'rytkoset_theme_render_mollie_thankyou_rf_reference_notice', 12 );
add_action( 'woocommerce_order_details_after_order_table', 'rytkoset_theme_start_mollie_order_details_instruction_buffer', 9 );
add_action( 'woocommerce_order_details_after_order_table', 'rytkoset_theme_end_mollie_order_details_instruction_buffer', 11 );
add_action( 'woocommerce_order_details_after_order_table', 'rytkoset_theme_render_mollie_order_details_rf_reference_notice', 12 );
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_start_mollie_email_instruction_buffer', 9, 3 );
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_end_mollie_email_instruction_buffer', 11, 3 );
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_render_mollie_email_rf_reference_notice', 12, 3 );
add_action( 'woocommerce_email_order_meta', 'rytkoset_theme_start_mollie_email_instruction_buffer', 9, 3 );
add_action( 'woocommerce_email_order_meta', 'rytkoset_theme_end_mollie_email_instruction_buffer', 11, 3 );
