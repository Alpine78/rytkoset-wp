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

	$reply_id    = bbp_get_reply_id();
	$author_name = bbp_get_reply_author_display_name( $reply_id );
	$author_id   = (int) get_post_field( 'post_author', $reply_id );
	$is_admin    = $author_id && user_can( $author_id, 'moderate' );
	$post_date   = get_the_date( 'd.m.Y', $reply_id );
	$post_time   = get_the_time( 'H:i', $reply_id );
	?>
	<article id="post-<?php echo esc_attr( $reply_id ); ?>" class="forum-post">

		<div class="forum-post__side">
			<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $author_name, 'xl' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="forum-post__author-name"><?php echo esc_html( $author_name ); ?></div>
			<div class="forum-post__author-role">
				<?php echo $is_admin ? esc_html__( 'Ylläpitäjä', 'rytkoset-theme' ) : esc_html__( 'Osallistuja', 'rytkoset-theme' ); ?>
			</div>
			<?php if ( $is_admin ) : ?>
				<div class="forum-post__author-badge forum-post__author-badge--admin">
					<?php esc_html_e( 'Admin', 'rytkoset-theme' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="forum-post__body">
			<div class="forum-post__head">
				<div class="forum-post__date">
					<strong><?php echo esc_html( $post_date ); ?></strong>
					<?php echo esc_html( 'klo ' . $post_time ); ?>
				</div>
				<a href="<?php bbp_reply_permalink(); ?>" class="forum-post__permalink">
					#<?php echo esc_html( $reply_id ); ?>
				</a>
			</div>
			<div class="forum-post__content">
				<?php bbp_reply_content(); ?>
			</div>
		</div>

	</article>
	<?php
endwhile;
