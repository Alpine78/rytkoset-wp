<?php
/**
 * Sivupohja yksittäisille artikkeleille.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

get_header();
?>

<main id="primary" class="site-main">
	<section class="section">
		<div class="container section__narrow">
			<?php
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					$blog_page = get_page_by_path( 'blogi' );
					$blog_url  = $blog_page instanceof WP_Post ? get_permalink( $blog_page ) : home_url( '/blogi/' );
					?>
					<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Murupolku', 'rytkoset-theme' ); ?>">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Etusivu', 'rytkoset-theme' ); ?></a>
						<span aria-hidden="true">/</span>
						<a href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'Blogi', 'rytkoset-theme' ); ?></a>
						<span aria-hidden="true">/</span>
						<span><?php the_title(); ?></span>
					</nav>

					<article <?php post_class( 'article blog-post' ); ?>>
						<header class="article__header blog-post__header">
							<p class="article__meta blog-post__meta">
								<time datetime="<?php echo esc_attr( get_post_time( 'c', true ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<span aria-hidden="true"> &middot; </span>
								<span><?php echo esc_html( get_the_author() ); ?></span>
							</p>
							<h1 class="article__title"><?php the_title(); ?></h1>
						</header>

						<?php if ( has_post_thumbnail() ) : ?>
							<figure class="blog-post__featured-image">
								<?php
								the_post_thumbnail(
									'large',
									array(
										'loading' => 'eager',
										'sizes'   => '(min-width: 760px) 720px, calc(100vw - 2rem)',
									)
								);
								?>
							</figure>
						<?php endif; ?>

						<div class="article__content">
							<?php the_content(); ?>
						</div>

						<?php
						$categories = get_the_category_list( ', ' );
						$tags       = get_the_tag_list( '', ', ' );

						if ( $categories || $tags ) :
							?>
							<footer class="blog-post__terms">
								<?php if ( $categories ) : ?>
									<p>
										<strong><?php esc_html_e( 'Kategoriat:', 'rytkoset-theme' ); ?></strong>
										<?php echo wp_kses_post( $categories ); ?>
									</p>
								<?php endif; ?>

								<?php if ( $tags ) : ?>
									<p>
										<strong><?php esc_html_e( 'Avainsanat:', 'rytkoset-theme' ); ?></strong>
										<?php echo wp_kses_post( $tags ); ?>
									</p>
								<?php endif; ?>
							</footer>
						<?php endif; ?>

						<?php
						rytkoset_theme_share_buttons(
							array(
								'heading' => __( 'Jaa artikkeli', 'rytkoset-theme' ),
								'post_id' => $post->ID,
							)
						);
						?>
					</article>
					<?php
				endwhile;
			endif;
			?>

			<?php comments_template(); ?>
		</div>
	</section>
</main>

<?php
get_footer();
