<?php
/**
 * Tapahtuma-arkisto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$rytkoset_today              = current_time( 'Y-m-d' );
$rytkoset_event_date_key     = rytkoset_theme_get_event_date_meta_key();
$rytkoset_upcoming_events    = new WP_Query(
	array(
		'post_type'      => 'rytkoset_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_key'       => $rytkoset_event_date_key,
		'meta_type'      => 'DATE',
		'meta_query'     => array(
			array(
				'key'     => $rytkoset_event_date_key,
				'value'   => $rytkoset_today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	)
);
$rytkoset_past_events        = new WP_Query(
	array(
		'post_type'      => 'rytkoset_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_key'       => $rytkoset_event_date_key,
		'meta_type'      => 'DATE',
		'meta_query'     => array(
			array(
				'key'     => $rytkoset_event_date_key,
				'value'   => $rytkoset_today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
		'orderby'        => 'meta_value',
		'order'          => 'DESC',
	)
);
$rytkoset_undated_events     = new WP_Query(
	array(
		'post_type'      => 'rytkoset_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => $rytkoset_event_date_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $rytkoset_event_date_key,
				'value'   => '',
				'compare' => '=',
			),
		),
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);
$rytkoset_has_event_sections = $rytkoset_upcoming_events->have_posts() || $rytkoset_past_events->have_posts() || $rytkoset_undated_events->have_posts();

$rytkoset_render_event_list = static function ( WP_Query $event_query ) {
	?>
	<div class="event-archive-grid">
		<?php
		while ( $event_query->have_posts() ) :
			$event_query->the_post();
			$event_date_raw     = rytkoset_theme_get_event_date_raw( get_the_ID() );
			$event_date_display = rytkoset_theme_get_event_date_display( get_the_ID() );
			$event_location     = rytkoset_theme_get_event_location( get_the_ID() );
			$event_excerpt      = trim( get_the_excerpt() );
			$has_product_link   = '' !== rytkoset_theme_get_event_product_url( get_the_ID() );

			if ( '' === $event_excerpt ) {
				$event_excerpt = wp_strip_all_tags( get_the_content() );
			}
			?>
			<article <?php post_class( 'event-card' ); ?>>
				<a class="event-card__media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large' ); ?>
					<?php else : ?>
						<span class="event-card__media-placeholder" aria-hidden="true">
							<?php esc_html_e( 'Tapahtuma', 'rytkoset-theme' ); ?>
						</span>
					<?php endif; ?>
				</a>

				<div class="event-card__body">
					<?php if ( '' !== $event_date_display || '' !== $event_location ) : ?>
						<p class="event-card__meta">
							<?php if ( '' !== $event_date_display ) : ?>
								<time datetime="<?php echo esc_attr( $event_date_raw ); ?>"><?php echo esc_html( $event_date_display ); ?></time>
							<?php endif; ?>
							<?php if ( '' !== $event_date_display && '' !== $event_location ) : ?>
								<span aria-hidden="true"> &middot; </span>
							<?php endif; ?>
							<?php if ( '' !== $event_location ) : ?>
								<span class="article__meta-location"><?php echo esc_html( $event_location ); ?></span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
					<h3 class="event-card__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>

					<?php if ( '' !== $event_excerpt ) : ?>
						<p class="event-card__excerpt"><?php echo esc_html( wp_trim_words( $event_excerpt, 32 ) ); ?></p>
					<?php endif; ?>

					<div class="event-card__actions">
						<a class="btn btn--light event-card__link" href="<?php the_permalink(); ?>">
							<?php esc_html_e( 'Katso tapahtuma', 'rytkoset-theme' ); ?>
						</a>
						<?php if ( $has_product_link ) : ?>
							<span class="event-card__badge"><?php esc_html_e( 'Ilmoittautuminen avoinna', 'rytkoset-theme' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</div>
	<?php
	wp_reset_postdata();
};
?>

<main id="primary" class="site-main" tabindex="-1">
<section class="section">
	<div class="container">
		<header class="section__header">
			<h1 class="section__title"><?php post_type_archive_title(); ?></h1>
			<?php if ( get_the_archive_description() ) : ?>
				<div class="section__description"><?php echo wp_kses_post( wpautop( get_the_archive_description() ) ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( $rytkoset_has_event_sections ) : ?>
			<?php if ( $rytkoset_upcoming_events->have_posts() ) : ?>
				<section class="event-archive-section" aria-labelledby="upcoming-events-heading">
					<h2 id="upcoming-events-heading"><?php esc_html_e( 'Tulevat tapahtumat', 'rytkoset-theme' ); ?></h2>
					<?php $rytkoset_render_event_list( $rytkoset_upcoming_events ); ?>
				</section>
			<?php endif; ?>

			<?php if ( $rytkoset_past_events->have_posts() ) : ?>
				<section class="event-archive-section" aria-labelledby="past-events-heading">
					<h2 id="past-events-heading"><?php esc_html_e( 'Menneet tapahtumat', 'rytkoset-theme' ); ?></h2>
					<?php $rytkoset_render_event_list( $rytkoset_past_events ); ?>
				</section>
			<?php endif; ?>

			<?php if ( $rytkoset_undated_events->have_posts() ) : ?>
				<section class="event-archive-section" aria-labelledby="undated-events-heading">
					<h2 id="undated-events-heading"><?php esc_html_e( 'Päivämäärättömät tapahtumat', 'rytkoset-theme' ); ?></h2>
					<?php $rytkoset_render_event_list( $rytkoset_undated_events ); ?>
				</section>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Tapahtumia ei ole vielä julkaistu.', 'rytkoset-theme' ); ?></p>
		<?php endif; ?>
	</div>
</section>
</main>

<?php
get_footer();
