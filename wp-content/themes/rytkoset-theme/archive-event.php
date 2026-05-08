<?php
/**
 * Tapahtuma-arkisto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$today              = current_time( 'Y-m-d' );
$event_date_key     = rytkoset_theme_get_event_date_meta_key();
$upcoming_events    = new WP_Query(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_key'       => $event_date_key,
		'meta_type'      => 'DATE',
		'meta_query'     => array(
			array(
				'key'     => $event_date_key,
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	)
);
$past_events        = new WP_Query(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_key'       => $event_date_key,
		'meta_type'      => 'DATE',
		'meta_query'     => array(
			array(
				'key'     => $event_date_key,
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
		'orderby'        => 'meta_value',
		'order'          => 'DESC',
	)
);
$undated_events     = new WP_Query(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => $event_date_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $event_date_key,
				'value'   => '',
				'compare' => '=',
			),
		),
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);
$has_event_sections = $upcoming_events->have_posts() || $past_events->have_posts() || $undated_events->have_posts();

$render_event_list = static function ( WP_Query $event_query ) {
	?>
	<div class="article-list">
		<?php
		while ( $event_query->have_posts() ) :
			$event_query->the_post();
			$event_date_raw     = rytkoset_theme_get_event_date_raw( get_the_ID() );
			$event_date_display = rytkoset_theme_get_event_date_display( get_the_ID() );
			$event_location     = rytkoset_theme_get_event_location( get_the_ID() );
			?>
			<article <?php post_class( 'article' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
						<?php the_post_thumbnail( 'large' ); ?>
					</a>
				<?php endif; ?>

				<header class="article__header">
					<?php if ( '' !== $event_date_display || '' !== $event_location ) : ?>
						<p class="article__meta">
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
					<h3 class="article__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>
				</header>

				<div class="article__content">
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 32 ) ); ?></p>
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

<section class="section">
	<div class="container section__narrow">
		<header class="section__header">
			<h1 class="section__title"><?php post_type_archive_title(); ?></h1>
			<?php if ( get_the_archive_description() ) : ?>
				<div class="section__description"><?php echo wp_kses_post( wpautop( get_the_archive_description() ) ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( $has_event_sections ) : ?>
			<?php if ( $upcoming_events->have_posts() ) : ?>
				<section class="event-archive-section" aria-labelledby="upcoming-events-heading">
					<h2 id="upcoming-events-heading"><?php esc_html_e( 'Tulevat tapahtumat', 'rytkoset-theme' ); ?></h2>
					<?php $render_event_list( $upcoming_events ); ?>
				</section>
			<?php endif; ?>

			<?php if ( $past_events->have_posts() ) : ?>
				<section class="event-archive-section" aria-labelledby="past-events-heading">
					<h2 id="past-events-heading"><?php esc_html_e( 'Menneet tapahtumat', 'rytkoset-theme' ); ?></h2>
					<?php $render_event_list( $past_events ); ?>
				</section>
			<?php endif; ?>

			<?php if ( $undated_events->have_posts() ) : ?>
				<section class="event-archive-section" aria-labelledby="undated-events-heading">
					<h2 id="undated-events-heading"><?php esc_html_e( 'Päivämäärättömät tapahtumat', 'rytkoset-theme' ); ?></h2>
					<?php $render_event_list( $undated_events ); ?>
				</section>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Tapahtumia ei ole vielä julkaistu.', 'rytkoset-theme' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
