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

$topic_id = bbp_get_topic_id();

if ( ! $topic_id ) {
	return;
}

$author_name = bbp_get_topic_author_display_name( $topic_id );
$author_id   = (int) get_post_field( 'post_author', $topic_id );
$is_admin    = $author_id && user_can( $author_id, 'moderate' );
$post_date   = get_the_date( 'd.m.Y', $topic_id );
$post_time   = get_the_time( 'H:i', $topic_id );
?>
<article id="post-<?php echo esc_attr( $topic_id ); ?>" class="forum-post forum-post--op">

	<div class="forum-post__side">
		<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $author_name, 'xl' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="forum-post__author-name"><?php echo esc_html( $author_name ); ?></div>
		<div class="forum-post__author-role">
			<?php echo $is_admin ? esc_html__( 'Ylläpitäjä', 'rytkoset-theme' ) : esc_html__( 'Osallistuja', 'rytkoset-theme' ); ?>
		</div>
		<div class="forum-post__author-badge <?php echo $is_admin ? 'forum-post__author-badge--admin' : ''; ?>">
			<?php echo $is_admin ? esc_html__( 'Admin', 'rytkoset-theme' ) : esc_html__( 'Aloittaja', 'rytkoset-theme' ); ?>
		</div>
	</div>

	<div class="forum-post__body">
		<div class="forum-post__head">
			<div class="forum-post__date">
				<strong><?php echo esc_html( $post_date ); ?></strong>
				<?php echo esc_html( 'klo ' . $post_time ); ?>
			</div>
			<a href="<?php bbp_topic_permalink(); ?>" class="forum-post__permalink">
				#<?php echo esc_html( $topic_id ); ?>
			</a>
		</div>
		<div class="forum-post__content">
			<?php bbp_topic_content(); ?>
		</div>
	</div>

</article>
