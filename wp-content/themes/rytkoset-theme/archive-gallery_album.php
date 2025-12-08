<?php
/**
 * Arkisto gallerialbumit.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

get_header();
?>

<section class="section">
        <div class="container section__wide">
                <header class="section__header">
                        <h1 class="section__title"><?php post_type_archive_title(); ?></h1>
                        <?php if ( get_the_archive_description() ) : ?>
                                <div class="section__description"><?php echo wp_kses_post( wpautop( get_the_archive_description() ) ); ?></div>
                        <?php endif; ?>
                </header>

                <?php if ( have_posts() ) : ?>
                        <div class="album-archive">
                                <?php
                                while ( have_posts() ) :
                                        the_post();
                                        ?>
                                        <article <?php post_class( 'album-card' ); ?>>
                                                <a class="album-card__link" href="<?php the_permalink(); ?>">
                                                        <div class="album-card__thumb">
                                                                <?php
                                                                if ( has_post_thumbnail() ) {
                                                                        the_post_thumbnail( 'large' );
                                                                }
                                                                ?>
                                                        </div>

                                                        <div class="album-card__body">
                                                                <p class="album-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
                                                                <h2 class="album-card__title"><?php the_title(); ?></h2>
                                                                <p class="album-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
                                                        </div>
                                                </a>
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
                        <p><?php esc_html_e( 'Albumit eivät ole vielä valmiina.', 'rytkoset-theme' ); ?></p>
                <?php endif; ?>
        </div>
</section>

<?php
get_footer();
