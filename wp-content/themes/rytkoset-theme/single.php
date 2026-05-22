<?php
/**
 * Geneerinen fallback-sivupohja yksittäisille sisällöille.
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
					?>
					<article <?php post_class( 'article' ); ?>>
						<header class="article__header">
							<p class="article__meta"><?php echo esc_html( get_the_date() ); ?></p>
							<h1 class="article__title"><?php the_title(); ?></h1>
						</header>

						<div class="article__content">
							<?php the_content(); ?>
						</div>

						<?php
						$share_heading = 'product' === get_post_type( $post )
							? __( 'Jaa tuote', 'rytkoset-theme' )
							: __( 'Jaa artikkeli', 'rytkoset-theme' );
						rytkoset_theme_share_buttons(
							array(
								'heading' => $share_heading,
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
