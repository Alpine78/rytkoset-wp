<?php
/**
 * Header template for Rytköset Theme
 *
 * @package Rytkoset_Theme
 * @version 0.2.0
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary">
    <?php esc_html_e( 'Siirry suoraan sisältöön', 'rytkoset-theme' ); ?>
</a>

<header class="site-header" role="banner">
    <?php
    $home_url      = esc_url( home_url( '/' ) );
    $custom_logo   = '';
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    $social_links  = rytkoset_theme_get_social_links();

    if ( $custom_logo_id ) {
        $custom_logo = wp_get_attachment_image( $custom_logo_id, 'full', false, array( 'class' => 'custom-logo' ) );
    }
    ?>

    <div class="site-header__utility">
        <div class="site-header__container site-header__utility-inner">
            <a class="utility-contact" href="mailto:info@rytkoset.net">
                <svg aria-hidden="true" focusable="false" viewBox="0 0 16 16" width="16" height="16">
                    <rect x="2" y="3.5" width="12" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"></rect>
                    <path d="m2.5 5 5.5 4 5.5-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>info@rytkoset.net</span>
            </a>

            <div class="site-header__utility-actions">
                <?php if ( ! empty( $social_links ) ) : ?>
                    <nav class="site-header__social" aria-label="<?php esc_attr_e( 'Sosiaalisen median linkit', 'rytkoset-theme' ); ?>">
                        <ul class="site-header__social-list">
                            <?php foreach ( $social_links as $social_link ) : ?>
                                <?php
                                if ( empty( $social_link['icon_src'] ) ) {
                                    continue;
                                }
                                ?>
                                <li class="site-header__social-item">
                                    <a class="site-header__social-link site-header__social-link--<?php echo esc_attr( $social_link['icon'] ); ?>" href="<?php echo esc_url( $social_link['url'] ); ?>">
                                        <span class="screen-reader-text"><?php echo esc_html( $social_link['label'] ); ?></span>
                                        <img src="<?php echo esc_url( $social_link['icon_src'] ); ?>" alt="" aria-hidden="true" />
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                    <span class="site-header__utility-divider" aria-hidden="true"></span>
                <?php endif; ?>

                <button class="theme-toggle desktop-theme-toggle" type="button" aria-pressed="false">
                    <span class="theme-toggle__icon" aria-hidden="true">🌙</span>
                    <span class="theme-toggle__label"><?php esc_html_e( 'Tumma', 'rytkoset-theme' ); ?></span>
                </button>

                <span class="site-header__utility-divider" aria-hidden="true"></span>

                <div class="account-nav-wrapper">
                    <?php if ( is_user_logged_in() ) : ?>
                        <nav class="account-nav" aria-label="<?php esc_attr_e( 'Tilivalikko', 'rytkoset-theme' ); ?>">
                            <?php
                            wp_nav_menu(
                                array(
                                    'theme_location' => 'account',
                                    'menu_class'     => 'account-nav__list',
                                    'container'      => false,
                                    'fallback_cb'    => 'rytkoset_theme_account_menu_logged_in_fallback',
                                )
                            );
                            ?>
                        </nav>
                    <?php else : ?>
                        <nav class="account-nav" aria-label="<?php esc_attr_e( 'Tilivalikko', 'rytkoset-theme' ); ?>">
                            <?php rytkoset_theme_account_menu_logged_out_fallback(); ?>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="site-header__primary">
        <div class="site-header__container site-header__primary-inner">
            <div class="site-branding">
                <a class="site-branding__link" href="<?php echo $home_url; ?>" aria-label="<?php esc_attr_e( 'Rytkösten sukuseura - etusivulle', 'rytkoset-theme' ); ?>">
                    <span class="site-logo site-logo--header">
                        <?php
                        if ( $custom_logo ) {
                            echo wp_kses_post( $custom_logo );
                        } else {
                            bloginfo( 'name' );
                        }
                        ?>
                    </span>

                    <span class="site-title-group">
                        <?php if ( is_front_page() && is_home() ) : ?>
                            <h1 class="site-title"><?php bloginfo( 'name' ); ?></h1>
                        <?php else : ?>
                            <p class="site-title"><?php bloginfo( 'name' ); ?></p>
                        <?php endif; ?>

                        <?php
                        $description = get_bloginfo( 'description', 'display' );
                        if ( $description || is_customize_preview() ) :
                            ?>
                            <span class="site-description"><?php echo esc_html( $description ); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </div><!-- .site-branding -->

            <div class="site-nav-wrapper">
                <nav class="site-nav primary-nav" aria-label="<?php esc_attr_e( 'Päävalikko', 'rytkoset-theme' ); ?>">
                    <?php
                    wp_nav_menu(
                        array(
                            'theme_location' => 'primary',
                            'menu_class'     => 'site-nav__list primary-nav__list',
                            'container'      => false,
                            'fallback_cb'    => false,
                        )
                    );
                    ?>
                </nav>

                <div class="site-header__tools">
                    <div class="site-header__search">
                        <button class="site-search-toggle icon-btn" type="button" aria-expanded="false" aria-controls="site-header-search" aria-label="<?php esc_attr_e( 'Avaa haku', 'rytkoset-theme' ); ?>" data-label-open="<?php esc_attr_e( 'Avaa haku', 'rytkoset-theme' ); ?>" data-label-close="<?php esc_attr_e( 'Sulje haku', 'rytkoset-theme' ); ?>">
                            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="20" height="20">
                                <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"></circle>
                                <path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                            </svg>
                        </button>

                        <form id="site-header-search" class="site-header__search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" hidden>
                            <label class="screen-reader-text" for="site-header-search-field"><?php esc_html_e( 'Hae sivustolta', 'rytkoset-theme' ); ?></label>
                            <input id="site-header-search-field" class="site-header__search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Hae sivustolta', 'rytkoset-theme' ); ?>">
                            <button class="site-header__search-submit" type="submit"><?php esc_html_e( 'Hae', 'rytkoset-theme' ); ?></button>
                        </form>
                    </div>

                    <?php
                    echo wp_kses_post(
                        rytkoset_theme_get_cart_link_markup(
                            array(
                                'class' => 'site-cart-link site-cart-link--desktop',
                            )
                        )
                    );
                    ?>
                </div>

                <div class="site-header__mobile-actions">
                    <button class="mobile-menu-toggle"
                            type="button"
                            aria-expanded="false"
                            aria-controls="mobile-menu"
                            aria-haspopup="true"
                            data-submenu-label="<?php esc_attr_e( 'Avaa alavalikko', 'rytkoset-theme' ); ?>">
                        <span class="mobile-menu-toggle__icon" aria-hidden="true"><span></span></span>
                        <span class="mobile-menu-toggle__label">
                            <?php esc_html_e( 'Valikko', 'rytkoset-theme' ); ?>
                        </span>
                    </button>

                    <?php
                    echo wp_kses_post(
                        rytkoset_theme_get_cart_link_markup(
                            array(
                                'class' => 'site-cart-link site-cart-link--mobile',
                            )
                        )
                    );
                    ?>
                </div>
            </div><!-- .site-nav-wrapper -->
        </div>
    </div>

    <div class="mobile-menu-layer">
        <div class="mobile-menu__overlay" aria-hidden="true" hidden></div>
        <nav id="mobile-menu"
            class="mobile-menu"
            aria-label="<?php esc_attr_e( 'Mobiilivalikko', 'rytkoset-theme' ); ?>"
            aria-hidden="true"
            aria-expanded="false"
            tabindex="-1">

            <div class="mm-header">
                <a class="mm-brand" href="<?php echo $home_url; ?>">
                    <?php if ( $custom_logo ) : ?>
                        <span class="mm-brand__logo"><?php echo wp_kses_post( $custom_logo ); ?></span>
                    <?php else : ?>
                        <span class="mm-brand__name"><?php bloginfo( 'name' ); ?></span>
                    <?php endif; ?>
                </a>
                <button type="button" class="mobile-menu__close mm-close" aria-label="<?php esc_attr_e( 'Sulje valikko', 'rytkoset-theme' ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" aria-hidden="true" focusable="false">
                        <path d="M6 6l12 12M18 6l-12 12" />
                    </svg>
                </button>
            </div>

            <div class="mm-scroll">
                <?php
                wp_nav_menu(
                    array(
                        'theme_location' => 'primary',
                        'menu_class'     => 'mm-list',
                        'container'      => false,
                        'fallback_cb'    => false,
                    )
                );
                ?>

                <div class="mm-section mm-section--account">
                    <p class="mm-section__title"><?php esc_html_e( 'Tili', 'rytkoset-theme' ); ?></p>
                    <?php if ( is_user_logged_in() ) :
                        $current_user  = wp_get_current_user();
                        $first_initial = $current_user->first_name ? mb_strtoupper( mb_substr( $current_user->first_name, 0, 1 ) ) : '';
                        $last_initial  = $current_user->last_name  ? mb_strtoupper( mb_substr( $current_user->last_name,  0, 1 ) ) : '';
                        $initials      = $first_initial . $last_initial;
                        if ( ! $initials ) {
                            $initials = mb_strtoupper( mb_substr( $current_user->display_name, 0, 2 ) );
                        }
                        $account_url = function_exists( 'wc_get_account_endpoint_url' )
                            ? wc_get_account_endpoint_url( 'dashboard' )
                            : admin_url( 'profile.php' );
                    ?>
                        <div class="mm-account">
                            <div class="mm-account__user">
                                <span class="mm-account__avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
                                <span class="mm-account__meta">
                                    <span class="mm-account__greeting"><?php esc_html_e( 'Tervetuloa,', 'rytkoset-theme' ); ?></span>
                                    <span class="mm-account__name"><?php echo esc_html( $current_user->display_name ); ?></span>
                                </span>
                            </div>
                            <div class="mm-account__actions">
                                <a href="<?php echo esc_url( $account_url ); ?>" class="mm-btn mm-btn--ghost">
                                    <?php esc_html_e( 'Oma tili', 'rytkoset-theme' ); ?>
                                </a>
                                <a href="<?php echo esc_url( rytkoset_theme_get_logout_url() ); ?>" class="mm-btn mm-btn--ghost">
                                    <?php esc_html_e( 'Kirjaudu ulos', 'rytkoset-theme' ); ?>
                                </a>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="mm-account__actions">
                            <a href="<?php echo esc_url( wp_login_url() ); ?>" class="mm-btn mm-btn--ghost">
                                <?php esc_html_e( 'Kirjaudu', 'rytkoset-theme' ); ?>
                            </a>
                            <?php if ( get_option( 'users_can_register' ) ) : ?>
                                <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="mm-btn mm-btn--primary">
                                    <?php esc_html_e( 'Rekisteröidy', 'rytkoset-theme' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mm-section">
                    <p class="mm-section__title"><?php esc_html_e( 'Asetukset', 'rytkoset-theme' ); ?></p>
                    <button class="theme-toggle mm-theme" type="button" aria-pressed="false">
                        <span class="mm-theme__left">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z" />
                            </svg>
                            <span><?php esc_html_e( 'Tumma tila', 'rytkoset-theme' ); ?></span>
                        </span>
                        <span class="mm-switch" aria-hidden="true">
                            <span class="mm-switch__thumb"></span>
                        </span>
                    </button>
                </div>

                <?php if ( ! empty( $social_links ) ) : ?>
                <div class="mm-section">
                    <p class="mm-section__title"><?php esc_html_e( 'Seuraa meitä', 'rytkoset-theme' ); ?></p>
                    <ul class="mm-social" aria-label="<?php esc_attr_e( 'Sosiaalisen median linkit', 'rytkoset-theme' ); ?>">
                        <?php foreach ( $social_links as $social_link ) :
                            if ( empty( $social_link['icon_src'] ) ) continue; ?>
                            <li>
                                <a class="mm-social__link" href="<?php echo esc_url( $social_link['url'] ); ?>" aria-label="<?php echo esc_attr( $social_link['label'] ); ?>">
                                    <img src="<?php echo esc_url( $social_link['icon_src'] ); ?>" alt="" width="36" height="36" />
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div><!-- .mm-scroll -->

            <div class="mm-footer">
                <a href="mailto:info@rytkoset.net" class="mm-footer__contact">
                    <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="2" y="3.5" width="12" height="9" rx="1.5" />
                        <path d="m2.5 5 5.5 4 5.5-4" />
                    </svg>
                    <span>info@rytkoset.net</span>
                </a>
            </div>

        </nav>
    </div>

</header>



