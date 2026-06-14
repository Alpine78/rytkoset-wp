<?php
/**
 * Single gallery album template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="primary" class="site-main" tabindex="-1">
<?php

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$rytkoset_gallery_images     = function_exists( 'get_field' ) ? (array) get_field( 'gallery_images' ) : array();
		$rytkoset_gallery_videos     = rytkoset_theme_get_gallery_album_video_rows( get_the_ID() );
		$rytkoset_album_date         = rytkoset_theme_get_album_display_date( get_the_ID() );
		$rytkoset_album_description  = trim( (string) get_post_field( 'post_excerpt', get_the_ID() ) );
		$rytkoset_album_body_content = trim( (string) get_post_field( 'post_content', get_the_ID() ) );
		$rytkoset_album_videos       = array();

		$rytkoset_gallery_images = rytkoset_theme_sort_gallery_images_by_filename( $rytkoset_gallery_images );

		if ( ! empty( $rytkoset_gallery_videos ) ) {
			foreach ( $rytkoset_gallery_videos as $rytkoset_video ) {
				$rytkoset_video_url = isset( $rytkoset_video['video_url'] ) ? $rytkoset_video['video_url'] : '';
				$rytkoset_embed_url = rytkoset_theme_get_video_embed_url( $rytkoset_video_url );

				if ( empty( $rytkoset_embed_url ) ) {
					continue;
				}

				$rytkoset_album_videos[] = array(
					'embed_url' => $rytkoset_embed_url,
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
							<p class="album-hero__meta"><?php echo esc_html( $rytkoset_album_date ); ?></p>
							<h1 class="album-hero__title"><?php the_title(); ?></h1>

							<?php if ( '' !== $rytkoset_album_description ) : ?>
								<div class="album-hero__lead"><?php echo wp_kses_post( wpautop( $rytkoset_album_description ) ); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</header>

			<section class="section album__section">
				<div class="container section__wide">
					<div class="album__body">
						<?php if ( '' !== $rytkoset_album_body_content ) : ?>
							<div class="album__content"><?php the_content(); ?></div>
						<?php endif; ?>

						<?php if ( ! empty( $rytkoset_album_videos ) ) : ?>
							<div class="album__videos">
								<h2 class="album__section-title"><?php esc_html_e( 'Videot', 'rytkoset-theme' ); ?></h2>
								<?php
								$rytkoset_album_title_plain = wp_strip_all_tags( get_the_title() );
								$rytkoset_videos_total      = count( $rytkoset_album_videos );
								?>
								<?php foreach ( $rytkoset_album_videos as $rytkoset_video_index => $rytkoset_video ) : ?>
									<?php
									if ( $rytkoset_videos_total > 1 ) {
										$rytkoset_iframe_title = sprintf(
											/* translators: 1: video number, 2: total number of videos, 3: album title */
											__( 'Video %1$d/%2$d albumissa %3$s', 'rytkoset-theme' ),
											$rytkoset_video_index + 1,
											$rytkoset_videos_total,
											$rytkoset_album_title_plain
										);
									} else {
										$rytkoset_iframe_title = sprintf(
											/* translators: %s: album title */
											__( 'Video albumissa %s', 'rytkoset-theme' ),
											$rytkoset_album_title_plain
										);
									}
									?>
									<div class="album__video">
										<div class="album__video-embed">
											<iframe
												src="<?php echo esc_url( $rytkoset_video['embed_url'] ); ?>"
												title="<?php echo esc_attr( $rytkoset_iframe_title ); ?>"
												allow="encrypted-media; picture-in-picture"
												allowfullscreen
												loading="lazy"
												referrerpolicy="strict-origin-when-cross-origin"
											></iframe>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php
						$rytkoset_gallery_items = array();

						if ( ! empty( $rytkoset_gallery_images ) ) {
							foreach ( $rytkoset_gallery_images as $rytkoset_image ) {
								$rytkoset_image_id = rytkoset_theme_get_gallery_image_attachment_id( $rytkoset_image );

								if ( ! $rytkoset_image_id ) {
									continue;
								}

								$rytkoset_full  = wp_get_attachment_image_src( $rytkoset_image_id, 'full' );
								$rytkoset_thumb = wp_get_attachment_image_src( $rytkoset_image_id, 'large' );

								if ( ! $rytkoset_full ) {
									continue;
								}

								$rytkoset_explicit_alt = is_array( $rytkoset_image ) && isset( $rytkoset_image['alt'] ) ? (string) $rytkoset_image['alt'] : '';

								$rytkoset_gallery_items[] = array(
									'type'                 => 'image',
									'item_id'              => (string) $rytkoset_image_id,
									'src'                  => $rytkoset_full[0],
									'width'                => isset( $rytkoset_full[1] ) ? (int) $rytkoset_full[1] : 0,
									'height'               => isset( $rytkoset_full[2] ) ? (int) $rytkoset_full[2] : 0,
									'alt'                  => rytkoset_theme_get_gallery_image_alt( $rytkoset_image_id, $rytkoset_explicit_alt ),
									'srcset'               => wp_get_attachment_image_srcset( $rytkoset_image_id, 'large' ),
									'sizes'                => '(min-width: 960px) 25vw, 90vw',
									'thumb_src'            => $rytkoset_thumb ? $rytkoset_thumb[0] : $rytkoset_full[0],
									'thumb_srcset'         => wp_get_attachment_image_srcset( $rytkoset_image_id, 'large' ),
									'pswp_srcset'          => wp_get_attachment_image_srcset( $rytkoset_image_id, 'full' ),
									'pswp_sizes'           => '100vw',
									'caption_html'         => rytkoset_theme_get_attachment_caption_html( $rytkoset_image_id ),
									'visible_caption_html' => rytkoset_theme_get_attachment_visible_caption_html( $rytkoset_image_id ),
								);
							}
						}

						if ( ! empty( $rytkoset_gallery_items ) ) :
							?>
							<div class="album__gallery">
								<h2 class="album__section-title"><?php esc_html_e( 'Kuvat', 'rytkoset-theme' ); ?></h2>
								<div class="gallery-grid js-gallery-grid" data-pswp-gallery="album-<?php echo esc_attr( get_the_ID() ); ?>">
									<?php foreach ( $rytkoset_gallery_items as $rytkoset_item ) : ?>
										<a
											class="gallery-grid__item js-gallery-item"
											href="<?php echo esc_url( $rytkoset_item['src'] ); ?>"
											data-pswp-item-id="<?php echo esc_attr( $rytkoset_item['item_id'] ); ?>"
											data-pswp-width="<?php echo esc_attr( $rytkoset_item['width'] ); ?>"
											data-pswp-height="<?php echo esc_attr( $rytkoset_item['height'] ); ?>"
											<?php
											if ( ! empty( $rytkoset_item['pswp_srcset'] ) ) :
												?>
												data-pswp-srcset="<?php echo esc_attr( $rytkoset_item['pswp_srcset'] ); ?>"<?php endif; ?>
											<?php
											if ( ! empty( $rytkoset_item['pswp_sizes'] ) ) :
												?>
												data-pswp-sizes="<?php echo esc_attr( $rytkoset_item['pswp_sizes'] ); ?>"<?php endif; ?>
											<?php
											if ( ! empty( $rytkoset_item['caption_html'] ) ) :
												?>
												data-pswp-caption-html="<?php echo esc_attr( $rytkoset_item['caption_html'] ); ?>"<?php endif; ?>
										>
											<img
												class="gallery-grid__image"
												src="<?php echo esc_url( $rytkoset_item['thumb_src'] ); ?>"
												<?php
												if ( ! empty( $rytkoset_item['thumb_srcset'] ) ) :
													?>
													srcset="<?php echo esc_attr( $rytkoset_item['thumb_srcset'] ); ?>"<?php endif; ?>
												sizes="<?php echo esc_attr( $rytkoset_item['sizes'] ); ?>"
												alt="<?php echo esc_attr( $rytkoset_item['alt'] ); ?>"
											/>
											<?php if ( ! empty( $rytkoset_item['visible_caption_html'] ) ) : ?>
												<div class="gallery-grid__caption"><?php echo wp_kses_post( $rytkoset_item['visible_caption_html'] ); ?></div>
											<?php endif; ?>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
							<?php
						endif;
						?>
					</div>

					<?php
					rytkoset_theme_share_buttons(
						array(
							'heading' => __( 'Jaa albumi', 'rytkoset-theme' ),
							'post_id' => get_the_ID(),
						)
					);
					?>
				</div>
			</section>
		</article>

		<section class="section album-comments-section">
			<div class="container section__wide">
				<div class="album-comments">
					<?php comments_template(); ?>
				</div>
			</div>
		</section>
		<?php
	endwhile;
endif;

?>
</main>
<?php

get_footer();
