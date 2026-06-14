<?php
/**
 * Renders the original post (OP) in a topic thread.
 *
 * Uses the current topic context directly — do NOT use bbp_has_topics() here
 * because on a single-topic page it queries ALL topics in the parent forum and
 * would render every one as an OP card.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_topic_id = bbp_get_topic_id();

if ( ! $rytkoset_topic_id ) {
	return;
}

$rytkoset_author_name = bbp_get_topic_author_display_name( $rytkoset_topic_id );
$rytkoset_author_id   = (int) get_post_field( 'post_author', $rytkoset_topic_id );
$rytkoset_is_admin    = $rytkoset_author_id && user_can( $rytkoset_author_id, 'moderate' );
$rytkoset_post_date   = get_the_date( 'd.m.Y', $rytkoset_topic_id );
$rytkoset_post_time   = get_the_time( 'H:i', $rytkoset_topic_id );
?>
<article id="post-<?php echo esc_attr( $rytkoset_topic_id ); ?>" class="forum-post forum-post--op">

	<div class="forum-post__side">
		<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $rytkoset_author_name, 'xl' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="forum-post__author-name"><?php echo esc_html( $rytkoset_author_name ); ?></div>
		<div class="forum-post__author-role">
			<?php echo $rytkoset_is_admin ? esc_html__( 'Ylläpitäjä', 'rytkoset-theme' ) : esc_html__( 'Osallistuja', 'rytkoset-theme' ); ?>
		</div>
		<div class="forum-post__author-badge <?php echo $rytkoset_is_admin ? 'forum-post__author-badge--admin' : ''; ?>">
			<?php echo $rytkoset_is_admin ? esc_html__( 'Admin', 'rytkoset-theme' ) : esc_html__( 'Aloittaja', 'rytkoset-theme' ); ?>
		</div>
	</div>

	<div class="forum-post__body">
		<div class="forum-post__head">
			<div class="forum-post__date">
				<strong><?php echo esc_html( $rytkoset_post_date ); ?></strong>
				<?php echo esc_html( 'klo ' . $rytkoset_post_time ); ?>
			</div>
			<a href="<?php bbp_topic_permalink(); ?>" class="forum-post__permalink">
				#<?php echo esc_html( $rytkoset_topic_id ); ?>
			</a>
		</div>
		<div class="forum-post__content">
			<?php bbp_topic_content(); ?>
		</div>
	</div>

</article>
