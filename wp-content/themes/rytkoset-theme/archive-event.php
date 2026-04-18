<?php
/**
 * Tapahtuma-arkisto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="section">
	<div class="container section__narrow">
		<header class="section__header">
			<h1 class="section__title"><?php post_type_archive_title(); ?></h1>
			<?php if ( get_the_archive_description() ) : ?>
				<div class="section__description"><?php echo wp_kses_post( wpautop( get_the_archive_description() ) ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="article-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'article' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
								<?php the_post_thumbnail( 'large' ); ?>
							</a>
						<?php endif; ?>

						<header class="article__header">
							<h2 class="article__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
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
			the_posts_pagination(
				array(
					'prev_text' => __( 'Edelliset', 'rytkoset-theme' ),
					'next_text' => __( 'Seuraavat', 'rytkoset-theme' ),
				)
			);
			?>
		<?php else : ?>
			<p><?php esc_html_e( 'Tapahtumia ei ole vielä julkaistu.', 'rytkoset-theme' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
