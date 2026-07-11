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
}
