<?php
/**
 * Rytköset Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/social-links.php';
require_once get_template_directory() . '/inc/share.php';
require_once get_template_directory() . '/inc/gallery-albums.php';

if ( ! function_exists( 'rytkoset_theme_get_attachment_display_caption_text' ) ) {
	/**
	 * Returns the short display caption for an attachment.
	 *
	 * Prefers the media caption field and falls back to the attachment title.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string
	 */
	function rytkoset_theme_get_attachment_display_caption_text( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return '';
		}

		$title   = trim( (string) get_the_title( $attachment_id ) );
		$caption = trim( (string) wp_get_attachment_caption( $attachment_id ) );

		return '' !== $caption ? $caption : $title;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_attachment_visible_caption_html' ) ) {
	/**
	 * Builds the short visible caption HTML for grid thumbnails and Gutenberg figcaptions.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string
	 */
	function rytkoset_theme_get_attachment_visible_caption_html( $attachment_id ) {
		$display_caption = rytkoset_theme_get_attachment_display_caption_text( $attachment_id );

		if ( '' === $display_caption ) {
			return '';
		}

		return '<p class="pswp-caption-content__caption">' . esc_html( $display_caption ) . '</p>';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_attachment_caption_html' ) ) {
	/**
	 * Builds PhotoSwipe caption HTML from attachment metadata.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string
	 */
	function rytkoset_theme_get_attachment_caption_html( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return '';
		}

		$display_caption = rytkoset_theme_get_attachment_display_caption_text( $attachment_id );
		$description     = trim( (string) get_post_field( 'post_content', $attachment_id ) );
		$parts           = array();

		if ( '' !== $display_caption ) {
			$parts[] = '<p class="pswp-caption-content__caption">' . esc_html( $display_caption ) . '</p>';
		}

		if ( '' !== $description ) {
			$parts[] = '<div class="pswp-caption-content__description">' . wp_kses_post( wpautop( $description ) ) . '</div>';
		}

		return implode( '', $parts );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_image_iptc_text_fields' ) ) {
	/**
	 * Reads raw IPTC headline and description fields from an image file.
	 *
	 * @param string $file Absolute image file path.
	 * @return array{headline:string,description:string}
	 */
	function rytkoset_theme_get_image_iptc_text_fields( $file ) {
		$fields = array(
			'headline'    => '',
			'description' => '',
		);

		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) || ! is_callable( 'iptcparse' ) ) {
			return $fields;
		}

		$info       = array();
		$image_size = wp_getimagesize( $file, $info );

		if ( false === $image_size || empty( $info['APP13'] ) ) {
			return $fields;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! defined( 'WP_RUN_CORE_TESTS' ) ) {
			$iptc = iptcparse( $info['APP13'] );
		} else {
			// Silencing notice and warning is intentional, same as WordPress core.
			$iptc = @iptcparse( $info['APP13'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( ! is_array( $iptc ) ) {
			return $fields;
		}

		if ( ! empty( $iptc['2#105'][0] ) ) {
			$fields['headline'] = trim( (string) $iptc['2#105'][0] );
		}

		if ( ! empty( $iptc['2#120'][0] ) ) {
			$fields['description'] = trim( (string) $iptc['2#120'][0] );
		}

		return $fields;
	}
}

if ( ! function_exists( 'rytkoset_theme_sync_attachment_iptc_text_fields' ) ) {
	/**
	 * Maps IPTC Headline and Description to attachment caption and description on upload.
	 *
	 * Headline -> post_excerpt (WordPress media caption)
	 * Description -> post_content (WordPress media description)
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	function rytkoset_theme_sync_attachment_iptc_text_fields( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! is_string( $file ) || '' === $file ) {
			return;
		}

		$iptc_fields = rytkoset_theme_get_image_iptc_text_fields( $file );
		$post_update = array(
			'ID' => $attachment_id,
		);

		if ( '' !== $iptc_fields['headline'] ) {
			$post_update['post_excerpt'] = $iptc_fields['headline'];
		}

		if ( '' !== $iptc_fields['description'] ) {
			$post_update['post_content'] = $iptc_fields['description'];
		}

		if ( 1 === count( $post_update ) ) {
			return;
		}

		wp_update_post( wp_slash( $post_update ) );
	}
}
add_action( 'add_attachment', 'rytkoset_theme_sync_attachment_iptc_text_fields' );

function rytkoset_theme_setup() {
	// Otsikkotagi WP:n hallintaan
	add_theme_support( 'title-tag' );

	// Esikatselukuvat
	add_theme_support( 'post-thumbnails' );

	// Sivuston logo
	add_theme_support(
		'custom-logo',
		array(
			'height'               => 160,
			'width'                => 160,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => false,
		)
	);

	// HTML5-markup
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' )
	);

	// Navigaatiomenut
        register_nav_menus(
                array(
                        'primary'   => __( 'Päävalikko', 'rytkoset-theme' ),
                        'footer'    => __( 'Footer-valikko', 'rytkoset-theme' ),
                        'account'   => __( 'Käyttäjä/tili-valikko', 'rytkoset-theme' ),
                )
        );
}
add_action( 'after_setup_theme', 'rytkoset_theme_setup' );

/**
 * Palauttaa logon HTML:n wrapper-luokkineen.
 *
 * @param array $args Asetukset: class (wrapper) ja link_class (fallback-linkille).
 * @return string Logo-html.
 */
function rytkoset_theme_get_logo_markup( $args = array() ) {
	$defaults = array(
		'class'      => 'site-logo',
		'link_class' => 'site-logo__link',
	);

	$args      = wp_parse_args( $args, $defaults );
	$home_url  = esc_url( home_url( '/' ) );
	$site_name = get_bloginfo( 'name' );

	ob_start();
	?>
	<div class="<?php echo esc_attr( trim( $args['class'] ) ); ?>">
		<?php
		$logo = get_custom_logo();

		if ( $logo ) {
			if ( ! empty( $args['link_class'] ) ) {
				$logo = str_replace(
					'custom-logo-link',
					'custom-logo-link ' . esc_attr( $args['link_class'] ),
					$logo
				);
			}

			echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			?>
			<a class="<?php echo esc_attr( trim( $args['link_class'] ) ); ?>" href="<?php echo $home_url; ?>">
				<?php echo esc_html( $site_name ); ?>
			</a>
			<?php
		}
		?>
	</div>
	<?php

	return trim( ob_get_clean() );
}

/**
 * Tulostaa logon.
 *
 * @param array $args Asetukset: class (wrapper) ja link_class (fallback-linkille).
 */
function rytkoset_theme_the_logo( $args = array() ) {
	echo rytkoset_theme_get_logo_markup( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Palauttaa uloskirjautumis-URL:n etusivulle ohjauksella.
 *
 * @return string Uloskirjautumis-URL.
 */
function rytkoset_theme_get_logout_url() {
        return wp_logout_url( home_url( '/' ) );
}

/**
 * Fallback-kutsu kirjautuneiden tilivalikolle.
 */
function rytkoset_theme_account_menu_logged_in_fallback() {
        $current_user = wp_get_current_user();

        if ( ! $current_user instanceof WP_User ) {
                return;
        }

        $display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        $profile_url  = admin_url( 'profile.php' );
        $avatar       = wp_kses_post( get_avatar( $current_user->ID, 40 ) );

        echo '<ul class="account-nav__list">';
        echo '<li class="menu-item menu-item-has-children account-menu__user">';
        echo '<button type="button" class="account-menu__user-trigger" aria-haspopup="true" aria-expanded="false">';
        echo '<span class="account-menu__avatar">' . $avatar . '</span>';
        echo '<span class="account-menu__meta">';
        echo '<span class="account-menu__greeting">' . esc_html__( 'Kirjautunut', 'rytkoset-theme' ) . '</span>';
        echo '<span class="account-menu__name">' . esc_html( $display_name ) . '</span>';
        echo '</span>';
        echo '</button>';
        echo '<ul class="sub-menu" aria-label="' . esc_attr__( 'Tilivalikko', 'rytkoset-theme' ) . '">';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( $profile_url ) . '">';
        echo esc_html__( 'Muokkaa profiilia', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( rytkoset_theme_get_logout_url() ) . '">';
        echo esc_html__( 'Kirjaudu ulos', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';
        echo '</ul>';
        echo '</li>';
        echo '</ul>';
}

/**
 * Fallback-kutsu vierailijoiden tilivalikolle.
 */
function rytkoset_theme_account_menu_logged_out_fallback() {
        echo '<ul class="account-nav__list">';
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url( wp_login_url() ) . '">';
        echo esc_html__( 'Kirjaudu', 'rytkoset-theme' );
        echo '</a>';
        echo '</li>';

        if ( get_option( 'users_can_register' ) && wp_registration_url() ) {
                echo '<li class="menu-item">';
                echo '<a href="' . esc_url( wp_registration_url() ) . '">';
                echo esc_html__( 'Rekisteröidy', 'rytkoset-theme' );
                echo '</a>';
                echo '</li>';
        }

        echo '</ul>';
}

/**
 * Lataa tyylit ja skriptit.
 */
function rytkoset_theme_scripts() {
	$theme_version = wp_get_theme()->get( 'Version' );

    // Teeman päätyyli (style.css) – WordPress hoitaa tämän usein automaattisesti, mutta tehdään eksplisiittisesti.
    wp_enqueue_style(
        'rytkoset-theme-style',
        get_stylesheet_uri(),
        array(),
        $theme_version
    );

    // Mobiilivalikon JS
    wp_enqueue_script(
        'rytkoset-theme-main',
        get_template_directory_uri() . '/assets/js/main.js',
        array(),
        $theme_version,
        true // footer
    );

    // Load PhotoSwipe on album archive, single albums, and fallback query var (plain permalinks).
    if (
        is_post_type_archive( 'gallery_album' )
        || is_singular( 'gallery_album' )
        || get_query_var( 'gallery_album' )
        || 'gallery_album' === get_query_var( 'post_type' )
    ) {
        $photoswipe_version = '5.4.4';
        $photoswipe_base    = get_template_directory_uri() . '/assets/vendor/photoswipe';

        wp_enqueue_style(
            'rytkoset-theme-gallery',
            get_template_directory_uri() . '/assets/css/gallery.css',
            array( 'rytkoset-theme-style' ),
            $theme_version
        );

        wp_enqueue_style(
            'photoswipe',
            $photoswipe_base . '/photoswipe.css',
            array(),
            $photoswipe_version
        );

        wp_enqueue_script(
            'photoswipe',
            $photoswipe_base . '/photoswipe.umd.min.js',
            array(),
            $photoswipe_version,
            true
        );

        wp_enqueue_script(
            'photoswipe-lightbox',
            $photoswipe_base . '/photoswipe-lightbox.umd.min.js',
            array( 'photoswipe' ),
            $photoswipe_version,
            true
        );

        wp_enqueue_script(
            'rytkoset-photoswipe-init',
            get_template_directory_uri() . '/assets/js/photoswipe-init.js',
            array( 'photoswipe-lightbox' ),
            $theme_version,
            true
        );

        wp_add_inline_script(
            'rytkoset-photoswipe-init',
            'window.rytkosetPhotoSwipe = ' . wp_json_encode(
                array(
                    'dynamicCaptionCssUrl' => $photoswipe_base . '/photoswipe-dynamic-caption-plugin.css',
                    'dynamicCaptionJsUrl'  => $photoswipe_base . '/photoswipe-dynamic-caption-plugin.esm.js',
                    'copyLinkLabel'        => __( 'Kopioi linkki tähän kuvaan', 'rytkoset-theme' ),
                    'copyLinkSuccess'      => __( 'Linkki kopioitu', 'rytkoset-theme' ),
                    'copyLinkToast'        => __( 'Kuvan linkki kopioitu', 'rytkoset-theme' ),
                    'copyLinkPrompt'       => __( 'Kopioi linkki tähän kuvaan:', 'rytkoset-theme' ),
                )
            ) . ';',
            'before'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'rytkoset_theme_scripts' );

if ( ! function_exists( 'rytkoset_theme_inject_image_caption_metadata' ) ) {
	/**
	 * Adds up-to-date attachment caption metadata to Gutenberg image blocks.
	 *
	 * Visible figcaptions in post content may be stale, because Gutenberg stores them in post_content.
	 * PhotoSwipe reads this data attribute first so media library edits take effect without rewriting content.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	function rytkoset_theme_inject_image_caption_metadata( $block_content, $block ) {
		if ( is_admin() || ! is_singular( 'gallery_album' ) ) {
			return $block_content;
		}

		if ( empty( $block['blockName'] ) || 'core/image' !== $block['blockName'] ) {
			return $block_content;
		}

		$has_caption_attr = false !== strpos( $block_content, 'data-pswp-caption-html=' );
		$has_item_id_attr = false !== strpos( $block_content, 'data-pswp-item-id=' );

		if ( $has_caption_attr && $has_item_id_attr ) {
			return $block_content;
		}

		$attachment_id         = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : 0;
		$item_id               = $attachment_id > 0 ? (string) $attachment_id : '';
		$caption_html          = rytkoset_theme_get_attachment_caption_html( $attachment_id );
		$visible_caption_html  = rytkoset_theme_get_attachment_visible_caption_html( $attachment_id );

		if ( '' === $caption_html && '' === $item_id ) {
			return $block_content;
		}

		$block_content = preg_replace_callback(
			'/<figure\b/',
			static function ( $matches ) use ( $caption_html, $item_id, $has_caption_attr, $has_item_id_attr ) {
				$attributes = '';

				if ( ! $has_caption_attr && '' !== $caption_html ) {
					$attributes .= ' data-pswp-caption-html="' . esc_attr( $caption_html ) . '"';
				}

				if ( ! $has_item_id_attr && '' !== $item_id ) {
					$attributes .= ' data-pswp-item-id="' . esc_attr( $item_id ) . '"';
				}

				return '<figure' . $attributes;
			},
			$block_content,
			1
		);

		if ( preg_match( '/<figcaption\b[^>]*>/i', $block_content ) ) {
			if ( '' === $visible_caption_html ) {
				return $block_content;
			}

			return preg_replace(
				'/(<figcaption\b[^>]*>).*?(<\/figcaption>)/is',
				'$1' . wp_kses_post( $visible_caption_html ) . '$2',
				$block_content,
				1
			);
		}

		if ( '' === $visible_caption_html ) {
			return $block_content;
		}

		return preg_replace(
			'/<\/figure>\s*$/',
			'<figcaption class="wp-element-caption">' . wp_kses_post( $visible_caption_html ) . '</figcaption></figure>',
			$block_content,
			1
		);
	}
}
add_filter( 'render_block', 'rytkoset_theme_inject_image_caption_metadata', 10, 2 );

/**
 * Disable WordPress core image lightbox to avoid conflicts with PhotoSwipe.
 */
add_filter(
	'wp_image_lightbox_enabled',
	function () {
		return false;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_script( 'wp-lightbox' );
		wp_deregister_script( 'wp-lightbox' );
		wp_dequeue_style( 'wp-lightbox' );
		wp_deregister_style( 'wp-lightbox' );
	},
	20
);

/**
 * Open Graph + Twitter Card meta.
 */
function rytkoset_theme_social_meta() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	if ( false === apply_filters( 'rytkoset_theme_output_social_meta', true ) ) {
		return;
	}

	$post_id     = is_singular() ? get_queried_object_id() : 0;
	$site_name   = get_bloginfo( 'name' );
	$title       = $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : $site_name;
	$description = $post_id
		? ( has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30 )
		)
		: get_bloginfo( 'description' );
	$url         = $post_id ? get_permalink( $post_id ) : home_url( '/' );
	$type        = $post_id ? 'article' : 'website';

	$image        = '';
	$image_width  = 0;
	$image_height = 0;

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		if ( is_array( $image_data ) ) {
			$image        = $image_data[0];
			$image_width  = isset( $image_data[1] ) ? (int) $image_data[1] : 0;
			$image_height = isset( $image_data[2] ) ? (int) $image_data[2] : 0;
		}
	}

	if ( empty( $image ) && function_exists( 'has_site_icon' ) && has_site_icon() ) {
		$icon_size = 512;
		$image     = get_site_icon_url( $icon_size );

		if ( $image ) {
			$image_width  = $icon_size;
			$image_height = $icon_size;
		}
	}

	if ( empty( $image ) ) {
		$logo_id   = get_theme_mod( 'custom_logo' );
		$logo_data = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : array();

		if ( is_array( $logo_data ) ) {
			$image        = $logo_data[0];
			$image_width  = isset( $logo_data[1] ) ? (int) $logo_data[1] : 0;
			$image_height = isset( $logo_data[2] ) ? (int) $logo_data[2] : 0;
		}
	}

	$locale = str_replace( '_', '-', get_locale() );

	$meta = array(
		'og:title'       => $title,
		'og:description' => $description,
		'og:url'         => $url,
		'og:type'        => $type,
		'og:site_name'   => $site_name,
		'og:locale'      => $locale,
	);

	if ( ! empty( $image ) ) {
		$meta['og:image'] = $image;

		if ( $image_width > 0 && $image_height > 0 ) {
			$meta['og:image:width']  = $image_width;
			$meta['og:image:height'] = $image_height;
		}
	}

	if ( $post_id ) {
		$meta['article:published_time'] = get_the_date( DATE_W3C, $post_id );
		$meta['article:modified_time']  = get_the_modified_date( DATE_W3C, $post_id );
	}

	$meta['twitter:card']        = 'summary_large_image';
	$meta['twitter:title']       = $title;
	$meta['twitter:description'] = $description;
	$meta['twitter:url']         = $url;

	if ( ! empty( $image ) ) {
		$meta['twitter:image'] = $image;
	}

	foreach ( $meta as $property => $content ) {
		if ( empty( $content ) ) {
			continue;
		}

		$attribute = 0 === strpos( $property, 'twitter:' ) ? 'name' : 'property';
		printf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			esc_attr( $attribute ),
			esc_attr( $property ),
			esc_attr( $content )
		);
	}
}
add_action( 'wp_head', 'rytkoset_theme_social_meta', 5 );

/**
 * Tyylitellään kirjautumissivu teeman mukaiseksi.
 */
function rytkoset_theme_login_assets() {
	$theme_version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'rytkoset-theme-base',
		get_template_directory_uri() . '/assets/css/base.css',
		array(),
		$theme_version
	);

	wp_enqueue_style(
		'rytkoset-theme-login',
		get_template_directory_uri() . '/assets/css/login.css',
		array( 'rytkoset-theme-base' ),
		$theme_version
	);

}
add_action( 'login_enqueue_scripts', 'rytkoset_theme_login_assets' );

/**
 * Palauttaa login-sivun logon URL:n (custom logo -> site icon -> tyhjä).
 *
 * @return string
 */
function rytkoset_theme_get_login_logo_url() {
	$logo_url = '';

	if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
		$logo_id     = get_theme_mod( 'custom_logo' );
		$logo_source = wp_get_attachment_image_src( $logo_id, 'full' );
		$logo_url    = is_array( $logo_source ) ? $logo_source[0] : '';
	}

	if ( empty( $logo_url ) && function_exists( 'has_site_icon' ) && has_site_icon() ) {
		$logo_url = get_site_icon_url( 192 );
	}

	return $logo_url;
}

/**
 * Ajetaan lopuksi varmistava JS, joka asettaa logon taustakuvan ja piilottaa tekstin.
 */
function rytkoset_theme_login_logo_script() {
	$logo_url  = rytkoset_theme_get_login_logo_url();
	$site_name = get_bloginfo( 'name' );
	$tagline   = get_bloginfo( 'description' );
	$home_url  = home_url( '/' );
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// Poista mahdolliset vanhat brändiblokit
			document.querySelectorAll('#login .login-branding').forEach(function (el) {
				el.remove();
			});

			var heading = document.querySelector('#login h1');
			if (!heading) return;

			// Rakennetaan yhtenäinen brändilinkki
			var brandLink = document.createElement('a');
			brandLink.className = 'login-branding';
			brandLink.href = '<?php echo esc_url( $home_url ); ?>';

			var logoBlock = document.createElement('span');
			logoBlock.className = 'login-branding__logo';
			<?php if ( ! empty( $logo_url ) ) : ?>
			logoBlock.style.backgroundImage = 'url("<?php echo esc_url( $logo_url ); ?>")';
			<?php endif; ?>

			var text = document.createElement('span');
			text.className = 'login-branding__text';
			text.innerHTML =
				'<span class="login-branding__title"><?php echo esc_js( $site_name ); ?></span>'
				<?php if ( $tagline ) : ?> +
				'<span class="login-branding__tagline"><?php echo esc_js( $tagline ); ?></span>'
				<?php endif; ?>;

			brandLink.appendChild(logoBlock);
			brandLink.appendChild(text);

			// 🔑 ÄLÄ koske h1:een, se saa olla screen-reader-text.
			// Lisää brändikortti heti h1:n jälkeen näkyviin.
			heading.insertAdjacentElement('afterend', brandLink);
		});
	</script>
	<?php
}
add_action( 'login_footer', 'rytkoset_theme_login_logo_script' );


/**
 * Korvataan login-logon linkki etusivun urlilla.
 *
 * @return string
 */
function rytkoset_theme_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'rytkoset_theme_login_header_url' );

/**
 * Käytetään sivuston nimeä logon tekstinä.
 *
 * @param string $text Oletusteksti.
 * @return string
 */
function rytkoset_theme_login_header_text( $text ) {
	$site_name = get_bloginfo( 'name' );

	return $site_name ? $site_name : $text;
}
add_filter( 'login_headertext', 'rytkoset_theme_login_header_text' );

/**
 * Käännetään kirjautumissivun avaintekstit suomeksi.
 *
 * @param string $translated Alkuperäinen käännös.
 * @param string $original   Lähdeteksti.
 * @param string $domain     Tekstidomain.
 * @return string
 */
function rytkoset_theme_login_finnish_strings( $translated, $original, $domain ) {
	if ( 'default' !== $domain ) {
		return $translated;
	}

	$back_link = html_entity_decode( '&larr; Go to %s', ENT_QUOTES, 'UTF-8' );

	$map = array(
		'Username or Email Address' => 'Käyttäjätunnus tai sähköposti',
		'Password'                  => 'Salasana',
		'Remember Me'               => 'Muista minut',
		'Log In'                    => 'Kirjaudu sisään',
		'Log in'                    => 'Kirjaudu sisään',
		'Lost your password?'       => 'Unohditko salasanasi?',
		'Register'                  => 'Rekisteröidy',
		'Register For This Site'    => 'Rekisteröidy tälle sivustolle',
		'Username'                  => 'Käyttäjätunnus',
		'Email'                     => 'Sähköposti',
		'Registration confirmation will be emailed to you.' => 'Vahvistus rekisteröitymisestä lähetetään sähköpostiisi.',
		'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' => 'Anna käyttäjätunnus tai sähköposti. Saat sähköpostitse ohjeet salasanan vaihtoon.',
		'Get New Password'          => 'Lähetä uusi salasana',
		$back_link                  => '← Palaa Rytkösten sukuseuran pääsivulle',
		'← Go to %s'                => '← Palaa Rytkösten sukuseuran pääsivulle',
		'&larr; Go to %s'           => '← Palaa Rytkösten sukuseuran pääsivulle',
		'&larr; Back to %s'         => '← Palaa Rytkösten sukuseuran pääsivulle',
		'← Back to %s'              => '← Palaa Rytkösten sukuseuran pääsivulle',
		'← Go to Rytkösten sukuseura' => '← Palaa Rytkösten sukuseuran pääsivulle',
		'Error: Cookies are blocked due to unexpected output. For help, please see this documentation or try the support forums.' => 'Virhe: Keksit on estetty odottamattoman tulosteen takia. Lue ohjeet dokumentaatiosta tai kokeile tukifoorumeita.',
		'Error: Cookies are blocked or not supported by your browser. You must enable cookies to use WordPress.' => 'Virhe: Evästeet on estetty tai selain ei tue niitä. Ota evästeet käyttöön käyttääksesi WordPressiä.',
	);

	if ( isset( $map[ $original ] ) ) {
		return $map[ $original ];
	}

	return $translated;
}
add_filter( 'gettext', 'rytkoset_theme_login_finnish_strings', 10, 3 );

/**
 * Varmistetaan, että back-linkki on suomeksi, vaikka gettext ei osuisi.
 */
function rytkoset_theme_login_backlink_text() {
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var back = document.getElementById('backtoblog');
			if (!back) return;
			var link = back.querySelector('a');
			if (link) {
				link.textContent = '← Palaa Rytkösten sukuseuran pääsivulle';
			}
		});
	</script>
	<?php
}
add_action( 'login_footer', 'rytkoset_theme_login_backlink_text' );

/**
 * Returns the SKU used to identify the Tampere 2026 registration product.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_registration_sku() {
	return 'tampere-2026-osallistumismaksu';
}

/**
 * Returns true when a product is the Tampere 2026 registration product.
 *
 * @param WC_Product|null $product WooCommerce product object.
 * @return bool
 */
function rytkoset_theme_is_tampere_2026_registration_product( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$registration_mode = $product->get_meta( '_rytkoset_registration_mode', true );

	if ( 'tampere_2026' === $registration_mode ) {
		return true;
	}

	return rytkoset_theme_get_tampere_2026_registration_sku() === (string) $product->get_sku();
}

/**
 * Returns the Tampere 2026 participant count from the current cart.
 *
 * @return int
 */
function rytkoset_theme_get_tampere_2026_participant_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	$participant_count = 0;

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$participant_count += isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
	}

	return max( 0, $participant_count );
}

/**
 * Returns the maximum number of Tampere 2026 participants supported in one order.
 *
 * @return int
 */
function rytkoset_theme_get_tampere_2026_max_participants() {
	return 10;
}

/**
 * Returns a JSON Schema fragment that matches when the Tampere 2026 product is in cart.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_cart_presence_schema() {
	$product_id = wc_get_product_id_by_sku( rytkoset_theme_get_tampere_2026_registration_sku() );

	if ( ! $product_id ) {
		return array(
			'type' => 'object',
			'not'  => array(),
		);
	}

	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'items' => array(
						'contains' => array(
							'const' => (int) $product_id,
						),
					),
				),
			),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the Tampere 2026 product is not in cart.
 *
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_cart_absence_schema() {
	return array(
		'not' => rytkoset_theme_get_tampere_2026_cart_presence_schema(),
	);
}

/**
 * Returns a JSON Schema fragment that matches when cart item count is at least the given threshold.
 *
 * This MVP assumes the Tampere registration product is purchased on its own,
 * so total cart quantity is used as the participant count.
 *
 * @param int $minimum Minimum item count.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_cart_items_count_minimum_schema( $minimum ) {
	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'items_count' => array(
						'minimum' => (int) $minimum,
					),
				),
			),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when cart item count is below the given threshold.
 *
 * @param int $minimum Minimum item count expected for the field to be relevant.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_cart_items_count_below_schema( $minimum ) {
	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'items_count' => array(
						'maximum' => max( 0, (int) $minimum - 1 ),
					),
				),
			),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be required.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_required_schema( $index ) {
	return array(
		'allOf' => array(
			rytkoset_theme_get_tampere_2026_cart_presence_schema(),
			rytkoset_theme_get_cart_items_count_minimum_schema( $index ),
		),
	);
}

/**
 * Returns a JSON Schema fragment that matches when the participant field should be hidden.
 *
 * @param int $index Participant index starting from 1.
 * @return array<string, mixed>
 */
function rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ) {
	return array(
		'anyOf' => array(
			rytkoset_theme_get_tampere_2026_cart_absence_schema(),
			rytkoset_theme_get_cart_items_count_below_schema( $index ),
		),
	);
}

/**
 * Registers participant fields for the Tampere 2026 registration checkout flow.
 *
 * Uses WooCommerce Blocks' additional checkout fields API so the fields are
 * always registered for Store API submissions and then conditionally shown.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_checkout_fields() {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	$product_id = wc_get_product_id_by_sku( rytkoset_theme_get_tampere_2026_registration_sku() );

	if ( ! $product_id ) {
		return;
	}

	for ( $index = 1; $index <= rytkoset_theme_get_tampere_2026_max_participants(); $index++ ) {
		$name_field_id = sprintf( 'rytkoset/participant_%d_name', $index );
		$diet_field_id = sprintf( 'rytkoset/participant_%d_diet', $index );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $name_field_id,
				'label'             => sprintf( __( 'Osallistuja %d: nimi', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => rytkoset_theme_get_tampere_2026_participant_required_schema( $index ),
				'hidden'            => rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-participant-%d-name new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => $diet_field_id,
				'label'             => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat', 'rytkoset-theme' ), $index ),
				'optionalLabel'     => sprintf( __( 'Osallistuja %d: ruokarajoitteet tai allergiat (valinnainen)', 'rytkoset-theme' ), $index ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'hidden'            => rytkoset_theme_get_tampere_2026_participant_hidden_schema( $index ),
				'sanitize_callback' => 'sanitize_text_field',
				'attributes'        => array(
					'autocomplete'   => sprintf( 'section-participant-%d-diet new-password', $index ),
					'data-lpignore'  => 'true',
					'data-1p-ignore' => 'true',
					'maxLength'      => 200,
				),
			)
		);
	}
}
add_action( 'woocommerce_init', 'rytkoset_theme_register_tampere_2026_checkout_fields' );

/**
 * Reads an additional checkout field value from an order.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param string   $field_id Additional checkout field ID.
 * @return string
 */
function rytkoset_theme_get_order_additional_checkout_field_value( $order, $field_id ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	if (
		class_exists( '\Automattic\WooCommerce\Blocks\Package' )
		&& class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' )
	) {
		$checkout_fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
			\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
		);

		if ( is_object( $checkout_fields ) && method_exists( $checkout_fields, 'get_all_fields_from_object' ) ) {
			$fields = $checkout_fields->get_all_fields_from_object( $order, 'other', true );

			if ( isset( $fields[ $field_id ] ) ) {
				return (string) $fields[ $field_id ];
			}
		}
	}

	return (string) $order->get_meta( '_wc_other/' . $field_id, true );
}

/**
 * Returns Tampere 2026 participant data saved on an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return array<int, array<string, string>>
 */
function rytkoset_theme_get_tampere_2026_order_participants( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$participant_count = 0;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( ! rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			continue;
		}

		$participant_count += (int) $item->get_quantity();
	}

	if ( $participant_count < 1 ) {
		return array();
	}

	$participants = array();

	for ( $index = 1; $index <= $participant_count; $index++ ) {
		$name = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_name', $index )
			)
		);
		$diet = trim(
			rytkoset_theme_get_order_additional_checkout_field_value(
				$order,
				sprintf( 'rytkoset/participant_%d_diet', $index )
			)
		);

		if ( '' === $name && '' === $diet ) {
			continue;
		}

		$participants[] = array(
			'name' => $name,
			'diet' => $diet,
		);
	}

	return $participants;
}

/**
 * Returns true when an order contains Tampere 2026 registrations.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_is_tampere_2026_registration_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( rytkoset_theme_is_tampere_2026_registration_product( $product ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns an order object from a meta box callback parameter.
 *
 * @param mixed $post_or_order_object Either a WP_Post or WC_Order.
 * @return WC_Order|false
 */
function rytkoset_theme_get_order_from_admin_screen_object( $post_or_order_object ) {
	if ( $post_or_order_object instanceof WC_Order ) {
		return $post_or_order_object;
	}

	if ( $post_or_order_object instanceof WP_Post ) {
		return wc_get_order( $post_or_order_object->ID );
	}

	return false;
}

/**
 * Registers the Tampere 2026 participants metabox for order admin screens.
 *
 * Uses a dedicated metabox so the participant list is visible in both legacy
 * and HPOS order editors.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_order_metabox() {
	if ( ! function_exists( 'wc_get_page_screen_id' ) || ! function_exists( 'wc_get_container' ) ) {
		add_meta_box(
			'rytkoset-tampere-2026-participants',
			__( 'Tampere 2026 osallistujat', 'rytkoset-theme' ),
			'rytkoset_theme_render_tampere_2026_order_participants_metabox',
			'shop_order',
			'side',
			'default'
		);

		return;
	}

	$screen = 'shop_order';

	if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
		$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
		$screen     = $controller->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	add_meta_box(
		'rytkoset-tampere-2026-participants',
		__( 'Tampere 2026 osallistujat', 'rytkoset-theme' ),
		'rytkoset_theme_render_tampere_2026_order_participants_metabox',
		$screen,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'rytkoset_theme_register_tampere_2026_order_metabox' );

/**
 * Renders participant details inside the order admin metabox.
 *
 * @param mixed $post_or_order_object Either a WP_Post or WC_Order.
 * @return void
 */
function rytkoset_theme_render_tampere_2026_order_participants_metabox( $post_or_order_object ) {
	$order = rytkoset_theme_get_order_from_admin_screen_object( $post_or_order_object );

	if ( ! $order instanceof WC_Order ) {
		echo '<p>' . esc_html__( 'Tilausta ei voitu lukea.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	$participants = rytkoset_theme_get_tampere_2026_order_participants( $order );

	if ( empty( $participants ) ) {
		echo '<p>' . esc_html__( 'Tälle tilaukselle ei löytynyt osallistujatietoja.', 'rytkoset-theme' ) . '</p>';
		return;
	}

	echo '<p><strong>' . esc_html__( 'Osallistujia:', 'rytkoset-theme' ) . '</strong> ' . esc_html( (string) count( $participants ) ) . '</p>';
	echo '<ol>';

	foreach ( $participants as $participant ) {
		echo '<li>';
		echo '<strong>' . esc_html( $participant['name'] ) . '</strong>';

		if ( '' !== $participant['diet'] ) {
			echo '<br>';
			echo esc_html__( 'Ruokarajoitteet / allergiat:', 'rytkoset-theme' ) . ' ' . esc_html( $participant['diet'] );
		}

		echo '</li>';
	}

	echo '</ol>';
}

/**
 * Returns the option name used for Tampere 2026 organizer notification recipients.
 *
 * @return string
 */
function rytkoset_theme_get_tampere_2026_notification_recipients_option_name() {
	return 'rytkoset_tampere_2026_notification_recipients';
}

/**
 * Normalizes a raw email list into unique, valid recipient addresses.
 *
 * @param string $raw_value Raw textarea value.
 * @return array<int, string>
 */
function rytkoset_theme_normalize_email_list( $raw_value ) {
	$parts   = preg_split( '/[\r\n,;]+/', (string) $raw_value );
	$emails  = array();
	$results = array();

	if ( ! is_array( $parts ) ) {
		return array();
	}

	foreach ( $parts as $part ) {
		$email = sanitize_email( trim( (string) $part ) );

		if ( '' === $email || ! is_email( $email ) ) {
			continue;
		}

		$index = strtolower( $email );

		if ( isset( $emails[ $index ] ) ) {
			continue;
		}

		$emails[ $index ] = true;
		$results[]        = $email;
	}

	return $results;
}

/**
 * Sanitizes the organizer notification recipients option.
 *
 * @param string $raw_value Raw textarea value.
 * @return string
 */
function rytkoset_theme_sanitize_tampere_2026_notification_recipients_option( $raw_value ) {
	$emails = rytkoset_theme_normalize_email_list( $raw_value );

	return implode( "\n", $emails );
}

/**
 * Returns the configured organizer notification recipients.
 *
 * @return array<int, string>
 */
function rytkoset_theme_get_tampere_2026_notification_recipients() {
	$value = get_option( rytkoset_theme_get_tampere_2026_notification_recipients_option_name(), '' );

	return rytkoset_theme_normalize_email_list( (string) $value );
}

/**
 * Renders the General Settings field for organizer notification recipients.
 *
 * @return void
 */
function rytkoset_theme_render_tampere_2026_notification_recipients_setting() {
	$option_name = rytkoset_theme_get_tampere_2026_notification_recipients_option_name();
	$value       = (string) get_option( $option_name, '' );
	?>
	<textarea
		name="<?php echo esc_attr( $option_name ); ?>"
		id="<?php echo esc_attr( $option_name ); ?>"
		rows="5"
		cols="50"
		class="large-text"
	><?php echo esc_textarea( $value ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'Anna vastaanottajaosoitteet pilkuilla tai rivinvaihdoilla eroteltuna. Vain kelvolliset sähköpostiosoitteet tallennetaan.', 'rytkoset-theme' ); ?>
	</p>
	<?php
}

/**
 * Registers the General Settings field for organizer notification recipients.
 *
 * @return void
 */
function rytkoset_theme_register_tampere_2026_notification_recipients_setting() {
	$option_name = rytkoset_theme_get_tampere_2026_notification_recipients_option_name();

	register_setting(
		'general',
		$option_name,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'rytkoset_theme_sanitize_tampere_2026_notification_recipients_option',
			'default'           => '',
		)
	);

	add_settings_field(
		$option_name,
		__( 'Tampere 2026 järjestäjäilmoitusten vastaanottajat', 'rytkoset-theme' ),
		'rytkoset_theme_render_tampere_2026_notification_recipients_setting',
		'general'
	);
}
add_action( 'admin_init', 'rytkoset_theme_register_tampere_2026_notification_recipients_setting' );

/**
 * Returns true when the current site runs in a local or development environment.
 *
 * @return bool
 */
function rytkoset_theme_is_local_or_dev_environment() {
	if ( function_exists( 'wp_get_environment_type' ) ) {
		$environment_type = wp_get_environment_type();

		if ( in_array( $environment_type, array( 'local', 'development' ), true ) ) {
			return true;
		}
	}

	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	if ( ! is_string( $host ) || '' === $host ) {
		return false;
	}

	return in_array(
		strtolower( $host ),
		array( 'localhost', '127.0.0.1', 'dev.rytkoset.net' ),
		true
	);
}

/**
 * Captures the latest wp_mail failure for debugging.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_capture_wp_mail_failure( $error ) {
	$GLOBALS['rytkoset_theme_last_wp_mail_failure'] = $error;
}

/**
 * Formats a wp_mail failure into a readable string.
 *
 * @param WP_Error|null $error wp_mail failure object.
 * @return string
 */
function rytkoset_theme_format_wp_mail_failure( $error ) {
	if ( ! $error instanceof WP_Error ) {
		return __( 'Tarkempaa virhesyyta ei saatu wp_mail-kutsusta.', 'rytkoset-theme' );
	}

	$parts = array();

	foreach ( $error->get_error_codes() as $code ) {
		$messages = $error->get_error_messages( $code );
		$message  = ! empty( $messages ) ? implode( ' | ', $messages ) : __( 'Ei virheviestia.', 'rytkoset-theme' );

		$parts[] = sprintf( '%s: %s', $code, $message );
	}

	return implode( ' || ', $parts );
}

/**
 * Adds a local/dev-only debug note for organizer notification failures.
 *
 * @param WC_Order $order         WooCommerce order object.
 * @param string   $subject       Email subject.
 * @param string   $message       Email body.
 * @param string   $error_summary Formatted wp_mail failure summary.
 * @return void
 */
function rytkoset_theme_add_tampere_2026_notification_debug_note( $order, $subject, $message, $error_summary ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$note_lines = array(
		__( 'Tampere 2026 -jarjestajailmoituksen debug (local/dev).', 'rytkoset-theme' ),
		__( 'Virhe:', 'rytkoset-theme' ) . ' ' . $error_summary,
		'',
		__( 'Aihe:', 'rytkoset-theme' ) . ' ' . $subject,
		'',
		__( 'Lahetyksen viestisisalto:', 'rytkoset-theme' ),
		$message,
	);

	$order->add_order_note( implode( PHP_EOL, $note_lines ), false );
}

/**
 * Captures outgoing Tampere 2026 organizer notification email arguments.
 *
 * @param array<string, mixed> $mail_atts wp_mail arguments.
 * @return array<string, mixed>
 */
function rytkoset_theme_capture_tampere_2026_notification_mail_args( $mail_atts ) {
	$subject = isset( $mail_atts['subject'] ) ? (string) $mail_atts['subject'] : '';

	if ( 0 === strpos( $subject, 'Tampere 2026 ilmoittautuminen #' ) ) {
		$GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] = $mail_atts;
	}

	return $mail_atts;
}
add_filter( 'wp_mail', 'rytkoset_theme_capture_tampere_2026_notification_mail_args' );

/**
 * Attempts to resolve a WooCommerce order from captured notification mail args.
 *
 * @param array<string, mixed> $mail_atts wp_mail arguments.
 * @return WC_Order|false
 */
function rytkoset_theme_get_tampere_2026_order_from_mail_args( $mail_atts ) {
	if ( ! is_array( $mail_atts ) ) {
		return false;
	}

	$message = isset( $mail_atts['message'] ) ? (string) $mail_atts['message'] : '';
	$subject = isset( $mail_atts['subject'] ) ? (string) $mail_atts['subject'] : '';

	if ( preg_match( '/[?&]id=(\d+)/', $message, $matches ) ) {
		return wc_get_order( (int) $matches[1] );
	}

	if ( preg_match( '/[?&]post=(\d+)/', $message, $matches ) ) {
		return wc_get_order( (int) $matches[1] );
	}

	if ( preg_match( '/#(\d+)\s*$/', $subject, $matches ) ) {
		return wc_get_order( (int) $matches[1] );
	}

	return false;
}

/**
 * Adds a local/dev-only debug note when wp_mail fails for Tampere 2026 notifications.
 *
 * @param WP_Error $error wp_mail failure object.
 * @return void
 */
function rytkoset_theme_maybe_log_tampere_2026_notification_mail_failure( $error ) {
	if ( ! rytkoset_theme_is_local_or_dev_environment() ) {
		return;
	}

	$mail_atts = isset( $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] ) && is_array( $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] )
		? $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args']
		: null;

	unset( $GLOBALS['rytkoset_theme_last_tampere_2026_mail_args'] );

	if ( ! is_array( $mail_atts ) ) {
		return;
	}

	$subject = isset( $mail_atts['subject'] ) ? (string) $mail_atts['subject'] : '';

	if ( 0 !== strpos( $subject, 'Tampere 2026 ilmoittautuminen #' ) ) {
		return;
	}

	$order = rytkoset_theme_get_tampere_2026_order_from_mail_args( $mail_atts );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$message       = isset( $mail_atts['message'] ) ? (string) $mail_atts['message'] : '';
	$error_summary = rytkoset_theme_format_wp_mail_failure( $error );

	rytkoset_theme_add_tampere_2026_notification_debug_note( $order, $subject, $message, $error_summary );
}
add_action( 'wp_mail_failed', 'rytkoset_theme_maybe_log_tampere_2026_notification_mail_failure' );

/**
 * Returns the edit URL for an order in the current admin configuration.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_order_admin_edit_url( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
		$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );

		if ( is_object( $controller ) && method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) && $controller->custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );
		}
	}

	return admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
}

/**
 * Builds the organizer notification email subject for a Tampere 2026 order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_notification_subject( $order ) {
	return sprintf( 'Tampere 2026 ilmoittautuminen #%s', $order->get_order_number() );
}

/**
 * Builds the organizer notification email body for a Tampere 2026 order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string
 */
function rytkoset_theme_get_tampere_2026_notification_message( $order ) {
	$participants   = rytkoset_theme_get_tampere_2026_order_participants( $order );
	$admin_edit_url = rytkoset_theme_get_order_admin_edit_url( $order );
	$contact_name   = trim( $order->get_formatted_billing_full_name() );
	$email          = trim( (string) $order->get_billing_email() );
	$phone          = trim( (string) $order->get_billing_phone() );
	$payment_method = trim( (string) $order->get_payment_method_title() );
	$customer_note  = trim( (string) $order->get_customer_note() );
	$created_at     = $order->get_date_created();
	$created_text   = $created_at ? wp_date( 'j.n.Y H:i', $created_at->getTimestamp(), wp_timezone() ) : __( 'Ei tiedossa', 'rytkoset-theme' );
	$status_name    = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();
	$lines          = array(
		'Rytkösten sukuseura / Tampere 2026 ilmoittautuminen',
		'',
		'tilausnumero: #' . $order->get_order_number(),
		'päiväys: ' . $created_text,
		'tila: ' . $status_name,
		'maksutapa: ' . ( '' !== $payment_method ? $payment_method : __( 'Ei tiedossa', 'rytkoset-theme' ) ),
	);

	if ( '' !== $admin_edit_url ) {
		$lines[] = 'tilauksen hallinta: ' . $admin_edit_url;
	}

	$lines[] = '';
	$lines[] = 'Yhteyshenkilö:';
	$lines[] = 'nimi: ' . ( '' !== $contact_name ? $contact_name : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = 'sähköposti: ' . ( '' !== $email ? $email : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = 'puhelin: ' . ( '' !== $phone ? $phone : __( 'Ei annettu', 'rytkoset-theme' ) );
	$lines[] = '';
	$lines[] = 'Osallistujat:';

	if ( empty( $participants ) ) {
		$lines[] = '- Osallistujatietoja ei löytynyt tilaukselta.';
	} else {
		foreach ( $participants as $index => $participant ) {
			$participant_name = '' !== $participant['name'] ? $participant['name'] : __( 'Nimi puuttuu', 'rytkoset-theme' );
			$lines[]          = sprintf( '%d. %s', $index + 1, $participant_name );

			if ( '' !== $participant['diet'] ) {
				$lines[] = '   ruokarajoitteet / allergiat: ' . $participant['diet'];
			}
		}
	}

	if ( '' !== $customer_note ) {
		$lines[] = '';
		$lines[] = 'Asiakkaan lisätiedot:';
		$lines[] = $customer_note;
	}

	return implode( PHP_EOL, $lines );
}

/**
 * Sends the Tampere 2026 organizer notification for an order.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function rytkoset_theme_send_tampere_2026_organizer_notification( $order ) {
	if ( ! $order instanceof WC_Order || ! rytkoset_theme_is_tampere_2026_registration_order( $order ) ) {
		return false;
	}

	$sent_at = (string) $order->get_meta( '_rytkoset_tampere_2026_notification_sent_at', true );

	if ( '' !== $sent_at ) {
		return false;
	}

	$recipients = rytkoset_theme_get_tampere_2026_notification_recipients();

	if ( empty( $recipients ) ) {
		$order->add_order_note(
			__( 'Tampere 2026 -järjestäjäilmoitusta ei lähetetty, koska vastaanottajaosoitteita ei ole asetettu kohdassa Asetukset > Yleiset.', 'rytkoset-theme' ),
			false
		);
		return false;
	}

	$sent = wp_mail(
		$recipients,
		rytkoset_theme_get_tampere_2026_notification_subject( $order ),
		rytkoset_theme_get_tampere_2026_notification_message( $order )
	);

	if ( ! $sent ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: recipient email list. */
				__( 'Tampere 2026 -järjestäjäilmoituksen lähetys epäonnistui. Vastaanottajat: %s', 'rytkoset-theme' ),
				implode( ', ', $recipients )
			),
			false
		);
		return false;
	}

	$order->update_meta_data( '_rytkoset_tampere_2026_notification_sent_at', current_time( 'mysql' ) );
	$order->update_meta_data( '_rytkoset_tampere_2026_notification_recipients', implode( ', ', $recipients ) );
	$order->save();

	$order->add_order_note(
		sprintf(
			/* translators: %s: recipient email list. */
			__( 'Tampere 2026 -järjestäjäilmoitus lähetettiin osoitteisiin: %s', 'rytkoset-theme' ),
			implode( ', ', $recipients )
		),
		false
	);

	return true;
}

/**
 * Sends the Tampere 2026 organizer notification when an order moves to on-hold.
 *
 * @param int      $order_id WooCommerce order ID.
 * @param WC_Order $order    WooCommerce order object.
 * @return void
 */
function rytkoset_theme_maybe_send_tampere_2026_organizer_notification( $order_id, $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	rytkoset_theme_send_tampere_2026_organizer_notification( $order );
}
add_action( 'woocommerce_order_status_on-hold', 'rytkoset_theme_maybe_send_tampere_2026_organizer_notification', 10, 2 );

