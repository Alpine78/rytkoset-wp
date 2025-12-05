<?php
/**
 * Some-jakonapit artikkeleille ja galleriasivuille.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'rytkoset_theme_get_share_links' ) ) {
        /**
         * Palauttaa sosiaalisen median jakolinkit annetulle sisällölle.
         *
         * @param int|null $post_id Viestin ID; jos tyhjä, käytetään nykyistä.
         * @return array[]
         */
        function rytkoset_theme_get_share_links( $post_id = null ) {
                $post_id = $post_id ? (int) $post_id : get_the_ID();

                if ( ! $post_id ) {
                        return array();
                }

                $permalink     = get_permalink( $post_id );
                $encoded_url   = rawurlencode( $permalink );
                $encoded_title = rawurlencode( get_the_title( $post_id ) );

                return array(
                        array(
                                'service' => 'facebook',
                                'label'   => __( 'Jaa Facebookissa', 'rytkoset-theme' ),
                                'url'     => sprintf( 'https://www.facebook.com/sharer/sharer.php?u=%s', $encoded_url ),
                                'type'    => 'link',
                        ),
                        array(
                                'service' => 'x',
                                'label'   => __( 'Jaa X:ssä', 'rytkoset-theme' ),
                                'url'     => sprintf( 'https://twitter.com/intent/tweet?url=%s&text=%s', $encoded_url, $encoded_title ),
                                'type'    => 'link',
                        ),
                        array(
                                'service' => 'copy',
                                'label'   => __( 'Kopioi linkki', 'rytkoset-theme' ),
                                'url'     => $permalink,
                                'type'    => 'copy',
                        ),
                );
        }
}

if ( ! function_exists( 'rytkoset_theme_should_show_gallery_share' ) ) {
        /**
         * Selvittää, näytetäänkö jakonapit galleriasivulla.
         *
         * @param WP_Post|int|null $post Viesti tai ID; oletuksena nykyinen.
         * @return bool
         */
        function rytkoset_theme_should_show_gallery_share( $post = null ) {
                $post = get_post( $post );

                if ( ! $post || 'page' !== $post->post_type ) {
                        return false;
                }

                $gallery_slugs = array( 'valokuvat', 'galleria', 'galleriat' );

                if ( in_array( $post->post_name, $gallery_slugs, true ) ) {
                        return true;
                }

                $content = $post->post_content;

                if ( function_exists( 'has_block' ) && has_block( 'gallery', $post ) ) {
                        return true;
                }

                if ( has_shortcode( $content, 'gallery' ) ) {
                        return true;
                }

                return false;
        }
}

if ( ! function_exists( 'rytkoset_theme_share_buttons' ) ) {
        /**
         * Tulostaa jakonapit.
         *
         * @param array $args Asetukset: heading ja post_id.
         */
        function rytkoset_theme_share_buttons( $args = array() ) {
                $defaults = array(
                        'heading' => __( 'Jaa tämä sisältö', 'rytkoset-theme' ),
                        'post_id' => get_the_ID(),
                );

                $args        = wp_parse_args( $args, $defaults );
                $share_links = rytkoset_theme_get_share_links( $args['post_id'] );

                if ( empty( $share_links ) ) {
                        return;
                }
                ?>
                <div class="share" data-share>
                        <h2 class="share__title"><?php echo esc_html( $args['heading'] ); ?></h2>
                        <div class="share__actions">
                                <?php foreach ( $share_links as $link ) : ?>
                                        <?php if ( 'copy' === $link['type'] ) : ?>
                                                <button
                                                        type="button"
                                                        class="share__button share__button--<?php echo esc_attr( $link['service'] ); ?>"
                                                        data-share-copy="<?php echo esc_url( $link['url'] ); ?>"
                                                        data-share-success="<?php esc_attr_e( 'Linkki kopioitu leikepöydälle', 'rytkoset-theme' ); ?>"
                                                        data-share-error="<?php esc_attr_e( 'Linkin kopiointi ei onnistunut', 'rytkoset-theme' ); ?>"
                                                >
                                                        <span aria-hidden="true" class="share__icon">🔗</span>
                                                        <span><?php echo esc_html( $link['label'] ); ?></span>
                                                </button>
                                        <?php else : ?>
                                                <a
                                                        class="share__button share__button--<?php echo esc_attr( $link['service'] ); ?>"
                                                        href="<?php echo esc_url( $link['url'] ); ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                >
                                                        <span aria-hidden="true" class="share__icon">
                                                                <?php echo 'facebook' === $link['service'] ? '👍' : '𝕏'; ?>
                                                        </span>
                                                        <span><?php echo esc_html( $link['label'] ); ?></span>
                                                </a>
                                        <?php endif; ?>
                                <?php endforeach; ?>
                        </div>
                        <p class="share__status" data-share-status hidden></p>
                </div>
                <?php
        }
}
