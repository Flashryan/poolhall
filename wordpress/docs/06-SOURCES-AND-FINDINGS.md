# Sources and Findings

Reviewed on 2026-06-10.

## Prototype

Source: https://poolhall.vercel.app/

Observed screens/states:

- Candidate home
- Employer landing
- Jobs archive with filters and sort
- Single job
- Apply form
- Team
- Contact

Observed design:

- Source Serif 4 and Hanken Grotesk
- 1152px/72rem measured desktop container at the 1280px reference viewport
- Navy/orange design tokens recorded in the UI spec
- Candidate/employer audience switch
- Featured jobs and reviews carousels

Observed issues corrected by this handoff:

- Mobile contact strip overlaps at 390px.
- Mobile header hides navigation without providing a menu.
- Mobile jobs retain the desktop sidebar and overflow horizontally.
- Navigation uses prototype-only `#` links.
- `Save job` has no state change in the prototype and is replaced by the
  authenticated candidate saved-jobs feature.
- Job/review content is sample data and must not be hard-coded.

## Current Poolhall Wix site

Source: https://www.poolhallrecruitment.co.uk/

Content/routes observed:

- Services: https://www.poolhallrecruitment.co.uk/services
- Sectors: https://www.poolhallrecruitment.co.uk/sectors
- Team: https://www.poolhallrecruitment.co.uk/team
- Join Our Team: https://www.poolhallrecruitment.co.uk/franchise
- Better Job Adverts: https://www.poolhallrecruitment.co.uk/better-job-adverts
- Blog: https://www.poolhallrecruitment.co.uk/blog
- Carbon Reduction: https://www.poolhallrecruitment.co.uk/carbon-reduction
- Modern Slavery: https://www.poolhallrecruitment.co.uk/modern-slavery-policy
- Complaints: https://www.poolhallrecruitment.co.uk/complaints-policy
- Registration procedure: https://www.poolhallrecruitment.co.uk/registration-procedures
- Privacy: https://www.poolhallrecruitment.co.uk/privacy-policy

The live site also exposed four blog posts dated in 2025 during review. A final
crawl/export is still required immediately before launch.

Content conflicts found:

- Prototype/archive says about 30 years combined experience.
- Current Wix home says almost 50 years combined experience.
- Current marketed sectors are Construction, Manufacturing and Digital, while
  Giig currently exposes additional industry taxonomy values.

## Giig

API docs: https://api-doc.giighire.com/

Current Poolhall careers page:
https://careers.giighire.com/poolhallrecruitmentlimited

Observed official API endpoints:

- `GET /public/api/v1/job/getjobs`
- `GET /public/api/v1/job/get`
- `POST /public/api/v1/candidate`
- `POST /public/api/v1/applicant/submit`

Observed risks:

- Documentation conflicts on the second auth header.
- Applicant submission is documented.
- CV upload is not documented in the public API.
- The hosted careers form accepts multipart CV uploads through
  `https://careers.giighire.com/Submit`, but this form endpoint is not documented
  as a supported public integration API.
- Current Giig job pages include `noindex, nofollow`.

These findings are why the build uses the explicit application Mode A/Mode B
gate.

## Google Jobs

Official guidance:
https://developers.google.com/search/docs/appearance/structured-data/job-posting

Official Indexing API:
https://developers.google.com/search/apis/indexing-api

Confirmed requirements used in this handoff:

- JobPosting markup is for individual job pages.
- Pages must be crawlable and canonical.
- Visible content and structured data must agree.
- Closed roles must be expired/removed promptly.
- Google recommends the Indexing API for job update/removal notification.
- Google still recommends a sitemap for overall coverage.
- `directApply` should only be true for a short, direct application experience.

## Google Places reviews

Place Details (New):
https://developers.google.com/maps/documentation/places/web-service/place-details

Places policies:
https://developers.google.com/maps/documentation/places/web-service/policies

Confirmed requirements used in this handoff:

- Use an explicit field mask.
- A Place response can contain up to five reviews.
- Reviews in the new API are sorted by relevance.
- Reviewer name must be displayed close to the review.
- Author/profile/photo attribution should be displayed when supplied.
- Google and third-party attribution requirements apply.

## Elementor

Official Loop Grid documentation:
https://elementor.com/help/loop-grid/

Official Atomic elements data structure:
https://developers.elementor.com/docs/data-structure/atomic-elements/

Official Elementor 4 developer update:
https://developers.elementor.com/elementor-editor-4-0-developers-update/

Official dynamic content overview:
https://elementor.com/help/intro-to-dynamic-content/

Official variable/global synchronization:
https://elementor.com/help/how-to-sync-variables-and-global-elements/

Decisions:

- Use Elementor Pro Theme Builder for global, blog and job presentation.
- Use a hybrid Atomic/classic build because Elementor 4 supports both systems
  and Atomic elements are enabled selectively.
- Use v4 Atomic elements, Variables, Classes and Components for normal page
  construction.
- Use v3/classic Loop Grid/Carousel and Loop Item templates for dynamic
  WordPress collections, with variables synced to classic globals.
- Keep dynamic job queries, secure forms and integrations in plugin-provided
  widgets/dynamic tags.
- Treat Site Settings, global classes and exported templates as controlled
  deployment artifacts.

## Novamira

Official site: https://novamira.ai/

Official connection guide:
https://novamira.ai/docs/getting-started/connecting/

Decisions:

- Use Novamira for local/staging WordPress and Elementor inspection/build work.
- Never connect it to production.
- Back up Elementor data before edits, make minimal changes, regenerate
  Elementor assets, clear caches and browser-verify the frontend.

## WordPress candidate authentication

Official cookie authentication:
https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/

Official nonce guidance:
https://developer.wordpress.org/apis/security/nonces/

Official roles and capabilities:
https://developer.wordpress.org/plugins/users/roles-and-capabilities/

Decisions:

- Use native WordPress users, secure cookies and session tokens.
- Use REST nonces for CSRF protection on authenticated frontend requests.
- Never treat a nonce as authorization; every private endpoint also checks
  capability and resource ownership.
- Give candidates a dedicated frontend-only role with no admin capabilities.

## Account security guidance

OWASP Authentication Cheat Sheet:
https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html

OWASP Forgot Password Cheat Sheet:
https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html

Decisions:

- Use generic login/registration/reset responses to reduce account enumeration.
- Store verification/reset/claim tokens as hashes, with expiry and single use.
- Apply rate limiting and require reauthentication for sensitive account
  changes.
- Invalidate sessions after password recovery and notify candidates of security
  changes.

## UK candidate privacy

ICO recruitment and selection guidance:
https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/employment/recruitment-and-selection/

ICO right to erasure:
https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/individual-rights/individual-rights/right-to-erasure/

ICO right to data portability:
https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/individual-rights/individual-rights/right-to-data-portability/

Decisions:

- Keep candidate-profile and recruitment data purpose-limited and transparent.
- Provide self-service export and deletion controls, while retaining a process
  for requests received verbally or through other channels.
- Treat erasure as a qualified right requiring documented legal/retention
  review, not an unconditional database delete.
- Make the boundary between Poolhall WordPress data and Giig-held data explicit.

## Penetration testing

OWASP ASVS:
https://owasp.org/www-project-application-security-verification-standard/

OWASP Web Security Testing Guide:
https://owasp.org/www-project-web-security-testing-guide/

OWASP Top 10:2025:
https://owasp.org/Top10/2025/

OWASP API Security Top 10:2023:
https://owasp.org/API-Security/editions/2023/en/0x11-t10/

NCSC penetration-testing guidance:
https://www.ncsc.gov.uk/guidance/penetration-testing

Decisions:

- Use ASVS 5.0.0 Level 2 as the build verification baseline.
- Require an independent, manually led penetration test rather than relying on
  automated scans.
- Define exact systems, threats, methods, permissions, exclusions, contacts and
  stop conditions before testing.
- Test the production-like staging release candidate, remediate findings and
  independently retest before launch.
- Restrict production testing to approved low-impact delta checks.

## Original archive

Source: `/Users/ryan/Downloads/files.zip`

Useful material retained:

- Client goals and commercial context
- Four-hour/same-day sync preference
- 30-day expiry decision
- 5 MB proposed CV limit
- Candidate/employer journey direction
- Featured jobs and reviews carousels
- Swappable ATS adapter requirement
- WordPress custom plugin/theme separation

Material superseded:

- Duplicate specs and build plans
- Optional treatment of the blog
- Omission of Better Job Adverts and Join Our Team
- Assumption that CV upload is available through the public Giig API
- Incomplete responsive specification
