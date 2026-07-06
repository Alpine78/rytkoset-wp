<?php
/**
 * Oma tili -sivupalkin tilivalikko (teeman override, #496).
 *
 * Perustuu WooCommercen myaccount/navigation.php-templateen (@version 9.3.0).
 * Lisäykset oletukseen: käyttäjäkortti (avatar-nimikirjaimet + näyttönimi +
 * sähköposti) valikon yläpuolella sekä endpoint-kohtaiset SVG-ikonit
 * (inc/woocommerce-my-account.php + inc/icons.php). Ulkoasu: account.css.
 *
 * @package Rytkoset_Theme
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_navigation' );

$rytkoset_account_user = wp_get_current_user();
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_attr_e( 'Oma tili', 'rytkoset-theme' ); ?>">
	<?php if ( $rytkoset_account_user->exists() ) : ?>
		<?php $rytkoset_avatar_url = rytkoset_theme_get_account_avatar_image_url( $rytkoset_account_user ); ?>
		<div class="rytkoset-account-nav__user">
			<span class="rytkoset-account-nav__avatar" aria-hidden="true">
				<?php if ( '' !== $rytkoset_avatar_url ) : ?>
					<img class="rytkoset-account-nav__avatar-img" src="<?php echo esc_url( $rytkoset_avatar_url ); ?>" alt="" loading="lazy" width="40" height="40" />
				<?php else : ?>
					<?php echo esc_html( rytkoset_theme_get_account_avatar_initials( $rytkoset_account_user->display_name ) ); ?>
				<?php endif; ?>
			</span>
			<span class="rytkoset-account-nav__id">
				<span class="rytkoset-account-nav__name"><?php echo esc_html( $rytkoset_account_user->display_name ); ?></span>
				<span class="rytkoset-account-nav__email" title="<?php echo esc_attr( $rytkoset_account_user->user_email ); ?>"><?php echo esc_html( $rytkoset_account_user->user_email ); ?></span>
			</span>
		</div>
	<?php endif; ?>
	<ul>
		<?php foreach ( wc_get_account_menu_items() as $rytkoset_endpoint => $rytkoset_label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $rytkoset_endpoint ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $rytkoset_endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $rytkoset_endpoint ) ? 'aria-current="page"' : ''; ?>>
					<?php echo rytkoset_theme_inline_icon( rytkoset_theme_get_account_menu_item_icon( $rytkoset_endpoint ), 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta (inc/icons.php). ?>
					<?php echo esc_html( $rytkoset_label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
