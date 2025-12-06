<?php
/**
 * Galleriat ja albumit.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'rytkoset_theme_register_gallery_album_cpt' ) ) {
        /**
         * Rekisteröi valokuva-albumien CPT:n.
         */
        function rytkoset_theme_register_gallery_album_cpt() {
                $labels = array(
                        'name'               => __( 'Albumit', 'rytkoset-theme' ),
                        'singular_name'      => __( 'Albumi', 'rytkoset-theme' ),
                        'menu_name'          => __( 'Albumit', 'rytkoset-theme' ),
                        'name_admin_bar'     => __( 'Albumi', 'rytkoset-theme' ),
                        'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
                        'add_new_item'       => __( 'Lisää uusi albumi', 'rytkoset-theme' ),
                        'new_item'           => __( 'Uusi albumi', 'rytkoset-theme' ),
                        'edit_item'          => __( 'Muokkaa albumia', 'rytkoset-theme' ),
                        'view_item'          => __( 'Näytä albumi', 'rytkoset-theme' ),
                        'all_items'          => __( 'Kaikki albumit', 'rytkoset-theme' ),
                        'search_items'       => __( 'Etsi albumeja', 'rytkoset-theme' ),
                        'parent_item_colon'  => __( 'Yläalbumit:', 'rytkoset-theme' ),
                        'not_found'          => __( 'Albumeja ei löytynyt.', 'rytkoset-theme' ),
                        'not_found_in_trash' => __( 'Roskakorissa ei ole albumeja.', 'rytkoset-theme' ),
                );

                $args = array(
                        'labels'             => $labels,
                        'public'             => true,
                        'has_archive'        => true,
                        'menu_icon'          => 'dashicons-format-gallery',
                        'show_in_rest'       => true,
                        'supports'           => array( 'title', 'editor', 'thumbnail', 'comments' ),
                        'rewrite'            => array(
                                'slug'       => 'albumit',
                                'with_front' => false,
                        ),
                );

                register_post_type( 'gallery_album', $args );
        }
}
add_action( 'init', 'rytkoset_theme_register_gallery_album_cpt' );

if ( ! function_exists( 'rytkoset_theme_register_gallery_fields' ) ) {
        /**
         * Lisää ACF-kentät albumien kuville ja videoille.
         */
        function rytkoset_theme_register_gallery_fields() {
                if ( ! function_exists( 'acf_add_local_field_group' ) ) {
                        return;
                }

                acf_add_local_field_group(
                        array(
                                'key'      => 'group_rytkoset_gallery_album',
                                'title'    => __( 'Albumin media', 'rytkoset-theme' ),
                                'fields'   => array(
                                        array(
                                                'key'           => 'field_gallery_images',
                                                'label'         => __( 'Albumin kuvat', 'rytkoset-theme' ),
                                                'name'          => 'gallery_images',
                                                'type'          => 'gallery',
                                                'instructions'  => __( 'Lisää albumin kuvat.', 'rytkoset-theme' ),
                                                'return_format' => 'array',
                                                'preview_size'  => 'medium',
                                                'library'       => 'all',
                                        ),
                                        array(
                                                'key'           => 'field_gallery_videos',
                                                'label'         => __( 'Videot', 'rytkoset-theme' ),
                                                'name'          => 'gallery_videos',
                                                'type'          => 'repeater',
                                                'instructions'  => __( 'YouTube-linkit, jotka näytetään albumisivun yläosassa ja gallerian yhteydessä.', 'rytkoset-theme' ),
                                                'layout'        => 'row',
                                                'button_label'  => __( 'Lisää video', 'rytkoset-theme' ),
                                                'sub_fields'    => array(
                                                        array(
                                                                'key'          => 'field_gallery_video_url',
                                                                'label'        => __( 'Video-URL', 'rytkoset-theme' ),
                                                                'name'         => 'video_url',
                                                                'type'         => 'url',
                                                                'placeholder'  => 'https://www.youtube.com/watch?v=abcd1234',
                                                                'instructions' => __( 'Liitä YouTube-linkki tai jakolinkki.', 'rytkoset-theme' ),
                                                        ),
                                                        array(
                                                                'key'           => 'field_gallery_video_thumbnail',
                                                                'label'         => __( 'Videon pikkukuva', 'rytkoset-theme' ),
                                                                'name'          => 'video_thumbnail',
                                                                'type'          => 'image',
                                                                'return_format' => 'array',
                                                                'preview_size'  => 'medium',
                                                                'library'       => 'all',
                                                        ),
                                                ),
                                        ),
                                ),
                                'location' => array(
                                        array(
                                                array(
                                                        'param'    => 'post_type',
                                                        'operator' => '==',
                                                        'value'    => 'gallery_album',
                                                ),
                                        ),
                                ),
                        )
                );
        }
}
add_action( 'acf/init', 'rytkoset_theme_register_gallery_fields' );

if ( ! function_exists( 'rytkoset_theme_get_video_embed_url' ) ) {
        /**
         * Palauttaa upotus-URL:n Video-sivustolle (tällä hetkellä YouTube).
         *
         * @param string $url Syötteen URL.
         * @return string Upotus-URL tai tyhjä jos ei tunnistettavissa.
         */
        function rytkoset_theme_get_video_embed_url( $url ) {
                if ( empty( $url ) ) {
                        return '';
                }

                $parsed = wp_parse_url( $url );

                if ( empty( $parsed['host'] ) ) {
                        return esc_url_raw( $url );
                }

                $host = $parsed['host'];

                if ( false !== strpos( $host, 'youtu.be' ) || false !== strpos( $host, 'youtube.com' ) ) {
                        $path      = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
                        $query     = isset( $parsed['query'] ) ? $parsed['query'] : '';
                        $video_id  = '';
                        $query_var = array();

                        if ( ! empty( $query ) ) {
                                parse_str( $query, $query_var );
                        }

                        if ( ! empty( $query_var['v'] ) ) {
                                $video_id = $query_var['v'];
                        } elseif ( 0 === strpos( $path, 'embed/' ) ) {
                                $video_id = substr( $path, 6 );
                        } elseif ( 0 === strpos( $path, 'shorts/' ) ) {
                                $video_id = substr( $path, 7 );
                        } elseif ( ! empty( $path ) ) {
                                $video_id = $path;
                        }

                        if ( ! empty( $video_id ) ) {
                                $params = array(
                                        'autoplay'        => 1,
                                        'rel'             => 0,
                                        'modestbranding'  => 1,
                                        'playsinline'     => 1,
                                );

                                return add_query_arg( $params, 'https://www.youtube.com/embed/' . rawurlencode( $video_id ) );
                        }
                }

                return esc_url_raw( $url );
        }
}
