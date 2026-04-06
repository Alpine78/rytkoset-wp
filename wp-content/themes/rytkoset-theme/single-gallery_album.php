<?php
/**
 * Single gallery album template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="primary" class="site-main">
<?php

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$gallery_images     = function_exists( 'get_field' ) ? (array) get_field( 'gallery_images' ) : array();
		$gallery_videos     = rytkoset_theme_get_gallery_album_video_rows( get_the_ID() );
		$album_date         = rytkoset_theme_get_album_display_date( get_the_ID() );
		$album_description  = trim( (string) get_post_field( 'post_excerpt', get_the_ID() ) );
		$album_body_content = trim( (string) get_post_field( 'post_content', get_the_ID() ) );
		$album_videos       = array();

		if ( ! empty( $gallery_videos ) ) {
			foreach ( $gallery_videos as $video ) {
				$video_url  = isset( $video['video_url'] ) ? $video['video_url'] : '';
				$embed_url  = rytkoset_theme_get_video_embed_url( $video_url );
				$embed_html = $video_url ? wp_oembed_get( $video_url ) : '';

				if ( empty( $embed_url ) && empty( $embed_html ) ) {
					continue;
				}

				$thumbnail      = isset( $video['video_thumbnail_id'] ) ? (int) $video['video_thumbnail_id'] : 0;
				$thumbnail_src  = $thumbnail ? wp_get_attachment_image_url( $thumbnail, 'large' ) : rytkoset_theme_get_video_thumbnail_url( $video_url );
				$thumbnail_set  = $thumbnail ? wp_get_attachment_image_srcset( $thumbnail, 'large' ) : '';
				$thumbnail_alt  = $thumbnail ? get_post_meta( $thumbnail, '_wp_attachment_image_alt', true ) : '';
				$thumbnail_meta = $thumbnail ? wp_get_attachment_image_src( $thumbnail, 'full' ) : array( $thumbnail_src, 480, 360 );

				$album_videos[] = array(
					'video_url'             => $video_url,
					'embed_url'             => $embed_url,
					'embed_html'            => $embed_html,
					'thumbnail_id'          => $thumbnail,
					'thumbnail_src'         => $thumbnail_src,
					'thumbnail_srcset'      => $thumbnail_set,
					'thumbnail_alt'         => $thumbnail_alt,
					'thumbnail_width'       => isset( $thumbnail_meta[1] ) ? (int) $thumbnail_meta[1] : 1280,
					'thumbnail_height'      => isset( $thumbnail_meta[2] ) ? (int) $thumbnail_meta[2] : 720,
					'caption_html'          => $thumbnail ? rytkoset_theme_get_attachment_caption_html( $thumbnail ) : '',
					'visible_caption_html'  => $thumbnail ? rytkoset_theme_get_attachment_visible_caption_html( $thumbnail ) : '',
					'item_id'               => 'video-' . substr( md5( $embed_url ), 0, 12 ),
				);
			}
		}
		?>
		<article <?php post_class( 'album' ); ?>>
			<header class="album-hero<?php echo has_post_thumbnail() ? '' : ' album-hero--no-image'; ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="album-hero__media">
						<?php
						the_post_thumbnail(
							'full',
							array(
								'class'         => 'album-hero__image',
								'loading'       => 'eager',
								'fetchpriority' => 'high',
								'sizes'         => '100vw',
							)
						);
						?>
					</div>
				<?php endif; ?>

				<div class="album-hero__content">
					<div class="container section__wide">
						<div class="album-hero__copy">
							<p class="album-hero__meta"><?php echo esc_html( $album_date ); ?></p>
							<h1 class="album-hero__title"><?php the_title(); ?></h1>

							<?php if ( '' !== $album_description ) : ?>
								<div class="album-hero__lead"><?php echo wp_kses_post( wpautop( $album_description ) ); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</header>

			<section class="section album__section">
				<div class="container section__wide">
					<div class="album__body">
						<?php if ( '' !== $album_body_content ) : ?>
							<div class="album__content"><?php the_content(); ?></div>
						<?php endif; ?>

						<?php if ( ! empty( $album_videos ) ) : ?>
							<div class="album__videos">
								<h2 class="album__section-title"><?php esc_html_e( 'Videot', 'rytkoset-theme' ); ?></h2>
								<?php foreach ( $album_videos as $video ) : ?>
									<div class="album__video">
										<div class="album__video-embed">
											<?php
											if ( ! empty( $video['embed_html'] ) ) {
												echo $video['embed_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											} else {
												?>
												<iframe src="<?php echo esc_url( $video['embed_url'] ); ?>" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>
												<?php
											}
											?>
										</div>
									</div>
								<?php endforeach; ?>
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
									'type'                 => 'image',
									'item_id'              => (string) $image['ID'],
									'src'                  => $full[0],
									'width'                => isset( $full[1] ) ? (int) $full[1] : 0,
									'height'               => isset( $full[2] ) ? (int) $full[2] : 0,
									'alt'                  => isset( $image['alt'] ) ? $image['alt'] : '',
									'srcset'               => wp_get_attachment_image_srcset( $image['ID'], 'large' ),
									'sizes'                => '(min-width: 960px) 25vw, 90vw',
									'thumb_src'            => $thumb ? $thumb[0] : $full[0],
									'thumb_srcset'         => wp_get_attachment_image_srcset( $image['ID'], 'large' ),
									'pswp_srcset'          => wp_get_attachment_image_srcset( $image['ID'], 'full' ),
									'pswp_sizes'           => '100vw',
									'caption_html'         => rytkoset_theme_get_attachment_caption_html( $image['ID'] ),
									'visible_caption_html' => rytkoset_theme_get_attachment_visible_caption_html( $image['ID'] ),
								);
							}
						}

						if ( ! empty( $gallery_items ) ) :
							?>
							<div class="album__gallery">
								<h2 class="album__section-title"><?php esc_html_e( 'Kuvat', 'rytkoset-theme' ); ?></h2>
								<div class="gallery-grid js-gallery-grid" data-pswp-gallery="album-<?php echo esc_attr( get_the_ID() ); ?>">
									<?php foreach ( $gallery_items as $item ) : ?>
										<a
											class="gallery-grid__item js-gallery-item"
											href="<?php echo esc_url( $item['src'] ); ?>"
											data-pswp-item-id="<?php echo esc_attr( $item['item_id'] ); ?>"
											data-pswp-width="<?php echo esc_attr( $item['width'] ); ?>"
											data-pswp-height="<?php echo esc_attr( $item['height'] ); ?>"
											<?php if ( ! empty( $item['pswp_srcset'] ) ) : ?>data-pswp-srcset="<?php echo esc_attr( $item['pswp_srcset'] ); ?>"<?php endif; ?>
											<?php if ( ! empty( $item['pswp_sizes'] ) ) : ?>data-pswp-sizes="<?php echo esc_attr( $item['pswp_sizes'] ); ?>"<?php endif; ?>
											<?php if ( ! empty( $item['caption_html'] ) ) : ?>data-pswp-caption-html="<?php echo esc_attr( $item['caption_html'] ); ?>"<?php endif; ?>
										>
											<img
												class="gallery-grid__image"
												src="<?php echo esc_url( $item['thumb_src'] ); ?>"
												<?php if ( ! empty( $item['thumb_srcset'] ) ) : ?>srcset="<?php echo esc_attr( $item['thumb_srcset'] ); ?>"<?php endif; ?>
												sizes="<?php echo esc_attr( $item['sizes'] ); ?>"
												alt="<?php echo esc_attr( $item['alt'] ); ?>"
											/>
											<?php if ( ! empty( $item['visible_caption_html'] ) ) : ?>
												<div class="gallery-grid__caption"><?php echo wp_kses_post( $item['visible_caption_html'] ); ?></div>
											<?php endif; ?>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
							<?php
						endif;
						?>
					</div>
				</div>
			</section>
		</article>
		<?php
	endwhile;
endif;

?>
</main>
<?php

get_footer();
