# CLAUDE.md

@AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project context

WordPress site for Rytkösten sukuseura ry (rytkoset.net). AGENTS.md contains project principles, priorities, and collaboration model — CLAUDE.md covers the technical environment and architecture.

## Development environment

```bash
# Start local environment
docker compose up -d
# WordPress: http://localhost:8000

# Stop
docker compose down
```

Three containers: `rytkoset-wp` (WordPress/PHP 8.3), `rytkoset-db` (MariaDB), `rytkoset-joomla-db` (Joomla migration). Only `wp-content/` is mounted from host — file changes are reflected immediately without restart.

## Linting and CI

No separate build step. PHP syntax validation:

```bash
find wp-content/themes/rytkoset-theme -name "*.php" -print0 | xargs -0 -n1 php -l
```

GitHub Actions runs this automatically for every PR and `main` push (`.github/workflows/php-ci.yml`).

## Deploy

- `dev` branch → automatic FTPS deploy → `dev.rytkoset.net` (when changes in `wp-content/themes/rytkoset-theme/**`)
- `main` branch → no automatic deploy
- Production (`rytkoset.net`) deploy is manual via `.github/workflows/deploy-production.yml`, defaulting to `main` and requiring production FTPS secrets (`PROD_FTP_HOST`, `PROD_FTP_USERNAME`, `PROD_FTP_PASSWORD`, `PROD_FTP_PORT`)

## Commit messages

Conventional Commits (see `CONTRIBUTING.md`):

```
feat(events): add single event template
fix(woo): fix membership order save
docs: update README with staging instructions
refactor: split functions.php into inc/ modules
```

Do not create commits automatically — report the implementation first, suggest a commit message, and let the user review the diff before committing.

## Theme architecture

The theme `wp-content/themes/rytkoset-theme/` is the only versioned codebase. WordPress core and plugins are not in the repo.

`wp-content/maintenance.php` overrides WordPress's built-in maintenance page (shown when a `.maintenance` file exists in the WordPress root, or during core/plugin updates). It is a self-contained PHP/HTML file — all CSS is inline, Google Fonts are loaded directly, and theme assets are referenced via `WP_CONTENT_URL`. It reads `get_theme_mod()` values (`rytkoset_theme_maintenance_concept`, `rytkoset_theme_maintenance_return_text`, `rytkoset_theme_contact_email`, `custom_logo`) which are available because WordPress's options and theme APIs load before maintenance mode is triggered. Customizer settings are under **Huoltotila** in the WordPress Customizer.

### Template hierarchy

| File                         | Purpose                                             |
| ---------------------------- | --------------------------------------------------- |
| `front-page.php`             | Front page                                          |
| `page.php`                   | Static pages                                        |
| `single.php`                 | Blog post                                           |
| `single-rytkoset_event.php`  | Single event                                        |
| `single-gallery_album.php`   | Single album                                        |
| `archive-rytkoset_event.php` | Event archive (`/tapahtumat`)                       |
| `archive-gallery_album.php`  | Album archive (`/albumit`)                          |
| `header.php` / `footer.php`  | Site header; footer = pre-footer band + slim footer |

Front page (#289): `front-page.php` builds a Claude Design layout — a split hero (`.hero__content--split`) with a welcome illustration, a Sukujuhlat **feature** band as the showpiece (date/location chips + floating badge), then alternating light/dark content bands (Albumit, Jäsenyys, Kauppa, Sukututkimus/Viljo). Band tones use the `--home-band-*` tokens (`assets/css/base.css`) which adapt to the dark theme; styles live in `assets/css/home.css`. Illustrations are theme assets under `assets/images/home/`.

Footer (Footer C, #278): `footer.php` renders a pre-footer newsletter band above the slim footer on every page — `template-parts/pre-footer-large.php` on the front page (`is_front_page()`), `template-parts/pre-footer-compact.php` elsewhere. Each partial calls `rytkoset_theme_get_footer_newsletter_form()` and renders nothing when there is no form to show (active subscriber, or newsletter shortcode not configured). The slim footer (`<footer class="site-footer">`) holds brand, footer nav, contact email and social links, and shows on all pages.

### functions.php and inc/ modules

`functions.php` (~580 lines) contains theme setup, asset enqueue, header/nav helpers, and shared WooCommerce helpers (`get_order_from_admin_screen_object`, `get_supported_order_statuses`). Domain-specific logic is split into modules under `inc/`:

| File                                   | Contents                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `inc/security.php`                     | Security hardening: blocks user enumeration (REST `/wp/v2/users` logged-out, `?author=N` redirects), disables XML-RPC (`xmlrpc_enabled`, pingback methods, `X-Pingback` header), sends frontend security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` via `send_headers`; HSTS/CSP left to server), and blocks spam registrations (honeypot field + blocked email domains via `registration_errors`). Toggles `rytkoset_theme_enable_security_hardening` / `rytkoset_theme_disable_xmlrpc`; filterable via `rytkoset_theme_security_headers` and `rytkoset_theme_blocked_registration_email_patterns` |
| `inc/events.php`                       | Event CPT, meta field registration and getters                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `inc/event-registrations.php`          | Free event registration CPT and form                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `inc/event-registration-privacy.php`   | Privacy Tools export, erasure and anonymization for free event registrations                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `inc/event-participants-admin.php`     | `Events > Participants` admin view                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `inc/event-participants-messaging.php` | `Events > Messaging` bulk email queue and WP-Cron rate limiter                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `inc/event-roles.php`                  | `event_organizer` role and capabilities                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `inc/gallery-albums.php`               | Gallery Album CPT and gallery stack logic                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `inc/media-library.php`                | Media library ordering by album                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `inc/digital-magazines.php`            | Digital magazine download pages                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `inc/email.php`                        | Default mail sender (`Rytkösten sukuseura ry` / `rytkoset_theme_get_contact_email()`) for theme-sent `wp_mail()`; overrides only WordPress's default sender via `wp_mail_from`/`wp_mail_from_name`                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `inc/icons.php`                        | Inline SVG icon helper (`rytkoset_theme_inline_icon`) — reads `currentColor` glyphs from `assets/icons/{social,ui}/`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `inc/share.php`                        | Share buttons (Facebook, X, WhatsApp); icons via `rytkoset_theme_inline_icon`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `inc/social-links.php`                 | Social media links in header/footer; icons inlined via `rytkoset_theme_inline_icon`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `inc/attachment-iptc.php`              | IPTC headline and description sync for attachment images                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `inc/seo-meta.php`                     | Open Graph and Twitter Card meta tags                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `inc/login.php`                        | `wp-login.php` redesign: JS builds a split-layout brand panel + form card around `#login`, per-view copy/tabs, theme-following dark mode, Finnish translations                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `inc/newsletter.php`                   | AcyMailing newsletter integration: footer signup, subscription helpers and opt-in hooks                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `inc/woocommerce-mollie.php`           | Mollie Finnish translations, RF reference normalization                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `inc/woocommerce-membership.php`       | Membership products, checkout notice, admin column and metabox                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `inc/woocommerce-tampere-2026.php`     | Tampere 2026 participation fee: product, checkout fields, admin, organizer notifications                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `inc/woocommerce-product-sync.php`     | WooCommerce product sync tool for local <-> dev                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `inc/customizer-contact.php`           | Customizer contact fields for footer and admin email; maintenance mode concept and return-text settings                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |

All functions use `if ( ! function_exists('rytkoset_theme_...') )` guard and `rytkoset_theme_` prefix.

### CSS structure

`style.css` imports all modules; no build step:

```
assets/css/base.css          # Typography, color variables, base elements
assets/css/layout.css        # Containers, grids, sections
assets/css/components.css    # Buttons, cards, general components
assets/css/nav.base.css      # Shared navigation styles
assets/css/nav.desktop.css   # Desktop navigation
assets/css/nav.mobile.css    # Mobile navigation (hamburger)
assets/css/nav.account.css   # User/account menu
assets/css/hero.css          # Front page hero section (split layout with illustration)
assets/css/home.css          # Front page content bands (alternating light/dark, feature + story)
assets/css/gallery.css       # Gallery and albums
assets/css/footer.css        # Footer
assets/css/login.css         # WP login page branding
assets/css/responsive.css    # Media queries
```

Use CSS variables for colors and spacing. No Bootstrap dependency.

### JavaScript

`assets/js/main.js` — mobile menu toggle and other general interactions.  
`assets/js/photoswipe-init.js` — PhotoSwipe 5 lightbox initialization for album pages.  
`assets/vendor/photoswipe/` — PhotoSwipe 5 vendored (no npm/bundler).

## Custom Post Types

### Event (`event`)

- Slug: `/tapahtumat/`
- Registered in: `inc/events.php`
- Meta keys (via `rytkoset_theme_get_event_details_meta_keys()`):
  - `_rytkoset_event_date` — date `YYYY-MM-DD`
  - `_rytkoset_event_start_time` — start time `HH:MM`
  - `_rytkoset_event_end_time` — end time `HH:MM`
  - `_rytkoset_event_location` — location
  - `_rytkoset_event_fee_type` — `free` | `paid`
  - `_rytkoset_event_price_text` — price text for display
  - `_rytkoset_event_registration_deadline` — free event registration deadline `YYYY-MM-DD`; empty falls back to event date for the public form cutoff
  - `_rytkoset_event_product_id` — linked WooCommerce product for paid registration/payment
  - `_rytkoset_event_organizer_notification_recipients` — event-specific organizer notification email recipients for paid event orders

Free event registration forms close after the event registration deadline. Paid event pages read the deadline and availability state from the linked WooCommerce product instead of duplicating that data on the event.

`Events > Messaging` keeps event participant messaging in WordPress. The admin form queues messages in `rytkoset_event_messaging_queue`; WP-Cron hook `rytkoset_process_event_messaging_queue` sends queued recipients with a rolling 18 `wp_mail()` attempts / 60 minutes limit tracked in `rytkoset_event_messaging_send_attempts`.

### Gallery Album (`gallery_album`)

- Slug: `/albumit/`
- Registered in: `inc/gallery-albums.php`
- Images are WordPress media attachments with `post_parent = album_post_id`
- Order: by filename (sorted in `inc/media-library.php`)

## WooCommerce integration

WooCommerce-specific logic lives in `inc/woocommerce-*.php` modules, with small shared helpers still in `functions.php`. Key areas:

- **Membership products** — annual and lifetime membership; checkout notice when product is in cart
- **Paid event fees / Tampere 2026** — linked event products, Tampere-specific checkout fields, stale participant field hiding in admin/emails, participant list in admin, CSV export, event-specific organizer email notifications
- **Mollie payments** — Finnish language texts, output buffering on `thankyou` page for bank transfer instructions
- **PhotoSwipe conflict** — WooCommerce registers PhotoSwipe 4 scripts; theme actively dequeues them to avoid conflicts

## Navigation menus

WordPress admin-managed menus: `primary` (main menu), `footer`, `account` (user/account).

## Documentation

`docs/` contains setup and maintenance guides for WooCommerce features. Read the relevant doc before making WooCommerce changes.

`docs/newsletter.md` documents the AcyMailing footer signup setup, target list, opt-in workflows and newsletter MVP boundaries.

`docs/design-system.md` documents the theme's color tokens, radius/shadow/transition variables, layout, and component conventions. Read it before writing or modifying CSS.

`docs/media-saavutettavuus.md` documents admin-facing media accessibility rules: alt-text guidance, the gallery alt-fallback chain (`rytkoset_theme_get_gallery_image_alt()`), automatic iframe titles for album videos, and PhotoSwipe keyboard shortcuts.

`docs/woocommerce-saavutettavuus.md` is a developer-facing audit of the WooCommerce-related a11y surface: cart link aria-label, the custom listbox sort widget, custom quantity controls, Tampere 2026 / membership checkout notices, and the third-party boundaries (Mollie hosted page, WC Checkout Block).

`docs/tietoturva.md` documents the theme's security hardening (`inc/security.php`: user enumeration blocking, XML-RPC disabling, frontend security headers) and the server/ops-level checklist (2FA, login throttling, updates, backups, HSTS/CSP, uploads protection). Read it before changing `inc/security.php`.
