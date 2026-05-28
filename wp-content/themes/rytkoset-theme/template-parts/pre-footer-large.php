<?php
/**
 * Pre-footer — large variant (front page).
 *
 * Storytelling newsletter signup block: copy + benefits on the left,
 * AcyMailing form in a card on the right. Hidden entirely when there is
 * no form to show (active subscriber or newsletter not configured).
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
<section class="site-prefooter site-prefooter--large" aria-labelledby="site-prefooter-title">
	<div class="container site-prefooter__inner">
		<div class="site-prefooter__copy">
			<p class="site-prefooter__eyebrow"><?php esc_html_e( 'Pysy mukana', 'rytkoset-theme' ); ?></p>
			<h2 id="site-prefooter-title" class="site-prefooter__title">
				<?php esc_html_e( 'Tilaa Rytkösten uutiskirje', 'rytkoset-theme' ); ?>
			</h2>
			<p class="site-prefooter__desc">
				<?php esc_html_e( 'Tilaa ajankohtaiset uutiset suoraan sähköpostiisi ja pysy kärryillä sukuseuran toiminnasta. Ei mainoksia, ja voit perua tilauksen milloin tahansa.', 'rytkoset-theme' ); ?>
			</p>
			<ul class="site-prefooter__bullets">
				<li><?php esc_html_e( 'Sukukokousten ohjelmat ja ilmoittautumislinkit', 'rytkoset-theme' ); ?></li>
				<li><?php esc_html_e( 'Uudet kuvagalleriat ja sukututkimusartikkelit', 'rytkoset-theme' ); ?></li>
				<li><?php esc_html_e( 'Kaupan uutuudet ja jäsenetuhinnat', 'rytkoset-theme' ); ?></li>
			</ul>
		</div>
		<div class="site-prefooter__form-wrap">
			<div class="site-prefooter__card">
				<div class="site-footer__newsletter-form">
					<?php echo $newsletter_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built by the theme and AcyMailing form renderer. ?>
				</div>
			</div>
		</div>
	</div>
</section>
