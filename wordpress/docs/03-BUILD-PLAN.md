# Build Plan

Each phase has an exit gate. Do not advance through a failed integration gate.

## Phase 0 - Repository and environments

Deliver:

- Hello Elementor child theme and Poolhall plugin repositories/scaffolds
- Local and staging WordPress
- Elementor Pro installed/licensed on staging
- Novamira installed and connected on local/staging only
- PHP_CodeSniffer and WordPress Coding Standards
- PHPUnit and frontend test setup
- Deployment path to staging
- Secret placeholders
- Synthetic test CVs

Exit gate:

- Fresh checkout can build and deploy to staging.
- No secret is committed.
- Staging contains no real candidate data.
- A test Elementor page has been backed up, minimally edited through the
  Novamira workflow, regenerated and verified on the frontend.

## Phase 1 - Prove Giig contracts

Using real test credentials on staging:

1. Verify required auth headers.
2. Fetch public jobs and one full job.
3. Record exact response fields and pagination behavior.
4. Create a synthetic candidate.
5. Submit that candidate to a test/live-approved job.
6. Obtain written confirmation or test proof for a supported CV upload route.
7. Select application Mode A or Mode B.

Exit gate:

- Endpoint/auth/field-map fixture is committed without secrets.
- Application mode is recorded.
- A synthetic applicant appears in the expected Giig job.
- CV behavior is proven or hosted fallback is locked.

## Phase 2 - Core plugin and sync

Deliver:

- Job CPT, taxonomies and metadata
- Giig job-source adapter
- Normalizers and value objects
- Idempotent sync
- Expiry and extension logic
- Real cron and WP-Cron fallback
- Admin health/log screen
- Alerting

Tests:

- New job
- Changed job
- Duplicate prevention
- Missing job expiry
- Incomplete source response does not mass-expire
- Lock prevents overlap
- Manual sync permissions

Exit gate:

- Current Giig jobs match staging by ID and visible fields.
- Repeated sync creates no duplicates.
- Forced API failure preserves existing jobs.

## Phase 3 - Elementor design system and global shell

Deliver:

- Tokens from the prototype
- Elementor Site Settings and named global classes
- v4 Variables, Components and style-guide page from
  `10-ELEMENTOR-DESIGN-SYSTEM.md`
- Synchronized v3 Global Colors/Fonts
- Typography and font loading
- Theme Builder header/contact strip
- Mobile navigation drawer
- Audience switch
- Theme Builder footer and membership marks
- Buttons, inputs, cards, badges and alerts
- Reusable section patterns
- v3 Loop Item templates for featured jobs, result rows, compact jobs, blog,
  sectors, team and reviews where each collection is dynamic

Exit gate:

- Header works at all test widths.
- No horizontal overflow at 360px.
- Keyboard navigation and focus order pass.
- An editor can change approved content without changing plugin code.
- Style-guide page demonstrates every shared token, class, component and state.
- Normal sections use v4 Atomic; v3 usage matches the documented exception list.
- Each Loop Item has an ID and usage inventory.

## Phase 4 - Jobs user experience

Deliver:

- Elementor home with plugin search/featured-job widgets
- Jobs archive shell with plugin results/filter widgets
- Filter drawer/sidebar behavior
- Sort and pagination
- Single-job Theme Builder template with plugin dynamic tags/widgets
- Expired job
- Similar roles
- Featured jobs carousel

Exit gate:

- URL-state filters work with Back/Forward.
- Mobile filters do not overflow.
- Every card and control is keyboard usable.
- Search works without JavaScript.

## Phase 5 - Applications and forms

Deliver:

- Selected application mode
- Mode A custom application widget and encrypted retry queue, or Mode B hosted
  links
- Employer enquiry form
- General contact form
- Join-team enquiry routing
- Turnstile, honeypot, rate limits and email delivery
- Elementor-rendered low-risk forms delegated to plugin handlers

Exit gate:

- Mode A: PDF and DOCX applications reach the correct Giig job and forced
  failures are queued and alerted.
- Mode B: every Apply URL opens the matching Giig hosted role.
- Contact forms deliver and duplicate submission is prevented.

## Phase 6 - Candidate accounts and portal

Deliver:

- `poolhall_candidate` role and frontend-only access controls
- Registration, email verification, login, logout and password recovery
- Candidate portal Elementor shells and plugin widgets
- Dashboard and account navigation
- Saved jobs across cards, details and dashboard
- Deterministic job recommendations
- Job-alert creation, delivery, unsubscribe and preferences
- Mode-aware application history/activity
- Profile and security/session management
- Encrypted reusable CV in Mode A only
- Privacy export and account-deletion workflows
- Candidate transactional email templates and delivery health

Tests:

- Account-enumeration resistance and rate limiting
- Cross-account authorization
- Verification/reset/claim token expiry and single use
- Save/unsave idempotency and expired-job behavior
- Alert delivery idempotency and unsubscribe
- Mode A submitted state and Mode B redirected wording
- CV access, replacement and deletion
- Session revocation and sensitive-action reauthentication
- Export and deletion lifecycle
- Private-page cache and indexing headers

Exit gate:

- Register -> Verify -> Save -> Dashboard -> Alert -> Logout passes.
- No candidate can read or mutate another candidate's records.
- Account emails pass delivery checks with aligned authentication.
- Application history contains no unsupported ATS status.
- Private pages are uncached, noindexed and absent from sitemaps.

## Phase 7 - Content pages and migration

Deliver:

- Employers
- Services
- Sectors
- Better Job Adverts
- Team
- Join Our Team
- Contact
- Blog archive and migrated posts
- Legal/compliance pages
- Redirect map

Exit gate:

- All current Wix navigation/footer destinations have a destination.
- Blog posts retain content, dates, images and URL intent.
- No placeholder links or prototype sample contact details remain.
- Theme Builder display conditions do not overlap or leave content untemplated.

## Phase 8 - Google Jobs, reviews and SEO

Deliver:

- JobPosting schema generator
- Accurate hiring-organization gate
- Jobs sitemap
- Indexing API queue
- Search Console
- Places API reviews with attribution/cache
- Metadata, canonicals, robots rules and Article schema

Exit gate:

- Onsite, hybrid and remote fixtures pass Rich Results Test.
- Closed job removes schema and queues deletion.
- Reviews show proper attribution.
- Filtered job URLs are noindex with correct canonical.

## Phase 9 - QA and hardening

Deliver:

- Accessibility fixes
- Performance optimization
- Security review
- Candidate authentication/authorization threat review
- Internal ASVS Level 2 verification and security test evidence
- Independent penetration test
- Remediation and independent retest
- Cross-browser/device testing
- Backup/restore test
- Elementor editor/frontend parity and cache-invalidation test
- Design-system raw-value/class-usage audit
- Operational runbook
- Client training draft

Exit gate:

- `04-ACCEPTANCE-QA.md` passes on staging.
- No P1/P2 defects remain.
- `10-ELEMENTOR-DESIGN-SYSTEM.md` acceptance checks pass.
- The independent tester has issued the final report and closure/retest letter.
- No release-blocking finding defined in `09-PENETRATION-TEST-PLAN.md` remains
  open.

## Phase 10 - Launch

Deliver:

- Final Wix crawl and redirect import
- Production backup
- DNS cutover and SSL
- Production smoke test
- Search Console verification
- Sitemap submission
- Indexing notifications for live jobs
- GA4 consent test
- Candidate email delivery and alert queue smoke test
- Candidate registration/save/dashboard production smoke test
- Authorised low-impact production security delta checks
- Account ownership transfer
- Training and handover

Exit gate:

- Production acceptance smoke test passes.
- Old URLs redirect correctly.
- Staff can run a sync and locate application status.
- A candidate can verify an account, save a job and manage an alert.
- Production security delta checks match the tested staging configuration.
- Poolhall owns hosting, domain, Google and API accounts.

## Work sequencing note

Visual/content implementation can proceed while Giig support confirms CV
capability, but the application screen must remain feature-flagged until Phase
1 selects the mode.
