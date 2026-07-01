# Starting a new Claude Code session — step by step

Follow this whenever you start a fresh session on this repo (the container
is wiped between sessions; everything below gets you back to exactly where
the last session ended).

## Where things stand (end of 12 June)

**The latest code is on branch `claude/gifted-ptolemy-p6lg3t`** — a fresh
session may boot on a stale checkout; fetch and reset onto that branch
first.

- **Staging is live and current with the repo:**
  `http://lightslategrey-hare-335761.hostingersite.com/` (Hostinger
  account `u232776807`, order 1008376137; admin user `poolhall-admin`,
  password held by Ryan — ask him to paste it if you need to drive
  staging wp-admin; never commit it). On 12 June the API deploy worked
  end to end (`srv1350-files.hstgr.io` + staging hostname are
  allowlisted): dist zips extracted and TUS-uploaded file-by-file to
  `wp-content/plugins/<slug>-<rand8>/`, then the
  plugins/themes `/deploy` endpoints (the exact flow the
  `hostinger-api-mcp` package uses — headers `X-Auth` + `X-Auth-Rest`,
  pre-create POST expecting 201, PATCH with `Tus-Resumable: 1.0.0`).
  Staging wp-admin was driven over HTTP with curl (login → nonce →
  Poolhall Jobs → Site setup) — all 7 steps green.
- **Built and verified locally AND on staging** (see
  `wordpress/README.md` status table): Phase 6 auth + security page
  (78/78 checks, 125 unit tests), Phase 3 design system + theme shell,
  Phase 4 jobs templates + Team page, **Home page with image hero,
  server-rendered job search (`[poolhall_job_search]`, GET to `/jobs/`,
  q/location/sector applied server-side, filtered results
  noindex,follow), featured jobs Loop Carousel (`poolhall_featured_jobs`,
  `is_featured` first), sectors/steps/stats/employer-CTA** — and all
  five **marketing pages (employers, sectors, services, contact,
  join-our-team) with the real enquiry backend**
  (`[poolhall_enquiry_form]`: honeypot+nonce+Turnstile seam, consent,
  rate-limited, mails `poolhall_enquiry_inbox` option → admin email
  fallback). Staging shows empty featured carousel/jobs archive until
  Phase 1 syncs real jobs — that is the honest expected state.
- **Deploys:** `bash wordpress/scripts/build-deploy-zips.sh` →
  `wordpress/dist/`, then the API flow above (a working deploy script
  from this session is the pattern: fetch upload-urls with
  `{username,domain}`, upload every file, POST deploy with
  `{slug, plugin_path|theme_path}`). The Hostinger MCP server tools also
  loaded this session (`mcp__hostinger-mcp__hosting_deployWordpressPlugin`)
  but direct REST worked fine.
- Local quirks already solved in the setup script: Hello Elementor and
  free Elementor install from `downloads.wordpress.org` (GitHub source
  tree fatals in wp-admin; GitHub API rate-limits without
  `GITHUB_TOKEN`).
- **Not allowlisted on 12 June:** `www.poolhallrecruitment.co.uk` (the
  live Wix site) — marketing-page copy is from the approved prototype;
  a Wix copy reconciliation pass needs that host allowlisted.

## Before you start the session (one-time setup)

1. **Environment variables** (claude.ai/code → environment settings):
   - `HOSTINGER_API_TOKEN` — ✅ landed, verified working on 11 June
     (second session).
   - Optional: `GITHUB_TOKEN` = a GitHub token (avoids API rate limits in
     the setup script; without it Elementor falls back to
     downloads.wordpress.org, which also works — confirmed this session).
   - **Gotcha (verified twice):** allowlist and env-var changes only apply
     when a container boots — a running session never sees them. Edit
     first, then start the session.
2. **Network policy** (same settings screen). Confirm these domains are
   allowed — you added the first five on 10 June:
   - `developers.hostinger.com` (Hostinger API; `api.hostinger.com` is NOT
     it). The proxy's block response is an HTTP 403 with body
     `Host not in allowlist`; a real Hostinger reply to a tokenless call
     is a 401 `Unauthenticated` JSON body.
   - **`srv1350-files.hstgr.io` (or `*.hstgr.io`)** — the Hostinger
     plugin/theme deploy uploads files there (TUS) before the deploy
     call; blocked all of 11 June, which is why builds went to Ryan as
     zips. Ryan said he'd add it before the next boot. The host is
     per-server: re-check with `POST /api/hosting/v1/files/upload-urls`
     if the site ever moves.
   - **`lightslategrey-hare-335761.hostingersite.com` (or
     `*.hostingersite.com`)** — lets the session load staging pages and
     drive staging wp-admin over HTTP (verification without Ryan
     relaying screenshots). Also promised for the next boot.
   - `wordpress.org` and `downloads.wordpress.org` (core/plugins; Elementor
     free comes from here when the GitHub API is rate-limited)
   - `api.giighire.com` (Giig — needed for Phase 1)
   - `places.googleapis.com` (Google reviews)
   - `my.elementor.com` (Elementor Pro licence)
3. **Upload the Elementor Pro zip** into the chat (licensed, deliberately
   not in the repo). Without it the local theme-shell and jobs-template
   scripts refuse to run (free Elementor from the setup script is enough
   for the kit/Site Settings script and all plugin work). **Forgotten on
   11 June (second session) — that's why Phase 3/4 visual work stayed
   paused.**

## Starting the session

4. Start a new Claude Code session on the **Flashryan/poolhall** repo.
5. Paste this as your first message:

   > Fetch origin and reset onto `origin/claude/gifted-ptolemy-p6lg3t`
   > (the latest work — your checkout may be stale). Read
   > `wordpress/README.md`, `wordpress/docs/CLAUDE.md` and
   > `NEXT-SESSION.md`. Then:
   > 1. Run `bash wordpress/scripts/setup-local-wp.sh` to rebuild the
   >    local WordPress dev environment, unzip the Elementor Pro upload
   >    into the local WP plugins folder and activate it, then run every
   >    Site setup script (kit/theme-shell/jobs/team/home/marketing) and
   >    verify the frontend renders.
   > 2. Continue the build from the status table in
   >    `wordpress/README.md` — next up: jobs filter/sort widgets
   >    (type, work mode, salary, sort, chips, mobile filter drawer),
   >    expired-job state and similar roles, then the save-control
   >    frontend. If I paste the Giig token + secret, do Phase 1 first
   >    (prove the contracts, lock GiigNormalizer::KEYS and the auth
   >    header, record fixtures).
   > 3. When done, rebuild `wordpress/dist/`, deploy to staging via the
   >    Hostinger API (flow in NEXT-SESSION.md, verified 12 June), drive
   >    staging wp-admin's Poolhall Jobs → Site setup over HTTP (I'll
   >    paste the admin password when you ask) and verify staging
   >    matches local.

## Hostinger staging runbook — steps 1–3 DONE on 11 June, remainder below

Ryan's decision on 11 June: **Claude provisions the staging site; Ryan
installs Elementor Pro and Novamira on it manually** (their zips/licences
never go through the API or the repo).

Done (11 June, second session): inventory recorded (no VPS → managed
path), free subdomain generated, website created on order 1008376137,
WordPress installed and `is_valid` —
`http://lightslategrey-hare-335761.hostingersite.com/`. Note the MCP
server tools may not load in the session; everything above worked by
calling the REST API directly with `HOSTINGER_API_TOKEN` (base
`https://developers.hostinger.com`, endpoints mirrored from the
`hostinger-api-mcp` npm package — the composite
`hosting_deployWordpressPlugin`/`Theme` tools are: POST
`/api/hosting/v1/files/upload-urls`, TUS-upload each file to the returned
`url` + `/wordpress-plugins/<slug>/…?override=true` with the returned
keys, then POST
`/api/hosting/v1/accounts/{username}/websites/{domain}/wordpress/plugins/deploy`).

DONE on 12 June: deploy steps 1, 2 and 4 — the API deploy ran end to end
(all plugin + theme files TUS-uploaded, both `/deploy` calls accepted),
the Site-setup runner (now 7 steps: + home page, + marketing pages) was
driven over HTTP and came back all green, and every page was verified on
the staging frontend. Elementor Pro was already active on staging (Ryan
installed it 11 June).

Remaining:

1. Run `verify-sync.php` / `verify-accounts.php` against the staging
   install (needs wp-cli or a temporary admin runner — they are dev
   scripts, not part of Site setup).
2. Never purchase anything (VPS, domains) through the API without Ryan
   confirming in chat first. (Nothing was purchased on 11 or 12 June.)

## What the next session can do

- **Phase 1 — prove the Giig API:** 🟡 mostly done (2026-07-01). Constants
  are on staging; the live contract was recorded and `GiigNormalizer::KEYS`
  + the `Access-Secret-Key` header + the `{status,body}` envelope are locked
  against `tests/fixtures/giig-getjobs.live.json`. All 16 live jobs
  normalize. **Remaining:** get Giig's enum legend for the five integer
  fields (`JobType`, `Experience`, `EducationRequirement`, `SalaryPeriod`,
  `CandidateRemote`) — currently left unmapped, not guessed — then finish
  work mode / job type / experience / education / salary period+currency in
  `GiigNormalizer` and run a real sync so the home featured carousel, jobs
  archive, sector taxonomy/search select and live-roles trust item come
  alive. (Sanity check: the live `Industry` field sometimes carries a raw
  id like `"109"` instead of a label — confirm sector mapping with Matt.)
- **Continue Phase 4** (needs the Elementor Pro zip uploaded) — jobs
  filter/sort widgets (type, work mode, salary, sort, applied chips,
  mobile filter drawer), expired-job state, similar roles, save control
  frontend; mobile drawer audience/account state (Phase 3 leftover).
- **Content passes** — reconcile marketing copy against the live Wix
  site (needs `www.poolhallrecruitment.co.uk` allowlisted), Better Job
  Adverts page (price needs Ryan's confirmation), blog migration,
  legal/compliance pages.
- **Continue Phase 6** — dashboard modules, alerts, profile,
  recommendations, application history; CV + privacy export/deletion
  (with reauthentication, hard rule 17) once Mode A is decided.

## If something looks broken in a new session

- `bash wordpress/scripts/setup-local-wp.sh` is idempotent — re-run it.
- Serve locally with `(cd ~/wp-local && php -S 127.0.0.1:8080 router.php)`
  — the router file is required for pretty permalinks under `php -S`.
- Tests: `cd wordpress/plugins/poolhall-integration && composer test`
  (run Composer commands with `COMPOSER_ALLOW_SUPERUSER=1` in these
  root containers, or `composer lint` reports missing sniffs).
- Integration checks: `wp eval-file wp-content/plugins/poolhall-integration/scripts/dev/verify-sync.php`
  and `.../verify-accounts.php`.
- All build decisions live in `wordpress/docs/` — the build brief
  (`00-ONE-SHOT-BUILD-BRIEF.md`) wins when documents disagree.

## Credentials checklist (never commit any of these)

| Credential | Where it lives | Status |
|---|---|---|
| GitHub PAT | you paste it when pushing | active |
| Hostinger API token | `HOSTINGER_API_TOKEN` env var | ✅ active, API deploy verified 12 June |
| Staging wp-admin (`poolhall-admin`) | Ryan holds it; paste into chat when the session asks (needed to drive staging Site setup over HTTP) | active |
| Giig API token + secret | `POOLHALL_GIIG_*` wp-config constants on staging | ✅ added to staging wp-config (base URL, token, secret; secret-header left at the `Access-Secret-Key` default) |
| Google Places API key | wp-config constants on staging | not yet created |
| Elementor Pro licence | elementor.com account | zip uploaded per session (forgotten 11 June); installed manually on staging by Ryan |
| Novamira (page builder) | Ryan installs manually on staging/local only | never production (hard rule 13) |
| Novamira MCP server (`NOVAMIRA_WP_APP_PASSWORD`) | web environment env var; referenced by `.mcp.json` | set it in the environment settings so the `novamira-lightslategrey-h` MCP server can authenticate — the password is never committed |

## MCP servers (Claude Code)

Both `.mcp.json` servers (`hostinger-mcp`, `novamira-lightslategrey-h`) are
pre-approved in `.claude/settings.json` (`enabledMcpjsonServers`). This
matters on the **web/remote environment**: without pre-approval, project
`.mcp.json` servers sit in "Pending approval" (there is no interactive
trust prompt on the web) and silently never connect — restarting the
session does not help. To connect Novamira:

1. Set `NOVAMIRA_WP_APP_PASSWORD` in the environment settings (its value is
   the `poolhall-admin` application password; verified working against
   `…/wp-json/mcp/novamira` — server reports `Novamira v1.0.0`).
2. Confirm `*.hostingersite.com` is allowlisted in the network policy.
3. Reboot the container (env-var/config changes only take effect on boot).

The server is the Automattic WordPress remote MCP proxy
(`@automattic/mcp-wordpress-remote`, a stdio server launched via `npx`).
In **Claude Desktop** the usual failure is that the GUI app can't find
`npx` on its PATH — use the absolute path from `which npx` as `command`.
