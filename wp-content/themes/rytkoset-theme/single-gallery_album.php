<?php
/**
 * Yksittäinen gallerialbumi.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

global $post;

get_header();
?>

<section class="section">
        <div class="container section__wide">
                <?php
                if ( have_posts() ) :
                        while ( have_posts() ) :
                                the_post();

                                $gallery_images = function_exists( 'get_field' ) ? (array) get_field( 'gallery_images' ) : array();
                                $gallery_videos = function_exists( 'get_field' ) ? (array) get_field( 'gallery_videos' ) : array();
                                ?>
                                <article <?php post_class( 'album' ); ?>>
                                        <header class="album__header">
                                                <p class="album__meta"><?php echo esc_html( get_the_date() ); ?></p>
                                                <h1 class="album__title"><?php the_title(); ?></h1>

                                                <?php if ( has_post_thumbnail() ) : ?>
                                                        <div class="album__cover"><?php the_post_thumbnail( 'large' ); ?></div>
                                                <?php endif; ?>
                                        </header>

                                        <div class="album__content"><?php the_content(); ?></div>

                                        <?php if ( ! empty( $gallery_videos ) ) : ?>
                                                <div class="album__videos">
                                                        <h2 class="album__section-title"><?php esc_html_e( 'Videot', 'rytkoset-theme' ); ?></h2>
                                                        <?php foreach ( $gallery_videos as $video ) :
                                                                $video_url  = isset( $video['video_url'] ) ? $video['video_url'] : '';
                                                                $embed_url  = rytkoset_theme_get_video_embed_url( $video_url );
                                                                $embed_html = $video_url ? wp_oembed_get( $video_url ) : '';

                                                                if ( empty( $embed_url ) && empty( $embed_html ) ) {
                                                                        continue;
                                                                }
                                                                ?>
                                                                <div class="album__video">
                                                                        <div class="album__video-embed">
                                                                                <?php
                                                                                if ( ! empty( $embed_html ) ) {
                                                                                        echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                                                } else {
                                                                                        ?>
                                                                                        <iframe src="<?php echo esc_url( $embed_url ); ?>" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                                                                                        <?php
                                                                                }
                                                                                ?>
                                                                        </div>
                                                                </div>
                                                                <?php
                                                        endforeach;
                                                        ?>
                                                </div>
                                        <?php endif; ?>

                                        <?php
                                        $gallery_items = array();

                                        if ( ! empty( $gallery_images ) ) {
                                                foreach ( $gallery_images as $image ) {
                                                        if ( empty( $image['ID'] ) ) {
                                                                continue;
                                                        }

                                                        $full  = wp_get_attachment_image_src( $image['ID'], 'full' );
                                                        $thumb = wp_get_attachment_image_src( $image['ID'], 'large' );

                                                        if ( ! $full ) {
                                                                continue;
                                                        }

                                                        $gallery_items[] = array(
                                                                'type'        => 'image',
                                                                'src'         => $full[0],
                                                                'width'       => isset( $full[1] ) ? (int) $full[1] : 0,
                                                                'height'      => isset( $full[2] ) ? (int) $full[2] : 0,
                                                                'alt'         => isset( $image['alt'] ) ? $image['alt'] : '',
                                                                'srcset'      => wp_get_attachment_image_srcset( $image['ID'], 'large' ),
                                                                'sizes'       => '(min-width: 960px) 25vw, 90vw',
                                                                'thumb_src'   => $thumb ? $thumb[0] : $full[0],
                                                                'thumb_srcset' => wp_get_attachment_image_srcset( $image['ID'], 'large' ),
                                                                'pswp_srcset' => wp_get_attachment_image_srcset( $image['ID'], 'full' ),
                                                                'pswp_sizes'  => '100vw',
                                                        );
                                                }
                                        }

                                        if ( ! empty( $gallery_videos ) ) {
                                                foreach ( $gallery_videos as $video ) {
                                                        $video_url = isset( $video['video_url'] ) ? $video['video_url'] : '';
                                                        $embed_url = rytkoset_theme_get_video_embed_url( $video_url );

                                                        if ( empty( $embed_url ) ) {
                                                                continue;
                                                        }

                                                        $thumbnail      = isset( $video['video_thumbnail']['ID'] ) ? (int) $video['video_thumbnail']['ID'] : 0;
                                                        $thumbnail_src  = $thumbnail ? wp_get_attachment_image_url( $thumbnail, 'large' ) : '';
                                                        $thumbnail_set  = $thumbnail ? wp_get_attachment_image_srcset( $thumbnail, 'large' ) : '';
                                                        $thumbnail_alt  = $thumbnail ? get_post_meta( $thumbnail, '_wp_attachment_image_alt', true ) : '';
                                                        $thumbnail_meta = $thumbnail ? wp_get_attachment_image_src( $thumbnail, 'full' ) : array( 1280, 720 );

                                                        $gallery_items[] = array(
                                                                'type'             => 'video',
                                                                'video_src'        => $embed_url,
                                                                'poster'           => $thumbnail_src,
                                                                'srcset'           => $thumbnail_set,
                                                                'alt'              => $thumbnail_alt,
                                                                'width'            => isset( $thumbnail_meta[1] ) ? (int) $thumbnail_meta[1] : 1280,
                                                                'height'           => isset( $thumbnail_meta[2] ) ? (int) $thumbnail_meta[2] : 720,
                                                                'pswp_srcset'      => $thumbnail ? wp_get_attachment_image_srcset( $thumbnail, 'full' ) : '',
                                                                'pswp_sizes'       => '100vw',
                                                                'thumb_src'        => $thumbnail_src,
                                                                'thumb_srcset'     => $thumbnail_set,
                                                        );
                                                }
                                        }

                                        if ( ! empty( $gallery_items ) ) :
                                                ?>
                                                <div class="album__gallery">
                                                        <h2 class="album__section-title"><?php esc_html_e( 'Kuvat ja videot', 'rytkoset-theme' ); ?></h2>
                                                        <div class="gallery-grid js-gallery-grid" data-pswp-gallery="album-<?php echo esc_attr( get_the_ID() ); ?>">
                                                                <?php foreach ( $gallery_items as $item ) : ?>
                                                                        <?php if ( 'video' === $item['type'] ) : ?>
                                                                                <a
                                                                                        class="gallery-grid__item js-gallery-item"
                                                                                        href="<?php echo esc_url( $item['video_src'] ); ?>"
                                                                                        data-video-src="<?php echo esc_url( $item['video_src'] ); ?>"
                                                                                        data-pswp-width="<?php echo esc_attr( $item['width'] ); ?>"
                                                                                        data-pswp-height="<?php echo esc_attr( $item['height'] ); ?>"
                                                                                        <?php if ( ! empty( $item['poster'] ) ) : ?>data-poster="<?php echo esc_url( $item['poster'] ); ?>"<?php endif; ?>
                                                                                        <?php if ( ! empty( $item['pswp_srcset'] ) ) : ?>data-pswp-srcset="<?php echo esc_attr( $item['pswp_srcset'] ); ?>"<?php endif; ?>
                                                                                        <?php if ( ! empty( $item['pswp_sizes'] ) ) : ?>data-pswp-sizes="<?php echo esc_attr( $item['pswp_sizes'] ); ?>"<?php endif; ?>
                                                                                >
                                                                                        <?php if ( ! empty( $item['thumb_src'] ) ) : ?>
                                                                                                <img
                                                                                                        class="gallery-grid__image"
                                                                                                        src="<?php echo esc_url( $item['thumb_src'] ); ?>"
                                                                                                        <?php if ( ! empty( $item['thumb_srcset'] ) ) : ?>srcset="<?php echo esc_attr( $item['thumb_srcset'] ); ?>"<?php endif; ?>
                                                                                                        sizes="(min-width: 960px) 25vw, 90vw"
                                                                                                        alt="<?php echo esc_attr( $item['alt'] ); ?>"
                                                                                                />
                                                                                        <?php else : ?>
                                                                                                <span class="gallery-grid__placeholder">Video</span>
                                                                                        <?php endif; ?>
                                                                                        <span class="gallery-grid__badge">
                                                                                                <span aria-hidden="true">▶</span>
                                                                                                <span><?php esc_html_e( 'Video', 'rytkoset-theme' ); ?></span>
                                                                                        </span>
                                                                                </a>
                                                                        <?php else : ?>
                                                                                <a
                                                                                        class="gallery-grid__item js-gallery-item"
                                                                                        href="<?php echo esc_url( $item['src'] ); ?>"
                                                                                        data-pswp-width="<?php echo esc_attr( $item['width'] ); ?>"
                                                                                        data-pswp-height="<?php echo esc_attr( $item['height'] ); ?>"
                                                                                        <?php if ( ! empty( $item['pswp_srcset'] ) ) : ?>data-pswp-srcset="<?php echo esc_attr( $item['pswp_srcset'] ); ?>"<?php endif; ?>
                                                                                        <?php if ( ! empty( $item['pswp_sizes'] ) ) : ?>data-pswp-sizes="<?php echo esc_attr( $item['pswp_sizes'] ); ?>"<?php endif; ?>
                                                                                >
                                                                                        <img
                                                                                                class="gallery-grid__image"
                                                                                                src="<?php echo esc_url( $item['thumb_src'] ); ?>"
                                                                                                <?php if ( ! empty( $item['thumb_srcset'] ) ) : ?>srcset="<?php echo esc_attr( $item['thumb_srcset'] ); ?>"<?php endif; ?>
                                                                                                sizes="<?php echo esc_attr( $item['sizes'] ); ?>"
                                                                                                alt="<?php echo esc_attr( $item['alt'] ); ?>"
                                                                                        />
                                                                                </a>
                                                                        <?php endif; ?>
                                                                <?php endforeach; ?>
                                                        </div>
                                                </div>
                                                <?php
                                        endif;
                                        ?>
                                </article>
                                <?php
                        endwhile;
                endif;
                ?>
        </div>
</section>

<?php
get_footer();
