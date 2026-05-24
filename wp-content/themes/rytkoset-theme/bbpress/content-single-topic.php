<?php
/**
 * Single topic (thread) page content.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$topic_id   = bbp_get_topic_id();
$forum_id   = bbp_get_topic_forum_id( $topic_id );
$forum_url  = function_exists( 'bbp_get_forum_permalink' ) ? bbp_get_forum_permalink( $forum_id ) : '';
$forum_name = function_exists( 'bbp_get_forum_title' ) ? bbp_get_forum_title( $forum_id ) : '';
$reply_count = bbp_get_topic_reply_count( $topic_id );
$color      = function_exists( 'rytkoset_theme_forum_color' ) ? rytkoset_theme_forum_color( $forum_id ) : 'default';
?>
<div id="bbpress-forums" class="forum-scope forum-page">
<div class="forum-page__inner">

	<?php /* Breadcrumb */ ?>
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Polku', 'rytkoset-theme' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Etusivu', 'rytkoset-theme' ); ?></a>
		<span class="crumbs__sep" aria-hidden="true">›</span>
		<?php if ( function_exists( 'bbp_get_forums_url' ) ) : ?>
			<a href="<?php echo esc_url( bbp_get_forums_url() ); ?>"><?php esc_html_e( 'Foorumit', 'rytkoset-theme' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( home_url( '/forums/' ) ); ?>"><?php esc_html_e( 'Foorumit', 'rytkoset-theme' ); ?></a>
		<?php endif; ?>
		<?php if ( $forum_url && $forum_name ) : ?>
			<span class="crumbs__sep" aria-hidden="true">›</span>
			<a href="<?php echo esc_url( $forum_url ); ?>"><?php echo esc_html( $forum_name ); ?></a>
		<?php endif; ?>
		<span class="crumbs__sep" aria-hidden="true">›</span>
		<span class="crumbs__here"><?php bbp_topic_title(); ?></span>
	</nav>

	<?php /* Thread header */ ?>
	<header class="thread-header">
		<?php if ( $forum_url && $forum_name ) : ?>
			<a href="<?php echo esc_url( $forum_url ); ?>" class="thread-header__category">
				<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
					<path d="M15 18l-6-6 6-6"/>
				</svg>
				<?php echo esc_html( $forum_name ); ?>
			</a>
		<?php endif; ?>

		<h1 class="thread-header__title"><?php bbp_topic_title(); ?></h1>

		<div class="thread-header__meta">
			<span>
				<?php esc_html_e( 'Aloittanut', 'rytkoset-theme' ); ?>
				<strong><?php bbp_topic_author_display_name(); ?></strong>
			</span>
			<span class="thread-header__dot" aria-hidden="true"></span>
			<span>
				<strong><?php echo esc_html( number_format_i18n( (int) $reply_count ) ); ?></strong>
				<?php esc_html_e( 'vastausta', 'rytkoset-theme' ); ?>
			</span>
			<span class="thread-header__dot" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Viimeksi', 'rytkoset-theme' ); ?>
				<strong><?php bbp_topic_last_active_time(); ?></strong>
			</span>
		</div>
	</header>

	<?php /* Original post */ ?>
	<div class="forum-posts">
		<?php bbp_get_template_part( 'loop', 'single-topic' ); ?>

		<?php /* Replies — force post_type=reply only, since we always show the OP separately above */ ?>
		<?php if ( function_exists( 'bbp_has_replies' ) && bbp_has_replies( array( 'post_type' => bbp_get_reply_post_type() ) ) ) : ?>
			<?php bbp_get_template_part( 'loop', 'replies' ); ?>
		<?php endif; ?>
	</div>

	<?php /* Pagination */ ?>
	<?php if ( function_exists( 'bbp_get_template_part' ) ) : ?>
		<?php bbp_get_template_part( 'pagination', 'replies' ); ?>
	<?php endif; ?>

	<?php /* Reply form */ ?>
	<?php if ( function_exists( 'bbp_get_template_part' ) ) : ?>
		<?php bbp_get_template_part( 'form', 'reply' ); ?>
	<?php endif; ?>

	<?php do_action( 'bbp_template_notices' ); ?>

</div><!-- .forum-page__inner -->
</div><!-- #bbpress-forums -->
