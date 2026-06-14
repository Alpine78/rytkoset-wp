<?php
/**
 * Search results page.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_search_terms = function_exists( 'bbp_get_search_terms' ) ? bbp_get_search_terms() : '';
?>
<div id="bbpress-forums" class="forum-scope forum-page">
<div class="forum-page__inner">

	<?php /* Breadcrumb */ ?>
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Polku', 'rytkoset-theme' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Etusivu', 'rytkoset-theme' ); ?></a>
		<span class="crumbs__sep" aria-hidden="true">›</span>
		<?php if ( function_exists( 'bbp_get_forums_url' ) ) : ?>
			<a href="<?php echo esc_url( bbp_get_forums_url() ); ?>"><?php esc_html_e( 'Foorumit', 'rytkoset-theme' ); ?></a>
		<?php endif; ?>
		<span class="crumbs__sep" aria-hidden="true">›</span>
		<span class="crumbs__here"><?php esc_html_e( 'Haku', 'rytkoset-theme' ); ?></span>
	</nav>

	<h1 class="forum-h1">
		<?php if ( $rytkoset_search_terms ) : ?>
			<?php /* translators: %s: forum search term. */ ?>
			<?php printf( esc_html__( 'Haku: &ldquo;%s&rdquo;', 'rytkoset-theme' ), esc_html( $rytkoset_search_terms ) ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Haku', 'rytkoset-theme' ); ?>
		<?php endif; ?>
	</h1>

	<?php /* Search form */ ?>
	<div class="forum-toolbar" style="margin-bottom: 28px;">
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
					value="<?php echo esc_attr( $rytkoset_search_terms ); ?>"
				/>
			</form>
			<?php endif; ?>
		</div>
		<div class="forum-actions">
			<?php if ( function_exists( 'bbp_get_forums_url' ) ) : ?>
				<a href="<?php echo esc_url( bbp_get_forums_url() ); ?>" class="forum-btn forum-btn--outline">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
					<?php esc_html_e( 'Kaikki foorumit', 'rytkoset-theme' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php bbp_set_query_name( bbp_get_search_rewrite_id() ); ?>

	<?php do_action( 'bbp_template_before_search' ); ?>

	<?php if ( function_exists( 'bbp_has_search_results' ) && bbp_has_search_results() ) : ?>

		<?php bbp_get_template_part( 'pagination', 'search' ); ?>
		<?php bbp_get_template_part( 'loop', 'search' ); ?>
		<?php bbp_get_template_part( 'pagination', 'search' ); ?>

	<?php else : ?>

		<div class="bbp-template-notice">
			<p>
				<?php if ( $rytkoset_search_terms ) : ?>
					<?php /* translators: %s: forum search term. */ ?>
					<?php printf( esc_html__( 'Haulla &ldquo;%s&rdquo; ei löytynyt tuloksia.', 'rytkoset-theme' ), esc_html( $rytkoset_search_terms ) ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Kirjoita hakusana yllä olevaan kenttään.', 'rytkoset-theme' ); ?>
				<?php endif; ?>
			</p>
		</div>

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_search_results' ); ?>

</div><!-- .forum-page__inner -->
</div><!-- #bbpress-forums -->
