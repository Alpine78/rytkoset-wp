<?php
/**
 * New/Edit Topic form.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! bbp_is_single_forum() ) : ?>
<div id="bbpress-forums" class="bbpress-wrapper">
<?php endif; ?>

<?php if ( bbp_is_topic_edit() ) : ?>
	<?php bbp_topic_tag_list( bbp_get_topic_id() ); ?>
	<?php bbp_single_topic_description( array( 'topic_id' => bbp_get_topic_id() ) ); ?>
	<?php bbp_get_template_part( 'alert', 'topic-lock' ); ?>
<?php endif; ?>

<?php if ( bbp_current_user_can_access_create_topic_form() ) : ?>

	<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">
		<form id="new-post" name="new-post" method="post">

			<?php do_action( 'bbp_theme_before_topic_form' ); ?>

			<fieldset class="bbp-form">
				<legend>
					<?php if ( bbp_is_topic_edit() ) :
						printf( esc_html__( 'Muokkaa aihetta: &ldquo;%s&rdquo;', 'rytkoset-theme' ), bbp_get_topic_title() );
					elseif ( bbp_is_single_forum() && bbp_get_forum_title() ) :
						printf( esc_html__( 'Luo uusi aihe alueelle &ldquo;%s&rdquo;', 'rytkoset-theme' ), bbp_get_forum_title() );
					else :
						esc_html_e( 'Luo uusi aihe', 'rytkoset-theme' );
					endif; ?>
				</legend>

				<?php do_action( 'bbp_theme_before_topic_form_notices' ); ?>

				<?php if ( ! bbp_is_topic_edit() && bbp_is_forum_closed() ) : ?>
					<div class="bbp-template-notice">
						<ul><li><?php esc_html_e( 'Tämä foorumi on suljettu uusilta aiheilta, mutta julkaisuoikeutesi mahdollistavat aiheen luomisen.', 'rytkoset-theme' ); ?></li></ul>
					</div>
				<?php endif; ?>

				<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>
					<div class="bbp-template-notice">
						<ul><li><?php esc_html_e( 'Tunnuksellasi on mahdollista syöttää HTML-sisältöä, jota ei tulla tarkistamaan.', 'rytkoset-theme' ); ?></li></ul>
					</div>
				<?php endif; ?>

				<?php do_action( 'bbp_template_notices' ); ?>

				<div class="bbp-form-body">

					<?php bbp_get_template_part( 'form', 'anonymous' ); ?>

					<?php /* Title field */ ?>
					<?php do_action( 'bbp_theme_before_topic_form_title' ); ?>
					<div class="bbp-form-field">
						<label for="bbp_topic_title"><?php esc_html_e( 'Aihe:', 'rytkoset-theme' ); ?></label>
						<input
							type="text"
							id="bbp_topic_title"
							name="bbp_topic_title"
							value="<?php bbp_form_topic_title(); ?>"
							maxlength="<?php bbp_title_max_length(); ?>"
							placeholder="<?php esc_attr_e( 'Mistä haluaisit keskustella?', 'rytkoset-theme' ); ?>"
						/>
					</div>
					<?php do_action( 'bbp_theme_after_topic_form_title' ); ?>

					<?php /* Content editor */ ?>
					<?php do_action( 'bbp_theme_before_topic_form_content' ); ?>
					<?php bbp_the_content( array( 'context' => 'topic' ) ); ?>
					<?php do_action( 'bbp_theme_after_topic_form_content' ); ?>

					<?php /* Tags — only for users who can assign them */ ?>
					<?php if ( bbp_allow_topic_tags() && current_user_can( 'assign_topic_tags', bbp_get_topic_id() ) ) : ?>
						<?php do_action( 'bbp_theme_before_topic_form_tags' ); ?>
						<div class="bbp-form-field">
							<label for="bbp_topic_tags"><?php esc_html_e( 'Avainsanat:', 'rytkoset-theme' ); ?></label>
							<input type="text" value="<?php bbp_form_topic_tags(); ?>" name="bbp_topic_tags" id="bbp_topic_tags" <?php disabled( bbp_is_topic_spam() ); ?> />
						</div>
						<?php do_action( 'bbp_theme_after_topic_form_tags' ); ?>
					<?php endif; ?>

					<?php /* Forum selector — only outside single-forum context */ ?>
					<?php if ( ! bbp_is_single_forum() ) : ?>
						<?php do_action( 'bbp_theme_before_topic_form_forum' ); ?>
						<div class="bbp-form-field">
							<label for="bbp_forum_id"><?php esc_html_e( 'Foorumi:', 'rytkoset-theme' ); ?></label>
							<?php bbp_dropdown( array(
								'show_none' => esc_html__( '— Valitse foorumi —', 'rytkoset-theme' ),
								'selected'  => bbp_get_form_topic_forum(),
							) ); ?>
						</div>
						<?php do_action( 'bbp_theme_after_topic_form_forum' ); ?>
					<?php endif; ?>

					<?php /* Moderator-only: type and status */ ?>
					<?php if ( current_user_can( 'moderate', bbp_get_topic_id() ) ) : ?>
						<div class="bbp-form-admin-fields">
							<?php do_action( 'bbp_theme_before_topic_form_type' ); ?>
							<div class="bbp-form-field bbp-form-field--inline">
								<label for="bbp_stick_topic"><?php esc_html_e( 'Tyyppi:', 'rytkoset-theme' ); ?></label>
								<?php bbp_form_topic_type_dropdown(); ?>
							</div>
							<?php do_action( 'bbp_theme_after_topic_form_type' ); ?>
							<?php do_action( 'bbp_theme_before_topic_form_status' ); ?>
							<div class="bbp-form-field bbp-form-field--inline">
								<label for="bbp_topic_status"><?php esc_html_e( 'Tila:', 'rytkoset-theme' ); ?></label>
								<?php bbp_form_topic_status_dropdown(); ?>
							</div>
							<?php do_action( 'bbp_theme_after_topic_form_status' ); ?>
						</div>
					<?php endif; ?>

					<?php /* Email subscription checkbox */ ?>
					<?php if ( bbp_is_subscriptions_active() && ! bbp_is_anonymous() && ( ! bbp_is_topic_edit() || ( bbp_is_topic_edit() && ! bbp_is_topic_anonymous() ) ) ) : ?>
						<?php do_action( 'bbp_theme_before_topic_form_subscriptions' ); ?>
						<div class="bbp-form-field bbp-form-field--checkbox">
							<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe" <?php bbp_form_topic_subscribed(); ?> />
							<?php if ( bbp_is_topic_edit() && bbp_get_topic_author_id() !== bbp_get_current_user_id() ) : ?>
								<label for="bbp_topic_subscription"><?php esc_html_e( 'Ilmoita kirjoittajalle vastauksista sähköpostilla', 'rytkoset-theme' ); ?></label>
							<?php else : ?>
								<label for="bbp_topic_subscription"><?php esc_html_e( 'Ilmoita vastauksista sähköpostilla', 'rytkoset-theme' ); ?></label>
							<?php endif; ?>
						</div>
						<?php do_action( 'bbp_theme_after_topic_form_subscriptions' ); ?>
					<?php endif; ?>

					<?php /* Revision log — edit mode only */ ?>
					<?php if ( bbp_allow_revisions() && bbp_is_topic_edit() ) : ?>
						<?php do_action( 'bbp_theme_before_topic_form_revisions' ); ?>
						<fieldset class="bbp-form bbp-form--revision">
							<legend>
								<input name="bbp_log_topic_edit" id="bbp_log_topic_edit" type="checkbox" value="1" <?php bbp_form_topic_log_edit(); ?> />
								<label for="bbp_log_topic_edit"><?php esc_html_e( 'Kirjaa muokkaus historiaan:', 'rytkoset-theme' ); ?></label>
							</legend>
							<div class="bbp-form-field">
								<label for="bbp_topic_edit_reason"><?php esc_html_e( 'Muokkauksen syy (valinnainen):', 'rytkoset-theme' ); ?></label>
								<input type="text" value="<?php bbp_form_topic_edit_reason(); ?>" name="bbp_topic_edit_reason" id="bbp_topic_edit_reason" />
							</div>
						</fieldset>
						<?php do_action( 'bbp_theme_after_topic_form_revisions' ); ?>
					<?php endif; ?>

					<?php /* Submit row */ ?>
					<?php do_action( 'bbp_theme_before_topic_form_submit_wrapper' ); ?>
					<div class="bbp-submit-wrapper">
						<?php do_action( 'bbp_theme_before_topic_form_submit_button' ); ?>
						<button type="submit" id="bbp_topic_submit" name="bbp_topic_submit" class="button submit">
							<?php echo bbp_is_topic_edit()
								? esc_html__( 'Tallenna muutokset', 'rytkoset-theme' )
								: esc_html__( 'Lähetä aihe', 'rytkoset-theme' ); ?>
						</button>
						<?php do_action( 'bbp_theme_after_topic_form_submit_button' ); ?>
					</div>
					<?php do_action( 'bbp_theme_after_topic_form_submit_wrapper' ); ?>

				</div><!-- .bbp-form-body -->

				<?php bbp_topic_form_fields(); ?>

			</fieldset>

			<?php do_action( 'bbp_theme_after_topic_form' ); ?>

		</form>
	</div><!-- .bbp-topic-form -->

<?php elseif ( bbp_is_forum_closed() ) : ?>

	<div id="forum-closed-<?php bbp_forum_id(); ?>" class="bbp-forum-closed">
		<div class="bbp-template-notice">
			<ul><li><?php printf( esc_html__( 'Foorumi &laquo;%s&raquo; on suljettu uusilta aiheilta ja vastauksilta.', 'rytkoset-theme' ), bbp_get_forum_title() ); ?></li></ul>
		</div>
	</div>

<?php else : ?>

	<div id="no-topic-<?php bbp_forum_id(); ?>" class="bbp-no-topic">
		<div class="bbp-template-notice">
			<ul><li><?php is_user_logged_in()
				? esc_html_e( 'Sinulla ei ole oikeutta luoda uusia aiheita.', 'rytkoset-theme' )
				: esc_html_e( 'Kirjaudu sisään luodaksesi uuden aiheen.', 'rytkoset-theme' );
			?></li></ul>
		</div>
		<?php if ( ! is_user_logged_in() ) : ?>
			<?php bbp_get_template_part( 'form', 'user-login' ); ?>
		<?php endif; ?>
	</div>

<?php endif; ?>

<?php if ( ! bbp_is_single_forum() ) : ?>
</div>
<?php endif;
