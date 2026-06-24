<?php
/**
 * Locked members-only page notice (#392).
 *
 * Shown in place of a restricted page's content to logged-out visitors and non-members.
 * The page title is rendered separately by the template, so only the body is replaced.
 *
 * Expected args:
 * - post_id: Current page post ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_locked_post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : get_the_ID();
$rytkoset_membership_url = home_url( '/sukuseura/jasenyys' );
$rytkoset_locked_login   = wp_login_url( get_permalink( $rytkoset_locked_post_id ) );

if ( is_user_logged_in() ) {
	// Logged in but not an active member: point straight to membership.
	$rytkoset_locked_message  = __( 'Tämä sisältö on tarkoitettu seuran jäsenille. Tutustu jäsenyyteen, niin saat sisällön luettavaksi.', 'rytkoset-theme' );
	$rytkoset_primary_url     = $rytkoset_membership_url;
	$rytkoset_primary_label   = __( 'Tutustu jäsenyyteen', 'rytkoset-theme' );
	$rytkoset_secondary_url   = '';
	$rytkoset_secondary_label = '';
} else {
	// Logged out: log in first, membership info as secondary action.
	$rytkoset_locked_message  = __( 'Aktiiviset jäsenet voivat lukea tämän sisällön kirjautumisen jälkeen. Jos et ole vielä jäsen, voit tutustua jäsenyyteen.', 'rytkoset-theme' );
	$rytkoset_primary_url     = $rytkoset_locked_login;
	$rytkoset_primary_label   = __( 'Kirjaudu sisään', 'rytkoset-theme' );
	$rytkoset_secondary_url   = $rytkoset_membership_url;
	$rytkoset_secondary_label = __( 'Tutustu jäsenyyteen', 'rytkoset-theme' );
}
?>
<section class="members-only-lock" aria-labelledby="members-only-lock-heading">
	<p class="members-only-lock__eyebrow">
		<?php esc_html_e( 'Vain jäsenille', 'rytkoset-theme' ); ?>
	</p>
	<h2 id="members-only-lock-heading" class="members-only-lock__title">
		<?php esc_html_e( 'Sisältö on jäsenille', 'rytkoset-theme' ); ?>
	</h2>
	<p class="members-only-lock__text">
		<?php echo esc_html( $rytkoset_locked_message ); ?>
	</p>
	<div class="members-only-lock__actions">
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
