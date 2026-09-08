<?php
/**
 * Tests for inc/event-participants-admin.php — free-registration participant rows
 * and the choice/quantity summary shown in the event participants admin page.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventParticipantsAdminTest extends Rytkoset_Theme_Test_Case {

	public function test_free_participants_include_choice_and_quantity(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Bussikyyti' );
		rytkoset_test_register_post( 101, 'event_registration', 'Maija Meikäläinen - Bussikyyti' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		update_post_meta( 101, $meta_keys['event_id'], 10 );
		update_post_meta( 101, $meta_keys['name'], 'Maija Meikäläinen' );
		update_post_meta( 101, $meta_keys['email'], 'maija@example.test' );
		update_post_meta( 101, $meta_keys['choice'], 'Kuopio' );
		update_post_meta( 101, $meta_keys['quantity'], 3 );
		update_post_meta( 101, $meta_keys['status'], 'confirmed' );

		$rows = rytkoset_theme_get_event_free_participants( 10 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Maija Meikäläinen', $rows[0]['name'] );
		$this->assertSame( 'Kuopio', $rows[0]['choice'] );
		$this->assertSame( 3, $rows[0]['quantity'] );
		$this->assertSame( 'confirmed', $rows[0]['status'] );
		$this->assertSame( 'Bussikyyti', $rows[0]['event_title'] );
		$this->assertSame( 'free', $rows[0]['source'] );
	}

	public function test_choice_summary_counts_quantities_and_skips_cancelled_rows(): void {
		$summary = rytkoset_theme_get_event_participant_choice_summary(
			array(
				array(
					'choice'   => 'Kuopio',
					'quantity' => 2,
					'status'   => 'pending',
				),
				array(
					'choice'   => 'Kuopio',
					'quantity' => 3,
					'status'   => 'confirmed',
				),
				array(
					'choice'   => 'Varkaus',
					'quantity' => 1,
					'status'   => 'cancelled',
				),
				array(
					'choice'   => '',
					'quantity' => 4,
					'status'   => 'pending',
				),
			),
			true
		);

		$this->assertSame( 9, $summary['total'] );
		$this->assertSame(
			array(
				'Kuopio'      => 5,
				'Ei valintaa' => 4,
			),
			$summary['breakdown']
		);
	}

	public function test_choice_summary_counts_registrations_without_quantity_column(): void {
		$summary = rytkoset_theme_get_event_participant_choice_summary(
			array(
				array(
					'choice'   => 'Kuopio',
					'quantity' => 4,
					'status'   => 'pending',
				),
				array(
					'choice'   => 'Varkaus',
					'quantity' => 2,
					'status'   => 'confirmed',
				),
			),
			false
		);

		$this->assertSame( 2, $summary['total'] );
		$this->assertSame(
			array(
				'Kuopio'  => 1,
				'Varkaus' => 1,
			),
			$summary['breakdown']
		);
	}

	public function test_free_participant_row_exposes_origin(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Sukujuhla' );
		rytkoset_test_register_post( 110, 'event_registration', 'Verkko Ilmoittautuja - Sukujuhla' );
		rytkoset_test_register_post( 111, 'event_registration', 'Kasin Lisatty - Sukujuhla' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();

		update_post_meta( 110, $meta_keys['event_id'], 10 );
		update_post_meta( 110, $meta_keys['name'], 'Verkko Ilmoittautuja' );
		update_post_meta( 110, $meta_keys['status'], 'confirmed' );
		update_post_meta( 110, $meta_keys['source'], 'web_form' );

		update_post_meta( 111, $meta_keys['event_id'], 10 );
		update_post_meta( 111, $meta_keys['name'], 'Kasin Lisatty' );
		update_post_meta( 111, $meta_keys['status'], 'confirmed' );
		update_post_meta( 111, $meta_keys['source'], 'manual' );

		$rows = rytkoset_theme_get_event_free_participants( 10 );
		$by_name = array();

		foreach ( $rows as $row ) {
			$by_name[ $row['name'] ] = $row;
		}

		$this->assertSame( 'free', $by_name['Verkko Ilmoittautuja']['source'] );
		$this->assertSame( 'web_form', $by_name['Verkko Ilmoittautuja']['origin'] );
		$this->assertSame( 'Verkkolomake', $by_name['Verkko Ilmoittautuja']['origin_label'] );
		$this->assertSame( 110, $by_name['Verkko Ilmoittautuja']['registration_id'] );

		$this->assertSame( 'free', $by_name['Kasin Lisatty']['source'] );
		$this->assertSame( 'manual', $by_name['Kasin Lisatty']['origin'] );
		$this->assertSame( 'Käsin lisätty', $by_name['Kasin Lisatty']['origin_label'] );
	}

	public function test_legacy_free_row_without_source_meta_reads_as_web_form(): void {
		rytkoset_test_register_post( 10, 'rytkoset_event', 'Sukujuhla' );
		rytkoset_test_register_post( 112, 'event_registration', 'Vanha Rivi - Sukujuhla' );

		$meta_keys = rytkoset_theme_get_event_registration_meta_keys();
		update_post_meta( 112, $meta_keys['event_id'], 10 );
		update_post_meta( 112, $meta_keys['name'], 'Vanha Rivi' );
		update_post_meta( 112, $meta_keys['status'], 'confirmed' );

		$rows = rytkoset_theme_get_event_free_participants( 10 );

		$this->assertSame( 'web_form', $rows[0]['origin'] );
		$this->assertSame( 'Verkkolomake', $rows[0]['origin_label'] );
	}

	public function test_inactive_order_statuses_default_set(): void {
		$this->assertSame(
			array( 'cancelled', 'refunded', 'failed' ),
			rytkoset_theme_get_event_feedback_inactive_order_statuses()
		);
	}

	public function test_active_participant_filter_drops_cancelled_and_dead_orders(): void {
		$rows = array(
			array( 'name' => 'Aktiivinen maksuton', 'source' => 'free', 'status' => 'confirmed' ),
			array( 'name' => 'Peruttu maksuton', 'source' => 'free', 'status' => 'cancelled' ),
			array( 'name' => 'Odottava maksuton', 'source' => 'free', 'status' => 'pending' ),
			array( 'name' => 'Maksettu tilaus', 'source' => 'paid', 'status' => 'paid', 'order_status' => 'completed' ),
			array( 'name' => 'Peruttu tilaus', 'source' => 'paid', 'status' => 'paid', 'order_status' => 'cancelled' ),
			array( 'name' => 'Hyvitetty tilaus', 'source' => 'paid', 'status' => 'paid', 'order_status' => 'refunded' ),
			array( 'name' => 'Epäonnistunut tilaus', 'source' => 'paid', 'status' => 'paid', 'order_status' => 'failed' ),
		);

		$kept = array_map(
			static fn( $row ) => $row['name'],
			rytkoset_theme_filter_active_event_participants( $rows )
		);

		$this->assertSame(
			array( 'Aktiivinen maksuton', 'Odottava maksuton', 'Maksettu tilaus' ),
			$kept
		);
	}

	public function test_active_participant_filter_keeps_cancelled_free_when_filter_targets_cancelled(): void {
		$rows = array(
			array( 'name' => 'Peruttu maksuton', 'source' => 'free', 'status' => 'cancelled' ),
			array( 'name' => 'Peruttu tilaus', 'source' => 'paid', 'status' => 'paid', 'order_status' => 'cancelled' ),
		);

		$kept = array_map(
			static fn( $row ) => $row['name'],
			rytkoset_theme_filter_active_event_participants( $rows, 'cancelled' )
		);

		// The cancelled free row is kept on purpose; a dead order still drops.
		$this->assertSame( array( 'Peruttu maksuton' ), $kept );
	}
}
