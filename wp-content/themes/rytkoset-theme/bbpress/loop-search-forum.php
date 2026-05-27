<?php
/**
 * Search result — single forum.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forum_id = bbp_get_forum_id();
$color    = function_exists( 'rytkoset_theme_forum_color' ) ? rytkoset_theme_forum_color( $forum_id ) : 'default';
$icon     = function_exists( 'rytkoset_theme_forum_icon' ) ? rytkoset_theme_forum_icon( $forum_id, $color ) : '';
$desc     = wp_trim_words( wp_strip_all_tags( (string) bbp_get_forum_content( $forum_id ) ), 20, '…' );
?>
<article class="search-result search-result--forum" id="search-forum-<?php echo esc_attr( $forum_id ); ?>">

	<div class="search-result__head">
		<div class="search-result__type-date">
			<span class="search-result__badge search-result__badge--forum"><?php esc_html_e( 'Foorumi', 'rytkoset-theme' ); ?></span>
		</div>
	</div>

	<h3 class="search-result__title search-result__title--forum">
		<?php if ( $icon ) : ?>
			<span class="cat-row__icon cat-row__icon--<?php echo esc_attr( $color ); ?> search-result__forum-icon"><?php echo esc_html( $icon ); ?></span>
		<?php endif; ?>
		<a href="<?php bbp_forum_permalink(); ?>"><?php bbp_forum_title(); ?></a>
	</h3>

	<?php if ( $desc ) : ?>
		<p class="search-result__excerpt" style="margin-top: 6px;"><?php echo esc_html( $desc ); ?></p>
	<?php endif; ?>

</article>
