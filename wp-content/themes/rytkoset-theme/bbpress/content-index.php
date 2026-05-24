<?php
/**
 * Forum index content — replaces the bbPress default forum listing.
 * Renders the design's category-card layout.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats        = function_exists( 'bbp_get_statistics' ) ? bbp_get_statistics() : array();
$topic_count  = ! empty( $stats['topic_count'] ) ? $stats['topic_count'] : 0;
$reply_count  = ! empty( $stats['reply_count'] ) ? $stats['reply_count'] : 0;
$user_count   = ! empty( $stats['user_count'] ) ? $stats['user_count'] : 0;
$forum_count  = ! empty( $stats['forum_count'] ) ? $stats['forum_count'] : 0;
?>
<div id="bbpress-forums" class="forum-scope forum-page">
<div class="forum-page__inner">

	<?php /* Breadcrumb */ ?>
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Polku', 'rytkoset-theme' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Etusivu', 'rytkoset-theme' ); ?></a>
		<span class="crumbs__sep" aria-hidden="true">›</span>
		<span class="crumbs__here"><?php esc_html_e( 'Foorumit', 'rytkoset-theme' ); ?></span>
	</nav>

	<h1 class="forum-h1"><?php esc_html_e( 'Foorumi', 'rytkoset-theme' ); ?></h1>
	<p class="forum-lede"><?php esc_html_e( 'Sukuun ja sukututkimukseen liittyvät keskustelut. Esittele itsesi, jaa löytöjäsi tai kysy apua suvun jäljittämiseen — kaikki Rytköset ovat tervetulleita.', 'rytkoset-theme' ); ?></p>

	<?php /* Search + CTA toolbar */ ?>
	<div class="forum-toolbar">
		<div class="forum-search">
			<span class="forum-search__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
				</svg>
			</span>
			<?php if ( function_exists( 'bbp_get_search_url' ) ) : ?>
			<form id="bbp-search-form" method="get" action="<?php echo esc_url( bbp_get_search_url() ); ?>">
				<input
					class="forum-search__input"
					type="search"
					name="bbp_search"
					placeholder="<?php esc_attr_e( 'Etsi foorumilta…', 'rytkoset-theme' ); ?>"
					value="<?php echo esc_attr( function_exists( 'bbp_get_search_terms' ) ? bbp_get_search_terms() : '' ); ?>"
				/>
			</form>
			<?php endif; ?>
		</div>
		<div class="forum-actions">
			<?php if ( is_user_logged_in() && function_exists( 'bbp_get_user_subscriptions_permalink' ) ) : ?>
				<a href="<?php echo esc_url( bbp_get_user_subscriptions_permalink( get_current_user_id() ) ); ?>" class="forum-btn forum-btn--outline">
					<?php esc_html_e( 'Omat tilaukset', 'rytkoset-theme' ); ?>
				</a>
			<?php endif; ?>
			<?php
			// Link to the first available forum to start a new topic.
			$first_forum_url = '';
			if ( function_exists( 'bbp_has_forums' ) ) {
				$forums_query = new WP_Query( array(
					'post_type'      => bbp_get_forum_post_type(),
					'posts_per_page' => 1,
					'post_parent'    => 0,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				) );
				if ( $forums_query->have_posts() ) {
					$forums_query->the_post();
					$first_forum_url = function_exists( 'bbp_get_forum_permalink' ) ? bbp_get_forum_permalink( get_the_ID() ) : '';
					wp_reset_postdata();
				}
			}
			if ( is_user_logged_in() && $first_forum_url ) : ?>
				<a href="<?php echo esc_url( $first_forum_url . '#new-topic' ); ?>" class="forum-btn forum-btn--primary">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
					<?php esc_html_e( 'Aloita keskustelu', 'rytkoset-theme' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Stats strip */ ?>
	<?php if ( $topic_count || $reply_count || $user_count ) : ?>
	<div class="forum-stats">
		<div class="forum-stat">
			<div class="forum-stat__value"><?php echo esc_html( number_format_i18n( (int) $topic_count ) ); ?></div>
			<div class="forum-stat__label"><?php esc_html_e( 'Aihetta', 'rytkoset-theme' ); ?></div>
		</div>
		<div class="forum-stat">
			<div class="forum-stat__value"><?php echo esc_html( number_format_i18n( (int) $reply_count ) ); ?></div>
			<div class="forum-stat__label"><?php esc_html_e( 'Viestiä', 'rytkoset-theme' ); ?></div>
		</div>
		<div class="forum-stat">
			<div class="forum-stat__value"><?php echo esc_html( number_format_i18n( (int) $user_count ) ); ?></div>
			<div class="forum-stat__label"><?php esc_html_e( 'Jäsentä', 'rytkoset-theme' ); ?></div>
		</div>
		<div class="forum-stat">
			<div class="forum-stat__value"><?php echo esc_html( number_format_i18n( (int) $forum_count ) ); ?></div>
			<div class="forum-stat__label"><?php esc_html_e( 'Foorumia', 'rytkoset-theme' ); ?></div>
		</div>
	</div>
	<?php endif; ?>

	<?php /* Category list */ ?>
	<div class="cat-section-title">
		<h2><?php esc_html_e( 'Kategoriat', 'rytkoset-theme' ); ?></h2>
		<small><?php esc_html_e( 'Suosituin ensin', 'rytkoset-theme' ); ?></small>
	</div>

	<?php if ( function_exists( 'bbp_has_forums' ) && bbp_has_forums() ) : ?>
		<div class="cat-list">
			<?php bbp_get_template_part( 'loop', 'forums' ); ?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Foorumeja ei löydy.', 'rytkoset-theme' ); ?></p>
	<?php endif; ?>

	<?php /* bbPress notices (login required, etc.) */ ?>
	<?php do_action( 'bbp_template_notices' ); ?>

</div><!-- .forum-page__inner -->
</div><!-- #bbpress-forums -->
