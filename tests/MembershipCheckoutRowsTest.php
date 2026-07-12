<?php
/**
 * Tests for the dynamic membership checkout row server logic (#520).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembershipCheckoutRowsTest extends Rytkoset_Theme_Test_Case {

	public function test_max_member_rows_defaults_to_six_and_is_filterable(): void {
		$this->assertSame( 6, rytkoset_theme_get_membership_max_member_rows() );

		$filter = static fn() => 8;
		add_filter( 'rytkoset_theme_membership_max_member_rows', $filter );

		$this->assertSame( 8, rytkoset_theme_get_membership_max_member_rows() );

		remove_filter( 'rytkoset_theme_membership_max_member_rows', $filter );
	}

	public function test_max_member_rows_filter_cannot_lower_limit_below_one(): void {
		$filter = static fn() => 0;
		add_filter( 'rytkoset_theme_membership_max_member_rows', $filter );

		$this->assertSame( 1, rytkoset_theme_get_membership_max_member_rows() );

		remove_filter( 'rytkoset_theme_membership_max_member_rows', $filter );
	}

	public function test_row_bounds_follow_membership_product_kind(): void {
		$this->assertSame(
			array( 'min' => 0, 'max' => 0 ),
			rytkoset_theme_resolve_membership_member_row_bounds( false, false )
		);
		$this->assertSame(
			array( 'min' => 1, 'max' => 1 ),
			rytkoset_theme_resolve_membership_member_row_bounds( true, false )
		);
		$this->assertSame(
			array( 'min' => 1, 'max' => 6 ),
			rytkoset_theme_resolve_membership_member_row_bounds( true, true )
		);
	}

	public function test_requested_row_count_is_clamped_to_server_bounds(): void {
		$family_bounds = array( 'min' => 1, 'max' => 6 );

		$this->assertSame( 1, rytkoset_theme_clamp_membership_member_rows( 0, $family_bounds ) );
		$this->assertSame( 4, rytkoset_theme_clamp_membership_member_rows( 4, $family_bounds ) );
		$this->assertSame( 6, rytkoset_theme_clamp_membership_member_rows( 99, $family_bounds ) );
		$this->assertSame(
			1,
			rytkoset_theme_clamp_membership_member_rows( 5, array( 'min' => 1, 'max' => 1 ) )
		);
		$this->assertSame(
			0,
			rytkoset_theme_clamp_membership_member_rows( 5, array( 'min' => 0, 'max' => 0 ) )
		);
	}

	public function test_active_and_hidden_schemas_use_member_row_count(): void {
		$active = rytkoset_theme_get_membership_member_active_schema( 3 );
		$count  = $active['properties']['cart']['properties']['extensions']['properties']['rytkoset_membership']['properties']['member_row_count'];
		$hidden = rytkoset_theme_get_membership_member_hidden_schema( 3 );

		$this->assertSame( array( 'type' => 'integer', 'minimum' => 3 ), $count );
		$this->assertSame( $active, $hidden['not'] );
	}

	public function test_store_api_schema_publishes_current_and_maximum_row_counts(): void {
		$schema = rytkoset_theme_get_membership_store_api_cart_schema();

		$this->assertArrayHasKey( 'member_row_count', $schema );
		$this->assertArrayHasKey( 'member_row_max', $schema );
		$this->assertTrue( $schema['member_row_max']['readonly'] );
	}

}
