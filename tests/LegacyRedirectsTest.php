<?php
/**
 * Tests for inc/legacy-redirects.php (#515).
 *
 * @package Rytkoset\Tests
 */

declare( strict_types=1 );

final class LegacyRedirectsTest extends Rytkoset_Theme_Test_Case {

	public function test_normalize_strips_slashes_and_decodes_percent_encoding(): void {
		$this->assertSame(
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-8-detail',
			rytkoset_theme_normalize_legacy_redirect_path( '/kauppa/sukulehdet/rytk%C3%B6sten-sukulainen-nro-8-detail/' )
		);
	}

	public function test_normalize_ignores_query_string(): void {
		$this->assertSame(
			'haku',
			rytkoset_theme_normalize_legacy_redirect_path( '/haku?searchword=rytkonen' )
		);
	}

	public function test_normalize_returns_empty_string_for_root(): void {
		$this->assertSame( '', rytkoset_theme_normalize_legacy_redirect_path( '/' ) );
		$this->assertSame( '', rytkoset_theme_normalize_legacy_redirect_path( '' ) );
	}

	public function test_resolve_returns_null_when_path_is_empty(): void {
		$this->assertNull( rytkoset_theme_resolve_legacy_redirect_target( '', array( 'haku' => 'https://rytkoset.test/?s=' ), array() ) );
	}

	public function test_resolve_prefers_exact_match_over_prefix_rule(): void {
		$target = rytkoset_theme_resolve_legacy_redirect_target(
			'kauppa/tilinhallinta',
			array( 'kauppa/tilinhallinta' => 'https://rytkoset.test/oma-tili/' ),
			array(
				array(
					'prefixes' => array( 'kauppa' ),
					'target'   => 'https://rytkoset.test/kauppa/',
				),
			)
		);

		$this->assertSame( 'https://rytkoset.test/oma-tili/', $target );
	}

	public function test_resolve_falls_back_to_prefix_rule_for_unknown_shop_path(): void {
		$target = rytkoset_theme_resolve_legacy_redirect_target(
			'kauppa/ulkopuoliset/by,product_sku',
			array(),
			array(
				array(
					'prefixes' => array( 'kauppa' ),
					'target'   => 'https://rytkoset.test/kauppa/',
				),
			)
		);

		$this->assertSame( 'https://rytkoset.test/kauppa/', $target );
	}

	public function test_resolve_matches_forum_prefix_for_any_old_thread(): void {
		$prefix_rules = array(
			array(
				'prefixes' => array( 'foorumi', 'en/forum', 'fi/foorumi' ),
				'target'   => 'https://rytkoset.test/foorumi/',
			),
		);

		$this->assertSame(
			'https://rytkoset.test/foorumi/',
			rytkoset_theme_resolve_legacy_redirect_target( 'foorumi/rytkoset/93-mista-rytkoset-tulleet-iisalmeen', array(), $prefix_rules )
		);
		$this->assertSame(
			'https://rytkoset.test/foorumi/',
			rytkoset_theme_resolve_legacy_redirect_target( 'en/forum/rytkoset', array(), $prefix_rules )
		);
		$this->assertSame(
			'https://rytkoset.test/foorumi/',
			rytkoset_theme_resolve_legacy_redirect_target( 'foorumi', array(), $prefix_rules )
		);
	}

	public function test_resolve_prefix_rule_does_not_match_unrelated_path_with_same_start(): void {
		$prefix_rules = array(
			array(
				'prefixes' => array( 'en/forum' ),
				'target'   => 'https://rytkoset.test/foorumi/',
			),
		);

		$this->assertNull(
			rytkoset_theme_resolve_legacy_redirect_target( 'en/forumsomethingelse', array(), $prefix_rules )
		);
	}

	public function test_resolve_returns_null_when_nothing_matches(): void {
		$this->assertNull(
			rytkoset_theme_resolve_legacy_redirect_target( 'ip', array( 'haku' => 'https://rytkoset.test/?s=' ), array() )
		);
	}

	public function test_exact_map_covers_known_high_traffic_paths(): void {
		$map = rytkoset_theme_get_legacy_redirect_exact_map();

		$this->assertSame( 'https://rytkoset.test/wp-login.php', $map['kirjaudu'] );
		$this->assertSame( 'https://rytkoset.test/wp-login.php', $map['en/component/users/login'] );
		$this->assertSame( 'https://rytkoset.test/?s=', $map['haku'] );
		$this->assertSame( 'https://rytkoset.test/tapahtumat/', $map['sukuseura/sukukokous'] );
		$this->assertSame( 'https://rytkoset.test/sukuseura/jasenyys/', $map['sukuseura/perhetietolomake'] );
		$this->assertSame(
			'https://rytkoset.test/kauppa/sukulehdet/rytkosten-sukulainen-nro-8/',
			$map['kauppa/sukulehdet/rytkösten-sukulainen-nro-8-detail']
		);
		$this->assertSame(
			'https://rytkoset.test/tapahtumat/yhteiskuljetus-tampereen-sukujuhliin-iisalmi-tampere-iisalmi/',
			$map['kauppa/tapahtumat/bussikyyti-tampereen-sukujuhliin-savo-tampere-savo']
		);
	}

	public function test_prefix_rules_cover_forum_hallitus_and_shop_fallback(): void {
		$rules = rytkoset_theme_get_legacy_redirect_prefix_rules();

		$forum_target = rytkoset_theme_resolve_legacy_redirect_target( 'foorumi/testiviestit', array(), $rules );
		$this->assertSame( 'https://rytkoset.test/foorumi/', $forum_target );

		$hallitus_target = rytkoset_theme_resolve_legacy_redirect_target( 'sukuseura/sukuseuran-hallitus/antti-rytkonen', array(), $rules );
		$this->assertSame( 'https://rytkoset.test/sukuseura/sukuseuran-hallitus/', $hallitus_target );

		$shop_target = rytkoset_theme_resolve_legacy_redirect_target( 'kauppa/manufacturer/sukuseura', array(), $rules );
		$this->assertSame( 'https://rytkoset.test/kauppa/', $shop_target );
	}
}
