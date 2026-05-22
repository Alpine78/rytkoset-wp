<?php
/**
 * Some-jakonapit artikkeleille ja galleriasivuille.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	$title         = get_the_title( $post_id );
	$encoded_title = rawurlencode( $title );
	$encoded_text  = rawurlencode( trim( $title . ' ' . $permalink ) );
	$email_body    = rawurlencode( trim( $title . "\n" . $permalink ) );
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
			'service' => 'email',
			'label'   => __( 'Sähköposti', 'rytkoset-theme' ),
			'url'     => sprintf( 'mailto:%s?subject=%s&body=%s', rawurlencode( rytkoset_theme_get_contact_email() ), $encoded_title, $email_body ),
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

/**
 * Palauttaa kanavakohtaisen inline-SVG-ikonin (monovärinen, currentColor).
 *
 * Ikonit ovat inlinenä, jotta hover-tila voi värjätä ne kanavan
 * brändivärillä (`currentColor`).
 *
 * @param string $channel Kanava-avain: fb, x, li, wa, ms, mail, copy, native, arrow.
 * @return string SVG-merkkaus.
 */
function rytkoset_theme_get_share_icon( $channel ) {
	$icons = array(
		'fb'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.55l.38-2.95H13.5V8.7c0-.85.24-1.43 1.46-1.43h1.56V4.63c-.27-.04-1.2-.12-2.27-.12-2.25 0-3.79 1.37-3.79 3.9v2.14H7.9v2.95h2.56V21h3.04Z"/></svg>',
		'x'      => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.53 3H20.5l-6.5 7.43L21.5 21h-6l-4.7-6.13L5.4 21H2.43l6.95-7.95L2 3h6.16l4.25 5.61L17.53 3Z"/></svg>',
		'li'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.94 8.5H4.06V20h2.88V8.5Zm.18-3.7c-.02-.85-.62-1.5-1.6-1.5s-1.6.65-1.6 1.5c0 .83.59 1.5 1.56 1.5h.02c1 0 1.62-.67 1.62-1.5Zm9.6 9.45c0-2.74-1.46-4.02-3.42-4.02-1.58 0-2.29.87-2.69 1.48V8.5H7.74c.04.83 0 11.5 0 11.5h2.87v-6.43c0-.26.02-.52.1-.7.21-.51.69-1.05 1.5-1.05 1.06 0 1.49.81 1.49 2v6.18h2.87v-6.5Z"/></svg>',
		'wa'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.5 3.45A10.4 10.4 0 0 0 12.04 0C6.27 0 1.6 4.67 1.6 10.44c0 1.84.48 3.63 1.4 5.21L1.5 21l5.5-1.44a10.4 10.4 0 0 0 5.04 1.29h.01c5.77 0 10.45-4.68 10.45-10.45 0-2.79-1.08-5.41-3.05-7.4Z"/></svg>',
		'ms'     => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.36 2 2 6.14 2 11.6c0 2.85 1.21 5.34 3.18 7.04v3.46l2.9-1.6c.78.22 1.6.34 2.43.38h.49c5.64 0 10-4.14 10-9.28C21 6.14 17.64 2 12 2Zm1.06 12.5-2.55-2.72L5.5 14.5l5.4-5.74 2.6 2.72 4.98-2.72-5.42 5.74Z"/></svg>',
		'mail'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>',
		'copy'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
		'native' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>',
		'arrow'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>',
	);

	return isset( $icons[ $channel ] ) ? $icons[ $channel ] : '';
}

/**
 * Tulostaa jakonapit.
 *
 * Sama DOM kahdessa leveydessä: työpöydällä (≥ 760 px) yhdellä vaakarivillä,
 * mobiilissa (< 760 px) pinottuna hero-korttina + kanavarivinä. CSS:n
 * media-query hoitaa eron — ks. components.css `.share`.
 *
 * @param array $args {
 *     @type string $heading Komponentin otsikko.
 *     @type int    $post_id Jaettavan sisällön ID.
 *     @type string $theme   'auto' | 'light' | 'dark'. Oletus 'auto', joka
 *                           periytyy sivun data-theme-attribuutista.
 * }
 */
function rytkoset_theme_share_buttons( $args = array() ) {
	$defaults = array(
		'heading' => __( 'Jaa tämä sisältö', 'rytkoset-theme' ),
		'post_id' => get_the_ID(),
		'theme'   => 'auto',
	);

	$args        = wp_parse_args( $args, $defaults );
	$share_links = rytkoset_theme_get_share_links( $args['post_id'] );

	if ( empty( $share_links ) ) {
		return;
	}

	// Indeksoi linkit palvelun mukaan ja kytke kanava-avaimiin (data-ch).
	$service_map = array(
		'facebook'  => array( 'ch' => 'fb',   'label' => __( 'Jaa Facebookissa', 'rytkoset-theme' ) ),
		'x'         => array( 'ch' => 'x',    'label' => __( 'Jaa X:ssä', 'rytkoset-theme' ) ),
		'linkedin'  => array( 'ch' => 'li',   'label' => __( 'Jaa LinkedInissä', 'rytkoset-theme' ) ),
		'whatsapp'  => array( 'ch' => 'wa',   'label' => __( 'Jaa WhatsAppissa', 'rytkoset-theme' ) ),
		'messenger' => array( 'ch' => 'ms',   'label' => __( 'Jaa Messengerissä', 'rytkoset-theme' ) ),
		'email'     => array( 'ch' => 'mail', 'label' => __( 'Lähetä sähköpostilla', 'rytkoset-theme' ) ),
	);

	$links = array();
	foreach ( $share_links as $link ) {
		$links[ $link['service'] ] = $link;
	}

	$permalink = $args['post_id'] ? get_permalink( $args['post_id'] ) : home_url( '/' );
	$title     = $args['post_id'] ? wp_strip_all_tags( get_the_title( $args['post_id'] ) ) : get_bloginfo( 'name' );
	$url_label = preg_replace( '#^https?://#', '', untrailingslashit( $permalink ) );
	$theme_cl  = 'share--theme-' . sanitize_html_class( $args['theme'] );
	$hid       = 'share-heading-' . ( $args['post_id'] ? (int) $args['post_id'] : 'x' );
	?>
	<section
		class="share <?php echo esc_attr( $theme_cl ); ?>"
		data-share-url="<?php echo esc_url( $permalink ); ?>"
		data-share-title="<?php echo esc_attr( $title ); ?>"
		aria-labelledby="<?php echo esc_attr( $hid ); ?>"
	>
		<h2 class="share__heading" id="<?php echo esc_attr( $hid ); ?>">
			<?php echo esc_html( $args['heading'] ); ?>
		</h2>

		<?php // Mobiili: hero-kortti (kutsuu laitteen natiivijakovalikon). ?>
		<button type="button" class="share__hero" data-share-native>
			<span class="share__hero-ic" aria-hidden="true">
				<?php echo rytkoset_theme_get_share_icon( 'native' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="share__hero-t">
				<strong><?php esc_html_e( 'Jaa laitteella', 'rytkoset-theme' ); ?></strong>
				<span><?php esc_html_e( 'Käytä laitteen omaa jakovalikkoa', 'rytkoset-theme' ); ?></span>
			</span>
			<span class="share__hero-arrow" aria-hidden="true">
				<?php echo rytkoset_theme_get_share_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		</button>

		<div class="share__divider" aria-hidden="true">
			<span><?php esc_html_e( 'tai valitse kanava', 'rytkoset-theme' ); ?></span>
		</div>

		<?php // Työpöytä: rivilayout. Sisältää myös molemmille yhteisen kanavalistan. ?>
		<div class="share__row">
			<button type="button" class="share__cta" data-share-native>
				<?php echo rytkoset_theme_get_share_icon( 'native' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Jaa laitteella', 'rytkoset-theme' ); ?>
			</button>
			<span class="share__sep" aria-hidden="true"></span>

			<ul class="share__quick" role="list">
				<?php
				foreach ( $service_map as $service => $meta ) :
					if ( empty( $links[ $service ]['url'] ) ) {
						continue;
					}
					$is_mail = ( 'email' === $service );
					?>
					<li>
						<a
							class="share__ico"
							data-ch="<?php echo esc_attr( $meta['ch'] ); ?>"
							href="<?php echo esc_url( $links[ $service ]['url'] ); ?>"
							<?php if ( ! $is_mail ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
							aria-label="<?php echo esc_attr( $meta['label'] ); ?>"
						>
							<?php echo rytkoset_theme_get_share_icon( $meta['ch'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li>
					<button
						type="button"
						class="share__ico"
						data-ch="copy"
						data-share-copy
						aria-label="<?php esc_attr_e( 'Kopioi linkki', 'rytkoset-theme' ); ?>"
					>
						<?php echo rytkoset_theme_get_share_icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</li>
			</ul>

			<button
				type="button"
				class="share__url-link"
				data-share-copy
				aria-label="<?php esc_attr_e( 'Kopioi linkki', 'rytkoset-theme' ); ?>"
			>
				<?php echo rytkoset_theme_get_share_icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo esc_html( $url_label ); ?>
			</button>
		</div>

		<p class="share__status" role="status" aria-live="polite" hidden></p>
	</section>
	<?php
}
