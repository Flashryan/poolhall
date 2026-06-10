# Poolhall Recruitment - One-Shot Build Handoff

This folder is the authoritative build pack for the full WordPress replacement.
It supersedes the duplicate and conflicting files in the supplied archive.

## Source priority

When sources disagree, use this order:

1. `00-ONE-SHOT-BUILD-BRIEF.md`
2. `10-ELEMENTOR-DESIGN-SYSTEM.md` for visual and Elementor implementation
3. The live prototype: https://poolhall.vercel.app/
4. The current Wix site for content and legacy URLs: https://www.poolhallrecruitment.co.uk/
5. Current official Giig, Google, WordPress, Elementor and security guidance
6. The original archive, only for background and commercial history

## Files

- `00-ONE-SHOT-BUILD-BRIEF.md` - locked product scope and decisions.
- `01-UX-UI-CONTENT-SPEC.md` - routes, screens, states, responsive rules, copy and design tokens.
- `02-TECHNICAL-ARCHITECTURE.md` - WordPress architecture, Giig, applications, Google Jobs, reviews, security and operations.
- `03-BUILD-PLAN.md` - build order, gates and deliverables.
- `04-ACCEPTANCE-QA.md` - testable definition of done.
- `05-MIGRATION-AND-LAUNCH-INPUTS.md` - legacy URL preservation, content migration and launch-only inputs.
- `06-SOURCES-AND-FINDINGS.md` - dated evidence behind the decisions.
- `07-NOVAMIRA-ELEMENTOR-WORKFLOW.md` - exact Elementor ownership, Novamira edit loop, cache regeneration and deployment controls.
- `08-CANDIDATE-PORTAL-SPEC.md` - candidate identity, dashboard, saved jobs, alerts, applications, profile, CV and privacy behavior.
- `09-PENETRATION-TEST-PLAN.md` - authorised test scope, test script, evidence, remediation, retest and launch gate.
- `10-ELEMENTOR-DESIGN-SYSTEM.md` - prototype-derived variables, Atomic classes, components, loop templates, fluid sizing and responsive rules.
- `CLAUDE.md` - concise standing context for the coding agent.

## Non-negotiable decisions

- Build Option B: owned WordPress site with locally cached job posts.
- Match the prototype's visual direction; do not reproduce its mobile layout bugs.
- Build presentation with Elementor Pro Theme Builder on a Hello Elementor child
  theme.
- Use Elementor v4 Atomic elements/classes/components for normal page
  construction and v3 Loop Grid/Loop Items for dynamic WordPress collections.
- Use Novamira only on local/staging, with a backup before every Elementor data
  change.
- Keep Giig, jobs, applications, schema and custom Elementor data widgets in a
  version-controlled plugin.
- Do not depend on a live Giig request to render a public page.
- Candidate accounts use native WordPress identity with a frontend-only
  `poolhall_candidate` role; Giig is not the login provider.
- Saved jobs, alerts and dashboard features require a verified account.
- Application history may show only states proven by Poolhall or a supported
  Giig API. A hosted Apply redirect is not a confirmed application.
- An independent penetration test and remediation retest are mandatory before
  production launch.
- Never silently lose an application.
- Use supported Giig endpoints only. Do not proxy the undocumented hosted
  `careers.giighire.com/Submit` form.
- Replace the prototype's non-functional `Save job` action with the authenticated
  saved-jobs feature.
- Preserve the existing blog, Better Job Adverts offer, join-the-team page and
  legal/compliance URLs.
- Do not launch until the application/CV route has been proven with Giig.

## Build-ready status

Implementation can begin immediately. Items in
`05-MIGRATION-AND-LAUNCH-INPUTS.md` are launch gates or content confirmations,
not reasons to delay the technical build.
