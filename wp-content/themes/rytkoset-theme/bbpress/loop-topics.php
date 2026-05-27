<?php
/**
 * Topic loop — renders each topic as a row (.topic-row).
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( bbp_topics() ) :
	bbp_the_topic();

	$topic_id       = bbp_get_topic_id();
	$starter_name   = bbp_get_topic_author_display_name( $topic_id );
	$is_sticky      = function_exists( 'bbp_is_topic_sticky' ) && bbp_is_topic_sticky( $topic_id );
	$is_super_sticky = function_exists( 'bbp_is_topic_super_sticky' ) && bbp_is_topic_super_sticky( $topic_id );
	$is_pinned      = $is_sticky || $is_super_sticky;

	// Last reply author.
	$last_active_id = function_exists( 'bbp_get_topic_last_active_id' ) ? (int) bbp_get_topic_last_active_id( $topic_id ) : 0;
	$last_name      = '';
	if ( $last_active_id ) {
		$last_name = function_exists( 'rytkoset_theme_bbp_author_name' ) ? rytkoset_theme_bbp_author_name( $last_active_id ) : '';
	}
	if ( ! $last_name ) {
		$last_name = $starter_name;
	}
	?>
	<div class="topic-row">

		<?php /* Starter avatar */ ?>
		<div class="topic-row__avatar" aria-hidden="true">
			<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $starter_name, 'lg' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php /* Title + tags + meta */ ?>
		<div class="topic-row__main">
			<div>
				<a href="<?php bbp_topic_permalink(); ?>" class="topic-row__title">
					<?php bbp_topic_title(); ?>
				</a>
				<?php if ( $is_pinned ) : ?>
					<span class="topic-row__tags">
						<span class="topic-tag topic-tag--pinned">
							<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<line x1="12" y1="17" x2="12" y2="22"/>
								<path d="M5 17h14l-1.5-3V8a4 4 0 0 0-4-4h-3a4 4 0 0 0-4 4v6L5 17z"/>
							</svg>
							<?php esc_html_e( 'Kiinnitetty', 'rytkoset-theme' ); ?>
						</span>
					</span>
				<?php endif; ?>
			</div>
			<div class="topic-row__meta">
				<?php esc_html_e( 'Aloittaja', 'rytkoset-theme' ); ?>
				<strong><?php echo esc_html( $starter_name ); ?></strong>
			</div>
		</div>

		<?php /* Reply count */ ?>
		<div class="topic-row__num">
			<div class="topic-row__num-value"><?php bbp_topic_reply_count(); ?></div>
			<div class="topic-row__num-label"><?php esc_html_e( 'Vastausta', 'rytkoset-theme' ); ?></div>
		</div>

		<?php /* Voice count (participants) */ ?>
		<div class="topic-row__num">
			<div class="topic-row__num-value"><?php bbp_topic_voice_count(); ?></div>
			<div class="topic-row__num-label"><?php esc_html_e( 'Osallista', 'rytkoset-theme' ); ?></div>
		</div>

		<?php /* Latest reply */ ?>
		<div class="topic-row__latest">
			<?php echo function_exists( 'rytkoset_theme_forum_avatar' ) ? rytkoset_theme_forum_avatar( $last_name ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="topic-row__latest-text">
				<div class="topic-row__latest-name"><?php echo esc_html( $last_name ); ?></div>
				<div class="topic-row__latest-time"><?php bbp_topic_last_active_time(); ?></div>
			</div>
		</div>

	</div>
	<?php
endwhile;
