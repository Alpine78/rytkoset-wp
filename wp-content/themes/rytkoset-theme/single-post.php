<?php
/**
 * Sivupohja yksittäisille blogiartikkeleille.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		$rytkoset_blog_page = get_page_by_path( 'blogi' );
		$rytkoset_blog_url  = $rytkoset_blog_page instanceof WP_Post ? get_permalink( $rytkoset_blog_page ) : home_url( '/blogi/' );
		?>
<main id="primary" class="site-main" tabindex="-1">

	<header class="blog-post-hero<?php echo has_post_thumbnail() ? '' : ' blog-post-hero--no-image'; ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="blog-post-hero__media">
				<?php
				the_post_thumbnail(
					'full',
					array(
						'class'         => 'blog-post-hero__image',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'sizes'         => '100vw',
					)
				);
				?>
			</div>
		<?php endif; ?>

		<div class="blog-post-hero__content">
			<div class="container section__narrow">
				<nav class="breadcrumb blog-post-hero__breadcrumb" aria-label="<?php esc_attr_e( 'Murupolku', 'rytkoset-theme' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Etusivu', 'rytkoset-theme' ); ?></a>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( $rytkoset_blog_url ); ?>"><?php esc_html_e( 'Blogi', 'rytkoset-theme' ); ?></a>
					<span aria-hidden="true">/</span>
					<span><?php the_title(); ?></span>
				</nav>
			</div>

			<div class="container section__narrow">
				<div class="blog-post-hero__copy">
					<p class="blog-post-hero__meta">
						<time datetime="<?php echo esc_attr( get_post_time( 'c', true ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
						<span aria-hidden="true"> &middot; </span>
						<span><?php echo esc_html( get_the_author() ); ?></span>
					</p>
					<h1 class="blog-post-hero__title"><?php the_title(); ?></h1>
				</div>
			</div>
		</div>
	</header>

	<section class="section">
		<div class="container section__narrow">
			<article <?php post_class( 'article blog-post' ); ?>>
				<div class="article__content">
					<?php the_content(); ?>
				</div>

				<?php
				$rytkoset_categories = get_the_category_list( ', ' );
				$rytkoset_tags       = get_the_tag_list( '', ', ' );

				if ( $rytkoset_categories || $rytkoset_tags ) :
					?>
					<footer class="blog-post__terms">
						<?php if ( $rytkoset_categories ) : ?>
							<p>
								<strong><?php esc_html_e( 'Kategoriat:', 'rytkoset-theme' ); ?></strong>
								<?php echo wp_kses_post( $rytkoset_categories ); ?>
							</p>
						<?php endif; ?>

						<?php if ( $rytkoset_tags ) : ?>
							<p>
								<strong><?php esc_html_e( 'Avainsanat:', 'rytkoset-theme' ); ?></strong>
								<?php echo wp_kses_post( $rytkoset_tags ); ?>
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

			<?php comments_template(); ?>
		</div>
	</section>

</main>
		<?php
	endwhile;
endif;

get_footer();
