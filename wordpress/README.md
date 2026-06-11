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

# 5. Candidate-accounts verification (real users/role/saved-jobs table with
#    a capturing mailer — no email sent, no real candidate data)
wp eval-file wp-content/plugins/poolhall-integration/scripts/dev/verify-accounts.php
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
- **Phase 3 — Elementor design system & global shell:** 🟡 nearly done.
  Done: child-theme token layer (`themes/hello-elementor-child/assets/css/shared.css`,
  full §3 variable contract + the complete §6 global class registry —
  layout/stacks/grids, typography, buttons with all states, cards, badges,
  chips, alerts, forms, utilities, reduced-motion rules), kit Site Settings
  script (`scripts/dev/configure-elementor-kit.php`), theme shell script
  (`scripts/dev/create-theme-shell.php` — 8 core pages, menus, header with
  §8 contact strip + desktop audience switch, footer, site-wide conditions),
  and the style-guide page (`/style-guide/`, server-rendered by the plugin
  `[poolhall_style_guide]` shortcode from the live token contract; noindexed
  and excluded from sitemaps). All verified on the local frontend.
  Note: the child theme deliberately owns all `--ph-*` custom properties;
  Elementor kit Global Colors/Fonts stay synced for v3 widgets (doc 10 §3
  fallback clause). Remaining: loop item templates (with Phase 4), mobile
  drawer audience/account state.
- **Phase 4 (part) — jobs user experience:** 🟡 started:
  `scripts/dev/create-jobs-templates.php` builds the §12 loop items
  (`PH Loop - Job Featured Card`, `PH Loop - Job Result Row`), the jobs
  archive page (navy slim hero + one-column Loop Grid, 10/page, numbered
  pagination, query ID `poolhall_jobs_archive` owned by
  `src/Jobs/ArchiveQuery.php`, which also excludes locally-expired jobs
  between crons) and the §14 single-job template (navy hero, content +
  card sidebar; CTA routes to Contact while the application mode is unset
  per hard rules 5/15). Template IDs recorded in `poolhall_template_ids`.
  Remaining: home page + featured carousel, search/filter/sort widgets,
  expired-job state, similar roles, save control.
- **Phase 6 (part) — candidate accounts backend:** 🟡 auth complete:
  `poolhall_candidate` role with minimal caps + admin/author-archive/REST
  lockout (admin-post stays open for the frontend form handlers),
  registration with consent capture and enumeration-safe generic
  responses, hashed single-use 24h verification tokens with 60s/hourly/daily
  resend limits, idempotent saved-jobs table keyed by source identity
  (survives job recreation, live-first listing, clear-expired).
  **Login/logout/recovery (spec §5):** generic-failure login with
  self-clearing account+network rate limits (`LoginRateLimiter`,
  transient-backed `FailedLoginStore`), unverified accounts routed to a
  resend path only after the password proves ownership; single-use hashed
  60-minute reset tokens that revoke all sessions and send a security
  email; allowlisted same-origin return URLs (`ReturnUrlPolicy`).
  **Portal routes (spec §2):** `PortalGuard` noindexes/no-caches all
  `/candidate/*` URLs, redirects signed-out users to login with a return
  URL, unverified candidates to the verification screen, and keeps the
  page tree out of sitemaps; `scripts/dev/create-portal-pages.php` builds
  the six portal pages from the server-rendered `[poolhall_candidate_auth]`
  forms (design-system markup, honeypot + nonce + `poolhall_human_check`
  Turnstile seam, no Elementor Pro needed — the future Candidate Auth
  widget wraps the same renderer). **Save control backend (spec §7, hard
  rule 6):** REST `poolhall/v1/saved-jobs` (toggle idempotently, list,
  clear-expired) enforcing 401 signed-out / 403 unverified / ownership by
  construction, plus a no-JS admin-post fallback that routes signed-out
  saves through login back to the same role. Unit tests (`PasswordPolicy`,
  `TokenPolicy`, `ResendPolicy`, `EmailAddress`, `LoginRateLimiter`,
  `ReturnUrlPolicy`) + `scripts/dev/verify-accounts.php` (56 checks) +
  HTTP-level E2E (login → dashboard → logout, guard redirects,
  open-redirect and honeypot defenses). Remaining: security page (change
  password/email, session list/revocation), portal widgets/dashboard
  modules, alerts, recommendations, history, CV, privacy export/deletion.
- **Phase 8 (part) — JobPosting schema + reviews:** ✅ schema generator +
  eligibility gate + output on single jobs; Places client + cache policy.
- **Phases 4–5, 7, 9, 10:** not started. See `/NEXT-SESSION.md` for how to
  resume in a fresh container.
