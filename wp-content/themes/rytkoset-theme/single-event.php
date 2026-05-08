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
	<div class="container">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				$event_id         = get_the_ID();
				$has_summary_card = function_exists( 'rytkoset_theme_event_has_summary_card' ) && rytkoset_theme_event_has_summary_card( $event_id );
				?>
				<article <?php post_class( 'article article--event' ); ?>>
					<header class="article__header event-article__header">
						<h1 class="article__title"><?php the_title(); ?></h1>
					</header>

					<div class="<?php echo esc_attr( $has_summary_card ? 'event-layout event-layout--has-sidebar' : 'event-layout' ); ?>">
						<?php if ( $has_summary_card ) : ?>
							<div class="event-layout__sidebar">
								<?php rytkoset_theme_render_event_summary_card( $event_id ); ?>
							</div>
						<?php endif; ?>

						<div class="event-layout__main">
							<?php if ( has_post_thumbnail() ) : ?>
								<div class="article__image">
									<?php the_post_thumbnail( 'large' ); ?>
								</div>
							<?php endif; ?>

							<div class="article__content">
								<?php the_content(); ?>
							</div>

							<?php
							rytkoset_theme_share_buttons(
								array(
									'heading' => __( 'Jaa tapahtuma', 'rytkoset-theme' ),
									'post_id' => $post->ID,
								)
							);
							?>
						</div>
					</div>
				</article>
				<?php
			endwhile;
		endif;
		?>
	</div>
</section>

<?php
get_footer();
