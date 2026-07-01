# CLAUDE.md - Poolhall Recruitment Build Context

## Authority

Read `00-ONE-SHOT-BUILD-BRIEF.md` first. The files in this folder supersede the
older duplicate archive.

Before visual implementation, read `10-ELEMENTOR-DESIGN-SYSTEM.md`, then
`07-NOVAMIRA-ELEMENTOR-WORKFLOW.md`. Do not invent a parallel token or class
system.

## Product

Build a WordPress and Elementor Pro site for Poolhall Recruitment that matches
https://poolhall.vercel.app/ visually, fixes its responsive issues, migrates the
current Wix content and gives Poolhall first-party, indexable job pages.
It also includes a secure candidate portal with saved jobs, alerts, profile/CV
management and evidence-based application history.

## Architecture

- Hello Elementor child theme: minimal shared CSS, assets and fallbacks.
- Elementor Pro: Theme Builder templates and editable marketing content.
- Novamira: local/staging implementation and inspection only; never production.
- Custom plugin: jobs, Giig, applications, reviews, schema, admin health and
  candidate accounts plus custom Elementor dynamic tags/widgets.
- Jobs are cached locally as `poolhall_job`.
- No live Giig request during public page rendering.
- All ATS code sits behind `JobSource` and `ApplicationSink`.

## Hard rules

1. Never silently lose an application.
2. Never mass-expire jobs after a failed/incomplete source fetch.
3. Never commit secrets.
4. Never put real candidate data/CVs on local or staging.
5. Never proxy the undocumented Giig hosted `/Submit` form.
6. `Save job` must use the authenticated saved-jobs service, never local-only
   browser state.
7. Use real URLs, not prototype `#` links.
8. Preserve blog, Better Job Adverts, Join Our Team and legal URLs.
9. JobPosting schema appears only when the hiring organization and other fields
   can be represented accurately.
10. Mobile must have no horizontal overflow at 360px.
11. Back up `_elementor_data` before a Novamira/Elementor data edit.
12. Regenerate Elementor CSS/data, clear Elementor caches and verify the
    frontend after every template or shared-class change.
13. Never install or connect Novamira on production.
14. Candidate accounts use native WordPress authentication and the
    `poolhall_candidate` role; never use Giig credentials for site login.
15. Do not claim a hosted Giig redirect is a completed application.
16. Never expose candidate account pages to public caches or search indexing.
17. Require reauthentication for email, password, CV deletion and account
    deletion changes.
18. Do not launch without the independent penetration-test closure evidence
    required by `09-PENETRATION-TEST-PLAN.md`.

## Giig facts to verify in Phase 1

- Public jobs: `/public/api/v1/job/getjobs`
- Single job: `/public/api/v1/job/get`
- Candidate create: `/public/api/v1/candidate`
- Applicant submit: `/public/api/v1/applicant/submit`
- Docs conflict between `Access-Session-Type` and `Access-Secret-Key`.
- No public CV upload endpoint was documented on 2026-06-10.

## Application modes

- Mode A: first-party form only after supported CV upload is proven.
- Mode B: link Apply to the exact Giig hosted job page.
- Feature-flag the first-party apply route until the mode is locked.
- Mode A application history may show `Submitted` only after Giig acknowledges
  the submission.
- Mode B shows `Redirected to Giig` under activity, not applications.

## Candidate portal

- Read `08-CANDIDATE-PORTAL-SPEC.md`.
- Verified accounts can save jobs, create alerts and manage profile/security.
- Guest applications remain possible in Mode A and can be securely claimed
  after email verification.
- Recommendations are deterministic from profile/saved-job preferences, not AI.
- One encrypted reusable CV is allowed only when Mode A is proven.

## Design

- Source Serif 4 display type.
- Hanken Grotesk body type.
- Navy `#14233F`, orange `#EC6F1E`, paper `#F7F8FA`, ink `#16202F`.
- 1152px/72rem standard container, 16px card radius and fluid section spacing.
- Match the prototype's desktop character.
- Add a real mobile menu and mobile filter drawer.
- Read `10-ELEMENTOR-DESIGN-SYSTEM.md` before building any visual template.
- Use v4 Atomic for normal structure/content and v3 only for Loop
  Grid/Carousel/Loop Items or documented compatibility exceptions.
- Use `clamp()` for bounded fluid sizing and `svh`/`dvh`/`vw` only under the
  viewport-unit rules in the design system.

## Required verification

- Unit tests for normalizers, salary parsing, expiry and schema.
- Integration tests for sync and application queue.
- Integration tests for registration, verification, saved jobs, alerts,
  application claims, access control and account deletion.
- Browser tests for Home -> Jobs -> Job -> Apply and Employer -> Enquiry.
- Browser tests for Register -> Verify -> Save -> Dashboard -> Alert -> Logout.
- Rich Results Test fixtures for onsite, hybrid and remote jobs.
- Accessibility and responsive checks in `04-ACCEPTANCE-QA.md`.
- Elementor/Novamira checks in `07-NOVAMIRA-ELEMENTOR-WORKFLOW.md`.
- Design-system checks in `10-ELEMENTOR-DESIGN-SYSTEM.md`.
- Internal ASVS checks and the independent test/retest in
  `09-PENETRATION-TEST-PLAN.md`.

## Definition of done

All checks in `04-ACCEPTANCE-QA.md` pass on staging and production smoke tests.
