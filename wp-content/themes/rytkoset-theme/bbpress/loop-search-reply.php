<?php
/**
 * Search result — single reply.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reply_id   = bbp_get_reply_id();
$topic_id   = bbp_get_reply_topic_id( $reply_id );
$forum_id   = bbp_get_topic_forum_id( $topic_id );
$author     = bbp_get_reply_author_display_name( $reply_id );
$post_date  = get_the_date( 'd.m.Y', $reply_id );
$post_time  = get_the_time( 'H:i', $reply_id );
$excerpt    = wp_trim_words( wp_strip_all_tags( bbp_get_reply_content( $reply_id ) ), 28, '…' );
?>
<article class="search-result" id="search-reply-<?php echo esc_attr( $reply_id ); ?>">

	<div class="search-result__head">
		<div class="search-result__type-date">
			<span class="search-result__badge search-result__badge--reply"><?php esc_html_e( 'Vastaus', 'rytkoset-theme' ); ?></span>
			<span class="search-result__date"><?php echo esc_html( $post_date . ' klo ' . $post_time ); ?></span>
		</div>
		<a href="<?php bbp_reply_url(); ?>" class="search-result__permalink">#<?php echo esc_html( $reply_id ); ?></a>
	</div>

	<h3 class="search-result__title search-result__title--reply">
		<?php esc_html_e( 'Vastaus aiheeseen:', 'rytkoset-theme' ); ?>
		<a href="<?php bbp_topic_permalink( $topic_id ); ?>"><?php bbp_topic_title( $topic_id ); ?></a>
	</h3>

	<?php if ( $forum_id ) : ?>
		<div class="search-result__context">
			<?php esc_html_e( 'foorumissa', 'rytkoset-theme' ); ?>
			<a href="<?php bbp_forum_permalink( $forum_id ); ?>"><?php bbp_forum_title( $forum_id ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( $author || $excerpt ) : ?>
		<div class="search-result__preview">
			<?php if ( $author ) : ?>
				<div class="search-result__author">
					<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $author, 'sm' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="search-result__author-name"><?php echo esc_html( $author ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $excerpt ) : ?>
				<p class="search-result__excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

</article>
