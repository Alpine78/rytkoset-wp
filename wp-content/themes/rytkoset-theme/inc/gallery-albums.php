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
                        'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
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
                                                'key'          => 'field_gallery_caption_note',
                                                'label'        => __( 'Kuvatekstit', 'rytkoset-theme' ),
                                                'name'         => '',
                                                'type'         => 'message',
                                                'message'      => __( 'Kuvatekstit ja kuvaukset tulevat mediakirjastosta. Albumin lopulliset kuvatekstit eivät tule Gutenberg-lohkon omasta caption-kentästä.', 'rytkoset-theme' ),
                                                'new_lines'    => 'wpautop',
                                                'esc_html'     => 1,
                                        ),
                                        array(
                                                'key'           => 'field_gallery_event_date',
                                                'label'         => __( 'Tapahtumapäivä', 'rytkoset-theme' ),
                                                'name'          => 'event_date',
                                                'type'          => 'date_picker',
                                                'instructions'  => __( 'Näytetään albumin päiväyksenä ja käytetään albumien lajitteluun. Jos jätät tyhjäksi, käytetään julkaisupäivää.', 'rytkoset-theme' ),
                                                'display_format'=> 'd.m.Y',
                                                'return_format' => 'Ymd',
                                                'first_day'     => 1,
                                        ),
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
												'instructions'  => __( 'Liitä yksi YouTube-linkki per rivi. Videot näytetään albumisivun yläosan Videot-osiossa.', 'rytkoset-theme' ),
												'layout'        => 'row',
												'button_label'  => __( 'Lisää YouTube-video', 'rytkoset-theme' ),
												'sub_fields'    => array(
                                                        array(
                                                                'key'          => 'field_gallery_video_url',
                                                                'label'        => __( 'Video-URL', 'rytkoset-theme' ),
                                                                'name'         => 'video_url',
                                                                'type'         => 'url',
                                                                'placeholder'  => 'https://www.youtube.com/watch?v=abcd1234',
                                                                'instructions' => __( 'Liitä YouTube-linkki. Tuettuja muotoja ovat esimerkiksi watch-, youtu.be-, embed- ja shorts-linkit.', 'rytkoset-theme' ),
                                                        ),
                                                        array(
                                                                'key'           => 'field_gallery_video_thumbnail',
                                                                'label'         => __( 'Videon pikkukuva', 'rytkoset-theme' ),
                                                                'name'          => 'video_thumbnail',
                                                                'type'          => 'image',
                                                                'instructions'  => __( 'Valinnainen. Käytetään videon pikkukuvana galleriassa ja lightboxissa.', 'rytkoset-theme' ),
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

if ( ! function_exists( 'rytkoset_theme_get_youtube_video_id' ) ) {
        /**
         * Returns a normalized YouTube video ID from a supported URL.
         *
         * @param string $url Video URL.
         * @return string
         */
        function rytkoset_theme_get_youtube_video_id( $url ) {
                if ( empty( $url ) ) {
                        return '';
                }

                $parsed = wp_parse_url( $url );

                if ( empty( $parsed['host'] ) ) {
                        return '';
                }

                $host      = strtolower( (string) $parsed['host'] );
                $path      = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
                $query     = isset( $parsed['query'] ) ? (string) $parsed['query'] : '';
                $video_id  = '';
                $query_var = array();

                if ( false === strpos( $host, 'youtu.be' ) && false === strpos( $host, 'youtube.com' ) ) {
                        return '';
                }

                if ( '' !== $query ) {
                        parse_str( $query, $query_var );
                }

                if ( ! empty( $query_var['v'] ) ) {
                        $video_id = (string) $query_var['v'];
                } elseif ( 0 === strpos( $path, 'embed/' ) ) {
                        $video_id = substr( $path, 6 );
                } elseif ( 0 === strpos( $path, 'shorts/' ) ) {
                        $video_id = substr( $path, 7 );
                } elseif ( '' !== $path ) {
                        $video_id = $path;
                }

                $video_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $video_id );

                return is_string( $video_id ) ? $video_id : '';
        }
}

if ( ! function_exists( 'rytkoset_theme_make_gallery_video_field_acf_free_friendly' ) ) {
        /**
         * Converts the video field to a simple textarea when repeater support is unavailable.
         *
         * @param array $field ACF field config.
         * @return array
         */
        function rytkoset_theme_make_gallery_video_field_acf_free_friendly( $field ) {
                if ( class_exists( 'acf_field_repeater' ) ) {
                        return $field;
                }

                $field['type']         = 'textarea';
                $field['instructions'] = __( 'Liitä yksi YouTube-linkki per rivi. Tuettuja muotoja ovat esimerkiksi watch-, youtu.be-, embed- ja shorts-linkit.', 'rytkoset-theme' );
                $field['rows']         = 4;
                $field['new_lines']    = '';
                $field['placeholder']  = "https://www.youtube.com/watch?v=abcd1234\nhttps://youtu.be/abcd1234";

                unset(
                        $field['sub_fields'],
                        $field['layout'],
                        $field['button_label'],
                        $field['min'],
                        $field['max'],
                        $field['collapsed']
                );

                return $field;
        }
}
add_filter( 'acf/load_field/key=field_gallery_videos', 'rytkoset_theme_make_gallery_video_field_acf_free_friendly' );

if ( ! function_exists( 'rytkoset_theme_render_gallery_video_field_helper' ) ) {
        /**
         * Renders a helper note for the textarea-based video field.
         *
         * @param array $field ACF field config.
         * @return void
         */
        function rytkoset_theme_render_gallery_video_field_helper( $field ) {
                if ( class_exists( 'acf_field_repeater' ) ) {
                        return;
                }

                echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Riittää, että liität videolinkit kenttään yksi per rivi. Erillistä nappia ei tarvitse käyttää.', 'rytkoset-theme' ) . '</p>';
        }
}
add_action( 'acf/render_field/key=field_gallery_videos', 'rytkoset_theme_render_gallery_video_field_helper' );

if ( ! function_exists( 'rytkoset_theme_get_gallery_album_video_rows' ) ) {
        /**
         * Returns normalized gallery album video rows.
         *
         * Supports both the legacy repeater meta and the textarea fallback.
         *
         * @param int $post_id Album post ID.
         * @return array<int, array<string, mixed>>
         */
        function rytkoset_theme_get_gallery_album_video_rows( $post_id ) {
                $post_id = (int) $post_id;
                $videos  = array();
                $seen    = array();

                $append_video = static function ( $video_url, $thumbnail_id = 0 ) use ( &$videos, &$seen ) {
                        $video_url = esc_url_raw( trim( (string) $video_url ) );

                        if ( '' === $video_url || '' === rytkoset_theme_get_youtube_video_id( $video_url ) || in_array( $video_url, $seen, true ) ) {
                                return;
                        }

                        $seen[]   = $video_url;
                        $videos[] = array(
                                'video_url'          => $video_url,
                                'video_thumbnail_id' => (int) $thumbnail_id,
                        );
                };

                $video_urls = function_exists( 'get_field' ) ? get_field( 'gallery_videos', $post_id ) : get_post_meta( $post_id, 'gallery_videos', true );

                if ( is_string( $video_urls ) && '' !== trim( $video_urls ) ) {
                        $video_urls = preg_split( '/\r\n|\r|\n/', $video_urls );
                }

                if ( is_array( $video_urls ) ) {
                        foreach ( $video_urls as $video_url ) {
                                if ( is_string( $video_url ) ) {
                                        $append_video( $video_url );
                                        continue;
                                }

                                if ( ! is_array( $video_url ) ) {
                                        continue;
                                }

                                $thumbnail_id = 0;

                                if ( isset( $video_url['video_thumbnail']['ID'] ) ) {
                                        $thumbnail_id = (int) $video_url['video_thumbnail']['ID'];
                                } elseif ( isset( $video_url['video_thumbnail'] ) ) {
                                        $thumbnail_id = (int) $video_url['video_thumbnail'];
                                }

                                $append_video( isset( $video_url['video_url'] ) ? $video_url['video_url'] : '', $thumbnail_id );
                        }
                }

                $legacy_row_count = (int) get_post_meta( $post_id, 'gallery_videos', true );

                if ( $legacy_row_count > 0 ) {
                        for ( $index = 0; $index < $legacy_row_count; $index++ ) {
                                $append_video(
                                        get_post_meta( $post_id, 'gallery_videos_' . $index . '_video_url', true ),
                                        (int) get_post_meta( $post_id, 'gallery_videos_' . $index . '_video_thumbnail', true )
                                );
                        }
                }

                return $videos;
        }
}

if ( ! function_exists( 'rytkoset_theme_get_album_event_date_raw' ) ) {
        /**
         * Returns the raw album event date meta value in Ymd format.
         *
         * @param int $post_id Album post ID.
         * @return string
         */
        function rytkoset_theme_get_album_event_date_raw( $post_id ) {
                $post_id    = (int) $post_id;
                $event_date = (string) get_post_meta( $post_id, 'event_date', true );

                if ( preg_match( '/^\d{8}$/', $event_date ) ) {
                        return $event_date;
                }

                return '';
        }
}

if ( ! function_exists( 'rytkoset_theme_get_album_display_date' ) ) {
        /**
         * Returns the album date shown to users.
         *
         * Prefers the event date field and falls back to the post publish date.
         *
         * @param int $post_id Album post ID.
         * @return string
         */
        function rytkoset_theme_get_album_display_date( $post_id ) {
                $post_id    = (int) $post_id;
                $event_date = rytkoset_theme_get_album_event_date_raw( $post_id );

                if ( '' === $event_date ) {
                        return get_the_date( '', $post_id );
                }

                $date = DateTimeImmutable::createFromFormat( '!Ymd', $event_date, wp_timezone() );

                if ( false === $date ) {
                        return get_the_date( '', $post_id );
                }

                return wp_date( get_option( 'date_format' ), $date->getTimestamp(), wp_timezone() );
        }
}

if ( ! function_exists( 'rytkoset_theme_apply_gallery_album_event_date_order' ) ) {
        /**
         * Applies event-date-first ordering to album queries.
         *
         * @param WP_Query $query Query instance.
         * @return void
         */
        function rytkoset_theme_apply_gallery_album_event_date_order( $query ) {
                $query->set(
                        'meta_query',
                        array(
                                'relation'           => 'OR',
                                'event_date_clause'  => array(
                                        'key'     => 'event_date',
                                        'compare' => 'EXISTS',
                                        'type'    => 'NUMERIC',
                                ),
                                'event_date_missing' => array(
                                        'key'     => 'event_date',
                                        'compare' => 'NOT EXISTS',
                                ),
                        )
                );

                $query->set(
                        'orderby',
                        array(
                                'event_date_clause' => 'DESC',
                                'date'              => 'DESC',
                        )
                );
        }
}

if ( ! function_exists( 'rytkoset_theme_sort_gallery_album_archive_by_event_date' ) ) {
        /**
         * Sorts the album archive by event date, newest first.
         *
         * Albums without an event date fall back behind dated albums and then use publish date.
         *
         * @param WP_Query $query Query instance.
         * @return void
         */
        function rytkoset_theme_sort_gallery_album_archive_by_event_date( $query ) {
                if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'gallery_album' ) ) {
                        return;
                }

                rytkoset_theme_apply_gallery_album_event_date_order( $query );
        }
}
add_action( 'pre_get_posts', 'rytkoset_theme_sort_gallery_album_archive_by_event_date' );

if ( ! function_exists( 'rytkoset_theme_gallery_album_admin_columns' ) ) {
        /**
         * Customizes admin columns for gallery albums.
         *
         * @param array $columns Existing columns.
         * @return array
         */
        function rytkoset_theme_gallery_album_admin_columns( $columns ) {
                return array(
                        'cb'            => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
                        'title'         => __( 'Albumi', 'rytkoset-theme' ),
                        'event_date'    => __( 'Tapahtumapäivä', 'rytkoset-theme' ),
                        'album_cover'   => __( 'Kansikuva', 'rytkoset-theme' ),
                        'album_excerpt' => __( 'Ingressi', 'rytkoset-theme' ),
                        'date'          => __( 'Julkaistu', 'rytkoset-theme' ),
                );
        }
}
add_filter( 'manage_edit-gallery_album_columns', 'rytkoset_theme_gallery_album_admin_columns' );

if ( ! function_exists( 'rytkoset_theme_render_gallery_album_admin_column' ) ) {
        /**
         * Renders custom admin column content for gallery albums.
         *
         * @param string $column  Column key.
         * @param int    $post_id Post ID.
         * @return void
         */
        function rytkoset_theme_render_gallery_album_admin_column( $column, $post_id ) {
                if ( 'gallery_album' !== get_post_type( $post_id ) ) {
                        return;
                }

                if ( 'event_date' === $column ) {
                        $event_date = rytkoset_theme_get_album_event_date_raw( $post_id );

                        if ( '' !== $event_date ) {
                                echo esc_html( rytkoset_theme_get_album_display_date( $post_id ) );
                                return;
                        }

                        echo '<span style="color:#8a8f98;">' . esc_html__( 'Ei asetettu', 'rytkoset-theme' ) . '</span>';
                        echo '<br><span style="color:#8a8f98;">' . esc_html__( 'Käyttää julkaisupäivää', 'rytkoset-theme' ) . '</span>';
                        return;
                }

                if ( 'album_cover' === $column ) {
                        if ( has_post_thumbnail( $post_id ) ) {
                                echo wp_kses_post(
                                        get_the_post_thumbnail(
                                                $post_id,
                                                array( 72, 72 ),
                                                array(
                                                        'alt'   => '',
                                                        'style' => 'display:block;width:72px;height:72px;object-fit:cover;border-radius:8px;',
                                                )
                                        )
                                );
                                return;
                        }

                        echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Puuttuu', 'rytkoset-theme' ) . '</span>';
                        return;
                }

                if ( 'album_excerpt' === $column ) {
                        $excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );

                        if ( '' === $excerpt ) {
                                echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Puuttuu', 'rytkoset-theme' ) . '</span>';
                                return;
                        }

                        echo esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), 10, '...' ) );
                }
        }
}
add_action( 'manage_gallery_album_posts_custom_column', 'rytkoset_theme_render_gallery_album_admin_column', 10, 2 );

if ( ! function_exists( 'rytkoset_theme_gallery_album_sortable_columns' ) ) {
        /**
         * Marks gallery album admin columns sortable.
         *
         * @param array $columns Existing sortable columns.
         * @return array
         */
        function rytkoset_theme_gallery_album_sortable_columns( $columns ) {
                $columns['event_date'] = 'event_date';

                return $columns;
        }
}
add_filter( 'manage_edit-gallery_album_sortable_columns', 'rytkoset_theme_gallery_album_sortable_columns' );

if ( ! function_exists( 'rytkoset_theme_sort_gallery_album_admin_list' ) ) {
        /**
         * Sorts the album admin list by event date by default.
         *
         * @param WP_Query $query Query instance.
         * @return void
         */
        function rytkoset_theme_sort_gallery_album_admin_list( $query ) {
                if ( ! is_admin() || ! $query->is_main_query() || 'gallery_album' !== $query->get( 'post_type' ) ) {
                        return;
                }

                $orderby = (string) $query->get( 'orderby' );

                if ( '' !== $orderby && 'event_date' !== $orderby ) {
                        return;
                }

                $query->set( 'order', 'DESC' );
                rytkoset_theme_apply_gallery_album_event_date_order( $query );
        }
}
add_action( 'pre_get_posts', 'rytkoset_theme_sort_gallery_album_admin_list' );

if ( ! function_exists( 'rytkoset_theme_gallery_album_submitbox_hint' ) ) {
        /**
         * Shows a small readiness checklist in the album editor.
         *
         * @param WP_Post $post Post object.
         * @return void
         */
        function rytkoset_theme_gallery_album_submitbox_hint( $post ) {
                if ( ! $post instanceof WP_Post || 'gallery_album' !== $post->post_type ) {
                        return;
                }

                $has_thumbnail  = has_post_thumbnail( $post );
                $has_event_date = '' !== rytkoset_theme_get_album_event_date_raw( $post->ID );
                $has_excerpt    = '' !== trim( (string) $post->post_excerpt );

                echo '<div class="misc-pub-section">';
                echo '<strong>' . esc_html__( 'Albumin tarkistuslista', 'rytkoset-theme' ) . '</strong>';
                echo '<ul style="margin:8px 0 0 16px;list-style:disc;">';
                echo '<li>' . esc_html( $has_thumbnail ? __( 'Kansikuva asetettu', 'rytkoset-theme' ) : __( 'Lisää kansikuva', 'rytkoset-theme' ) ) . '</li>';
                echo '<li>' . esc_html( $has_event_date ? __( 'Tapahtumapäivä asetettu', 'rytkoset-theme' ) : __( 'Aseta tapahtumapäivä', 'rytkoset-theme' ) ) . '</li>';
                echo '<li>' . esc_html( $has_excerpt ? __( 'Ingressi lisätty', 'rytkoset-theme' ) : __( 'Lisää lyhyt ingressi', 'rytkoset-theme' ) ) . '</li>';
                echo '</ul>';
                echo '</div>';
        }
}
add_action( 'post_submitbox_misc_actions', 'rytkoset_theme_gallery_album_submitbox_hint' );

if ( ! function_exists( 'rytkoset_theme_enqueue_gallery_album_editor_assets' ) ) {
        /**
         * Tweaks the gallery album block editor UI.
         *
         * Final album image captions come from the media library, not from Gutenberg block captions.
         *
         * @return void
         */
		function rytkoset_theme_enqueue_gallery_album_editor_assets() {
				$screen = get_current_screen();

                if ( ! $screen || 'gallery_album' !== $screen->post_type ) {
                        return;
                }

				$message = __(
						'Albumikuvien kuvatekstit tulevat mediakirjastosta. Muokkaa kuvan caption ja description Media-kirjastossa, ei albumin lohkoeditorissa.',
						'rytkoset-theme'
				);
				$hide_video_helper = ! class_exists( 'acf_field_repeater' );

				$script = '( function( wp ) {
						if ( ! wp || ! wp.domReady || ! wp.data ) {
								return;
						}

                        function lockCaptionsInDocument( doc ) {
                                if ( ! doc ) {
                                        return;
                                }

                                if ( doc.head && ! doc.getElementById( "rytkoset-gallery-caption-lock" ) ) {
                                        var style = doc.createElement( "style" );

                                        style.id = "rytkoset-gallery-caption-lock";
                                        style.textContent = [
                                                "figcaption.wp-element-caption,",
                                                ".wp-block-image figcaption,",
                                                ".wp-block-gallery figcaption {",
                                                "display:none !important;",
                                                "pointer-events:none !important;",
                                                "user-select:none !important;",
                                                "}",
                                        ].join( "" );

                                        doc.head.appendChild( style );
                                }

                                doc.querySelectorAll( "figcaption.wp-element-caption, .wp-block-image figcaption, .wp-block-gallery figcaption" ).forEach( function( caption ) {
                                        caption.setAttribute( "aria-hidden", "true" );
                                        caption.setAttribute( "contenteditable", "false" );
                                        caption.style.display = "none";
                                        caption.style.pointerEvents = "none";
                                } );
                        }

						function lockCaptions() {
								lockCaptionsInDocument( document );

                                document.querySelectorAll( "iframe[name=\'editor-canvas\']" ).forEach( function( frame ) {
                                        try {
                                                lockCaptionsInDocument( frame.contentDocument );
                                        } catch ( error ) {
                                                // Ignore cross-document access errors.
                                        }
								} );
						}

						function cleanupVideoFieldHelpers( doc ) {
								if ( ! doc ) {
										return;
								}

								doc.querySelectorAll( "[data-key=\'field_gallery_videos\'] .acf-actions, [data-key=\'field_gallery_videos\'] .rytkoset-video-field-helper" ).forEach( function( element ) {
										element.remove();
								} );

								doc.querySelectorAll( "[data-key=\'field_gallery_videos\'] button, [data-key=\'field_gallery_videos\'] a.button, [data-key=\'field_gallery_videos\'] .button" ).forEach( function( element ) {
										if ( element.textContent && -1 !== element.textContent.indexOf( "YouTube-linkki" ) ) {
												element.remove();
										}
								} );
						}

						wp.domReady( function() {
								var noticeStore = wp.data.dispatch( "core/notices" );

                                if ( noticeStore && noticeStore.createNotice ) {
                                        noticeStore.createNotice(
                                                "info",
                                                ' . wp_json_encode( $message ) . ',
                                                {
                                                        id: "rytkoset-gallery-caption-note",
                                                        isDismissible: true
                                                }
                                        );
								}

								lockCaptions();
' . ( $hide_video_helper ? '
								cleanupVideoFieldHelpers( document );
' : '' ) . '

								if ( window.MutationObserver ) {
										var observer = new MutationObserver( function() {
												lockCaptions();
' . ( $hide_video_helper ? '
												cleanupVideoFieldHelpers( document );
' : '' ) . '
										} );
										observer.observe( document.body, { childList: true, subtree: true } );
								}

								window.setTimeout( lockCaptions, 250 );
								window.setTimeout( lockCaptions, 1000 );
' . ( $hide_video_helper ? '
								window.setTimeout( function() { cleanupVideoFieldHelpers( document ); }, 250 );
								window.setTimeout( function() { cleanupVideoFieldHelpers( document ); }, 1000 );
' : '' ) . '
						} );
				} )( window.wp );';

                wp_add_inline_script( 'wp-edit-post', $script, 'after' );
        }
}
add_action( 'enqueue_block_editor_assets', 'rytkoset_theme_enqueue_gallery_album_editor_assets' );

if ( ! function_exists( 'rytkoset_theme_strip_gallery_album_block_captions' ) ) {
        /**
         * Removes Gutenberg block figcaptions from gallery album content.
         *
         * Final album image captions are injected from attachment metadata on render.
         *
         * @param string $content Raw post content.
         * @return string
         */
        function rytkoset_theme_strip_gallery_album_block_captions( $content ) {
                if ( ! is_string( $content ) || '' === $content ) {
                        return $content;
                }

                $content = preg_replace( '/<figcaption\b[^>]*>.*?<\/figcaption>/is', '', $content );

                if ( ! is_string( $content ) ) {
                        return '';
                }

                return preg_replace( "/\n{3,}/", "\n\n", $content );
        }
}

if ( ! function_exists( 'rytkoset_theme_strip_gallery_album_block_captions_on_save' ) ) {
        /**
         * Ensures gallery albums do not persist editable Gutenberg image captions.
         *
         * @param array $data    Sanitized post data.
         * @param array $postarr Raw submitted post data.
         * @return array
         */
        function rytkoset_theme_strip_gallery_album_block_captions_on_save( $data, $postarr ) {
                if ( empty( $data['post_type'] ) || 'gallery_album' !== $data['post_type'] ) {
                        return $data;
                }

                if ( empty( $data['post_content'] ) || ! is_string( $data['post_content'] ) ) {
                        return $data;
                }

                $data['post_content'] = rytkoset_theme_strip_gallery_album_block_captions( $data['post_content'] );

                return $data;
        }
}
add_filter( 'wp_insert_post_data', 'rytkoset_theme_strip_gallery_album_block_captions_on_save', 10, 2 );

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

                $video_id = rytkoset_theme_get_youtube_video_id( $url );

                if ( '' !== $video_id ) {
                        $params = array(
                                'autoplay'        => 1,
                                'rel'             => 0,
                                'modestbranding'  => 1,
                                'playsinline'     => 1,
                        );

                        return add_query_arg( $params, 'https://www.youtube.com/embed/' . rawurlencode( $video_id ) );
                }

                return esc_url_raw( $url );
        }
}
