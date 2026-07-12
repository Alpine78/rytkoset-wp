<?php
/**
 * Tests for the members-only AcyMailing list integration (#535).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MemberNewsletterTest extends Rytkoset_Theme_Test_Case {

	public function test_member_list_id_is_configured_separately(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_member_newsletter_list_id'] = '17';

		$this->assertSame( 17, rytkoset_theme_get_member_newsletter_list_id() );
	}

	public function test_general_newsletter_list_id_is_rejected(): void {
		$GLOBALS['wpdb']->get_var_result = json_encode(
			array(
				'lists' => array(
					'checked' => array( '17' ),
				),
			)
		);
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_newsletter_shortcode'] = '[acymailing_form_shortcode id="2"]';
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_member_newsletter_list_id'] = '17';

		$this->assertSame( 0, rytkoset_theme_get_member_newsletter_list_id() );
	}

	public function test_active_own_membership_user_is_recipient(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11';
		rytkoset_test_register_user( 10, 'jasen@example.test', 'Jäsen' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );

		$this->assertTrue( rytkoset_theme_user_is_member_newsletter_recipient( 10 ) );
	}

	public function test_lifetime_member_is_recipient(): void {
		rytkoset_test_register_user( 10, 'ainaisjasen@example.test', 'Ainaisjäsen' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$this->assertTrue( rytkoset_theme_user_is_member_newsletter_recipient( 10 ) );
	}

	public function test_expired_member_is_not_recipient(): void {
		$GLOBALS['rytkoset_test_now'] = '2030-01-01';
		rytkoset_test_register_user( 10, 'vanhentunut@example.test', 'Vanhentunut' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );

		$this->assertFalse( rytkoset_theme_user_is_member_newsletter_recipient( 10 ) );
	}

	public function test_linked_active_family_member_is_recipient(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11';
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'perhejasen@example.test', 'Perhejäsen' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Perhejäsen',
					'email'          => 'perhejasen@example.test',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$this->assertTrue( rytkoset_theme_user_is_member_newsletter_recipient( 20 ) );
	}

	public function test_recipient_is_removed_only_after_last_membership_source_ends(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11';
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'perhejasen@example.test', 'Perhejäsen' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'family' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2029-12-31' );
		update_user_meta( 20, rytkoset_theme_get_user_membership_type_meta_key(), 'annual' );
		update_user_meta( 20, rytkoset_theme_get_user_membership_expires_meta_key(), '2025-12-31' );
		rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Perhejäsen',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$this->assertTrue( rytkoset_theme_user_is_member_newsletter_recipient( 20 ) );

		update_user_meta( 10, rytkoset_theme_get_user_membership_expires_meta_key(), '2025-12-31' );
		$this->assertFalse( rytkoset_theme_user_is_member_newsletter_recipient( 20 ) );
	}

	public function test_pending_or_email_only_records_are_not_recipients(): void {
		$this->assertFalse( rytkoset_theme_user_is_member_newsletter_recipient( 0 ) );
		$this->assertFalse( rytkoset_theme_user_is_member_newsletter_recipient( 99 ) );
	}

	public function test_invalid_account_email_is_not_recipient(): void {
		rytkoset_test_register_user( 10, 'ei-email', 'Jäsen' );
		update_user_meta( 10, rytkoset_theme_get_user_membership_type_meta_key(), 'lifetime' );

		$this->assertFalse( rytkoset_theme_user_is_member_newsletter_recipient( 10 ) );
	}

	public function test_sync_action_adds_missing_active_membership(): void {
		$this->assertSame(
			'add',
			rytkoset_theme_get_member_newsletter_sync_action( array(), true )
		);
	}

	public function test_sync_action_preserves_manual_opt_out(): void {
		$this->assertSame(
			'protected_opt_out',
			rytkoset_theme_get_member_newsletter_sync_action(
				array(
					'subscriber_id' => 12,
					'active'        => 1,
					'list_status'   => 0,
				),
				true
			)
		);
	}

	public function test_sync_action_preserves_global_block(): void {
		$this->assertSame(
			'protected_inactive',
			rytkoset_theme_get_member_newsletter_sync_action(
				array(
					'subscriber_id' => 12,
					'active'        => 0,
					'list_status'   => null,
				),
				true
			)
		);
	}

	public function test_sync_action_preserves_global_block_after_membership_ends(): void {
		$this->assertSame(
			'protected_inactive',
			rytkoset_theme_get_member_newsletter_sync_action(
				array(
					'subscriber_id' => 12,
					'active'        => 0,
					'list_status'   => 1,
				),
				false
			)
		);
	}

	public function test_sync_action_removes_only_current_membership_relation(): void {
		$this->assertSame(
			'remove',
			rytkoset_theme_get_member_newsletter_sync_action(
				array(
					'subscriber_id' => 12,
					'active'        => 1,
					'list_status'   => 1,
				),
				false
			)
		);
	}

	public function test_sync_action_is_idempotent(): void {
		$this->assertSame( 'none', rytkoset_theme_get_member_newsletter_sync_action( array( 'list_status' => 1 ), true ) );
		$this->assertSame( 'none', rytkoset_theme_get_member_newsletter_sync_action( array(), false ) );
	}

	public function test_primary_membership_change_affects_linked_family_users(): void {
		rytkoset_test_register_user( 10, 'paakayttaja@example.test', 'Pääkäyttäjä' );
		rytkoset_test_register_user( 20, 'perhejasen@example.test', 'Perhejäsen' );
		rytkoset_theme_update_family_members(
			10,
			array(
				array(
					'name'           => 'Perhejäsen',
					'linked_user_id' => 20,
					'status'         => 'active',
				),
			)
		);

		$this->assertSame(
			array( 10, 20 ),
			rytkoset_theme_get_member_newsletter_affected_user_ids(
				10,
				rytkoset_theme_get_user_membership_expires_meta_key()
			)
		);
	}

	public function test_reverse_link_change_affects_only_linked_user(): void {
		$this->assertSame(
			array( 20 ),
			rytkoset_theme_get_member_newsletter_affected_user_ids(
				20,
				rytkoset_theme_get_family_primary_user_meta_key()
			)
		);
	}

	public function test_state_reader_uses_only_configured_list_and_email(): void {
		$GLOBALS['wpdb']->get_row_result = (object) array(
			'subscriber_id' => '12',
			'active'        => '1',
			'confirmed'     => '1',
			'list_status'   => '0',
		);

		$state = rytkoset_theme_get_member_newsletter_state( 'jasen@example.test', 17 );

		$this->assertSame( 12, $state['subscriber_id'] );
		$this->assertSame( 0, $state['list_status'] );
		$this->assertSame( array( 17, 'jasen@example.test' ), $GLOBALS['wpdb']->last_prepare_args );
		$this->assertStringContainsString( 'LEFT JOIN wp_acym_user_has_list', $GLOBALS['wpdb']->last_query );
	}

	public function test_sync_rejects_missing_member_list_before_using_acymailing(): void {
		$result = rytkoset_theme_sync_member_newsletter_email( 'jasen@example.test', true, 10 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'missing_member_newsletter_list', $result->get_error_codes() );
	}

	public function test_reconciliation_state_normalization_keeps_only_aggregate_fields(): void {
		$state = rytkoset_theme_normalize_member_newsletter_reconciliation_state(
			array(
				'running'    => true,
				'phase'      => 'list',
				'cursor'     => '42',
				'processed'  => '7',
				'outcome'    => 'running',
				'email'      => 'henkilo@example.test',
				'last_error_code' => 'DB ERROR!',
			)
		);

		$this->assertTrue( $state['running'] );
		$this->assertSame( 'list', $state['phase'] );
		$this->assertSame( 42, $state['cursor'] );
		$this->assertSame( 7, $state['processed'] );
		$this->assertSame( 'dberror', $state['last_error_code'] );
		$this->assertArrayNotHasKey( 'email', $state );
	}

	public function test_reconciliation_result_counters_are_aggregate_only(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11 12:00:00';
		$state = rytkoset_theme_get_default_member_newsletter_reconciliation_state();

		foreach ( array( 'added', 'removed', 'none', 'protected_opt_out', 'protected_inactive' ) as $result ) {
			$state = rytkoset_theme_record_member_newsletter_reconciliation_result( $state, $result );
		}
		$state = rytkoset_theme_record_member_newsletter_reconciliation_result(
			$state,
			new WP_Error( 'temporary_failure', 'Sähköposti henkilo@example.test' )
		);

		$this->assertSame( 6, $state['processed'] );
		$this->assertSame( 1, $state['added'] );
		$this->assertSame( 1, $state['removed'] );
		$this->assertSame( 1, $state['unchanged'] );
		$this->assertSame( 2, $state['protected'] );
		$this->assertSame( 1, $state['errors'] );
		$this->assertSame( 'temporary_failure', $state['last_error_code'] );
		$this->assertSame( '2026-07-11 12:00:00', $state['last_error_at'] );
		$this->assertStringNotContainsString( 'example.test', serialize( $state ) );
	}

	public function test_successful_reconciliation_records_last_success(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11 13:00:00';
		$state                        = rytkoset_theme_get_default_member_newsletter_reconciliation_state();
		$state['running']             = true;
		$state['phase']               = 'list';

		$state = rytkoset_theme_complete_member_newsletter_reconciliation( $state );

		$this->assertFalse( $state['running'] );
		$this->assertSame( 'success', $state['outcome'] );
		$this->assertSame( '2026-07-11 13:00:00', $state['last_completed_at'] );
		$this->assertSame( $state['last_completed_at'], $state['last_success_at'] );
	}

	public function test_reconciliation_with_errors_keeps_previous_last_success(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11 14:00:00';
		$state                        = rytkoset_theme_get_default_member_newsletter_reconciliation_state();
		$state['running']             = true;
		$state['phase']               = 'list';
		$state['errors']              = 2;
		$state['last_success_at']     = '2026-07-10 10:00:00';

		$state = rytkoset_theme_complete_member_newsletter_reconciliation( $state );

		$this->assertSame( 'errors', $state['outcome'] );
		$this->assertSame( '2026-07-10 10:00:00', $state['last_success_at'] );
	}

	public function test_configuration_error_resets_current_counts_but_keeps_last_success(): void {
		$GLOBALS['rytkoset_test_now'] = '2026-07-11 15:00:00';
		$GLOBALS['rytkoset_test_options'][ rytkoset_theme_get_member_newsletter_reconciliation_option_name() ] = array(
			'processed'       => 99,
			'errors'          => 4,
			'last_success_at' => '2026-07-10 10:00:00',
		);

		rytkoset_theme_record_member_newsletter_configuration_error( new WP_Error( 'acymailing_missing' ) );
		$state = rytkoset_theme_get_member_newsletter_reconciliation_state();

		$this->assertSame( 'configuration_error', $state['outcome'] );
		$this->assertSame( 0, $state['processed'] );
		$this->assertSame( 1, $state['errors'] );
		$this->assertSame( 'acymailing_missing', $state['last_error_code'] );
		$this->assertSame( '2026-07-10 10:00:00', $state['last_success_at'] );
		$this->assertSame( '2026-07-11 15:00:00', $state['last_completed_at'] );
	}

	public function test_user_batch_uses_stable_id_cursor_and_limit(): void {
		$GLOBALS['wpdb']->get_col_result = array( '11', '14' );

		$this->assertSame( array( 11, 14 ), rytkoset_theme_get_member_newsletter_user_batch( 10, 2 ) );
		$this->assertSame( array( 10, 2 ), $GLOBALS['wpdb']->last_prepare_args );
		$this->assertStringContainsString( 'WHERE ID > 10', $GLOBALS['wpdb']->last_query );
		$this->assertStringContainsString( 'ORDER BY ID ASC', $GLOBALS['wpdb']->last_query );
	}

	public function test_list_batch_uses_only_active_relations_on_configured_list(): void {
		$GLOBALS['rytkoset_test_options']['theme_mod_rytkoset_theme_member_newsletter_list_id'] = 17;
		$GLOBALS['wpdb']->get_results_result = array(
			(object) array(
				'subscriber_id' => '31',
				'email'         => 'JASEN@example.test',
			),
		);

		$rows = rytkoset_theme_get_member_newsletter_list_batch( 30, 20 );

		$this->assertSame( array( array( 'subscriber_id' => 31, 'email' => 'jasen@example.test' ) ), $rows );
		$this->assertSame( array( 17, 30, 20 ), $GLOBALS['wpdb']->last_prepare_args );
		$this->assertStringContainsString( 'acym_list.status = 1', $GLOBALS['wpdb']->last_query );
	}

	public function test_batch_queries_report_database_errors(): void {
		$GLOBALS['wpdb']->last_error = 'database unavailable';

		$this->assertInstanceOf( WP_Error::class, rytkoset_theme_get_member_newsletter_user_batch( 0, 10 ) );
		$this->assertInstanceOf( WP_Error::class, rytkoset_theme_get_member_newsletter_list_batch( 0, 10 ) );
	}

	public function test_batch_size_is_clamped(): void {
		$too_large = static fn() => 999;
		add_filter( 'rytkoset_theme_member_newsletter_batch_size', $too_large );
		$this->assertSame( 200, rytkoset_theme_get_member_newsletter_batch_size() );
		remove_filter( 'rytkoset_theme_member_newsletter_batch_size', $too_large );

		$zero = static fn() => 0;
		add_filter( 'rytkoset_theme_member_newsletter_batch_size', $zero );
		$this->assertSame( 1, rytkoset_theme_get_member_newsletter_batch_size() );
		remove_filter( 'rytkoset_theme_member_newsletter_batch_size', $zero );
	}

	public function test_daily_cron_is_scheduled_idempotently(): void {
		rytkoset_theme_ensure_member_newsletter_daily_cron_scheduled();
		$hook      = rytkoset_theme_get_member_newsletter_daily_cron_hook();
		$scheduled = $GLOBALS['rytkoset_test_cron_events'][ $hook ];

		$this->assertGreaterThan( time(), $scheduled );

		rytkoset_theme_ensure_member_newsletter_daily_cron_scheduled();
		$this->assertSame( $scheduled, $GLOBALS['rytkoset_test_cron_events'][ $hook ] );
	}

	public function test_continuation_batch_is_scheduled_only_once(): void {
		rytkoset_theme_schedule_member_newsletter_reconciliation_batch();
		$hook      = rytkoset_theme_get_member_newsletter_batch_cron_hook();
		$scheduled = $GLOBALS['rytkoset_test_cron_events'][ $hook ];

		rytkoset_theme_schedule_member_newsletter_reconciliation_batch();
		$this->assertSame( $scheduled, $GLOBALS['rytkoset_test_cron_events'][ $hook ] );
	}

	public function test_switching_theme_clears_both_cron_hooks(): void {
		rytkoset_theme_ensure_member_newsletter_daily_cron_scheduled();
		rytkoset_theme_schedule_member_newsletter_reconciliation_batch();

		rytkoset_theme_clear_member_newsletter_cron();

		$this->assertFalse( wp_next_scheduled( rytkoset_theme_get_member_newsletter_daily_cron_hook() ) );
		$this->assertFalse( wp_next_scheduled( rytkoset_theme_get_member_newsletter_batch_cron_hook() ) );
	}
}
