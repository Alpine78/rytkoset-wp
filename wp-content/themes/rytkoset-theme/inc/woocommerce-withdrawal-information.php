<?php
/**
 * Statutory withdrawal instructions and form for WooCommerce customer emails.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns true when a product is an event product with no statutory withdrawal right.
 *
 * The known event products require performance at a specified time. Other event products can
 * extend the result through the filter without changing the email renderer.
 *
 * @param WC_Product $product WooCommerce product object.
 * @param WC_Order   $order   WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_product_is_withdrawal_exempt_event( $product, $order ) {
	$is_event = false;

	if ( $product instanceof WC_Product ) {
		$is_event = (
			function_exists( 'rytkoset_theme_is_tampere_2026_registration_product' )
			&& rytkoset_theme_is_tampere_2026_registration_product( $product )
		) || (
			function_exists( 'rytkoset_theme_is_bus_transport_product' )
			&& rytkoset_theme_is_bus_transport_product( $product )
		);
	}

	/**
	 * Filters whether a product is an event product excluded from the withdrawal right.
	 *
	 * @param bool       $is_event Whether the product is an excluded event product.
	 * @param WC_Product $product  WooCommerce product object.
	 * @param WC_Order   $order    WooCommerce order object.
	 */
	return (bool) apply_filters( 'rytkoset_theme_product_is_withdrawal_exempt_event', $is_event, $product, $order );
}

/**
 * Returns true when an order item has a statutory withdrawal right.
 *
 * Known specified-date event products are excluded. A digital magazine is excluded only when
 * the order records the customer's explicit consent to immediate delivery and acknowledgement
 * that the withdrawal right is lost. All other products default to included so that a new
 * product cannot silently omit mandatory information; the result remains filterable.
 *
 * @param WC_Product $product WooCommerce product object.
 * @param WC_Order   $order   WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_product_has_withdrawal_right( $product, $order ) {
	if ( ! $product instanceof WC_Product || ! $order instanceof WC_Order ) {
		return false;
	}

	$has_right = ! rytkoset_theme_product_is_withdrawal_exempt_event( $product, $order );

	if (
		$has_right
		&& function_exists( 'rytkoset_theme_get_magazine_id_by_product' )
		&& rytkoset_theme_get_magazine_id_by_product( $product->get_id() ) > 0
		&& function_exists( 'rytkoset_theme_order_has_digital_magazine_cancellation_consent' )
		&& rytkoset_theme_order_has_digital_magazine_cancellation_consent( $order )
	) {
		$has_right = false;
	}

	/**
	 * Filters whether an order item has a statutory withdrawal right.
	 *
	 * @param bool       $has_right Whether the product has a withdrawal right.
	 * @param WC_Product $product   WooCommerce product object.
	 * @param WC_Order   $order     WooCommerce order object.
	 */
	return (bool) apply_filters( 'rytkoset_theme_product_has_withdrawal_right', $has_right, $product, $order );
}

/**
 * Returns true when an order contains at least one product with a withdrawal right.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_order_has_withdrawal_right_products( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	foreach ( $order->get_items() as $item ) {
		$product = is_object( $item ) && is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

		if ( $product instanceof WC_Product && rytkoset_theme_product_has_withdrawal_right( $product, $order ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns WooCommerce customer email IDs that can act as the initial order confirmation.
 *
 * Completed-order email is included because orders containing only virtual products can move
 * directly to completed without a separate processing-order message.
 *
 * @return array<int, string>
 */
function rytkoset_theme_get_withdrawal_information_email_ids() {
	/**
	 * Filters customer email IDs that receive the withdrawal information.
	 *
	 * @param array<int, string> $email_ids WooCommerce email IDs.
	 */
	return (array) apply_filters(
		'rytkoset_theme_withdrawal_information_email_ids',
		array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' )
	);
}

/**
 * Returns the filled statutory withdrawal instruction and form content.
 *
 * The structure follows the current annexes I and II of Ministry of Justice regulation
 * 110/2014, as amended by regulation 754/2022.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_withdrawal_information_content() {
	$contact_email = function_exists( 'rytkoset_theme_get_contact_email' )
		? rytkoset_theme_get_contact_email()
		: 'info@rytkoset.net';

	return array(
		'intro'        => __( 'Tämä ohje ja lomake koskevat tilauksesi tuotteita, joihin sovelletaan peruuttamisoikeutta.', 'rytkoset-theme' ),
		'right'        => array(
			'heading'    => __( 'Peruuttamisoikeus', 'rytkoset-theme' ),
			'paragraphs' => array(
				__( 'Teillä on oikeus peruuttaa tämä sopimus 14 päivän kuluessa syytä ilmoittamatta.', 'rytkoset-theme' ),
				__( 'Peruuttamisen määräaika päättyy 14 päivän kuluttua sopimuksen tekemisestä, kun kyseessä on palvelu tai sähköisesti toimitettava digitaalinen sisältö. Tavaran kaupassa määräaika päättyy 14 päivän kuluttua siitä, kun tavara, viimeinen tavaraerä tai säännöllisesti toimitettavien tavaroiden ensimmäinen tavaraerä on vastaanotettu.', 'rytkoset-theme' ),
				sprintf(
					/* translators: %s: association contact email address. */
					__( 'Peruuttamisoikeuden käyttämiseksi teidän on ilmoitettava meille, Rytkösten sukuseura ry, Tyrmynniementie 71, 74595 Runni, puhelin 040 592 2842, sähköposti %s, päätöksestänne peruuttaa sopimus yksiselitteisellä tavalla, esimerkiksi kirjeellä postitse tai sähköpostilla. Voitte käyttää jäljempänä olevaa peruuttamislomaketta, mutta sen käyttö ei ole pakollista.', 'rytkoset-theme' ),
					$contact_email
				),
				__( 'Voitte tehdä yksiselitteisen peruuttamisilmoituksen myös verkkosivustollamme. Jos teitte tilauksen käyttäjätilillä, toiminto löytyy kohdasta Oma tili → Tilaukset. Ilman käyttäjätiliä tehdyn tilauksen henkilökohtainen Peruuta tilaus -linkki on tilausvahvistuksessa. Jos käytätte verkkosivuston peruuttamistoimintoa, ilmoitamme teille viipymättä sähköpostitse peruuttamisilmoituksen saapumisesta.', 'rytkoset-theme' ),
				__( 'Peruuttamisen määräajan noudattamiseksi riittää, että lähetätte ilmoituksenne peruuttamisoikeuden käytöstä ennen peruuttamisajan päättymistä.', 'rytkoset-theme' ),
			),
		),
		'effects'      => array(
			'heading'    => __( 'Peruuttamisen vaikutukset', 'rytkoset-theme' ),
			'paragraphs' => array(
				__( 'Jos peruutatte tämän sopimuksen, palautamme teille kaikki teiltä saamamme suoritukset, myös toimituskustannukset (paitsi lisäkustannuksia siitä, että olette valinnut tarjoamastamme edullisimmasta vakiotoimitustavasta poikkeavan toimitustavan), viivytyksettä ja joka tapauksessa viimeistään 14 päivän kuluttua peruuttamisilmoituksen saatuamme. Suoritamme palautuksen sillä maksutavalla, jota olette käyttänyt alkuperäisessä liiketoimessa, ellette ole nimenomaisesti suostunut muuhun, ja joka tapauksessa siten, että teille ei aiheudu suoritusten palauttamisesta kustannuksia.', 'rytkoset-theme' ),
				__( 'Voimme pidättyä maksujen palautuksesta, kunnes olemme saaneet tavaran takaisin tai kunnes olette osoittanut lähettäneenne tavaran takaisin.', 'rytkoset-theme' ),
				__( 'Teidän on lähetettävä tavarat takaisin osoitteeseen Rytkösten sukuseura ry, Tyrmynniementie 71, 74595 Runni viivytyksettä ja viimeistään 14 päivän kuluttua peruuttamisilmoituksen tekemisestä. Määräaikaa on noudatettu, jos lähetätte tavarat takaisin ennen kyseisen 14 päivän määräajan päättymistä.', 'rytkoset-theme' ),
				__( 'Teidän on vastattava tavaroiden palauttamisesta johtuvista välittömistä kustannuksista.', 'rytkoset-theme' ),
				__( 'Olette vastuussa vain sellaisesta tavaroiden arvon alentumisesta, joka on seurausta muusta kuin tavaroiden luonteen, ominaisuuksien ja toimivuuden toteamiseksi tarvittavasta käsittelystä.', 'rytkoset-theme' ),
				__( 'Jos olette pyytänyt palvelun suorittamista ennen peruuttamisajan päättymistä, teidän on maksettava meille peruuttamisilmoituksen tekemiseen mennessä sopimuksen täyttämiseksi tehdystä suorituksesta kohtuullinen korvaus.', 'rytkoset-theme' ),
			),
		),
		'exceptions'   => array(
			'heading'    => __( 'Tuotteet, joita peruuttamisoikeus ei koske', 'rytkoset-theme' ),
			'paragraphs' => array(
				__( 'Peruuttamisoikeutta ei sovelleta tapahtumamaksuihin, kun vapaa-ajanpalvelu sovitaan suoritettavaksi määrättynä ajankohtana.', 'rytkoset-theme' ),
				__( 'Sähköisesti toimitettavan digitaalisen sisällön peruuttamisoikeus päättyy, kun toimittaminen on aloitettu peruuttamisaikana kuluttajan nimenomaisella ennakkosuostumuksella, kuluttaja on hyväksynyt peruuttamisoikeuden puuttumisen ja elinkeinonharjoittaja on toimittanut tästä vahvistuksen.', 'rytkoset-theme' ),
			),
		),
		'form_heading' => __( 'Peruuttamislomakkeen malli', 'rytkoset-theme' ),
		'form_intro'   => __( 'Täyttäkää ja palauttakaa tämä lomake vain siinä tapauksessa, että haluatte peruuttaa sopimuksen.', 'rytkoset-theme' ),
		'form_lines'   => array(
			sprintf(
				/* translators: %s: association contact email address. */
				__( 'Vastaanottaja: Rytkösten sukuseura ry, Tyrmynniementie 71, 74595 Runni, %s', 'rytkoset-theme' ),
				$contact_email
			),
			__( 'Ilmoitan/Ilmoitamme (*), että haluan/haluamme (*) peruuttaa tekemäni/tekemämme (*) sopimuksen, joka koskee seuraavien tavaroiden toimittamista (*) / seuraavan palvelun suorittamista (*):', 'rytkoset-theme' ),
			__( 'Tavarat tai palvelu: ________________________________________________', 'rytkoset-theme' ),
			__( 'Tilauspäivä (*) / Vastaanottopäivä (*): _____________________________', 'rytkoset-theme' ),
			__( 'Kuluttajan nimi (*) / Kuluttajien nimet (*): _________________________', 'rytkoset-theme' ),
			__( 'Kuluttajan osoite (*) / Kuluttajien osoitteet (*): ___________________', 'rytkoset-theme' ),
			__( 'Kuluttajan allekirjoitus (*) / Kuluttajien allekirjoitukset (*) (vain jos lomake täytetään paperimuodossa):', 'rytkoset-theme' ),
			__( 'Allekirjoitus: ______________________________________________________', 'rytkoset-theme' ),
			__( 'Päiväys: ____________________________________________________________', 'rytkoset-theme' ),
			__( '(*) Tarpeeton yliviivataan.', 'rytkoset-theme' ),
		),
	);
}

/**
 * Renders the statutory withdrawal information in a WooCommerce customer email.
 *
 * @param WC_Order      $order         WooCommerce order object.
 * @param bool          $sent_to_admin Whether the email is sent to an administrator.
 * @param bool          $plain_text    Whether the email uses plain text.
 * @param WC_Email|null $email         WooCommerce email object.
 * @return void
 */
function rytkoset_theme_render_withdrawal_information_in_email( $order, $sent_to_admin, $plain_text, $email = null ) {
	$email_id = is_object( $email ) && isset( $email->id ) ? (string) $email->id : '';

	if (
		$sent_to_admin
		|| ! $order instanceof WC_Order
		|| ! in_array( $email_id, rytkoset_theme_get_withdrawal_information_email_ids(), true )
		|| ! rytkoset_theme_order_has_withdrawal_right_products( $order )
	) {
		return;
	}

	$content = rytkoset_theme_get_withdrawal_information_content();

	if ( $plain_text ) {
		echo "\n" . esc_html__( 'PERUUTTAMISOHJE JA -LOMAKE', 'rytkoset-theme' ) . "\n\n";
		echo esc_html( $content['intro'] ) . "\n\n";

		foreach ( array( 'right', 'effects', 'exceptions' ) as $section_key ) {
			$section = $content[ $section_key ];
			echo esc_html( strtoupper( $section['heading'] ) ) . "\n";
			echo esc_html( implode( "\n\n", $section['paragraphs'] ) ) . "\n\n";
		}

		echo esc_html( strtoupper( $content['form_heading'] ) ) . "\n";
		echo esc_html( $content['form_intro'] ) . "\n\n";
		echo esc_html( implode( "\n", $content['form_lines'] ) ) . "\n";
		return;
	}

	echo '<div class="rytkoset-withdrawal-information">';
	echo '<h2>' . esc_html__( 'Peruuttamisohje ja -lomake', 'rytkoset-theme' ) . '</h2>';
	echo '<p>' . esc_html( $content['intro'] ) . '</p>';

	foreach ( array( 'right', 'effects', 'exceptions' ) as $section_key ) {
		$section = $content[ $section_key ];
		echo '<h3>' . esc_html( $section['heading'] ) . '</h3>';

		foreach ( $section['paragraphs'] as $paragraph ) {
			echo '<p>' . esc_html( $paragraph ) . '</p>';
		}
	}

	echo '<h3>' . esc_html( $content['form_heading'] ) . '</h3>';
	echo '<p><em>' . esc_html( $content['form_intro'] ) . '</em></p>';
	echo '<ul>';

	foreach ( $content['form_lines'] as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}

	echo '</ul>';
	echo '</div>';
}
add_action( 'woocommerce_email_after_order_table', 'rytkoset_theme_render_withdrawal_information_in_email', 20, 4 );
