<?php
/**
 * Reply loop — renders each reply as a .forum-post card.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( bbp_replies() ) :
	bbp_the_reply();

	$rytkoset_reply_id    = bbp_get_reply_id();
	$rytkoset_author_name = bbp_get_reply_author_display_name( $rytkoset_reply_id );
	$rytkoset_author_id   = (int) get_post_field( 'post_author', $rytkoset_reply_id );
	$rytkoset_is_admin    = $rytkoset_author_id && user_can( $rytkoset_author_id, 'moderate' );
	$rytkoset_post_date   = get_the_date( 'd.m.Y', $rytkoset_reply_id );
	$rytkoset_post_time   = get_the_time( 'H:i', $rytkoset_reply_id );
	?>
	<article id="post-<?php echo esc_attr( $rytkoset_reply_id ); ?>" class="forum-post">

		<div class="forum-post__side">
			<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $rytkoset_author_name, 'xl' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="forum-post__author-name"><?php echo esc_html( $rytkoset_author_name ); ?></div>
			<div class="forum-post__author-role">
				<?php echo $rytkoset_is_admin ? esc_html__( 'Ylläpitäjä', 'rytkoset-theme' ) : esc_html__( 'Osallistuja', 'rytkoset-theme' ); ?>
			</div>
			<?php if ( $rytkoset_is_admin ) : ?>
				<div class="forum-post__author-badge forum-post__author-badge--admin">
					<?php esc_html_e( 'Admin', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="forum-post__body">
			<div class="forum-post__head">
				<div class="forum-post__date">
					<strong><?php echo esc_html( $rytkoset_post_date ); ?></strong>
					<?php echo esc_html( 'klo ' . $rytkoset_post_time ); ?>
				</div>
				<a href="<?php bbp_reply_permalink(); ?>" class="forum-post__permalink">
					#<?php echo esc_html( $rytkoset_reply_id ); ?>
				</a>
			</div>
			<div class="forum-post__content">
				<?php bbp_reply_content(); ?>
			</div>
		</div>

	</article>
	<?php
endwhile;
