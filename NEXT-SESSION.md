# Starting a new Claude Code session — step by step

Follow this whenever you start a fresh session on this repo (the container
is wiped between sessions; everything below gets you back to exactly where
the last session ended).

## Where things stand (end of 11 June)

**The latest code is on branch `claude/quirky-darwin-vj4vbn`** (tip
`8f6dde9`) — a fresh session may boot on a stale checkout; fetch and reset
onto that branch first.

- **Staging is live with WordPress + Elementor Pro:**
  `http://lightslategrey-hare-335761.hostingersite.com/` (Hostinger
  account `u232776807`, order 1008376137; admin user `poolhall-admin`,
  password held by Ryan — ask him to paste it if you need to drive
  staging wp-admin; never commit it). Ryan installed Elementor Pro
  manually and uploaded an earlier build of the plugin/child theme by
  hand. **Staging may be one build behind the repo** — redeploy
  `wordpress/dist/*` (rebuild first) once the network allowlist includes
  the Hostinger hosts, then re-run Site setup.
- **Built and verified locally** (see `wordpress/README.md` status table
  for detail): Phase 6 auth + security page (78/78 integration checks,
  110 unit tests), Phase 3 design system + theme shell, Phase 4 jobs
  templates, **Meet the Team page with real client photography bundled
  in the plugin** (`scripts/dev/create-team-page.php`), and the
  **wp-admin Site setup runner** (Poolhall Jobs → Site setup) that runs
  every setup script with requirement-aware skips — so staging needs no
  SSH/wp-cli (the Hostinger API has none).
- **Deploys:** `bash wordpress/scripts/build-deploy-zips.sh` →
  `wordpress/dist/` (plugin zip includes `assets/img/content/`
  photography). API deploy path: POST
  `/api/hosting/v1/files/upload-urls`, TUS-upload files to the returned
  per-server host (`srv1350-files.hstgr.io`), then POST
  `.../websites/{domain}/wordpress/plugins/deploy` — blocked until that
  host is allowlisted (Ryan said he'd add it before the next boot).
  With the staging hostname also allowlisted you can log into staging
  wp-admin over HTTP (credentials from Ryan) and trigger Site setup +
  verify pages yourself — same curl flow as the local E2E.
- Local quirks already solved in the setup script: Hello Elementor and
  free Elementor install from `downloads.wordpress.org` (GitHub source
  tree fatals in wp-admin; GitHub API rate-limits without
  `GITHUB_TOKEN`).

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

   > Fetch origin and reset onto `origin/claude/quirky-darwin-vj4vbn`
   > (the latest work — your checkout may be stale). Read
   > `wordpress/README.md`, `wordpress/docs/CLAUDE.md` and
   > `NEXT-SESSION.md`. Then:
   > 1. Run `bash wordpress/scripts/setup-local-wp.sh` to rebuild the
   >    local WordPress dev environment, unzip the Elementor Pro upload
   >    into the local WP plugins folder and activate it, then run the
   >    Site setup steps (`wp eval-file` the kit/theme-shell/jobs/team
   >    scripts) and verify the frontend renders.
   > 2. Rebuild `wordpress/dist/` and deploy the plugin + child theme to
   >    staging via the Hostinger API (the files host is allowlisted
   >    now), then drive staging wp-admin's Poolhall Jobs → Site setup
   >    over HTTP (I'll paste the admin password when you ask) and
   >    verify the staging frontend matches local.
   > 3. Continue the build from the status table in
   >    `wordpress/README.md` — next up: home page + featured carousel,
   >    then the remaining marketing pages (employers, sectors,
   >    services, contact, join-our-team).

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

Remaining:

1. **Deploy plugin + child theme to staging** once `srv1350-files.hstgr.io`
   is allowlisted (build `wordpress/dist/` zips first), or have Ryan
   upload the two zips through staging wp-admin → Plugins/Themes → Add.
2. Activate both on staging, then run **wp-admin → Poolhall Jobs → Site
   setup** (added 11 June, third session): one nonce-protected button runs
   portal pages + the kit/theme-shell/jobs-template scripts, skipping any
   step whose requirements (Elementor / Elementor Pro) are missing and
   saying why. No SSH/wp-cli needed.
3. Ryan installs **Elementor Pro + Novamira manually** on staging
   (Novamira is staging/local only — hard rule 13 — and never production).
4. Re-run the kit/theme-shell/jobs-template scripts on staging once
   Elementor Pro is active, then `verify-sync.php` and
   `verify-accounts.php` (78 checks) against the staging install.
5. Never purchase anything (VPS, domains) through the API without Ryan
   confirming in chat first. (Nothing was purchased on 11 June — the
   subdomain and WP install are free on the existing plan.)

## What the next session can do

- **Phase 1 — prove the Giig API** (needs the Giig token + secret from
  Matt's Giig account; have them ready to paste when asked; they go into
  wp-config constants on staging, never the repo).
- **Continue Phase 3/4** (needs the Elementor Pro zip uploaded) — mobile
  drawer audience/account state, home page + featured carousel,
  search/filter/sort widgets, expired-job state, similar roles, save
  control frontend.
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
| Hostinger API token | `HOSTINGER_API_TOKEN` env var | ✅ active, verified 11 June |
| Staging wp-admin (`poolhall-admin`) | Ryan holds it; paste into chat when the session asks (needed to drive staging Site setup over HTTP) | active |
| Giig API token + secret | wp-config constants on staging | waiting on you/Matt |
| Google Places API key | wp-config constants on staging | not yet created |
| Elementor Pro licence | elementor.com account | zip uploaded per session (forgotten 11 June); installed manually on staging by Ryan |
| Novamira | Ryan installs manually on staging/local only | never production (hard rule 13) |
