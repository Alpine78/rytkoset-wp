<?php
/**
 * Rytkösten sukuseura — Huoltotila
 *
 * WordPress käyttää tätä tiedostoa automaattisesti, kun huoltotila on päällä
 * (.maintenance-tiedosto wp-juuressa tai WordPressin päivityksen aikana).
 * Tämä ohittaa WordPressin oletushuoltotilasivun.
 *
 * Asetukset (Customizer → Ylläpito):
 *   - rytkoset_theme_maintenance_concept      uudistus|huolto|talkoot
 *   - rytkoset_theme_maintenance_return_text  esim. "Takaisin elokuussa 2026"
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// HTTP-otsakkeet — huoltotila on oikea 503-vastaus, ei 200.
// WordPressin ydin ei lähetä otsakkeita, kun tämä tiedosto on olemassa, joten
// teemme sen itse. Funktiokutsut suojataan, koska WordPress on vasta osittain
// ladattu. Tämä on ennen kaikkea tulostusta, jotta otsakkeet ehtivät matkaan.
// ---------------------------------------------------------------------------

if ( ! headers_sent() ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	if ( function_exists( 'status_header' ) ) {
		status_header( 503 );
	} else {
		header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1' ) . ' 503 Service Unavailable', true, 503 );
	}
	header( 'Retry-After: 3600' );
	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	} else {
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
	}
}

// ---------------------------------------------------------------------------
// Escaping-varafunktiot — WordPressin formatting.php (esc_url/esc_html/esc_attr)
// latautuu vasta wp_maintenance()-kutsun jälkeen, joten ne eivät ole vielä
// käytettävissä, kun tämä tiedosto ajetaan oikeasta .maintenance-tilasta.
// Määritellään kevyet, turvalliset varafunktiot, jos ne puuttuvat. WordPress
// ajaa die() tämän inkluusion jälkeen, joten redeclare-riskiä ei ole.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$url = (string) $url;
		// Salli vain turvalliset protokollat sekä suhteelliset, mailto- ja ankkuri-URLit.
		if ( '' !== $url && ! preg_match( '#^(https?:|mailto:|/|\#)#i', $url ) ) {
			return '';
		}
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

// ---------------------------------------------------------------------------
// Perustiedot — WordPressin osittain ladattu tila tarjoaa nämä.
// ---------------------------------------------------------------------------

$mnt_site_name    = function_exists( 'get_option' ) ? (string) get_option( 'blogname', 'Rytkösten sukuseura' ) : 'Rytkösten sukuseura';
$mnt_home_url     = function_exists( 'home_url' ) ? esc_url( home_url( '/' ) ) : '/';
$mnt_login_url    = function_exists( 'wp_login_url' ) ? esc_url( wp_login_url() ) : '/wp-login.php';
$mnt_theme_url    = defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL . '/themes/rytkoset-theme' : '/wp-content/themes/rytkoset-theme';

// Yhteyssähköposti
$mnt_email = 'info@rytkoset.net';
if ( function_exists( 'get_theme_mod' ) ) {
	$mnt_email_raw = get_theme_mod( 'rytkoset_theme_contact_email', 'info@rytkoset.net' );
	if ( function_exists( 'sanitize_email' ) && $mnt_email_raw ) {
		$mnt_email = sanitize_email( $mnt_email_raw ) ?: 'info@rytkoset.net';
	}
}

// Viestisävy (customizer-asetus)
$mnt_concept     = 'uudistus';
$mnt_return_text = '';
if ( function_exists( 'get_theme_mod' ) ) {
	$concept_raw = get_theme_mod( 'rytkoset_theme_maintenance_concept', 'uudistus' );
	if ( in_array( $concept_raw, array( 'uudistus', 'huolto', 'talkoot' ), true ) ) {
		$mnt_concept = $concept_raw;
	}
	$mnt_return_text = (string) get_theme_mod( 'rytkoset_theme_maintenance_return_text', '' );
}

// Logo-URL (customizer custom_logo)
$mnt_logo_url = '';
if ( function_exists( 'get_theme_mod' ) && function_exists( 'wp_get_attachment_image_src' ) ) {
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo_src = wp_get_attachment_image_src( (int) $logo_id, 'full' );
		if ( is_array( $logo_src ) && ! empty( $logo_src[0] ) ) {
			$mnt_logo_url = esc_url( $logo_src[0] );
		}
	}
}

// ---------------------------------------------------------------------------
// Kopiotekstit kolmelle konseptille
// ---------------------------------------------------------------------------

$mnt_concepts = array(
	'uudistus' => array(
		'status'  => 'Sivusto päivittyy',
		'title'   => 'Sukusivut saavat uuden ilmeen',
		'lede'    => 'Kokoamme Rytkösten sivuja uuteen uskoon — albumit, tapahtumat ja kauppa palaavat pian entistä ehompina. Kiitos kärsivällisyydestä.',
		'caption' => 'Suvun tarina jatkuu — pian uudessa asussa.',
	),
	'huolto'   => array(
		'status'  => 'Huoltokatko',
		'title'   => 'Pieni huoltohetki, kohta jatketaan',
		'lede'    => 'Sivusto on hetken huollossa. Olemme pian taas pystyssä — sillä välin tavoitat meidät sähköpostitse ja sosiaalisessa mediassa.',
		'caption' => 'Sukupuu, sukukirja ja muistot — kohta taas selattavissa.',
	),
	'talkoot'  => array(
		'status'  => 'Sivustoa rakennetaan',
		'title'   => 'Talkoot käynnissä — sivut uudistuvat',
		'lede'    => 'Rakennamme sukuseuran sivuista selkeämmät ja kauniimmat. Käymme läpi vanhat kuvat ja tarinat, jotta ne löytyvät jatkossa entistä helpommin.',
		'caption' => 'Yhdessä rakennettu — sukupolvesta toiseen.',
	),
);

$mnt_copy = $mnt_concepts[ $mnt_concept ];

// ---------------------------------------------------------------------------
// Sosiaalisen median linkit (oletukset; ACF ei ole vielä ladattu)
// ---------------------------------------------------------------------------

$mnt_socials = array(
	array(
		'label'   => 'Facebook',
		'url'     => 'https://www.facebook.com/rytkoset',
		'network' => 'facebook',
	),
	array(
		'label'   => 'Instagram',
		'url'     => 'https://www.instagram.com/rytkoset/',
		'network' => 'instagram',
	),
	array(
		'label'   => 'YouTube',
		'url'     => 'https://www.youtube.com/@rytkoset',
		'network' => 'youtube',
	),
	array(
		'label'   => 'X',
		'url'     => 'https://x.com/rytkoset',
		'network' => 'x',
	),
);

// ---------------------------------------------------------------------------
// Kuvan URL
// ---------------------------------------------------------------------------

$mnt_illustration = esc_url( $mnt_theme_url . '/assets/images/home/home-welcome-illustration.png' );
?>
<!doctype html>
<html lang="fi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Sivusto päivittyy — <?php echo esc_html( $mnt_site_name ); ?></title>
<meta name="robots" content="noindex, nofollow" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&display=swap" rel="stylesheet" />
<style>
/* Rytköset — Huoltotila (standalone, kaikki CSS sisäistetty) */
*, *::before, *::after { box-sizing: border-box; }

:root {
  --color-primary: #0f4c81;
  --color-primary-dark: #0b315b;
  --color-primary-deeper: #08254a;
  --color-primary-light: #3b76a8;
  --color-accent: #fbbf24;
  --color-nav-border: rgba(255, 255, 255, 0.14);
  --color-nav-hover: rgba(255, 255, 255, 0.10);
  --color-nav-surface: rgba(255, 255, 255, 0.08);
  --color-text-mute: #5b6577;
  --cream: #f6f1e7;
  --cream-edge: #ece4d3;
  --font-body: "Manrope", system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-heading: "Newsreader", Georgia, serif;
  --radius-pill: 999px;
}

html, body {
  margin: 0;
  padding: 0;
  height: 100%;
  font-family: var(--font-body);
  background: #08254a;
  -webkit-font-smoothing: antialiased;
}

img { max-width: 100%; display: block; }

.container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 32px;
}

/* =====================================================
   Huoltotila — tumma hero-tausta, lämmin korttikolumni
   ===================================================== */
.mnt {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background:
    radial-gradient(120% 90% at 85% 0%, rgba(59, 118, 168, 0.35) 0%, rgba(8, 37, 74, 0) 55%),
    linear-gradient(180deg, #0d3868 0%, var(--color-primary-dark) 55%, var(--color-primary-deeper) 100%);
  color: #fff;
  position: relative;
  overflow: hidden;
}

/* Vehnätähkä-vesileima, logon kaiku */
.mnt__watermark {
  position: absolute;
  right: -90px;
  bottom: -120px;
  width: 520px;
  opacity: 0.05;
  pointer-events: none;
  user-select: none;
  aria-hidden: true;
}

/* ---- Kapea brändipalkkki ---- */
.mnt__bar {
  position: relative;
  z-index: 2;
  border-bottom: 1px solid var(--color-nav-border);
}
.mnt__bar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 78px;
}
.mnt__brand {
  display: inline-flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
}
.mnt__brand img {
  height: 46px;
  width: auto;
  display: block;
}
.mnt__brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.12;
}
.mnt__brand-title {
  font-family: var(--font-heading);
  font-weight: 600;
  font-size: 21px;
  color: #fff;
  letter-spacing: -0.01em;
  white-space: nowrap;
}
.mnt__brand-tag {
  font-family: var(--font-body);
  font-weight: 500;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.62);
  margin-top: 2px;
  white-space: nowrap;
}
.mnt__login {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px 9px 14px;
  border-radius: var(--radius-pill);
  background: var(--color-nav-surface);
  border: 1px solid var(--color-nav-border);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: background-color 150ms ease, border-color 150ms ease, transform 150ms ease;
}
.mnt__login:hover {
  background: var(--color-nav-hover);
  border-color: rgba(255, 255, 255, 0.32);
  transform: translateY(-1px);
}
.mnt__login:focus-visible {
  outline: 3px solid var(--color-accent);
  outline-offset: 3px;
  border-radius: var(--radius-pill);
}

/* ---- Pääsisältö — split-asettelu ---- */
.mnt__inner {
  position: relative;
  z-index: 2;
  flex: 1;
  display: grid;
  grid-template-columns: 1.02fr 0.98fr;
  gap: 64px;
  align-items: center;
  padding-top: 72px;
  padding-bottom: 84px;
}

/* ---- Tekstikolumni ---- */
.mnt__status {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 7px 15px 7px 12px;
  border-radius: var(--radius-pill);
  background: rgba(251, 191, 36, 0.12);
  border: 1px solid rgba(251, 191, 36, 0.32);
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--color-accent);
  margin: 0 0 26px;
}
.mnt__dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--color-accent);
  box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.55);
  animation: mntPulse 2s ease-out infinite;
  flex-shrink: 0;
}
@keyframes mntPulse {
  0%   { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.5); }
  70%  { box-shadow: 0 0 0 11px rgba(251, 191, 36, 0); }
  100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
}
@media (prefers-reduced-motion: reduce) {
  .mnt__dot { animation: none; }
}

.mnt__title {
  font-family: var(--font-heading);
  font-weight: 500;
  font-size: clamp(38px, 5vw, 62px);
  line-height: 1.03;
  letter-spacing: -0.02em;
  margin: 0 0 20px;
  text-wrap: balance;
}
.mnt__lede {
  font-size: 18.5px;
  line-height: 1.62;
  color: rgba(255, 255, 255, 0.82);
  max-width: 500px;
  margin: 0 0 32px;
  text-wrap: pretty;
}

/* ---- Tiedot (paluuaika + yhteystieto) ---- */
.mnt__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 0 0 34px;
}
.mnt__chip {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  padding: 11px 18px;
  border-radius: 12px;
  background: var(--color-nav-surface);
  border: 1px solid var(--color-nav-border);
  font-size: 14.5px;
  color: rgba(255, 255, 255, 0.92);
}
.mnt__chip svg { color: var(--color-accent); flex-shrink: 0; }
.mnt__chip strong { font-weight: 700; color: #fff; }
.mnt__chip a {
  color: #fff;
  font-weight: 700;
  text-decoration: none;
}
.mnt__chip a:hover { text-decoration: underline; }
.mnt__chip a:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  border-radius: 3px;
}

/* ---- Some-linkit ---- */
.mnt__social {
  border-top: 1px solid var(--color-nav-border);
  padding-top: 24px;
}
.mnt__social-label {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
  margin: 0 0 13px;
}
.mnt__social-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.mnt__social-link {
  display: inline-grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.03);
  text-decoration: none;
  transition: transform 140ms ease, border-color 140ms ease, background-color 140ms ease;
}
.mnt__social-link:hover {
  transform: translateY(-1px);
  border-color: rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.06);
}
.mnt__social-link:focus-visible {
  outline: 3px solid var(--color-accent);
  outline-offset: 3px;
  border-radius: 999px;
}
.mnt__social-link svg { width: 32px; height: 32px; display: block; }

/* ---- Illustraatiokortti ---- */
.mnt__media { position: relative; }
.mnt__card {
  margin: 0;
  background: var(--cream);
  border-radius: 22px;
  padding: 26px 26px 20px;
  box-shadow: 0 30px 72px rgba(0, 0, 0, 0.36);
  border: 1px solid rgba(255, 255, 255, 0.06);
}
.mnt__card img {
  width: 100%;
  height: auto;
  display: block;
  border-radius: 12px;
}
.mnt__card-caption {
  margin: 16px 4px 2px;
  text-align: center;
  font-family: var(--font-heading);
  font-style: italic;
  font-size: 15px;
  color: var(--color-text-mute);
}
/* Kelluva merkki kortissa */
.mnt__badge {
  position: absolute;
  top: 18px;
  left: -18px;
  background: var(--color-accent);
  color: var(--color-primary-deeper);
  border-radius: 14px;
  padding: 11px 17px;
  box-shadow: 0 16px 34px rgba(8, 37, 74, 0.34);
  text-align: center;
  line-height: 1.05;
}
.mnt__badge strong {
  display: block;
  font-family: var(--font-heading);
  font-weight: 600;
  font-size: 22px;
  letter-spacing: -0.01em;
}
.mnt__badge span {
  display: block;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.13em;
  margin-top: 3px;
}

/* ---- Reagoiva asettelu ---- */
@media (max-width: 920px) {
  .mnt__inner {
    grid-template-columns: 1fr;
    gap: 44px;
    padding-top: 52px;
    padding-bottom: 64px;
  }
  .mnt__media { order: -1; max-width: 480px; }
  .mnt__brand-tag { display: none; }
}
@media (max-width: 540px) {
  .container { padding: 0 20px; }
  .mnt__bar-inner { min-height: 64px; }
  .mnt__brand img { height: 38px; }
  .mnt__brand-title { font-size: 18px; }
  .mnt__card { padding: 18px 18px 14px; }
  .mnt__login span { display: none; }
  .mnt__badge { left: 10px; top: 12px; }
}
</style>
</head>
<body>

<div class="mnt">

  <!-- Vehnätähkä-vesileima -->
  <svg class="mnt__watermark" viewBox="0 0 120 300" fill="none" aria-hidden="true" focusable="false">
    <path d="M60 300V70" stroke="#fbbf24" stroke-width="5" stroke-linecap="round" />
    <g stroke="#fbbf24" stroke-width="4" stroke-linecap="round">
      <path d="M60 70c0-14 0-24 0-40" />
      <path d="M60 68c-16 -6 -26 -16 -30 -34" />
      <path d="M60 68c16 -6 26 -16 30 -34" />
      <path d="M60 96c-16 -6 -26 -16 -30 -34" />
      <path d="M60 96c16 -6 26 -16 30 -34" />
      <path d="M60 124c-16 -6 -26 -16 -30 -34" />
      <path d="M60 124c16 -6 26 -16 30 -34" />
      <path d="M60 152c-16 -6 -26 -16 -30 -34" />
      <path d="M60 152c16 -6 26 -16 30 -34" />
      <path d="M60 180c-16 -6 -26 -16 -30 -34" />
      <path d="M60 180c16 -6 26 -16 30 -34" />
    </g>
  </svg>

  <!-- Kapea brändipalkkki -->
  <div class="mnt__bar" role="banner">
    <div class="container mnt__bar-inner">
      <a class="mnt__brand" href="<?php echo $mnt_home_url; ?>" aria-label="<?php echo esc_attr( $mnt_site_name ); ?> — etusivu">
        <?php if ( $mnt_logo_url ) : ?>
          <img src="<?php echo $mnt_logo_url; ?>" alt="" width="46" height="46" />
        <?php endif; ?>
        <span class="mnt__brand-text">
          <span class="mnt__brand-title"><?php echo esc_html( $mnt_site_name ); ?></span>
          <span class="mnt__brand-tag">Rytkösiä sukupolvesta toiseen</span>
        </span>
      </a>
      <a class="mnt__login" href="<?php echo $mnt_login_url; ?>">
        <svg viewBox="0 0 20 20" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <rect x="4" y="9" width="12" height="8" rx="2" />
          <path d="M7 9V6.5a3 3 0 0 1 6 0V9" />
        </svg>
        <span>Kirjaudu sisään</span>
      </a>
    </div>
  </div>

  <!-- Pääsisältö -->
  <div class="container mnt__inner">

    <!-- Tekstikolumni -->
    <div class="mnt__copy">
      <p class="mnt__status" role="status">
        <span class="mnt__dot" aria-hidden="true"></span>
        <?php echo esc_html( $mnt_copy['status'] ); ?>
      </p>

      <h1 class="mnt__title"><?php echo esc_html( $mnt_copy['title'] ); ?></h1>

      <p class="mnt__lede"><?php echo esc_html( $mnt_copy['lede'] ); ?></p>

      <div class="mnt__meta">
        <?php if ( ! empty( $mnt_return_text ) ) : ?>
          <span class="mnt__chip">
            <svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
              <rect x="3" y="4.5" width="14" height="12.5" rx="2" />
              <path d="M3 8h14M7 3v3M13 3v3" />
            </svg>
            <strong><?php echo esc_html( $mnt_return_text ); ?></strong>
          </span>
        <?php endif; ?>

        <span class="mnt__chip">
          <svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <rect x="2.5" y="4.5" width="15" height="11" rx="2" />
            <path d="m3 6 7 5 7-5" />
          </svg>
          <a href="mailto:<?php echo esc_attr( $mnt_email ); ?>"><?php echo esc_html( $mnt_email ); ?></a>
        </span>
      </div>

      <!-- Some-linkit -->
      <div class="mnt__social">
        <p class="mnt__social-label">Seuraa meitä sillä välin</p>
        <ul class="mnt__social-list" aria-label="Sosiaalisen median linkit">
          <?php foreach ( $mnt_socials as $social ) : ?>
            <li>
              <a class="mnt__social-link" href="<?php echo esc_url( $social['url'] ); ?>" aria-label="<?php echo esc_attr( $social['label'] ); ?>">
                <?php echo rytkoset_maintenance_social_icon( $social['network'] ); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- Illustraatiokortti -->
    <div class="mnt__media">
      <figure class="mnt__card">
        <img
          src="<?php echo $mnt_illustration; ?>"
          alt="Sukupuu, sukukirja ja valokuvakehyksiä"
          width="780"
          height="520"
          loading="eager"
        />
        <figcaption class="mnt__card-caption"><?php echo esc_html( $mnt_copy['caption'] ); ?></figcaption>
      </figure>
      <div class="mnt__badge" aria-hidden="true">
        <strong>Pian</strong>
        <span>takaisin</span>
      </div>
    </div>

  </div><!-- .mnt__inner -->
</div><!-- .mnt -->

<?php
/**
 * Palauttaa sosiaalisen median SVG-ikonin verkoston perusteella.
 *
 * @param string $network  facebook|instagram|youtube|x
 * @return string  SVG-markup (turvallinen tulostettavaksi suoraan)
 */
function rytkoset_maintenance_social_icon( $network ) {
	switch ( $network ) {
		case 'facebook':
			return '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="12" fill="#1877F2"/><path fill="#fff" d="M14.5 12.5h2L17 9.6h-2.5V8c0-.9.3-1.4 1.4-1.4H17V4c-.3 0-1.3-.1-2.3-.1-2.3 0-3.8 1.4-3.8 4v1.7H8.5v2.9h2.4V20h3.6v-7.5z"/></svg>';

		case 'instagram':
			return '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false"><defs><radialGradient id="mntIgGrad" cx="0.3" cy="1" r="1"><stop offset="0" stop-color="#FED576"/><stop offset="0.25" stop-color="#F47133"/><stop offset="0.5" stop-color="#BC3081"/><stop offset="1" stop-color="#4C63D2"/></radialGradient></defs><circle cx="12" cy="12" r="12" fill="url(#mntIgGrad)"/><g fill="none" stroke="#fff" stroke-width="1.4"><rect x="7.4" y="7.4" width="9.2" height="9.2" rx="2.6"/><circle cx="12" cy="12" r="2.4"/></g><circle cx="15.2" cy="8.8" r="0.7" fill="#fff"/></svg>';

		case 'youtube':
			return '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="12" fill="#FF0000"/><path fill="#fff" d="M17.6 9c-.1-.5-.5-.9-1-1C15.7 7.8 12 7.8 12 7.8s-3.7 0-4.6.2c-.5.1-.9.5-1 1-.2.9-.2 3-.2 3s0 2.1.2 3c.1.5.5.9 1 1 .9.2 4.6.2 4.6.2s3.7 0 4.6-.2c.5-.1.9-.5 1-1 .2-.9.2-3 .2-3s0-2.1-.2-3zM10.8 13.7V10.3l3.1 1.7-3.1 1.7z"/></svg>';

		case 'x':
		default:
			return '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="12" fill="#000"/><path fill="#fff" d="M13.27 10.89 17.54 6h-1.01l-3.71 4.31L9.81 6H6.6l4.48 6.52L6.6 18h1.01l3.92-4.56L14.2 18h3.21l-4.14-7.11zM12 12.6l-.45-.65L8 6.85h1.55l2.92 4.17.45.65 3.79 5.42H15.2L12 12.6z"/></svg>';
	}
}
?>
</body>
</html>
<?php
// WordPress ajaa die() tämän inkluusion jälkeen — ei tarvita eksplisiittistä exit-kutsua.
