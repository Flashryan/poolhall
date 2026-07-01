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
- **Phase 1 — prove Giig contracts:** 🟢 **live on staging (2026-07-01);
  enum legend pending.** Verified end to end against the live API
  (`https://giigapi.com`, company 166598): the `Access-Secret-Key` auth header
  works, responses arrive wrapped in a `{status,body}` transport envelope
  (now unwrapped in `GiigClient::unwrap_envelope`), and the real payload uses
  PascalCase fields — so `GiigNormalizer::KEYS` is locked to the recorded
  response (`tests/fixtures/giig-getjobs.live.json`, sanitised). All 16 live
  jobs normalize (title, salary range from `SalaryFrom`/`SalaryTo`, location,
  sector, company, date). Giig also serialises empty fields as the literal
  strings `"null"`/`"undefined"`, now treated as absent. The fixed adapter is
  **deployed to staging and a real sync has run** — 16 fetched/created, 8
  published (the rest aged out by `ExpiryPolicy`); the jobs archive, home
  featured carousel and single-job page render real roles.
  **Update (v2 handoff, same day):** the v2 directive's §2.2 legend resolved
  `JobType` (0=Perm 1=Temp), `Currency` (0=£ 1=$ 2=€) and `SalaryPeriod`
  (0=year 1=month 2=week 3=day 4=hour) — mapped, tested and live on staging
  (salaries "£50,000–£55,000 / year", `baseSalary` JSON-LD, job-type
  taxonomy). Expiry flipped: source presence governs visibility (16/16 open
  roles published). **Still open:** (1) `CandidateRemote` (work mode),
  `Experience`, `EducationRequirement` and `DisplaySalary` have no legend —
  unmapped, not guessed; (2) live `Industry` sometimes carries a raw id
  (`"109"`) — confirm sector mapping with Matt. Application Mode A/B still
  undecided (`poolhall_application_mode` stays `unset`; first-party apply UI
  and `directApply: true` hard-gated behind Mode A).
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
  fallback clause). All of these setup scripts (plus the portal pages) can
  also be run from wp-admin — Poolhall Jobs → **Site setup**, a
  nonce-protected runner that skips steps whose requirements are missing —
  so managed hosts without wp-cli/SSH (Hostinger staging) configure
  through the same idempotent code path. Remaining: loop item templates
  (with Phase 4), mobile drawer audience/account state.
- **Phase 4 (part) — jobs user experience:** 🟡 started:
  `scripts/dev/create-jobs-templates.php` builds the §12 loop items
  (`PH Loop - Job Featured Card`, `PH Loop - Job Result Row`), the jobs
  archive page (navy slim hero + one-column Loop Grid, 10/page, numbered
  pagination, query ID `poolhall_jobs_archive` owned by
  `src/Jobs/ArchiveQuery.php`, which also excludes locally-expired jobs
  between crons) and the §14 single-job template (navy hero, content +
  card sidebar; CTA routes to Contact while the application mode is unset
  per hard rules 5/15). Template IDs recorded in `poolhall_template_ids`.
  **Team page (doc 10 §18/§9):** `scripts/dev/create-team-page.php` —
  compact image hero, story split with `ph-stat` figures, three
  `ph-card--team` profiles (4:5 portraits via `ph-team-portrait`), navy
  CTA band; real client photography ships in the plugin
  (`assets/img/content/`, extracted from the design handoff) and
  sideloads idempotently into the media library. No placeholder `#`
  links (hard rule 7) — the prototype's dummy social icons are omitted
  until real profile URLs exist. Part of the admin Site-setup runner.
  **Home page (doc 10 §25, §9, §11, §12):** `scripts/dev/create-home-page.php` —
  image hero with the server-rendered `[poolhall_job_search]` panel
  (`src/Jobs/SearchForm.php`; plain GET to `/jobs/`, works without JS,
  values retained on the results page) and trust row
  (`[poolhall_live_roles]` renders nothing while the job store is empty),
  featured jobs Loop Carousel (query `poolhall_featured_jobs` owned by
  `src/Jobs/FeaturedQuery.php` — unexpired, manually featured via the
  `is_featured` meta flag then newest, max six, no autoplay), six static
  sector cards, the three-step process, stat strip and employer CTA
  split; sets the site front page and runs from the Site-setup runner.
  The reviews carousel is deliberately absent until Phase 8 supplies real
  Google reviews. `ArchiveQuery` now applies `q`/`location`/`sector`
  server-side (normalized by the unit-tested `SearchRequest`), marks
  filtered results `noindex,follow`, and the jobs archive carries the
  wide variant of the same search panel above the Loop Grid.
  **Marketing pages (spec 01 §7–§10):** `scripts/dev/create-marketing-pages.php`
  builds Employers (services cards, ethics proof split, sectors strip,
  Better Job Adverts callout, hiring enquiry), Sectors (three core
  capability splits with discipline chips + candidate/employer CTAs),
  Services (three service cards + prominent Better Job Adverts band),
  Contact (details + general form) and Join Our Team (both propositions,
  with the unconfirmed salary/commission/ownership figures deliberately
  absent — migration doc §4). The hiring/contact forms are the
  server-rendered `[poolhall_enquiry_form]` (`src/Enquiries/` —
  unit-tested `EnquiryRequest` validation, honeypot + nonce +
  `poolhall_human_check` Turnstile seam, required privacy consent,
  network rate limiting, notification to the `poolhall_enquiry_inbox`
  option (default admin email), redacted log events; a failed send shows
  the visitor an honest phone/email fallback, never a silent drop). All
  in the Site-setup runner.
  **Directive pass (12 June, from the design audit):** footer rebuilt to
  the prototype (4 columns, TEAM + BNI accreditation chips extracted from
  the prototype assets, company no./VAT/address bar; '#' social links
  omitted until real URLs exist); serif weights corrected (display 500,
  card titles 600); Better Job Adverts page built (six proposition
  cards, three steps, "Pricing confirmed when you enquire" — never a
  figure); employers BJA callout is the gold band; job cards carry the
  §4.1 anatomy ([poolhall_job_card_flair]/[poolhall_job_card_meta]:
  gold Featured pill + orange top border, icon meta row, clamped real
  summary, salary + View job footer); [poolhall_reviews] renders the
  Places snapshot as a scroll-snap carousel with a staging-only demo
  fallback; gated Health-page action seeds prototype sample jobs/quotes
  (source=demo, refuses on the production domain). FeaturedQuery now
  resolves IDs deterministically (featured first, then newest) with a
  re-entrancy guard.
  Remaining: jobs archive filter/sort UX (sidebar, chips, pagination
  styling, empty state, mobile filter drawer — directive §3.2), single
  job enrichment + expired state + similar roles + save control
  (§3.3), apply page (blocked: application Mode A/B undecided, hard
  rules 5/15), carousel arrow buttons + drawer JS, portal auth-card
  styling pass (§3.9), Wix copy reconciliation, blog migration.
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
  saves through login back to the same role.
  **Security page (spec §5, hard rule 17):** `/candidate/security/`
  (`SecurityService`/`SecurityEndpoints`, server-rendered like the other
  auth flows) — change password after current-password reauthentication
  (shares login's account+network rate limits, revokes every other
  session, kills any outstanding reset link, mails a security notice);
  change email via a single-use hashed 24h token mailed to the *new*
  address with enumeration-safe generic responses and a notice to the old
  address on completion (core's own change email suppressed — the Mailer
  seam owns email); session list with approximate device/browser
  (`SessionDescriber`) and a current-session marker, revoking one other
  session by verifier or all others (`SessionRegistry`); `PortalGuard`
  now preserves query strings through the login round trip so emailed
  confirmation links survive sign-in. Unit tests (`PasswordPolicy`,
  `TokenPolicy`, `ResendPolicy`, `EmailAddress`, `LoginRateLimiter`,
  `ReturnUrlPolicy`, `SessionDescriber`) +
  `scripts/dev/verify-accounts.php` (78 checks) + HTTP-level E2E (login →
  dashboard → logout, guard redirects, open-redirect and honeypot
  defenses, security-page reauth + revoke-others). Remaining: portal
  widgets/dashboard modules, alerts, recommendations, history, CV,
  privacy export/deletion (reauth for CV/account deletion lands with
  those features).
- **Phase 8 (part) — JobPosting schema + reviews:** ✅ schema generator +
  eligibility gate + output on single jobs; Places client + cache policy.
- **Phases 4–5, 7, 9, 10:** not started. See `/NEXT-SESSION.md` for how to
  resume in a fresh container.
