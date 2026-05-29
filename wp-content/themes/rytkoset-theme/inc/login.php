<?php
/**
 * Kirjautumissivun brändäys: tyylit, logo, otsikko ja suomenkieliset käännökset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tyylitellään kirjautumissivu teeman mukaiseksi.
 */
function rytkoset_theme_login_assets() {
	$theme_version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'rytkoset-theme-base',
		get_template_directory_uri() . '/assets/css/base.css',
		array(),
		$theme_version
	);

	wp_enqueue_style(
		'rytkoset-theme-login',
		get_template_directory_uri() . '/assets/css/login.css',
		array( 'rytkoset-theme-base' ),
		$theme_version
	);

}
add_action( 'login_enqueue_scripts', 'rytkoset_theme_login_assets' );

/**
 * Palauttaa login-sivun logon URL:n (custom logo -> site icon -> tyhjä).
 *
 * @return string
 */
function rytkoset_theme_get_login_logo_url() {
	$logo_url = '';

	if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
		$logo_id     = get_theme_mod( 'custom_logo' );
		$logo_source = wp_get_attachment_image_src( $logo_id, 'full' );
		$logo_url    = is_array( $logo_source ) ? $logo_source[0] : '';
	}

	if ( empty( $logo_url ) && function_exists( 'has_site_icon' ) && has_site_icon() ) {
		$logo_url = get_site_icon_url( 192 );
	}

	return $logo_url;
}

/**
 * Asetetaan teema (data-theme) heti <head>:ssä sivuston valinnan mukaan.
 *
 * Kirjautumissivulla ei ladata teemanvaihtonappia eikä main.js:ää, joten
 * luetaan sama localStorage-avain ('rytkoset-theme', fallback prefers-color-scheme)
 * ja asetetaan attribuutti ennen renderöintiä — estää teeman välähdyksen.
 */
function rytkoset_theme_login_theme_attribute() {
	?>
	<script>
		(function () {
			try {
				var stored = localStorage.getItem('rytkoset-theme');
				var theme = (stored === 'dark' || stored === 'light')
					? stored
					: (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
				document.documentElement.setAttribute('data-theme', theme);
			} catch (e) {}
		})();
	</script>
	<?php
}
add_action( 'login_head', 'rytkoset_theme_login_theme_attribute' );

/**
 * Palauttaa kirjautumissivun näkymäkohtaiset tekstit ja asetukset JS:lle.
 *
 * @return array
 */
function rytkoset_theme_get_login_layout_config() {
	$views = array(
		'login'  => array(
			'eyebrow'        => __( 'Jäsenten alue', 'rytkoset-theme' ),
			'title'          => __( 'Tervetuloa takaisin', 'rytkoset-theme' ),
			'sub'            => __( 'Kirjaudu sisään jatkaaksesi matkaa. Kehitämme sivustolle jatkuvasti uusia ominaisuuksia rekisteröityneille käyttäjille.', 'rytkoset-theme' ),
			'brandEyebrow'   => __( 'Rytkösten sukuseura', 'rytkoset-theme' ),
			'brandHeadline'  => __( 'Sukulaisten oma kohtaamispaikka.', 'rytkoset-theme' ),
			'brandLede'      => __( 'Kirjaudu sisään ja jatka matkaa suvun tarinoiden, kuvien ja tapahtumien parissa.', 'rytkoset-theme' ),
			'switchText'     => __( 'Ei vielä tunnusta?', 'rytkoset-theme' ),
			'switchLinkText' => __( 'Liity sukuseuraan', 'rytkoset-theme' ),
			'switchLinkUrl'  => wp_registration_url(),
		),
		'register' => array(
			'eyebrow'        => __( 'Uusi käyttäjä', 'rytkoset-theme' ),
			'title'          => __( 'Luo tunnus', 'rytkoset-theme' ),
			'sub'            => __( 'Tervetuloa sivustolle - Rytkösiä sukupolvesta toiseen.', 'rytkoset-theme' ),
			'brandEyebrow'   => __( 'Liity mukaan', 'rytkoset-theme' ),
			'brandHeadline'  => __( 'Tule osaksi suvun tarinaa.', 'rytkoset-theme' ),
			'brandLede'      => __( 'Luo oma käyttäjätunnus, niin pääset aktiivisemmin mukaan sukuseuran verkkotoimintaan', 'rytkoset-theme' ),
			'switchText'     => __( 'Onko sinulla jo tunnus?', 'rytkoset-theme' ),
			'switchLinkText' => __( 'Kirjaudu sisään', 'rytkoset-theme' ),
			'switchLinkUrl'  => wp_login_url(),
		),
		'forgot' => array(
			'eyebrow'        => __( 'Salasanan palautus', 'rytkoset-theme' ),
			'title'          => __( 'Unohtuiko salasana?', 'rytkoset-theme' ),
			'sub'            => __( 'Kirjoita käyttäjätunnuksesi tai sähköpostiosoitteesi, niin lähetämme sinulle linkin uuden salasanan asettamiseen.', 'rytkoset-theme' ),
			'brandEyebrow'   => __( 'Ei hätää', 'rytkoset-theme' ),
			'brandHeadline'  => __( 'Palautetaan salasanasi.', 'rytkoset-theme' ),
			'brandLede'      => __( 'Palautuslinkki on perillä hetkessä. Suku odottaa!', 'rytkoset-theme' ),
			'switchText'     => __( 'Muistitko sittenkin?', 'rytkoset-theme' ),
			'switchLinkText' => __( 'Kirjaudu sisään', 'rytkoset-theme' ),
			'switchLinkUrl'  => wp_login_url(),
		),
	);

	return array(
		'siteName'             => get_bloginfo( 'name' ),
		'tagline'              => get_bloginfo( 'description' ),
		'logoUrl'              => rytkoset_theme_get_login_logo_url(),
		'homeUrl'              => home_url( '/' ),
		'homeLabel'            => __( 'Palaa pääsivulle', 'rytkoset-theme' ),
		'privacyUrl'           => get_privacy_policy_url(),
		'privacyLabel'         => __( 'Tietosuojaseloste', 'rytkoset-theme' ),
		'loginUrl'             => wp_login_url(),
		'registerUrl'          => wp_registration_url(),
		'lostpasswordUrl'      => wp_lostpassword_url(),
		'tabLogin'             => __( 'Kirjaudu sisään', 'rytkoset-theme' ),
		'tabRegister'          => __( 'Rekisteröidy', 'rytkoset-theme' ),
		'lostpasswordLinkText' => __( 'Unohditko salasanasi?', 'rytkoset-theme' ),
		'backLinkText'         => __( 'Takaisin kirjautumiseen', 'rytkoset-theme' ),
		'icons'                => array(
			'arrowLeft' => rytkoset_theme_inline_icon( 'arrow-left', 'ui' ),
			'info'      => rytkoset_theme_inline_icon( 'info', 'ui' ),
		),
		'views'                => $views,
	);
}

/**
 * Rakentaa kirjautumissivun split-layoutin (brändipaneeli + lomakekortti).
 *
 * WordPressin lomakemerkintä säilytetään; brändipaneeli, välilehdet,
 * näkymäotsikko ja alalinkit injektoidaan #login-elementin ympärille.
 */
function rytkoset_theme_login_layout_script() {
	$config = rytkoset_theme_get_login_layout_config();
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var CFG = <?php echo wp_json_encode( $config ); ?>;
			var loginEl = document.getElementById('login');
			if (!loginEl || document.querySelector('.auth-split')) return;

			var params = new URLSearchParams(window.location.search);
			var action = params.get('action') || 'login';
			var view = (action === 'register') ? 'register'
				: (action === 'lostpassword' || action === 'retrievepassword') ? 'forgot'
				: (action === 'login' || action === '') ? 'login'
				: 'other';
			var v = CFG.views[view] || CFG.views.login;

			function el(tag, cls, text) {
				var e = document.createElement(tag);
				if (cls) e.className = cls;
				if (text != null) e.textContent = text;
				return e;
			}
			function icon(svg) {
				var s = el('span', 'auth-ic');
				s.setAttribute('aria-hidden', 'true');
				s.innerHTML = svg;
				return s;
			}
			function linkWithLeadIcon(href, svg, text) {
				var a = el('a');
				a.href = href;
				if (svg) a.appendChild(icon(svg));
				a.appendChild(document.createTextNode((svg ? ' ' : '') + text));
				return a;
			}

			/* ---- Brändipaneeli ---- */
			var brand = el('aside', 'auth-brand');
			if (CFG.logoUrl) {
				var motif = el('img', 'brand-motif');
				motif.src = CFG.logoUrl;
				motif.alt = '';
				motif.setAttribute('aria-hidden', 'true');
				brand.appendChild(motif);
			}
			var top = el('div', 'brand-top');
			var badge = el('span', 'brand-badge');
			if (CFG.logoUrl) {
				var bi = el('img');
				bi.src = CFG.logoUrl;
				bi.alt = '';
				badge.appendChild(bi);
			}
			var titles = el('span', 'brand-titles');
			titles.appendChild(el('span', 'brand-name', CFG.siteName));
			if (CFG.tagline) titles.appendChild(el('span', 'brand-tag', CFG.tagline));
			top.appendChild(badge);
			top.appendChild(titles);
			brand.appendChild(top);

			var body = el('div', 'brand-body');
			body.appendChild(el('p', 'brand-eyebrow', v.brandEyebrow));
			body.appendChild(el('h2', 'brand-headline', v.brandHeadline));
			body.appendChild(el('p', 'brand-lede', v.brandLede));
			brand.appendChild(body);

			var foot = el('div', 'brand-foot');
			foot.appendChild(linkWithLeadIcon(CFG.homeUrl, CFG.icons.arrowLeft, CFG.homeLabel));
			if (CFG.privacyUrl) {
				foot.appendChild(el('span', 'dot'));
				var pl = el('a', null, CFG.privacyLabel);
				pl.href = CFG.privacyUrl;
				foot.appendChild(pl);
			}
			brand.appendChild(foot);

			/* ---- Split-kehys: siirretään #login lomakepuolelle ---- */
			var split = el('div', 'auth-split');
			var stage = el('div', 'auth-stage');
			loginEl.parentNode.insertBefore(split, loginEl);
			split.appendChild(brand);
			split.appendChild(stage);
			stage.appendChild(loginEl);

			/* ---- Kortin yläosa: välilehdet / back-linkki + näkymäotsikko ---- */
			var heading = loginEl.querySelector('h1');
			var ref = heading ? heading.nextSibling : loginEl.firstChild;
			function insertRef(node) { return loginEl.insertBefore(node, ref); }

			if (view === 'login' || view === 'register') {
				var tabs = el('div', 'auth-tabs');
				tabs.setAttribute('role', 'tablist');
				var t1 = el('a', 'auth-tab' + (view === 'login' ? ' is-active' : ''), CFG.tabLogin);
				t1.href = CFG.loginUrl;
				t1.setAttribute('role', 'tab');
				t1.setAttribute('aria-selected', view === 'login' ? 'true' : 'false');
				var t2 = el('a', 'auth-tab' + (view === 'register' ? ' is-active' : ''), CFG.tabRegister);
				t2.href = CFG.registerUrl;
				t2.setAttribute('role', 'tab');
				t2.setAttribute('aria-selected', view === 'register' ? 'true' : 'false');
				tabs.appendChild(t1);
				tabs.appendChild(t2);
				insertRef(tabs);
			} else if (view === 'forgot') {
				var back = linkWithLeadIcon(CFG.loginUrl, CFG.icons.arrowLeft, CFG.backLinkText);
				back.className = 'back-link';
				insertRef(back);
			}

			if (CFG.views[view]) {
				var head = el('div', 'auth-view-head');
				head.appendChild(el('p', 'auth-view-eyebrow', v.eyebrow));
				head.appendChild(el('h1', 'auth-view-title', v.title));
				head.appendChild(el('p', 'auth-view-sub', v.sub));
				insertRef(head);
			}

			/* ---- Register: info-ikoni vahvistustekstin (#reg_passmail) eteen ---- */
			if (view === 'register' && CFG.icons.info) {
				var note = loginEl.querySelector('#reg_passmail');
				if (note && !note.querySelector('.auth-ic')) {
					note.classList.add('auth-note');
					note.insertBefore(icon(CFG.icons.info), note.firstChild);
				}
			}

			/* ---- Login: "Unohditko salasanasi?" rivin oikeaan reunaan ---- */
			if (view === 'login') {
				var fmn = loginEl.querySelector('.forgetmenot');
				if (fmn) {
					var lp = el('a', 'auth-inline-link', CFG.lostpasswordLinkText);
					lp.href = CFG.lostpasswordUrl;
					fmn.appendChild(lp);
				}
			}

			/* ---- Alalinkki: korvataan WP:n #nav näkymäkohtaisella switch-rivillä ---- */
			if (CFG.views[view] && v.switchText) {
				var sw = el('p', 'auth-switch');
				sw.appendChild(document.createTextNode(v.switchText + ' '));
				var swa = el('a', null, v.switchLinkText);
				swa.href = v.switchLinkUrl;
				sw.appendChild(swa);
				var nav = loginEl.querySelector('#nav');
				if (nav) {
					nav.parentNode.insertBefore(sw, nav);
					nav.parentNode.removeChild(nav);
				} else {
					loginEl.appendChild(sw);
				}
			}
		});
	</script>
	<?php
}
add_action( 'login_footer', 'rytkoset_theme_login_layout_script' );


/**
 * Korvataan login-logon linkki etusivun urlilla.
 *
 * @return string
 */
function rytkoset_theme_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'rytkoset_theme_login_header_url' );

/**
 * Käytetään sivuston nimeä logon tekstinä.
 *
 * @param string $text Oletusteksti.
 * @return string
 */
function rytkoset_theme_login_header_text( $text ) {
	$site_name = get_bloginfo( 'name' );

	return $site_name ? $site_name : $text;
}
add_filter( 'login_headertext', 'rytkoset_theme_login_header_text' );

/**
 * Käännetään kirjautumissivun avaintekstit suomeksi.
 *
 * @param string $translated Alkuperäinen käännös.
 * @param string $original   Lähdeteksti.
 * @param string $domain     Tekstidomain.
 * @return string
 */
function rytkoset_theme_login_finnish_strings( $translated, $original, $domain ) {
	if ( 'default' !== $domain ) {
		return $translated;
	}

	$back_link = html_entity_decode( '&larr; Go to %s', ENT_QUOTES, 'UTF-8' );

	$map = array(
		'Username or Email Address' => 'Käyttäjätunnus tai sähköposti',
		'Password'                  => 'Salasana',
		'Remember Me'               => 'Muista minut',
		'Log In'                    => 'Kirjaudu sisään',
		'Log in'                    => 'Kirjaudu sisään',
		'Lost your password?'       => 'Unohditko salasanasi?',
		'Register'                  => 'Rekisteröidy',
		'Register For This Site'    => 'Rekisteröidy tälle sivustolle',
		'Username'                  => 'Käyttäjätunnus',
		'Email'                     => 'Sähköposti',
		'Registration confirmation will be emailed to you.' => 'Lähetämme vahvistuslinkin antamaasi sähköpostiin.',
		'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' => 'Syötä käyttäjätunnuksesi tai sähköpostiosoitteesi saadaksesi ohjeet salasanan vaihtoon.',
		'Get New Password'          => 'Lähetä palautuslinkki',
		$back_link                  => '← Palaa Rytkösten sukuseuran pääsivulle',
		'← Go to %s'                => '← Palaa Rytkösten sukuseuran pääsivulle',
		'&larr; Go to %s'           => '← Palaa Rytkösten sukuseuran pääsivulle',
		'&larr; Back to %s'         => '← Palaa Rytkösten sukuseuran pääsivulle',
		'← Back to %s'              => '← Palaa Rytkösten sukuseuran pääsivulle',
		'← Go to Rytkösten sukuseura' => '← Palaa Rytkösten sukuseuran pääsivulle',
		'Error: Cookies are blocked due to unexpected output. For help, please see this documentation or try the support forums.' => 'Virhe: Keksit on estetty odottamattoman tulosteen takia. Lue ohjeet dokumentaatiosta tai kokeile tukifoorumeita.',
		'Error: Cookies are blocked or not supported by your browser. You must enable cookies to use WordPress.' => 'Virhe: Evästeet on estetty tai selain ei tue niitä. Ota evästeet käyttöön käyttääksesi WordPressiä.',
	);

	if ( isset( $map[ $original ] ) ) {
		return $map[ $original ];
	}

	return $translated;
}
add_filter( 'gettext', 'rytkoset_theme_login_finnish_strings', 10, 3 );

/**
 * Piilotetaan WordPressin sisäinen kielenvalitsin — sivusto on suomenkielinen.
 */
add_filter( 'login_display_language_dropdown', '__return_false' );

/**
 * Varmistetaan, että back-linkki on suomeksi, vaikka gettext ei osuisi.
 */
function rytkoset_theme_login_backlink_text() {
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var back = document.getElementById('backtoblog');
			if (!back) return;
			var link = back.querySelector('a');
			if (link) {
				link.textContent = '← Palaa Rytkösten sukuseuran pääsivulle';
			}
		});
	</script>
	<?php
}
add_action( 'login_footer', 'rytkoset_theme_login_backlink_text' );
