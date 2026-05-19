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
