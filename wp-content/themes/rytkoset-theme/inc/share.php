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
	// Messengerin web-jako (Send Dialog) vaatii Facebook App ID:n. Ilman
	// sitä ei ole toimivaa web-jakolinkkiä — silloin Messenger-ikoni
	// jätetään pois ja mobiilin natiivijako hoitaa Messengerin.
	$fb_app_id     = apply_filters( 'rytkoset_theme_facebook_app_id', '' );
	$messenger_url = $fb_app_id
		? sprintf(
			'https://www.facebook.com/dialog/send?app_id=%s&link=%s&redirect_uri=%s',
			rawurlencode( $fb_app_id ),
			$encoded_url,
			$encoded_url
		)
		: '';

	return array(
		array(
			'service' => 'facebook',
			'label'   => __( 'Facebook', 'rytkoset-theme' ),
			// Facebook lukee esikatselun (otsikko/kuva/kuvaus) jaettavan
			// sivun Open Graph -tageista; sharer.php ottaa vain u-parametrin.
			'url'     => sprintf(
				'https://www.facebook.com/sharer/sharer.php?u=%s',
				$encoded_url
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
	// Kanava-avain → ikonitiedosto (assets/icons/{dir}/{name}.svg).
	$map = array(
		'fb'     => array( 'social', 'Facebook' ),
		'x'      => array( 'social', 'X' ),
		'li'     => array( 'social', 'LinkedIn' ),
		'wa'     => array( 'social', 'WhatsApp' ),
		'ms'     => array( 'social', 'Messenger' ),
		'mail'   => array( 'social', 'Email' ),
		'copy'   => array( 'ui', 'copy' ),
		'native' => array( 'ui', 'native-share' ),
		'arrow'  => array( 'ui', 'chevron-right' ),
	);

	if ( ! isset( $map[ $channel ] ) ) {
		return '';
	}

	list( $dir, $name ) = $map[ $channel ];

	return rytkoset_theme_inline_icon( $name, $dir );
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
