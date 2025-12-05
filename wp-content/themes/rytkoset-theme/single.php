<?php
/**
 * Sivupohja yksittäisille artikkeleille.
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
                                                <p class="article__meta"><?php echo esc_html( get_the_date() ); ?></p>
                                                <h1 class="article__title"><?php the_title(); ?></h1>
                                        </header>

                                        <div class="article__content">
                                                <?php the_content(); ?>
                                        </div>

                                        <?php
                                        rytkoset_theme_share_buttons(
                                                array(
                                                        'heading' => __( 'Jaa artikkeli', 'rytkoset-theme' ),
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
