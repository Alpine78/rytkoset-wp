<?php
/**
 * Yksittäinen tapahtuma.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

get_header();
?>

<section class="section">
	<div class="container section__narrow">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'article' ); ?>>
					<header class="article__header">
						<h1 class="article__title"><?php the_title(); ?></h1>
						<?php
						$event_date_raw     = rytkoset_theme_get_event_date_raw( get_the_ID() );
						$event_date_display = rytkoset_theme_get_event_date_display( get_the_ID() );
						?>
						<?php if ( '' !== $event_date_display ) : ?>
							<p class="article__meta">
								<time datetime="<?php echo esc_attr( $event_date_raw ); ?>"><?php echo esc_html( $event_date_display ); ?></time>
							</p>
						<?php endif; ?>
					</header>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="article__image">
							<?php the_post_thumbnail( 'large' ); ?>
						</div>
					<?php endif; ?>

					<div class="article__content">
						<?php the_content(); ?>
					</div>

					<?php rytkoset_theme_render_event_product_cta( get_the_ID() ); ?>

					<?php
					rytkoset_theme_share_buttons(
						array(
							'heading' => __( 'Jaa tapahtuma', 'rytkoset-theme' ),
							'post_id' => $post->ID,
						)
					);
					?>
				</article>
				<?php
			endwhile;
		endif;
		?>
	</div>
</section>

<?php
get_footer();
