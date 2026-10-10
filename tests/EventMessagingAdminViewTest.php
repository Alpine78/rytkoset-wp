<?php
/**
 * Tests for the messaging and feedback admin view helpers (#696).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventMessagingAdminViewTest extends Rytkoset_Theme_Test_Case {

	public function test_overview_lists_queued_jobs_before_log_entries(): void {
		$queue = array(
			array(
				'subject'      => 'Bussin lähtöajat',
				'event_title'  => 'Sukujuhla',
				'sender_name'  => 'Ilkka',
				'created_at'   => '25.8.2026 18:40',
				'sent_count'   => 5,
				'recipients'   => array(
					array( 'status' => 'sent' ),
					array( 'status' => 'pending' ),
					array( 'status' => 'pending' ),
				),
			),
		);
		$log   = array(
			array(
				'subject'       => 'Muistutus',
				'timestamp'     => '10.8.2026 9:12',
				'sent_count'    => 20,
				'failed_count'  => 2,
				'skipped_count' => 1,
			),
			array(
				'subject'      => 'Kaikki epäonnistui',
				'sent_count'   => 0,
				'failed_count' => 3,
			),
		);

		$rows = rytkoset_theme_get_event_messaging_overview_rows( $queue, $log );

		$this->assertSame( array( 'Bussin lähtöajat', 'Muistutus', 'Kaikki epäonnistui' ), array_column( $rows, 'subject' ) );
		$this->assertSame( 'Jonossa, 2 / 3 jäljellä', $rows[0]['status_label'] );
		$this->assertSame( 'info', $rows[0]['variant'] );
		$this->assertSame( 3, $rows[0]['recipients'] );
		$this->assertSame( array( 'Lähetetty', 'warning', 22, 1 ), array( $rows[1]['status_label'], $rows[1]['variant'], $rows[1]['recipients'], $rows[1]['skipped'] ) );
		$this->assertSame( array( 'Epäonnistui', 'error' ), array( $rows[2]['status_label'], $rows[2]['variant'] ) );
	}

	public function test_finished_queue_job_reads_as_done(): void {
		$rows = rytkoset_theme_get_event_messaging_overview_rows(
			array( array( 'subject' => 'Valmis', 'recipients' => array( array( 'status' => 'sent' ) ) ) ),
			array()
		);

		$this->assertSame( array( 'Valmis', 'success' ), array( $rows[0]['status_label'], $rows[0]['variant'] ) );
	}

	public function test_feedback_csv_is_anonymous_and_neutralizes_formulas(): void {
		$keys = rytkoset_theme_get_event_feedback_response_meta_keys();
		$GLOBALS['rytkoset_test_posts'][801] = new WP_Post( 801, 'event_feedback', '' );
		update_post_meta( 801, $keys['rating'], 5 );
		update_post_meta( 801, $keys['well'], '=HYPERLINK("x")' );
		update_post_meta( 801, $keys['improve'], 'Äänentoisto' );

		$lines = rytkoset_theme_get_event_feedback_csv_rows( array( $GLOBALS['rytkoset_test_posts'][801] ) );

		$this->assertSame( array( 'Arvio', 'Mikä onnistui hyvin?', 'Mitä voisimme parantaa?', 'Toiveita tuleviin tapahtumiin' ), $lines[0] );
		$this->assertSame( '5', $lines[1][0] );
		$this->assertStringStartsWith( "'", $lines[1][1] );
		$this->assertSame( array( 'Äänentoisto', '' ), array( $lines[1][2], $lines[1][3] ) );
	}
}
