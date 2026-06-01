<?php
/**
 * Hakutulosten sivupohja.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main" tabindex="-1">
<section class="section">
	<div class="container section__narrow">

		<header class="search-results-header">
			<h1 class="search-results-header__title">
				<?php
				printf(
					/* translators: %s: search query */
					esc_html__( 'Hakutulokset: "%s"', 'rytkoset-theme' ),
					get_search_query()
				);
				?>
			</h1>
			<?php if ( have_posts() ) : ?>
				<p class="search-results-header__count">
					<?php
					printf(
						/* translators: %d: number of results */
						esc_html( _n( '%d tulos', '%d tulosta', (int) $wp_query->found_posts, 'rytkoset-theme' ) ),
						(int) $wp_query->found_posts
					);
					?>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>

			<ol class="search-results" role="list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<li class="search-result">
						<article class="search-result__article">
							<p class="search-result__meta">
								<span class="search-result__type"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></span>
								<time class="search-result__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
							</p>
							<h2 class="search-result__title">
								<a class="search-result__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<?php if ( has_excerpt() || get_the_excerpt() ) : ?>
								<p class="search-result__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
							<?php endif; ?>
						</article>
					</li>
				<?php endwhile; ?>
			</ol>

			<?php the_posts_pagination( array( 'mid_size' => 2 ) ); ?>

		<?php else : ?>

			<div class="search-results--empty">
				<p><?php esc_html_e( 'Haullasi ei löytynyt tuloksia. Kokeile eri hakusanaa.', 'rytkoset-theme' ); ?></p>
				<?php get_search_form(); ?>
			</div>

		<?php endif; ?>

	</div>
</section>
</main>

<?php get_footer(); ?>
