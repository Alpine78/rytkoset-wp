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
- Production (`rytkoset.net`) deploy is always manual

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

### Template hierarchy

| File                        | Purpose                              |
| --------------------------- | ------------------------------------ |
| `front-page.php`            | Front page                           |
| `page.php`                  | Static pages                         |
| `single.php`                | Blog post                            |
| `single-rytkoset_event.php` | Single event                         |
| `single-gallery_album.php`  | Single album                         |
| `archive-rytkoset_event.php` | Event archive (`/tapahtumat`)       |
| `archive-gallery_album.php` | Album archive (`/albumit`)           |
| `header.php` / `footer.php` | Site header and footer               |

### functions.php and inc/ modules

`functions.php` (~580 lines) contains theme setup, asset enqueue, header/nav helpers, and shared WooCommerce helpers (`get_order_from_admin_screen_object`, `get_supported_order_statuses`). Domain-specific logic is split into modules under `inc/`:

| File                                   | Contents                                                                                   |
| -------------------------------------- | ------------------------------------------------------------------------------------------ |
| `inc/events.php`                       | Event CPT, meta field registration and getters                                             |
| `inc/event-registrations.php`          | Free event registration CPT and form                                                       |
| `inc/event-registration-privacy.php`    | Privacy Tools export, erasure and anonymization for free event registrations               |
| `inc/event-participants-admin.php`     | `Events > Participants` admin view                                                         |
| `inc/event-participants-messaging.php` | `Events > Messaging` bulk email                                                            |
| `inc/event-roles.php`                  | `event_organizer` role and capabilities                                                    |
| `inc/gallery-albums.php`               | Gallery Album CPT and gallery stack logic                                                  |
| `inc/media-library.php`                | Media library ordering by album                                                            |
| `inc/digital-magazines.php`            | Digital magazine download pages                                                            |
| `inc/share.php`                        | Share buttons (Facebook, X, WhatsApp)                                                      |
| `inc/social-links.php`                 | Social media links in header/footer                                                        |
| `inc/attachment-iptc.php`              | IPTC headline and description sync for attachment images                                   |
| `inc/seo-meta.php`                     | Open Graph and Twitter Card meta tags                                                      |
| `inc/login.php`                        | Login page branding and Finnish translations                                               |
| `inc/newsletter.php`                   | Footer newsletter signup Customizer setting and AcyMailing shortcode rendering             |
| `inc/woocommerce-mollie.php`           | Mollie Finnish translations, RF reference normalization                                    |
| `inc/woocommerce-membership.php`       | Membership products, checkout notice, admin column and metabox                             |
| `inc/woocommerce-tampere-2026.php`     | Tampere 2026 participation fee: product, checkout fields, admin, organizer notifications   |
| `inc/woocommerce-product-sync.php`      | WooCommerce product sync tool for local <-> dev                                           |
| `inc/customizer-contact.php`            | Customizer contact fields for footer and admin email                                       |

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
assets/css/hero.css          # Front page hero section
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

### Gallery Album (`gallery_album`)

- Slug: `/albumit/`
- Registered in: `inc/gallery-albums.php`
- Images are WordPress media attachments with `post_parent = album_post_id`
- Order: by filename (sorted in `inc/media-library.php`)

## WooCommerce integration

WooCommerce-specific logic lives in `inc/woocommerce-*.php` modules, with small shared helpers still in `functions.php`. Key areas:

- **Membership products** — annual and lifetime membership; checkout notice when product is in cart
- **Paid event fees / Tampere 2026** — linked event products, Tampere-specific checkout fields, participant list in admin, CSV export, event-specific organizer email notifications
- **Mollie payments** — Finnish language texts, output buffering on `thankyou` page for bank transfer instructions
- **PhotoSwipe conflict** — WooCommerce registers PhotoSwipe 4 scripts; theme actively dequeues them to avoid conflicts

## Navigation menus

WordPress admin-managed menus: `primary` (main menu), `footer`, `account` (user/account).

## Documentation

`docs/` contains setup and maintenance guides for WooCommerce features. Read the relevant doc before making WooCommerce changes.

`docs/newsletter.md` documents the AcyMailing footer signup setup, target list and newsletter MVP boundaries.

`docs/design-system.md` documents the theme's color tokens, radius/shadow/transition variables, layout, and component conventions. Read it before writing or modifying CSS.
