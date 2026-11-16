<?php
/**
 * Tests for the event messaging queue locks and queue processing (#747).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventMessagingQueueTest extends Rytkoset_Theme_Test_Case {

	/** @var callable|null Filter registered on pre_wp_mail. */
	private $mail_filter = null;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['rytkoset_theme_event_messaging_locks'] = array();
	}

	protected function tearDown(): void {
		if ( null !== $this->mail_filter ) {
			remove_filter( 'pre_wp_mail', $this->mail_filter, 10 );
			$this->mail_filter = null;
		}

		parent::tearDown();
	}

	/**
	 * Queues a job for the given number of recipients and returns its ID.
	 */
	private function enqueue( string $subject, int $recipient_count, string $prefix = 'vastaanottaja' ): string {
		$recipients = array();

		for ( $i = 1; $i <= $recipient_count; $i++ ) {
			$recipients[] = array(
				'email' => $prefix . $i . '@example.test',
				'name'  => 'Nimi ' . $i,
			);
		}

		return rytkoset_theme_enqueue_event_messaging_job(
			array(
				'recipients' => $recipients,
				'subject'    => $subject,
				'body'       => 'Hei {nimi}',
				'event_id'   => 0,
			)
		);
	}

	/**
	 * Returns the recipient addresses of the mails sent so far.
	 *
	 * @return string[]
	 */
	private function sent_to(): array {
		return array_column( $GLOBALS['rytkoset_test_mails'], 'to' );
	}

	private function on_mail( callable $callback ): void {
		$this->mail_filter = $callback;
		add_filter( 'pre_wp_mail', $this->mail_filter, 10, 2 );
	}

	public function test_lock_is_exclusive_until_released(): void {
		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );
		$this->assertFalse( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );

		// The two locks are independent.
		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'queue', 30 ) );

		rytkoset_theme_release_event_messaging_lock( 'process' );

		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );
	}

	public function test_abandoned_lock_is_taken_over_once(): void {
		$option = rytkoset_theme_get_event_messaging_lock_option_name( 'process' );
		update_option( $option, (string) ( time() - 601 ) );

		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );
		// The takeover refreshed the timestamp, so a second request is refused.
		$this->assertFalse( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );
		$this->assertGreaterThan( time() - 5, (int) get_option( $option ) );
	}

	public function test_fresh_lock_with_unreadable_value_is_not_taken_over(): void {
		update_option( rytkoset_theme_get_event_messaging_lock_option_name( 'process' ), 'ei-aikaleima' );

		$this->assertFalse( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );
	}

	public function test_expired_owner_cannot_release_or_renew_a_lock_taken_over(): void {
		$option = rytkoset_theme_get_event_messaging_lock_option_name( 'queue' );

		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'queue', 30 ) );

		// Another request took the stalled lock over.
		$other_owner = time() . '|toinenomistaja';
		update_option( $option, $other_owner );

		$this->assertFalse( rytkoset_theme_renew_event_messaging_lock( 'queue' ) );

		rytkoset_theme_release_event_messaging_lock( 'queue' );

		$this->assertSame( $other_owner, get_option( $option ) );
	}

	public function test_mutation_is_not_written_after_the_lock_was_lost(): void {
		$option = rytkoset_theme_get_event_messaging_lock_option_name( 'queue' );

		$written = rytkoset_theme_mutate_event_messaging_queue(
			static function ( $queue ) use ( $option ) {
				// The request stalls and another one takes the lock over meanwhile.
				update_option( $option, time() . '|toinenomistaja' );
				$queue[] = array( 'id' => 'myohassa' );

				return $queue;
			},
			0
		);

		$this->assertFalse( $written );
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_queue() );
		$this->assertSame( 'toinenomistaja', substr( (string) strstr( (string) get_option( $option ), '|' ), 1 ) );
	}

	public function test_run_stops_without_logging_when_progress_cannot_be_stored(): void {
		$this->enqueue( 'Ensimmäinen', 1, 'a' );
		$this->enqueue( 'Toinen', 1, 'b' );

		$option = rytkoset_theme_get_event_messaging_lock_option_name( 'queue' );

		$this->on_mail(
			static function ( $pre ) use ( $option ) {
				// Another request holds a fresh queue lock for the rest of the run.
				update_option( $option, ( time() + 600 ) . '|toinenomistaja' );

				return $pre;
			}
		);

		// Keep the test fast: the commit would otherwise wait for the lock.
		add_filter( 'rytkoset_theme_event_messaging_commit_wait_ms', '__return_zero' );
		rytkoset_theme_process_event_messaging_queue();
		remove_filter( 'rytkoset_theme_event_messaging_commit_wait_ms', '__return_zero' );

		$this->assertSame( array( 'a1@example.test' ), $this->sent_to() );
		$this->assertCount( 2, rytkoset_theme_get_event_messaging_queue() );
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_log( 5 ) );
		// The send attempt still counts toward the hourly limit.
		$this->assertCount( 1, rytkoset_theme_get_event_messaging_send_attempts() );
		$this->assertFalse( get_option( rytkoset_theme_get_event_messaging_lock_option_name( 'process' ) ) );
	}

	public function test_enqueue_fails_closed_while_the_queue_lock_is_held(): void {
		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'queue', 30 ) );

		$this->assertFalse(
			rytkoset_theme_mutate_event_messaging_queue(
				static function ( $queue ) {
					$queue[] = array( 'id' => 'x' );

					return $queue;
				},
				0
			)
		);
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_queue() );
	}

	public function test_run_sends_each_pending_recipient_once_and_logs_the_job(): void {
		$job_id = $this->enqueue( 'Tiedote', 3 );

		$this->assertNotSame( '', $job_id );

		rytkoset_theme_process_event_messaging_queue();

		$this->assertSame(
			array( 'vastaanottaja1@example.test', 'vastaanottaja2@example.test', 'vastaanottaja3@example.test' ),
			$this->sent_to()
		);
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_queue() );
		$this->assertCount( 3, rytkoset_theme_get_event_messaging_send_attempts() );

		$log = rytkoset_theme_get_event_messaging_log( 5 );
		$this->assertSame( $job_id, $log[0]['id'] );
		$this->assertSame( 3, $log[0]['sent_count'] );

		// Both locks are released, and a second run has nothing to send.
		$this->assertFalse( get_option( rytkoset_theme_get_event_messaging_lock_option_name( 'process' ) ) );
		$this->assertFalse( get_option( rytkoset_theme_get_event_messaging_lock_option_name( 'queue' ) ) );

		rytkoset_theme_process_event_messaging_queue();

		$this->assertCount( 3, $this->sent_to() );
	}

	public function test_hourly_limit_holds_across_jobs_and_runs(): void {
		$this->enqueue( 'Ensimmäinen', 12, 'a' );
		$this->enqueue( 'Toinen', 12, 'b' );

		rytkoset_theme_process_event_messaging_queue();

		$this->assertCount( 18, $this->sent_to() );

		$queue = rytkoset_theme_get_event_messaging_queue();
		$this->assertCount( 1, $queue );
		$this->assertSame( 'Toinen', $queue[0]['subject'] );
		$this->assertSame( 6, rytkoset_theme_get_event_messaging_pending_recipient_count( $queue[0] ) );
		$this->assertSame( 6, $queue[0]['sent_count'] );

		// The rolling hour is full: another run sends nothing and resends nobody.
		rytkoset_theme_process_event_messaging_queue();

		$this->assertCount( 18, $this->sent_to() );
		$this->assertSame( count( $this->sent_to() ), count( array_unique( $this->sent_to() ) ) );
	}

	public function test_concurrent_run_is_skipped_while_the_process_lock_is_held(): void {
		$this->enqueue( 'Tiedote', 2 );
		$this->assertTrue( rytkoset_theme_acquire_event_messaging_lock( 'process', 600 ) );

		rytkoset_theme_process_event_messaging_queue();

		$this->assertSame( array(), $this->sent_to() );
		$this->assertSame( 2, rytkoset_theme_get_event_messaging_pending_recipient_count( rytkoset_theme_get_event_messaging_queue()[0] ) );
		// The skipped run must not release the other run's lock.
		$this->assertNotFalse( get_option( rytkoset_theme_get_event_messaging_lock_option_name( 'process' ) ) );
	}

	public function test_job_queued_during_a_run_is_kept_and_sent_recipients_stay_sent(): void {
		$this->enqueue( 'Käynnissä', 2, 'a' );

		$queued_during_run = '';
		$nested_run_mails  = null;

		$this->on_mail(
			function ( $pre, $atts ) use ( &$queued_during_run, &$nested_run_mails ) {
				if ( 'a1@example.test' === $atts['to'] ) {
					// Another request queues a job and another cron run starts mid-send.
					$queued_during_run = $this->enqueue( 'Lisätty kesken ajon', 1, 'b' );
					$before            = count( $this->sent_to() );
					rytkoset_theme_process_event_messaging_queue();
					$nested_run_mails = count( $this->sent_to() ) - $before;
				}

				return $pre;
			}
		);

		rytkoset_theme_process_event_messaging_queue();

		$this->assertNotSame( '', $queued_during_run );
		$this->assertSame( 0, $nested_run_mails );
		$this->assertSame( array( 'a1@example.test', 'a2@example.test' ), $this->sent_to() );

		// The first job is done; the job queued mid-run is still waiting, untouched.
		$queue = rytkoset_theme_get_event_messaging_queue();
		$this->assertCount( 1, $queue );
		$this->assertSame( $queued_during_run, $queue[0]['id'] );
		$this->assertSame( 'pending', $queue[0]['recipients'][0]['status'] );

		rytkoset_theme_process_event_messaging_queue();

		$this->assertSame( array( 'a1@example.test', 'a2@example.test', 'b1@example.test' ), $this->sent_to() );
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_queue() );
	}

	public function test_commit_replaces_only_its_own_job(): void {
		$first  = $this->enqueue( 'Ensimmäinen', 2, 'a' );
		$second = $this->enqueue( 'Toinen', 1, 'b' );
		$queue  = rytkoset_theme_get_event_messaging_queue();

		$job                             = $queue[0];
		$job['recipients'][0]['status']  = 'sent';
		$job['sent_count']               = 1;

		// A third job appears after this run took its snapshot.
		$third = $this->enqueue( 'Kolmas', 1, 'c' );

		$this->assertTrue( rytkoset_theme_commit_event_messaging_job( $job ) );

		$stored = rytkoset_theme_get_event_messaging_queue();
		$this->assertSame( array( $first, $second, $third ), array_column( $stored, 'id' ) );
		$this->assertSame( 'sent', $stored[0]['recipients'][0]['status'] );
		$this->assertSame( 'pending', $stored[0]['recipients'][1]['status'] );

		// A finished job is removed; the others stay.
		$job['recipients'][1]['status'] = 'failed';
		$this->assertTrue( rytkoset_theme_commit_event_messaging_job( $job ) );
		$this->assertSame( array( $second, $third ), array_column( rytkoset_theme_get_event_messaging_queue(), 'id' ) );
	}

	public function test_failed_and_invalid_recipients_are_final_and_counted(): void {
		$this->enqueue( 'Tiedote', 2 );

		$queue                              = rytkoset_theme_get_event_messaging_queue();
		$queue[0]['recipients'][1]['email'] = 'rikki';
		rytkoset_theme_update_event_messaging_queue( $queue );

		$this->on_mail(
			static function () {
				return false;
			}
		);

		rytkoset_theme_process_event_messaging_queue();

		$log = rytkoset_theme_get_event_messaging_log( 5 );
		$this->assertSame( 0, $log[0]['sent_count'] );
		$this->assertSame( 2, $log[0]['failed_count'] );
		// Only the valid address used a send attempt.
		$this->assertCount( 1, rytkoset_theme_get_event_messaging_send_attempts() );
		$this->assertSame( array(), rytkoset_theme_get_event_messaging_queue() );
	}
}
