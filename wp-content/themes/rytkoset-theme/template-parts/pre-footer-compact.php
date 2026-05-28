<?php
/**
 * Pre-footer — compact variant (sub pages).
 *
 * Single-row newsletter reminder: short copy on the left, inline AcyMailing
 * form on the right. Hidden entirely when there is no form to show
 * (active subscriber or newsletter not configured).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$newsletter_form = function_exists( 'rytkoset_theme_get_footer_newsletter_form' )
	? rytkoset_theme_get_footer_newsletter_form()
	: '';

if ( '' === $newsletter_form ) {
	return;
}
?>
<section class="site-prefooter site-prefooter--compact" aria-labelledby="site-prefooter-title">
	<div class="container site-prefooter__row">
		<div class="site-prefooter__copy">
			<h2 id="site-prefooter-title" class="site-prefooter__title site-prefooter__title--sm">
				<?php esc_html_e( 'Tilaa uutiskirje', 'rytkoset-theme' ); ?>
			</h2>
			<p class="site-prefooter__desc site-prefooter__desc--sm">
				<?php esc_html_e( 'Sukuseuran ajankohtaiset uutiset ja tapahtumat sähköpostiisi.', 'rytkoset-theme' ); ?>
			</p>
		</div>
		<div class="site-prefooter__form-wrap">
			<div class="site-footer__newsletter-form site-footer__newsletter-form--inline">
				<?php echo $newsletter_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built by the theme and AcyMailing form renderer. ?>
			</div>
		</div>
	</div>
</section>
