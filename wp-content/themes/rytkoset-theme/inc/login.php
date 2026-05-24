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
 * Ajetaan lopuksi varmistava JS, joka asettaa logon taustakuvan ja piilottaa tekstin.
 */
function rytkoset_theme_login_logo_script() {
	$logo_url  = rytkoset_theme_get_login_logo_url();
	$site_name = get_bloginfo( 'name' );
	$tagline   = get_bloginfo( 'description' );
	$home_url  = home_url( '/' );
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// Poista mahdolliset vanhat brändiblokit
			document.querySelectorAll('#login .login-branding').forEach(function (el) {
				el.remove();
			});

			var heading = document.querySelector('#login h1');
			if (!heading) return;

			// Rakennetaan yhtenäinen brändilinkki
			var brandLink = document.createElement('a');
			brandLink.className = 'login-branding';
			brandLink.href = '<?php echo esc_url( $home_url ); ?>';

			var logoBlock = document.createElement('span');
			logoBlock.className = 'login-branding__logo';
			<?php if ( ! empty( $logo_url ) ) : ?>
			logoBlock.style.backgroundImage = 'url("<?php echo esc_url( $logo_url ); ?>")';
			<?php endif; ?>

			var text = document.createElement('span');
			text.className = 'login-branding__text';
			text.innerHTML =
				'<span class="login-branding__title"><?php echo esc_js( $site_name ); ?></span>'
				<?php if ( $tagline ) : ?> +
				'<span class="login-branding__tagline"><?php echo esc_js( $tagline ); ?></span>'
				<?php endif; ?>;

			brandLink.appendChild(logoBlock);
			brandLink.appendChild(text);

			// 🔑 ÄLÄ koske h1:een, se saa olla screen-reader-text.
			// Lisää brändikortti heti h1:n jälkeen näkyviin.
			heading.insertAdjacentElement('afterend', brandLink);
		});
	</script>
	<?php
}
add_action( 'login_footer', 'rytkoset_theme_login_logo_script' );


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
		'Registration confirmation will be emailed to you.' => 'Vahvistus rekisteröitymisestä lähetetään sähköpostiisi.',
		'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' => 'Anna käyttäjätunnus tai sähköposti. Saat sähköpostitse ohjeet salasanan vaihtoon.',
		'Get New Password'          => 'Lähetä uusi salasana',
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
