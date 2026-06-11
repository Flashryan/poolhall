# Starting a new Claude Code session — step by step

Follow this whenever you start a fresh session on this repo (the container
is wiped between sessions; everything below gets you back to exactly where
the last session ended).

## Status after the 11 June session

- Phase 6 auth is built and verified: candidate login/logout, password
  recovery, the `/candidate/*` portal guard, server-rendered auth pages
  and the saved-jobs REST API (56/56 integration checks, 105 unit tests,
  HTTP-level E2E). See the status table in `wordpress/README.md`.
- The Hostinger MCP server now starts from `.mcp.json`, **but the API
  rejected every call with `Unauthenticated` because `HOSTINGER_API_TOKEN`
  is not in the container** — staging could not be provisioned. Fixing
  that is item 1 below.
- Deploy artifacts are scripted: `bash wordpress/scripts/build-deploy-zips.sh`
  produces `wordpress/dist/poolhall-integration.zip` and
  `wordpress/dist/hello-elementor-child.zip` (correct top-level folders for
  the WP uploader, hPanel and the Hostinger deploy API; no vendor/ needed).

## Before you start the session (one-time setup)

1. **Environment variables** (claude.ai/code → environment settings):
   - `HOSTINGER_API_TOKEN` = token from hPanel → Account → API.
     **This was still missing on 11 June — the whole staging step is
     blocked until it lands.**
   - Optional: `GITHUB_TOKEN` = a GitHub token (avoids API rate limits in
     the setup script; without it Elementor falls back to
     downloads.wordpress.org, which also works).
   - **Gotcha (verified twice):** allowlist and env-var changes only apply
     when a container boots — a running session never sees them. Edit
     first, then start the session.
2. **Network policy** (same settings screen). Confirm these domains are
   allowed — you added them on 10 June:
   - `developers.hostinger.com` (Hostinger API; `api.hostinger.com` is NOT
     it). The proxy's block response is an HTTP 403 with body
     `Host not in allowlist`; a real Hostinger reply to a tokenless call
     is a 401 `Unauthenticated` JSON body.
   - `wordpress.org` and `downloads.wordpress.org` (core/plugins; Elementor
     free comes from here when the GitHub API is rate-limited)
   - `api.giighire.com` (Giig — needed for Phase 1)
   - `places.googleapis.com` (Google reviews)
   - `my.elementor.com` (Elementor Pro licence)
3. **Upload the Elementor Pro zip** into the chat (licensed, deliberately
   not in the repo). Without it the local theme-shell and jobs-template
   scripts refuse to run (free Elementor from the setup script is enough
   for the kit/Site Settings script and all plugin work).

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

## Hostinger staging runbook (blocked only on the token)

Ryan's decision on 11 June: **Claude provisions the staging site; Ryan
installs Elementor Pro and Novamira on it manually** (their zips/licences
never go through the API or the repo).

1. Inventory: `hosting_listWebsitesV1`, `billing_getSubscriptionListV1`,
   `VPS_getVirtualMachinesV1`, `domains_getDomainListV1` (MCP tools — the
   server starts from `.mcp.json` automatically). What we can provision
   depends on which plan the subscriptions show; record it here.
2. Managed/shared WP plan path (expected): create a staging subdomain on
   the existing site (`hosting_createWebsiteSubdomainV1` or
   `hosting_generateAFreeSubdomainV1` if no domain fits), install WordPress
   on it (`hosting_installWordPressV1`), then deploy our artifacts with
   `hosting_deployWordpressPlugin` / `hosting_deployWordpressTheme` from
   `wordpress/dist/` (build them first: `bash
   wordpress/scripts/build-deploy-zips.sh`).
3. VPS path (only if the inventory says so): pick the WordPress template
   via `VPS_getTemplatesV1` and set up through the VPS endpoints instead.
4. After WordPress is up: activate the plugin + child theme, run the
   portal-pages script, then hand the wp-admin URL to Ryan to install
   **Elementor Pro + Novamira manually** (Novamira is staging/local only —
   hard rule 13 — and never production).
5. Re-run the kit/theme-shell/jobs-template scripts on staging once
   Elementor Pro is active, then `verify-sync.php` and
   `verify-accounts.php` (56 checks) against the staging install.
6. Never purchase anything (VPS, domains) through the API without Ryan
   confirming in chat first.

## What the session after that can do

- **Phase 1 — prove the Giig API** (needs the Giig token + secret from
  Matt's Giig account; have them ready to paste when asked; they go into
  wp-config constants on staging, never the repo).
- **Continue Phase 3/4** — mobile drawer audience/account state, home page
  + featured carousel, search/filter/sort widgets, expired-job state,
  similar roles, save control frontend.
- **Continue Phase 6** — security page (change password/email, session
  revocation), dashboard modules, alerts.

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
| Hostinger API token | `HOSTINGER_API_TOKEN` env var | **still to add — blocked staging on 11 June** |
| Giig API token + secret | wp-config constants on staging | waiting on you/Matt |
| Google Places API key | wp-config constants on staging | not yet created |
| Elementor Pro licence | elementor.com account | zip uploaded per session; installed manually on staging by Ryan |
| Novamira | Ryan installs manually on staging/local only | never production (hard rule 13) |
