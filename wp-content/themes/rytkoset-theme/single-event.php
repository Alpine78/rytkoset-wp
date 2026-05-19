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

<main id="primary" class="site-main">
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		$event_id         = get_the_ID();
		$has_summary_card = function_exists( 'rytkoset_theme_event_has_summary_card' ) && rytkoset_theme_event_has_summary_card( $event_id );
		$event_date       = function_exists( 'rytkoset_theme_get_event_date_display' ) ? rytkoset_theme_get_event_date_display( $event_id ) : '';
		?>
		<article <?php post_class( 'event' ); ?>>
			<header class="event-hero<?php echo has_post_thumbnail() ? '' : ' event-hero--no-image'; ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="event-hero__media">
						<?php
						the_post_thumbnail(
							'full',
							array(
								'class'         => 'event-hero__image',
								'loading'       => 'eager',
								'fetchpriority' => 'high',
								'sizes'         => '100vw',
							)
						);
						?>
					</div>
				<?php endif; ?>

				<div class="event-hero__content">
					<div class="container section__wide">
						<div class="event-hero__copy">
							<?php if ( '' !== $event_date ) : ?>
								<p class="event-hero__meta"><?php echo esc_html( $event_date ); ?></p>
							<?php endif; ?>
							<h1 class="event-hero__title"><?php the_title(); ?></h1>
						</div>
					</div>
				</div>
			</header>

			<section class="section event__section">
				<div class="container section__wide">
					<div class="<?php echo esc_attr( $has_summary_card ? 'event-layout event-layout--has-sidebar' : 'event-layout' ); ?>">
						<div class="event-layout__main">
							<div class="article__content">
								<?php the_content(); ?>
							</div>

							<?php
							if ( function_exists( 'rytkoset_theme_render_free_event_registration_form' ) ) {
								rytkoset_theme_render_free_event_registration_form( $event_id );
							}
							?>

							<?php
							rytkoset_theme_share_buttons(
								array(
									'heading' => __( 'Jaa tapahtuma', 'rytkoset-theme' ),
									'post_id' => $post->ID,
								)
							);
							?>
						</div>

						<?php if ( $has_summary_card ) : ?>
							<div class="event-layout__sidebar">
								<?php rytkoset_theme_render_event_summary_card( $event_id ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>
		</article>
		<?php
	endwhile;
endif;
?>
</main>

<?php
get_footer();
