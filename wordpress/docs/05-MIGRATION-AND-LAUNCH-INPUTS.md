# Migration and Launch Inputs

## 1. Existing URLs that must be preserved

At minimum, retain or redirect:

- `/`
- `/services`
- `/sectors`
- `/team`
- `/franchise`
- `/better-job-adverts`
- `/blog`
- `/post/are-you-using-the-right-job-title-in-your-job-adverts`
- `/post/don-t-be-fooled-by-a-big-cv-response-why-quality-beats-quantity-in-recruitment`
- `/post/how-to-prepare-for-an-interview-candidate-edition`
- `/post/how-the-uk-minimum-wage-rise-impacts-your-hiring-strategy`
- `/carbon-reduction`
- `/modern-slavery-policy`
- `/complaints-policy`
- `/registration-procedures`
- `/privacy-policy`

Run a final Wix crawl/export immediately before launch. This list reflects the
visible site on 2026-06-10 and may not be exhaustive.

## 2. Redirect defaults

| Legacy | Destination |
|---|---|
| `/services` | `/services/` |
| `/sectors` | `/sectors/` |
| `/team` | `/team/` |
| `/franchise` | `/join-our-team/` |
| `/better-job-adverts` | `/better-job-adverts/` |
| `/blog` | `/blog/` |
| `/post/{slug}` | Preserve same path |
| Legal/compliance paths | Preserve same path |

Do not redirect all missing pages to the home page.

## 3. Content migration

Export and migrate:

- Page copy
- Team bios
- Blog posts, dates and images
- Better Job Adverts content and proof
- Join-team proposition
- Legal/compliance text
- Logo and brand marks
- TEAM and BNI membership assets
- Approved office/team photography
- Social profile URLs

Download original media, not transformed Wix thumbnails.

## 4. Content conflicts to confirm before launch

These do not block coding:

- Combined recruitment experience:
  - Prototype/archive says about 30 years.
  - Current Wix home says almost 50 years.
- Better Job Adverts current fixed price.
- Current Senior Recruitment Consultant salary/bonus.
- Partner commission, directorship and share-ownership claims.
- Current opening hours.
- Current team titles, biographies, email and LinkedIn links.
- Which client company names may appear as `hiringOrganization`.
- Whether Blog should be labelled Blog, Insights or Advice.

## 5. Required account/access inputs

Needed during build:

- Giig API token
- Giig second access/session secret
- Giig CompanyId
- Giig support contact for CV endpoint confirmation
- Staging WordPress admin
- Managed host access
- Elementor Pro licence
- Novamira local/staging connection and least-privilege development user
- Independent penetration-test supplier and named lead tester
- Signed penetration-test authorisation/rules of engagement

Needed before launch:

- Wix/domain DNS access
- Google Search Console owner access
- GA4 property access or approval to create one
- Google Cloud project with Indexing API enabled
- Places API key and Poolhall Place ID
- Cloudflare Turnstile site/secret keys
- Authenticated email provider/SMTP credentials
- Sending-domain DNS access for SPF, DKIM and DMARC alignment
- Approved notification inboxes
- Hosting/CDN/provider approval for the agreed test source IPs and window

## 6. Launch policy decisions

Confirm before production:

- Application Mode A or B
- CV maximum size, default 5 MB
- Candidate data retention, build default 12 months
- Candidate account/profile retention and inactive-account policy
- Candidate reusable-CV retention and deletion policy
- Account deletion grace period, build default 7 days
- Whether and how account deletion requests are forwarded to Giig
- Candidate email-verification and alert sender name/address
- Cookie/analytics consent wording
- Legal owner approval for privacy/cookie/legal pages
- Domain remains at Wix or transfers to a registrar
- Final maintenance/alert recipient
- Penetration-test scope, dates, emergency contacts and production-check window
- Named business owner authorised to accept residual security risk

## 7. Recommended defaults

Use these unless the client changes them:

- Job sync every four hours
- 10 jobs per archive page
- 30-day job expiry with admin extension
- 5 MB CV limit
- PDF, DOC and DOCX
- Candidate application notifications: `matthew@poolhallrecruitment.co.uk`
- General/employer enquiries: `jobs@poolhallrecruitment.co.uk`
- Reviews refresh every 24 hours
- Stale reviews cache up to 7 days
- Application queue retry window 24 hours
- Failed encrypted application retention 7 days
- Local candidate retention setting 12 months, pending legal confirmation
- Candidate verification link expiry 24 hours
- Password-reset link expiry 60 minutes
- One active reusable CV in supported Mode A only
- Daily job-alert default; immediate and weekly alternatives
- Maximum 10 active alerts per candidate
- Seven-day account-deletion cancellation window, pending legal confirmation

## 8. Handover deliverables

- Source repository
- Deployment instructions
- Environment variable reference
- Data model and API field map
- Redirect export
- Backup/restore runbook
- Sync/application incident runbook
- Candidate account/security support runbook
- Candidate email and alert delivery runbook
- Candidate privacy export/deletion runbook
- Google Jobs monitoring instructions
- Content editing guide
- Elementor Site Settings export
- Theme Builder template export and display-condition inventory
- Global class and custom widget reference
- Elementor v4 Variable/Component export and style-guide reference
- v3 Loop Item template export, ID and usage inventory
- Novamira removal/connection record
- Candidate email template inventory
- Candidate data-retention and external Giig-deletion responsibility matrix
- Penetration-test final report, remediation evidence and closure letter
- Account ownership checklist
- 30-day post-launch defect support start/end dates
