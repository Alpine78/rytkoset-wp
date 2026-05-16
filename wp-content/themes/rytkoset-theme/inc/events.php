<?php
/**
 * Tapahtumat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_register_event_cpt' ) ) {
	/**
	 * Rekisteröi tapahtumien CPT:n.
	 */
	function rytkoset_theme_register_event_cpt() {
		$labels = array(
			'name'               => __( 'Tapahtumat', 'rytkoset-theme' ),
			'singular_name'      => __( 'Tapahtuma', 'rytkoset-theme' ),
			'menu_name'          => __( 'Tapahtumat', 'rytkoset-theme' ),
			'name_admin_bar'     => __( 'Tapahtuma', 'rytkoset-theme' ),
			'add_new'            => __( 'Lisää uusi', 'rytkoset-theme' ),
			'add_new_item'       => __( 'Lisää uusi tapahtuma', 'rytkoset-theme' ),
			'new_item'           => __( 'Uusi tapahtuma', 'rytkoset-theme' ),
			'edit_item'          => __( 'Muokkaa tapahtumaa', 'rytkoset-theme' ),
			'view_item'          => __( 'Näytä tapahtuma', 'rytkoset-theme' ),
			'all_items'          => __( 'Kaikki tapahtumat', 'rytkoset-theme' ),
			'search_items'       => __( 'Etsi tapahtumia', 'rytkoset-theme' ),
			'not_found'          => __( 'Tapahtumia ei löytynyt.', 'rytkoset-theme' ),
			'not_found_in_trash' => __( 'Roskakorissa ei ole tapahtumia.', 'rytkoset-theme' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => true,
			'has_archive'     => true,
			'menu_icon'       => 'dashicons-calendar-alt',
			'show_in_rest'    => true,
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'capability_type' => function_exists( 'rytkoset_theme_get_event_capability_type' )
				? rytkoset_theme_get_event_capability_type()
				: array( 'event', 'events' ),
			'map_meta_cap'    => true,
			'rewrite'         => array(
				'slug'       => 'tapahtumat',
				'with_front' => false,
			),
		);

		register_post_type( 'event', $args );
	}
}
add_action( 'init', 'rytkoset_theme_register_event_cpt' );

if ( ! function_exists( 'rytkoset_theme_get_event_date_meta_key' ) ) {
	/**
	 * Returns the meta key used for the event date.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_event_date_meta_key() {
		return '_rytkoset_event_date';
	}
}

if ( ! function_exists( 'rytkoset_theme_is_valid_event_date' ) ) {
	/**
	 * Checks whether an event date uses the expected YYYY-MM-DD format.
	 *
	 * @param string $date Event date.
	 * @return bool
	 */
	function rytkoset_theme_is_valid_event_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		$date_parts = array_map( 'absint', explode( '-', $date ) );

		if ( 3 !== count( $date_parts ) ) {
			return false;
		}

		return checkdate( $date_parts[1], $date_parts[2], $date_parts[0] );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_date_raw' ) ) {
	/**
	 * Returns a validated event date in YYYY-MM-DD format.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_date_raw( $event_id ) {
		$date = get_post_meta( $event_id, rytkoset_theme_get_event_date_meta_key(), true );

		if ( ! is_string( $date ) || ! rytkoset_theme_is_valid_event_date( $date ) ) {
			return '';
		}

		return $date;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_date_display' ) ) {
	/**
	 * Returns a localized display date for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_date_display( $event_id ) {
		$date = rytkoset_theme_get_event_date_raw( $event_id );

		if ( '' === $date ) {
			return '';
		}

		$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		if ( false === $datetime ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ), $datetime->getTimestamp() );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_details_meta_keys' ) ) {
	/**
	 * Returns meta keys used for event details.
	 *
	 * @return array
	 */
	function rytkoset_theme_get_event_details_meta_keys() {
		return array(
			'start_time' => '_rytkoset_event_start_time',
			'end_time'   => '_rytkoset_event_end_time',
			'location'   => '_rytkoset_event_location',
			'fee_type'   => '_rytkoset_event_fee_type',
			'price_text' => '_rytkoset_event_price_text',
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_is_valid_event_time' ) ) {
	/**
	 * Checks whether an event time uses the expected HH:MM format.
	 *
	 * @param string $time Event time.
	 * @return bool
	 */
	function rytkoset_theme_is_valid_event_time( $time ) {
		return is_string( $time ) && 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_time_raw' ) ) {
	/**
	 * Returns a validated event time.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $key      Event detail key.
	 * @return string
	 */
	function rytkoset_theme_get_event_time_raw( $event_id, $key ) {
		$meta_keys = rytkoset_theme_get_event_details_meta_keys();

		if ( ! isset( $meta_keys[ $key ] ) ) {
			return '';
		}

		$time = get_post_meta( $event_id, $meta_keys[ $key ], true );

		if ( ! is_string( $time ) || ! rytkoset_theme_is_valid_event_time( $time ) ) {
			return '';
		}

		return $time;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_time_display' ) ) {
	/**
	 * Returns the event time range for display.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_time_display( $event_id ) {
		$start_time = rytkoset_theme_get_event_time_raw( $event_id, 'start_time' );
		$end_time   = rytkoset_theme_get_event_time_raw( $event_id, 'end_time' );

		if ( '' !== $start_time && '' !== $end_time ) {
			return $start_time . '–' . $end_time;
		}

		if ( '' !== $start_time ) {
			return $start_time;
		}

		return $end_time;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_location' ) ) {
	/**
	 * Returns the event location.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_location( $event_id ) {
		$meta_keys = rytkoset_theme_get_event_details_meta_keys();
		$location  = get_post_meta( $event_id, $meta_keys['location'], true );

		return is_string( $location ) ? trim( $location ) : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_fee_type' ) ) {
	/**
	 * Returns the event fee type.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_fee_type( $event_id ) {
		$meta_keys = rytkoset_theme_get_event_details_meta_keys();
		$fee_type  = get_post_meta( $event_id, $meta_keys['fee_type'], true );

		if ( ! is_string( $fee_type ) || ! in_array( $fee_type, array( 'free', 'paid' ), true ) ) {
			return '';
		}

		return $fee_type;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_price_text' ) ) {
	/**
	 * Returns the event price text.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_price_text( $event_id ) {
		$meta_keys  = rytkoset_theme_get_event_details_meta_keys();
		$price_text = get_post_meta( $event_id, $meta_keys['price_text'], true );

		return is_string( $price_text ) ? trim( $price_text ) : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_format_event_price_text' ) ) {
	/**
	 * Formats event price text for display.
	 *
	 * @param string $price_text Event price text.
	 * @return string
	 */
	function rytkoset_theme_format_event_price_text( $price_text ) {
		$price_text = trim( $price_text );

		if ( '' === $price_text ) {
			return '';
		}

		if ( preg_match( '/^\d+(?:[,.]\d{1,2})?$/', $price_text ) ) {
			return $price_text . ' €';
		}

		return $price_text;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_fee_display' ) ) {
	/**
	 * Returns the event fee information for display.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_fee_display( $event_id ) {
		$fee_type   = rytkoset_theme_get_event_fee_type( $event_id );
		$price_text = rytkoset_theme_get_event_price_text( $event_id );

		if ( 'free' === $fee_type ) {
			return __( 'Maksuton', 'rytkoset-theme' );
		}

		if ( 'paid' === $fee_type && '' !== $price_text ) {
			return rytkoset_theme_format_event_price_text( $price_text );
		}

		if ( 'paid' === $fee_type ) {
			return __( 'Maksullinen', 'rytkoset-theme' );
		}

		return $price_text;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_detail_items' ) ) {
	/**
	 * Returns visible event detail items.
	 *
	 * @param int $event_id Event post ID.
	 * @return array
	 */
	function rytkoset_theme_get_event_detail_items( $event_id ) {
		$items        = array();
		$date_display = rytkoset_theme_get_event_date_display( $event_id );
		$time_display = rytkoset_theme_get_event_time_display( $event_id );
		$location     = rytkoset_theme_get_event_location( $event_id );
		$fee_display  = rytkoset_theme_get_event_fee_display( $event_id );

		if ( '' !== $date_display ) {
			$items[] = array(
				'label'    => __( 'Päivämäärä', 'rytkoset-theme' ),
				'value'    => $date_display,
				'datetime' => rytkoset_theme_get_event_date_raw( $event_id ),
			);
		}

		if ( '' !== $time_display ) {
			$items[] = array(
				'label' => __( 'Kellonaika', 'rytkoset-theme' ),
				'value' => $time_display,
			);
		}

		if ( '' !== $location ) {
			$items[] = array(
				'label' => __( 'Paikka', 'rytkoset-theme' ),
				'value' => $location,
			);
		}

		if ( '' !== $fee_display ) {
			$items[] = array(
				'label' => __( 'Hinta', 'rytkoset-theme' ),
				'value' => $fee_display,
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'rytkoset_theme_render_event_details' ) ) {
	/**
	 * Renders event details on the single event page.
	 *
	 * @param int $event_id Event post ID.
	 */
	function rytkoset_theme_render_event_details( $event_id ) {
		$items = rytkoset_theme_get_event_detail_items( $event_id );

		if ( empty( $items ) ) {
			return;
		}
		?>
		<dl class="event-details">
			<?php foreach ( $items as $item ) : ?>
				<div class="event-details__item">
					<dt class="event-details__label"><?php echo esc_html( $item['label'] ); ?></dt>
					<dd class="event-details__value">
						<?php if ( ! empty( $item['datetime'] ) ) : ?>
							<time datetime="<?php echo esc_attr( $item['datetime'] ); ?>"><?php echo esc_html( $item['value'] ); ?></time>
						<?php else : ?>
							<?php echo esc_html( $item['value'] ); ?>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_register_event_date_metabox' ) ) {
	/**
	 * Adds the event date metabox.
	 */
	function rytkoset_theme_register_event_date_metabox() {
		add_meta_box(
			'rytkoset_event_date',
			__( 'Tapahtumapäivä', 'rytkoset-theme' ),
			'rytkoset_theme_render_event_date_metabox',
			'event',
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes_event', 'rytkoset_theme_register_event_date_metabox' );

if ( ! function_exists( 'rytkoset_theme_render_event_date_metabox' ) ) {
	/**
	 * Renders the event date metabox.
	 *
	 * @param WP_Post $post Event post object.
	 */
	function rytkoset_theme_render_event_date_metabox( $post ) {
		$date = rytkoset_theme_get_event_date_raw( $post->ID );

		wp_nonce_field( 'rytkoset_save_event_date', 'rytkoset_event_date_nonce' );
		?>
		<p>
			<label for="rytkoset_event_date_field">
				<?php esc_html_e( 'Päivämäärä', 'rytkoset-theme' ); ?>
			</label>
		</p>
		<input
			type="date"
			id="rytkoset_event_date_field"
			name="rytkoset_event_date"
			value="<?php echo esc_attr( $date ); ?>"
			class="widefat"
		/>
		<p class="description">
			<?php esc_html_e( 'Käytetään tapahtuma-arkiston järjestämiseen. Muoto tallennuksessa on YYYY-MM-DD.', 'rytkoset-theme' ); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_save_event_date' ) ) {
	/**
	 * Saves the event date.
	 *
	 * @param int $post_id Event post ID.
	 */
	function rytkoset_theme_save_event_date( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['rytkoset_event_date_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_event_date_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_event_date' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$date = isset( $_POST['rytkoset_event_date'] )
			? sanitize_text_field( wp_unslash( $_POST['rytkoset_event_date'] ) )
			: '';

		if ( '' === $date ) {
			delete_post_meta( $post_id, rytkoset_theme_get_event_date_meta_key() );
			return;
		}

		if ( ! rytkoset_theme_is_valid_event_date( $date ) ) {
			return;
		}

		update_post_meta( $post_id, rytkoset_theme_get_event_date_meta_key(), $date );
	}
}
add_action( 'save_post_event', 'rytkoset_theme_save_event_date' );

if ( ! function_exists( 'rytkoset_theme_register_event_details_metabox' ) ) {
	/**
	 * Adds the event details metabox.
	 */
	function rytkoset_theme_register_event_details_metabox() {
		add_meta_box(
			'rytkoset_event_details',
			__( 'Tapahtuman tiedot', 'rytkoset-theme' ),
			'rytkoset_theme_render_event_details_metabox',
			'event',
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_event', 'rytkoset_theme_register_event_details_metabox' );

if ( ! function_exists( 'rytkoset_theme_render_event_details_metabox' ) ) {
	/**
	 * Renders the event details metabox.
	 *
	 * @param WP_Post $post Event post object.
	 */
	function rytkoset_theme_render_event_details_metabox( $post ) {
		$start_time = rytkoset_theme_get_event_time_raw( $post->ID, 'start_time' );
		$end_time   = rytkoset_theme_get_event_time_raw( $post->ID, 'end_time' );
		$location   = rytkoset_theme_get_event_location( $post->ID );
		$fee_type   = rytkoset_theme_get_event_fee_type( $post->ID );
		$price_text = rytkoset_theme_get_event_price_text( $post->ID );

		wp_nonce_field( 'rytkoset_save_event_details', 'rytkoset_event_details_nonce' );
		?>
		<p>
			<label for="rytkoset_event_start_time">
				<?php esc_html_e( 'Alkamisaika', 'rytkoset-theme' ); ?>
			</label>
			<input
				type="text"
				id="rytkoset_event_start_time"
				name="rytkoset_event_start_time"
				value="<?php echo esc_attr( $start_time ); ?>"
				class="widefat"
				inputmode="numeric"
				maxlength="5"
				pattern="(?:[01][0-9]|2[0-3]):[0-5][0-9]"
				placeholder="<?php esc_attr_e( 'Esim. 11:30', 'rytkoset-theme' ); ?>"
			/>
			<span class="description"><?php esc_html_e( 'Muoto HH:MM.', 'rytkoset-theme' ); ?></span>
		</p>
		<p>
			<label for="rytkoset_event_end_time">
				<?php esc_html_e( 'Päättymisaika', 'rytkoset-theme' ); ?>
			</label>
			<input
				type="text"
				id="rytkoset_event_end_time"
				name="rytkoset_event_end_time"
				value="<?php echo esc_attr( $end_time ); ?>"
				class="widefat"
				inputmode="numeric"
				maxlength="5"
				pattern="(?:[01][0-9]|2[0-3]):[0-5][0-9]"
				placeholder="<?php esc_attr_e( 'Esim. 18:00', 'rytkoset-theme' ); ?>"
			/>
			<span class="description"><?php esc_html_e( 'Valinnainen. Muoto HH:MM.', 'rytkoset-theme' ); ?></span>
		</p>
		<p>
			<label for="rytkoset_event_location">
				<?php esc_html_e( 'Paikka', 'rytkoset-theme' ); ?>
			</label>
			<textarea
				id="rytkoset_event_location"
				name="rytkoset_event_location"
				class="widefat"
				rows="3"
			><?php echo esc_textarea( $location ); ?></textarea>
		</p>
		<p>
			<label for="rytkoset_event_fee_type">
				<?php esc_html_e( 'Maksullisuus', 'rytkoset-theme' ); ?>
			</label>
			<select id="rytkoset_event_fee_type" name="rytkoset_event_fee_type">
				<option value=""><?php esc_html_e( 'Ei määritelty', 'rytkoset-theme' ); ?></option>
				<option value="free" <?php selected( $fee_type, 'free' ); ?>><?php esc_html_e( 'Maksuton', 'rytkoset-theme' ); ?></option>
				<option value="paid" <?php selected( $fee_type, 'paid' ); ?>><?php esc_html_e( 'Maksullinen', 'rytkoset-theme' ); ?></option>
			</select>
		</p>
		<p>
			<label for="rytkoset_event_price_text">
				<?php esc_html_e( 'Hintateksti', 'rytkoset-theme' ); ?>
			</label>
			<input
				type="text"
				id="rytkoset_event_price_text"
				name="rytkoset_event_price_text"
				value="<?php echo esc_attr( $price_text ); ?>"
				class="widefat"
				placeholder="<?php esc_attr_e( 'Esim. 49 € / henkilö', 'rytkoset-theme' ); ?>"
			/>
		</p>
		<p class="description">
			<?php esc_html_e( 'Hintateksti on informatiivinen. Varsinainen maksaminen hoidetaan erillisellä WooCommerce-tuotteella, jos tapahtumaan on linkitetty maksutuote.', 'rytkoset-theme' ); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_save_event_details' ) ) {
	/**
	 * Saves the event details.
	 *
	 * @param int $post_id Event post ID.
	 */
	function rytkoset_theme_save_event_details( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['rytkoset_event_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_event_details_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_event_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$meta_keys = rytkoset_theme_get_event_details_meta_keys();

		foreach ( array( 'start_time', 'end_time' ) as $time_key ) {
			$field_name = 'rytkoset_event_' . $time_key;
			$time       = isset( $_POST[ $field_name ] )
				? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) )
				: '';

			if ( '' === $time ) {
				delete_post_meta( $post_id, $meta_keys[ $time_key ] );
				continue;
			}

			if ( rytkoset_theme_is_valid_event_time( $time ) ) {
				update_post_meta( $post_id, $meta_keys[ $time_key ], $time );
			}
		}

		$location = isset( $_POST['rytkoset_event_location'] )
			? sanitize_textarea_field( wp_unslash( $_POST['rytkoset_event_location'] ) )
			: '';

		if ( '' === $location ) {
			delete_post_meta( $post_id, $meta_keys['location'] );
		} else {
			update_post_meta( $post_id, $meta_keys['location'], $location );
		}

		$fee_type = isset( $_POST['rytkoset_event_fee_type'] )
			? sanitize_key( wp_unslash( $_POST['rytkoset_event_fee_type'] ) )
			: '';

		if ( in_array( $fee_type, array( 'free', 'paid' ), true ) ) {
			update_post_meta( $post_id, $meta_keys['fee_type'], $fee_type );
		} else {
			delete_post_meta( $post_id, $meta_keys['fee_type'] );
		}

		$price_text = isset( $_POST['rytkoset_event_price_text'] )
			? sanitize_text_field( wp_unslash( $_POST['rytkoset_event_price_text'] ) )
			: '';

		if ( '' === $price_text ) {
			delete_post_meta( $post_id, $meta_keys['price_text'] );
		} else {
			update_post_meta( $post_id, $meta_keys['price_text'], $price_text );
		}
	}
}
add_action( 'save_post_event', 'rytkoset_theme_save_event_details' );

if ( ! function_exists( 'rytkoset_theme_get_event_product_meta_key' ) ) {
	/**
	 * Returns the meta key used to link an event to a WooCommerce product.
	 *
	 * @return string
	 */
	function rytkoset_theme_get_event_product_meta_key() {
		return '_rytkoset_event_product_id';
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_linked_product' ) ) {
	/**
	 * Returns the WooCommerce product linked to an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return WC_Product|null
	 */
	function rytkoset_theme_get_event_linked_product( $event_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product_id = absint( get_post_meta( $event_id, rytkoset_theme_get_event_product_meta_key(), true ) );

		if ( $product_id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			return null;
		}

		return $product;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_event_product_options' ) ) {
	/**
	 * Returns published WooCommerce products for the event product selector.
	 *
	 * @return WC_Product[]
	 */
	function rytkoset_theme_get_event_product_options() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		return wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_register_event_product_metabox' ) ) {
	/**
	 * Adds the event product selector metabox.
	 */
	function rytkoset_theme_register_event_product_metabox() {
		add_meta_box(
			'rytkoset_event_product',
			__( 'Maksutuote', 'rytkoset-theme' ),
			'rytkoset_theme_render_event_product_metabox',
			'event',
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_event', 'rytkoset_theme_register_event_product_metabox' );

if ( ! function_exists( 'rytkoset_theme_print_event_admin_styles' ) ) {
	/**
	 * Prints small editor-only styles for event admin metaboxes.
	 */
	function rytkoset_theme_print_event_admin_styles() {
		$screen = get_current_screen();

		if ( ! $screen || 'event' !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			#rytkoset_event_date_field,
			#rytkoset_event_product_id,
			#rytkoset_event_start_time,
			#rytkoset_event_end_time,
			#rytkoset_event_fee_type {
				box-sizing: border-box;
				max-width: calc(100% - 12px);
				width: calc(100% - 12px);
			}
		</style>
		<?php
	}
}
add_action( 'admin_head-post.php', 'rytkoset_theme_print_event_admin_styles' );
add_action( 'admin_head-post-new.php', 'rytkoset_theme_print_event_admin_styles' );

if ( ! function_exists( 'rytkoset_theme_event_admin_columns' ) ) {
	/**
	 * Adds event-specific columns to the admin list.
	 *
	 * @param array $columns Admin columns.
	 * @return array
	 */
	function rytkoset_theme_event_admin_columns( $columns ) {
		$updated_columns = array();

		foreach ( $columns as $key => $label ) {
			$updated_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$updated_columns['rytkoset_event_date'] = __( 'Tapahtumapäivä', 'rytkoset-theme' );
			}
		}

		return $updated_columns;
	}
}
add_filter( 'manage_event_posts_columns', 'rytkoset_theme_event_admin_columns' );

if ( ! function_exists( 'rytkoset_theme_event_admin_column_content' ) ) {
	/**
	 * Renders event-specific admin column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Event post ID.
	 */
	function rytkoset_theme_event_admin_column_content( $column, $post_id ) {
		if ( 'rytkoset_event_date' !== $column ) {
			return;
		}

		$date_display = rytkoset_theme_get_event_date_display( $post_id );

		if ( '' === $date_display ) {
			echo '&mdash;';
			return;
		}

		printf(
			'<time datetime="%1$s">%2$s</time>',
			esc_attr( rytkoset_theme_get_event_date_raw( $post_id ) ),
			esc_html( $date_display )
		);
	}
}
add_action( 'manage_event_posts_custom_column', 'rytkoset_theme_event_admin_column_content', 10, 2 );

if ( ! function_exists( 'rytkoset_theme_event_sortable_columns' ) ) {
	/**
	 * Makes the event date column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	function rytkoset_theme_event_sortable_columns( $columns ) {
		$columns['rytkoset_event_date'] = 'rytkoset_event_date';

		return $columns;
	}
}
add_filter( 'manage_edit-event_sortable_columns', 'rytkoset_theme_event_sortable_columns' );

if ( ! function_exists( 'rytkoset_theme_sort_event_admin_by_event_date' ) ) {
	/**
	 * Sorts the event admin list by event date when requested.
	 *
	 * @param WP_Query $query Current query.
	 */
	function rytkoset_theme_sort_event_admin_by_event_date( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		if ( 'event' !== $post_type ) {
			return;
		}

		if ( 'rytkoset_event_date' !== $query->get( 'orderby' ) ) {
			return;
		}

		$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

		$query->set(
			'meta_query',
			array(
				'relation'          => 'OR',
				'event_date_clause' => array(
					'key'     => rytkoset_theme_get_event_date_meta_key(),
					'compare' => 'EXISTS',
					'type'    => 'DATE',
				),
				'event_date_missing_clause' => array(
					'key'     => rytkoset_theme_get_event_date_meta_key(),
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set(
			'orderby',
			array(
				'event_date_clause' => $order,
				'date'              => 'DESC',
			)
		);
	}
}
add_action( 'pre_get_posts', 'rytkoset_theme_sort_event_admin_by_event_date' );

if ( ! function_exists( 'rytkoset_theme_render_event_product_metabox' ) ) {
	/**
	 * Renders the event product selector metabox.
	 *
	 * @param WP_Post $post Event post object.
	 */
	function rytkoset_theme_render_event_product_metabox( $post ) {
		$selected_product_id = absint( get_post_meta( $post->ID, rytkoset_theme_get_event_product_meta_key(), true ) );
		$products            = rytkoset_theme_get_event_product_options();

		wp_nonce_field( 'rytkoset_save_event_product_link', 'rytkoset_event_product_nonce' );

		if ( ! function_exists( 'wc_get_products' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce ei ole käytössä, joten maksutuotetta ei voi valita.', 'rytkoset-theme' ) . '</p>';
			return;
		}
		?>
		<p>
			<label for="rytkoset_event_product_id">
				<?php esc_html_e( 'WooCommerce-tuote', 'rytkoset-theme' ); ?>
			</label>
		</p>
		<select id="rytkoset_event_product_id" name="rytkoset_event_product_id" class="widefat">
			<option value=""><?php esc_html_e( 'Ei maksutuotetta', 'rytkoset-theme' ); ?></option>
			<?php foreach ( $products as $product ) : ?>
				<?php
				if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
					continue;
				}

				$product_label = $product->get_name();
				?>
				<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>" <?php selected( $selected_product_id, $product->get_id() ); ?>>
					<?php echo esc_html( $product_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		if ( $selected_product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$selected_product = wc_get_product( $selected_product_id );

			if ( class_exists( 'WC_Product' ) && $selected_product instanceof WC_Product && '' !== $selected_product->get_sku() ) {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: product SKU. */
							__( 'Valitun tuotteen SKU: %s', 'rytkoset-theme' ),
							$selected_product->get_sku()
						)
					)
				);
			}
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Valitse tapahtumaan liittyvä WooCommerce-tuote. Tapahtumasivulle lisätään painike tuotteen sivulle.', 'rytkoset-theme' ); ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_save_event_product_link' ) ) {
	/**
	 * Saves the WooCommerce product linked to an event.
	 *
	 * @param int $post_id Event post ID.
	 */
	function rytkoset_theme_save_event_product_link( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['rytkoset_event_product_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['rytkoset_event_product_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'rytkoset_save_event_product_link' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_id = isset( $_POST['rytkoset_event_product_id'] )
			? absint( wp_unslash( $_POST['rytkoset_event_product_id'] ) )
			: 0;

		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			delete_post_meta( $post_id, rytkoset_theme_get_event_product_meta_key() );
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			delete_post_meta( $post_id, rytkoset_theme_get_event_product_meta_key() );
			return;
		}

		update_post_meta( $post_id, rytkoset_theme_get_event_product_meta_key(), $product_id );
	}
}
add_action( 'save_post_event', 'rytkoset_theme_save_event_product_link' );

if ( ! function_exists( 'rytkoset_theme_get_event_product_url' ) ) {
	/**
	 * Returns the linked product URL for an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	function rytkoset_theme_get_event_product_url( $event_id ) {
		$product = rytkoset_theme_get_event_linked_product( $event_id );

		if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
			return '';
		}

		$product_url = get_permalink( $product->get_id() );

		return is_string( $product_url ) ? $product_url : '';
	}
}

if ( ! function_exists( 'rytkoset_theme_render_event_product_cta' ) ) {
	/**
	 * Renders the linked product CTA for an event.
	 *
	 * @param int $event_id Event post ID.
	 */
	function rytkoset_theme_render_event_product_cta( $event_id ) {
		$product_url = rytkoset_theme_get_event_product_url( $event_id );

		if ( ! $product_url ) {
			return;
		}
		?>
		<div class="event-product-cta">
			<a class="btn btn--primary" href="<?php echo esc_url( $product_url ); ?>">
				<?php esc_html_e( 'Ilmoittaudu ja maksa', 'rytkoset-theme' ); ?>
			</a>
		</div>
		<?php
	}
}

if ( ! function_exists( 'rytkoset_theme_event_has_summary_card' ) ) {
	/**
	 * Checks whether an event has visible summary card content.
	 *
	 * @param int $event_id Event post ID.
	 * @return bool
	 */
	function rytkoset_theme_event_has_summary_card( $event_id ) {
		return ! empty( rytkoset_theme_get_event_detail_items( $event_id ) )
			|| '' !== rytkoset_theme_get_event_product_url( $event_id );
	}
}

if ( ! function_exists( 'rytkoset_theme_render_event_summary_card' ) ) {
	/**
	 * Renders the public event summary card.
	 *
	 * @param int $event_id Event post ID.
	 */
	function rytkoset_theme_render_event_summary_card( $event_id ) {
		$items       = rytkoset_theme_get_event_detail_items( $event_id );
		$product_url = rytkoset_theme_get_event_product_url( $event_id );

		if ( empty( $items ) && '' === $product_url ) {
			return;
		}

		$title_id = 'event-summary-card-title-' . absint( $event_id );
		?>
		<aside class="event-summary-card" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<h2 id="<?php echo esc_attr( $title_id ); ?>" class="event-summary-card__title">
				<?php esc_html_e( 'Tapahtuman tiedot', 'rytkoset-theme' ); ?>
			</h2>

			<?php if ( ! empty( $items ) ) : ?>
				<dl class="event-summary-card__details">
					<?php foreach ( $items as $item ) : ?>
						<div class="event-summary-card__item">
							<dt class="event-summary-card__label"><?php echo esc_html( $item['label'] ); ?></dt>
							<dd class="event-summary-card__value">
								<?php if ( ! empty( $item['datetime'] ) ) : ?>
									<time datetime="<?php echo esc_attr( $item['datetime'] ); ?>"><?php echo esc_html( $item['value'] ); ?></time>
								<?php else : ?>
									<?php echo esc_html( $item['value'] ); ?>
								<?php endif; ?>
							</dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<?php if ( '' !== $product_url ) : ?>
				<div class="event-summary-card__cta">
					<a class="btn btn--primary event-summary-card__button" href="<?php echo esc_url( $product_url ); ?>">
						<?php esc_html_e( 'Ilmoittaudu ja maksa', 'rytkoset-theme' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</aside>
		<?php
	}
}
