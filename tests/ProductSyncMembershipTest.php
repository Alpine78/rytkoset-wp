<?php
/**
 * Tests for the WooCommerce product-sync membership meta validation (#407),
 * in inc/woocommerce-product-sync.php.
 *
 * The validator is pure: it takes the whitelisted `_rytkoset_*` meta array and
 * returns a list of error strings (empty = valid or not a membership product).
 * Allowed membership types come from the membership module, not a second
 * hardcoded list.
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class ProductSyncMembershipTest extends Rytkoset_Theme_Test_Case {

	/**
	 * Builds a meta array keyed by the canonical membership meta keys.
	 *
	 * @param array<string, string> $overrides Raw key => value overrides.
	 * @return array<string, string>
	 */
	private function meta( array $overrides ): array {
		$map = array(
			'product' => rytkoset_theme_get_membership_product_meta_key(),
			'type'    => rytkoset_theme_get_membership_type_meta_key(),
			'period'  => rytkoset_theme_get_membership_period_meta_key(),
			'expiry'  => rytkoset_theme_get_membership_expiry_date_meta_key(),
			'names'   => rytkoset_theme_get_member_names_required_meta_key(),
		);

		$meta = array();
		foreach ( $overrides as $short => $value ) {
			$meta[ $map[ $short ] ] = $value;
		}

		return $meta;
	}

	/**
	 * Flattens the summary list into a label => value map.
	 *
	 * @param array<int, array{label: string, value: string}> $summary Summary rows.
	 * @return array<string, string>
	 */
	private function summary_map( array $summary ): array {
		$map = array();
		foreach ( $summary as $row ) {
			$map[ $row['label'] ] = $row['value'];
		}

		return $map;
	}

	// --- Valid configurations ------------------------------------------------

	public function test_valid_individual_annual(): void {
		$this->assertSame(
			array(),
			rytkoset_theme_product_sync_validate_membership_meta(
				$this->meta( array( 'product' => 'yes', 'type' => 'annual_individual', 'period' => '2026' ) )
			)
		);
	}

	public function test_valid_family_annual(): void {
		$this->assertSame(
			array(),
			rytkoset_theme_product_sync_validate_membership_meta(
				$this->meta( array( 'product' => 'yes', 'type' => 'annual_family', 'period' => '2026' ) )
			)
		);
	}

	public function test_valid_lifetime_without_period(): void {
		$this->assertSame(
			array(),
			rytkoset_theme_product_sync_validate_membership_meta(
				$this->meta( array( 'product' => 'yes', 'type' => 'lifetime' ) )
			)
		);
	}

	public function test_non_membership_product_is_unaffected(): void {
		// A normal product without any membership meta passes.
		$this->assertSame( array(), rytkoset_theme_product_sync_validate_membership_meta( array() ) );
		// Explicit "no" flag also passes.
		$this->assertSame(
			array(),
			rytkoset_theme_product_sync_validate_membership_meta( $this->meta( array( 'product' => 'no' ) ) )
		);
	}

	// --- Invalid configurations ----------------------------------------------

	public function test_membership_product_without_type_fails(): void {
		$errors = rytkoset_theme_product_sync_validate_membership_meta(
			$this->meta( array( 'product' => 'yes', 'type' => '' ) )
		);
		$this->assertNotEmpty( $errors );
	}

	public function test_membership_product_with_invalid_type_fails(): void {
		$errors = rytkoset_theme_product_sync_validate_membership_meta(
			$this->meta( array( 'product' => 'yes', 'type' => 'gold_tier' ) )
		);
		$this->assertNotEmpty( $errors );
	}

	public function test_annual_without_period_fails(): void {
		$errors = rytkoset_theme_product_sync_validate_membership_meta(
			$this->meta( array( 'product' => 'yes', 'type' => 'annual_individual', 'period' => '' ) )
		);
		$this->assertNotEmpty( $errors );
	}

	public function test_family_without_period_fails(): void {
		$errors = rytkoset_theme_product_sync_validate_membership_meta(
			$this->meta( array( 'product' => 'yes', 'type' => 'annual_family' ) )
		);
		$this->assertNotEmpty( $errors );
	}

	public function test_type_without_product_flag_is_flagged(): void {
		// Conflicting state: type set but the product isn't marked a membership product.
		$errors = rytkoset_theme_product_sync_validate_membership_meta(
			$this->meta( array( 'type' => 'annual_individual' ) )
		);
		$this->assertNotEmpty( $errors );
	}

	public function test_period_without_product_flag_is_flagged(): void {
		$errors = rytkoset_theme_product_sync_validate_membership_meta(
			$this->meta( array( 'period' => '2026' ) )
		);
		$this->assertNotEmpty( $errors );
	}

	// --- Readable preview summary --------------------------------------------

	public function test_summary_empty_for_non_membership(): void {
		$this->assertSame( array(), rytkoset_theme_product_sync_get_membership_summary( array() ) );
	}

	public function test_summary_lists_membership_settings(): void {
		$summary = rytkoset_theme_product_sync_get_membership_summary(
			$this->meta(
				array(
					'product' => 'yes',
					'type'    => 'annual_family',
					'period'  => '2026',
					'expiry'  => '2026-07-30',
					'names'   => 'yes',
				)
			)
		);

		$labels = $this->summary_map( $summary );

		$this->assertSame( rytkoset_theme_get_membership_type_label( 'annual_family' ), $labels['Tyyppi'] );
		$this->assertSame( '2026', $labels['Jäsenkausi'] );
		$this->assertSame( 'Kyllä', $labels['Jäsenten nimet vaaditaan'] );
		$this->assertArrayHasKey( 'Jäsenyys voimassa asti', $labels );
		$this->assertSame( '2026-07-30', $labels['Jäsenyys voimassa asti'] );
	}

	public function test_summary_omits_expiry_when_unset(): void {
		$summary = rytkoset_theme_product_sync_get_membership_summary(
			$this->meta( array( 'product' => 'yes', 'type' => 'lifetime' ) )
		);
		$labels = $this->summary_map( $summary );

		$this->assertArrayNotHasKey( 'Jäsenyys voimassa asti', $labels );
	}
}
