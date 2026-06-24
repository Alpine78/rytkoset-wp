<?php
/**
 * Tests for inc/event-roles.php — capability sets and role capability checks.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EventRolesTest extends Rytkoset_Theme_Test_Case {

	// --- capability sets ----------------------------------------------------

	public function test_post_type_capabilities_are_built_for_plural(): void {
		$caps = rytkoset_theme_get_event_post_type_capabilities( 'rytkoset_events' );

		$this->assertContains( 'edit_rytkoset_events', $caps );
		$this->assertContains( 'publish_rytkoset_events', $caps );
		$this->assertContains( 'delete_others_rytkoset_events', $caps );
		$this->assertCount( 10, $caps );
	}

	public function test_organizer_capabilities_include_base_and_event_caps(): void {
		$caps = rytkoset_theme_get_event_organizer_capabilities();

		$this->assertContains( 'read', $caps );
		$this->assertContains( 'upload_files', $caps );
		$this->assertContains( 'edit_rytkoset_events', $caps );
		$this->assertContains( 'edit_event_registrations', $caps );
		// array_unique must not leave duplicate entries.
		$this->assertSame( array_values( array_unique( $caps ) ), array_values( $caps ) );
	}

	public function test_forbidden_capabilities_cover_store_management(): void {
		$forbidden = rytkoset_theme_get_event_organizer_forbidden_capabilities();

		$this->assertContains( 'manage_options', $forbidden );
		$this->assertContains( 'manage_woocommerce', $forbidden );
		$this->assertContains( 'edit_products', $forbidden );
	}

	// --- role_has_capabilities ---------------------------------------------

	public function test_role_has_capabilities_true_when_all_present(): void {
		$role = new WP_Role( 'event_organizer', array( 'read' => true, 'upload_files' => true ) );

		$this->assertTrue( rytkoset_theme_role_has_capabilities( $role, array( 'read', 'upload_files' ) ) );
	}

	public function test_role_has_capabilities_false_when_one_missing(): void {
		$role = new WP_Role( 'event_organizer', array( 'read' => true ) );

		$this->assertFalse( rytkoset_theme_role_has_capabilities( $role, array( 'read', 'upload_files' ) ) );
	}

	public function test_role_lacks_capabilities_true_when_none_present(): void {
		$role = new WP_Role( 'event_organizer', array( 'read' => true ) );

		$this->assertTrue( rytkoset_theme_role_lacks_capabilities( $role, array( 'manage_options', 'edit_products' ) ) );
	}

	public function test_role_lacks_capabilities_false_when_one_present(): void {
		$role = new WP_Role( 'event_organizer', array( 'manage_options' => true ) );

		$this->assertFalse( rytkoset_theme_role_lacks_capabilities( $role, array( 'manage_options' ) ) );
	}

	public function test_non_role_returns_false_for_both_checks(): void {
		$this->assertFalse( rytkoset_theme_role_has_capabilities( null, array( 'read' ) ) );
		$this->assertFalse( rytkoset_theme_role_lacks_capabilities( null, array( 'read' ) ) );
	}
}
