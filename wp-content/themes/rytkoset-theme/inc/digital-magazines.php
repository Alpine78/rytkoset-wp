<?php
/**
 * Digilehdet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rekisteröi digilehtien hierarkkisen sisältötyypin.
 */
function rytkoset_theme_register_digital_magazine_cpt() {
	$labels = array(
		'name'               => __( 'Digilehdet', 'rytkoset-theme' ),
		'singular_name'      => __( 'Digilehti', 'rytkoset-theme' ),
		'menu_name'          => __( 'Digilehdet', 'rytkoset-theme' ),
		'name_admin_bar'     => __( 'Digilehti', 'rytkoset-theme' ),
		'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
		'add_new_item'       => __( 'Lisää uusi digilehti tai juttu', 'rytkoset-theme' ),
		'new_item'           => __( 'Uusi digilehti', 'rytkoset-theme' ),
		'edit_item'          => __( 'Muokkaa digilehteä', 'rytkoset-theme' ),
		'view_item'          => __( 'Näytä digilehti', 'rytkoset-theme' ),
		'all_items'          => __( 'Kaikki digilehdet', 'rytkoset-theme' ),
		'search_items'       => __( 'Etsi digilehtiä', 'rytkoset-theme' ),
		'parent_item_colon'  => __( 'Ylälehti:', 'rytkoset-theme' ),
		'not_found'          => __( 'Digilehtiä ei löytynyt.', 'rytkoset-theme' ),
		'not_found_in_trash' => __( 'Roskakorissa ei ole digilehtiä.', 'rytkoset-theme' ),
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'has_archive'  => true,
		'hierarchical' => true,
		'menu_icon'    => 'dashicons-book-alt',
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
		'rewrite'      => array(
			'slug'         => 'digilehdet',
			'with_front'   => false,
			'hierarchical' => true,
		),
	);

	register_post_type( 'digital_magazine', $args );
}
add_action( 'init', 'rytkoset_theme_register_digital_magazine_cpt' );

/**
 * Returns the post meta key storing the digital magazine access mode.
 *
 * @return string
 */
function rytkoset_theme_get_digital_magazine_access_mode_meta_key() {
	return '_rytkoset_magazine_access_mode';
}

/**
 * Returns the supported digital magazine access modes.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_digital_magazine_access_mode_options() {
	return array(
		'free'               => __( 'Kaikille ilmainen', 'rytkoset-theme' ),
		'members_only'       => __( 'Vain jäsenille', 'rytkoset-theme' ),
		'member_and_regular' => __( 'Jäsenhinta + normaalihinta', 'rytkoset-theme' ),
		'paid'               => __( 'Kaikille maksullinen', 'rytkoset-theme' ),
	);
}

/**
 * Normalizes a digital magazine access mode.
 *
 * Missing mode defaults to free for existing magazines. Unknown values are
 * treated as members-only to avoid opening restricted content by accident.
 *
 * @param string $mode Raw access mode.
 * @return string
 */
function rytkoset_theme_normalize_digital_magazine_access_mode( $mode ) {
	$mode = sanitize_key( $mode );

	if ( '' === $mode ) {
		return 'free';
	}

	$options = rytkoset_theme_get_digital_magazine_access_mode_options();

	return isset( $options[ $mode ] ) ? $mode : 'members_only';
}

/**
 * Returns the digital magazine access mode label.
 *
 * @param string $mode Access mode.
 * @return string
 */
function rytkoset_theme_get_digital_magazine_access_mode_label( $mode ) {
	$mode    = rytkoset_theme_normalize_digital_magazine_access_mode( $mode );
	$options = rytkoset_theme_get_digital_magazine_access_mode_options();

	return isset( $options[ $mode ] ) ? $options[ $mode ] : $options['free'];
}

/**
 * Checks whether a digital magazine post is an article.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function rytkoset_theme_is_digital_magazine_article( $post_id ) {
	return 0 < (int) wp_get_post_parent_id( $post_id );
}

/**
 * Returns the parent magazine ID for a magazine or article.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function rytkoset_theme_get_digital_magazine_parent_id( $post_id ) {
	$parent_id = (int) wp_get_post_parent_id( $post_id );

	return 0 < $parent_id ? $parent_id : (int) $post_id;
}

/**
 * Returns the access mode for a magazine or article.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function rytkoset_theme_get_digital_magazine_access_mode( $post_id ) {
	$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( $post_id );

	if ( $magazine_id <= 0 ) {
		return 'free';
	}

	return rytkoset_theme_normalize_digital_magazine_access_mode(
		(string) get_post_meta( $magazine_id, rytkoset_theme_get_digital_magazine_access_mode_meta_key(), true )
	);
}

/**
 * Returns true when the access mode restricts content for at least some users.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function rytkoset_theme_digital_magazine_is_restricted( $post_id ) {
	return 'free' !== rytkoset_theme_get_digital_magazine_access_mode( $post_id );
}

/**
 * Returns true when the user has bought access to the digital magazine.
 *
 * Product links and purchase checks are implemented in #201. Until then this
 * helper intentionally returns false, while exposing a filter for that follow-up.
 *
 * @param int      $magazine_id Parent magazine post ID.
 * @param int|null $user_id     User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_has_purchased_digital_magazine( $magazine_id, $user_id = null ) {
	$magazine_id = absint( $magazine_id );
	$user_id     = null === $user_id ? get_current_user_id() : absint( $user_id );

	/**
	 * Filters whether a user has purchased a digital magazine.
	 *
	 * @param bool $has_purchased Whether the user has bought access.
	 * @param int  $magazine_id   Parent magazine post ID.
	 * @param int  $user_id       User ID, or 0 for anonymous users.
	 */
	return (bool) apply_filters(
		'rytkoset_theme_user_has_purchased_digital_magazine',
		false,
		$magazine_id,
		$user_id
	);
}

/**
 * Returns true when the user can read a magazine or article.
 *
 * @param int      $post_id Post ID.
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return bool
 */
function rytkoset_theme_user_can_read_digital_magazine( $post_id, $user_id = null ) {
	$magazine_id = rytkoset_theme_get_digital_magazine_parent_id( $post_id );
	$mode        = rytkoset_theme_get_digital_magazine_access_mode( $magazine_id );
	$user_id     = null === $user_id ? get_current_user_id() : absint( $user_id );

	if ( 'free' === $mode ) {
		return true;
	}

	if ( current_user_can( 'edit_post', $magazine_id ) ) {
		return true;
	}

	if ( 'members_only' === $mode ) {
		return function_exists( 'rytkoset_theme_user_is_active_member' )
			&& rytkoset_theme_user_is_active_member( $user_id );
	}

	if ( in_array( $mode, array( 'member_and_regular', 'paid' ), true ) ) {
		return rytkoset_theme_user_has_purchased_digital_magazine( $magazine_id, $user_id );
	}

	return false;
}

/**
 * Registers the digital magazine access mode metabox.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function rytkoset_theme_register_digital_magazine_access_metabox( $post ) {
	if ( $post instanceof WP_Post && 0 < (int) $post->post_parent ) {
		return;
	}

	add_meta_box(
		'rytkoset-digital-magazine-access',
		__( 'Käyttöoikeus', 'rytkoset-theme' ),
		'rytkoset_theme_render_digital_magazine_access_metabox',
		'digital_magazine',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_digital_magazine', 'rytkoset_theme_register_digital_magazine_access_metabox' );

/**
 * Renders the digital magazine access mode metabox.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function rytkoset_theme_render_digital_magazine_access_metabox( $post ) {
	$meta_key = rytkoset_theme_get_digital_magazine_access_mode_meta_key();
	$mode     = rytkoset_theme_get_digital_magazine_access_mode( $post->ID );
	$options  = rytkoset_theme_get_digital_magazine_access_mode_options();

	wp_nonce_field( 'rytkoset_save_digital_magazine_access', 'rytkoset_digital_magazine_access_nonce' );
	?>
	<p>
		<label for="rytkoset_magazine_access_mode">
			<?php esc_html_e( 'Lehden käyttöoikeusmalli', 'rytkoset-theme' ); ?>
		</label>
	</p>
	<select id="rytkoset_magazine_access_mode" name="<?php echo esc_attr( $meta_key ); ?>" class="widefat" style="max-width: 100%; box-sizing: border-box;">
		<?php foreach ( $options as $option_value => $option_label ) : ?>
			<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $mode, $option_value ); ?>>
				<?php echo esc_html( $option_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php esc_html_e( 'Asetetaan vain lehdelle. Jutut perivät emolehden käyttöoikeusmallin.', 'rytkoset-theme' ); ?>
	</p>
	<?php
}

/**
 * Saves the digital magazine access mode.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function rytkoset_theme_save_digital_magazine_access_mode( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! $post instanceof WP_Post || 0 < (int) $post->post_parent ) {
		delete_post_meta( $post_id, rytkoset_theme_get_digital_magazine_access_mode_meta_key() );
		return;
	}

	if ( ! isset( $_POST['rytkoset_digital_magazine_access_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_digital_magazine_access_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_digital_magazine_access' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$meta_key = rytkoset_theme_get_digital_magazine_access_mode_meta_key();
	$mode     = isset( $_POST[ $meta_key ] )
		? rytkoset_theme_normalize_digital_magazine_access_mode( wp_unslash( $_POST[ $meta_key ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalizer returns only allowlisted access modes.
		: 'free';

	if ( 'free' === $mode ) {
		delete_post_meta( $post_id, $meta_key );
		return;
	}

	update_post_meta( $post_id, $meta_key, $mode );
}
add_action( 'save_post_digital_magazine', 'rytkoset_theme_save_digital_magazine_access_mode', 10, 2 );

/**
 * Returns published articles for one digital magazine.
 *
 * @param int $magazine_id Magazine post ID.
 * @return WP_Post[]
 */
function rytkoset_theme_get_digital_magazine_articles( $magazine_id ) {
	$magazine_id = absint( $magazine_id );

	if ( 0 === $magazine_id ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => 'digital_magazine',
			'post_status'    => 'publish',
			'post_parent'    => $magazine_id,
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => true,
		)
	);
}

/**
 * Returns the previous or next article within the same digital magazine.
 *
 * @param int    $article_id Article post ID.
 * @param string $direction  Direction: previous or next.
 * @return WP_Post|null
 */
function rytkoset_theme_get_adjacent_digital_magazine_article( $article_id, $direction ) {
	$article = get_post( $article_id );

	if ( ! $article instanceof WP_Post || 'digital_magazine' !== $article->post_type || 0 === (int) $article->post_parent ) {
		return null;
	}

	$articles = rytkoset_theme_get_digital_magazine_articles( (int) $article->post_parent );
	$ids      = array_map( 'intval', wp_list_pluck( $articles, 'ID' ) );
	$index    = array_search( (int) $article_id, $ids, true );

	if ( false === $index ) {
		return null;
	}

	$target_index = 'previous' === $direction ? $index - 1 : $index + 1;

	return isset( $articles[ $target_index ] ) ? $articles[ $target_index ] : null;
}

/**
 * Shows only top-level magazines on the public archive.
 *
 * @param WP_Query $query Query instance.
 */
function rytkoset_theme_limit_digital_magazine_archive_to_top_level( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'digital_magazine' ) ) {
		return;
	}

	$query->set( 'post_parent', 0 );
	$query->set( 'orderby', 'date' );
	$query->set( 'order', 'DESC' );
}
add_action( 'pre_get_posts', 'rytkoset_theme_limit_digital_magazine_archive_to_top_level' );
