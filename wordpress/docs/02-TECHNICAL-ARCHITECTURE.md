# Technical Architecture

## 1. Platform

- WordPress current stable release at build time.
- PHP 8.2 or newer, subject to host/plugin compatibility.
- MySQL 8 or compatible managed database.
- Hello Elementor child theme for minimal shared CSS, assets and fallbacks.
- Elementor Pro Theme Builder for presentation and editable content.
- Elementor 4 Atomic elements/global classes where stable, with classic widgets
  retained where required.
- Elementor v3/classic Loop Grid, Loop Carousel and Loop Items for dynamic
  WordPress-query-backed collections.
- Novamira connected to local/staging only.
- One custom plugin for ATS, jobs, applications, candidate accounts, reviews,
  schema, admin tools and controlled Elementor dynamic tags/widgets.
- Git repository contains the child theme, custom plugin, deployment
  configuration and tested Elementor export artifacts, not WordPress core or
  uploaded secrets.
- Managed hosting with staging, daily backups, SSL, real cron and deploy rollback.

Do not hard-code a future WordPress version number. Test against the actual
staging version before launch.

## 2. Ownership boundaries

### Hello Elementor child theme

- Font files and shared assets
- Minimal shared CSS not reliably represented in Site Settings
- Accessibility/no-JavaScript fallbacks
- Theme compatibility hooks

### Elementor

- Site Settings and global classes
- Global navigation and footer
- Marketing page layouts and editable content
- Theme Builder blog templates
- Job archive/single/apply presentation shells
- Template display conditions

### Poolhall custom plugin

- Job CPT and taxonomies
- Giig adapter and sync
- Application adapter and queue
- Reviews API/cache
- JobPosting schema
- Jobs sitemap integration
- Indexing API queue
- Admin health/log screens
- Retention cleanup
- Candidate identity, verification, profile and privacy services
- Saved jobs, job alerts and application-history services
- Custom Elementor dynamic tags/widgets for job, application and review data

Elementor and the child theme may call plugin presentation APIs but must not
contain Giig-specific request logic, credentials or application rules.

## 2A. Elementor data strategy

- Elementor Site Settings are the visual-token source of truth.
- `10-ELEMENTOR-DESIGN-SYSTEM.md` is the class/component/layout contract.
- Use named global classes for repeated components and layout patterns.
- Prefer Atomic elements where stable; allow classic widgets in the same build
  where Atomic controls or integrations are incomplete.
- Use custom plugin widgets where dynamic data, secure submission or complex
  querying is required.
- Avoid page-level custom CSS. Shared CSS belongs in the child theme.
- Functional JavaScript belongs in version control, not arbitrary HTML widgets.
- Elementor Forms may render low-risk contact forms only when processing is
  delegated to plugin handlers. Applications always use the custom widget.
- Editors may change layout and content but cannot access credentials,
  operational logs or candidate PII.
- Sync v4 Variables to v3 Global Colors/Fonts so Loop Items do not carry copied
  raw brand values.
- Private candidate collections never use Loop Grid; they remain authorized
  plugin widgets.

## 3. Data model

### Job custom post type

Post type: `poolhall_job`

Taxonomies:

- `poolhall_sector`
- `poolhall_job_type`
- `poolhall_work_mode`
- `poolhall_location`

Required metadata:

- `source`
- `source_job_id`
- `source_company_id`
- `source_url`
- `source_payload_hash`
- `source_modified_at`
- `source_status`
- `job_reference`
- `salary_display`
- `salary_currency`
- `salary_min`
- `salary_max`
- `salary_period`
- `location_display`
- `address_locality`
- `address_region`
- `address_country`
- `work_mode_raw`
- `experience_requirement`
- `education_requirement`
- `date_posted`
- `expires_at`
- `expiry_override_at`
- `is_featured`
- `schema_hiring_org_name`
- `schema_hiring_org_url`
- `schema_hiring_org_logo`
- `last_synced_at`

Use one unique database constraint or guarded lookup for
`source + source_job_id`.

## 3A. Candidate portal data

Identity uses native WordPress users with a dedicated `poolhall_candidate` role.
Candidates have no WordPress admin capability and are redirected away from
`/wp-admin/`.

- Generate a non-email internal `user_login`/`user_nicename`; login still uses
  email.
- Exclude candidates from public author archives, author sitemaps and public
  user REST responses.
- Create all custom tables through `$wpdb->prefix` and versioned migrations.

Use user meta only for low-volume profile fields:

- first and last name
- phone
- town/city
- postcode district, not full address unless operationally required
- current job title
- preferred sectors, locations, work modes and job types
- salary preference
- profile completion timestamp
- email verification timestamp
- terms/privacy/alerts consent versions and timestamps
- Giig candidate ID only when returned through a supported API

Use versioned custom tables for relational/private activity:

- `poolhall_saved_jobs`
  - `user_id`, `job_post_id`, `source_job_id`, `saved_at`
  - unique key on `user_id + source_job_id`
- `poolhall_job_alerts`
  - typed search criteria, frequency, active state, next/last run and send state
- `poolhall_alert_deliveries`
  - alert/job/message idempotency key and delivery result
- `poolhall_application_history`
  - user, job/source IDs, mode, evidence-backed state, timestamps and minimal
    immutable job snapshot
- `poolhall_candidate_documents`
  - encrypted private path/key reference, original safe filename, MIME, size,
    checksum and lifecycle timestamps
- `poolhall_candidate_audit`
  - security/privacy events without passwords, reset tokens or CV contents
- `poolhall_candidate_mail`
  - template, recipient user/reference, send-after, idempotency key, attempts,
    provider message ID and redacted result
- `poolhall_email_suppressions`
  - normalized address hash, reason, source and lifecycle timestamps

Store verification, reset, claim and unsubscribe tokens only as hashes. Tokens
are single-purpose, expire and become invalid after use.

Index candidate tables by `user_id`, lifecycle/status timestamps and stable
source job ID. Enforce uniqueness for save and delivery idempotency keys in the
database, not only in PHP.

The detailed product and state contract is in
`08-CANDIDATE-PORTAL-SPEC.md`.

## 4. ATS abstraction

Interfaces:

```php
interface JobSource {
    public function fetchOpenJobs(JobSyncCursor $cursor): JobPage;
    public function fetchJob(string $sourceJobId): SourceJob;
}

interface ApplicationSink {
    public function capabilities(): ApplicationCapabilities;
    public function submit(ApplicationPayload $payload): ApplicationResult;
}
```

No public template, block or form handler should know Giig endpoint details.

## 5. Giig integration

Official documentation reviewed on 2026-06-10 shows:

- Bearer API token
- A second access/session header
- `GET /public/api/v1/job/getjobs?max={n}`
- `GET /public/api/v1/job/get?JobId={id}&CompanyId={id}`
- `POST /public/api/v1/candidate`
- `POST /public/api/v1/applicant/submit`

The documentation is inconsistent about the second header:

- Intro text names `Access-Session-Type`.
- Endpoint tables use `Access-Secret-Key`.

Phase 1 must verify the exact production contract before plugin implementation.

### Job sync algorithm

1. Acquire a short-lived sync lock.
2. Fetch all public jobs with pagination/maximum handling.
3. Validate and normalize each payload.
4. Upsert by `source_job_id`.
5. Update only when the normalized payload hash changes.
6. Record IDs present in the successful source response.
7. Unpublish local jobs missing from a complete successful response.
8. Apply local expiry rules.
9. Queue Google URL update/delete notifications.
10. Write one summarized sync log.
11. Release the lock.

If the source fetch fails or appears incomplete, do not expire any jobs.

### Scheduling

- Real cron every four hours.
- WP-Cron fallback only.
- Manual `Sync now` action with capability and nonce checks.
- Prevent overlapping syncs.
- Alert after three consecutive failures.

### Expiry

- Default expiry: Giig `datePosted + 30 days`, reflecting the client decision in
  the original brief.
- Warn staff 72 hours before local expiry when Giig still reports the job live.
- Allow a 7, 14 or 30-day admin extension.
- When expired: unpublish, remove schema and notify Indexing API.
- A later Giig update must not silently overwrite an explicit admin expiry
  override.

## 6. Application modes

### Mode A - supported API mode

Enable only after Giig proves a supported CV upload mechanism.

Flow:

1. Validate nonce, Turnstile, rate limit and fields.
2. Validate CV extension, MIME and size.
3. Create/update candidate.
4. Upload CV using the supported Giig route.
5. Submit candidate against the Giig job ID.
6. Send confirmation and configured notification.
7. Delete temporary local files.

Set `directApply: true` only when the whole application can be completed in one
first-party flow.

For a signed-in candidate, attach the successful local history row to the
WordPress user. For a guest, issue a short-lived claim token to the application
email; attach history only after that email is verified.

### Mode B - hosted Giig mode

Default fallback if CV upload cannot be proven.

- Apply CTA links to the exact Giig hosted job URL.
- Do not show a first-party upload form.
- Do not store application-form PII or a CV solely because the candidate was
  redirected. Separately consented account/profile data remains in WordPress.
- Set `directApply: false`.
- Track an outbound Apply click as an analytics event only after consent.
- Logged-in outbound activity may be stored as `redirected`, but must not appear
  as a completed application or ATS status.

### Unsupported approach

Do not proxy the hosted multipart form at
`https://careers.giighire.com/Submit`. It depends on hidden session/token fields
and is not documented as a supported public integration contract.

## 7. Failure-safe application queue

Used only in Mode A.

- Persist before or atomically with the outbound attempt.
- Encrypt PII and files using a key stored outside the database.
- Store files outside the public uploads path.
- Retry with exponential backoff.
- Alert staff immediately after the first permanent-looking failure.
- Maximum automated retry window: 24 hours.
- Keep failed records for at most 7 days unless manually resolved.
- Delete successful queue payloads immediately.
- Logs contain correlation IDs, not CV contents or full personal details.
- Admin actions: retry, mark resolved, securely delete.

## 8. Search implementation

- Use `WP_Query` with indexed meta/taxonomy queries suitable for the expected
  volume.
- Parse all request parameters through a typed query object.
- Render initial results server-side.
- Add an AJAX/REST enhancement only if it preserves URL/history state.
- Escape output and whitelist sort keys.
- Do not query Giig during page requests.

## 8A. Candidate authentication and authorization

- Use native WordPress password hashing, secure auth cookies and session tokens.
- Use cookie-authenticated REST/AJAX requests with WordPress REST nonces.
- Nonces protect CSRF but never replace `current_user_can()` and ownership
  checks.
- Login accepts email, not public usernames.
- Registration requires email verification before dashboard features activate.
- Generic responses prevent login, registration and reset account enumeration.
- Rate-limit registration, login, verification resend, reset and sensitive
  actions; use Turnstile after risk thresholds or from the first anonymous
  submission where agreed.
- Rotate/invalidate sessions after password or email changes and account
  recovery.
- Require current-password reauthentication for email change, password change,
  CV deletion, session revocation and account deletion.
- Return URLs are signed and restricted to same-origin allowlisted routes.
- Private endpoints check both authentication and resource ownership.
- Candidate pages are `noindex,nofollow`, excluded from sitemaps and bypass all
  shared page/CDN caches.

## 8B. Saved jobs, alerts and recommendations

- Saved jobs always reference the local canonical job and source job ID.
- Expired saved jobs remain visible with an expired label until removed, but
  cannot be applied to.
- Alert criteria use the same typed query object as `/jobs/`.
- Delivery is batched through real cron with idempotency keys, retry limits and
  authenticated email delivery.
- Every alert supports pause, edit, delete, per-alert unsubscribe and
  unsubscribe-all.
- Recommendations are deterministic from explicit candidate preferences,
  saved-job attributes and recent application sectors; no AI profiling.
- Never include expired jobs, jobs already applied to or jobs the candidate has
  explicitly dismissed in the current recommendation set.

## 8C. Candidate CV

- Enable reusable CV storage only in supported application Mode A.
- Allow one active CV; replacing it securely deletes the prior file after the
  new file is validated and committed.
- Encrypt at rest with a key outside the database.
- Store outside public uploads and serve only through an authorized,
  short-lived download response.
- Validate extension, MIME, size and malware scan where available.
- Never expose a filesystem path or predictable attachment URL.
- Successful Giig submission does not retain an extra application-copy CV.
- Candidate deletion securely removes the reusable CV and queued copies, subject
  to confirmed legal retention obligations.

## 8D. Candidate email delivery

- Send through the authenticated transactional provider, never unauthenticated
  PHP mail in production.
- Queue emails with database idempotency keys and a real-cron worker.
- Verification, reset and security emails are high priority; alerts are batched
  separately so a large digest cannot delay account recovery.
- Store template identifiers and minimal substitution data, not rendered CV or
  application contents.
- Retry transient failures with bounded exponential backoff.
- Process provider bounce/complaint callbacks through signed webhooks where
  available.
- Pause alerts after a hard bounce or complaint and surface a safe dashboard
  message after the candidate next authenticates.
- Never suppress password/security messages solely because marketing or alerts
  were unsubscribed.
- Monitor queue age, failure rate and provider response without logging complete
  email bodies or reset/verification links.

## 9. Google Jobs

Official Google guidance reviewed on 2026-06-10 confirms:

- JobPosting markup belongs on individual job pages.
- Job pages must be crawlable and canonical.
- Indexing API is recommended for job update/removal notifications.
- A sitemap is still recommended for site-wide coverage.
- Closed jobs must be expired, removed or lose JobPosting markup promptly.

### Schema rules

Required/used fields:

- `@context`
- `@type`
- `title`
- `description`
- `identifier`
- `datePosted`
- `validThrough`
- `employmentType`
- `hiringOrganization`
- `jobLocation` and/or remote fields
- `baseSalary` when reliable
- `directApply`

Optional source fields:

- `educationRequirements`
- `experienceRequirements`

### Hiring organization

For permanent placements, use the actual end employer only when supplied and
approved for publication. For temporary workers employed by Poolhall, Poolhall
may be the hiring organization.

If an eligible hiring organization cannot be represented accurately, omit
JobPosting schema for that role and show an admin warning. Do not publish
misleading organization data just to pass validation.

### Work-mode mapping

- Onsite: physical `jobLocation`.
- Part remote/hybrid: physical `jobLocation`, plus remote fields only when the
  role genuinely allows remote work and applicant geography is known.
- Fully remote: `jobLocationType: TELECOMMUTE` and
  `applicantLocationRequirements`.

### Indexing API

- Service account credentials in secret storage.
- Queue URL_UPDATED after publish/material update.
- Queue URL_DELETED after expiry/removal.
- Retry transient errors.
- Log response status and quota errors.
- Do not block a job save on an Indexing API failure.

## 10. Google Reviews

Use Places API (New), server-side only.

Request the minimum required field mask:

- `displayName`
- `rating`
- `userRatingCount`
- `reviews`
- `attributions`
- `googleMapsUri`

Current Places rules:

- A Place response can contain up to five reviews.
- Reviews are sorted by relevance in the new API.
- Reviewer name must be shown close to the review.
- Include profile/photo links when available.
- Display required Google/third-party attribution.

Caching:

- Refresh every 24 hours.
- Serve stale cache for up to 7 days during API failure.
- If there is no valid cache, hide the carousel and keep the layout intact.
- Admin health panel shows last refresh and last error.

Do not hard-code prototype review text as if it came from Google.

## 11. Forms

Use Cloudflare Turnstile, honeypot and server-side rate limiting.

- CSRF nonce
- Server-side validation
- Generic public error messages
- Structured internal logs
- Email delivery through authenticated SMTP/provider
- Idempotency token to stop accidental duplicate submissions
- Success pages are `noindex`

## 12. Security and privacy

- Secrets only in environment or host secret storage.
- Enforce HTTPS.
- Sanitize on input and escape on output.
- Allowlist HTML from Giig with `wp_kses`.
- Restrict SVG uploads.
- Validate real MIME type for CVs.
- Malware scan CVs when the host provides a scanner.
- No real candidate records or CVs on local/staging.
- Least-privilege WordPress roles and capabilities.
- Redact PII from logs and analytics.
- Consent-aware GA4.
- Configurable retention cleanup job.
- Default local application retention setting: 12 months, but client/legal
  confirmation is required before launch.
- Candidate profile/CV/account retention and deletion behavior must be approved
  in the privacy notice before launch.
- A WordPress account deletion cannot claim to erase Giig-held data; send or
  queue a separate supported Giig/privacy request and explain that boundary.

## 13. Admin experience

One `Poolhall Jobs` admin area:

- Health summary
- Last successful sync
- Next scheduled sync
- Created/updated/expired counts
- Consecutive failures
- Application mode and capability status
- Reviews cache status
- Indexing API queue status
- Recent redacted errors
- Sync now
- Retry failed application
- Extend job expiry
- Candidate-account health: verified/unverified counts and failed mail summary
- Candidate support actions: resend verification, suspend/reactivate and
  initiate a password reset without setting or viewing a password
- Alert queue status and recent redacted failures
- Privacy export/deletion queue status

Staff cannot impersonate candidates, view passwords, retrieve reusable CVs by
default or edit application history into unsupported ATS stages.

## 14. Observability

Use structured log events with correlation IDs:

- `job_sync_started`
- `job_sync_completed`
- `job_sync_failed`
- `job_expiry_warning`
- `job_expired`
- `application_received`
- `application_submitted`
- `application_queued`
- `application_failed`
- `reviews_refresh_failed`
- `indexing_notification_failed`
- `candidate_registered`
- `candidate_verified`
- `candidate_login_failed`
- `candidate_security_changed`
- `job_saved`
- `job_alert_sent`
- `job_alert_failed`
- `candidate_export_requested`
- `candidate_deletion_requested`

Send operational email only when action is needed. Do not email on every
successful sync.

## 15. Performance

- Page cache public pages.
- Explicitly bypass page/CDN cache for all candidate/auth routes and responses
  containing candidate state.
- Object cache if available.
- Local jobs only at render time.
- Responsive images in WebP/AVIF with original fallback.
- Self-host or efficiently load the two required font families.
- Lazy-load below-the-fold images.
- Do not load Places or Giig scripts in the browser.
- Target LCP under 2.5s and CLS under 0.1 at the 75th percentile.

## 16. Deployment

- Local -> staging -> production.
- Database migrations/version upgrades in the plugin.
- Deploy child-theme/plugin code through Git/host deploy.
- Move Elementor content through a tested, serialization-safe database
  migration or Elementor export/import process.
- Regenerate Elementor CSS/data after migration and verify editor/frontend
  parity.
- Novamira is local/staging-only and must never be connected to production.
- Back up files and database before cutover.
- Keep DNS rollback values.
- Remove development users, connectors, tokens and temporary Elementor backups
  before production.

## 17. Novamira and Elementor operations

Follow `07-NOVAMIRA-ELEMENTOR-WORKFLOW.md` for every template/data edit:

- Identify and inspect the exact page/template/element IDs.
- Back up `_elementor_data` before modification.
- Make a minimal edit and audit shared-class impact.
- Regenerate Elementor CSS/data and clear element, CSS, page-asset and WordPress
  post caches.
- Purge page/object/CDN caches.
- Verify the rendered frontend at desktop and mobile widths.

An Elementor database change is not complete until the frontend and editor
render the same approved result.

## 18. Security verification and penetration testing

- Use OWASP ASVS 5.0.0 Level 2 as the verification baseline.
- Cover the OWASP Web Security Testing Guide, OWASP Top 10:2025 and API
  Security Top 10:2023.
- Run automated security checks during development, but do not treat scanners
  as a substitute for manual independent testing.
- Commission an independent tester after the staging release candidate is
  feature-complete and internally hardened.
- Test a production-like staging environment with the same caching, security
  headers, storage model, cron, mail integration and role configuration.
- Perform only explicitly authorised, low-impact production verification after
  deployment.
- Follow the full rules, test cases, evidence standard and release gate in
  `09-PENETRATION-TEST-PLAN.md`.
