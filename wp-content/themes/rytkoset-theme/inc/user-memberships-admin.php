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

		$primary_membership = $membership;
		foreach ( rytkoset_theme_get_family_members( $user->ID ) as $family_member ) {
			if ( 'removed' === $family_member['status'] ) {
				continue;
			}

			$linked_user      = $family_member['linked_user_id'] > 0 ? get_userdata( $family_member['linked_user_id'] ) : false;
			$is_linked        = 'active' === $family_member['status'] && $linked_user instanceof WP_User;
			$inherited_status = 'family' === $primary_membership['type']
				? rytkoset_theme_get_membership_overview_status( $primary_membership, $today )
				: 'incomplete';
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
	$rows  = rytkoset_theme_filter_membership_overview_rows(
		rytkoset_theme_get_membership_overview_rows(),
		array(
			'search' => $search,
			'status' => $status,
			'type'   => $type,
		)
	);
	$paged = rytkoset_theme_paginate_membership_overview_rows( $rows, $page, 50 );
	?>
	<div class="wrap rytkoset-memberships-admin">
		<h1><?php esc_html_e( 'Verkkojäsenyydet', 'rytkoset-theme' ); ?></h1>
		<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Tämä näkymä ei ole virallinen eikä täydellinen jäsenrekisteri.', 'rytkoset-theme' ); ?></strong> <?php esc_html_e( 'Mukana ovat vain henkilöt, joilla on käyttäjätili, odottava verkkojäsenyys tai perhejäsenrivi tällä sivustolla.', 'rytkoset-theme' ); ?></p></div>

		<form method="get" class="rytkoset-memberships-admin__filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( rytkoset_theme_get_membership_overview_admin_page_slug() ); ?>" />
			<label class="screen-reader-text" for="rytkoset-membership-search"><?php esc_html_e( 'Hae nimellä tai sähköpostilla', 'rytkoset-theme' ); ?></label>
			<input id="rytkoset-membership-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Nimi tai sähköposti', 'rytkoset-theme' ); ?>" />
			<label class="screen-reader-text" for="rytkoset-membership-status"><?php esc_html_e( 'Suodata tilan mukaan', 'rytkoset-theme' ); ?></label>
			<select id="rytkoset-membership-status" name="membership_status">
				<option value=""><?php esc_html_e( 'Kaikki tilat', 'rytkoset-theme' ); ?></option>
				<?php foreach ( rytkoset_theme_get_membership_overview_status_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="rytkoset-membership-type"><?php esc_html_e( 'Suodata jäsenyyden tyypin mukaan', 'rytkoset-theme' ); ?></label>
			<select id="rytkoset-membership-type" name="membership_type">
				<option value=""><?php esc_html_e( 'Kaikki jäsenyystyypit', 'rytkoset-theme' ); ?></option>
				<?php foreach ( rytkoset_theme_get_user_membership_type_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Suodata', 'rytkoset-theme' ), 'secondary', 'filter_action', false ); ?>
			<?php if ( '' !== $search || '' !== $status || '' !== $type ) : ?>
				<a href="<?php echo esc_url( admin_url( 'users.php?page=' . rytkoset_theme_get_membership_overview_admin_page_slug() ) ); ?>"><?php esc_html_e( 'Tyhjennä suodattimet', 'rytkoset-theme' ); ?></a>
			<?php endif; ?>
		</form>

		<p class="displaying-num"><?php echo esc_html( sprintf( /* translators: %s: number of membership rows */ _n( '%s rivi', '%s riviä', $paged['total'], 'rytkoset-theme' ), number_format_i18n( $paged['total'] ) ) ); ?></p>
		<div class="rytkoset-memberships-admin__table-wrap">
			<table class="widefat striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Nimi', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Sähköposti', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Jäsenyyden tyyppi', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Kausi', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Voimassa asti', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Lähde', 'rytkoset-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Päätili', 'rytkoset-theme' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				if ( empty( $paged['items'] ) ) :
					?>
					<tr><td colspan="8"><?php esc_html_e( 'Hakuehdoilla ei löytynyt verkkojäsenyyksiä.', 'rytkoset-theme' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $paged['items'] as $row ) : ?>
					<tr>
						<td>
						<?php
						if ( $row['user_id'] > 0 ) :
							?>
							<a href="<?php echo esc_url( get_edit_user_link( $row['user_id'] ) ); ?>"><?php echo esc_html( '' !== $row['name'] ? $row['name'] : __( 'Nimetön käyttäjä', 'rytkoset-theme' ) ); ?></a>
							<?php
else :
	?>
							<?php echo esc_html( '' !== $row['name'] ? $row['name'] : '—' ); ?><?php endif; ?></td>
						<td><?php echo esc_html( '' !== $row['email'] ? $row['email'] : '—' ); ?></td>
						<td><span class="rytkoset-memberships-admin__status rytkoset-memberships-admin__status--<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( rytkoset_theme_get_membership_overview_status_options()[ $row['status'] ] ); ?></span></td>
						<td><?php echo esc_html( rytkoset_theme_get_user_membership_type_label( $row['type'] ) ); ?></td>
						<td><?php echo esc_html( '' !== $row['period'] ? $row['period'] : '—' ); ?></td>
						<td><?php echo esc_html( '' !== $row['expires'] ? rytkoset_theme_get_user_membership_expires_display( $row['expires'] ) : '—' ); ?></td>
						<td>
						<?php
						if ( 'manual_pending' === $row['source'] ) :
							?>
							<a href="<?php echo esc_url( rytkoset_theme_get_membership_activation_admin_url() ); ?>"><?php echo esc_html( rytkoset_theme_get_membership_overview_source_label( $row['source'] ) ); ?></a>
							<?php
else :
	?>
							<?php echo esc_html( rytkoset_theme_get_membership_overview_source_label( $row['source'] ) ); ?><?php endif; ?></td>
						<td>
							<?php $primary_user = $row['primary_user_id'] > 0 ? get_userdata( $row['primary_user_id'] ) : false; ?>
							<?php if ( $primary_user instanceof WP_User ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $row['primary_user_id'] ) ); ?>"><?php echo esc_html( $primary_user->display_name ); ?></a>
								<?php
							else :
								?>
								—<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $paged['total_pages'] > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
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
 * Adds narrowly scoped layout styles to the overview admin page.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return void
 */
function rytkoset_theme_membership_overview_admin_styles( $hook_suffix ) {
	if ( 'users_page_' . rytkoset_theme_get_membership_overview_admin_page_slug() !== $hook_suffix ) {
		return;
	}
	?>
	<style>
		.rytkoset-memberships-admin__filters { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0; }
		.rytkoset-memberships-admin__filters .button { margin: 0; }
		.rytkoset-memberships-admin__table-wrap { overflow-x: auto; }
		.rytkoset-memberships-admin__table-wrap table { min-width: 920px; }
		.rytkoset-memberships-admin__status { border-radius: 999px; display: inline-block; font-weight: 600; padding: 2px 8px; white-space: nowrap; }
		.rytkoset-memberships-admin__status--active { background: #d7f0df; color: #14532d; }
		.rytkoset-memberships-admin__status--expired, .rytkoset-memberships-admin__status--incomplete { background: #fbe2e2; color: #7f1d1d; }
		.rytkoset-memberships-admin__status--pending_account { background: #fff1c2; color: #713f12; }
		@media (prefers-color-scheme: dark) {
			.rytkoset-memberships-admin__status--active { background: #173f2a; color: #c9f7d5; }
			.rytkoset-memberships-admin__status--expired, .rytkoset-memberships-admin__status--incomplete { background: #4c2020; color: #ffd2d2; }
			.rytkoset-memberships-admin__status--pending_account { background: #4a3711; color: #ffe7a0; }
		}
	</style>
	<?php
}
add_action( 'admin_head', 'rytkoset_theme_membership_overview_admin_styles' );
