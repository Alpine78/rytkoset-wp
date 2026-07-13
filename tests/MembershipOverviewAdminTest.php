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
}
