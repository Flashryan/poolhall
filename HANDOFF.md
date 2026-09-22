# Poolhall — session handoff

**Written:** 2026-09-22 · **Branch:** `claude/poolhall-giig-job-pull-h9cqpw` (clean, pushed, HEAD `1546fb6`)
**Site:** https://lightslategrey-hare-335761.hostingersite.com (staging, now publicly reachable)

---

## 1. Read this before you touch anything

**The live site is ahead of this repository, and the difference is not in git.**

The owner has been developing the site with Codex in parallel. That work exists **only on the
server** — no branch, no commit, no backup. In this session the live theme was `0.25.13` while
this branch said `0.19.0`:

| File | Live | This repo (July baseline) |
|---|---|---|
| `shared.css` | ~175 KB | ~108 KB |
| `ui.js` | ~29 KB | ~22 KB |
| `functions.php` | 4,899 B (v3 design pilot, Manrope, `design-system-v3.css`) | 1,980 B |
| `Plugin.php` | 15,012 B (`WorkflowMigration`, `ReminderService`, `DraftManager`, `WorkflowState`, `InviteMailer`, `EmployersVisuals`, `SectorJobs`, `DB_VERSION = 5`) | 13,039 B (`DB_VERSION = 2`) |

> **Never upload a repo file wholesale over a live file.** Doing so in this session would have
> deleted ~67 KB of CSS, unregistered the whole candidate onboarding workflow, and rolled
> `DB_VERSION` backwards — unrecoverably, since none of it is committed.

**The rule: read the live file first, merge your change into it, upload the merged result.**
Live `SchemaOutput.php` also carried a `wp_footer` fallback (some Elementor Theme Builder
templates resolve singular context after `wp_head`) plus a duplicate-print guard. Overwriting it
would have removed JobPosting markup from the very pages we were trying to get into Google.

Before editing any live file, diff it against the matching commit here:

```bash
git show 302842b:wordpress/plugins/poolhall-integration/src/Schema/JobPostingSchema.php
```

If live matches a known baseline, your diff applies cleanly. If not, port your change by hand.

---

## 2. Connecting (do this first — it does not survive a new container)

`NOVAMIRA_WP_APP_PASSWORD` is **not** set in the environment, so the `novamira-lightslategrey-h`
MCP server never connects. Ignore it. Use the **Novamira CLI** instead:

```bash
curl -fsSL https://raw.githubusercontent.com/use-novamira/novamira-cli/main/install.sh \
  | env NOVAMIRA_AGENT='claude-code' sh

export PATH="$(npm prefix --global)/bin:$PATH"

# Headless container: the browser flow fails instantly, go straight to --device
novamira auth login 'https://lightslategrey-hare-335761.hostingersite.com/' --device
```

Run the login **in the background**, read the verification URL and short code from its output,
and give the code to the owner to approve at
`/wp-admin/admin.php?page=novamira-oauth-device`. Then:

```bash
novamira doctor --json     # expect ok:true, ~112 abilities, restReachable:true
```

Notes:
- The OAuth token is short-lived (this session's expired ~90 minutes out). Re-run the device
  flow if calls start failing with auth errors.
- `doctor` reports `status: warn` — that is only the credential store falling back to an
  owner-only file because a headless container has no OS keychain. Harmless.
- Hostinger's firewall does **not** block this container. No support template needed.
- Other Novamira servers in the tool list (Howells, Icospa, sstest) are **different clients'
  sites**. Do not touch them.

---

## 3. Deploy recipes that actually work

### Non-PHP files (CSS, JS, images)

`novamira upload` refuses to overwrite. Use an upload link with `overwrite: true`:

```bash
novamira run novamira/create-upload-link --json \
  --input '{"path":"wp-content/themes/hello-elementor-child/assets/css/shared.css","overwrite":true}'
# -> upload_url, upload_token, token_header
curl -sS -X PUT -H "$token_header: $upload_token" --data-binary @local.css "$upload_url"
```

### PHP files

Novamira blocks PHP writes outside `wp-content/novamira-sandbox/` — this applies to
`upload`, `write-file` **and** `edit-file`. The vendor's documented route for plugins and
themes is a ZIP (`write-file`'s own description says so; the sandbox is explicitly *not* a
security boundary):

1. Build a ZIP whose paths are relative to `wp-content/` (`themes/...`, `plugins/...`)
2. Upload it to `wp-content/uploads/x.zip` via `create-upload-link`
3. Extract with `execute-php`, verifying md5 **before** unzipping:

```php
$zip = WP_CONTENT_DIR . '/uploads/x.zip';
if ( md5_file( $zip ) !== '<expected>' ) { return 'MD5 MISMATCH'; }
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
$r = unzip_file( $zip, WP_CONTENT_DIR );
if ( is_wp_error( $r ) ) { return 'FAIL: ' . $r->get_error_message(); }
unlink( $zip );
if ( function_exists( 'opcache_reset' ) ) { opcache_reset(); }
do_action( 'litespeed_purge_all' );
return 'OK';
```

> **`execute-php` is flagged destructive and silently no-ops without `--yes`.** It returns
> `data: {}` and looks like a success. My first extraction never ran and I only caught it by
> verifying the files afterwards. Always pass `--yes` and always verify the result.

`mkdir(): File exists` warnings during unzip are benign.

### Bump the theme version on every CSS/JS change

`POOLHALL_CHILD_VERSION` in `functions.php` is the cache-buster. **Read the live value first**
(it moves independently of this repo) and increment from that. Live is currently `0.25.15`.

---

## 4. Verifying against the live site

Outbound HTTPS is MITM'd by the agent proxy, so Playwright fails with
`ERR_CERT_AUTHORITY_INVALID` on a direct `goto`. Route every request through Node `fetch`:

```js
await page.route('**/*', async r => {
  const q = r.request();
  const h = { ...q.headers() }; delete h.host; delete h['content-length'];
  const s = await fetch(q.url(), { method: q.method(), headers: h,
    body: q.postDataBuffer() || undefined, redirect: 'follow' });
  const body = Buffer.from(await s.arrayBuffer()); const H = {};
  s.headers.forEach((v,k)=>{ if(!['content-encoding','content-length','transfer-encoding','connection'].includes(k)) H[k]=v; });
  await r.fulfill({ status: s.status, headers: H, body });
});
```

Env: `NODE_USE_ENV_PROXY=1 NODE_EXTRA_CA_CERTS=/root/.ccr/ca-bundle.crt`,
browser at `/opt/pw-browsers/chromium`.

**Dry-run trick that caught two bugs:** intercept `shared.css` / `ui.js` and serve your *merged*
files to the live page before uploading anything. That is how the stacked-letters and stale-rule
problems surfaced without touching the server.

The section has an entrance animation (`ph-motion-ready`) — wait ~2s after scrolling or you will
screenshot a half-faded section and misread it.

---

## 5. Shipped this session (all live and verified)

**Google Jobs** (`d2ee3b5`, `6dfe3d7`)
- `src/Admin/GoogleJobsPage.php` — new **Poolhall Jobs → Google Jobs** screen: site
  prerequisites, per-role verdict with the exact reason, organisation settings form (those
  options previously had no UI at all), and a *Test with Google* link per role.
- `src/Schema/JobFromPost.php` — shared rebuild of a `SourceJob` from post meta, so schema
  output and the admin verdict can never disagree.
- `JobPostingSchema::problems()` / `recommendations()` — the same gate `build()` uses, exposed
  so a missing location stops being a silent non-listing.
- Expiry hygiene in `SchemaOutput` — expired roles leave the sitemap and go `noindex`. The
  sitemap clause uses a **plain string compare on ATOM** (matching `ArchiveQuery`); a
  `DATETIME` cast chokes on the `T` separator.
- `docs/GOOGLE-JOBS-SETUP.md` — non-technical walkthrough for the client.

**PRL brand mark** (`8efe5dd`, `1546fb6`)
- Logo initials as a hairline serif outline, horizontal, 250px, light grey, resting on the
  bottom edge of the "people at the heart of it" section, behind the photography.
- Scroll-direction-aware: each letter runs −1 → 0 → +1 through its pass and the sign flips with
  direction, so it arrives from above going down and from below going up, staggered P/R/L.
- Injected from `ui.js` (decorative — no JS means no ornament, and Elementor never needs
  rebuilding). Hosted on the `<section>`, not `.ph-split`.
- `flex-direction` and `inset-block-start` are declared **explicitly**: without them a stale
  rule elsewhere in the cascade resurrects the old vertical, centred placement.

---

## 6. Outstanding

### Owner actions (blocking Google Jobs)
1. **`blog_public` is `0`** — Settings → Reading has "Discourage search engines" ticked. This
   alone stops Google entirely. Turning off the site password did **not** clear it.
2. **Organisation name reads "Poolhall Recruitment (staging)"** — that is what Google would
   show as the employer. Fix on the Google Jobs screen before launch.
3. **Submit `wp-sitemap.xml`** once in Search Console (needs site verification first).

Current readiness: **11 of 11 live roles qualify.** The schema side is done.

### Engineering
- **Back up the live code.** Still the highest-value task: pull `wp-content` into a branch so
  two months of Codex's work has a restore point and the stale-branch trap stops recurring.
  Offered twice, not yet taken up.
- `password_protected_status` is a stale `1` in the options table with no plugin active. The
  readiness check now ignores it unless `Password_Protected` is loaded, but the row could be
  deleted.
- Optional: Google **Indexing API** for near-instant listing and removal of filled roles. Needs
  a Google Cloud service account; only worth doing once the basics above are live.

### Carried from July (unverified — confirm before acting)
- Giig test data to delete: candidates `562869`, `562876`–`562879`, company `475898`,
  contacts `689509`/`689510`, auto-created company `475900`, plus two "TEST IGNORE" timeline
  notes on candidate `563932`.
- Giig **sensitive-route access** still 403s. Until support enables it, `candidate/update`
  (update-instead-of-duplicate) and `candidate/get` reconciliation stay dormant.
- Candidate-list IDs for Giig (`poolhall_giig_candidate_list`) never supplied; the wiring is in
  place and inactive.
- Final Candidate Terms legal wording is still placeholder text flagged for review.
- SMTP plugin (WP Mail SMTP) recommended before launch so form mail is reliable.

---

## 7. House rules carried forward

- Work on `claude/poolhall-giig-job-pull-h9cqpw`; never push to another branch without asking.
- Secret-scan the staged diff before every commit:
  `git diff --cached | grep -cE 'eyJ0eXAiOiJKV1Q|49b6c46f8fe3998d|Fjd1nplo'` (expect `0`).
- Never commit live credentials; never put `POOLHALL_GIIG_*` values in the repo.
- Run `vendor/bin/phpunit` and `composer lint` (needs `COMPOSER_ALLOW_SUPERUSER=1`) before
  deploying plugin changes. `composer install` takes ~3 minutes in a fresh container.
- GDPR rules stand: never log or email candidates' bank details, NI numbers, health answers or
  signature data; no personal data in URL parameters.
