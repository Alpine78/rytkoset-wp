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
                                ?>
                                <article <?php post_class( 'article' ); ?>>
                                        <h1 class="article__title"><?php the_title(); ?></h1>
                                        <div class="article__content">
                                                <?php the_content(); ?>
                                        </div>

                                        <?php
                                        if ( rytkoset_theme_should_show_gallery_share( get_the_ID() ) ) {
                                                rytkoset_theme_share_buttons(
                                                        array(
                                                                'heading' => __( 'Jaa galleria', 'rytkoset-theme' ),
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
