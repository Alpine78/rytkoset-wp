<?php
/**
 * Vanhojen Joomla-osoitteiden 301-uudelleenohjaukset (#515).
 *
 * Rank Math SEO:n 404-monitorista löytyi satoja osumia vanhalta
 * Joomla-sivustolta. Tämä moduuli ohjaa 301:llä vain toistuvat, selkeästi
 * kohdennettavat polut nykyiselle vastineelle template_redirect-koukussa —
 * ei jokaista 404-riviä. Ei automaattista/älykästä URL-täsmäystä (esim.
 * vanhojen foorumiketjujen 1:1-mäppäystä uuteen bbPress-foorumiin):
 * yksittäiset foorumiketjut ohjataan vain foorumin etusivulle, ja
 * tunnistamattomat vanhan VirtueMart-kaupan osoitteet kaupan etusivulle.
 *
 * @package Rytkoset_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rytkoset_theme_normalize_legacy_redirect_path' ) ) {
	/**
	 * Normalisoi pyynnön polun taulukkovertailua varten.
	 *
	 * Purkaa prosenttikoodauksen (esim. vanhat ö/ä-kirjaimet sisältävät
	 * VirtueMart-osoitteet) ja siistii alku-/loppukauttaviivat. Kyselymerkkijono
	 * jää huomiotta.
	 *
	 * @param string $request_uri Raaka pyyntöosoite, esim. $_SERVER['REQUEST_URI'].
	 * @return string Normalisoitu polku ilman alku-/loppukauttaviivaa, tai tyhjä merkkijono.
	 */
	function rytkoset_theme_normalize_legacy_redirect_path( $request_uri ) {
		$path = wp_parse_url( (string) $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		return trim( rawurldecode( $path ), '/' );
	}
}

if ( ! function_exists( 'rytkoset_theme_resolve_legacy_redirect_target' ) ) {
	/**
	 * Etsii uudelleenohjauskohteen normalisoidulle polulle.
	 *
	 * Tarkka taulukko tarkistetaan ensin. Jos täsmäystä ei löydy, käydään läpi
	 * etuliitesäännöt (esim. foorumin yksittäiset ketjut foorumin etusivulle).
	 *
	 * @param string $normalized_path Ks. rytkoset_theme_normalize_legacy_redirect_path().
	 * @param array  $exact_map       Polku => kohde-URL.
	 * @param array  $prefix_rules    Lista ['prefixes' => string[], 'target' => string].
	 * @return string|null Kohde-URL, tai null jos täsmäystä ei löytynyt.
	 */
	function rytkoset_theme_resolve_legacy_redirect_target( $normalized_path, array $exact_map, array $prefix_rules ) {
		if ( '' === $normalized_path ) {
			return null;
		}

		if ( isset( $exact_map[ $normalized_path ] ) ) {
			return $exact_map[ $normalized_path ];
		}

		foreach ( $prefix_rules as $rule ) {
			foreach ( $rule['prefixes'] as $prefix ) {
				$prefix = trim( $prefix, '/' );

				if ( $normalized_path === $prefix || 0 === strpos( $normalized_path, $prefix . '/' ) ) {
					return $rule['target'];
				}
			}
		}

		return null;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_legacy_redirect_exact_map' ) ) {
	/**
	 * Tarkka polku => kohde-URL -taulukko Rank Math 404-monitorin
	 * yleisimmille vanhoille Joomla-osoitteille (#515).
	 *
	 * @return array<string, string>
	 */
	function rytkoset_theme_get_legacy_redirect_exact_map() {
		$exact_map = array(
			// Kirjautuminen ja käyttäjätili (Joomla com_users).
			'kirjaudu'                                 => wp_login_url(),
			'en/component/users/login'                 => wp_login_url(),
			'en/component/users/remind'                => wp_lostpassword_url(),
			'en/component/users/reset'                 => wp_lostpassword_url(),
			'unohtuiko-kayttajatunnus'                 => wp_lostpassword_url(),
			'unohtuiko-salasana'                       => wp_lostpassword_url(),
			'rekisteroidy'                             => wp_registration_url(),
			'en/component/users/registration'          => wp_registration_url(),
			'en/component/users'                       => wp_login_url(),
			'muokkaa-profiilia'                        => rytkoset_theme_get_my_account_endpoint_url( 'edit-account' ),
			'kauppa/tilinhallinta'                     => rytkoset_theme_get_my_account_url(),
			// WooCommercen oletusslugit; endpointit on konfiguroitu suomeksi
			// (docs/woocommerce-setup.md), joten oletusosoitteet päätyvät 404:ään.
			'my-account'                               => rytkoset_theme_get_my_account_url(),
			'oma-tili/lost-password'                   => rytkoset_theme_get_my_account_endpoint_url( 'lost-password' ),

			// Haku.
			'haku'                                     => home_url( '/?s=' ),

			// Vanha englanninkielinen juuri (sivusto on suomenkielinen).
			'en'                                       => home_url( '/' ),

			// Legacy homepage aliases observed in the 404 monitor.
			'fi'                                       => home_url( '/' ),
			'etusivu'                                  => home_url( '/' ),
			'index.html'                               => home_url( '/' ),

			// Sukuseura-sivut.
			'sukuseura/sukukokous'                     => home_url( '/tapahtumat/' ),
			'sukuseura/sukukokous.html'                => home_url( '/tapahtumat/' ),
			'sukuseura/tapahtumat'                     => home_url( '/tapahtumat/' ),
			'sukuseura/tapahtumat.html'                => home_url( '/tapahtumat/' ),
			'sukuseura/jaesenyys'                      => home_url( '/sukuseura/jasenyys/' ),
			'sukuseura/jasenyys.html'                  => home_url( '/sukuseura/jasenyys/' ),
			'sukuseura/hallitus.html'                  => home_url( '/sukuseura/sukuseuran-hallitus/' ),
			'sukuseura/toimintakertomus.html'          => home_url( '/sukuseura/toimintakertomus/' ),
			'sukuseura/sukuseuran-saannot'             => home_url( '/sukuseura/saannot/' ),
			'sukuseura/content/5-sukututkimus'         => home_url( '/sukuseura/sukututkimus/' ),
			'sukututkimus'                             => home_url( '/sukuseura/sukututkimus/' ),

			// Galleria: /kuvat/ ei ole koskaan ollut olemassa, mutta siihen on
			// linkitetty (mm. tukichatin aiemmin keksimä osoite) — albumit on kohde.
			'kuvat'                                    => home_url( '/albumit/' ),

			// Tapahtumat: linkkejä, joiden perässä on vahingossa ylimääräinen sana.
			'tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/Tervetuloa' => home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ),
			// Toteutumaton yhteiskuljetus on poistettu julkisista tapahtumista
			// (#645); sen osoitteet ohjataan sukujuhlatapahtuman ajantasaiseen
			// tiedotteeseen.
			'tapahtumat/yhteiskuljetus-tampereen-sukujuhliin-iisalmi-tampere-iisalmi' => home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ),
			'tapahtumat/yhteiskuljetus-tampereen-sukujuhliin-iisalmi-tampere-iisalmi/Jos' => home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ),
			'tapahtumat/yhteiskuljetus-tampereen-sukujuhliin-iisalmi-tampere-iisalmi/Lisätietoa' => home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ),
			'tapahtumat/yhteiskuljetus-tampereen-sukujuhliin-iisalmi-tampere-iisalmi/Lisätiedot' => home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ),
			// Vanha maksullinen bussituote korvattiin #450:n maksuttomalla
			// ilmoittautumisella; yhteiskuljetuksen peruunnuttua myös sen
			// osoite ohjataan sukujuhlatapahtumaan.
			'kauppa/tapahtumat/bussikyyti-tampereen-sukujuhliin-savo-tampere-savo' => home_url( '/tapahtumat/rytkosten-sukukokous-tampereella-29-8-2026/' ),

			// Maksutuotteet (sama tuote on yhä myynnissä samalla tunnisteella).
			'tuote/tampere-2026-osallistumismaksu'     => home_url( '/kauppa/tapahtumat/tampere-2026-osallistumismaksu/' ),
			'tuote/ainaisjasenmaksu'                   => home_url( '/kauppa/jasenyys/ainaisjasenmaksu/' ),
			'tuote/vuosijasenmaksu-yksityishenkilo'    => home_url( '/kauppa/jasenyys/vuosijasenmaksu-yksityishenkilo/' ),
			'tuote/vuosijasenmaksu-perhe'              => home_url( '/kauppa/jasenyys/vuosijasenmaksu-perhe/' ),

			// Rytkösten sukulainen -lehdet (numerot 2-9 yhä myynnissä).
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-2-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-2/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-3-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-3/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-4-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-4/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-5-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-5/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-6-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-6/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-7-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-7/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-8-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-8/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-9-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-9/' ),
			'kauppa/sukulehdet/rytkösten-sukulainen-nro-9-detail/recommend' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-9/' ),
			// Vanha litteä (ei kategoriapolkua) osoitemuoto samalle lehdelle.
			'kauppa/rytkösten-sukulainen-nro-5-detail' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-5/' ),
			// Earlier flat product URLs from the Joomla shop.
			'sukuseura/tuotteet/rytkosten_sukulainen_nro_2.html' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-2/' ),
			'tuotteet/rytkosten_sukulainen_nro_4.html' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-4/' ),
			'tuotteet/rytkosten_sukulainen_nro_5.html' => home_url( '/kauppa/sukulehdet/rytkosten-sukulainen-nro-5/' ),

			// Muut tuotteet.
			'kauppa/muut-tuotteet/rytkösten-sukuseuran-isännänviiri-detail' => home_url( '/kauppa/muut-tuotteet-2/rytkosten-sukuseuran-isannanviiri/' ),
			'kauppa/muut-tuotteet/t-paita-rytkösten-sukuseuran-logolla-detail' => home_url( '/kauppa/vaatteet/t-paita-rytkosten-sukuseuran-logolla/' ),
			'kauppa/muut-tuotteet/t-paita-rytkösten-sukuseuran-logolla-xl-detail' => home_url( '/kauppa/vaatteet/t-paita-rytkosten-sukuseuran-logolla/' ),
			'kauppa/muut-tuotteet/t-paita-rytkösten-sukuseuran-logolla-xxl-detail' => home_url( '/kauppa/vaatteet/t-paita-rytkosten-sukuseuran-logolla/' ),
			'tuotteet/rytkosten_isannanviiri.html'     => home_url( '/kauppa/muut-tuotteet-2/rytkosten-sukuseuran-isannanviiri/' ),

			// Tuotekategoriat.
			'kauppa/sukukirjat'                        => home_url( '/tuote-osasto/sukukirjat/' ),
			'kauppa/sukulehdet'                        => home_url( '/tuote-osasto/sukulehdet/' ),
			'kauppa/muut-tuotteet'                     => home_url( '/tuote-osasto/muut-tuotteet-2/' ),

			// Vanhan VirtueMart-kaupan juuri.
			'component/virtuemart'                     => home_url( '/kauppa/' ),
		);

		// Malformed legacy gallery feed URLs with query arguments in the path.
		// The prefix rule below does not catch these because the path continues
		// with "&" instead of "/" after the prefix.
		$exact_map['valokuvat&format=feed&Itemid=175&type=rss'] = home_url( '/albumit/' );
		$exact_map['valokuvat&format=feed&Itemid=175']          = home_url( '/albumit/' );

		// Flat legacy URL for the current family book product.
		$exact_map['sukuseura/tuotteet/sukukirja_pohjois-savon_rytkoset.html'] = home_url( '/kauppa/sukukirjat/rytkosia-sukupolvesta-toiseen/' );

		return $exact_map;
	}
}

if ( ! function_exists( 'rytkoset_theme_get_legacy_redirect_prefix_rules' ) ) {
	/**
	 * Etuliitesäännöt vanhoille osoitteille, joita ei kannata mäpätä
	 * yksitellen: foorumin yksittäiset ketjut ja tuntemattomat vanhan
	 * VirtueMart-kaupan osoitteet ohjataan vain oman osionsa etusivulle.
	 *
	 * @return array<int, array{prefixes: string[], target: string}>
	 */
	function rytkoset_theme_get_legacy_redirect_prefix_rules() {
		return array(
			// Entire legacy gallery section: albums, tags, authors and feeds.
			// Sukujuhlat 2006/2009 are ancient static slide-show galleries
			// (incl. slides/*.php subpaths) predating even the Joomla site.
			array(
				'prefixes' => array( 'valokuvat', 'Sukujuhlat2006', 'Sukujuhlat_2009' ),
				'target'   => home_url( '/albumit/' ),
			),
			// Vanha perhetietolomake (myös content/*-alipolut) → sukututkimussivu.
			array(
				'prefixes' => array( 'sukuseura/perhetietolomake' ),
				'target'   => home_url( '/sukuseura/sukututkimus/' ),
			),
			array(
				'prefixes' => array( 'foorumi', 'en/forum', 'fi/foorumi' ),
				'target'   => home_url( '/foorumi/' ),
			),
			array(
				'prefixes' => array( 'sukuseura/sukuseuran-hallitus' ),
				'target'   => home_url( '/sukuseura/sukuseuran-hallitus/' ),
			),
			// Varaohjaus kaikille muille tunnistamattomille vanhan
			// VirtueMart-kaupan osoitteille (esim. kategorialajittelut,
			// tuntemattomat kategoriat, "notify"-toiminnot).
			array(
				'prefixes' => array( 'kauppa' ),
				'target'   => home_url( '/kauppa/' ),
			),
		);
	}
}

if ( ! function_exists( 'rytkoset_theme_maybe_redirect_legacy_url' ) ) {
	/**
	 * Ohjaa 404:ään päätyneen vanhan Joomla-osoitteen 301:llä nykyiselle
	 * vastineelle, jos osoite löytyy taulukosta tai täsmää etuliitesääntöön.
	 *
	 * @return void
	 */
	function rytkoset_theme_maybe_redirect_legacy_url() {
		if ( ! is_404() || is_admin() ) {
			return;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request_uri     = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$normalized_path = rytkoset_theme_normalize_legacy_redirect_path( $request_uri );

		$target = rytkoset_theme_resolve_legacy_redirect_target(
			$normalized_path,
			rytkoset_theme_get_legacy_redirect_exact_map(),
			rytkoset_theme_get_legacy_redirect_prefix_rules()
		);

		if ( ! $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}
}

add_action( 'template_redirect', 'rytkoset_theme_maybe_redirect_legacy_url' );
