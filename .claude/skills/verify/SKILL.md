---
name: verify
description: Runtime verification recipe for the rytkoset-wp local Docker WordPress. Use when verifying theme changes end-to-end against the running site instead of unit tests.
---

# Verifying theme changes in the local Docker WordPress

Containers `rytkoset-wp` (http://localhost:8000) and `rytkoset-db` are usually
already running (`docker compose up -d` if not). The active theme is
`rytkoset-theme`; `wp-content/` is bind-mounted, so PHP edits apply
immediately without restarts.

## Handle: WP-CLI service

The on-demand `wpcli` Compose service is the main handle. It loads full
WordPress including the theme's `inc/` modules, so all `rytkoset_theme_*`
functions are callable:

```bash
docker compose run --rm wpcli wp theme list --status=active --field=name
docker compose run --rm wpcli wp eval '<php>'      # arrange/observe state
docker compose run --rm wpcli wp user delete <id> --yes   # real wp_delete_user() surface
```

Compose prints volume/orphan warnings to stderr; append `2>/dev/null | tail -N`
to keep output clean.

## Patterns that worked

- Arrange state with `wp eval` calling the theme's own helpers (e.g.
  `rytkoset_theme_update_family_members()`), then drive the real surface
  (`wp user delete`, `wp user create` for `user_register` hooks) and observe
  via `get_user_meta` / helper getters in another `wp eval`.
- Prefix test users/emails with a ticket tag (e.g. `v544_`) and clean up with
  `wp user delete <ids> --yes`; confirm with `wp user list --search='v544*'`.
- Frontend/browser flows: use the `playwright-cli` skill against
  http://localhost:8000 (see also `docs/local-dev-wsl.md`).

## Gotchas

- PHPUnit/phpcs also need Docker (no local PHP):
  `docker compose run --rm -v "$PWD":/app -w /app --entrypoint sh wordpress -lc 'vendor/bin/phpunit'`
  but these are CI gates, not runtime verification.
- `wp eval` runs as no user (`get_current_user_id()` = 0); pass explicit user
  IDs to capability-free helpers, or `wp_set_current_user()` first.
