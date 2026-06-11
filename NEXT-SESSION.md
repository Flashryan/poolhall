# Starting a new Claude Code session — step by step

Follow this whenever you start a fresh session on this repo (the container
is wiped between sessions; everything below gets you back to exactly where
the last session ended).

## Status after the second 11 June session

- **Staging exists.** Provisioned via the Hostinger API (token landed —
  thank you): WordPress is installed and valid at
  `http://lightslategrey-hare-335761.hostingersite.com/`
  (account `u232776807`, order 1008376137 = the Business Web Hosting
  plan; site title "Poolhall Recruitment (staging)", admin user
  `poolhall-admin`, password passed to Ryan in chat — never in the repo).
  Account inventory (runbook step 1): no VPS; orders 1008376137
  (business_v2), 1006103588 (premium), 200041955 (business); 22 websites,
  none poolhall-related, so a free `hostingersite.com` subdomain was
  generated rather than nesting under an unrelated client domain.
- **Plugin/theme deploy to staging is blocked on the network allowlist,
  not the API.** The deploy endpoints first upload files via TUS to the
  per-server files host — for this site
  `srv1350-files.hstgr.io` — which the proxy rejects (`Host not in
  allowlist`). Add `srv1350-files.hstgr.io` (or `*.hstgr.io`) to the
  network policy **before** booting the next session, then deploy
  `wordpress/dist/*.zip`. Until then the zips also install fine through
  staging wp-admin → upload (Ryan is in there anyway for Elementor Pro).
  Note: the Hostinger API has **no SSH/wp-cli execution**, so the
  portal-pages/kit/template scripts on staging need either wp-admin SSH
  from hPanel or a plugin-side admin trigger (small build task).
- Phase 6 security page is built and verified: `/candidate/security/`
  with reauthenticated password change, confirmed email change
  (enumeration-safe, old address notified), session list with
  this-device marker, revoke one/others. 78/78 integration checks,
  110 unit tests, HTTP-level E2E. See `wordpress/README.md`.
- The earlier 11 June session: Phase 6 auth (login/logout/recovery,
  portal guard, auth pages, saved-jobs REST).
- Deploy artifacts are scripted: `bash wordpress/scripts/build-deploy-zips.sh`
  produces `wordpress/dist/poolhall-integration.zip` and
  `wordpress/dist/hello-elementor-child.zip` (correct top-level folders for
  the WP uploader, hPanel and the Hostinger deploy API; no vendor/ needed).
- **The Elementor Pro zip was not uploaded this session**, so the
  theme-shell and jobs-template scripts could not run locally (they
  hard-require `ELEMENTOR_PRO_VERSION`); the kit/Site Settings script ran
  fine on free Elementor (which had to come from
  `downloads.wordpress.org` — the GitHub API was rate-limited without a
  `GITHUB_TOKEN`).
- Third session additions: **wp-admin Site setup runner** (Poolhall Jobs →
  Site setup; runs portal pages + the three Elementor scripts with
  requirement-aware skips — staging needs no SSH), and two setup-script
  fixes: the Hello Elementor parent now installs from
  `downloads.wordpress.org` (the GitHub source tree ships unbuilt assets
  and fatals on every wp-admin page) and free Elementor falls back to
  `downloads.wordpress.org` automatically when the GitHub API is
  rate-limited.

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
   - **`srv1350-files.hstgr.io` (or `*.hstgr.io`) — NEW, add this:** the
     Hostinger plugin/theme deploy uploads files there (TUS) before the
     deploy call; it was blocked on 11 June so staging deploys had to
     wait. The host is per-server: re-check with
     `POST /api/hosting/v1/files/upload-urls` if the site ever moves.
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

   > Read `wordpress/README.md`, `wordpress/docs/CLAUDE.md` and
   > `NEXT-SESSION.md`. Then:
   > 1. Run `bash wordpress/scripts/setup-local-wp.sh` to rebuild the local
   >    WordPress dev environment (idempotent; ends with tests + 15/15 sync
   >    checks; also creates the candidate portal pages).
   > 2. Unzip the Elementor Pro upload into the local WP plugins folder and
   >    activate it.
   > 3. Run `wp eval-file .../scripts/dev/configure-elementor-kit.php`,
   >    `.../create-theme-shell.php` and `.../create-jobs-templates.php` to
   >    restore the Phase 3/4 design system, theme shell and jobs
   >    templates, and verify the frontend renders.
   > 4. Run the Hostinger staging runbook below.
   > 5. Continue the build from the status table in `wordpress/README.md`.

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
| Staging wp-admin (`poolhall-admin`) | passed to Ryan in chat, 11 June | active — change it after first sign-in if you like |
| Giig API token + secret | wp-config constants on staging | waiting on you/Matt |
| Google Places API key | wp-config constants on staging | not yet created |
| Elementor Pro licence | elementor.com account | zip uploaded per session (forgotten 11 June); installed manually on staging by Ryan |
| Novamira | Ryan installs manually on staging/local only | never production (hard rule 13) |
