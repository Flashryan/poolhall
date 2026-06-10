# Poolhall Recruitment - One-Shot Build Brief

**Version:** 1.1  
**Prepared:** 2026-06-10  
**Target:** WordPress + Elementor Pro + Novamira build  
**Prototype:** https://poolhall.vercel.app/  
**Live site being replaced:** https://www.poolhallrecruitment.co.uk/

## 1. Product outcome

Build an owned, fast, accessible recruitment website that:

1. Presents separate candidate and employer journeys.
2. Syncs public jobs from Giig into native WordPress job posts.
3. Gives every role a crawlable first-party URL eligible for Google Jobs.
4. Lets candidates apply through a proven Giig-compatible flow.
5. Preserves Poolhall's current services, team, blog, legal content and
   Better Job Adverts offer.
6. Gives staff clear sync, application, error and expiry controls.
7. Can swap Giig for another ATS without rebuilding the website.
8. Gives candidates a secure account area for saved jobs, job alerts, profile
   management and trustworthy application activity.

## 2. Audience and primary journeys

### Candidate

Home -> search or browse jobs -> filter results -> job detail -> save or apply
-> confirmation -> dashboard.

Secondary candidate tasks:

- Browse core sectors.
- Understand Poolhall's candidate process.
- Read career advice/blog posts.
- Contact the team.
- View team credibility and Google reviews.
- Register, verify email, sign in and recover access.
- Manage saved jobs and job alerts.
- Maintain profile details and a reusable CV when direct application mode is
  supported.
- Review confirmed application history and clearly-labelled outbound Giig
  activity.

### Employer

Employer landing -> understand services and sectors -> view proof and values ->
submit hiring enquiry.

Secondary employer tasks:

- Explore permanent, temp-to-perm and retained recruitment.
- Explore Better Job Adverts as a fixed-fee service.
- Read employer advice/blog posts.
- Consider joining Poolhall as an employed consultant or partner.

### Poolhall staff

WordPress admin -> see last sync -> run sync -> inspect errors -> manage expiry
overrides -> confirm application mode -> edit site content -> review queued
applications only when API mode is enabled.

## 3. Locked v1 scope

### Build method

- Current stable WordPress release at build time.
- Hello Elementor child theme.
- Elementor Pro Theme Builder for global header/footer, pages, blog templates
  and job templates.
- Elementor 4 Atomic elements and global classes where stable; classic widgets
  are allowed where the Atomic equivalent is incomplete.
- Elementor v3/classic Loop Grid, Loop Carousel and Loop Item templates are the
  required implementation for dynamic WordPress collections.
- Use the complete visual/class/component contract in
  `10-ELEMENTOR-DESIGN-SYSTEM.md`.
- Novamira connected only to local/staging WordPress.
- One custom plugin owns Giig, jobs, applications, reviews, schema, admin tools
  and the custom Elementor dynamic tags/widgets needed for job data.
- Elementor owns layout and editable marketing content. The plugin owns data,
  security, integrations and business rules.

### Public pages

- Home
- Jobs archive/search
- Single job
- Apply page, only when supported API mode is enabled
- Employers
- Services
- Sectors
- Better Job Adverts
- Meet the Team
- Join Our Team
- Contact
- Blog/Insights archive
- Existing blog posts
- Privacy Policy
- Cookie Policy
- Terms and Conditions
- Carbon Reduction/Sustainability
- Modern Slavery Policy
- Complaints Procedure
- Candidate Registration Procedure
- Accessible 404 and job-expired pages

### Functional capabilities

- Giig public job pull and local caching
- Idempotent create/update/expire sync
- Manual sync and scheduled sync
- Search, filters, sort and pagination
- Featured jobs carousel
- Google Reviews carousel
- JobPosting JSON-LD on eligible single jobs only
- Jobs XML sitemap
- Google Indexing API notifications
- Search Console and GA4 setup
- Candidate application route with a documented fallback
- Candidate registration, verification, login and password reset
- Candidate dashboard and account navigation
- Saved jobs
- Job alerts and notification preferences
- Candidate profile, security and privacy controls
- Application history constrained to verified status data
- Reusable CV management when supported API mode is enabled
- Employer and general contact forms
- Blog migration
- Wix URL redirects
- Cookie consent and consent-aware analytics
- Admin logs, health summary and alerts
- Independent penetration test, remediation and retest

## 4. Explicitly out of scope for v1

- Employer self-service vacancy portal
- Multi-language content
- Paid job-board syndication
- Custom CRM dashboards
- Per-job custom screening questions
- Multi-brand or white-label sites
- Candidate social login
- Candidate messaging/chat
- AI job matching or candidate scoring
- Interview scheduling
- ATS-stage labels such as shortlisted, rejected or hired unless Giig exposes a
  supported status API/webhook and the mapping is proven
- Native mobile apps

The prototype's `Save job` button must become a real account-aware control.
Signed-out users are taken through login/registration and returned to the same
job; signed-in users receive immediate saved/unsaved feedback.

## 5. Visual direction

The live prototype is the visual source of truth:

- Premium but approachable recruitment brand.
- Dark navy hero sections with warm orange actions.
- Source Serif 4 display type with Hanken Grotesk body type.
- Editorial headings, practical cards and restrained use of shadows.
- Real Poolhall office/team photography.
- Candidate and employer experiences share one system but use distinct content.

Implementation must preserve the desktop character while fixing the prototype's
responsive problems:

- The contact strip overlaps at 390px.
- The mobile header has no navigation menu.
- The mobile jobs layout retains the desktop sidebar and overflows horizontally.
- Some links are prototype-only `#` links.
- Full-page sticky header capture reveals repeated header behavior.

## 6. Content decisions

- Use the prototype's tone and structure.
- Migrate factual content from the current Wix site rather than replacing it
  with generic copy.
- Keep Better Job Adverts as a dedicated commercial landing page.
- Keep Join Our Team, including employed consultant and partner propositions.
- Migrate the current blog archive and all posts.
- Keep existing legal and compliance pages.
- Use the actual current team: Matthew Tonks, Jay Thornton and Sam Ogle, subject
  to final client confirmation.
- Separate marketed core sectors from job-data taxonomies:
  - Marketed sectors: Construction, Manufacturing, Digital/Marketing.
  - Job filters: all industry values returned by Giig.

## 7. Application decision tree

The Giig public documentation currently shows:

- Candidate creation: `POST /public/api/v1/candidate`
- Applicant submission: `POST /public/api/v1/applicant/submit`
- No documented CV upload endpoint
- The hosted Giig careers form accepts a multipart CV through an undocumented
  `https://careers.giighire.com/Submit` form action

Therefore:

1. First prove a supported CV upload route with Giig using test credentials.
2. If supported, enable the first-party Poolhall apply form and set
   `directApply: true` where eligible.
3. If not supported, remove the first-party form and send the Apply CTA to the
   matching Giig hosted job application page. Set `directApply: false`.
4. Do not reverse engineer or proxy the undocumented hosted form endpoint.
5. Do not create an API candidate/application while sending the CV separately;
   that produces split records and a weak operational process.

The website can still launch with first-party job pages and Google Jobs
eligibility while the final application occurs on Giig's hosted page.

Candidate accounts do not change that gate:

- In Mode A, successful first-party applications appear as `Submitted`.
- In Mode B, the dashboard records only `Redirected to Giig` activity and never
  claims that an application was completed.
- Guest application remains available in Mode A. After submission, the
  candidate may securely claim an account through the same verified email.

## 8. Success measures

- Jobs appear on Poolhall URLs within four hours of publication in Giig.
- Removed or expired jobs leave the public site and notify Google promptly.
- No duplicate jobs for the same Giig job ID.
- No application is silently lost.
- Registration, verification, login and reset flows do not enumerate accounts.
- Saved-job state is consistent across job cards, detail pages and dashboard.
- Job alerts send once per matching job/frequency window and unsubscribe works.
- Candidate dashboards never display an unverified ATS status.
- Private account pages are never publicly cached or indexed.
- The independent penetration test closes with no unresolved release-blocking
  finding.
- Staff can understand sync health without reading logs.
- Mobile pages have no horizontal overflow at 360px and above.
- Core templates meet WCAG 2.2 AA.
- Valid job variants pass Google's Rich Results Test without errors.
- Existing content URLs either survive or 301 to a relevant replacement.
- Lighthouse targets on production mobile:
  - Performance: 85+
  - Accessibility: 95+
  - Best Practices: 95+
  - SEO: 95+

## 9. Definition of done

The build is complete only when all acceptance checks in
`04-ACCEPTANCE-QA.md` pass, the migration map is implemented, application mode
is proven, `08-CANDIDATE-PORTAL-SPEC.md` and
`10-ELEMENTOR-DESIGN-SYSTEM.md` are implemented, the penetration-test gate in
`09-PENETRATION-TEST-PLAN.md` is closed, the workflow in
`07-NOVAMIRA-ELEMENTOR-WORKFLOW.md` is followed, production smoke tests pass
and Poolhall owns every account.
