<?php
/**
 * Tests for the member row 1 checkout prefill helpers (#521)
 * in inc/woocommerce-membership.php.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class MembershipCheckoutPrefillTest extends Rytkoset_Theme_Test_Case {
	public function test_member_1_fields_are_account_owned_and_read_only(): void {
		$name_attributes  = rytkoset_theme_get_membership_member_field_attributes( 1, 'name' );
		$email_attributes = rytkoset_theme_get_membership_member_field_attributes( 1, 'email' );

		$this->assertTrue( $name_attributes['readOnly'] );
		$this->assertSame( 'true', $name_attributes['aria-disabled'] );
		$this->assertSame( 'true', $name_attributes['data-rytkoset-account-field'] );
		$this->assertSame( 'section-member-1-name new-password', $name_attributes['autocomplete'] );

		$this->assertTrue( $email_attributes['readOnly'] );
		$this->assertSame( 'true', $email_attributes['aria-disabled'] );
		$this->assertSame( 'true', $email_attributes['data-rytkoset-account-field'] );
		$this->assertSame( 'section-member-1-email new-password', $email_attributes['autocomplete'] );
	}

	public function test_later_member_fields_remain_editable(): void {
		$attributes = rytkoset_theme_get_membership_member_field_attributes( 2, 'email' );

		$this->assertArrayNotHasKey( 'readOnly', $attributes );
		$this->assertArrayNotHasKey( 'aria-disabled', $attributes );
		$this->assertArrayNotHasKey( 'data-rytkoset-account-field', $attributes );
		$this->assertSame( 'section-member-2-email new-password', $attributes['autocomplete'] );
	}

	// --- Prefill name ---------------------------------------------------------

	public function test_prefill_name_uses_first_and_last_name(): void {
		$user = rytkoset_test_register_user( 5, 'matti@example.com', 'mattim' );
		update_user_meta( 5, 'first_name', 'Matti' );
		update_user_meta( 5, 'last_name', 'Meikäläinen' );

		$this->assertSame( 'Matti Meikäläinen', rytkoset_theme_get_membership_member_prefill_name( $user ) );
	}

	public function test_prefill_name_uses_partial_profile_name(): void {
		$user = rytkoset_test_register_user( 5, 'matti@example.com', 'mattim' );
		update_user_meta( 5, 'first_name', 'Matti' );

		$this->assertSame( 'Matti', rytkoset_theme_get_membership_member_prefill_name( $user ) );
	}

	public function test_prefill_name_falls_back_to_display_name(): void {
		$user = rytkoset_test_register_user( 5, 'matti@example.com', 'Matti M.' );

		$this->assertSame( 'Matti M.', rytkoset_theme_get_membership_member_prefill_name( $user ) );
	}

	public function test_prefill_name_empty_without_any_name(): void {
		$user = rytkoset_test_register_user( 5, 'matti@example.com', '' );

		$this->assertSame( '', rytkoset_theme_get_membership_member_prefill_name( $user ) );
	}

	public function test_prefill_name_empty_for_missing_user(): void {
		$this->assertSame( '', rytkoset_theme_get_membership_member_prefill_name( null ) );
		$this->assertSame( '', rytkoset_theme_get_membership_member_prefill_name( new WP_User( 0 ) ) );
	}

	// --- Prefill email --------------------------------------------------------

	public function test_prefill_email_uses_account_email(): void {
		$user = rytkoset_test_register_user( 5, 'matti@example.com', 'Matti' );

		$this->assertSame( 'matti@example.com', rytkoset_theme_get_membership_member_prefill_email( $user ) );
	}

	public function test_prefill_email_rejects_invalid_email(): void {
		$user = rytkoset_test_register_user( 5, 'ei-osoite', 'Matti' );

		$this->assertSame( '', rytkoset_theme_get_membership_member_prefill_email( $user ) );
	}

	public function test_prefill_email_empty_for_missing_user(): void {
		$this->assertSame( '', rytkoset_theme_get_membership_member_prefill_email( null ) );
		$this->assertSame( '', rytkoset_theme_get_membership_member_prefill_email( new WP_User( 0 ) ) );
	}

	public function test_member_1_email_must_match_logged_in_account(): void {
		rytkoset_test_register_user( 5, 'matti@example.com', 'Matti' );
		$GLOBALS['rytkoset_test_current_user'] = 5;

		$this->assertNull( rytkoset_theme_validate_membership_primary_email_field( 'MATTI@example.com' ) );

		$result = rytkoset_theme_validate_membership_primary_email_field( 'toinen@example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_membership_primary_email_mismatch', $result->get_error_codes()[0] );
	}

	public function test_member_1_email_requires_authenticated_account(): void {
		$result = rytkoset_theme_validate_membership_primary_email_field( 'matti@example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rytkoset_membership_account_required', $result->get_error_codes()[0] );
	}

	// --- Prefill values map -----------------------------------------------------

	public function test_prefill_values_contain_only_member_1_fields(): void {
		$user = rytkoset_test_register_user( 5, 'matti@example.com', 'Matti M.' );

		$this->assertSame(
			array(
				'rytkoset/member_1_name'  => 'Matti M.',
				'rytkoset/member_1_email' => 'matti@example.com',
			),
			rytkoset_theme_get_membership_member_prefill_values( $user )
		);
	}

	public function test_prefill_values_skip_empty_fields(): void {
		$user = rytkoset_test_register_user( 5, 'ei-osoite', 'Matti M.' );

		$this->assertSame(
			array( 'rytkoset/member_1_name' => 'Matti M.' ),
			rytkoset_theme_get_membership_member_prefill_values( $user )
		);
	}

	public function test_prefill_values_empty_for_missing_user(): void {
		$this->assertSame( array(), rytkoset_theme_get_membership_member_prefill_values( null ) );
		$this->assertSame( array(), rytkoset_theme_get_membership_member_prefill_values( new WP_User( 0 ) ) );
	}

	// --- Availability -----------------------------------------------------------

	public function test_prefill_not_available_for_guests(): void {
		$GLOBALS['rytkoset_test_current_user'] = 0;

		$this->assertFalse( rytkoset_theme_membership_member_prefill_is_available() );
	}

	public function test_prefill_not_available_in_admin(): void {
		rytkoset_test_register_user( 5, 'matti@example.com', 'Matti' );
		$GLOBALS['rytkoset_test_current_user'] = 5;
		$GLOBALS['rytkoset_test_is_admin']     = true;

		$this->assertFalse( rytkoset_theme_membership_member_prefill_is_available() );
	}
}
