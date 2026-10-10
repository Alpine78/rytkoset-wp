<?php
/**
 * Tests for inc/admin-appearance.php: the load decision behind the emergency switch.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class AdminAppearanceTest extends Rytkoset_Theme_Test_Case {

	public function test_loads_by_default(): void {
		$this->assertTrue( rytkoset_theme_admin_appearance_should_load( false ) );
	}

	public function test_constant_disables_loading(): void {
		$this->assertFalse( rytkoset_theme_admin_appearance_should_load( true ) );
	}

	public function test_filter_disables_loading(): void {
		$disable = static fn() => false;
		add_filter( 'rytkoset_theme_enable_admin_appearance', $disable );

		try {
			$this->assertFalse( rytkoset_theme_admin_appearance_should_load( false ) );
		} finally {
			remove_filter( 'rytkoset_theme_enable_admin_appearance', $disable );
		}
	}

	public function test_enqueue_loads_stylesheet_after_core_colors(): void {
		rytkoset_theme_enqueue_admin_appearance();

		$this->assertArrayHasKey( 'rytkoset-admin', $GLOBALS['rytkoset_test_enqueued_styles'] );
		$this->assertSame( array( 'colors' ), $GLOBALS['rytkoset_test_enqueued_styles']['rytkoset-admin']['deps'] );
		$this->assertStringEndsWith( '/assets/css/admin.css', $GLOBALS['rytkoset_test_enqueued_styles']['rytkoset-admin']['src'] );
	}

	public function test_enqueue_skips_stylesheet_when_filter_disables(): void {
		$disable = static fn() => false;
		add_filter( 'rytkoset_theme_enable_admin_appearance', $disable );

		try {
			rytkoset_theme_enqueue_admin_appearance();
			$this->assertArrayNotHasKey( 'rytkoset-admin', $GLOBALS['rytkoset_test_enqueued_styles'] );
		} finally {
			remove_filter( 'rytkoset_theme_enable_admin_appearance', $disable );
		}
	}

	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function test_enqueue_skips_stylesheet_when_constant_is_defined(): void {
		// A separate process because a PHP constant cannot be undefined afterwards.
		define( 'RYTKOSET_DISABLE_ADMIN_APPEARANCE', true );

		rytkoset_theme_enqueue_admin_appearance();

		$this->assertArrayNotHasKey( 'rytkoset-admin', $GLOBALS['rytkoset_test_enqueued_styles'] );
	}

	public function test_stylesheet_also_loads_in_customizer(): void {
		$callbacks = array_column( $GLOBALS['rytkoset_test_hooks']['customize_controls_enqueue_scripts'] ?? array(), 1 );

		$this->assertContains( 'rytkoset_theme_enqueue_admin_appearance', $callbacks );
	}

	public function test_editor_content_styles_mirror_public_text_by_default(): void {
		$settings = rytkoset_theme_add_admin_editor_content_styles( array( 'styles' => array( array( 'css' => 'core' ) ) ) );

		$this->assertCount( 2, $settings['styles'] );
		$this->assertSame( 'core', $settings['styles'][0]['css'] );
		$this->assertStringContainsString( 'system-ui', $settings['styles'][1]['css'] );
		$this->assertSame( 'theme', $settings['styles'][1]['__unstableType'] );
	}

	public function test_editor_content_styles_follow_the_filter_switch(): void {
		$disable = static fn() => false;
		add_filter( 'rytkoset_theme_enable_admin_appearance', $disable );

		try {
			$this->assertSame( array( 'styles' => array() ), rytkoset_theme_add_admin_editor_content_styles( array( 'styles' => array() ) ) );
		} finally {
			remove_filter( 'rytkoset_theme_enable_admin_appearance', $disable );
		}
	}

	public function test_filter_cannot_override_constant(): void {
		$enable = static fn() => true;
		add_filter( 'rytkoset_theme_enable_admin_appearance', $enable );

		try {
			$this->assertFalse( rytkoset_theme_admin_appearance_should_load( true ) );
		} finally {
			remove_filter( 'rytkoset_theme_enable_admin_appearance', $enable );
		}
	}
}
