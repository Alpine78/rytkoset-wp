<?php
/**
 * Single forum content (category/topic-list page).
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_forum_id = bbp_get_forum_id();
$rytkoset_color    = function_exists( 'rytkoset_theme_forum_color' ) ? rytkoset_theme_forum_color( $rytkoset_forum_id ) : 'default';
$rytkoset_icon     = function_exists( 'rytkoset_theme_forum_icon' ) ? rytkoset_theme_forum_icon( $rytkoset_forum_id, $rytkoset_color ) : '';
$rytkoset_desc     = wp_strip_all_tags( (string) bbp_get_forum_content( $rytkoset_forum_id ) );
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
		<span class="crumbs__sep" aria-hidden="true">›</span>
		<span class="crumbs__here"><?php bbp_forum_title(); ?></span>
	</nav>

	<?php /* Category hero */ ?>
	<div class="forum-category-head">
		<span class="cat-row__icon forum-category-icon cat-row__icon--<?php echo esc_attr( $rytkoset_color ); ?>">
			<?php echo esc_html( $rytkoset_icon ); ?>
		</span>
		<div>
			<h1 class="forum-h1" style="font-size:36px;margin-bottom:6px;"><?php bbp_forum_title(); ?></h1>
			<?php if ( $rytkoset_desc ) : ?>
				<p class="forum-lede" style="margin-bottom:0;"><?php echo esc_html( $rytkoset_desc ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Search + CTA toolbar */ ?>
	<div class="forum-toolbar" style="margin-top:28px;">
		<div class="forum-search">
			<span class="forum-search__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
				</svg>
			</span>
			<?php if ( function_exists( 'bbp_get_search_url' ) ) : ?>
			<form id="bbp-search-form" method="get" action="<?php echo esc_url( bbp_get_search_url() ); ?>">
				<input type="hidden" name="bbp_forum_id" value="<?php echo esc_attr( $rytkoset_forum_id ); ?>" />
				<input
					class="forum-search__input"
					type="search"
					name="bbp_search"
					placeholder="<?php esc_attr_e( 'Etsi tästä kategoriasta…', 'rytkoset-theme' ); ?>"
					value="<?php echo esc_attr( function_exists( 'bbp_get_search_terms' ) ? bbp_get_search_terms() : '' ); ?>"
				/>
			</form>
			<?php endif; ?>
		</div>
		<div class="forum-actions">
			<?php if ( is_user_logged_in() && function_exists( 'bbp_get_forum_subscribe_link' ) ) : ?>
				<?php
				bbp_forum_subscribe_link(
					array(
						'subscribe'   => __( 'Tilaa', 'rytkoset-theme' ),
						'unsubscribe' => __( 'Peru tilaus', 'rytkoset-theme' ),
						'before'      => '<span class="forum-btn forum-btn--outline">',
						'after'       => '</span>',
					)
				);
				?>
			<?php endif; ?>
			<?php if ( function_exists( 'bbp_current_user_can_access_create_topic_form' ) && bbp_current_user_can_access_create_topic_form() ) : ?>
				<a href="#new-topic" class="forum-btn forum-btn--primary">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
					<?php esc_html_e( 'Uusi aihe', 'rytkoset-theme' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Filter chips — active state derived from current URL */ ?>
	<?php $rytkoset_active_tag = isset( $_GET['topic-tag'] ) ? sanitize_key( $_GET['topic-tag'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="topic-toolbar">
		<div class="filter-chips" role="group" aria-label="<?php esc_attr_e( 'Suodata aiheita', 'rytkoset-theme' ); ?>">
			<a href="<?php bbp_forum_permalink(); ?>" class="filter-chips__btn<?php echo '' === $rytkoset_active_tag ? ' is-active' : ''; ?>" <?php echo '' === $rytkoset_active_tag ? 'aria-current="true"' : ''; ?>><?php esc_html_e( 'Kaikki', 'rytkoset-theme' ); ?></a>
			<?php if ( function_exists( 'bbp_get_forum_permalink' ) ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'topic-tag', 'ratkaistu', bbp_get_forum_permalink() ) ); ?>" class="filter-chips__btn<?php echo 'ratkaistu' === $rytkoset_active_tag ? ' is-active' : ''; ?>" <?php echo 'ratkaistu' === $rytkoset_active_tag ? 'aria-current="true"' : ''; ?>><?php esc_html_e( 'Ratkaistut', 'rytkoset-theme' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( function_exists( 'bbp_forum_topic_count' ) ) : ?>
			<div class="topic-result-count">
				<?php
				$rytkoset_count = bbp_get_forum_topic_count( $rytkoset_forum_id );
				printf(
					/* translators: %s: number of topics */
					esc_html__( '%s aihetta', 'rytkoset-theme' ),
					'<strong>' . esc_html( number_format_i18n( (int) $rytkoset_count ) ) . '</strong>'
				);
				?>
			</div>
		<?php endif; ?>
	</div>

	<?php /* Topic list */ ?>
	<?php if ( function_exists( 'bbp_has_topics' ) && bbp_has_topics() ) : ?>
		<div class="topic-list">
			<?php bbp_get_template_part( 'loop', 'topics' ); ?>
		</div>
		<?php bbp_get_template_part( 'pagination', 'topics' ); ?>
	<?php else : ?>
		<div class="bbp-template-notice">
			<?php esc_html_e( 'Tässä kategoriassa ei ole vielä yhtään aihetta.', 'rytkoset-theme' ); ?>
		</div>
	<?php endif; ?>

	<?php /* New topic form */ ?>
	<?php if ( function_exists( 'bbp_get_template_part' ) ) : ?>
		<?php bbp_get_template_part( 'form', 'topic' ); ?>
	<?php endif; ?>

	<?php do_action( 'bbp_template_notices' ); ?>

</div><!-- .forum-page__inner -->
</div><!-- #bbpress-forums -->
