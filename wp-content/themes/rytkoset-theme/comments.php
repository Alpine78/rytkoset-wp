<?php
/**
 * Kommenttitemplate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$rytkoset_comment_count = get_comments_number();
			if ( '1' === $rytkoset_comment_count ) {
				echo wp_kses_post(
					sprintf(
						/* translators: %s: post title */
						__( '1 kommentti aiheeseen "%s"', 'rytkoset-theme' ),
						'<span>' . esc_html( get_the_title() ) . '</span>'
					)
				);
			} else {
				echo wp_kses_post(
					sprintf(
						/* translators: 1: number of comments, 2: post title */
						_n( '%1$s kommentti aiheeseen "%2$s"', '%1$s kommenttia aiheeseen "%2$s"', $rytkoset_comment_count, 'rytkoset-theme' ),
						number_format_i18n( $rytkoset_comment_count ),
						'<span>' . esc_html( get_the_title() ) . '</span>'
					)
				);
			}
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
				)
			);
			?>
		</ol>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
			)
		);
		?>

	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'          => __( 'Jätä kommentti', 'rytkoset-theme' ),
			'title_reply_before'   => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'    => '</h2>',
			'label_submit'         => __( 'Lähetä kommentti', 'rytkoset-theme' ),
			'comment_notes_before' => '',
		)
	);
	?>

</div>
