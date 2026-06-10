# Starting a new Claude Code session — step by step

Follow this whenever you start a fresh session on this repo (the container
is wiped between sessions; everything below gets you back to exactly where
the last session ended).

## Before you start the session (one-time setup, already mostly done)

1. **Network policy** (claude.ai/code → environment settings → Network).
   Make sure these domains are allowed — you added them on 10 June:
   - `api.hostinger.com` (Hostinger API)
   - `wordpress.org` and `downloads.wordpress.org` (plugins/translations)
   - `api.giighire.com` (Giig — needed for Phase 1)
   - `places.googleapis.com` (Google reviews)
   - `my.elementor.com` (Elementor Pro licence)
2. **Environment variables** (same settings screen):
   - `HOSTINGER_API_TOKEN` = token from hPanel → Account → API
   - Optional: `GITHUB_TOKEN` = a GitHub token (avoids API rate limits in
     the setup script)
3. Know which **Hostinger plan** you're on (VPS or shared/managed WP) —
   it changes how we deploy staging.

## Starting the session

4. Start a new Claude Code session on the **Flashryan/poolhall** repo.
5. **Upload the Elementor Pro zip** into the chat (it's licensed, so it is
   deliberately not in the public repo).
6. Paste this as your first message:

   > Read `wordpress/README.md`, `wordpress/docs/CLAUDE.md` and
   > `NEXT-SESSION.md`. Then:
   > 1. Run `bash wordpress/scripts/setup-local-wp.sh` to rebuild the local
   >    WordPress dev environment.
   > 2. Unzip the Elementor Pro upload into the local WP plugins folder and
   >    activate it.
   > 3. Run `wp eval-file .../scripts/dev/configure-elementor-kit.php` and
   >    `.../scripts/dev/create-theme-shell.php` to restore the Phase 3
   >    design system and theme shell, and verify the frontend renders.
   > 4. Check Hostinger API access with my HOSTINGER_API_TOKEN and tell me
   >    what we can provision.
   > 5. Continue the build from the status table in `wordpress/README.md`.

## What the new session can then do

- **Provision staging on Hostinger** (path depends on your plan — Claude
  will check via the API and tell you).
- **Phase 1 — prove the Giig API** (needs the Giig token + secret from
  Matt's Giig account; have them ready to paste when asked).
- **Continue Phase 3/4** — style-guide page, jobs archive/single templates,
  search widgets.

## If something looks broken in a new session

- `bash wordpress/scripts/setup-local-wp.sh` is idempotent — re-run it.
- Tests: `cd wordpress/plugins/poolhall-integration && composer test`
- Integration check: `wp eval-file wp-content/plugins/poolhall-integration/scripts/dev/verify-sync.php`
- All build decisions live in `wordpress/docs/` — the build brief
  (`00-ONE-SHOT-BUILD-BRIEF.md`) wins when documents disagree.

## Credentials checklist (never commit any of these)

| Credential | Where it lives | Status |
|---|---|---|
| GitHub PAT | you paste it when pushing | active |
| Hostinger API token | `HOSTINGER_API_TOKEN` env var | you to add |
| Giig API token + secret | wp-config constants on staging | waiting on you/Matt |
| Google Places API key | wp-config constants on staging | not yet created |
| Elementor Pro licence | elementor.com account | zip uploaded per session |
