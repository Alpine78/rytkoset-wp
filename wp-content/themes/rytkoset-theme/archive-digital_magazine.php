<?php
/**
 * Digilehtien arkisto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main">
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
						$excerpt = trim( get_the_excerpt() );

						if ( '' === $excerpt ) {
							$excerpt = wp_strip_all_tags( get_the_content() );
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
								<h2 class="digital-magazine-card__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>

								<?php if ( '' !== $excerpt ) : ?>
									<p class="digital-magazine-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 32 ) ); ?></p>
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
