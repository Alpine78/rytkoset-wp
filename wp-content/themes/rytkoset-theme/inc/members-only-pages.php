<?php
/**
 * Jäsenille rajatut sisältösivut (#392, EPIC 10).
 *
 * Ensimmäinen jäsenetu: ylläpitäjä voi merkitä yksittäisen tavallisen WordPress-sivun
 * "vain jäsenille" -rajatuksi. Aktiivinen jäsen näkee sisällön normaalisti; muille
 * näytetään sisällön tilalle kirjautumis-/liittymiskehote (otsikko saa näkyä).
 *
 * Jäsenstatus tulee EPIC 10:n jaetusta helperistä rytkoset_theme_user_is_active_member()
 * (#301, inc/user-membership.php) — sama lähde kuin digilehtien käyttöoikeudella. Rajaus
 * tallennetaan sivun metatietoon (_rytkoset_members_only = yes); ei uusia lisäosia eikä
 * tietokantatauluja. REST-, syöte-, haku- ja oEmbed-vuodot tukitaan samalla mallilla kuin
 * digilehdissä (#381).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the post meta key flagging a page as members-only.
 *
 * @return string
 */
function rytkoset_theme_get_members_only_page_meta_key() {
	return '_rytkoset_members_only';
}

/**
 * Returns true when a page is flagged members-only.
 *
 * @param int|WP_Post|null $post Post object or ID. Defaults to the current post.
 * @return bool
 */
function rytkoset_theme_page_is_members_only( $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	return 'yes' === get_post_meta( $post->ID, rytkoset_theme_get_members_only_page_meta_key(), true );
}

/**
 * Returns true when the user may read a members-only page.
 *
 * A non-restricted page is always readable. Editors who can edit the page keep access so they
 * can preview restricted content. Everyone else needs an active membership (#301). Any uncertain
 * state falls through to the membership check, which itself fails closed.
 *
 * @param int|WP_Post|null $post    Post object or ID. Defaults to the current post.
 * @param int|null         $user_id User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_can_read_members_only_page( $post = null, $user_id = null ) {
	$post = get_post( $post );

	if ( ! rytkoset_theme_page_is_members_only( $post ) ) {
		return true;
	}

	if ( current_user_can( 'edit_post', $post->ID ) ) {
		return true;
	}

	return function_exists( 'rytkoset_theme_user_is_active_member' )
		&& rytkoset_theme_user_is_active_member( $user_id );
}

/**
 * Returns true when a members-only page's content must be hidden from the viewer.
 *
 * Shared by the template gate and the REST/feed/excerpt leak guards so they all honour the same
 * access decision.
 *
 * @param int|WP_Post|null $post Post object or ID. Defaults to the current post.
 * @return bool
 */
function rytkoset_theme_should_hide_members_only_page( $post = null ) {
	$post = get_post( $post );

	if ( ! rytkoset_theme_page_is_members_only( $post ) ) {
		return false;
	}

	return ! rytkoset_theme_user_can_read_members_only_page( $post );
}

/**
 * Returns the placeholder shown in place of restricted page content (feeds, search, REST).
 *
 * @return string
 */
function rytkoset_theme_get_members_only_page_locked_notice() {
	return __( 'Tämä sisältö on tarkoitettu seuran jäsenille.', 'rytkoset-theme' );
}

/**
 * Registers the members-only metabox on the page editor.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function rytkoset_theme_register_members_only_page_metabox( $post ) {
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}

	add_meta_box(
		'rytkoset-members-only-page',
		__( 'Näkyvyys', 'rytkoset-theme' ),
		'rytkoset_theme_render_members_only_page_metabox',
		'page',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_page', 'rytkoset_theme_register_members_only_page_metabox' );

/**
 * Renders the members-only metabox.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function rytkoset_theme_render_members_only_page_metabox( $post ) {
	$meta_key  = rytkoset_theme_get_members_only_page_meta_key();
	$is_locked = rytkoset_theme_page_is_members_only( $post );

	wp_nonce_field( 'rytkoset_save_members_only_page', 'rytkoset_members_only_page_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $meta_key ); ?>" value="yes" <?php checked( $is_locked ); ?> />
			<?php esc_html_e( 'Vain jäsenille', 'rytkoset-theme' ); ?>
		</label>
	</p>
	<p class="description">
		<?php esc_html_e( 'Vain aktiiviset jäsenet näkevät sisällön. Muille näytetään sisällön tilalle kirjautumis- ja liittymiskehote; sivun otsikko näkyy kaikille.', 'rytkoset-theme' ); ?>
	</p>
	<?php
}

/**
 * Saves the members-only flag from the page editor.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function rytkoset_theme_save_members_only_page( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rytkoset_members_only_page_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_members_only_page_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_members_only_page' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$meta_key = rytkoset_theme_get_members_only_page_meta_key();

	if ( isset( $_POST[ $meta_key ] ) && 'yes' === sanitize_key( wp_unslash( $_POST[ $meta_key ] ) ) ) {
		update_post_meta( $post_id, $meta_key, 'yes' );
	} else {
		delete_post_meta( $post_id, $meta_key );
	}
}
add_action( 'save_post_page', 'rytkoset_theme_save_members_only_page', 10, 2 );

/**
 * Suppresses content-derived excerpts for restricted pages.
 *
 * Search results, RSS summary feeds and the oEmbed/embed view all build their excerpt from
 * get_the_excerpt(), so one filter closes all three routes. A manually entered page excerpt is
 * kept as an intentional public teaser; only the auto-generated, content-derived excerpt is
 * replaced with the locked notice.
 *
 * @param string           $excerpt Post excerpt.
 * @param int|WP_Post|null $post    Post object or ID.
 * @return string
 */
function rytkoset_theme_protect_members_only_page_excerpt( $excerpt, $post = null ) {
	$post = get_post( $post );

	if ( ! rytkoset_theme_should_hide_members_only_page( $post ) ) {
		return $excerpt;
	}

	if ( '' !== trim( (string) $post->post_excerpt ) ) {
		return $excerpt;
	}

	return rytkoset_theme_get_members_only_page_locked_notice();
}
add_filter( 'get_the_excerpt', 'rytkoset_theme_protect_members_only_page_excerpt', 10, 2 );

/**
 * Replaces full-content RSS feed output for restricted pages.
 *
 * Summary feeds route through get_the_excerpt() above; this covers feeds configured to deliver
 * the full post content (the_content_feed). Pages are not in feeds by default, but custom feeds
 * and plugins can surface them, so this guards the route regardless.
 *
 * @param string $content   Feed content.
 * @param string $feed_type Feed type.
 * @return string
 */
function rytkoset_theme_protect_members_only_page_feed_content( $content, $feed_type = null ) {
	if ( ! rytkoset_theme_should_hide_members_only_page( get_post() ) ) {
		return $content;
	}

	return wpautop( rytkoset_theme_get_members_only_page_locked_notice() );
}
add_filter( 'the_content_feed', 'rytkoset_theme_protect_members_only_page_feed_content', 10, 2 );

/**
 * Strips restricted page content and excerpt from REST responses.
 *
 * Pages stay in REST so the block editor keeps working; users who can edit the page (and active
 * members) keep the fields through the shared read-access helper. Everyone else receives the
 * item with blanked content and excerpt, while title, slug and featured media stay available.
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_Post          $post     Post object.
 * @param WP_REST_Request  $request  Request object.
 * @return WP_REST_Response
 */
function rytkoset_theme_protect_members_only_page_rest( $response, $post, $request ) {
	if ( ! $post instanceof WP_Post || ! rytkoset_theme_should_hide_members_only_page( $post ) ) {
		return $response;
	}

	$data = $response->get_data();

	if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
		$data['content']['rendered'] = '';

		if ( array_key_exists( 'raw', $data['content'] ) ) {
			$data['content']['raw'] = '';
		}
	}

	if ( isset( $data['excerpt'] ) && is_array( $data['excerpt'] ) ) {
		$data['excerpt']['rendered'] = '';

		if ( array_key_exists( 'raw', $data['excerpt'] ) ) {
			$data['excerpt']['raw'] = '';
		}
	}

	$response->set_data( $data );

	return $response;
}
add_filter( 'rest_prepare_page', 'rytkoset_theme_protect_members_only_page_rest', 10, 3 );
