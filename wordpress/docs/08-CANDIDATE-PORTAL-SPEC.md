# Candidate Portal Product and Build Specification

This file extends the one-shot build with a secure, frontend-only candidate
account area. The portal must look and behave like Poolhall, not WordPress
admin, and must never imply that Poolhall knows an ATS status it cannot prove.

## 1. Product principles

- Browsing and applying must remain possible without an account.
- Saving jobs, creating alerts and viewing a dashboard require a verified
  account.
- Registration is lightweight; profile completion is progressive.
- Candidate identity belongs to WordPress. Giig remains an application
  destination and optional external candidate record.
- Every status shown to a candidate has an evidence source.
- Store the minimum candidate data required for the feature.
- Private content is never indexed, publicly cached or exposed through
  predictable file URLs.

## 2. Routes and access

### Public account routes

- `/candidate/login/`
- `/candidate/register/`
- `/candidate/verify-email/`
- `/candidate/forgot-password/`
- `/candidate/reset-password/`

Authenticated users visiting login/register are redirected to the dashboard or
a valid signed return URL.

### Verified candidate routes

- `/candidate/`
- `/candidate/saved-jobs/`
- `/candidate/applications/`
- `/candidate/job-alerts/`
- `/candidate/profile/`
- `/candidate/security/`
- `/candidate/privacy/`

Unverified users see a limited verification-required screen with resend and
sign-out actions. Signed-out users are redirected to login with a same-origin
return URL. Candidate routes return `noindex,nofollow`, are absent from
sitemaps and bypass shared caches.

## 3. Candidate role

Create `poolhall_candidate` with only the capabilities required for its own
frontend records.

- No post, page, media, plugin, theme or settings capabilities.
- No access to `/wp-admin/` or the admin toolbar.
- No ability to list users or retrieve another candidate's data.
- Every request checks authentication, role/capability and resource ownership.

Administrators and approved recruitment staff use separate capabilities for
support tools. Staff cannot impersonate a candidate or view a password.

## 4. Registration

Fields:

- First name
- Last name
- Email
- Password
- Required acceptance of Terms and Privacy Notice
- Optional job-alert/product email consent, separate and unticked

Behavior:

1. Validate and normalize email.
2. Enforce the password policy and show a strength/help indicator.
3. Apply rate limiting, honeypot and Turnstile policy.
4. Create an unverified candidate account.
5. Store consent document versions and timestamps.
6. Send a single-use verification link.
7. Show a generic check-your-email response that does not reveal whether the
   address already existed.

Verification token:

- Random, high entropy and stored only as a hash.
- Single use.
- Default expiry: 24 hours.
- Resend invalidates previous verification tokens.
- Resend cooldown: 60 seconds, with hourly and daily limits.
- Successful verification rotates the session and records `verified_at`.

If the email already belongs to a verified account, send an account-access
email rather than exposing that fact in the browser.

Password policy:

- 12-128 characters.
- Allow spaces, Unicode, paste and password managers.
- Require an acceptable strength result; do not require arbitrary character
  classes.
- Reject known/common compromised choices where the implementation can do so
  without sending the raw password to a third party.
- No forced periodic password change.

Generate an internal non-email username/nicename. Candidate accounts must not
appear in author archives, author sitemaps or public user API responses.

## 5. Login, recovery and sessions

### Login

- Email and password.
- Optional Remember me.
- Generic invalid-credentials response.
- Rate limit by account and network signal without permanently locking the
  account.
- Unverified accounts receive a generic response plus a safe resend path.
- Preserve only allowlisted same-origin return destinations.

### Password recovery

- Forgot-password response is identical for known and unknown emails.
- Reset links are single-use, hashed and expire after 60 minutes.
- Successful reset invalidates existing sessions and sends a security email.
- Never email a password.

### Security page

- Change password after current-password verification.
- View active sessions with approximate device/browser and last activity.
- Revoke one other session or all other sessions.
- Changing email requires current password, verification of the new email and a
  notification to the old email.
- Security-sensitive changes create redacted audit events.

Optional MFA/social login are not part of this release.

## 6. Dashboard

The dashboard answers three questions: what should I do next, what have I saved,
and what happened with my applications?

### Header

- Personal greeting
- Profile completion state
- Primary action based on context:
  - Complete profile
  - Browse matching jobs
  - Review saved jobs

### Modules

- Recommended jobs: up to six deterministic matches
- Recently saved jobs: up to four
- Application summary:
  - Confirmed submitted applications
  - Items needing attention
  - Hosted Giig redirects shown separately as activity
- Active job alerts and next delivery
- Profile/CV status
- Candidate support/contact link

No vanity score or unexplained matching percentage is shown.

### Dashboard states

- New verified account
- Partially completed profile
- No saved jobs
- No applications
- No recommendations
- Expired saved roles
- Alert delivery failure
- Application needing attention
- Portal service temporarily unavailable

## 7. Saved jobs

### Save control

- Appears on job cards and single-job pages.
- Signed-in verified candidate: toggles saved state immediately.
- Signed-out candidate: opens login/register path and returns to the same role.
- Unverified candidate: prompts verification.
- State and accessible label stay consistent everywhere.
- Repeated requests are idempotent.

### Saved-jobs page

- Live jobs first, newest saved first by default.
- Optional sort: newest saved, newest role, salary, A-Z.
- Filter by live/expired and sector.
- Each item shows the date saved and current role status.
- Candidate can remove one job or clear expired jobs after confirmation.
- Expired jobs remain visible until removed and link to the useful expired-job
  state with related live roles.

If a source job is recreated under the same source ID, preserve the saved
relationship. Never attach a saved record to a different source job.

## 8. Recommended jobs

Recommendations use a transparent weighted ruleset:

1. Preferred sectors
2. Preferred locations
3. Work mode
4. Job type
5. Salary preference
6. Attributes of saved and confirmed-applied jobs
7. Recency

Rules:

- Candidate-entered preferences have higher weight than inferred attributes.
- Exclude expired/unpublished jobs.
- Exclude confirmed applications.
- Avoid duplicates and cap at six on the dashboard.
- Show a plain explanation such as `Matches your Manufacturing preference`.
- Provide Edit preferences and Browse all jobs actions.
- Do not use AI scoring, protected characteristics or opaque behavioral
  profiling.

## 9. Job alerts

### Create alert

Candidates can create an alert from:

- Current jobs-search filters
- Dashboard/alerts page
- A no-results search

Criteria:

- Keywords
- Location
- Sector
- Job type
- Work mode
- Minimum salary

Frequencies:

- As jobs are added, processed after the four-hour job sync
- Daily digest
- Weekly digest

Default: daily. Maximum active alerts per candidate: 10.

### Delivery

- Send only newly matching live jobs not previously delivered for that alert.
- Maximum 10 jobs per email with a link to all matching roles.
- Use an idempotency key for alert + job + frequency window.
- Retry temporary provider failures; stop and surface repeated permanent
  failures.
- Include manage, pause, per-alert unsubscribe and unsubscribe-all links.
- Unsubscribe links are signed, single-purpose and do not require login.
- Unsubscribing from alerts does not delete the candidate account.

### Alerts page

- List name/summary, frequency, status, last sent and next expected run.
- Edit, pause/resume and delete.
- Show delivery problems without exposing provider internals.
- Alert email consent is distinct from general marketing consent.

## 10. Applications and activity

### Evidence model

Every history row stores a `status_source`:

- `poolhall_submission`: local first-party workflow evidence
- `giig_supported_api`: proven supported Giig response/status
- `candidate_action`: outbound redirect only
- `staff_verified`: exceptional manual correction with actor/time/reason audit

### Mode A

Allowed candidate-facing states:

- `Processing`: local submission/queue is still active
- `Submitted`: Giig acknowledged the application
- `Needs attention`: submission could not complete and staff/candidate action is
  required

Do not show shortlisted, interview, rejected, withdrawn, offered or hired unless
a supported Giig status endpoint/webhook is later proven and mapped.

History item:

- Job title, location and reference snapshot
- Submitted date
- Evidence-backed state
- Link to the live or expired job
- Contact Poolhall action

### Mode B

Clicking Apply records `Redirected to Giig` only for a signed-in candidate.
Display these rows in a separate `Giig application activity` section with:

`You opened the Giig application page. Poolhall cannot confirm from this
website whether you completed the application.`

Never count redirected rows as applications submitted.

### Guest Mode A claim

After a successful guest submission:

1. Offer Create account/claim history.
2. Send a single-use claim link to the application email.
3. Candidate registers or signs in with that same verified email.
4. Attach only matching unclaimed history rows after verification.
5. Invalidate the token and audit the claim.

Never attach history based only on a browser cookie or matching typed email.

## 11. Profile

Fields:

- First and last name
- Email, changed through the security flow
- Phone
- Town/city
- Current job title
- Preferred sectors
- Preferred locations
- Preferred job types
- Preferred work modes
- Minimum salary preference
- Alert and marketing preferences

Profile completion is guidance, not a barrier to browsing, saving or applying.
Do not request date of birth, gender, ethnicity, disability, NI number, full
address or right-to-work documents in this portal.

## 12. Reusable CV

Enable only in supported Mode A.

- One active CV per candidate.
- PDF, DOC or DOCX; default 5 MB maximum.
- Validate extension, real MIME and file signature where practical.
- Malware scan when host capability exists.
- Encrypt outside the public uploads path.
- Show safe filename, upload date and size.
- Replace, download and delete require authenticated ownership checks.
- Delete and email/security-sensitive actions require current-password
  reauthentication.
- Download uses a short-lived authorized response and never reveals storage
  paths.
- Replacing a CV securely removes the previous file after the new upload is
  committed.

On the apply form, the candidate can use the active CV or upload a replacement.
Do not keep a second permanent copy after successful submission.

In Mode B, hide reusable CV storage because the website cannot reliably deliver
that CV into the hosted Giig application.

## 13. Privacy controls

### Data export

- Candidate requests export after reauthentication.
- Generate asynchronously.
- Notify by email when ready.
- Download link is single-use or short-lived.
- Include profile, consents, saved jobs, alerts, application/activity history
  and audit information appropriate for the candidate.
- Do not include internal security signals, other users or secrets.
- Provide a common machine-readable format such as JSON plus a human-readable
  HTML summary.
- Self-service export does not replace Poolhall's process for verbal or written
  subject-access requests.

### Account deletion

1. Explain which WordPress data will be deleted and that Giig-held data is a
   separate system.
2. Require current password and explicit typed confirmation.
3. Disable alerts and revoke sessions immediately.
4. Default seven-day cancellation window, configurable after legal review.
5. Delete/anonymize site data and CVs after the window, except information that
   must legally be retained.
6. Send/queue a supported Giig/privacy request where agreed; never claim Giig
   deletion until confirmed.
7. Send completion notice without sensitive detail.

The request email includes a single-use cancellation link. Support may also
cancel after identity verification; a disabled candidate is not required to
restore a normal session solely to cancel deletion.

Privacy retention, legal basis and wording require client/legal approval before
launch. The workflow must not delay or narrow statutory request handling;
requests received through other channels are logged and handled within the
applicable legal deadline.

## 14. Email inventory

- Verify email
- Verification resend
- Existing-account access help
- Password reset
- Password changed
- Email-change request to new address
- Email changed notification to old address
- Job alert/digest
- Alert delivery disabled
- Application confirmation in Mode A
- Application needs attention
- Data export ready
- Account deletion requested/cancelled/completed

Templates use Poolhall branding, plain-text alternatives, accessible links and
authenticated sending. Security messages contain no CV or application details.

## 15. Elementor and plugin ownership

Elementor owns:

- Auth and portal page shells
- Introductory/editorial copy
- Account navigation presentation
- Empty-state editorial content
- Responsive layout and visual tokens

The custom plugin owns:

- Authentication, verification and recovery handlers
- Candidate role/capability enforcement
- All private queries and mutations
- Saved jobs, alerts, recommendations and history
- CV storage/download/deletion
- Privacy export/deletion
- Email queue and idempotency
- Candidate-facing state labels

Required custom Elementor widgets:

- Candidate Auth
- Candidate Account Navigation
- Candidate Dashboard
- Saved Jobs
- Application History
- Job Alerts
- Candidate Profile and CV
- Candidate Security
- Candidate Privacy

Widgets expose safe presentation controls only.

## 16. Responsive and accessibility behavior

- Desktop portal: 240-280px account navigation plus flexible main column.
- Mobile: single column; account navigation becomes an accessible disclosure or
  sheet and never traps content behind horizontal scrolling.
- Tables become semantic stacked lists/cards on narrow screens.
- Status is communicated with text and icon, never color alone.
- Authentication errors use a summary and field relationships.
- Save buttons announce state changes through an `aria-live` region without
  stealing focus.
- Session, deletion and CV confirmation dialogs trap focus and restore it.
- Portal remains usable at 200% zoom and with JavaScript enhancement failure
  where a server-rendered fallback is practical.

## 17. Analytics

With approved consent, record product events without CV data, search keywords
that may contain personal data, email or user IDs:

- registration_started/completed
- verification_completed
- login_completed
- job_saved/unsaved
- alert_created/paused
- dashboard_job_opened
- application_started/submitted
- hosted_apply_redirected

Use anonymous/internal event correlation rather than exposing WordPress user IDs
to analytics.

## 18. Definition of done

- All candidate checks in `04-ACCEPTANCE-QA.md` pass.
- Account emails deliver under SPF/DKIM/DMARC-aligned sending.
- Private pages are uncached and unindexed.
- Cross-account authorization tests return no candidate data.
- Mode A and Mode B histories use the correct language.
- Saved jobs and alerts survive normal job syncs and handle expiry.
- CV and account deletion are verified from storage through UI.
- Staff can support an account without seeing passwords or impersonating users.
