<?php
/**
 * Tests for inc/event-registration-summary.php — the registration situation summary
 * shared by the free and paid organizer notifications (#643).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventRegistrationSummaryTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Registers an event, optionally linked to a WooCommerce product.
	 *
	 * @param int $id         Event post ID.
	 * @param int $product_id Linked product ID (0 for none).
	 * @return void
	 */
	private function event( int $id, int $product_id = 0 ): void {
		rytkoset_test_register_post( $id, 'rytkoset_event', 'Sukujuhla', 0 );

		if ( $product_id > 0 ) {
			update_post_meta( $id, rytkoset_theme_get_event_product_meta_key(), (string) $product_id );
		}
	}

	/**
	 * Registers a free event registration.
	 *
	 * @param int                  $id        Registration post ID.
	 * @param int                  $event_id  Event post ID.
	 * @param array<string,string> $overrides Meta alias => value pairs.
	 * @return void
	 */
	private function registration( int $id, int $event_id, array $overrides = array() ): void {
		rytkoset_test_register_post( $id, 'event_registration', 'Ilmoittautuminen', 0 );

		$keys   = rytkoset_theme_get_event_registration_meta_keys();
		$values = array_merge(
			array(
				'event_id' => (string) $event_id,
				'name'     => 'Maija Meikäläinen',
				'email'    => 'maija@example.test',
				'status'   => 'confirmed',
			),
			$overrides
		);

		foreach ( $values as $alias => $value ) {
			update_post_meta( $id, $keys[ $alias ], $value );
		}
	}

	// --- active order statuses ----------------------------------------------

	public function test_active_order_statuses_drop_dead_orders(): void {
		$statuses = rytkoset_theme_get_event_summary_active_order_statuses();

		$this->assertContains( 'processing', $statuses );
		$this->assertContains( 'completed', $statuses );
		$this->assertContains( 'pending', $statuses );
		$this->assertNotContains( 'cancelled', $statuses );
		$this->assertNotContains( 'refunded', $statuses );
		$this->assertNotContains( 'failed', $statuses );
		$this->assertNotContains( 'checkout-draft', $statuses );
	}

	// --- free registration counting -----------------------------------------

	public function test_free_count_skips_cancelled_registrations(): void {
		$this->event( 10 );
		$this->registration( 501, 10 );
		$this->registration( 502, 10, array( 'status' => 'pending' ) );
		$this->registration( 503, 10, array( 'status' => 'cancelled' ) );

		$this->assertSame( 2, rytkoset_theme_count_event_free_registrations( 10 ) );
	}

	public function test_free_count_uses_quantity_when_the_event_collects_it(): void {
		$this->event( 10 );
		update_post_meta( 10, rytkoset_theme_get_event_collect_quantity_meta_key(), 'yes' );

		$this->registration( 501, 10, array( 'quantity' => '3' ) );
		$this->registration( 502, 10, array( 'quantity' => '' ) );

		// 3 + a missing quantity that still counts as one person.
		$this->assertSame( 4, rytkoset_theme_count_event_free_registrations( 10 ) );
	}

	public function test_free_count_ignores_other_events(): void {
		$this->event( 10 );
		$this->event( 11 );
		$this->registration( 501, 10 );
		$this->registration( 502, 11 );

		$this->assertSame( 1, rytkoset_theme_count_event_free_registrations( 10 ) );
	}

	public function test_free_count_is_zero_for_an_invalid_event(): void {
		$this->assertSame( 0, rytkoset_theme_count_event_free_registrations( 0 ) );
	}

	// --- registration participant count -------------------------------------

	public function test_registration_participant_count_is_one_without_quantity_collection(): void {
		$this->event( 10 );
		$this->registration( 501, 10, array( 'quantity' => '4' ) );

		$this->assertSame( 1, rytkoset_theme_get_event_registration_participant_count( 501 ) );
	}

	public function test_registration_participant_count_uses_quantity_when_collected(): void {
		$this->event( 10 );
		update_post_meta( 10, rytkoset_theme_get_event_collect_quantity_meta_key(), 'yes' );
		$this->registration( 501, 10, array( 'quantity' => '4' ) );

		$this->assertSame( 4, rytkoset_theme_get_event_registration_participant_count( 501 ) );
	}

	// --- paid registration counting -----------------------------------------

	public function test_paid_count_is_zero_without_a_linked_product(): void {
		$this->event( 10 );

		$GLOBALS['wpdb']->get_var_result = '7';

		$this->assertSame( 0, rytkoset_theme_count_event_paid_registrations( 10 ) );
		$this->assertSame( '', $GLOBALS['wpdb']->last_query, 'No order query may run for an event without a linked product.' );
	}

	public function test_paid_count_queries_the_linked_product_and_active_statuses(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );

		$GLOBALS['wpdb']->get_var_result = '11';

		$this->assertSame( 11, rytkoset_theme_count_event_paid_registrations( 10 ) );

		$query = $GLOBALS['wpdb']->last_query;

		$this->assertStringContainsString( 'woocommerce_order_items', $query );
		$this->assertStringContainsString( "'_product_id', '_variation_id'", $query );
		$this->assertStringContainsString( '900', $query );
		$this->assertStringContainsString( "'wc-completed'", $query );
		$this->assertStringNotContainsString( "'wc-cancelled'", $query );
		$this->assertStringNotContainsString( "'wc-refunded'", $query );
	}

	public function test_paid_count_is_zero_when_the_query_returns_nothing(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );

		$GLOBALS['wpdb']->get_var_result = null;

		$this->assertSame( 0, rytkoset_theme_count_event_paid_registrations( 10 ) );
	}

	public function test_paid_count_is_null_on_a_database_error(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );

		$GLOBALS['wpdb']->get_var_result = null;
		$GLOBALS['wpdb']->last_error     = 'Table is missing';

		$this->assertNull( rytkoset_theme_count_event_paid_registrations( 10 ) );
	}

	public function test_paid_count_can_be_short_circuited_by_filter(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );

		$filter = static fn() => 5;
		add_filter( 'rytkoset_theme_pre_event_paid_registration_count', $filter );

		$this->assertSame( 5, rytkoset_theme_count_event_paid_registrations( 10 ) );
		$this->assertSame( '', $GLOBALS['wpdb']->last_query );

		remove_filter( 'rytkoset_theme_pre_event_paid_registration_count', $filter );
	}

	// --- remaining capacity --------------------------------------------------

	public function test_remaining_capacity_is_null_without_stock_management(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );

		$this->assertNull( rytkoset_theme_get_event_remaining_capacity( 10 ) );
	}

	public function test_remaining_capacity_reads_managed_stock(): void {
		rytkoset_test_register_product(
			900,
			'publish',
			'Bussikyyti',
			array(
				'_manage_stock' => 'yes',
				'_stock'        => 8,
			)
		);
		$this->event( 10, 900 );

		$this->assertSame( 8, rytkoset_theme_get_event_remaining_capacity( 10 ) );
	}

	public function test_remaining_capacity_is_null_without_a_linked_product(): void {
		$this->event( 10 );

		$this->assertNull( rytkoset_theme_get_event_remaining_capacity( 10 ) );
	}

	// --- deadline days left ---------------------------------------------------

	public function test_days_left_counts_whole_days_to_an_inclusive_deadline(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-08-10 22:30:00';

		$this->assertSame( 4, rytkoset_theme_get_event_registration_deadline_days_left( '2026-08-14' ) );
		$this->assertSame( 0, rytkoset_theme_get_event_registration_deadline_days_left( '2026-08-10' ) );
		$this->assertSame( -2, rytkoset_theme_get_event_registration_deadline_days_left( '2026-08-08' ) );
	}

	public function test_days_left_is_null_for_an_unusable_deadline(): void {
		$this->assertNull( rytkoset_theme_get_event_registration_deadline_days_left( '' ) );
		$this->assertNull( rytkoset_theme_get_event_registration_deadline_days_left( 'ensi kesänä' ) );
	}

	// --- summary formatting ---------------------------------------------------

	public function test_summary_lines_render_every_known_value(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-08-10 09:00:00';

		$lines = rytkoset_theme_format_event_registration_summary_lines(
			array(
				'total'              => 12,
				'free'               => 4,
				'paid'               => 8,
				'new_participants'   => 2,
				'capacity_remaining' => 18,
				'deadline'           => '2026-08-14',
				'deadline_days_left' => 4,
			)
		);

		$this->assertSame(
			array(
				'Ilmoittautumistilanne:',
				'Tämän ilmoittautumisen henkilömäärä: 2',
				'Ilmoittautuneita yhteensä: 12',
				'Paikkoja jäljellä: 18',
				'Ilmoittautuminen päättyy: 14.8.2026 (4 päivää jäljellä)',
			),
			$lines
		);
	}

	public function test_summary_lines_omit_unknown_values(): void {
		$lines = rytkoset_theme_format_event_registration_summary_lines(
			array(
				'total'              => null,
				'new_participants'   => 0,
				'capacity_remaining' => null,
				'deadline'           => '',
				'deadline_days_left' => null,
			)
		);

		$this->assertSame( array(), $lines );
	}

	public function test_summary_lines_use_singular_and_boundary_deadline_wording(): void {
		$one_day = rytkoset_theme_format_event_registration_summary_lines(
			array(
				'total'              => 1,
				'deadline'           => '2026-08-14',
				'deadline_days_left' => 1,
			)
		);

		$this->assertContains( 'Ilmoittautuminen päättyy: 14.8.2026 (1 päivä jäljellä)', $one_day );

		$last_day = rytkoset_theme_format_event_registration_summary_lines(
			array(
				'deadline'           => '2026-08-14',
				'deadline_days_left' => 0,
			)
		);

		$this->assertContains( 'Ilmoittautuminen päättyy: 14.8.2026 (viimeinen ilmoittautumispäivä)', $last_day );

		$passed = rytkoset_theme_format_event_registration_summary_lines(
			array(
				'deadline'           => '2026-08-14',
				'deadline_days_left' => -3,
			)
		);

		$this->assertContains( 'Ilmoittautuminen päättyy: 14.8.2026 (määräaika on ohitettu)', $passed );
	}

	public function test_summary_lines_tolerate_a_non_array_summary(): void {
		$this->assertSame( array(), rytkoset_theme_format_event_registration_summary_lines( 'ei mitään' ) );
	}

	// --- summary composition --------------------------------------------------

	public function test_summary_combines_free_and_paid_counts(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );
		$this->registration( 501, 10 );
		$this->registration( 502, 10 );

		$GLOBALS['wpdb']->get_var_result = '6';

		$summary = rytkoset_theme_get_event_registration_summary( 10, 2 );

		$this->assertSame( 2, $summary['free'] );
		$this->assertSame( 6, $summary['paid'] );
		$this->assertSame( 8, $summary['total'] );
		$this->assertSame( 2, $summary['new_participants'] );
	}

	public function test_summary_total_is_null_when_the_paid_count_is_unavailable(): void {
		rytkoset_test_register_product( 900, 'publish', 'Osallistumismaksu' );
		$this->event( 10, 900 );
		$this->registration( 501, 10 );

		$GLOBALS['wpdb']->last_error = 'Table is missing';

		$summary = rytkoset_theme_get_event_registration_summary( 10, 1 );

		$this->assertSame( 1, $summary['free'] );
		$this->assertNull( $summary['paid'] );
		$this->assertNull( $summary['total'] );
	}

	public function test_summary_reads_the_free_event_registration_deadline(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-08-10 09:00:00';

		$this->event( 10 );
		update_post_meta( 10, rytkoset_theme_get_event_details_meta_keys()['fee_type'], 'free' );
		update_post_meta( 10, rytkoset_theme_get_event_registration_deadline_meta_key(), '2026-08-14' );

		$summary = rytkoset_theme_get_event_registration_summary( 10, 1 );

		$this->assertSame( '2026-08-14', $summary['deadline'] );
		$this->assertSame( 4, $summary['deadline_days_left'] );
	}
}
