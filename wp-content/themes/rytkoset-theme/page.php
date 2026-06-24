<?php
/**
 * Yleinen sivupohja (Pages)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main" tabindex="-1">
<section class="section">
	<div class="container section__narrow">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				$rytkoset_page_can_read = rytkoset_theme_user_can_read_members_only_page( get_the_ID() );
				?>
				<article <?php post_class( 'article' ); ?>>
					<h1 class="article__title"><?php the_title(); ?></h1>
					<div class="article__content">
						<?php
						if ( $rytkoset_page_can_read ) {
							the_content();
						} else {
							get_template_part( 'template-parts/members-only-locked', null, array( 'post_id' => get_the_ID() ) );
						}
						?>
					</div>

					<?php
					if ( $rytkoset_page_can_read && rytkoset_theme_should_show_page_share( get_the_ID() ) ) {
						rytkoset_theme_share_buttons(
							array(
								'heading' => __( 'Jaa sivu', 'rytkoset-theme' ),
								'post_id' => get_the_ID(),
							)
						);
					}
					?>
				</article>
				<?php
			endwhile;
		endif;
		?>
	</div>
</section>
</main>

<?php
get_footer();
