<?php
/**
 * Customizer-asetus yhteysosoitteelle ja getter sen hakemiseen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palauttaa Customizerissa määritetyn yhteyssähköpostin.
 *
 * @return string
 */
function rytkoset_theme_get_contact_email() {
	$email = get_theme_mod( 'rytkoset_theme_contact_email', 'info@rytkoset.net' );
	$email = sanitize_email( $email );

	return $email ? $email : 'info@rytkoset.net';
}

/**
 * Rekisteröi Customizeriin osion ja kentän yhteyssähköpostille.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function rytkoset_theme_register_contact_email_customizer( $wp_customize ) {
	$wp_customize->add_section(
		'rytkoset_theme_contact',
		array(
			'title'    => __( 'Yhteystiedot', 'rytkoset-theme' ),
			'priority' => 80,
		)
	);

	$wp_customize->add_setting(
		'rytkoset_theme_contact_email',
		array(
			'default'           => 'info@rytkoset.net',
			'sanitize_callback' => 'sanitize_email',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		'rytkoset_theme_contact_email',
		array(
			'label'       => __( 'Yhteyssähköposti', 'rytkoset-theme' ),
			'description' => __( 'Headerin, footerin ja jako-painikkeen mailto-osoitteena käytettävä sähköpostiosoite.', 'rytkoset-theme' ),
			'section'     => 'rytkoset_theme_contact',
			'type'        => 'email',
		)
	);
}
add_action( 'customize_register', 'rytkoset_theme_register_contact_email_customizer' );

/**
 * Rekisteröi huoltotila-asetukset Customizeriin.
 *
 * Asetukset luetaan wp-content/maintenance.php:ssä WordPressin
 * osittain ladatussa tilassa (get_theme_mod toimii).
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function rytkoset_theme_register_maintenance_customizer( $wp_customize ) {
	$wp_customize->add_section(
		'rytkoset_theme_maintenance',
		array(
			'title'       => __( 'Huoltotila', 'rytkoset-theme' ),
			'description' => __( 'Nämä asetukset näkyvät, kun sivusto on huoltotilassa (wp-content/maintenance.php). Muuta ennen huoltotilan aktivointia.', 'rytkoset-theme' ),
			'priority'    => 82,
		)
	);

	$wp_customize->add_setting(
		'rytkoset_theme_maintenance_concept',
		array(
			'default'           => 'uudistus',
			'sanitize_callback' => 'rytkoset_theme_sanitize_maintenance_concept',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		'rytkoset_theme_maintenance_concept',
		array(
			'label'       => __( 'Viestin sävy', 'rytkoset-theme' ),
			'description' => __( '"Uudistus" kertoo uudesta ilmeestä, "Huolto" on lyhyt huoltokatko ja "Talkoot" korostaa yhdessä tekemistä.', 'rytkoset-theme' ),
			'section'     => 'rytkoset_theme_maintenance',
			'type'        => 'select',
			'choices'     => array(
				'uudistus' => __( 'Uudistus — sivusto päivittyy', 'rytkoset-theme' ),
				'huolto'   => __( 'Huolto — lyhyt huoltokatko', 'rytkoset-theme' ),
				'talkoot'  => __( 'Talkoot — sivustoa rakennetaan', 'rytkoset-theme' ),
			),
		)
	);

	$wp_customize->add_setting(
		'rytkoset_theme_maintenance_return_text',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		'rytkoset_theme_maintenance_return_text',
		array(
			'label'       => __( 'Paluuaika (valinnainen)', 'rytkoset-theme' ),
			'description' => __( 'Esim. "Takaisin elokuussa 2026". Jätä tyhjäksi, jos paluuaikaa ei haluta näyttää.', 'rytkoset-theme' ),
			'section'     => 'rytkoset_theme_maintenance',
			'type'        => 'text',
		)
	);
}
add_action( 'customize_register', 'rytkoset_theme_register_maintenance_customizer' );

/**
 * Sanitoi huoltotila-konsepti sallituille arvoille.
 *
 * @param string $value Syötetty arvo.
 * @return string Sanitetoitu arvo.
 */
function rytkoset_theme_sanitize_maintenance_concept( $value ) {
	$allowed = array( 'uudistus', 'huolto', 'talkoot' );
	return in_array( $value, $allowed, true ) ? $value : 'uudistus';
}
