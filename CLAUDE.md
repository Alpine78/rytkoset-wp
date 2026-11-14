# Project guide

@AGENTS.md

## Project context

Production WordPress site for Rytkösten sukuseura ry at `rytkoset.net`.
The Finnish-only classic theme is `wp-content/themes/rytkoset-theme/`.
WordPress core, installed plugins and uploads are not versioned. Integrations:
WooCommerce, AcyMailing, bbPress and optional legacy ACF fields. Project rules
and documentation policy: `AGENTS.md`; feature details: the linked `docs/` guides.

## Development environment

- WordPress 7 / PHP 8.3 (Apache), MariaDB 10.11; see `Dockerfile` and `docker-compose.yml`.
- Start: `docker compose up -d`; site: `http://localhost:8000`; stop: `docker compose down`.
- Services: `wordpress` (`rytkoset-wp`), `db` (`rytkoset-db`), on-demand `wpcli` (`cli` profile).
- Host `wp-content/` is mounted directly; edits need no restart. Core persists in `wp_core`, database in `db_data`.
- WP-CLI: `docker compose run --rm wpcli wp theme activate rytkoset-theme`.
- Windows: Docker Engine inside WSL2 is supported; keep the repo in the Linux filesystem. Bootstrap: `docs/local-dev-wsl.md`.
- No asset build step, Node tooling or framework dependencies; PhotoSwipe 5 is vendored.
- Theme-only FTPS deploy: `dev` automatically on theme changes; `main` has no automatic deploy. Production uses the manual `deploy-production.yml` workflow.
- Standalone `wp-content/maintenance.php` and `mu-plugins/automation-by-klik.php` require separate delivery; see `docs/local-dev-wsl.md`.

## Lint, CI and tests

`.github/workflows/php-ci.yml` runs on every PR and `main` push with PHP 8.3.
All three checks are hard gates:

1. PHP syntax: `find wp-content/themes/rytkoset-theme -name '*.php' -print0 | xargs -0 -n1 php -l`.
2. WordPress Coding Standards: `composer run lint` (PHP_CodeSniffer + WPCS).
3. PHPUnit: `composer run test` (PHPUnit 11; `phpunit.xml.dist`, `tests/`).

Run `composer install` once for development dependencies. `composer run lint:fix`
runs phpcbf; review its changes. `phpcs.xml.dist` uses WordPress-Core and
WordPress.Security for the theme, maintenance page and MU-plugins, excluding
vendored assets. Text domain: `rytkoset-theme`; prefixes: `rytkoset_theme` and
`rytkoset`. Exceptions cover bbPress/WooCommerce public hooks and CPT filenames.
All scoped files must pass without errors or warnings. Keep ignores minimal
and explain the technical reason. For legacy
8-space indentation, phpcbf may need `--tab-width=8`; verify with normal lint.

Unit tests do not boot WordPress or a database. `tests/bootstrap.php` provides
minimal WordPress/WooCommerce stubs, in-memory stores, controllable time and
hooks. Extend `Rytkoset_Theme_Test_Case`, which resets stub state in `setUp()`;
add only the stubs a meaningful test needs. Isolate deterministic logic in small
helpers. Use browser/manual validation for rendering, real WooCommerce flows,
permissions and persistence; stub tests alone do not verify those integrations.
Without host PHP, run tests with:
`docker compose run --rm -v "$PWD":/app -w /app --entrypoint sh wordpress -lc 'vendor/bin/phpunit'`.
Validate changed user flows on dev, including mobile, dark theme and visible focus.

## Directory and module map

Paths below are relative to the theme unless otherwise stated. Read the linked
guide before changing its area. `functions.php` loads `inc/` modules and owns
setup, asset enqueue, navigation/cart and shared WooCommerce/bbPress helpers.

- `front-page.php`, `inc/home-highlight.php`, blog/page templates: public content and current highlight; `docs/design-system.md`.
- `header.php`, `footer.php`, `template-parts/pre-footer-*.php`: menus and newsletter bands; `docs/menu-structure.md`, `docs/newsletter.md`.
- `assets/css/`, `assets/js/main.js`, `inc/icons.php`, `inc/share.php`, `inc/social-links.php`: tokens, interactions, SVGs and sharing; `docs/design-system.md`.
- `inc/admin-{appearance,dashboard,menu}.php`, `assets/css/admin.css`, `assets/css/editor-style.css`: admin presentation and role-aware navigation; `docs/admin-ulkoasu.md`.
- `inc/events.php`, `inc/event-roles.php`, event templates: `rytkoset_event` CPT (`/tapahtumat/`), metadata and organizer role; `docs/events.md`.
- `inc/event-registrations.php`, `inc/event-registration-summary.php`: `event_registration` CPT, free form and shared counts; `docs/events.md`.
- `inc/event-registration-{privacy,anonymization}.php`: Privacy Tools and scheduled retention; `docs/events.md`, `docs/tietosuoja.md`.
- `inc/event-participants-admin.php`: combined free/paid participant rows and CSV; `docs/event-participants-admin.md`.
- `inc/event-participants-messaging.php`, `assets/js/event-messaging-admin.js`: queued event mail; `docs/event-participants-messaging.md`.
- `inc/event-feedback.php`, `assets/{css,js}/event-feedback.*`: anonymous `event_feedback` CPT and `/palaute/{event_id}/`; `docs/event-feedback.md`.
- `inc/gallery-albums.php`, `inc/media-{library,usage}.php`, album templates: `gallery_album` CPT (`/albumit/`), sorting and delete protection; `docs/media-library-ordering.md`.
- `inc/attachment-iptc.php`, `inc/youtube-privacy.php`, `assets/js/{album-filter,photoswipe-init}.js`, `assets/vendor/photoswipe/`: media metadata, embeds and lightbox; `docs/media-saavutettavuus.md`, `docs/media-library-ordering.md`.
- `inc/digital-magazines.php`, `inc/digital-magazines-admin.php`, `inc/digital-magazine-access.php`: hierarchical `digital_magazine` CPT, gates and manual grants; `docs/digital-magazines.md`.
- `inc/user-membership.php`, `inc/user-membership-activation.php`, `inc/user-memberships-admin.php`, `inc/user-email-verification.php`: membership, family links, activation and verified email; `docs/jasenyys.md`.
- `inc/members-only-pages.php`: page access flag and leak guards; `docs/jasenille-rajatut-sivut.md`.
- `inc/newsletter.php`, `inc/member-newsletter.php`, `inc/email.php`: AcyMailing and mail sender; `docs/newsletter.md`.
- `inc/woocommerce-membership.php`, `assets/js/membership-checkout-*.js`: membership products and checkout rows; `docs/woocommerce-membership-products.md`, `docs/jasenyys.md`.
- `inc/woocommerce-event-registration.php`, `inc/woocommerce-event-privacy.php`, `assets/js/event-checkout-participants.js`: paid participants and privacy; `docs/woocommerce-event-registration.md`.
- `inc/woocommerce-bus-transport.php`: legacy purchase guard; `docs/woocommerce-event-registration.md`.
- `inc/woocommerce-digital-magazine.php`, `inc/woocommerce-member-coupon.php`: magazine purchases and member coupons; `docs/digital-magazines.md`, `docs/woocommerce-jasenalennus.md`.
- `inc/woocommerce-my-account.php`, `woocommerce/myaccount/`, `assets/css/account.css`: account views and self-service; `docs/woocommerce-setup.md`, `docs/jasenyys.md`, `docs/newsletter.md`.
- `inc/woocommerce-{checkout-session,empty-cart,shop-categories,translations}.php`, `inc/coming-soon.php`, `coming-soon.php`: cart/session and shop presentation; `docs/woocommerce-setup.md`, `docs/woocommerce-event-registration.md`.
- `inc/woocommerce-payment-retry.php`, `inc/woocommerce-cancellation.php`, `inc/woocommerce-withdrawal-information.php`: retry, cancellation and durable instructions; `docs/woocommerce-peruutus.md`, `docs/woocommerce-mollie-payments.md`.
- `inc/woocommerce-mollie.php`: dormant payment compatibility; `docs/woocommerce-paytrail.md`, `docs/woocommerce-mollie-payments.md`.
- `inc/woocommerce-product-sync.php`: allowlisted product export/import; `docs/woocommerce-product-sync.md`.
- `inc/seo-meta.php`, `inc/structured-data.php`, `inc/woocommerce-structured-data.php`: metadata and schema; `docs/jasenille-rajatut-sivut.md`, `docs/events.md`, `docs/woocommerce-physical-products.md`.
- `inc/chat.php`, `assets/{css,js}/chat.*`: public-source chat proxy/widget and runtime configuration; `docs/chat.md`.
- `inc/security.php`, `inc/login.php`, `assets/css/login.css`: hardening and login; `docs/tietoturva.md`, `docs/design-system.md`.
- `inc/legacy-redirects.php`: bounded legacy URL map; `docs/menu-structure.md`.
- `inc/customizer-contact.php`, repo `wp-content/maintenance.php`, `wp-content/mu-plugins/`: contact settings and operations; `docs/local-dev-wsl.md`.
- Repo `docs/tietosuoja.md`, `docs/maksu-ja-toimitusehdot.md`, `docs/rekisteriseloste.md`: CMS publishing sources; `docs/tietosuoja-kasittelytoimet.md` is internal only.
- Repo `newsletters/660-pilot/`: standalone HTML email draft outside theme deployment; `docs/newsletter.md`.

## Cross-cutting invariants

- Benefit-granting email lookups must use `rytkoset_theme_get_verified_user_by_email()`; account creation alone proves no email ownership.
- Membership is user meta, not a role; never shorten a valid entitlement or overwrite lifetime membership with a family-sharing term.
- Mutate family rows through `rytkoset_theme_update_family_members()` to keep forward/reverse links and revoked benefits consistent.
- Shared access gates must also protect REST, feeds, excerpts, SEO and caches; chat never receives members-only content, regardless of viewer.
- Check capabilities and nonces on writes, sanitize input, escape output; public nonces alone do not authorize privileged actions.
- Preserve order-item participant/question/collection snapshots and existing checkout field IDs; product edits must not rewrite old purchases.
- Organizer notifications omit health data and free-text notes; event mail/feedback share the rolling 18 mail attempts per 60 minutes queue.
- Anonymous feedback must contain no participant identity; server-side one-time claims remain authoritative without JavaScript.
- Use WooCommerce endpoint keys and URL helpers, not hard-coded localized account paths.
- Preserve CSS dependency order and `rytkoset_theme_get_asset_version()` cache busting; dequeue WooCommerce PhotoSwipe 4 when using vendored v5.
- Prefix theme functions with `rytkoset_theme_` and retain `function_exists` guards; code comments/PHPDoc are English, site strings Finnish.
