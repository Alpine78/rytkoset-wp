<?php
/**
 * Forum loop — renders each forum as a category card (.cat-row).
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( bbp_forums() ) :
	bbp_the_forum();

	$rytkoset_forum_id = bbp_get_forum_id();
	$rytkoset_color    = function_exists( 'rytkoset_theme_forum_color' ) ? rytkoset_theme_forum_color( $rytkoset_forum_id ) : 'default';
	$rytkoset_icon     = function_exists( 'rytkoset_theme_forum_icon' ) ? rytkoset_theme_forum_icon( $rytkoset_forum_id, $rytkoset_color ) : '';
	$rytkoset_desc     = wp_strip_all_tags( (string) bbp_get_forum_content( $rytkoset_forum_id ) );

	// Last active post info.
	$rytkoset_last_active_id = function_exists( 'bbp_get_forum_last_active_id' ) ? (int) bbp_get_forum_last_active_id( $rytkoset_forum_id ) : 0;
	$rytkoset_last_name      = '';
	$rytkoset_last_title     = function_exists( 'bbp_get_forum_last_topic_title' ) ? bbp_get_forum_last_topic_title( $rytkoset_forum_id ) : '';
	if ( $rytkoset_last_active_id ) {
		$rytkoset_last_name = function_exists( 'rytkoset_theme_bbp_author_name' ) ? rytkoset_theme_bbp_author_name( $rytkoset_last_active_id ) : '';
	}
	?>
	<a href="<?php bbp_forum_permalink(); ?>" class="cat-row">

		<span class="cat-row__icon cat-row__icon--<?php echo esc_attr( $rytkoset_color ); ?>">
			<?php echo esc_html( $rytkoset_icon ); ?>
		</span>

		<div class="cat-row__main">
			<h3 class="cat-row__title"><?php bbp_forum_title(); ?></h3>
			<?php if ( $rytkoset_desc ) : ?>
				<p class="cat-row__desc"><?php echo esc_html( $rytkoset_desc ); ?></p>
			<?php endif; ?>
		</div>

		<div class="cat-row__num">
			<div class="cat-row__num-value"><?php bbp_forum_topic_count(); ?></div>
			<div class="cat-row__num-label"><?php esc_html_e( 'Aihetta', 'rytkoset-theme' ); ?></div>
		</div>

		<div class="cat-row__num">
			<div class="cat-row__num-value"><?php bbp_forum_reply_count(); ?></div>
			<div class="cat-row__num-label"><?php esc_html_e( 'Viestiä', 'rytkoset-theme' ); ?></div>
		</div>

		<div class="cat-row__latest">
			<?php if ( $rytkoset_last_name ) : ?>
				<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $rytkoset_last_name ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="cat-row__latest-text">
					<?php if ( $rytkoset_last_title ) : ?>
						<div class="cat-row__latest-topic"><?php echo esc_html( $rytkoset_last_title ); ?></div>
					<?php endif; ?>
					<div class="cat-row__latest-meta">
						<strong><?php echo esc_html( $rytkoset_last_name ); ?></strong>
						<?php if ( function_exists( 'bbp_forum_last_active_time' ) ) : ?>
							· <?php bbp_forum_last_active_time(); ?>
						<?php endif; ?>
					</div>
				</div>
			<?php else : ?>
				<div class="cat-row__latest-meta"><?php esc_html_e( 'Ei vielä viestejä', 'rytkoset-theme' ); ?></div>
			<?php endif; ?>
		</div>

	</a>
	<?php

	// Render any child sub-forums within this forum.
	if ( function_exists( 'bbp_list_forums' ) ) {
		bbp_list_forums(
			array(
				'before'      => '<div class="cat-list cat-list--sub">',
				'after'       => '</div>',
				'link_before' => '',
				'link_after'  => '',
			)
		);
	}

endwhile;
