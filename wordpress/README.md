# Poolhall Recruitment — WordPress build

The real site replacing both the Wix site and the Vercel prototype
(`project/ui_kits/website`, which stays as the visual reference).

The authoritative build pack is in [`docs/`](docs/) — start with
`docs/00-ONE-SHOT-BUILD-BRIEF.md`. This directory contains the two
version-controlled artifacts the architecture mandates:

| Path | What it is |
|---|---|
| `plugins/poolhall-integration/` | The custom plugin: job CPT, Giig adapter, sync, expiry, JobPosting schema, admin health. All data, security and integration logic. |
| `themes/hello-elementor-child/` | Hello Elementor child theme: shared CSS tokens, focus/overflow rules, no-JS fallbacks. Presentation only. |

WordPress core, Elementor and uploads are **not** committed.

## Local development

Requires PHP 8.2+, Composer, WP-CLI. No MySQL needed — local dev runs on
the official SQLite integration plugin.

```sh
# 1. WordPress core (any current stable) + SQLite drop-in
#    wordpress.org or the GitHub mirror (WordPress/WordPress +
#    WordPress/sqlite-database-integration), then:
cp wp-content/plugins/sqlite-database-integration/db.copy wp-content/db.php
wp config create --dbname=poolhall --dbuser=na --dbpass=na --skip-check
wp core install --url=http://localhost:8080 --title="Poolhall (local)" ...

# 2. Symlink the repo artifacts into wp-content
ln -s <repo>/wordpress/plugins/poolhall-integration  wp-content/plugins/
ln -s <repo>/wordpress/themes/hello-elementor-child  wp-content/themes/
wp plugin activate poolhall-integration
wp theme activate hello-elementor-child   # needs hello-elementor parent

# 3. Plugin dev loop
cd wordpress/plugins/poolhall-integration
composer install
composer test     # PHPUnit unit tests (pure domain logic, no WP needed)
composer lint     # PHPCS, WordPress Coding Standards

# 4. Integration verification (runs the real sync pipeline in WP against
#    the synthetic fixture — no Giig credentials, no real data)
wp eval-file wp-content/plugins/poolhall-integration/scripts/dev/verify-sync.php
```

## Secrets

Giig credentials are read from wp-config constants or environment only
(never the options table, never committed):

```php
define( 'POOLHALL_GIIG_BASE_URL', '...' );
define( 'POOLHALL_GIIG_TOKEN', '...' );
define( 'POOLHALL_GIIG_SECRET', '...' );
// Optional — docs conflict on the header name; Phase 1 locks it:
define( 'POOLHALL_GIIG_SECRET_HEADER', 'Access-Secret-Key' );
```

## Build status (docs/03-BUILD-PLAN.md)

- **Phase 0 — repo & environments:** ✅ scaffolds, tests, lint, local WP loop.
- **Phase 1 — prove Giig contracts:** ⏳ **blocked on real test credentials.**
  The adapter is built against the documented endpoints with a synthetic
  fixture; `GiigNormalizer::KEYS` and the auth header get locked when a live
  response is recorded. Application Mode A/B is undecided until then
  (`poolhall_application_mode` option stays `unset`; first-party apply UI and
  `directApply: true` are hard-gated behind Mode A).
- **Phase 2 — core plugin and sync:** ✅ code + unit tests + integration
  verification (idempotent sync, duplicate prevention, update-in-place,
  unpublish-on-removal, failure preserves jobs, mass-unpublish guard).
- **Phase 3 — Elementor design system & global shell:** 🟡 in progress.
  Done: child-theme token layer (`themes/hello-elementor-child/assets/css/shared.css`,
  full §3 variable contract + fluid type classes), kit Site Settings script
  (`scripts/dev/configure-elementor-kit.php` — system/custom colors,
  typography, 1152px container, 639/899/1199 breakpoints), theme shell
  script (`scripts/dev/create-theme-shell.php` — 8 core pages, Primary +
  Footer menus, Theme Builder header/footer with site-wide conditions,
  mobile nav dropdown). All verified rendering on the local frontend.
  Remaining: v4 Variables/Global Classes in the editor, style-guide page,
  contact strip, audience switch, loop item templates.
- **Phase 8 (part) — JobPosting schema + reviews:** ✅ schema generator +
  eligibility gate + output on single jobs; Places client + cache policy.
- **Phases 4–7, 9, 10:** not started. See `/NEXT-SESSION.md` for how to
  resume in a fresh container.
