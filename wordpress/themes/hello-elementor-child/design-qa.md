# Poolhall v3 full-site design QA

Date: 2026-08-28
Environment: Poolhall Recruitment staging
Active design: `poolhall-human-marine-v3`
Project root: `wp-content/themes/hello-elementor-child`

## Source visual truth

- Canonical package: `/Volumes/Backup/Users/Poolhall Recruitment Design System (3).zip`
- Homepage reference: `/tmp/poolhall-plan.QzSCyY/screenshots/v3-home.png`
- About reference: `/tmp/poolhall-plan.QzSCyY/screenshots/about-check.png`
- Contact reference: `/tmp/poolhall-plan.QzSCyY/screenshots/contact.png`
- Jobs reference: `/tmp/poolhall-plan.QzSCyY/screenshots/jobs.png`
- Job interface reference: `/tmp/poolhall-plan.QzSCyY/screenshots/01-jobsingle.png`

The supplied package is authoritative for typography, palette, square geometry, spacing and component character. Existing staging copy, imagery, URLs and working flows were preserved where the static examples show different content.

## Implementation evidence

- About desktop: `/tmp/poolhall-after-about.png`
- Jobs desktop: `/tmp/poolhall-after-jobs-v2.png`
- Job detail desktop: `/tmp/poolhall-after-jobdetail-v2.png`
- Contact desktop: `/tmp/poolhall-after-contact-v2.png`
- Employers desktop: `/tmp/poolhall-after-employers-v2.png`
- Team desktop: `/tmp/poolhall-after-team.png`
- Desktop review grid: `/tmp/poolhall-desktop-qa-grid.png`
- Mobile jobs: `/tmp/poolhall-mobile-jobs.png`
- Mobile job detail: `/tmp/poolhall-mobile-jobdetail.png`
- Mobile contact: `/tmp/poolhall-mobile-contact.png`
- Mobile team: `/tmp/poolhall-mobile-team.png`
- Mobile review grid: `/tmp/poolhall-mobile-qa-grid.png`

## Viewports and normalization

- Desktop CSS viewport: 1185 x 760, device scale factor 1.
- Mobile CSS viewport: 390 x 844, device scale factor 1.
- About source and implementation: source 1185 x 745; implementation cropped from 1185 x 760 to 1185 x 745 for the combined view.
- Contact source and implementation: both 1185 x 760.
- Combined comparison files:
  - `/tmp/poolhall-compare-about.png`, 2355 x 745.
  - `/tmp/poolhall-compare-contact.png`, 2355 x 760.
  - `/tmp/poolhall-compare-jobdetail.png`, 2355 x 760.
- The jobs sample is a different content state, so it was used to judge component language rather than pixel position.

## State

- Public staging site behind its existing Hostinger preview access.
- Default, non-authenticated visitor state.
- Desktop header expanded.
- Mobile header collapsed.
- Jobs filter drawer and primary navigation tested open and closed.
- Job application modal tested open and closed without submitting data.

## Full-view comparison evidence

The combined about comparison confirms the Plus Jakarta Sans hierarchy, deep marine hero, gold details, warm editorial ground, square geometry and human photographic treatment match the approved direction. Existing hero imagery is intentionally preserved.

The contact comparison confirms the marine and gold balance, clear lead hierarchy and two-column contact/form composition. The implementation keeps the existing founder image while the static example uses a solid field.

The supplied jobs references and rendered jobs archive share the same search-first hierarchy, restrained cards, gold rules, navy display type and compact metadata. Live staging content and filters remain authoritative.

## Focused region evidence

Focused checks covered the header and CTA cluster, interior hero labels and title wrapping, job search and filter controls, job cards, contact and registration fields, job-detail aside, mobile fixed apply bar, team portrait cards and legal page headings. These regions were readable in the full-view and mobile grid captures, so no additional density-normalized crops were required.

## Required fidelity surfaces

- Fonts and typography: Plus Jakarta Sans is used for display type, Inter for body and data, and Playfair Display italic for editorial accents. Elementor v3 presets and v4 variables/classes are aligned.
- Spacing and layout rhythm: section spacing follows the v3 clamp, interior heroes were reduced after the first pass, and cards/fields use square geometry.
- Colors and visual tokens: marine, deep marine, gold, paper, sand, ink and muted tokens are active across every public page.
- Image quality and assets: existing real Poolhall and sector photography is preserved with corrected crops and square treatments. No placeholder imagery or code-drawn assets were introduced.
- Copy and content: staging copy, links, live vacancies, form fields and sector data are unchanged except for the previously approved homepage accent markup and number-label cleanup.

## Comparison history

### Iteration 1

Finding: P2, generic interior heroes were too tall at the 1185 x 760 comparison viewport and pushed the contact form too far below the first view.

Fix: reduced the generic pagehead height from a 38vw scale to a 32vw scale, tightened hero padding and reduced the contact section inset.

Post-fix evidence: `/tmp/poolhall-after-contact-v2.png` and `/tmp/poolhall-desktop-qa-grid.png`.

Result: resolved.

### Iteration 2

No actionable P0, P1 or P2 mismatches remained. Remaining differences are intentional content and imagery preservation from the live Elementor site.

## Route and interaction QA

- 28 public routes checked at 1185 x 760 and 390 x 844.
- No horizontal overflow, missing primary heading or broken image was found.
- Homepage hero carousel and featured-job carousel remain functional.
- Mobile navigation opens and closes.
- Mobile job filters open and close.
- Live job links remain intact.
- Job application modal opens with its expected fields and closes without submission.
- Contact and registration forms retain their original inputs and validation surfaces.
- Console check found only a browser-extension asynchronous channel message and no site JavaScript failure.

## Design pre-flight

`novamira/check-design` returns `ok: true` for the full v3 rollout CSS. The only warnings are intentional extended ramp colours supplied by the design package: `#123255`, `#D7D9D6`, `#E4E2DC`, `#FECF87`, `#FCEFD7`, `#FEF8EC`, `#DEDAD3` and `#C8C1B7`.

## Rollback

- Original private backup: `/home/u232776807/domains/lightslategrey-hare-335761.hostingersite.com/novamira-private-backups/poolhall-reskin-20260828-210119`
- Pre-rollout stylesheet snapshot: `wp-content/themes/hello-elementor-child/rollout-backups/shared.css.pre-full-rollout-20260828`

The original private package contains the pre-change child-theme ZIP and targeted Elementor/WordPress snapshot. A full SQL dump was unavailable because the hosting environment disables process execution.

## Findings

No actionable P0, P1 or P2 findings remain.

## Follow-up polish

P3 only: final copyediting and client-selected photography swaps can be handled after stakeholder review without changing the system architecture.

final result: passed
