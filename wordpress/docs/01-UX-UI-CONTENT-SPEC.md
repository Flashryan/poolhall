# UX, UI and Content Specification

## 1. Information architecture

Use real routes and links. Do not reproduce the prototype's single-page
JavaScript navigation or `#` URLs.

| Route | Purpose |
|---|---|
| `/` | Candidate-first home page |
| `/jobs/` | Searchable jobs archive |
| `/jobs/{slug}-{giig_id}/` | Canonical single job |
| `/apply/{slug}-{giig_id}/` | First-party apply form when API mode is enabled |
| `/candidate/login/` | Candidate login |
| `/candidate/register/` | Candidate registration |
| `/candidate/verify-email/` | Email-verification result |
| `/candidate/forgot-password/` | Password-reset request |
| `/candidate/reset-password/` | Password reset |
| `/candidate/` | Authenticated dashboard |
| `/candidate/saved-jobs/` | Saved jobs |
| `/candidate/applications/` | Application history and outbound activity |
| `/candidate/job-alerts/` | Job-alert management |
| `/candidate/profile/` | Profile and CV |
| `/candidate/security/` | Password and sessions |
| `/candidate/privacy/` | Export and account deletion |
| `/employers/` | Employer landing page |
| `/services/` | Permanent, temp-to-perm and retained recruitment |
| `/sectors/` | Core sector landing content |
| `/better-job-adverts/` | Fixed-fee job advertising offer |
| `/team/` | Team and company story |
| `/join-our-team/` | Employed consultant and partner propositions |
| `/contact/` | General contact form and office details |
| `/blog/` | Insights archive |
| `/post/{legacy-slug}/` | Migrated blog post URLs |
| `/privacy-policy/` | Privacy policy |
| `/cookie-policy/` | Cookie policy |
| `/terms/` | Terms |
| `/carbon-reduction/` | Sustainability/carbon content |
| `/modern-slavery-policy/` | Modern slavery statement |
| `/complaints-policy/` | Complaints process |
| `/registration-procedures/` | Candidate registration procedure |

## 2. Global navigation

### Desktop

- Contact strip: phone, email and office location.
- Main navigation: Find a Job, Employers, Services, Sectors, Meet the Team,
  Contact.
- Secondary path switch:
  - Candidates -> `/jobs/`
  - Employers -> `/employers/`
- Primary CTA:
  - Candidate context: Browse jobs
  - Employer context: Hire talent
- Candidate account:
  - Signed out: Sign in
  - Signed in: first name/avatar trigger with Dashboard, Saved jobs, Alerts,
    Profile and Sign out

### Mobile

- Hide the desktop contact strip or reduce it to phone and email on one line.
- Show logo, primary CTA and accessible menu button.
- Menu opens a focus-trapped drawer with all primary links and audience switch.
- Escape closes the drawer; focus returns to the opener.
- Candidate account links appear inside the drawer and retain the same
  signed-in/signed-out state as desktop.
- No content may extend beyond the viewport at 360px.

## 3. Home page

Match the prototype's order:

1. Candidate hero with image, eyebrow, headline and introduction.
2. Search panel:
   - Keyword
   - Location
   - Sector
   - Search
3. Trust points:
   - Google rating
   - Quality/ethical positioning
   - Live role count from the local job store
4. Featured jobs carousel.
5. Core sectors.
6. Three-step candidate process.
7. Credibility stats.
8. Google Reviews carousel.
9. Employer CTA.
10. Footer.

### Home search behavior

- Submit with GET parameters to `/jobs/`.
- Keyboard Enter submits from any search field.
- Sector values come from the synced local taxonomy.
- Do not autocomplete location unless a supported location dataset is added.
- Inputs retain their values on the results page.

### Featured jobs

- Source: published, unexpired local jobs.
- Priority: manually featured, then newest.
- Maximum: six.
- Each card is one real link with a descriptive accessible name.
- Carousel does not auto-rotate.
- Desktop: three visible cards.
- Tablet: two visible cards.
- Mobile: one card with partial next-card cue.
- Controls work by keyboard and touch.

## 4. Jobs archive

### URL state

Use shareable query parameters:

`q`, `location`, `sector`, `type`, `work_mode`, `salary_min`, `sort`, `page`

### Filters

- Keyword
- Location
- Sector/industry
- Job type
- Working pattern: onsite, hybrid/part remote, fully remote
- Minimum salary
- Sort: most recent, highest salary, A-Z

Experience and education remain display fields, not primary v1 filters.

### Behavior

- Server-render the initial result set.
- Progressive enhancement may update results without a full reload.
- Browser Back/Forward must restore filters.
- Pagination: 10 jobs per page.
- Show applied filter chips and a clear-all action.
- Result counts must reflect the current result set, not prototype sample totals.
- Empty state offers Clear filters and Contact us actions.
- Filtered/search result URLs are `noindex,follow` with canonical `/jobs/`.
- The unfiltered jobs archive is indexable.

### Mobile jobs behavior

The prototype's persistent left sidebar must not be used on mobile.

- Filters collapse into a `Filter jobs` button.
- Open filters in an accessible modal or drawer.
- Results remain a single column.
- Sort stays above results.
- The page must not horizontally scroll.

## 5. Single job

### Visible content

- Featured label when applicable
- Sector
- Job title
- Full location
- Employment type
- Working pattern
- Posted date
- Summary
- Sanitized full description from Giig
- Experience and education when present
- Salary and salary period
- Job reference
- Apply CTA
- Phone alternative
- Similar roles from the same sector

### Actions

- Apply:
  - API mode -> first-party apply route.
  - Hosted mode -> exact Giig hosted job URL.
- Save job:
  - Signed in -> save/unsave without leaving the page and announce the result.
  - Signed out -> go to login/register with a signed, same-origin return URL.
  - After authentication -> return to the role and complete the save.
- Optional native share links may be added, but are secondary.

### Expired job

- Return HTTP 410 for permanently removed jobs when there is no replacement.
- Show a useful expired-role page with related live jobs.
- Remove JobPosting schema.
- Notify the Indexing API of deletion.

## 6. Apply page

Render only in supported API mode.

Fields:

- First name
- Last name
- Email
- Phone
- Current job title
- Town/city
- CV upload
- Optional message
- Required privacy consent

Rules:

- PDF, DOC and DOCX.
- Default 5 MB maximum, configurable.
- Server-side MIME and extension validation.
- Show upload progress and a removable selected-file state.
- Preserve non-file fields after validation failure.
- Disable duplicate submit while processing.
- Show field-level and summary errors.
- Confirmation includes job title and expected next step.
- Never expose an API exception to the user.
- Signed-in candidates see profile fields prefilled and may use their active CV.
- Guest applications remain supported; after success, offer a secure account
  claim flow tied to the verified application email.

## 6A. Candidate portal

The complete account, dashboard, saved-jobs, alert, application-history,
profile/CV, security and privacy specification is in
`08-CANDIDATE-PORTAL-SPEC.md`.

Portal visual direction:

- Continue the navy/orange editorial system without looking like WordPress
  admin.
- Desktop uses a compact left account navigation and a flexible content region.
- Mobile uses an account overview header and an accessible navigation dropdown
  or sheet.
- Dashboard cards prioritize the next useful action, not vanity metrics.
- Every empty, loading, success, validation, expired-job and service-failure
  state is designed.

## 7. Employers page

Retain the prototype's visual structure but reconcile it with real services:

1. Employer hero.
2. Service cards:
   - Permanent recruitment
   - Temp-to-perm
   - Retained/embedded support
3. Quality and ethics proof section.
4. Core sectors.
5. Better Job Adverts callout linking to its dedicated page.
6. Hiring enquiry form.

Hiring form:

- Name
- Company
- Email
- Phone
- Role(s), sector and timescale
- Required privacy consent
- Turnstile, honeypot and rate limiting
- Email notification to configurable employer inbox

## 8. Services and Better Job Adverts

### Services

Migrate and edit the current Wix content for:

- Temp-to-perm
- Permanent recruitment
- Retained/custom support

### Better Job Adverts

Preserve the dedicated offer and current commercial proposition, including:

- Fixed-fee positioning
- Multi-board advertising
- Branded advert creation
- CV screening/shortlisting
- ATS-ready candidate organization
- Proof/testimonial content
- Current price only after client reconfirms it

Do not bury Better Job Adverts as a small card on the Services page.

## 9. Sectors

Market three main capabilities:

- Construction
- Manufacturing
- Digital/Marketing

Use examples from the current site. Job filters can display additional Giig
industry values such as Sales, Insurance and Automotive without creating thin
marketing pages for each one.

Each core sector section includes:

- Disciplines/roles recruited
- Candidate CTA to filtered jobs
- Employer CTA to hiring enquiry
- Current live job count

## 10. Team and Join Our Team

### Team

Use the prototype layout and current factual biographies for:

- Matthew Tonks
- Jay Thornton
- Sam Ogle

Use real email and LinkedIn links. Do not use placeholder links.

### Join Our Team

Preserve both propositions from the current site:

- Employed senior consultant
- Self-employed/partner model

The page must not publish stale salary, commission or ownership claims without
client confirmation.

## 11. Blog

- Migrate the archive and all existing posts.
- Preserve legacy `/post/{slug}` URLs where practical.
- Include author, published date, featured image and Article schema.
- Provide category/tag support only if the current content uses it.
- Add related posts and employer/candidate CTA blocks.

At least four posts were live when reviewed on 2026-06-10.

## 12. Contact

Match the prototype's two-column desktop layout:

- Phone
- Email
- Office address
- Opening hours
- Map link or static map with accessible text alternative
- Contact form

Contact form audience choices:

- Candidate looking for work
- Employer looking to hire
- Recruiter interested in joining
- Something else

Route notifications by audience where configured.

## 13. Footer

Include:

- Candidate links
- Employer links
- Company links
- LinkedIn and Instagram
- TEAM and BNI membership marks, using approved assets
- Company number
- VAT number
- Full office address
- Legal/compliance links

## 14. Design system

These values were extracted from the prototype and are the implementation
baseline.

### Type

- Display: Source Serif 4
- Body: Hanken Grotesk
- Mono/data: IBM Plex Mono
- Desktop display: 56px / 1.08
- H1: 44px / 1.12
- H2: 34px / 1.15
- H3: 24px
- Body: 16px / 1.6
- Small: 14px
- Eyebrow: 12px, uppercase, 0.14em tracking

Use fluid type with `clamp()` below desktop sizes.

### Core colors

| Token | Value |
|---|---|
| Navy 950 | `#0B1626` |
| Navy 900 | `#0F1D33` |
| Navy 800 | `#14233F` |
| Navy 700 | `#1B3052` |
| Orange 700 | `#B9510E` |
| Orange 600 | `#D45F12` |
| Orange 500 | `#EC6F1E` |
| Orange 50 | `#FDF1E7` |
| Blue 700 | `#155389` |
| Blue 600 | `#1F6FB2` |
| Gold 500 | `#C6A052` |
| Paper | `#F7F8FA` |
| Mist | `#EEF1F5` |
| Border | `#E3E7ED` |
| Ink | `#16202F` |
| Muted text | `#586375` |
| Success | `#1F8A5B` |
| Error | `#C9382E` |

### Spacing and shape

- Base spacing: 4px
- Section desktop padding: 84px
- Tight section padding: 60px
- Standard container: 1152px/72rem with fluid side gutters
- Wide-media container: maximum 1200px/75rem
- Card radius: 16px
- Input radius: 6-10px
- Button radius: 6px
- Pill radius: 999px
- Primary button: orange, white text, 700 weight

### Motion

- Respect `prefers-reduced-motion`.
- No autoplay carousels.
- Use 150-250ms transitions for hover/focus.
- Never animate layout in a way that moves a focused control.
- Use Elementor interactions only for simple editorial entrances, never for
  navigation, filtering, application state or other functional UI.

### Elementor implementation

- Elementor Site Settings are the visual-token source of truth.
- Use named global classes for repeated sections, cards, buttons and form
  fields; do not duplicate arbitrary page-level CSS.
- Mirror essential tokens in the child theme and custom plugin widgets so
  dynamic interfaces match editor-built content.
- Keep custom CSS minimal, documented and version-controlled in the child theme
  when it is shared across pages.

## 15. Elementor template inventory

| Template | Elementor ownership |
|---|---|
| Global header | Theme Builder with desktop/mobile navigation states |
| Global footer | Theme Builder |
| Home | Elementor page |
| Employers | Elementor page |
| Services | Elementor page |
| Sectors | Elementor page |
| Better Job Adverts | Elementor page |
| Team | Elementor page |
| Join Our Team | Elementor page |
| Contact | Elementor page |
| Blog archive | Theme Builder archive |
| Blog single | Theme Builder single |
| Jobs archive | Theme Builder shell plus plugin widgets |
| Single job | Theme Builder single plus plugin dynamic tags/widgets |
| Apply | Elementor shell plus plugin application widget |
| Candidate auth | Elementor shell plus plugin auth widget |
| Candidate dashboard | Elementor shell plus plugin dashboard widget |
| Saved jobs | Elementor shell plus plugin saved-jobs widget |
| Applications | Elementor shell plus plugin application-history widget |
| Job alerts | Elementor shell plus plugin alert-management widget |
| Candidate profile | Elementor shell plus plugin profile/CV widget |
| Candidate security/privacy | Elementor shell plus plugin account widgets |
| Expired job | Dedicated template/state |
| 404 | Theme Builder |

Required plugin-provided Elementor components:

- Job Search
- Job Results and Filters
- Job Card
- Job Details
- Apply CTA
- Application Form
- Featured Jobs
- Google Reviews
- Live Job Count
- Candidate Auth
- Candidate Account Navigation
- Candidate Dashboard
- Saved Jobs
- Application History
- Job Alerts
- Candidate Profile and CV
- Candidate Security
- Candidate Privacy

Elementor controls presentation options only. It must not store credentials,
perform Giig requests or implement application and job-query business rules.

## 16. Responsive matrix

Test at:

- 360 x 800
- 390 x 844
- 768 x 1024
- 1024 x 768
- 1280 x 720
- 1440 x 900

Suggested breakpoints:

- Small: below 640px
- Medium: 640-899px
- Large: 900-1199px
- Wide: 1200px+

The existing prototype only has partial rules below 900px; the production build
requires component-level responsive behavior, not just stacked grids.

## 17. Accessibility

- WCAG 2.2 AA target.
- Visible focus on every interactive element.
- Minimum 44 x 44px touch targets.
- Semantic headings with one H1 per page.
- All cards expose one clear link target.
- Form errors are announced and linked to fields.
- Carousels have labels, disabled states and keyboard controls.
- Color is never the only status cue.
- Images have useful alt text or empty alt when decorative.
- Skip link and landmark structure are required.
