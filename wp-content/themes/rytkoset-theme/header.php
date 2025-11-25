<?php
/**
 * Header template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header class="site-header">
	<div class="site-header-inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<div class="site-logo">
					<?php the_custom_logo(); ?>
				</div>
			<?php endif; ?>

			<div>
				<p class="site-title">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php bloginfo( 'name' ); ?>
					</a>
				</p>
				<p class="site-description">
					<?php bloginfo( 'description' ); ?>
				</p>
			</div>
		</div>

		<button class="menu-toggle" aria-expanded="false" aria-controls="primary-menu">
			<span>Valikko</span>
			<span aria-hidden="true">☰</span>
		</button>

		<nav class="main-navigation" id="primary-menu" aria-label="<?php esc_attr_e( 'Päävalikko', 'rytkoset-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => '',
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>
</header>

<main class="site-main">
	<div class="site-container">
