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
            <?php if ( has_custom_logo() ) : ?>
                <div class="site-logo">
                    <?php the_custom_logo(); ?>
                </div>
            <?php endif; ?>

            <div class="site-title-group">
                <?php if ( is_front_page() && is_home() ) : ?>
                    <h1 class="site-title">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                            <?php bloginfo( 'name' ); ?>
                        </a>
                    </h1>
                <?php else : ?>
                    <p class="site-title">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                            <?php bloginfo( 'name' ); ?>
                        </a>
                    </p>
                <?php endif; ?>

                <?php
                $description = get_bloginfo( 'description', 'display' );
                if ( $description || is_customize_preview() ) :
                    ?>
                    <p class="site-description"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>
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
                    aria-controls="mobile-menu">
                <span class="mobile-menu-toggle__icon" aria-hidden="true"></span>
                <span class="mobile-menu-toggle__label">
                    <?php esc_html_e( 'Valikko', 'rytkoset-theme' ); ?>
                </span>
            </button>

        </div><!-- .site-nav-wrapper -->
    </div><!-- .site-header__inner -->

    <!-- Varsinainen mobiilivalikko (placeholder, logiikka lisätään myöhemmin) -->
    <nav id="mobile-menu"
        class="mobile-menu"
        aria-label="<?php esc_attr_e( 'Mobiilivalikko', 'rytkoset-theme' ); ?>">
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
    </nav>

</header>

<main id="primary" class="site-main">
