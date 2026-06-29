<?php
/**
 * Digilehtien arkisto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main" tabindex="-1">
	<section class="section">
		<div class="container section__wide">
			<header class="section__header">
				<h1 class="section__title"><?php post_type_archive_title(); ?></h1>
				<?php if ( get_the_archive_description() ) : ?>
					<div class="section__description"><?php echo wp_kses_post( wpautop( get_the_archive_description() ) ); ?></div>
				<?php endif; ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="digital-magazine-archive">
					<?php
					while ( have_posts() ) :
						the_post();

						$rytkoset_post_id       = get_the_ID();
						$rytkoset_can_read      = rytkoset_theme_user_can_read_digital_magazine( $rytkoset_post_id );
						$rytkoset_is_restricted = rytkoset_theme_digital_magazine_is_restricted( $rytkoset_post_id );
						$rytkoset_excerpt       = '';

						if ( $rytkoset_can_read || ! $rytkoset_is_restricted ) {
							$rytkoset_excerpt = trim( get_the_excerpt() );

							if ( '' === $rytkoset_excerpt ) {
								$rytkoset_excerpt = wp_strip_all_tags( get_the_content() );
							}
						} elseif ( has_excerpt() ) {
							$rytkoset_excerpt = trim( get_the_excerpt() );
						}
						?>
						<article <?php post_class( 'digital-magazine-card' ); ?>>
							<a class="digital-magazine-card__media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'large' ); ?>
								<?php else : ?>
									<span class="digital-magazine-card__placeholder" aria-hidden="true">
										<?php esc_html_e( 'Digilehti', 'rytkoset-theme' ); ?>
									</span>
								<?php endif; ?>
							</a>

							<div class="digital-magazine-card__body">
								<?php if ( $rytkoset_is_restricted && ! $rytkoset_can_read ) : ?>
									<p class="digital-magazine-card__badge">
										<?php
										echo esc_html(
											rytkoset_theme_get_digital_magazine_access_mode_label(
												rytkoset_theme_get_digital_magazine_access_mode( $rytkoset_post_id )
											)
										);
										?>
									</p>
								<?php endif; ?>

								<h2 class="digital-magazine-card__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>

								<?php if ( '' !== $rytkoset_excerpt ) : ?>
									<p class="digital-magazine-card__excerpt"><?php echo esc_html( wp_trim_words( $rytkoset_excerpt, 32 ) ); ?></p>
								<?php elseif ( $rytkoset_is_restricted && ! $rytkoset_can_read ) : ?>
									<p class="digital-magazine-card__excerpt">
										<?php esc_html_e( 'Sisältö avautuu, kun lukuoikeus on voimassa.', 'rytkoset-theme' ); ?>
									</p>
								<?php endif; ?>

								<a class="btn btn--light digital-magazine-card__link" href="<?php the_permalink(); ?>">
									<?php esc_html_e( 'Avaa lehti', 'rytkoset-theme' ); ?>
								</a>
							</div>
						</article>
						<?php
					endwhile;
					?>
				</div>

				<?php
				the_posts_pagination(
					array(
						'prev_text' => __( 'Edelliset', 'rytkoset-theme' ),
						'next_text' => __( 'Seuraavat', 'rytkoset-theme' ),
					)
				);
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'Digilehtiä ei ole vielä julkaistu.', 'rytkoset-theme' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
