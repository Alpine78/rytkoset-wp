<?php

declare(strict_types=1);

final class MembershipOverviewAdminTest extends Rytkoset_Theme_Test_Case {
	public function test_status_distinguishes_active_expired_and_incomplete_memberships(): void {
		$this->assertSame( 'active', rytkoset_theme_get_membership_overview_status( array( 'type' => 'lifetime' ), '2026-07-11' ) );
		$this->assertSame(
			'active',
			rytkoset_theme_get_membership_overview_status(
				array(
					'type'    => 'annual',
					'expires' => '2026-07-11',
				),
				'2026-07-11'
			)
		);
		$this->assertSame(
			'expired',
			rytkoset_theme_get_membership_overview_status(
				array(
					'type'    => 'family',
					'expires' => '2026-07-10',
				),
				'2026-07-11'
			)
		);
		$this->assertSame(
			'incomplete',
			rytkoset_theme_get_membership_overview_status(
				array(
					'type'    => 'annual',
					'expires' => '',
				),
				'2026-07-11'
			)
		);
	}

	public function test_filter_searches_name_and_email_and_combines_filters(): void {
		$rows = array(
			array(
				'name'   => 'Matti Rytkönen',
				'email'  => 'matti@example.com',
				'status' => 'active',
				'type'   => 'annual',
			),
			array(
				'name'   => 'Liisa Rytkönen',
				'email'  => 'liisa@example.com',
				'status' => 'pending_account',
				'type'   => 'family',
			),
			array(
				'name'   => '',
				'email'  => 'paper@example.com',
				'status' => 'pending_account',
				'type'   => 'lifetime',
			),
		);

		$this->assertCount( 1, rytkoset_theme_filter_membership_overview_rows( $rows, array( 'search' => 'MATTI' ) ) );
		$this->assertCount( 1, rytkoset_theme_filter_membership_overview_rows( $rows, array( 'search' => 'paper@' ) ) );
		$this->assertCount(
			1,
			rytkoset_theme_filter_membership_overview_rows(
				$rows,
				array(
					'status' => 'pending_account',
					'type'   => 'family',
				)
			)
		);
	}

	public function test_filter_sorts_rows_by_name_then_email(): void {
		$rows = rytkoset_theme_filter_membership_overview_rows(
			array(
				array(
					'name'   => 'Östen',
					'email'  => 'o@example.com',
					'status' => 'active',
					'type'   => 'annual',
				),
				array(
					'name'   => 'Aino',
					'email'  => 'a@example.com',
					'status' => 'active',
					'type'   => 'annual',
				),
			),
			array()
		);

		$this->assertSame( 'Aino', $rows[0]['name'] );
	}

	public function test_pagination_clamps_page_and_returns_totals(): void {
		$rows  = array_fill( 0, 51, array( 'name' => 'Jäsen' ) );
		$paged = rytkoset_theme_paginate_membership_overview_rows( $rows, 2, 50 );

		$this->assertCount( 1, $paged['items'] );
		$this->assertSame( 51, $paged['total'] );
		$this->assertSame( 2, $paged['total_pages'] );
		$this->assertSame( 2, $paged['page'] );
	}

	public function test_status_variant_and_incomplete_reason(): void {
		$this->assertSame( 'success', rytkoset_theme_get_membership_overview_status_variant( 'active' ) );
		$this->assertSame( 'warning', rytkoset_theme_get_membership_overview_status_variant( 'pending_account' ) );
		$this->assertSame( 'error', rytkoset_theme_get_membership_overview_status_variant( 'expired' ) );
		$this->assertSame( 'error', rytkoset_theme_get_membership_overview_status_variant( 'incomplete' ) );
		$this->assertSame( 'neutral', rytkoset_theme_get_membership_overview_status_variant( 'unknown' ) );

		$this->assertSame( '', rytkoset_theme_get_membership_overview_incomplete_reason( array( 'status' => 'active', 'type' => 'lifetime' ) ) );
		$this->assertSame( 'Jäsenyyden tyyppi puuttuu', rytkoset_theme_get_membership_overview_incomplete_reason( array( 'status' => 'incomplete', 'type' => '' ) ) );
		$this->assertSame( 'Voimassaolopäivä puuttuu', rytkoset_theme_get_membership_overview_incomplete_reason( array( 'status' => 'incomplete', 'type' => 'annual' ) ) );
	}

	public function test_status_counts_and_family_counts(): void {
		$rows = array(
			array( 'status' => 'active', 'primary_user_id' => 0 ),
			array( 'status' => 'active', 'primary_user_id' => 7 ),
			array( 'status' => 'pending_account', 'primary_user_id' => 7 ),
			array( 'status' => 'incomplete', 'primary_user_id' => 0 ),
		);

		$this->assertSame(
			array(
				''                => 4,
				'active'          => 2,
				'expired'         => 0,
				'incomplete'      => 1,
				'pending_account' => 1,
			),
			rytkoset_theme_get_membership_overview_status_counts( $rows )
		);
		$this->assertSame( array( 7 => 2 ), rytkoset_theme_get_membership_overview_family_counts( $rows ) );
	}

	public function test_csv_rows_have_header_labels_and_neutralize_formulas(): void {
		$lines = rytkoset_theme_get_membership_overview_csv_rows(
			array(
				rytkoset_theme_normalize_membership_overview_row(
					array(
						'name'    => '=HYPERLINK("x")',
						'email'   => 'jasen@example.test',
						'status'  => 'active',
						'type'    => 'lifetime',
						'expires' => '',
						'source'  => 'own',
					)
				),
			)
		);

		$this->assertCount( 2, $lines );
		$this->assertSame( 'Nimi', $lines[0][0] );
		$this->assertStringStartsWith( "'", $lines[1][0] );
		$this->assertSame( 'jasen@example.test', $lines[1][1] );
		$this->assertSame( 'Aktiivinen', $lines[1][2] );
		$this->assertSame( 'Oma jäsenyys', $lines[1][6] );
	}

	public function test_identity_leads_with_email_for_rows_without_account(): void {
		$this->assertSame(
			array(
				'primary' => 'Matti',
				'sub'     => 'matti@example.test',
			),
			rytkoset_theme_get_membership_overview_identity( array( 'user_id' => 5, 'name' => 'Matti', 'email' => 'matti@example.test' ) )
		);
		$this->assertSame(
			array(
				'primary' => 'liisa@example.test',
				'sub'     => 'Liisa · Ei vielä käyttäjätiliä',
			),
			rytkoset_theme_get_membership_overview_identity( array( 'user_id' => 0, 'name' => 'Liisa', 'email' => 'liisa@example.test' ) )
		);
		$this->assertSame(
			array(
				'primary' => 'Emmi',
				'sub'     => 'Ei vielä käyttäjätiliä',
			),
			rytkoset_theme_get_membership_overview_identity( array( 'user_id' => 0, 'name' => 'Emmi', 'email' => '' ) )
		);
	}

	public function test_incomplete_inherited_row_is_fixed_on_primary_account(): void {
		$inherited = array( 'status' => 'incomplete', 'type' => 'family', 'source' => 'inherited', 'user_id' => 12, 'primary_user_id' => 7 );
		$own       = array( 'status' => 'incomplete', 'type' => 'annual', 'source' => 'own', 'user_id' => 12, 'primary_user_id' => 0 );

		$this->assertSame( array( 'target' => 'fix_primary', 'user_id' => 7 ), rytkoset_theme_get_membership_overview_row_action( $inherited ) );
		$this->assertSame( array( 'target' => 'fix_profile', 'user_id' => 12 ), rytkoset_theme_get_membership_overview_row_action( $own ) );
		$this->assertSame( array( 'target' => 'profile', 'user_id' => 12 ), rytkoset_theme_get_membership_overview_row_action( array( 'status' => 'active', 'type' => 'lifetime', 'source' => 'own', 'user_id' => 12 ) ) );
		$this->assertSame( array( 'target' => 'activation', 'user_id' => 0 ), rytkoset_theme_get_membership_overview_row_action( array( 'status' => 'pending_account', 'type' => 'lifetime', 'source' => 'manual_pending', 'user_id' => 0 ) ) );
		$this->assertSame( array( 'target' => 'primary_profile', 'user_id' => 7 ), rytkoset_theme_get_membership_overview_row_action( array( 'status' => 'pending_account', 'type' => 'family', 'source' => 'family_pending', 'user_id' => 0, 'primary_user_id' => 7 ) ) );
	}

	public function test_csv_line_keeps_crafted_name_in_one_neutralized_cell(): void {
		$name  = 'Matti \\";=1+1;"';
		$lines = rytkoset_theme_get_membership_overview_csv_rows(
			array(
				rytkoset_theme_normalize_membership_overview_row(
					array(
						'name'   => $name,
						'email'  => 'matti@example.test',
						'status' => 'active',
						'type'   => 'lifetime',
						'source' => 'own',
					)
				),
			)
		);

		$handle = fopen( 'php://memory', 'w+' );
		rytkoset_theme_write_membership_overview_csv_line( $handle, $lines[1] );
		rewind( $handle );
		$read = fgetcsv( $handle, null, ';', '"', '' );
		fclose( $handle );

		$this->assertCount( 8, $read );
		$this->assertSame( $lines[1][0], $read[0] );
		$this->assertSame( 'matti@example.test', $read[1] );
	}
}
