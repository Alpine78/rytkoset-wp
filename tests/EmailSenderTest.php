<?php
/**
 * Tests for inc/email.php — default sender overrides that only touch WordPress's own defaults.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class EmailSenderTest extends Rytkoset_Theme_Test_Case {

	public function test_mail_from_name_is_association_name(): void {
		$this->assertSame( 'Rytkösten sukuseura ry', rytkoset_theme_get_mail_from_name() );
	}

	public function test_default_wordpress_from_address_is_replaced_with_contact_email(): void {
		$this->assertSame(
			'yhteys@rytkoset.test',
			rytkoset_theme_filter_mail_from( 'wordpress@example.com' )
		);
	}

	public function test_non_default_from_address_is_preserved(): void {
		$this->assertSame(
			'tilaukset@kauppa.fi',
			rytkoset_theme_filter_mail_from( 'tilaukset@kauppa.fi' )
		);
	}

	public function test_default_wordpress_from_name_is_replaced(): void {
		$this->assertSame(
			'Rytkösten sukuseura ry',
			rytkoset_theme_filter_mail_from_name( 'WordPress' )
		);
	}

	public function test_non_default_from_name_is_preserved(): void {
		$this->assertSame(
			'WooCommerce',
			rytkoset_theme_filter_mail_from_name( 'WooCommerce' )
		);
	}
}
