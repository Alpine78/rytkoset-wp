<?php
/**
 * Read-only Users > Verkkojäsenyydet overview (#534).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the overview status options.
 *
 * @return array<string, string>
 */
function rytkoset_theme_get_membership_overview_status_options() {
	return array(
		'active'          => __( 'Aktiivinen', 'rytkoset-theme' ),
		'expired'         => __( 'Vanhentunut', 'rytkoset-theme' ),
		'incomplete'      => __( 'Puutteellinen', 'rytkoset-theme' ),
		'pending_account' => __( 'Odottaa käyttäjätiliä', 'rytkoset-theme' ),
	);
}

/**
 * Resolves a stored membership's overview status.
 *
 * @param array<string, string> $membership Membership details.
 * @param string                $today      Current date in Y-m-d format.
 * @return string
 */
function rytkoset_theme_get_membership_overview_status( $membership, $today ) {
	$type    = rytkoset_theme_normalize_user_membership_type( (string) ( $membership['type'] ?? '' ) );
	$expires = (string) ( $membership['expires'] ?? '' );

	if ( '' === $type ) {
		return 'incomplete';
	}

	if ( 'lifetime' === $type ) {
		return 'active';
	}

	if ( ! rytkoset_theme_user_membership_type_is_time_bound( $type ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
		return 'incomplete';
	}

	return $expires >= $today ? 'active' : 'expired';
}

/**
 * Normalizes one overview row.
 *
 * @param array<string, mixed> $row Raw row.
 * @return array<string, mixed>
 */
function rytkoset_theme_normalize_membership_overview_row( $row ) {
	$status_options = rytkoset_theme_get_membership_overview_status_options();
	$status         = sanitize_key( (string) ( $row['status'] ?? '' ) );
	$type           = rytkoset_theme_normalize_user_membership_type( (string) ( $row['type'] ?? '' ) );

	return array(
		'name'            => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
		'email'           => rytkoset_theme_normalize_family_member_email( (string) ( $row['email'] ?? '' ) ),
		'status'          => isset( $status_options[ $status ] ) ? $status : 'incomplete',
		'type'            => $type,
		'period'          => sanitize_text_field( (string) ( $row['period'] ?? '' ) ),
		'expires'         => sanitize_text_field( (string) ( $row['expires'] ?? '' ) ),
		'source'          => sanitize_key( (string) ( $row['source'] ?? '' ) ),
		'user_id'         => absint( $row['user_id'] ?? 0 ),
		'primary_user_id' => absint( $row['primary_user_id'] ?? 0 ),
	);
}

/**
 * Filters and sorts normalized overview rows.
 *
 * @param array<int, array<string, mixed>> $rows    Overview rows.
 * @param array<string, string>            $filters Search, status and type filters.
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_filter_membership_overview_rows( $rows, $filters ) {
	$search  = strtolower( trim( sanitize_text_field( (string) ( $filters['search'] ?? '' ) ) ) );
	$status  = sanitize_key( (string) ( $filters['status'] ?? '' ) );
	$type    = rytkoset_theme_normalize_user_membership_type( (string) ( $filters['type'] ?? '' ) );
	$results = array();

	foreach ( $rows as $raw_row ) {
		$row = rytkoset_theme_normalize_membership_overview_row( $raw_row );

		if ( '' !== $search ) {
			$haystack = strtolower( $row['name'] . ' ' . $row['email'] );
			if ( false === strpos( $haystack, $search ) ) {
				continue;
			}
		}

		if ( '' !== $status && $row['status'] !== $status ) {
			continue;
		}

		if ( '' !== $type && $row['type'] !== $type ) {
			continue;
		}

		$results[] = $row;
	}

	usort(
		$results,
		static function ( $left, $right ) {
			$left_key  = strtolower( (string) $left['name'] . ' ' . (string) $left['email'] );
			$right_key = strtolower( (string) $right['name'] . ' ' . (string) $right['email'] );
			return strcmp( $left_key, $right_key );
		}
	);

	return $results;
}

/**
 * Paginates overview rows.
 *
 * @param array<int, array<string, mixed>> $rows     Filtered rows.
 * @param int                              $page     One-based page number.
 * @param int                              $per_page Rows per page.
 * @return array{items:array<int, array<string, mixed>>,total:int,total_pages:int,page:int,per_page:int}
 */
function rytkoset_theme_paginate_membership_overview_rows( $rows, $page, $per_page ) {
	$total       = count( $rows );
	$per_page    = max( 1, absint( $per_page ) );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$page        = min( max( 1, absint( $page ) ), $total_pages );

	return array(
		'items'       => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
		'total'       => $total,
		'total_pages' => $total_pages,
		'page'        => $page,
		'per_page'    => $per_page,
	);
}

/**
 * Builds overview rows from the current membership stores.
 *
 * User meta is primed by get_users(), avoiding row-by-row database queries.
 *
 * @return array<int, array<string, mixed>>
 */
function rytkoset_theme_get_membership_overview_rows() {
	$users = get_users(
		array(
			'fields'     => 'all',
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The overview intentionally finds the three existing membership meta sources.
				'relation' => 'OR',
				array(
					'key'     => rytkoset_theme_get_user_membership_type_meta_key(),
					'compare' => 'EXISTS',
				),
				array(
					'key'     => rytkoset_theme_get_family_members_meta_key(),
					'compare' => 'EXISTS',
				),
				array(
					'key'     => rytkoset_theme_get_family_primary_user_meta_key(),
					'compare' => 'EXISTS',
				),
			),
		)
	);
	$rows  = array();
	$today = current_datetime()->format( 'Y-m-d' );

	foreach ( $users as $user ) {
		if ( ! $user instanceof WP_User ) {
			continue;
		}

		$membership = rytkoset_theme_get_user_membership( $user->ID );
		if ( '' !== $membership['type'] ) {
			$rows[] = array(
				'name'            => $user->display_name,
				'email'           => $user->user_email,
				'status'          => rytkoset_theme_get_membership_overview_status( $membership, $today ),
				'type'            => $membership['type'],
				'period'          => $membership['period'],
				'expires'         => $membership['expires'],
				'source'          => 'own',
				'user_id'         => $user->ID,
				'primary_user_id' => 0,
			);
		}

		$primary_membership = rytkoset_theme_get_user_family_membership( $user->ID );
		foreach ( rytkoset_theme_get_family_members( $user->ID ) as $family_member ) {
			if ( 'removed' === $family_member['status'] ) {
				continue;
			}

			$linked_user      = $family_member['linked_user_id'] > 0 ? get_userdata( $family_member['linked_user_id'] ) : false;
			$is_linked        = 'active' === $family_member['status'] && $linked_user instanceof WP_User;
			$inherited_status = rytkoset_theme_get_membership_overview_status( $primary_membership, $today );
			$rows[]           = array(
				'name'            => $is_linked ? $linked_user->display_name : $family_member['name'],
				'email'           => $is_linked ? $linked_user->user_email : $family_member['email'],
				'status'          => $is_linked ? $inherited_status : 'pending_account',
				'type'            => 'family',
				'period'          => $primary_membership['period'],
				'expires'         => $primary_membership['expires'],
				'source'          => $is_linked ? 'inherited' : 'family_pending',
				'user_id'         => $is_linked ? $linked_user->ID : 0,
				'primary_user_id' => $user->ID,
			);
		}
	}

	foreach ( rytkoset_theme_get_pending_manual_memberships() as $email => $membership ) {
		$rows[] = array(
			'name'            => '',
			'email'           => $email,
			'status'          => 'pending_account',
			'type'            => $membership['type'],
			'period'          => $membership['period'],
			'expires'         => $membership['expires'],
			'source'          => 'manual_pending',
			'user_id'         => 0,
			'primary_user_id' => 0,
		);
	}

	return $rows;
}

/**
 * Returns the overview admin page slug.
 *
 * @return string
 */
function rytkoset_theme_get_membership_overview_admin_page_slug() {
	return 'rytkoset-memberships';
}

/**
 * Registers Users > Verkkojäsenyydet.
 *
 * @return void
 */
function rytkoset_theme_register_membership_overview_admin_page() {
	add_users_page(
		__( 'Verkkojäsenyydet', 'rytkoset-theme' ),
		__( 'Verkkojäsenyydet', 'rytkoset-theme' ),
		'edit_users',
		rytkoset_theme_get_membership_overview_admin_page_slug(),
		'rytkoset_theme_render_membership_overview_admin_page'
	);
}
add_action( 'admin_menu', 'rytkoset_theme_register_membership_overview_admin_page' );

/**
 * Returns a human-readable source label.
 *
 * @param string $source Source key.
 * @return string
 */
function rytkoset_theme_get_membership_overview_source_label( $source ) {
	$labels = array(
		'own'            => __( 'Oma jäsenyys', 'rytkoset-theme' ),
		'inherited'      => __( 'Peritty perhejäsenyys', 'rytkoset-theme' ),
		'manual_pending' => __( 'Jäsenten aktivointi', 'rytkoset-theme' ),
		'family_pending' => __( 'Perhejäsenrivi', 'rytkoset-theme' ),
	);

	return $labels[ $source ] ?? __( 'Tuntematon', 'rytkoset-theme' );
}

/**
 * Maps an overview status to a status badge variant (#697).
 *
 * @param string $status Overview status key.
 * @return string success|warning|error|neutral
 */
function rytkoset_theme_get_membership_overview_status_variant( $status ) {
	$map = array(
		'active'          => 'success',
		'pending_account' => 'warning',
		'expired'         => 'error',
		'incomplete'      => 'error',
	);

	return $map[ $status ] ?? 'neutral';
}

/**
 * Explains why an overview row is incomplete, so the admin knows what to fix (#697).
 *
 * @param array<string, mixed> $row Normalized overview row.
 * @return string Empty when the row is not incomplete.
 */
function rytkoset_theme_get_membership_overview_incomplete_reason( $row ) {
	if ( 'incomplete' !== ( $row['status'] ?? '' ) ) {
		return '';
	}

	if ( '' === (string) ( $row['type'] ?? '' ) ) {
		return __( 'Jäsenyyden tyyppi puuttuu', 'rytkoset-theme' );
	}

	return __( 'Voimassaolopäivä puuttuu', 'rytkoset-theme' );
}

/**
 * Returns the main text and secondary line identifying an overview row (#697).
 *
 * Rows without an account lead with the email address, because that is what an
 * account will later be matched against; the name moves to the secondary line.
 *
 * @param array<string, mixed> $row Normalized overview row.
 * @return array{primary:string,sub:string}
 */
function rytkoset_theme_get_membership_overview_identity( $row ) {
	$name  = (string) ( $row['name'] ?? '' );
	$email = (string) ( $row['email'] ?? '' );

	if ( absint( $row['user_id'] ?? 0 ) > 0 ) {
		return array(
			'primary' => '' !== $name ? $name : $email,
			'sub'     => '' !== $name ? $email : '',
		);
	}

	$no_account = __( 'Ei vielä käyttäjätiliä', 'rytkoset-theme' );

	return array(
		'primary' => '' !== $email ? $email : $name,
		'sub'     => '' !== $email && '' !== $name ? $name . ' · ' . $no_account : $no_account,
	);
}

/**
 * Picks the row action that leads to where a row is fixed or managed (#697).
 *
 * An inherited family benefit is stored on the primary account, so an
 * incomplete inherited row is fixed in the primary account's profile.
 *
 * @param array<string, mixed> $row Normalized overview row.
 * @return array{target:string,user_id:int} Target is profile, fix_profile, fix_primary, primary_profile, activation or ''.
 */
function rytkoset_theme_get_membership_overview_row_action( $row ) {
	$user_id    = absint( $row['user_id'] ?? 0 );
	$primary_id = absint( $row['primary_user_id'] ?? 0 );
	$incomplete = '' !== rytkoset_theme_get_membership_overview_incomplete_reason( $row );
	$source     = (string) ( $row['source'] ?? '' );

	if ( $incomplete && 'inherited' === $source && $primary_id > 0 ) {
		return array(
			'target'  => 'fix_primary',
			'user_id' => $primary_id,
		);
	}

	if ( $user_id > 0 ) {
		return array(
			'target'  => $incomplete ? 'fix_profile' : 'profile',
			'user_id' => $user_id,
		);
	}

	if ( 'manual_pending' === $source ) {
		return array(
			'target'  => 'activation',
			'user_id' => 0,
		);
	}

	if ( $primary_id > 0 ) {
		return array(
			'target'  => 'primary_profile',
			'user_id' => $primary_id,
		);
	}

	return array(
		'target'  => '',
		'user_id' => 0,
	);
}

/**
 * Counts rows per overview status for the status chips (#697).
 *
 * @param array<int, array<string, mixed>> $rows Normalized rows (search and type filters applied).
 * @return array<string, int> Status key => count; '' holds the total.
 */
function rytkoset_theme_get_membership_overview_status_counts( $rows ) {
	$counts = array( '' => 0 );

	foreach ( array_keys( rytkoset_theme_get_membership_overview_status_options() ) as $status ) {
		$counts[ $status ] = 0;
	}

	foreach ( $rows as $row ) {
		$status = (string) ( $row['status'] ?? '' );
		++$counts[''];

		if ( isset( $counts[ $status ] ) ) {
			++$counts[ $status ];
		}
	}

	return $counts;
}

/**
 * Counts family rows per primary account (#697).
 *
 * @param array<int, array<string, mixed>> $rows Overview rows.
 * @return array<int, int> Primary user ID => number of family rows.
 */
function rytkoset_theme_get_membership_overview_family_counts( $rows ) {
	$counts = array();

	foreach ( $rows as $row ) {
		$primary = absint( $row['primary_user_id'] ?? 0 );

		if ( $primary > 0 ) {
			$counts[ $primary ] = ( $counts[ $primary ] ?? 0 ) + 1;
		}
	}

	return $counts;
}

/**
 * Builds the CSV header and rows for the overview export (#697).
 *
 * @param array<int, array<string, mixed>> $rows Normalized rows.
 * @return array<int, array<int, string>>
 */
function rytkoset_theme_get_membership_overview_csv_rows( $rows ) {
	$status_options = rytkoset_theme_get_membership_overview_status_options();
	$lines          = array(
		array(
			__( 'Nimi', 'rytkoset-theme' ),
			__( 'Sähköposti', 'rytkoset-theme' ),
			__( 'Tila', 'rytkoset-theme' ),
			__( 'Jäsenyyden tyyppi', 'rytkoset-theme' ),
			__( 'Kausi', 'rytkoset-theme' ),
			__( 'Voimassa asti', 'rytkoset-theme' ),
			__( 'Lähde', 'rytkoset-theme' ),
			__( 'Päätili', 'rytkoset-theme' ),
		),
	);

	foreach ( $rows as $row ) {
		$primary      = absint( $row['primary_user_id'] ?? 0 );
		$primary_user = $primary > 0 ? get_userdata( $primary ) : false;
		$lines[]      = array_map(
			'rytkoset_theme_csv_neutralize_formula',
			array(
				(string) ( $row['name'] ?? '' ),
				(string) ( $row['email'] ?? '' ),
				(string) ( $status_options[ $row['status'] ] ?? '' ),
				'' !== (string) ( $row['type'] ?? '' ) ? rytkoset_theme_get_user_membership_type_label( (string) $row['type'] ) : '',
				(string) ( $row['period'] ?? '' ),
				'' !== (string) ( $row['expires'] ?? '' ) ? rytkoset_theme_get_user_membership_expires_display( (string) $row['expires'] ) : '',
				rytkoset_theme_get_membership_overview_source_label( (string) ( $row['source'] ?? '' ) ),
				$primary_user instanceof WP_User ? $primary_user->display_name : '',
			)
		);
	}

	return $lines;
}

/**
 * Writes one overview CSV line (#697).
 *
 * The empty escape character keeps RFC 4180 quoting: with PHP's default
 * backslash escape a crafted name could split into extra cells and smuggle an
 * un-neutralized formula past rytkoset_theme_csv_neutralize_formula().
 *
 * @param resource           $handle Open file handle.
 * @param array<int, string> $line   Cells.
 * @return void
 */
function rytkoset_theme_write_membership_overview_csv_line( $handle, $line ) {
	fputcsv( $handle, $line, ';', '"', '' );
}

/**
 * Returns the overview URL with the given filters, dropping empty values.
 *
 * @param array<string, string> $args Query arguments.
 * @return string
 */
function rytkoset_theme_get_membership_overview_url( $args ) {
	return add_query_arg(
		array_filter(
			array_merge( array( 'page' => rytkoset_theme_get_membership_overview_admin_page_slug() ), $args ),
			static function ( $value ) {
				return '' !== (string) $value;
			}
		),
		admin_url( 'users.php' )
	);
}

/**
 * Renders the read-only membership overview page.
 *
 * @return void
 */
function rytkoset_theme_render_membership_overview_admin_page() {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta tarkastella verkkojäsenyyksiä.', 'rytkoset-theme' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters do not change state.
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$status = isset( $_GET['membership_status'] ) ? sanitize_key( wp_unslash( $_GET['membership_status'] ) ) : '';
	$type   = isset( $_GET['membership_type'] ) ? rytkoset_theme_normalize_user_membership_type( sanitize_key( wp_unslash( $_GET['membership_type'] ) ) ) : '';
	$page   = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$status_options = rytkoset_theme_get_membership_overview_status_options();
	$status         = isset( $status_options[ $status ] ) ? $status : '';
	$all_rows       = rytkoset_theme_get_membership_overview_rows();
	$family_counts  = rytkoset_theme_get_membership_overview_family_counts( $all_rows );
	$base_rows      = rytkoset_theme_filter_membership_overview_rows(
		$all_rows,
		array(
			'search' => $search,
			'type'   => $type,
		)
	);
	$counts         = rytkoset_theme_get_membership_overview_status_counts( $base_rows );
	$rows           = rytkoset_theme_filter_membership_overview_rows(
		$all_rows,
		array(
			'search' => $search,
			'status' => $status,
			'type'   => $type,
		)
	);
	$paged          = rytkoset_theme_paginate_membership_overview_rows( $rows, $page, 50 );
	$chips          = array( '' => __( 'Kaikki', 'rytkoset-theme' ) ) + $status_options;
	?>
	<div class="wrap rytkoset-memberships-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Verkkojäsenyydet', 'rytkoset-theme' ); ?></h1>
		<hr class="wp-header-end" />
		<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Tämä näkymä ei ole virallinen eikä täydellinen jäsenrekisteri.', 'rytkoset-theme' ); ?></strong> <?php esc_html_e( 'Mukana ovat vain henkilöt, joilla on käyttäjätili, odottava verkkojäsenyys tai perhejäsenrivi tällä sivustolla.', 'rytkoset-theme' ); ?></p></div>

		<ul class="subsubsub">
			<?php foreach ( $chips as $chip_status => $chip_label ) : ?>
				<?php
				$chip_url = rytkoset_theme_get_membership_overview_url(
					array(
						's'                 => $search,
						'membership_type'   => $type,
						'membership_status' => $chip_status,
					)
				);
				?>
				<li><a href="<?php echo esc_url( $chip_url ); ?>"<?php echo $chip_status === $status ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $chip_label ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $counts[ $chip_status ] ?? 0 ) ); ?>)</span></a></li>
			<?php endforeach; ?>
		</ul>
		<br class="clear" />

		<div class="tablenav top">
			<div class="alignleft ra-toolbar">
				<form method="get" class="ra-actions">
					<input type="hidden" name="page" value="<?php echo esc_attr( rytkoset_theme_get_membership_overview_admin_page_slug() ); ?>" />
					<?php if ( '' !== $status ) : ?>
						<input type="hidden" name="membership_status" value="<?php echo esc_attr( $status ); ?>" />
					<?php endif; ?>
					<label class="screen-reader-text" for="rytkoset-membership-search"><?php esc_html_e( 'Hae nimellä tai sähköpostilla', 'rytkoset-theme' ); ?></label>
					<input id="rytkoset-membership-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Nimi tai sähköposti', 'rytkoset-theme' ); ?>" />
					<label class="screen-reader-text" for="rytkoset-membership-type"><?php esc_html_e( 'Suodata jäsenyyden tyypin mukaan', 'rytkoset-theme' ); ?></label>
					<select id="rytkoset-membership-type" name="membership_type">
						<option value=""><?php esc_html_e( 'Kaikki jäsenyystyypit', 'rytkoset-theme' ); ?></option>
						<?php foreach ( rytkoset_theme_get_user_membership_type_options() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Suodata', 'rytkoset-theme' ), 'secondary', 'filter_action', false ); ?>
					<?php if ( '' !== $search || '' !== $status || '' !== $type ) : ?>
						<a href="<?php echo esc_url( rytkoset_theme_get_membership_overview_url( array() ) ); ?>"><?php esc_html_e( 'Tyhjennä suodattimet', 'rytkoset-theme' ); ?></a>
					<?php endif; ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rytkoset_export_membership_overview_csv" />
					<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />
					<input type="hidden" name="membership_status" value="<?php echo esc_attr( $status ); ?>" />
					<input type="hidden" name="membership_type" value="<?php echo esc_attr( $type ); ?>" />
					<?php wp_nonce_field( 'rytkoset_export_membership_overview_csv', 'rytkoset_membership_overview_csv_nonce' ); ?>
					<?php submit_button( __( 'Vie CSV', 'rytkoset-theme' ), 'secondary', '', false ); ?>
				</form>
			</div>
			<div class="tablenav-pages one-page"><span class="displaying-num"><?php echo esc_html( sprintf( /* translators: %s: number of membership rows */ _n( '%s rivi', '%s riviä', $paged['total'], 'rytkoset-theme' ), number_format_i18n( $paged['total'] ) ) ); ?></span></div>
			<br class="clear" />
		</div>

		<?php // Explicit roles keep table semantics when the cells are restyled as cards on narrow screens. ?>
		<table class="widefat striped ra-responsive-table" role="table">
			<thead role="rowgroup">
				<tr role="row">
					<th role="columnheader" scope="col" class="column-primary"><?php esc_html_e( 'Jäsen', 'rytkoset-theme' ); ?></th>
					<th role="columnheader" scope="col"><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></th>
					<th role="columnheader" scope="col"><?php esc_html_e( 'Jäsenyys', 'rytkoset-theme' ); ?></th>
					<th role="columnheader" scope="col"><?php esc_html_e( 'Voimassa', 'rytkoset-theme' ); ?></th>
					<th role="columnheader" scope="col"><?php esc_html_e( 'Lähde', 'rytkoset-theme' ); ?></th>
				</tr>
			</thead>
			<tbody role="rowgroup">
				<?php if ( empty( $paged['items'] ) ) : ?>
					<tr role="row"><td role="cell" colspan="5"><?php esc_html_e( 'Hakuehdoilla ei löytynyt verkkojäsenyyksiä.', 'rytkoset-theme' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $paged['items'] as $row ) : ?>
					<?php
					$user_id      = (int) $row['user_id'];
					$edit_url     = $user_id > 0 ? get_edit_user_link( $user_id ) : '';
					$identity     = rytkoset_theme_get_membership_overview_identity( $row );
					$reason       = rytkoset_theme_get_membership_overview_incomplete_reason( $row );
					$action       = rytkoset_theme_get_membership_overview_row_action( $row );
					$primary_id   = (int) $row['primary_user_id'];
					$primary_user = $primary_id > 0 ? get_userdata( $primary_id ) : false;
					$family_count = 'own' === $row['source'] ? (int) ( $family_counts[ $user_id ] ?? 0 ) : 0;
					$action_url   = 'activation' === $action['target']
						? rytkoset_theme_get_membership_activation_admin_url()
						: ( $action['user_id'] > 0 ? get_edit_user_link( $action['user_id'] ) : '' );
					$action_label = array(
						'profile'         => __( 'Avaa profiili', 'rytkoset-theme' ),
						'fix_profile'     => __( 'Korjaa profiilissa', 'rytkoset-theme' ),
						'fix_primary'     => __( 'Korjaa päätilin profiilissa', 'rytkoset-theme' ),
						'primary_profile' => __( 'Avaa päätilin profiili', 'rytkoset-theme' ),
						'activation'      => __( 'Avaa jäsenten aktivointi', 'rytkoset-theme' ),
					);
					?>
					<tr role="row">
						<td role="cell" class="column-primary" data-colname="<?php esc_attr_e( 'Jäsen', 'rytkoset-theme' ); ?>">
							<strong>
								<?php if ( '' !== $edit_url ) : ?>
									<a class="row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( '' !== $identity['primary'] ? $identity['primary'] : __( 'Nimetön käyttäjä', 'rytkoset-theme' ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( '' !== $identity['primary'] ? $identity['primary'] : __( 'Nimetön perhejäsen', 'rytkoset-theme' ) ); ?>
								<?php endif; ?>
							</strong>
							<?php if ( '' !== $identity['sub'] ) : ?>
								<span class="ra-sub"><?php echo esc_html( $identity['sub'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $action['target'] && '' !== (string) $action_url ) : ?>
								<div class="row-actions">
									<span><a href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label[ $action['target'] ] ); ?></a></span>
								</div>
							<?php endif; ?>
						</td>
						<td role="cell" data-colname="<?php esc_attr_e( 'Tila', 'rytkoset-theme' ); ?>">
							<span class="ra-cell">
								<span class="ra-badge ra-badge--<?php echo esc_attr( rytkoset_theme_get_membership_overview_status_variant( $row['status'] ) ); ?>"><?php echo esc_html( $status_options[ $row['status'] ] ); ?></span>
								<?php if ( '' !== $reason ) : ?>
									<span class="ra-sub"><?php echo esc_html( $reason ); ?></span>
								<?php endif; ?>
							</span>
						</td>
						<td role="cell" data-colname="<?php esc_attr_e( 'Jäsenyys', 'rytkoset-theme' ); ?>">
							<span class="ra-cell">
								<?php echo esc_html( '' !== $row['type'] ? rytkoset_theme_get_user_membership_type_label( $row['type'] ) : '' ); ?>
								<?php if ( $primary_user instanceof WP_User ) : ?>
									<span class="ra-sub"><?php esc_html_e( 'Päätili:', 'rytkoset-theme' ); ?> <a href="<?php echo esc_url( get_edit_user_link( $primary_id ) ); ?>"><?php echo esc_html( $primary_user->display_name ); ?></a></span>
								<?php elseif ( $family_count > 0 ) : ?>
									<?php /* translators: %d: number of family member rows */ ?>
									<span class="ra-sub"><?php echo esc_html( sprintf( _n( 'Päätili, %d perheenjäsen', 'Päätili, %d perheenjäsentä', $family_count, 'rytkoset-theme' ), $family_count ) ); ?></span>
								<?php endif; ?>
							</span>
						</td>
						<td role="cell" data-colname="<?php esc_attr_e( 'Voimassa', 'rytkoset-theme' ); ?>">
							<span class="ra-cell">
								<?php echo esc_html( 'lifetime' === $row['type'] ? __( 'Toistaiseksi', 'rytkoset-theme' ) : ( '' !== $row['expires'] ? rytkoset_theme_get_user_membership_expires_display( $row['expires'] ) : '' ) ); ?>
								<?php if ( '' !== $row['period'] ) : ?>
									<?php /* translators: %s: membership period, e.g. 2026-2029 */ ?>
									<span class="ra-sub"><?php echo esc_html( sprintf( __( 'Kausi %s', 'rytkoset-theme' ), $row['period'] ) ); ?></span>
								<?php endif; ?>
							</span>
						</td>
						<td role="cell" data-colname="<?php esc_attr_e( 'Lähde', 'rytkoset-theme' ); ?>"><?php echo esc_html( rytkoset_theme_get_membership_overview_source_label( $row['source'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $paged['total_pages'] > 1 ) : ?>
			<div class="tablenav bottom"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged['page'],
						'total'   => $paged['total_pages'],
					)
				)
			);
			?>
			</div></div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Streams the filtered overview as CSV (#697).
 *
 * @return void
 */
function rytkoset_theme_export_membership_overview_csv() {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'Sinulla ei ole oikeutta viedä verkkojäsenyyksiä.', 'rytkoset-theme' ) );
	}

	check_admin_referer( 'rytkoset_export_membership_overview_csv', 'rytkoset_membership_overview_csv_nonce' );

	$rows = rytkoset_theme_filter_membership_overview_rows(
		rytkoset_theme_get_membership_overview_rows(),
		array(
			'search' => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
			'status' => isset( $_POST['membership_status'] ) ? sanitize_key( wp_unslash( $_POST['membership_status'] ) ) : '',
			'type'   => isset( $_POST['membership_type'] ) ? sanitize_key( wp_unslash( $_POST['membership_type'] ) ) : '',
		)
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="verkkojasenyydet-' . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'X-Content-Type-Options: nosniff' );

	$output = fopen( 'php://output', 'w' );

	if ( false === $output ) {
		wp_die( esc_html__( 'CSV-viennin alustaminen epäonnistui.', 'rytkoset-theme' ) );
	}

	// UTF-8 BOM so that Excel detects the encoding.
	echo "\xEF\xBB\xBF";

	foreach ( rytkoset_theme_get_membership_overview_csv_rows( $rows ) as $line ) {
		rytkoset_theme_write_membership_overview_csv_line( $output, $line );
	}

	fclose( $output );
	exit;
}
add_action( 'admin_post_rytkoset_export_membership_overview_csv', 'rytkoset_theme_export_membership_overview_csv' );
