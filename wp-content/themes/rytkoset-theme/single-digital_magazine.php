<?php
/**
 * Yksittäinen digilehti tai digilehden juttu.
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

		$post_id             = get_the_ID();
		$rytkoset_is_article = rytkoset_theme_is_digital_magazine_article( $post_id );

		if ( $rytkoset_is_article ) :
			$rytkoset_magazine_id      = rytkoset_theme_get_digital_magazine_parent_id( $post_id );
			$rytkoset_previous_article = rytkoset_theme_get_adjacent_digital_magazine_article( $post_id, 'previous' );
			$rytkoset_next_article     = rytkoset_theme_get_adjacent_digital_magazine_article( $post_id, 'next' );
			?>
			<article <?php post_class( 'digital-magazine digital-magazine--article' ); ?>>
				<header class="digital-magazine-reader__header">
					<div class="container section__narrow">
						<a class="digital-magazine-reader__back" href="<?php echo esc_url( get_permalink( $rytkoset_magazine_id ) ); ?>">
							<?php
							printf(
								/* translators: %s: magazine title */
								esc_html__( 'Takaisin lehteen: %s', 'rytkoset-theme' ),
								esc_html( get_the_title( $rytkoset_magazine_id ) )
							);
							?>
						</a>
						<p class="digital-magazine-reader__meta"><?php echo esc_html( get_the_title( $rytkoset_magazine_id ) ); ?></p>
						<h1 class="digital-magazine-reader__title"><?php the_title(); ?></h1>
					</div>
				</header>

				<section class="section digital-magazine-reader__section">
					<div class="container section__narrow">
						<div class="article__content digital-magazine-reader__content">
							<?php the_content(); ?>
						</div>

						<nav class="digital-magazine-reader__nav" aria-label="<?php esc_attr_e( 'Digilehden juttunavigaatio', 'rytkoset-theme' ); ?>">
							<?php if ( $rytkoset_previous_article instanceof WP_Post ) : ?>
								<a class="digital-magazine-reader__nav-link digital-magazine-reader__nav-link--previous" href="<?php echo esc_url( get_permalink( $rytkoset_previous_article ) ); ?>">
									<span><?php esc_html_e( 'Edellinen juttu', 'rytkoset-theme' ); ?></span>
									<strong><?php echo esc_html( get_the_title( $rytkoset_previous_article ) ); ?></strong>
								</a>
							<?php endif; ?>

							<?php if ( $rytkoset_next_article instanceof WP_Post ) : ?>
								<a class="digital-magazine-reader__nav-link digital-magazine-reader__nav-link--next" href="<?php echo esc_url( get_permalink( $rytkoset_next_article ) ); ?>">
									<span><?php esc_html_e( 'Seuraava juttu', 'rytkoset-theme' ); ?></span>
									<strong><?php echo esc_html( get_the_title( $rytkoset_next_article ) ); ?></strong>
								</a>
							<?php endif; ?>
						</nav>
					</div>
				</section>
			</article>
			<?php
		else :
			$rytkoset_articles = rytkoset_theme_get_digital_magazine_articles( $post_id );
			?>
			<article <?php post_class( 'digital-magazine digital-magazine--issue' ); ?>>
				<header class="digital-magazine-issue__hero<?php echo has_post_thumbnail() ? '' : ' digital-magazine-issue__hero--no-image'; ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="digital-magazine-issue__media">
							<?php
							the_post_thumbnail(
								'full',
								array(
									'class'         => 'digital-magazine-issue__image',
									'loading'       => 'eager',
									'fetchpriority' => 'high',
									'sizes'         => '100vw',
								)
							);
							?>
						</div>
					<?php endif; ?>

					<div class="digital-magazine-issue__content">
						<div class="container section__wide">
							<p class="digital-magazine-issue__meta"><?php esc_html_e( 'Digilehti', 'rytkoset-theme' ); ?></p>
							<h1 class="digital-magazine-issue__title"><?php the_title(); ?></h1>
							<?php if ( has_excerpt() ) : ?>
								<div class="digital-magazine-issue__lead"><?php the_excerpt(); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</header>

				<section class="section">
					<div class="container section__wide">
						<?php if ( trim( (string) get_post_field( 'post_content', $post_id ) ) ) : ?>
							<div class="article__content digital-magazine-issue__body">
								<?php the_content(); ?>
							</div>
						<?php endif; ?>

						<section class="digital-magazine-toc" aria-labelledby="digital-magazine-toc-heading">
							<h2 id="digital-magazine-toc-heading"><?php esc_html_e( 'Sisällysluettelo', 'rytkoset-theme' ); ?></h2>

							<?php if ( ! empty( $rytkoset_articles ) ) : ?>
								<ol class="digital-magazine-toc__list">
									<?php foreach ( $rytkoset_articles as $rytkoset_article ) : ?>
										<li class="digital-magazine-toc__item">
											<a class="digital-magazine-toc__link" href="<?php echo esc_url( get_permalink( $rytkoset_article ) ); ?>">
												<span class="digital-magazine-toc__title"><?php echo esc_html( get_the_title( $rytkoset_article ) ); ?></span>
												<?php if ( has_excerpt( $rytkoset_article ) ) : ?>
													<span class="digital-magazine-toc__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $rytkoset_article ), 24 ) ); ?></span>
												<?php endif; ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ol>
							<?php else : ?>
								<p><?php esc_html_e( 'Tähän lehteen ei ole vielä julkaistu juttuja.', 'rytkoset-theme' ); ?></p>
							<?php endif; ?>
						</section>
					</div>
				</section>
			</article>
			<?php
		endif;
	endwhile;
endif;
?>
</main>

<?php
get_footer();
