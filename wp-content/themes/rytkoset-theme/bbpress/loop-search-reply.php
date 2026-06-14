<?php
/**
 * Search result — single reply.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_reply_id  = bbp_get_reply_id();
$rytkoset_topic_id  = bbp_get_reply_topic_id( $rytkoset_reply_id );
$rytkoset_forum_id  = bbp_get_topic_forum_id( $rytkoset_topic_id );
$rytkoset_author    = bbp_get_reply_author_display_name( $rytkoset_reply_id );
$rytkoset_post_date = get_the_date( 'd.m.Y', $rytkoset_reply_id );
$rytkoset_post_time = get_the_time( 'H:i', $rytkoset_reply_id );
$rytkoset_excerpt   = wp_trim_words( wp_strip_all_tags( bbp_get_reply_content( $rytkoset_reply_id ) ), 28, '…' );
?>
<article class="search-result" id="search-reply-<?php echo esc_attr( $rytkoset_reply_id ); ?>">

	<div class="search-result__head">
		<div class="search-result__type-date">
			<span class="search-result__badge search-result__badge--reply"><?php esc_html_e( 'Vastaus', 'rytkoset-theme' ); ?></span>
			<span class="search-result__date"><?php echo esc_html( $rytkoset_post_date . ' klo ' . $rytkoset_post_time ); ?></span>
		</div>
		<a href="<?php bbp_reply_url(); ?>" class="search-result__permalink">#<?php echo esc_html( $rytkoset_reply_id ); ?></a>
	</div>

	<h3 class="search-result__title search-result__title--reply">
		<?php esc_html_e( 'Vastaus aiheeseen:', 'rytkoset-theme' ); ?>
		<a href="<?php bbp_topic_permalink( $rytkoset_topic_id ); ?>"><?php bbp_topic_title( $rytkoset_topic_id ); ?></a>
	</h3>

	<?php if ( $rytkoset_forum_id ) : ?>
		<div class="search-result__context">
			<?php esc_html_e( 'foorumissa', 'rytkoset-theme' ); ?>
			<a href="<?php bbp_forum_permalink( $rytkoset_forum_id ); ?>"><?php bbp_forum_title( $rytkoset_forum_id ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( $rytkoset_author || $rytkoset_excerpt ) : ?>
		<div class="search-result__preview">
			<?php if ( $rytkoset_author ) : ?>
				<div class="search-result__author">
					<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $rytkoset_author, 'sm' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="search-result__author-name"><?php echo esc_html( $rytkoset_author ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $rytkoset_excerpt ) : ?>
				<p class="search-result__excerpt"><?php echo esc_html( $rytkoset_excerpt ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

</article>
