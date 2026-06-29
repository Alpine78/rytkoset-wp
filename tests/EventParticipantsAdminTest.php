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
}
