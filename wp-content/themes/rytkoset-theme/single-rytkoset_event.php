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

<main id="primary" class="site-main" tabindex="-1">
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		$rytkoset_event_id         = get_the_ID();
		$rytkoset_has_summary_card = rytkoset_theme_event_has_summary_card( $rytkoset_event_id );
		$rytkoset_event_date       = rytkoset_theme_get_event_date_display( $rytkoset_event_id );
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
							<?php if ( '' !== $rytkoset_event_date ) : ?>
								<p class="event-hero__meta"><?php echo esc_html( $rytkoset_event_date ); ?></p>
							<?php endif; ?>
							<h1 class="event-hero__title"><?php the_title(); ?></h1>
						</div>
					</div>
				</div>
			</header>

			<section class="section event__section">
				<div class="container section__wide">
					<div class="<?php echo esc_attr( $rytkoset_has_summary_card ? 'event-layout event-layout--has-sidebar' : 'event-layout' ); ?>">
						<div class="event-layout__main">
							<div class="article__content">
								<?php the_content(); ?>
							</div>

							<?php rytkoset_theme_render_free_event_registration_form( $rytkoset_event_id ); ?>

							<?php
							rytkoset_theme_share_buttons(
								array(
									'heading' => __( 'Jaa tapahtuma', 'rytkoset-theme' ),
									'post_id' => $post->ID,
								)
							);
							?>
						</div>

						<?php if ( $rytkoset_has_summary_card ) : ?>
							<div class="event-layout__sidebar">
								<?php rytkoset_theme_render_event_summary_card( $rytkoset_event_id ); ?>
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
