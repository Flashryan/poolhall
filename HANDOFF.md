# Poolhall — session handoff

**Updated:** 2026-11-27 · **Branch:** `claude/poolhall-giig-job-pull-h9cqpw` (clean, pushed, HEAD `15c9eaa`)
**Site:** https://www.poolhallrecruitment.co.uk — **live**, on Hostinger, serving real candidates.
The old `lightslategrey-hare-335761.hostingersite.com` staging URL is the same box.

---

## 1. Read this before you touch anything

**The repo now matches live. It did not before 2026-11-27 — do not let it drift again.**

The owner develops the site with Codex in parallel, and that work lands **straight on the
server**. Until commit `21f46e2` none of it was in git: 160 files existed only on production and
35 more differed. That commit captured the whole live child theme and plugin, so
`wordpress/themes/hello-elementor-child` and `wordpress/plugins/poolhall-integration` are now a
faithful copy of what is running. Everything below assumes you re-verify that before trusting it.

**Re-check drift before your first write of any session.** Codex may have shipped again since:

```bash
# Pull live code and diff it against this repo before editing anything.
# (Recipe: §3 — zip the two directories server-side, download, diff -rq.)
```

> **Never upload a repo file wholesale over a live file without diffing first.** Before the
> capture this would have deleted ~67 KB of CSS, unregistered the whole candidate onboarding
> workflow and rolled `DB_VERSION` backwards. The risk is lower now, but it returns the moment
> Codex touches the server again.

**The rule stands: read the live file first, merge your change into it, upload the merged result.**
Live `SchemaOutput.php` carries a `wp_footer` fallback (some Elementor Theme Builder templates
resolve singular context after `wp_head`) plus a duplicate-print guard. Overwriting it would
remove JobPosting markup from the very pages we are trying to get into Google.

### Two traps that have already cost time

**Elementor CSS beats theme CSS, and it lives in the database.** The PRL brand mark looked
broken for a whole round of debugging — the script was missing, but even after restoring it the
mark stayed invisible. The real cause was custom CSS saved on the homepage section itself:

```
/* Cached pre-update theme scripts must not restore the retired PRL letters. */
selector .ph-brandmark { display: none !important; }
```

Codex **retired** the mark and guarded against its return. The owner was asked on 2026-11-27 and
chose to **leave it exactly as-is**: the guard stays, the dormant CSS and JS stay in the theme.
Do not "fix" it. Symptom to remember: computed styles look right but `boundingBox()` is null —
use CDP `CSS.getMatchedStylesForNode`, because `document.styleSheets` enumeration missed it.

**The asset cache key is `POOLHALL_CHILD_VERSION`** in `functions.php`. Change any theme CSS or JS
without bumping it and returning visitors keep the cached file. See §3.

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

### Host migration to SiteGround — in progress, blocked on the owner

SiteGround exists and SG Migrator **already ran once, on 16 Nov** (`"Wordpress was migrated"`;
uploads confirmed present). It is now stale by data only:

| Drifted since 16 Nov | |
|---|---|
| #1224 Harryram Babulall — CV registration (23 Nov) | **a real candidate — must not be lost** |
| #1223 | spam enquiry |
| 1 job | modified |
| **Code** | **none** — `theme_changed: []`, `plugin_changed: []` as of 27 Nov 10:43 UTC |

Note the ui.js/`functions.php` deploy of 27 Nov **post-dates** that check, so the SiteGround copy
is now one theme version behind as well (`0.25.30` vs live `0.25.31`).

**Needed from the owner — neither can be done from a cloud session:**
1. A **fresh SG Migrator transfer token** (Site Tools → WordPress → Migrator). Tokens are
   single-use and the 16 Nov one is spent.
2. The **SiteGround temporary domain or IP** (Site Tools → Site → Site Information), so the
   fresh copy can be verified over HTTPS before any DNS change.

**Cutover rule — this one matters.** DNS is at **Wix** (`ns10/ns11.wixdns.net`) and carries all
of the client's Microsoft 365 records: MX → `poolhallrecruitment-co-uk.mail.protection.outlook.com`,
SPF `v=spf1 include:spf.protection.outlook.com -all`, `MS=ms82451161`, a Google verification TXT,
the `autodiscover` CNAME and `selector1._domainkey` DKIM.

> **Change only the A record at Wix. Never move nameservers to SiteGround** — that drops every
> record above and stops the client's email.

Re-run the delta check after the migration to catch anything that lands mid-transfer.

### Email — still sending as the owner's personal Gmail
FluentSMTP is sending **everything** as `rldavies23@gmail.com` with force-from ON (9 sent, 0
failed). That is why mail works despite the `-all` SPF. The client's IT company refused an Entra
app and agreed on **Brevo free tier** (300/day). FluentSMTP is free and supports Brevo, so no WP
Mail SMTP Pro licence is needed. When sending Brevo's DNS records to the IT company: **merge into
the single existing SPF record — never add a second `v=spf1`.**

Also ruled out, with sources: M365 Direct Send cannot reach external recipients at all, and Graph
`sendMail` caps simple attachments near 3 MB while the site accepts 25 MB CVs.

### Engineering
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
- ~~SMTP plugin (WP Mail SMTP) recommended before launch~~ — superseded: FluentSMTP is installed
  and sending. The open item is switching it off the owner's personal Gmail and onto Brevo
  (see "Email" above).

---

## 7. House rules carried forward

- Work on `claude/poolhall-giig-job-pull-h9cqpw`; never push to another branch without asking.
- Secret-scan the staged diff before every commit:
  `git diff --cached | grep -cE 'eyJ0eXAiOiJKV1Q|49b6c46f8fe3998d|Fjd1nplo'` (expect `0`).
  Note: this file quotes those three patterns, so staging **this file** self-matches and
  reports `1`. That single hit is the line above, not a leak — confirm with
  `grep -n` before worrying, and expect `0` for every other change.
- Never commit live credentials; never put `POOLHALL_GIIG_*` values in the repo.
- Run `vendor/bin/phpunit` and `composer lint` (needs `COMPOSER_ALLOW_SUPERUSER=1`) before
  deploying plugin changes. `composer install` takes ~3 minutes in a fresh container.
- GDPR rules stand: never log or email candidates' bank details, NI numbers, health answers or
  signature data; no personal data in URL parameters.
