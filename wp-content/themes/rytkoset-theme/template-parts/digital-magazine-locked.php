<?php
/**
 * Locked digital magazine notice.
 *
 * Expected args:
 * - post_id: Current magazine or article post ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_locked_post_id  = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : get_the_ID();
$rytkoset_locked_mode     = rytkoset_theme_get_digital_magazine_access_mode( $rytkoset_locked_post_id );
$rytkoset_locked_login    = wp_login_url( get_permalink( $rytkoset_locked_post_id ) );
$rytkoset_membership_url  = home_url( '/sukuseura/jasenyys' );
$rytkoset_locked_heading  = __( 'Tämä digilehti on rajattu', 'rytkoset-theme' );
$rytkoset_locked_message  = __( 'Kirjaudu sisään, jotta voimme tarkistaa lukuoikeutesi.', 'rytkoset-theme' );
$rytkoset_primary_url     = $rytkoset_locked_login;
$rytkoset_primary_label   = __( 'Kirjaudu sisään', 'rytkoset-theme' );
$rytkoset_secondary_url   = '';
$rytkoset_secondary_label = '';

if ( 'members_only' === $rytkoset_locked_mode ) {
	$rytkoset_locked_heading = __( 'Digilehti on jäsenille', 'rytkoset-theme' );

	if ( is_user_logged_in() ) {
		// Already logged in but not an active member: point straight to membership.
		$rytkoset_locked_message  = __(
			'Tämä digilehti on tarkoitettu seuran jäsenille. Tutustu jäsenyyteen, niin saat lehden luettavaksi.',
			'rytkoset-theme'
		);
		$rytkoset_primary_url     = $rytkoset_membership_url;
		$rytkoset_primary_label   = __( 'Tutustu jäsenyyteen', 'rytkoset-theme' );
		$rytkoset_secondary_url   = '';
		$rytkoset_secondary_label = '';
	} else {
		// Logged out: log in first, membership info as secondary action.
		$rytkoset_locked_message  = __(
			'Aktiiviset jäsenet voivat lukea tämän digilehden kirjautumisen jälkeen. Jos et ole jäsen, voit tutustua jäsenyyteen.',
			'rytkoset-theme'
		);
		$rytkoset_secondary_url   = $rytkoset_membership_url;
		$rytkoset_secondary_label = __( 'Tutustu jäsenyyteen', 'rytkoset-theme' );
	}
} elseif ( 'member_and_regular' === $rytkoset_locked_mode ) {
	$rytkoset_locked_heading = __( 'Digilehti vaatii oston', 'rytkoset-theme' );
	$rytkoset_purchase_cta   = function_exists( 'rytkoset_theme_get_digital_magazine_purchase_cta' )
		? rytkoset_theme_get_digital_magazine_purchase_cta( $rytkoset_locked_post_id )
		: array();

	if ( ! empty( $rytkoset_purchase_cta['url'] ) ) {
		$rytkoset_primary_url   = $rytkoset_purchase_cta['url'];
		$rytkoset_primary_label = $rytkoset_purchase_cta['label'];

		if ( is_user_logged_in() ) {
			$rytkoset_locked_message  = __( 'Tämä digilehti on maksullinen. Aktiivisena jäsenenä saat sen jäsenhintaan.', 'rytkoset-theme' );
			$rytkoset_secondary_url   = $rytkoset_membership_url;
			$rytkoset_secondary_label = __( 'Tutustu jäsenyyteen', 'rytkoset-theme' );
		} else {
			$rytkoset_locked_message  = __( 'Tämä digilehti on maksullinen. Aktiiviset jäsenet saavat sen jäsenhintaan — kirjaudu ensin saadaksesi jäsenhinnan.', 'rytkoset-theme' );
			$rytkoset_secondary_url   = $rytkoset_locked_login;
			$rytkoset_secondary_label = __( 'Kirjaudu (jäsenhinta)', 'rytkoset-theme' );
		}
	} else {
		$rytkoset_locked_message  = __( 'Tämä digilehti on maksullinen. Ostolinkki ei ole vielä saatavilla.', 'rytkoset-theme' );
		$rytkoset_secondary_url   = $rytkoset_membership_url;
		$rytkoset_secondary_label = __( 'Tutustu jäsenyyteen', 'rytkoset-theme' );
	}
} elseif ( 'paid' === $rytkoset_locked_mode ) {
	$rytkoset_locked_heading = __( 'Digilehti vaatii oston', 'rytkoset-theme' );
	$rytkoset_purchase_cta   = function_exists( 'rytkoset_theme_get_digital_magazine_purchase_cta' )
		? rytkoset_theme_get_digital_magazine_purchase_cta( $rytkoset_locked_post_id )
		: array();

	if ( ! empty( $rytkoset_purchase_cta['url'] ) ) {
		$rytkoset_locked_message = __( 'Tämä digilehti on maksullinen. Osta lukuoikeus jatkaaksesi.', 'rytkoset-theme' );
		$rytkoset_primary_url    = $rytkoset_purchase_cta['url'];
		$rytkoset_primary_label  = $rytkoset_purchase_cta['label'];
	} else {
		$rytkoset_locked_message = __( 'Tämä digilehti on maksullinen. Ostolinkki ei ole vielä saatavilla.', 'rytkoset-theme' );
	}
}
?>
<section class="digital-magazine-lock" aria-labelledby="digital-magazine-lock-heading">
	<p class="digital-magazine-lock__eyebrow">
		<?php echo esc_html( rytkoset_theme_get_digital_magazine_access_mode_label( $rytkoset_locked_mode ) ); ?>
	</p>
	<h2 id="digital-magazine-lock-heading" class="digital-magazine-lock__title">
		<?php echo esc_html( $rytkoset_locked_heading ); ?>
	</h2>
	<p class="digital-magazine-lock__text">
		<?php echo esc_html( $rytkoset_locked_message ); ?>
	</p>
	<div class="digital-magazine-lock__actions">
		<a class="btn btn--primary" href="<?php echo esc_url( $rytkoset_primary_url ); ?>">
			<?php echo esc_html( $rytkoset_primary_label ); ?>
		</a>
		<?php if ( '' !== $rytkoset_secondary_url && '' !== $rytkoset_secondary_label ) : ?>
			<a class="btn btn--light" href="<?php echo esc_url( $rytkoset_secondary_url ); ?>">
				<?php echo esc_html( $rytkoset_secondary_label ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
