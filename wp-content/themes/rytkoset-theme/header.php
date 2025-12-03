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

<header class="site-header">
    <div class="site-header__inner">

        <div class="site-branding">
            <?php
            $home_url      = esc_url( home_url( '/' ) );
            $custom_logo   = '';
            $custom_logo_id = get_theme_mod( 'custom_logo' );

            if ( $custom_logo_id ) {
                $custom_logo = wp_get_attachment_image( $custom_logo_id, 'full', false, array( 'class' => 'custom-logo' ) );
            }
            ?>
            <a class="site-branding__link" href="<?php echo $home_url; ?>">
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

            <!-- Desktop-navigaatio -->
            <nav class="site-nav" aria-label="<?php esc_attr_e( 'Päävalikko', 'rytkoset-theme' ); ?>">
                <?php
                wp_nav_menu(
                    array(
                        'theme_location' => 'primary',
                        'menu_class'     => 'site-nav__list',
                        'container'      => false,
                        'fallback_cb'    => false,
                    )
                );
                ?>
            </nav>

            <!-- Mobiilivalikon placeholder -->
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

        </div><!-- .site-nav-wrapper -->
    </div><!-- .site-header__inner -->

    <!-- Varsinainen mobiilivalikko (placeholder, logiikka lisätään myöhemmin) -->
    <div class="mobile-menu-layer">
        <div class="mobile-menu__overlay" aria-hidden="true" hidden></div>
        <nav id="mobile-menu"
            class="mobile-menu"
            aria-label="<?php esc_attr_e( 'Mobiilivalikko', 'rytkoset-theme' ); ?>"
            aria-hidden="true"
            aria-expanded="false"
            tabindex="-1">
            <button type="button" class="mobile-menu__close">
                <span aria-hidden="true">&#10005;</span>
                <span class="mobile-menu__close-label"><?php esc_html_e( 'Sulje valikko', 'rytkoset-theme' ); ?></span>
            </button>

            <div class="mobile-menu__brand">
                <?php
                rytkoset_theme_the_logo(
                    array(
                        'class'      => 'site-logo site-logo--mobile',
                        'link_class' => 'site-logo__link site-logo__link--mobile',
                    )
                );
                ?>
                <div class="mobile-menu__brand-meta">
                    <span class="mobile-menu__brand-name"><?php bloginfo( 'name' ); ?></span>
                    <?php
                    $description = get_bloginfo( 'description', 'display' );
                    if ( $description || is_customize_preview() ) :
                        ?>
                        <span class="mobile-menu__brand-tagline"><?php echo esc_html( $description ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'primary',
                    'menu_class'     => 'mobile-menu__list',
                    'container'      => false,
                    'fallback_cb'    => false,
                )
            );
            ?>

            <?php if ( is_user_logged_in() || has_nav_menu( 'account' ) ) : ?>
                <div class="mobile-menu__section mobile-menu__account">
                    <p class="mobile-menu__section-title">
                        <?php esc_html_e( 'Tili', 'rytkoset-theme' ); ?>
                    </p>
                    <?php
                    wp_nav_menu(
                        array(
                            'theme_location' => 'account',
                            'menu_class'     => 'mobile-menu__list mobile-account-nav__list',
                            'container'      => false,
                            'fallback_cb'    => is_user_logged_in()
                                ? 'rytkoset_theme_account_menu_logged_in_fallback'
                                : 'rytkoset_theme_account_menu_logged_out_fallback',
                        )
                    );
                    ?>
                </div>
            <?php endif; ?>
        </nav>
    </div>

</header>

<main id="primary" class="site-main">
