<?php
/**
 * bbPress page template.
 *
 * When this file exists, WordPress uses it for all bbPress pages and bbPress
 * disables its own theme compatibility layer. Forum content templates
 * (content-index.php, content-single-forum.php, etc.) manage their own
 * layout and max-width via .forum-page__inner, so no constraining wrapper
 * is added here.
 *
 * @package rytkoset-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main" tabindex="-1">
<?php
if ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
	bbp_get_template_part( 'content', 'single-topic' );
} elseif ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() ) {
	bbp_get_template_part( 'content', 'single-forum' );
} elseif ( function_exists( 'bbp_is_search' ) && ( bbp_is_search() || bbp_is_search_results() ) ) {
	bbp_get_template_part( 'content', 'search' );
} else {
	bbp_get_template_part( 'content', 'index' );
}
?>
</main>
<?php
get_footer();
