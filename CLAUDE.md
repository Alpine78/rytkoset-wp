# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project context

Rytkösten sukuseura ry:n WordPress-sivusto (rytkoset.net). Katso `AGENTS.md` projektin periaatteista, prioriteeteista ja yhteistyömallista — CLAUDE.md kattaa teknisen ympäristön ja arkkitehtuurin.

## Development environment

```bash
# Käynnistä paikallinen ympäristö
docker compose up -d
# WordPress: http://localhost:8000

# Sammuta
docker compose down
```

Kolme konttia: `rytkoset-wp` (WordPress/PHP 8.3), `rytkoset-db` (MariaDB), `rytkoset-joomla-db` (Joomla-migraatio). Vain `wp-content/` on mountattu hostilta — muutos tiedostoon näkyy välittömästi ilman uudelleenkäynnistystä.

## Linting ja CI

Ei erillistä build-vaihetta. PHP-syntaksivalidointi:

```bash
find wp-content/themes/rytkoset-theme -name "*.php" -print0 | xargs -0 -n1 php -l
```

GitHub Actions ajaa tämän automaattisesti jokaiselle PR:lle ja `main`-pushille (`.github/workflows/php-ci.yml`).

## Deploy

- `dev`-branchi → automaattinen FTPS-deploy → `dev.rytkoset.net` (kun muutoksia `wp-content/themes/rytkoset-theme/**`)
- `main`-branchi → ei automaattista deployta
- Tuotantoon (`rytkoset.net`) deploy on aina manuaalinen

## Commit-viestit

Conventional Commits (katso `CONTRIBUTING.md`):

```
feat(events): lisää yksittäisen tapahtuman template
fix(woo): korjaa membership-tilauksen tallennus
docs: päivitä README staging-ohjeilla
refactor: pilko functions.php inc/-moduuleihin
```

Älä luo committia automaattisesti — raportoi toteutus ensin, ehdota commit-viestiä, anna käyttäjän katsoa diff ennen commitia.

## Theme architecture

Teema `wp-content/themes/rytkoset-theme/` on ainoa versioitu koodipohja. WordPress-ydin ja pluginit eivät ole repossa.

### Template-hierarkia

| Tiedosto | Tarkoitus |
|----------|-----------|
| `front-page.php` | Etusivu |
| `page.php` | Staattiset sivut |
| `single.php` | Blogipostaus |
| `single-event.php` | Yksittäinen tapahtuma |
| `single-gallery_album.php` | Yksittäinen albumi |
| `archive-event.php` | Tapahtumaarkisto (`/tapahtumat`) |
| `archive-gallery_album.php` | Albumiarkisto (`/albumit`) |
| `header.php` / `footer.php` | Sivuston ylä- ja alaosa |

### functions.php ja inc/-moduulit

`functions.php` (~580 riviä) sisältää teeman perusasetukset, asset enqueue:n, header/nav-apufunktiot ja jaetut WooCommerce-apufunktiot (`get_order_from_admin_screen_object`, `get_supported_order_statuses`). Toimialakohtainen logiikka on pilkottu `inc/`-hakemiston moduuleihin:

| Tiedosto | Sisältö |
|----------|---------|
| `inc/events.php` | Event CPT, meta-kenttien rekisteröinti ja getterit |
| `inc/event-registrations.php` | Maksuttomien ilmoittautumisten CPT ja lomake |
| `inc/event-participants-admin.php` | `Tapahtumat > Osallistujat` -admin-näkymä |
| `inc/event-participants-messaging.php` | `Tapahtumat > Viestintä` -massasähköposti |
| `inc/event-roles.php` | `event_organizer`-rooli ja capabilityt |
| `inc/gallery-albums.php` | Gallery Album CPT ja galleriapinoliikenne |
| `inc/media-library.php` | Mediakirjaston järjestys albumeittain |
| `inc/digital-magazines.php` | Digitaalisten lehtien lataussivut |
| `inc/share.php` | Jako-painikkeet (Facebook, X, WhatsApp) |
| `inc/social-links.php` | Some-linkit headeriin/footeriin |
| `inc/attachment-iptc.php` | IPTC-headlinen ja -descriptionin synkronointi liitekuviin |
| `inc/seo-meta.php` | Open Graph- ja Twitter Card -metatagit |
| `inc/login.php` | Login-sivun brändäys ja suomennokset |
| `inc/woocommerce-mollie.php` | Mollie-suomennokset, RF-viitteiden normalisointi |
| `inc/woocommerce-membership.php` | Jäsenmaksutuotteet, kassailmoitus, admin-sarake ja metaboxi |
| `inc/woocommerce-tampere-2026.php` | Tampere 2026 -osallistumismaksu: tuote, checkout-kentät, admin, järjestäjäilmoitukset |

Kaikki funktiot käyttävät `if ( ! function_exists('rytkoset_theme_...') )` -suojausta ja `rytkoset_theme_` -etuliitettä.

### CSS-rakenne

`style.css` importoi kaikki moduulit; ei build-vaihetta:

```
assets/css/base.css          # Typografia, värimuuttujat, peruselementit
assets/css/layout.css        # Containerit, gridit, sectionit
assets/css/components.css    # Napit, kortit, yleiset komponentit
assets/css/nav.base.css      # Navigaation yhteiset tyylit
assets/css/nav.desktop.css   # Desktop-navigaatio
assets/css/nav.mobile.css    # Mobiilinavigaatio (hamburger)
assets/css/nav.account.css   # Käyttäjä/tili-valikko
assets/css/hero.css          # Etusivun hero-osio
assets/css/gallery.css       # Galleria ja albumit
assets/css/footer.css        # Footer
assets/css/login.css         # WP-kirjautumissivun brändäys
assets/css/responsive.css    # Media queryt
```

Käytä CSS-muuttujia väreille ja spacingille. Ei Bootstrap-riippuvuutta.

### JavaScript

`assets/js/main.js` — mobiilivalikon toggle ja muut yleiset interaktiot.  
`assets/js/photoswipe-init.js` — PhotoSwipe 5 -lightboxin alustus albumisivuille.  
`assets/vendor/photoswipe/` — PhotoSwipe 5 vendoroituna (ei npm/bundler).

## Custom Post Types

### Event (`event`)

- Slug: `/tapahtumat/`
- Rekisteröity: `inc/events.php`
- Meta-avaimet (via `rytkoset_theme_get_event_details_meta_keys()`):
  - `_rytkoset_event_date` — päivämäärä `YYYY-MM-DD`
  - `_rytkoset_event_start_time` — aloitusaika `HH:MM`
  - `_rytkoset_event_end_time` — lopetusaika `HH:MM`
  - `_rytkoset_event_location` — paikka
  - `_rytkoset_event_fee_type` — `free` | `paid`
  - `_rytkoset_event_price_text` — hintateksti näytettäväksi

### Gallery Album (`gallery_album`)

- Slug: `/albumit/`
- Rekisteröity: `inc/gallery-albums.php`
- Kuvat ovat WordPress media attachmentteja, joissa `post_parent = album_post_id`
- Järjestys: tiedostonimen mukaan (lajiteltu `inc/media-library.php`:ssä)

## WooCommerce-integraatio

WooCommerce-koodi elää tällä hetkellä `functions.php`:ssä (ei vielä `inc/`-moduuleissa). Tärkeimmät kokonaisuudet:

- **Jäsenmaksutuotteet** — vuosi- ja ainaisjäsenmaksu; kassa-huomio kun tuote korissa
- **Tampere 2026 -tapahtumamaksu** — custom checkout-kentät, osallistujalista adminissa, CSV-vienti, sähköposti-ilmoitukset järjestäjille
- **Mollie-maksut** — suomenkieliset tekstit, output-bufferointi `thankyou`-sivulla pankkisiirron ohjeille
- **PhotoSwipe-konflikti** — WooCommerce rekisteröi PhotoSwipe 4 -skriptit; teema dequeue niitä aktiivisesti konfliktien välttämiseksi

## Navigaatiomenut

WordPress-administa hallittavat menut: `primary` (päävalikko), `footer`, `account` (käyttäjä/tili).

## Dokumentaatio

`docs/`-hakemistossa on käyttöönotto- ja ylläpito-ohjeet WooCommercen eri ominaisuuksille. Lue relevantti doc ennen WooCommerce-muutoksia.
