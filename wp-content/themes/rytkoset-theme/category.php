<?php
/**
 * Blog category archive.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

global $wp_query;

$blog_page        = get_page_by_path( 'blogi' );
$blog_url         = $blog_page instanceof WP_Post ? get_permalink( $blog_page ) : home_url( '/blogi/' );
$category_name    = single_cat_title( '', false );
$category_excerpt = category_description();
?>

<main id="primary" class="site-main" tabindex="-1">
	<section class="section">
		<div class="container">
			<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Murupolku', 'rytkoset-theme' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Etusivu', 'rytkoset-theme' ); ?></a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'Blogi', 'rytkoset-theme' ); ?></a>
				<span aria-hidden="true">/</span>
				<span><?php echo esc_html( $category_name ); ?></span>
			</nav>

			<header class="section__header blog-archive__header">
				<h1 class="section__title"><?php echo esc_html( $category_name ); ?></h1>
				<?php if ( $category_excerpt ) : ?>
					<div class="section__description">
						<?php echo wp_kses_post( $category_excerpt ); ?>
					</div>
				<?php endif; ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="blog-archive-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						$excerpt = trim( get_the_excerpt() );

						if ( '' === $excerpt ) {
							$excerpt = wp_strip_all_tags( get_the_content() );
						}
						?>
						<article <?php post_class( 'blog-card' ); ?>>
							<a class="blog-card__media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'large' ); ?>
								<?php else : ?>
									<span class="blog-card__media-placeholder" aria-hidden="true">
										<?php esc_html_e( 'Blogi', 'rytkoset-theme' ); ?>
									</span>
								<?php endif; ?>
							</a>

							<div class="blog-card__body">
								<p class="blog-card__meta">
									<time datetime="<?php echo esc_attr( get_post_time( 'c', true ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
									<span aria-hidden="true"> &middot; </span>
									<span><?php echo esc_html( get_the_author() ); ?></span>
								</p>

								<h2 class="blog-card__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>

								<?php if ( '' !== $excerpt ) : ?>
									<p class="blog-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 32 ) ); ?></p>
								<?php endif; ?>

								<a class="btn btn--light blog-card__link" href="<?php the_permalink(); ?>">
									<?php esc_html_e( 'Lue lisää', 'rytkoset-theme' ); ?>
								</a>
							</div>
						</article>
						<?php
					endwhile;
					?>
				</div>

				<?php
				$pagination = paginate_links(
					array(
						'total'     => (int) $wp_query->max_num_pages,
						'current'   => max( 1, (int) get_query_var( 'paged' ) ),
						'prev_text' => __( 'Edelliset', 'rytkoset-theme' ),
						'next_text' => __( 'Seuraavat', 'rytkoset-theme' ),
						'type'      => 'list',
					)
				);

				if ( $pagination ) :
					?>
					<nav class="pagination blog-pagination" aria-label="<?php esc_attr_e( 'Kategorian sivutus', 'rytkoset-theme' ); ?>">
						<?php echo wp_kses_post( $pagination ); ?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Tässä kategoriassa ei ole vielä julkaistuja kirjoituksia.', 'rytkoset-theme' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
