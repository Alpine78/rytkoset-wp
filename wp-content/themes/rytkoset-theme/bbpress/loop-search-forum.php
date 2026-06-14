<?php
/**
 * Search result — single forum.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_forum_id = bbp_get_forum_id();
$rytkoset_color    = function_exists( 'rytkoset_theme_forum_color' ) ? rytkoset_theme_forum_color( $rytkoset_forum_id ) : 'default';
$rytkoset_icon     = function_exists( 'rytkoset_theme_forum_icon' ) ? rytkoset_theme_forum_icon( $rytkoset_forum_id, $rytkoset_color ) : '';
$rytkoset_desc     = wp_trim_words( wp_strip_all_tags( (string) bbp_get_forum_content( $rytkoset_forum_id ) ), 20, '…' );
?>
<article class="search-result search-result--forum" id="search-forum-<?php echo esc_attr( $rytkoset_forum_id ); ?>">

	<div class="search-result__head">
		<div class="search-result__type-date">
			<span class="search-result__badge search-result__badge--forum"><?php esc_html_e( 'Foorumi', 'rytkoset-theme' ); ?></span>
		</div>
	</div>

	<h3 class="search-result__title search-result__title--forum">
		<?php if ( $rytkoset_icon ) : ?>
			<span class="cat-row__icon cat-row__icon--<?php echo esc_attr( $rytkoset_color ); ?> search-result__forum-icon"><?php echo esc_html( $rytkoset_icon ); ?></span>
		<?php endif; ?>
		<a href="<?php bbp_forum_permalink(); ?>"><?php bbp_forum_title(); ?></a>
	</h3>

	<?php if ( $rytkoset_desc ) : ?>
		<p class="search-result__excerpt" style="margin-top: 6px;"><?php echo esc_html( $rytkoset_desc ); ?></p>
	<?php endif; ?>

</article>
