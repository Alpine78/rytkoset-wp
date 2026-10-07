<?php
/**
 * Tests for the membership section on the WordPress user profile screen (#701).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class UserMembershipProfileFieldsTest extends Rytkoset_Theme_Test_Case {

	public function test_family_sharing_section_and_fields_render_once(): void {
		$GLOBALS['rytkoset_test_caps']['edit_users'] = true;

		ob_start();
		try {
			rytkoset_theme_render_user_membership_fields( new WP_User( 41, 'jasen@example.test' ) );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertSame( 1, substr_count( $html, 'Perhejäsenyyden jakamisoikeus' ) );

		// Duplicate inputs share a name; PHP keeps only the last POST value, which could
		// silently discard an edit made in the first copy.
		foreach ( array( rytkoset_theme_get_family_membership_period_meta_key(), rytkoset_theme_get_family_membership_expires_meta_key() ) as $field ) {
			$this->assertSame( 1, substr_count( $html, 'name="' . $field . '"' ), $field );
			$this->assertSame( 1, substr_count( $html, 'id="' . $field . '"' ), $field );
			$this->assertSame( 1, substr_count( $html, 'for="' . $field . '"' ), $field );
		}
	}
}
