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

                $permalink      = get_permalink( $post_id );
                $encoded_url    = rawurlencode( $permalink );
                $title          = get_the_title( $post_id );
                $encoded_title  = rawurlencode( $title );
                $encoded_text   = rawurlencode( trim( $title . ' ' . $permalink ) );
		$fb_app_id     = apply_filters( 'rytkoset_theme_facebook_app_id', '' );
		$messenger_url = $fb_app_id
			? sprintf(
				'https://www.facebook.com/dialog/send?app_id=%s&link=%s&redirect_uri=%s',
				rawurlencode( $fb_app_id ),
				$encoded_url,
				$encoded_url
			)
			: sprintf( 'https://www.messenger.com/t/?link=%s', $encoded_url );

                return array(
                        array(
                                'service' => 'facebook',
                                'label'   => __( 'Facebook', 'rytkoset-theme' ),
                                'url'     => sprintf(
                                        'https://www.facebook.com/sharer/sharer.php?u=%s&quote=%s',
                                        $encoded_url,
                                        $encoded_title
                                ),
                                'type'    => 'link',
                        ),
                        array(
                                'service' => 'x',
                                'label'   => __( 'X', 'rytkoset-theme' ),
                                'url'     => sprintf( 'https://twitter.com/intent/tweet?url=%s&text=%s', $encoded_url, $encoded_title ),
                                'type'    => 'link',
                        ),
                        array(
                                'service' => 'linkedin',
                                'label'   => __( 'LinkedIn', 'rytkoset-theme' ),
                                'url'     => sprintf( 'https://www.linkedin.com/sharing/share-offsite/?url=%s', $encoded_url ),
                                'type'    => 'link',
                        ),
                        array(
                                'service' => 'whatsapp',
                                'label'   => __( 'WhatsApp', 'rytkoset-theme' ),
                                'url'     => sprintf( 'https://api.whatsapp.com/send?text=%s', $encoded_text ),
                                'type'    => 'link',
                        ),
                        array(
                                'service' => 'messenger',
                                'label'   => __( 'Messenger', 'rytkoset-theme' ),
                                'url'     => $messenger_url,
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

                $args              = wp_parse_args( $args, $defaults );
                $share_links       = rytkoset_theme_get_share_links( $args['post_id'] );
                $share_icons       = array(
                        'facebook'  => 'Facebook.svg',
                        'x'         => 'X.svg',
                        'linkedin'  => 'LinkedIn.svg',
                        'whatsapp'  => 'WhatsApp.svg',
                        'messenger' => 'Messenger.svg',
                );

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
                                                        <span aria-hidden="true" class="share__icon">&#128279;</span>
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
								<?php
								$icon_src = isset( $share_icons[ $link['service'] ] )
									? get_template_directory_uri() . '/assets/icons/social/' . $share_icons[ $link['service'] ]
									: '';

								if ( ! empty( $icon_src ) ) {
									printf(
										'<img class="share__icon-image" src="%s" alt="" aria-hidden="true" />',
										esc_url( $icon_src )
									);
								} else {
									switch ( $link['service'] ) {
										case 'facebook':
											echo 'F';
											break;
										case 'x':
											echo 'X';
											break;
										case 'linkedin':
											echo 'in';
											break;
										case 'whatsapp':
											echo 'WA';
											break;
										case 'messenger':
											echo 'M';
											break;
										default:
											echo '?';
											break;
									}
								}
								?>
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
