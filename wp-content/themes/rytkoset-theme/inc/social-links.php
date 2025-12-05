<?php
/**
 * Sosiaalisen median linkit – keskitetty listaus, jota voidaan käyttää useissa paikoissa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_register_social_fields' ) ) {
        /**
         * Lisää ACF-asetuskentät sosiaalisen median linkeille.
         */
        function rytkoset_theme_register_social_fields() {
                if ( ! function_exists( 'acf_add_local_field_group' ) ) {
                        return;
                }

                if ( function_exists( 'acf_add_options_page' ) ) {
                        acf_add_options_page(
                                array(
                                        'page_title' => __( 'Teema-asetukset', 'rytkoset-theme' ),
                                        'menu_title' => __( 'Teema-asetukset', 'rytkoset-theme' ),
                                        'menu_slug'  => 'rytkoset-theme-settings',
                                        'capability' => 'manage_options',
                                        'redirect'   => false,
                                )
                        );
                }

                acf_add_local_field_group(
                        array(
                                'key'    => 'group_rytkoset_social_links',
                                'title'  => __( 'Sosiaalisen median linkit', 'rytkoset-theme' ),
                                'fields' => array(
                                        array(
                                                'key'         => 'field_rytkoset_facebook_url',
                                                'label'       => __( 'Facebook-linkki', 'rytkoset-theme' ),
                                                'name'        => 'facebook_url',
                                                'type'        => 'url',
                                                'placeholder' => 'https://www.facebook.com/rytkoset',
                                        ),
                                        array(
                                                'key'         => 'field_rytkoset_youtube_url',
                                                'label'       => __( 'YouTube-linkki', 'rytkoset-theme' ),
                                                'name'        => 'youtube_url',
                                                'type'        => 'url',
                                                'placeholder' => 'https://www.youtube.com/@rytkoset',
                                        ),
                                        array(
                                                'key'         => 'field_rytkoset_instagram_url',
                                                'label'       => __( 'Instagram-linkki', 'rytkoset-theme' ),
                                                'name'        => 'instagram_url',
                                                'type'        => 'url',
                                                'placeholder' => 'https://www.instagram.com/rytkoset/',
                                        ),
                                        array(
                                                'key'         => 'field_rytkoset_x_url',
                                                'label'       => __( 'X-linkki', 'rytkoset-theme' ),
                                                'name'        => 'x_url',
                                                'type'        => 'url',
                                                'placeholder' => 'https://x.com/rytkoset',
                                        ),
                                ),
                                'location' => array(
                                        array(
                                                array(
                                                        'param'    => 'options_page',
                                                        'operator' => '==',
                                                        'value'    => 'rytkoset-theme-settings',
                                                ),
                                        ),
                                ),
                        )
                );
        }
}
add_action( 'acf/init', 'rytkoset_theme_register_social_fields' );

if ( ! function_exists( 'rytkoset_theme_get_social_links' ) ) {
        /**
         * Palauttaa sosiaalisen median linkit.
         *
         * @return array[] Lista sosiaalisen median linkkejä.
         */
        function rytkoset_theme_get_social_links() {
                $icon_files = array(
                        'facebook'  => 'Facebook.svg',
                        'youtube'   => 'YouTube.svg',
                        'instagram' => 'Instagram.svg',
                        'x'         => 'X.svg',
                );

                $defaults = array(
                        'facebook'  => array(
                                'label' => 'Facebook',
                                'url'   => 'https://www.facebook.com/rytkoset',
                        ),
                        'youtube'   => array(
                                'label' => 'YouTube',
                                'url'   => 'https://www.youtube.com/@rytkoset',
                        ),
                        'instagram' => array(
                                'label' => 'Instagram',
                                'url'   => 'https://www.instagram.com/rytkoset/',
                        ),
                        'x'         => array(
                                'label' => 'X',
                                'url'   => 'https://x.com/rytkoset',
                        ),
                );

                $fields = array(
                        'facebook'  => function_exists( 'get_field' ) ? get_field( 'facebook_url', 'option' ) : '',
                        'youtube'   => function_exists( 'get_field' ) ? get_field( 'youtube_url', 'option' ) : '',
                        'instagram' => function_exists( 'get_field' ) ? get_field( 'instagram_url', 'option' ) : '',
                        'x'         => function_exists( 'get_field' ) ? get_field( 'x_url', 'option' ) : '',
                );

                $social_links = array();

                foreach ( $defaults as $key => $data ) {
                        $url = ! empty( $fields[ $key ] ) ? $fields[ $key ] : $data['url'];

                        if ( empty( $url ) ) {
                                continue;
                        }

                        $social_links[] = array(
                                'label'    => $data['label'],
                                'url'      => esc_url_raw( $url ),
                                'icon'     => $key,
                                'icon_src' => isset( $icon_files[ $key ] ) ? get_template_directory_uri() . '/assets/icons/social/' . $icon_files[ $key ] : '',
                        );
                }

                return $social_links;
        }
}
