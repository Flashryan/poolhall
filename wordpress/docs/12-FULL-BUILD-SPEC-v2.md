# Poolhall Recruitment — Full Build Specification
**For: the Claude Code session building the WordPress site**
**Staging:** https://lightslategrey-hare-335761.hostingersite.com/
**Production target:** poolhallrecruitment.co.uk
**Spec version:** 1.0 (v2 "Engineered" design) · supersedes all earlier directives
**Visual contract:** the HTML prototype in `ui_kits/website/` (bundled as `poolhall-website.html`, also deployed at `deploy/index.html`).

> **Repository note (added when saved here):** this is the v2 "Engineered"
> build directive, stored verbatim for reference. It is the latest scope
> authority and supersedes the earlier directive; the numbered docs `00`–`11`
> remain the detailed references for areas this spec does not re-cover. The
> three Giig credentials supplied in §2.1 (company id, access token, API
> secret) have been **redacted** here per the spec's own security rule — they
> live only in `wp-config.php`/environment (`GIIG_COMPANY_ID`,
> `GIIG_ACCESS_TOKEN`, `GIIG_API_SECRET`) and were never committed. Treat the
> originally supplied values as compromised and rotate them.

> **Precedence:** where this doc and the prototype disagree, the **prototype wins for VISUALS** (layout, colour, type, spacing, states); **this doc wins for SCOPE, DATA rules, integrations and acceptance**. The design tokens in `colors_and_type.css` are the single source of truth for colour/type/spacing.

---

## 0. How to use this document

1. Read §1–§3 fully before writing code (architecture, stack, data model).
2. Build in this order: **adapter + CPT + sync (§2–§4) → templates page-by-page (§7) → application push (§8) → Google for Jobs (§9) → reviews (§10) → polish + a11y (§13)**.
3. The prototype files are **React/JSX for preview only — do NOT port JSX**. Re-implement the same rendered output in the theme's PHP/templating. Class names quoted (e.g. `.jobcard`) refer to the prototype's `kit.css`; copy exact values from there or from §11.
4. Every page in §7 and every requirement has an **acceptance check**. The master list is §15.

### Hard guardrails (do not violate)
1. **No price figure for Better Job Adverts.** Use "Pricing confirmed when you enquire."
2. **Service-tier prices (Bronze/Silver/Gold) are INDICATIVE** (12% / 15% / 20% of first-year salary). Keep the on-page note "Percentages are indicative… exact terms confirmed in writing when you enquire" until Matthew confirms real fees. Do not present them as contractual.
3. **No em-dashes (—) anywhere in copy.** Use commas, full stops, or rewrites. (En-dashes in numeric ranges like "£60,000–£75,000" are fine.)
4. **British English. Sentence-case headings. No emoji in UI.**
5. **Do not invent:** team emails/LinkedIn URLs, client names, candidate testimonials, real review text, or fee figures. Real reviews come only from the Places API; until the key exists, the placeholder quotes in `data.js` may appear **on staging only, never production**.
6. **Type:** display/headings = **Archivo** (500–900 weights, see §11). Body = **Source Sans 3**. Job meta/reference data = **IBM Plex Mono**. Do not substitute serif fonts (that was the retired v1 direction).
7. **Tone is warm and reassuring, not blunt.** Match the prototype copy (it is the approved voice).

---

## 1. Architecture

```
                       (server cron, every 2–4h / same-day)
   Giig Hire API  ───────────────────────────────►  WordPress (poolhallrecruitment.co.uk)
   (jobs source,                                     ├─ Jobs cached as a Custom Post Type (CPT)
    Plus plan,                                       ├─ Listing + filters + single-job templates
    public API)                                      ├─ Featured-jobs carousel on home
                  ◄───────────────────────────       ├─ Bronze/Silver/Gold employer tiers (static)
                   (application + CV, on submit)      └─ JobPosting JSON-LD → Google for Jobs

   Google Places API ──────────►  Reviews carousel (server-cached 24h)
```

**Why cache jobs locally as a CPT** (not live API per request): fast pages / Core Web Vitals; resilient if the API is briefly down; stable indexable URLs (required for Google for Jobs); filtering runs on the local DB; volume is small (~20 live roles) so cache invalidation is trivial.

---

## 2. Tech stack & the swappable adapter

- **WordPress**, custom theme + a custom **integration plugin** (keep all integration logic in the plugin, not the theme, so the theme stays portable).
- **All ATS-specific code sits behind ONE interface** so a future ATS change means rewriting the adapter, not the site:

```php
interface JobSource {
    /** @return JobDTO[]  Normalised jobs from the source. */
    public function fetch_jobs(): array;

    /** Push an application + CV back to the source.
     *  @return ApplyResult  ok flag + remote id or error. */
    public function submit_application(string $remoteJobId, CandidateDTO $candidate, FileUpload $cv): ApplyResult;
}

final class GiigAdapter implements JobSource { /* Giig Plus API specifics live ONLY here */ }
```

- `JobDTO` / `CandidateDTO` are plain normalised structs (see §3, §8). Nothing outside the adapter knows Giig field names.

### 2.1 Giig credentials (provided by client)

```
Giig Company ID : [REDACTED:GIIG_COMPANY_ID]
Access token    : [REDACTED — set GIIG_ACCESS_TOKEN in wp-config/env; never commit]
API secret      : [REDACTED — set GIIG_API_SECRET in wp-config/env; never commit]
```

> **SECURITY (do this, don't skip):** store all three in `wp-config.php` constants or environment variables (e.g. `GIIG_COMPANY_ID`, `GIIG_ACCESS_TOKEN`, `GIIG_API_SECRET`) and read them from there in `GiigAdapter`. **Never commit them to the repo, never expose them client-side, never echo them in markup or logs.** Rotate if they ever leak. This doc holds them only for handover; move them to secrets immediately and treat any committed copy as compromised.

### 2.2 Giig API surface (confirmed from the docs)

**Base:** `{API_URL}/public/api/v1/…` — ⚠️ the **actual `API_URL` host is still unknown** (docs use a `{{API_URL}}` placeholder). Get the real base from your Giig account settings / Giig support and store as `GIIG_API_URL`. Everything else below is confirmed.

**Auth — every request sends BOTH headers:**
```
Authorization: Bearer {GIIG_ACCESS_TOKEN}
Access-Secret-Key: {GIIG_API_SECRET}
```
> The docs' intro paragraph mentions a custom header `Access-Session-Type: SESSION_KEY`, but **every endpoint example uses `Access-Secret-Key`** instead. Use `Access-Secret-Key`; if a call 401s, try `Access-Session-Type` as the fallback and confirm with Giig. `CompanyId` ([REDACTED:GIIG_COMPANY_ID]) is required on some calls.

**Endpoints we use (read = sync, write = application):**
| Purpose | Method · path | Notes |
|---|---|---|
| **List live public jobs** | `GET /public/api/v1/job/getjobs?max=N` | Returns only live jobs shown on the careers page. This is the **sync source** (§4). `max` = how many, newest first. No paging param documented, so request a high `max` (e.g. 100). |
| **Get single job** | `GET /public/api/v1/job/get?JobId={id}` (+ optional `CompanyId`) | Live public jobs only. Use to refresh a single record if needed. |
| **Create candidate** | `POST /public/api/v1/candidate` | Required: `FirstName`, `LastName`. Optional: `EmailAddress`, `PhoneNumber`, `RoleTitle`, `Location`, `Source` (set "Website"), `Notes` (put the cover message here), `SalaryExpectations`, `LinkedIn`. **Returns the new `CandidateId`.** |
| **Submit application** | `POST /public/api/v1/applicant/submit` | Required: `CandidateId`, `JobId`, `Salary` (number). Optional: `Source`. |

**Application = a TWO-STEP write** (there is no single "apply" call): (1) `POST /candidate` → capture `CandidateId`; (2) `POST /applicant/submit` with that `CandidateId` + the `JobId`. For `Salary`, pass the job's `SalaryFrom` (or 0 if unknown) — confirm with Giig whether it expects the candidate's expectation or the role salary.

> ### ⚠️ CRITICAL GAP — no CV/file upload endpoint
> **The documented API has no resume/CV/file-upload endpoint.** `Create Candidate` takes no file field. So `submit_application()` as specced in §8 (push the CV to Giig) **cannot be fully satisfied with the documented surface.** Resolve with Giig before building §8:
> 1. ask Giig if there's an undocumented CV-upload endpoint or a base64 field on candidate create, **or**
> 2. interim: **store the CV in WordPress** (media library, private) and put a link to it in the candidate `Notes`, and email it to Matthew, **or**
> 3. confirm whether CVs should flow via a different Giig channel.
> Until resolved, build the apply flow to **create the candidate + submit the applicant + store/email the CV locally**, and keep the CV in the local retry queue so nothing is lost.

**Reference enums (for parsing job data, §3):** `JobType` 0=Perm 1=Temp · `Currency` 0=£ 1=$ 2=€ … · `SalaryPeriod` perm 0=year 1=month 2=week, temp 3=day 4=hour · `JobStatus` 1=Lead 2=Published&Live 4=Closed · `JobBoard` 1=Display on careers page. The adapter normalises these into `JobDTO` (e.g. salary period → "/ year"; currency → "£").

**Other endpoints available but not needed for v1** (logging activity, companies, contacts, candidate lists, updates): ignore unless we add CRM features later. There is **no reviews endpoint** here — reviews come from Google Places (§10), not Giig.

**Full docs:** https://api-doc.giighire.com/ (JS-rendered; read live for request/response bodies and any updates).

---

## 3. Data model

### 3.1 Job CPT — `job`
Public, archive enabled, slug `/jobs/`, single slug `/jobs/{slug}/`.

### 3.2 Taxonomies
| Taxonomy | Values (current) | Notes |
|---|---|---|
| `sector` | Construction, Manufacturing, Digital | **Primary 3** shown across the site. The Giig "Industry" field may also emit Marketing & PR, Sales, Insurance, Automotive — the adapter maps each Giig industry to a `sector` term (create terms on demand). Don't drop jobs whose industry isn't one of the three; show them under their mapped term. |
| `job_type` | Permanent (+ Temporary, Contract when present) | Support all Giig values. |
| `work_mode` | Must be onsite, Part Remote, Fully Remote | Also drives schema `jobLocationType: TELECOMMUTE` for remote. |
| `location` | city + region terms | Plus free-text meta for display. |

### 3.3 Post fields & meta (field map — confirmed against the live Giig careers page)
| Giig field | WP target | Notes |
|---|---|---|
| Job ID (numeric, e.g. 27499) | meta `giig_job_id` | **Dedupe key.** |
| Title | `post_title` | |
| Description | `post_content` | |
| Salary range ("£55,000 - £65,000/Year") | meta `salary_display` + parsed `salary_min`/`salary_max`/`salary_unit` | Display + schema `baseSalary`. Single value when min==max. |
| Location (city + region + country) | `location` taxonomy + meta | |
| Industry | `sector` taxonomy | See mapping above. |
| Job Type | `job_type` taxonomy | |
| Remote/Onsite | `work_mode` taxonomy | |
| Experience Required | meta `experience` | Display; optional filter (off by default). |
| Education Required | meta `education` | Display; optional filter (off by default). |
| Date posted | meta `date_posted` | Schema `datePosted`. |
| (derived) Expires at | meta `expires_at` = `date_posted` + 30 days | Schema `validThrough` (Giig has no closing date — confirmed). |
| `featured` | meta `featured` (bool) | Newest 4–6 or flagged; drives home carousel + gold edge. |

---

## 4. Jobs sync (pull from Giig)

- **Real server cron** (not WP pseudo-cron). Cadence: **every 2–4 hours** ("same-day" agreed with client).
- **Upsert by `giig_job_id`:** create new, update changed, **unpublish** jobs no longer present in the feed.
- **Auto-expire:** when `now > expires_at` (date_posted + 30d), set status to expired (see §7.3 expired state) and drop its JSON-LD.
- **Admin:** a "Sync now" button + a **sync log** (timestamp, created/updated/unpublished counts, errors).
- **Idempotent & safe:** a failed/partial fetch must never wipe the CPT. If the feed returns 0 or errors, keep the existing cache and log a warning + admin alert.

**Scenarios to handle:** API down/timeout (keep cache, alert); job removed from feed (unpublish, keep URL returning 410 or redirect per §9); salary missing (hide salary line, omit `baseSalary`); multi-location / region-only ("UK", "West Midlands") → use `addressRegion` fallback in schema; duplicate IDs (last write wins on dedupe key).

---

## 5. Endpoints & routes

### 5.1 Public URLs (must all exist, be indexable unless noted)
| Route | Template | §ref |
|---|---|---|
| `/` | Home | 7.1 |
| `/jobs/` | Jobs archive + filters | 7.2 |
| `/jobs/{slug}/` | Single job | 7.3 |
| `/jobs/{slug}/apply/` | Apply form | 7.4 |
| `/employers/` | Employers hub (incl. **Bronze/Silver/Gold tiers**) | 7.5, 6.2 |
| `/employers/delivery-options/` | 5 delivery models | 7.5 |
| `/better-job-adverts/` | BJA service (no price) | 7.6 |
| `/why-us/`, `/bespoke-search/`, `/hr-services/`, `/commitment/` | Secondary employer/info pages | 7.9 |
| `/sectors/`, `/sectors/{construction|manufacturing|digital}/` | Sector hub + templates | 7.7 |
| `/candidates/` | Candidate hub | 7.7 |
| `/register/` | Register-your-CV form | 7.4 |
| `/about/`, `/team/` | About / Meet the team | 7.8 |
| `/contact/` | Contact (3 routes) | 7.8 |
| `/blog/`, `/blog/{slug}/` | Blog index + post | 7.9 |
| `/privacy/`, `/terms/`, `/cookies/` | Legal templates | 7.9 |
| `/candidate/login` `/register` `/verify` `/forgot` `/reset` `/account/security` | Portal auth screens — **noindex, gated** | 7.10 |

### 5.2 Internal REST / actions (plugin)
| Method · route | Purpose |
|---|---|
| `POST /wp-json/poolhall/v1/apply` | Validate → `submit_application()` → on success confirmation, on failure local queue + admin alert (§8). |
| `POST /wp-json/poolhall/v1/contact` | Contact/enquiry → email to Matthew (+ Giig contact endpoint if it exists). |
| admin-post `poolhall_sync_now` | Manual sync trigger. |
| cron hook `poolhall_sync_jobs` | Scheduled sync (§4). |
| cron hook `poolhall_refresh_reviews` | 24h Places cache refresh (§10). |
| `GET /sitemap.xml` (or Yoast/RankMath) | Includes all live job URLs (§9). |

---

## 6. The two journeys

The site has **two clearly separated front doors**, carried through nav, CTA colour and page heads. **Steel (#3E6E8E) = employer cue; gold (#FDBB5D) = the primary action accent.** The header has a Candidates / Employers segmented switch (collapses into the mobile drawer).

### 6.1 Candidate journey
Home → **Find a Job** (`/jobs/`) → **Single job** → **Apply** → confirmation.
Supporting: Candidate hub (`/candidates/`, "how we work with you", 3 steps, guides), Register your CV (`/register/`), sectors, blog. Distinct, encouraging copy ("Find work", "your next move").

### 6.2 Employer journey
Home (employer CTA) → **Employers** (`/employers/`) → choose a **service tier** or a delivery model → **enquiry/contact**.
The Employers hub now includes a **Bronze / Silver / Gold pricing table** (§7.5, component §11.6) plus the 5 delivery options, the "why us" split, the sectors band, the gold BJA callout, and the enquiry form. Distinct, reassuring copy ("Hire talent", "represent your business like it's our own").

---

## 7. Page-by-page build spec

> All pages share: sticky translucent **header** (utility bar with phone/email/location, Archivo dropdown nav, Candidates/Employers switch, "Sign in" link, gold "Find work"/"Hire talent" primary), and the **footer** (§7.0). Interior pages use a **photo page-header** (`.pagehead.photo`): navy `#0B2846` ground, photo at ~0.5 opacity under a 100° navy gradient + a 4px gold left edge.

### 7.0 Footer (every page) — prototype `ui.jsx` → `Footer`, `.site-footer`
Navy `#06182B` (slate-950) background, 4-column grid (≈1.6fr/1fr/1fr/1fr):
1. **Brand:** the PRL navy/gold **logo lockup** (`assets/poolhall-logo-lockup.png` on a white rounded chip), about line, LinkedIn + Instagram circular icon buttons (inline brand SVGs — Lucide has no brand icons).
2. **Candidates:** Find a job · Browse sectors · Register your CV · Career advice.
3. **Employers:** Hire talent · Our services · Better Job Adverts · Partner with us.
4. **Company:** Meet the team · About us · Contact · Privacy policy.
Then an **accreditations strip**: top hairline, label "PROUD MEMBERS OF" (mono, .12em, uppercase), accreditation logos on white chips. Bottom bar: "© 2026 Poolhall Recruitment Limited · Company No. 13319338 · VAT 383617377" left, "Grosvenor House, 11 St Pauls Square, Birmingham, B3 1RB" right.
**Accept:** identical footer on every page; accreditation logos visible; all links resolve.

### 7.1 Home `/` — prototype `screen-home.jsx` + `blocks.jsx`
Top→bottom:
1. **Four-stage animated hero** (signature). Full-bleed, navy ground, four "worlds" (Construction · Manufacturing · Digital · Team) cross-fading with a slow ken-burns zoom (~4s each), world markers along the bottom (active marker gets a gold top rule). Eyebrow "// RECRUITMENT, DONE WELL", Archivo display H1 "West Midlands roots. *National* recruitment reach." (the emphasised word in gold), lead, two CTAs (gold "Find work" with navy text + ghost-light "Hire talent"). **No search box in the hero** (Poolhall is a partner, not a job board). `prefers-reduced-motion` → static first frame, no zoom/fade.
2. **Feature strip** (overlapping the hero, `-46px`): 3 cells (icon + title + line) on a white card.
3. **Featured jobs carousel** — horizontal scroll-snap, cards 380px, gap 20px, prev/next circle buttons BELOW the track. Section head right-slot ghost "View all jobs" → `/jobs/`. Cards from the CPT (featured or newest 4–6). **If sync isn't live, seed 6 sample posts from `data.js` so it's never empty on staging.**
4. **Sectors grid** — 3 photo tiles (Construction/Manufacturing/Digital) with gradient overlay, index, name, "N open roles" bound to CPT counts.
5. **Reviews carousel** (Google reviews) — same mechanics; `.review` cards (gold stars, quote, avatar initials + name + role). Places API, 24h cache; placeholder quotes staging-only.
6. **Paired CTA bands** — candidate (navy, gold edge) + employer (steel) side by side.

### 7.2 Jobs archive `/jobs/` — prototype `screen-jobs.jsx`
Photo page-header. Search strip (keyword / location / sector select / gold Search). Then `grid 280px + 1fr, gap 40px`:
- **Filter sidebar** (sticky, white card): Sector (+counts), Job type (+counts), Working pattern (+counts), Minimum salary (range slider £20k–£80k step 5k, gold accent, live "£Nk+" readout), "Clear filters". Optional secondary filters (Experience, Education) — **off by default**; only add if Matthew wants them.
- **Results:** header = active sector/"All roles" (Archivo) + "N jobs found" + sort select (Most recent / Highest salary / A–Z). **Applied-filter chips** (navy pill + × per active filter, "Clear all"). Cards stacked gap 16px (§11.5). **Pagination** (10–12/page) centred, active = navy fill. **Empty state** (`.empty-state`, dashed border, icon, "No jobs match those filters", widen-search guidance, "Clear all filters" + "Register your CV").
- **Mobile ≤900px:** sidebar hides; "Filters (N)" button opens a right-hand **drawer** (§11.7); footer = "Clear all" + "Show N roles".
Filtering/sorting/counts/chips work server-side (or via existing JS) against the CPT.
**Accept:** every filter narrows results; chips add/remove; count live-updates; 0-result case shows empty state; drawer works at 375px.

### 7.3 Single job `/jobs/{slug}/` — prototype `screen-jobsingle.jsx`
- **Navy header band** with photo: featured gold pill (if featured), sector eyebrow, Archivo title, mono meta row (location/region, type, work pattern, posted) with gold icons.
- **Body:** lead summary, "About the role", "What you'll do" bullets, "What we're looking for" (experience, education only if present, right-to-work line), steel-50 GDPR info box, and a collapsible JSON-LD preview (`<details>`).
- **Sticky apply aside** (top 100px): Archivo salary + "per year", meta rows (Sector/Location/Type/Working/Reference #ID, hairline dividers), **gold "Apply for this role"** (navy text), ghost **"Save job"** bookmark → toggles to "Saved" (client-side/localStorage ok v1), "or call 0121…".
- **Apply opens as a MODAL** over the page (the `/apply/` route is the deep-link/fallback).
- **Expired state** (validThrough passed): warm warning banner ("This role has now closed"), aside CTA → disabled navy "Role closed" + gold "Browse live roles"; **remove JSON-LD**; page stays reachable.
- **Similar roles:** 2-up grid, same sector.
- **JobPosting JSON-LD** on every live job (§9).

### 7.4 Apply `/jobs/{slug}/apply/` + Register `/register/` — prototype `screen-apply.jsx`
Two-col: form card + sticky job-summary aside (Apply) / standalone (Register). Fields: first/last name, email, phone, **CV dropzone** (2px dashed; hover → blue border/blue-50; PDF/DOC/DOCX, **max 25 MB**), optional message, **GDPR consent checkbox**. Full-width gold submit (navy text) with **spinner "Submitting…"** → success panel (green check, "Application sent", "We'll be in touch within 2 working days", browse/home buttons). See §8 for the push contract.

### 7.5 Employers `/employers/` — prototype `screen-employers.jsx`
Order: photo hero ("More than just recruitment.") → **how we help** (4 points) → **delivery options** (5: Temporary/Permanent/Scale/Pay Monthly/On-Site; "See all options" → `/employers/delivery-options/`) → **Bronze/Silver/Gold service tiers** (§11.6) → specialist-services split (Why Us / Bespoke / BJA / HR) → reviews proof strip → full-bleed photo CTA. The **gold BJA callout band** and the **tier table** are the two most build-critical additions.

### 7.6 Better Job Adverts `/better-job-adverts/` — prototype `BetterJobAdverts` in `screen-pages.jsx`
Hero → 6 proposition cards → **"Pricing confirmed when you enquire" pill (NO figure)** → 3 steps → navy CTA band. CTAs point at the employers enquiry anchor.

### 7.7 Sectors `/sectors/` + Candidate hub `/candidates/` — `screen-pages.jsx`
Sector template (Construction worked example): photo header, intro, 4 "why us in this sector" points, live roles in the sector, CTA. Candidate hub: "Find work that fits", 3-step, guides grid, register CTA.

### 7.8 About/Team `/team/` + Contact `/contact/` — `screen-team.jsx`, `screen-contact.jsx`
Team: photo hero, story split with inline stats, team cards (circular portraits, role label, tag pills, bios, LinkedIn/email circle buttons — **real URLs only when supplied**), "join the team" CTA. Contact: navy header, left rail of 3 routes (call / email / visit + hours), map (real Google embed in production), right form with "I'm a…" select (Candidate / Employer / Recruiter joining / Something else), consent, spinner→success. Contact submit → email to Matthew (+ Giig contact endpoint if it exists — verify).

### 7.9 Secondary + Blog + Legal — `screen-pages.jsx`
Why Us / Bespoke Search / HR Services / Commitment: photo header + points grid + in-body photo band + CTA bands. Blog index (cards) + **blog post template** (hero, meta, body, share). Legal template (Privacy/Terms/Cookies: title, intro, "updated" date, numbered sections). **Cookie consent banner** (privacy-friendly default).

### 7.10 Candidate portal `/candidate/*` — prototype `Portal` in `screen-pages.jsx`
Six screens, **all noindex + gated:** login, register, verify-email, forgot-password, reset-password (centred ~460px auth card: logo, Archivo title, helper sub, cross-links) + account security (authed stack: change-password card + active-sessions list with current-device badge + sign-out-all). The prototype's grey tab strip is preview-only — **do not build it.** *(Note: candidate accounts are listed out-of-scope for v1 in the original brief — confirm with Matthew whether the portal ships in v1 or is staged for later. The visual spec is ready either way.)*

---

## 8. Application push to Giig (§4.4 of the brief)

- **One application form for all jobs** (per-job custom questions = future add-on).
- **Fields:** name, email, phone, CV upload, optional message, **consent checkbox**.
- **CV upload:** PDF + Word, **max 25 MB**. Server-side type + size validation. (Ensure PHP `upload_max_filesize` / `post_max_size` and any host/CDN limits allow 25 MB.)
- **Spam:** reCAPTCHA + honeypot + rate limit.
- **On submit (TWO-STEP write — see §2.2):** (1) `POST /candidate` (FirstName, LastName, EmailAddress, PhoneNumber, RoleTitle=job title, Source="Website", Notes=cover message) → capture `CandidateId`; (2) `POST /applicant/submit` (CandidateId, JobId=`giig_job_id`, Salary). The adapter's `submit_application()` wraps both steps behind the one interface call.
- **CV handling (⚠️ no Giig upload endpoint — see §2.2 critical gap):** store the CV in the WordPress media library (private) + email it to Matthew, and reference it in the candidate `Notes`, **until Giig confirms a CV-upload path.** Keep the CV in the local queue regardless so it is never lost.
- **Confirmation page** + notification email to **matthew@poolhallrecruitment.co.uk** (admin-editable).
- **Failure safety net:** if the API call fails, **queue the application locally and retry**, with an admin alert. **Never silently drop.**
- **GDPR:** explicit consent; minimal local PII; honour deletion requests.
**Accept:** valid submit creates the Giig candidate + applicant (CV stored locally/emailed per §2.2); invalid input shows inline errors (§11.4); forced API failure → queued + alert, candidate still sees success; rate-limit + honeypot block spam.

---

## 9. Google for Jobs (§4.5 of the brief)

- **Valid `JobPosting` JSON-LD on every live single-job page:** title, description, datePosted, **validThrough = datePosted + 30d**, hiringOrganization (Poolhall), jobLocation **or** `jobLocationType: TELECOMMUTE`, employmentType, baseSalary, plus experienceRequirements/educationRequirements when useful.
- **Region/multi-location jobs** ("UK", "West Midlands"): `addressRegion` fallback.
- **Expired jobs:** remove JSON-LD (do not advertise closed roles).
- **Missing salary:** omit `baseSalary` rather than emit invalid markup.
- Jobs in the **XML sitemap**; indexable, stable URLs.
- **Validate every variant** in Google's Rich Results Test.
- **Set up Google Search Console** for the domain (create/connect — client unsure if one exists).
- The current Giig widget is `noindex,nofollow`, which is why jobs can't appear in Google's jobs box today — the whole point of moving onto Poolhall's own indexable URLs.

---

## 10. Google reviews (§4.8)

- Pull reviews from Poolhall's Google Business Profile via the **Places API**.
- **Server-cache ~24h** (cron `poolhall_refresh_reviews`) to protect quota.
- Render as the sideways carousel (matches featured jobs).
- **Needs:** Places API key (free tier fine at this volume) + Poolhall's **Place ID**.
- Until the key exists: placeholder quotes from `data.js` **staging only**.

---

## 11. Visual system reference (v2 "Engineered")

Single source of truth: `colors_and_type.css`. The prototype `kit.css` mirrors these into its own `:root`.

### 11.1 Palette (sampled from the PRL logo)
- **Navy / slate (ground, headings, dark bands):** `--slate-900 #0B2846` (brand navy), 950 `#06182B`, 800 `#123255`, 700 `#1B4068`, 600 `#2A5078`.
- **Gold (action + accent):** `--hivis-500 #FDBB5D` (brand gold, fills), 600 `#E0A33F` (hover/press), 400 `#FECF87`, 200 `#FBE4BC`, 100 `#FCEFD7`, 50 `#FEF8EC`. **`--gold-ink #8A5E12`** = readable gold for small accent TEXT on white. **`--on-accent` = navy** = text/icon colour ON gold fills (gold buttons carry navy text, like the logo).
- **Steel (secondary + employer cue):** 700 `#2C566F`, 600 `#345D79`, 500 `#3E6E8E`, 400 `#5E8AA6`, 200 `#AFC8D6`, 100 `#D7E3EA`, 50 `#EDF3F6`.
- **Neutrals:** concrete-50 `#F7F8FA`, 100 `#F2F4F6`, 200 `#E6E9ED` (borders), 300 `#D4DAE0` (strong borders); ink-900 `#11161B`; mist-400 `#8893A0`, 500 `#5B6670`, 600 `#4A555F`.
- **Status:** success `#1E7A52`/`#E7F3EC`, warning `#B5740C`/`#FBF1DD`, error `#C23A2B`/`#FBEAE7`, info = steel-600.

### 11.2 Type
- **Display/headings:** Archivo — display/H1/H2 weight **800**, H3/card titles **700**, section labels **600**. Tight tracking on display (~-0.02em).
- **Body:** Source Sans 3, 400/500/600, line-height 1.6, body ~17px.
- **Job meta / reference IDs / "// " labels:** IBM Plex Mono, uppercase, .1–.16em tracking. This mono "data" treatment is the engineered signature — use it for job meta rows, reference numbers, eyebrow labels.

### 11.3 Shape, elevation, motion
- **Radii (near-square, engineered):** `--r-sm 3px`, `--r 4px`, `--r-lg 6px`. Pills only for chips/badges/avatars.
- **Shadows:** subtle, navy-tinted, low spread (`--shadow-1/2/3`). Prefer **structural rules, grid lines, corner registration ticks** over heavy shadow.
- **Motion:** 0.15–0.2s ease on colour/shadow/transform; card hover lift `translateY(-3px)`; button press `translateY(1px)`; hero ken-burns ~4s. `prefers-reduced-motion` honoured everywhere.
- **Focus:** visible **steel ring** `0 0 0 3px rgba(62,110,142,.4)` for WCAG 2.2 AA.
- **Container** 1240px. Section rhythm ~88px desktop (tight ~60px).

### 11.4 Buttons & forms
- **Primary** (gold): `background var(--accent)`, **`color var(--on-accent)` (navy)**, hover `--accent-press`. **Navy:** navy bg, white text. **Steel.** **Ghost:** transparent, 1.5px strong border, navy text, hover white bg + navy border. **Ghost-light** (on navy): translucent white. lg = 16–17px / 26–30px padding. Submits get a spinner state.
- **Inputs:** 1.5px `--border-strong`, radius 3px, 13px/15px padding; focus = blue/steel border + focus ring; labels Archivo 13px 600; required asterisk uses **`--accent-ink`** (deep gold). Error = red border + 11.5px mono red hint (`.field-err`, `--error-600`).

### 11.5 Job card (`.jobcard`)
White; 1px border; radius `--r`; subtle shadow; hover lift + left gold rule grows; **featured = full gold left rule + gold "FEATURED" mono label (deep-gold `--accent-ink`)**. Anatomy: sector tag (mono, sector-tinted) → Archivo title → mono meta row (location/type/work/posted) → 2-line clamped summary → hairline → footer: Archivo salary "£60,000–£75,000 / year" left, deep-gold "View job →" right, mono reference right-meta.

### 11.6 Service tiers (`.pricing` / `.tier`) — NEW
3-up grid of `.tier` cards (`.bronze` / `.silver` / `.gold.feat`):
- **Header:** metallic badge dot + tier name (mono), Archivo product name (Bronze=**Advertise**, Silver=**Recruit**, Gold=**Search**), one-line sub, then `.tier-price`: big Archivo "12% / 15% / 20%" + mono "of first-year salary".
- **Feature list** (`.tier-feats`): green-check (`--success-600`) included items + grey-× (`--mist-300`) excluded items; "Everything in X, plus:" rows use `.head` (bold, no icon).
- **Gold tier** = `.feat`: gold border + "Most popular" gold corner ribbon (navy text); its CTA is the gold primary. Bronze CTA = ghost, Silver CTA = steel.
- Below the grid: `.pricing-note` (mono) — **the indicative-pricing disclaimer (guardrail #2).**
Tier inclusions (current copy): Bronze = branded advert, job-board posting, CV sift, shortlist of 5, 30-day rebate. Silver = + phone/competency screening, network search, managed scheduling, structured shortlist, 60-day rebate. Gold = + proactive headhunting, reference/RTW checks, dedicated manager, market & salary insight, onboarding/aftercare, 90-day guarantee, priority response.

### 11.7 Drawer (filters + mobile nav)
Fixed right, min(380px, 92vw), white, shadow, ~220ms slide; overlay `rgba(11,22,38,.55)`; head = title + circle ×; footer = side-by-side buttons. Mobile nav drawer: nav links (chevrons, hairlines) → "I'M A…" candidate/employer segmented switch → "YOUR ACCOUNT" sign-in link → full-width gold "Browse live jobs".

---

## 12. Edge cases & scenarios matrix

| Scenario | Required behaviour |
|---|---|
| Giig API down during sync | Keep existing CPT cache; log + admin alert; pages unaffected. |
| Job removed from feed | Unpublish; URL returns 410 (or 301 to `/jobs/`); drop from sitemap + JSON-LD. |
| Job older than 30 days | Auto-expire; expired single-job state (§7.3); JSON-LD removed. |
| 0 results on `/jobs/` | Empty state (§7.2), never a blank list. |
| Featured section but no featured jobs | Fall back to newest 6; if CPT empty on staging, seed from `data.js`. |
| Salary missing/blank | Hide salary line; omit `baseSalary` from schema. |
| Region-only / "UK" location | `addressRegion` schema fallback; display the region string. |
| Fully Remote job | `jobLocationType: TELECOMMUTE` in schema. |
| Application API failure | Queue locally + retry + admin alert; candidate sees success. Two-step write (§2.2): if `/candidate` succeeds but `/applicant/submit` fails, queue **step 2 only** with the captured `CandidateId` (don't recreate the candidate on retry). |
| Oversized/invalid CV | Inline error before submit; never reaches the API. |
| Reviews API quota/empty | Serve last cached set; never error the homepage. |
| Cookie consent declined | No analytics cookies set; essential only. |
| JS disabled | Core content + links work; hero shows a static frame; forms degrade to standard POST. |
| 404 / search | Branded 404 with search + popular links (compose from existing patterns). |

---

## 13. SEO, performance, accessibility

- **SEO:** clean indexable URLs; per-page titles/meta; Open Graph; XML sitemap incl. jobs; canonical tags; JobPosting + Organization + BreadcrumbList schema; connect Search Console.
- **Performance / Core Web Vitals:** cached CPT (no live API on render); lazy-load below-fold images; serve real photos as compressed WebP/AVIF with width-appropriate sizes (the prototype base64-embeds photos for offline preview — in production use proper optimised image files, not base64); preconnect fonts; defer non-critical JS.
- **Accessibility (WCAG 2.2 AA target):** visible focus rings; labels on every input; alt text; colour contrast on navy bands and on gold fills (gold uses navy text precisely for this); 44px+ tap targets; reduced-motion; keyboard-operable carousels, drawers and the apply modal (focus trap + Esc).

---

## 14. Asset manifest & extraction (from this design-system repo)

| Need | Where | How |
|---|---|---|
| Logo lockup (header/footer) | `assets/poolhall-logo-lockup.png` | **Confirmed as the brand logo** (the navy/gold PRL lockup the client supplied). Use it. Source is 153px and a little soft at large sizes — a vector/≥1000px version would sharpen it, but it is the correct mark. |
| Logo badge | `assets/poolhall-logo.png` | Alt mark. |
| Real photos (office/team/story) | `ui_kits/website/images.js` (`PH_PHOTO.*`) + `sector-photos.js` (`PH_SECTOR_IMG.*`) | Base64 in the prototype for offline preview. **Decode to optimised image files** for the theme. |
| Sector / hero / blog imagery | `data.js` (`PH_IMG.*`) | Currently Unsplash placeholders — **replace with Poolhall's own photography** (still outstanding). |
| Accreditation logos | `images.js` (`PH_PHOTO.accred*`) | Export to PNG/SVG chips for the footer strip. |
| All tokens | `colors_and_type.css` | The single source of truth; mirror into the theme's CSS variables. |
| Reusable components | `components/Button`, `components/JobCard` (+ `.d.ts`) | Reference implementations of the two core components. |
| Live visual contract | `poolhall-website.html` (bundled) / `deploy/index.html` | Open in a browser to compare pixel output while building. |

---

## 15. Acceptance checklist (run before "done")

**Integration & data**
- [ ] Adapter implements `JobSource`; all Giig specifics isolated; future-ATS swap = adapter only
- [ ] Cron sync every 2–4h: upsert, unpublish-missing, 30-day auto-expire, manual "Sync now", sync log
- [ ] Failed/empty fetch never wipes cache; admin alert fires
- [ ] Application push: candidate + applicant created in Giig (two-step §2.2); CV stored locally/emailed (no Giig upload endpoint); failure queues + retries + alerts; notification email to Matthew
- [ ] reCAPTCHA + honeypot + rate limit active; CV type/size enforced server-side (PDF/DOC/DOCX ≤ 25 MB); GDPR consent stored

**Google for Jobs & SEO**
- [ ] Valid JobPosting JSON-LD on every live job (Rich Results Test passes), incl. remote/region/no-salary variants
- [ ] Expired jobs drop JSON-LD; jobs in sitemap; Search Console connected

**Pages & journeys**
- [ ] Footer rebuilt site-wide: 4 columns, socials, accreditation chips, company/VAT/address
- [ ] Home: four-stage hero (+reduced-motion), featured carousel populated, sectors counts live, reviews carousel, paired CTA bands
- [ ] `/jobs/`: filters + sort + chips + clear-all + live counts + pagination + empty state + ≤900px filter drawer
- [ ] Single job: full layout, sticky aside, save-job toggle, apply MODAL, expired state, similar roles
- [ ] Apply/Register: dropzone, inline validation, spinner, success
- [ ] `/employers/`: Bronze/Silver/Gold tier table (green checks, "Most popular" Gold, indicative-price note), 5 delivery options, gold BJA callout band, enquiry form
- [ ] `/better-job-adverts/`: full page, NO price anywhere
- [ ] Sectors, Candidate hub, Team, Contact (3 routes), Why Us/Bespoke/HR/Commitment, Blog index + post, Legal templates
- [ ] Portal (if in v1): 6 screens, styled, noindexed, gated

**System**
- [ ] Headings Archivo (800/700/600), body Source Sans 3, job meta IBM Plex Mono; **no serif**
- [ ] Gold fills use navy text; small gold accents on white use `--accent-ink`; status tokens resolve
- [ ] No em-dashes in any rendered copy; British English; sentence case; no emoji
- [ ] Mobile 375px pass every page (burger drawer, stacked grids, 44px+ targets)
- [ ] WCAG 2.2 AA spot-check: focus rings, contrast, labels, reduced-motion, keyboard carousels/drawer/modal
- [ ] Cookie consent banner; privacy-friendly analytics default

---

## 16. Open questions for Matthew (resolve before / during build)
1. **Real service-tier fees** (Bronze/Silver/Gold) — confirm % or fixed £, and whether to show them publicly at all.
2. **Better Job Adverts price** — still withheld? (Currently never shown.)
3. **Combined-experience figure** — 30yrs vs 50yrs (prototype/old docs disagree).
4. **Candidate portal in v1?** (Brief lists accounts as out-of-scope v1; visuals are ready.)
5. **Giig Plus API surface** — base URL, endpoints, token/secret presentation, rate limits, contact endpoint (auth credentials already provided, §2.1).
6. **Secondary filters** (Experience, Education) on `/jobs/` — on or off?
7. **Places API key + Place ID** for reviews; **Search Console** account.
8. **Real photography** (§14) — still outstanding (logo now supplied).
9. **Team contact details** (emails/LinkedIn) for the team cards.

**Resolved:** CV max size = **25 MB** · logo = **the supplied PRL lockup** · Giig **auth credentials + company id [REDACTED:GIIG_COMPANY_ID]** + **full endpoint surface** mapped (§2.2). **New blocker surfaced:** Giig has **no CV-upload endpoint** (§2.2) and the **real API base URL** is still a placeholder — confirm both with Giig.
