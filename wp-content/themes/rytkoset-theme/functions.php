<?php
/**
 * Rytköset Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a cache-busting version for a local theme asset.
 *
 * @param string $file_path Absolute filesystem path to the asset.
 * @return string
 */
function rytkoset_theme_get_asset_version( $file_path ) {
	$modified_time = is_file( $file_path ) ? filemtime( $file_path ) : false;

	return false !== $modified_time
		? (string) $modified_time
		: (string) wp_get_theme()->get( 'Version' );
}

require_once get_template_directory() . '/inc/security.php';
require_once get_template_directory() . '/inc/icons.php';
require_once get_template_directory() . '/inc/social-links.php';
require_once get_template_directory() . '/inc/share.php';
require_once get_template_directory() . '/inc/gallery-albums.php';
require_once get_template_directory() . '/inc/media-library.php';
require_once get_template_directory() . '/inc/event-roles.php';
require_once get_template_directory() . '/inc/events.php';
require_once get_template_directory() . '/inc/event-registrations.php';
require_once get_template_directory() . '/inc/event-registration-privacy.php';
require_once get_template_directory() . '/inc/event-participants-admin.php';
require_once get_template_directory() . '/inc/event-participants-messaging.php';
require_once get_template_directory() . '/inc/digital-magazines.php';
require_once get_template_directory() . '/inc/digital-magazine-access.php';
require_once get_template_directory() . '/inc/attachment-iptc.php';
require_once get_template_directory() . '/inc/seo-meta.php';
require_once get_template_directory() . '/inc/login.php';
require_once get_template_directory() . '/inc/newsletter.php';
require_once get_template_directory() . '/inc/user-membership.php';
require_once get_template_directory() . '/inc/woocommerce-mollie.php';
require_once get_template_directory() . '/inc/woocommerce-membership.php';
require_once get_template_directory() . '/inc/woocommerce-digital-magazine.php';
require_once get_template_directory() . '/inc/woocommerce-tampere-2026.php';
require_once get_template_directory() . '/inc/woocommerce-product-sync.php';
require_once get_template_directory() . '/inc/woocommerce-shop-categories.php';
require_once get_template_directory() . '/inc/customizer-contact.php';
require_once get_template_directory() . '/inc/email.php';
require_once get_template_directory() . '/inc/coming-soon.php';

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

/**
 * Resolves an accessible alt text for a gallery image.
 *
 * WordPress treats an empty alt as decorative; for photo galleries this is
 * rarely what we want. Falls back through media-library alt → caption so a
 * meaningful name is announced even when the alt field was left empty.
 *
 * @param int    $attachment_id Attachment post ID.
 * @param string $explicit_alt  Alt provided by the calling context (e.g. ACF gallery row).
 * @return string
 */
function rytkoset_theme_get_gallery_image_alt( $attachment_id, $explicit_alt = '' ) {
	$attachment_id = (int) $attachment_id;
	$explicit      = trim( (string) $explicit_alt );

	if ( '' !== $explicit ) {
		return $explicit;
	}

	if ( $attachment_id <= 0 ) {
		return '';
	}

	$meta_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	if ( '' !== $meta_alt ) {
		return $meta_alt;
	}

	$caption = trim( (string) wp_get_attachment_caption( $attachment_id ) );
	if ( '' !== $caption ) {
		return $caption;
	}

	return '';
}

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

function rytkoset_theme_setup() {
	// Otsikkotagi WP:n hallintaan
	add_theme_support( 'title-tag' );

	// Esikatselukuvat
	add_theme_support( 'post-thumbnails' );

	// Some-jaon esikatselukuva (Open Graph). 1200 x 630 px on Facebookin,
	// LinkedInin ja X:n suosittelema koko isolle esikatselukortille.
	// HUOM: vanhat kuvat tarvitsevat "Regenerate Thumbnails" -ajon.
	add_image_size( 'rytkoset-og', 1200, 630, true );

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
			'primary' => __( 'Päävalikko', 'rytkoset-theme' ),
			'footer'  => __( 'Footer-valikko', 'rytkoset-theme' ),
			'account' => __( 'Käyttäjä/tili-valikko', 'rytkoset-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'rytkoset_theme_setup' );

/**
 * Checks whether the current request should show dev-environment markers.
 *
 * The marker is intentionally domain-bound so production never depends on a
 * manual WordPress setting.
 *
 * @return bool True when the current request host is dev.rytkoset.net.
 */
function rytkoset_theme_is_dev_site() {
	$host = '';

	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
	}

	if ( '' === $host ) {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	}

	$host = strtolower( trim( $host ) );
	$host = preg_replace( '/:\d+$/', '', $host );

	return 'dev.rytkoset.net' === $host;
}

/**
 * Builds a safe WooCommerce cart link for the site navigation.
 *
 * @param array $args Markup options.
 * @return string
 */
function rytkoset_theme_get_cart_link_markup( $args = array() ) {
	if ( ! function_exists( 'wc_get_cart_url' ) ) {
		return '';
	}

	$defaults = array(
		'class' => 'site-cart-link',
	);

	$args     = wp_parse_args( $args, $defaults );
	$cart_url = wc_get_cart_url();

	if ( '' === $cart_url ) {
		return '';
	}

	$item_count = 0;

	if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
		$item_count = (int) WC()->cart->get_cart_contents_count();
	}

	$label      = __( 'Ostoskori', 'rytkoset-theme' );
	$aria_label = $label;

	if ( $item_count > 0 ) {
		$aria_label = sprintf(
			/* translators: %d: Number of products in cart. */
			_n( 'Ostoskori, %d tuote', 'Ostoskori, %d tuotetta', $item_count, 'rytkoset-theme' ),
			$item_count
		);
	}

	ob_start();
	?>
	<a class="<?php echo esc_attr( trim( $args['class'] ) ); ?>" href="<?php echo esc_url( $cart_url ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>">
		<span class="site-cart-link__icon" aria-hidden="true"></span>
		<span class="site-cart-link__label"><?php echo esc_html( $label ); ?></span>
		<?php if ( $item_count > 0 ) : ?>
			<span class="site-cart-link__count" aria-hidden="true"><?php echo esc_html( (string) $item_count ); ?></span>
		<?php endif; ?>
	</a>
	<?php

	return trim( ob_get_clean() );
}

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
	$home_url  = home_url( '/' );
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
			<a class="<?php echo esc_attr( trim( $args['link_class'] ) ); ?>" href="<?php echo esc_url( $home_url ); ?>">
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
	/* translators: %s: logged-in user's display name. */
	echo '<button type="button" class="account-menu__user-trigger" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr( sprintf( __( 'Avaa tilivalikko (%s)', 'rytkoset-theme' ), $display_name ) ) . '">';
	echo '<span class="account-menu__avatar">';
	echo wp_kses_post( $avatar );
	echo '</span>';
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
 * Palauttaa korissa jo olevat yksittäin ostettavat WooCommerce-tuotteet.
 *
 * @return array<int>
 */
function rytkoset_theme_get_sold_individually_cart_product_ids() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	$product_ids = array();

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
		$product      = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product
			? $cart_item['data']
			: null;

		if ( ! $product_id || ! $product || ! $product->is_sold_individually() ) {
			continue;
		}

		$product_ids[] = $product_id;

		if ( $variation_id ) {
			$product_ids[] = $variation_id;
		}
	}

	return array_values( array_unique( $product_ids ) );
}

/**
 * Estää saman WooCommerce-virheilmoituksen lisäämisen sessioon kahdesti.
 *
 * @param string $message Lisättävä virheilmoitus.
 * @return string
 */
function rytkoset_theme_deduplicate_woocommerce_error_notice( $message ) {
	if ( ! function_exists( 'wc_has_notice' ) || ! is_string( $message ) ) {
		return $message;
	}

	return wc_has_notice( $message, 'error' ) ? '' : $message;
}
add_filter( 'woocommerce_add_error', 'rytkoset_theme_deduplicate_woocommerce_error_notice' );

/**
 * Lataa tyylit ja skriptit.
 */
function rytkoset_theme_scripts() {
		// Teeman päätyyli (style.css) – sisältää enää teemaotsakkeen. Pidetään
		// enqueutettuna moduuliketjun juurena (ja jotta WordPress näkee teeman tyylin).
		wp_enqueue_style(
			'rytkoset-theme-style',
			get_stylesheet_uri(),
			array(),
			rytkoset_theme_get_asset_version( get_stylesheet_directory() . '/style.css' )
		);

		// Teeman CSS-moduulit kaskadijärjestyksessä. Korvaa style.css:n vanhan
		// @import-ketjun erillisillä enqueueilla, jotta selain voi ladata moduulit
		// rinnakkain. Jokainen moduuli riippuu edellisestä, joten latausjärjestys
		// (base → layout/components → … → responsive viimeisenä) säilyy.
		$css_modules = array(
			'rytkoset-theme-base'        => 'base.css',
			'rytkoset-theme-layout'      => 'layout.css',
			'rytkoset-theme-hero'        => 'hero.css',
			'rytkoset-theme-home'        => 'home.css',
			'rytkoset-theme-404'         => '404.css',
			'rytkoset-theme-components'  => 'components.css',
			'rytkoset-theme-nav-base'    => 'nav.base.css',
			'rytkoset-theme-nav-desktop' => 'nav.desktop.css',
			'rytkoset-theme-nav-account' => 'nav.account.css',
			'rytkoset-theme-nav-mobile'  => 'nav.mobile.css',
			'rytkoset-theme-footer'      => 'footer.css',
			'rytkoset-theme-responsive'  => 'responsive.css',
		);

		$previous_css_handle = 'rytkoset-theme-style';
		foreach ( $css_modules as $handle => $filename ) {
			$module_path = get_template_directory() . '/assets/css/' . $filename;

			wp_enqueue_style(
				$handle,
				get_template_directory_uri() . '/assets/css/' . $filename,
				array( $previous_css_handle ),
				rytkoset_theme_get_asset_version( $module_path )
			);

			$previous_css_handle = $handle;
		}

		// Ehdolliset (sivukohtaiset) tyylit ladataan moduuliketjun jälkeen, jotta
		// ne pääsevät yliajamaan perustyylit kuten ennenkin.
		$core_css_dependency = $previous_css_handle;

		// Mobiilivalikon JS
		wp_enqueue_script(
			'rytkoset-theme-main',
			get_template_directory_uri() . '/assets/js/main.js',
			array(),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/js/main.js' ),
			true // footer
		);

		// Jakopainikkeiden JS (Web Share API + clipboard-fallback)
	if ( is_singular() ) {
		wp_enqueue_script(
			'rytkoset-theme-share',
			get_template_directory_uri() . '/assets/js/share.js',
			array(),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/js/share.js' ),
			true // footer
		);
	}

	if (
		function_exists( 'is_woocommerce' )
		&& ( is_woocommerce() || is_cart() || is_checkout() )
	) {
		wp_enqueue_style(
			'rytkoset-theme-shop',
			get_template_directory_uri() . '/assets/css/shop.css',
			array( $core_css_dependency ),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/css/shop.css' )
		);

		wp_enqueue_script(
			'rytkoset-theme-shop-select',
			get_template_directory_uri() . '/assets/js/shop-select.js',
			array(),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/js/shop-select.js' ),
			true
		);

		wp_add_inline_script(
			'rytkoset-theme-shop-select',
			'window.rytkosetShopConfig = ' . wp_json_encode(
				array(
					'soldIndividuallyCartProductIds' => rytkoset_theme_get_sold_individually_cart_product_ids(),
					'soldIndividuallyInCartText'     => __( 'Jo ostoskorissa', 'rytkoset-theme' ),
				)
			) . ';',
			'before'
		);
	}

	if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
		wp_enqueue_style(
			'rytkoset-theme-forum',
			get_template_directory_uri() . '/assets/css/forum.css',
			array( $core_css_dependency ),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/css/forum.css' )
		);
	}

	if ( is_post_type_archive( 'digital_magazine' ) || is_singular( 'digital_magazine' ) ) {
		wp_enqueue_style(
			'rytkoset-theme-digital-magazine',
			get_template_directory_uri() . '/assets/css/digital-magazine.css',
			array( $core_css_dependency ),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/css/digital-magazine.css' )
		);
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		wp_add_inline_script(
			'rytkoset-theme-main',
			'window.rytkosetCheckoutConfig = ' . wp_json_encode(
				array(
					'checkoutNotes' => array_values(
						array_filter(
							array(
								function_exists( 'rytkoset_theme_cart_requires_member_names' ) && rytkoset_theme_cart_requires_member_names()
										? rytkoset_theme_get_membership_checkout_notice_markup()
										: '',
								function_exists( 'rytkoset_theme_cart_has_tampere_2026_registration' ) && rytkoset_theme_cart_has_tampere_2026_registration()
										? rytkoset_theme_get_tampere_2026_checkout_notice_markup()
										: '',
							)
						)
					),
				)
			) . ';',
			'before'
		);
	}

		// Load PhotoSwipe on album archive, single albums, and fallback query var (plain permalinks).
	if (
	is_post_type_archive( 'gallery_album' )
	|| is_singular( 'gallery_album' )
	|| get_query_var( 'gallery_album' )
	|| 'gallery_album' === get_query_var( 'post_type' )
		) {
		$photoswipe_base      = get_template_directory_uri() . '/assets/vendor/photoswipe';
		$photoswipe_base_path = get_template_directory() . '/assets/vendor/photoswipe';

		// WooCommerce registers PhotoSwipe 4 under legacy handles that clash
		// with the theme gallery. Remove them on album pages so the theme can
		// load PhotoSwipe 5 consistently.
		wp_dequeue_script( 'wc-photoswipe' );
		wp_deregister_script( 'wc-photoswipe' );
		wp_dequeue_script( 'wc-photoswipe-ui-default' );
		wp_deregister_script( 'wc-photoswipe-ui-default' );
		wp_dequeue_style( 'photoswipe' );
		wp_deregister_style( 'photoswipe' );
		wp_dequeue_style( 'photoswipe-default-skin' );
		wp_deregister_style( 'photoswipe-default-skin' );

		wp_enqueue_style(
			'rytkoset-theme-gallery',
			get_template_directory_uri() . '/assets/css/gallery.css',
			array( $core_css_dependency ),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/css/gallery.css' )
		);

		wp_enqueue_style(
			'rytkoset-photoswipe-style',
			$photoswipe_base . '/photoswipe.css',
			array(),
			rytkoset_theme_get_asset_version( $photoswipe_base_path . '/photoswipe.css' )
		);

		wp_enqueue_script(
			'rytkoset-photoswipe-core',
			$photoswipe_base . '/photoswipe.umd.min.js',
			array(),
			rytkoset_theme_get_asset_version( $photoswipe_base_path . '/photoswipe.umd.min.js' ),
			true
		);

		wp_enqueue_script(
			'rytkoset-photoswipe-lightbox',
			$photoswipe_base . '/photoswipe-lightbox.umd.min.js',
			array( 'rytkoset-photoswipe-core' ),
			rytkoset_theme_get_asset_version( $photoswipe_base_path . '/photoswipe-lightbox.umd.min.js' ),
			true
		);

		wp_enqueue_script(
			'rytkoset-photoswipe-init',
			get_template_directory_uri() . '/assets/js/photoswipe-init.js',
			array( 'rytkoset-photoswipe-lightbox' ),
			rytkoset_theme_get_asset_version( get_template_directory() . '/assets/js/photoswipe-init.js' ),
			true
		);

		wp_add_inline_script(
			'rytkoset-photoswipe-init',
			'window.rytkosetPhotoSwipe = ' . wp_json_encode(
				array(
					'dynamicCaptionCssUrl' => add_query_arg(
						'ver',
						rytkoset_theme_get_asset_version( $photoswipe_base_path . '/photoswipe-dynamic-caption-plugin.css' ),
						$photoswipe_base . '/photoswipe-dynamic-caption-plugin.css'
					),
					'dynamicCaptionJsUrl'  => add_query_arg(
						'ver',
						rytkoset_theme_get_asset_version( $photoswipe_base_path . '/photoswipe-dynamic-caption-plugin.esm.js' ),
						$photoswipe_base . '/photoswipe-dynamic-caption-plugin.esm.js'
					),
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

	$attachment_id        = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : 0;
	$item_id              = $attachment_id > 0 ? (string) $attachment_id : '';
	$caption_html         = rytkoset_theme_get_attachment_caption_html( $attachment_id );
	$visible_caption_html = rytkoset_theme_get_attachment_visible_caption_html( $attachment_id );

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

// =============================================================================
// bbPress forum helpers
// =============================================================================

/**
 * Returns an initials-based avatar span for the forum design.
 *
 * @param string $display_name Full display name.
 * @param string $size         'sm' | 'md' (default) | 'lg' | 'xl'.
 * @return string HTML.
 */
if ( ! function_exists( 'rytkoset_theme_forum_avatar' ) ) :
	function rytkoset_theme_forum_avatar( $display_name, $size = 'md' ) {
		$name  = trim( (string) $display_name );
		$words = array_values( array_filter( explode( ' ', $name ) ) );
		if ( count( $words ) >= 2 ) {
			$initials = mb_strtoupper( mb_substr( $words[0], 0, 1 ) ) .
				mb_strtoupper( mb_substr( end( $words ), 0, 1 ) );
		} else {
			$initials = mb_strtoupper( mb_substr( $name, 0, 2 ) );
		}
		$size_class = 'md' === $size ? '' : ( 'lg' === $size ? ' forum-avatar--lg' : ( 'xl' === $size ? ' forum-avatar--xl' : '' ) );
		return '<span class="forum-avatar' . $size_class . '" aria-hidden="true">' . esc_html( $initials ) . '</span>';
	}
endif;

/**
 * Returns the color-variant slug for a forum based on its post_name.
 *
 * @param int $forum_id Forum post ID.
 * @return string Slug like 'rytkoset', 'net', 'seura', 'seka', 'testi', or 'default'.
 */
if ( ! function_exists( 'rytkoset_theme_forum_color' ) ) :
	function rytkoset_theme_forum_color( $forum_id ) {
		$slug = (string) get_post_field( 'post_name', (int) $forum_id );
		$map  = array(
			'net'          => 'net',
			'sukuseura'    => 'seura',
			'sekalainen'   => 'seka',
			'testiviestit' => 'testi',
			'testi'        => 'testi',
		);
		foreach ( $map as $key => $color ) {
			if ( false !== strpos( $slug, $key ) ) {
				return $color;
			}
		}
		return 'rytkoset'; // default / Rytköset forum
	}
endif;

/**
 * Returns the display icon text for a forum (single letter or abbreviation).
 *
 * @param int    $forum_id Forum post ID.
 * @param string $color    Color variant from rytkoset_theme_forum_color().
 * @return string Icon label.
 */
if ( ! function_exists( 'rytkoset_theme_forum_icon' ) ) :
	function rytkoset_theme_forum_icon( $forum_id, $color ) {
		if ( 'net' === $color ) {
			return '.net';
		}
		$title = (string) get_the_title( (int) $forum_id );
		return mb_strtoupper( mb_substr( wp_strip_all_tags( $title ), 0, 1 ) );
	}
endif;

/**
 * Returns an author's display name from any bbPress post (topic or reply).
 *
 * @param int $post_id Topic or reply post ID.
 * @return string
 */
if ( ! function_exists( 'rytkoset_theme_bbp_author_name' ) ) :
	function rytkoset_theme_bbp_author_name( $post_id ) {
		$author_id = (int) get_post_field( 'post_author', (int) $post_id );
		if ( ! $author_id ) {
			return __( 'Nimetön', 'rytkoset-theme' );
		}
		$user = get_userdata( $author_id );
		return $user ? $user->display_name : __( 'Nimetön', 'rytkoset-theme' );
	}
endif;

// =============================================================================

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
 * Returns the list of supported order statuses without the wc- prefix.
 *
 * @return array<int, string>
 */
function rytkoset_theme_get_supported_order_statuses() {
	$statuses = array_keys( wc_get_order_statuses() );

	return array_values(
		array_map(
			static function ( $status ) {
						$status = (string) $status;

						return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
			},
			$statuses
		)
	);
}
