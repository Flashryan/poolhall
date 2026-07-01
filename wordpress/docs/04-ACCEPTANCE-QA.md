# Acceptance and QA Checklist

## 1. Global

- [ ] Every navigation item has a real URL.
- [ ] Hello Elementor child theme is active.
- [ ] Elementor Pro Theme Builder owns global and content templates.
- [ ] Novamira is connected only to local/staging.
- [ ] Site Settings and named global classes implement the design tokens.
- [ ] The staging style-guide page represents every shared visual state.
- [ ] Normal page structure/content uses Elementor v4 Atomic elements.
- [ ] v3/classic usage is limited to Loop Grid/Carousel/Loop Items and
  documented compatibility exceptions.
- [ ] v4 Variables are synchronized to v3 Global Colors/Fonts.
- [ ] No critical functional JavaScript or credentials live in HTML widgets.
- [ ] Header, footer and primary CTA are consistent across templates.
- [ ] No placeholder `#` links remain.
- [ ] No horizontal scroll at 360, 390, 768, 1024, 1280 or 1440 widths.
- [ ] Browser zoom at 200% remains usable.
- [ ] Keyboard-only navigation reaches all controls.
- [ ] Focus is always visible.
- [ ] Skip link works.
- [ ] One H1 per page.
- [ ] 404 page provides Jobs and Contact actions.

## 2. Home

- [ ] Search submits to `/jobs/` using query parameters.
- [ ] Live-role count comes from published local jobs.
- [ ] Featured jobs exclude expired roles.
- [ ] Jobs carousel works with touch, mouse and keyboard.
- [ ] Reviews carousel does not autoplay.
- [ ] Employer CTA opens `/employers/`.

## 3. Jobs sync

- [ ] New Giig job creates one WordPress job.
- [ ] Updated Giig job updates the same post.
- [ ] Repeated sync creates no duplicate.
- [ ] Removed source job is unpublished only after a complete successful fetch.
- [ ] Failed/incomplete fetch does not expire existing jobs.
- [ ] 30-day expiry and admin extension behave as specified.
- [ ] Three consecutive failures send one actionable alert.
- [ ] Manual sync requires the correct capability and nonce.
- [ ] Sync logs contain no secrets or candidate PII.

## 4. Jobs archive

- [ ] Keyword searches title and description.
- [ ] Location filter works.
- [ ] Sector filter works.
- [ ] Job type filter works.
- [ ] Work mode filter works.
- [ ] Minimum salary filter works for ranges and fixed salaries.
- [ ] Sort options are correct.
- [ ] Pagination retains filters.
- [ ] Browser Back/Forward restores state.
- [ ] Clear filters resets URL and controls.
- [ ] Empty state is useful.
- [ ] Mobile filter drawer traps focus and closes with Escape.

## 5. Single job

- [ ] Canonical URL is unique and stable.
- [ ] Visible data matches the source record.
- [ ] Description HTML is sanitized.
- [ ] Salary period/currency are correct.
- [ ] Apply action uses the configured mode.
- [ ] Save/unsave works for a verified candidate.
- [ ] Signed-out Save returns through authentication to the same role.
- [ ] Saved state is consistent on card, detail and dashboard.
- [ ] Similar jobs never include the current or expired job.
- [ ] Expired job returns 410 or approved alternative.
- [ ] Expired job has no JobPosting schema.

## 6. Application Mode A

- [ ] First/last name, email, phone, role title and town validation work.
- [ ] Consent is required.
- [ ] PDF upload succeeds.
- [ ] DOCX upload succeeds.
- [ ] Disallowed extension is rejected.
- [ ] Spoofed MIME is rejected.
- [ ] Oversize file is rejected.
- [ ] Duplicate submit creates one application.
- [ ] Candidate and applicant link to the correct Giig job.
- [ ] CV appears in the supported Giig location.
- [ ] Forced timeout queues the application.
- [ ] Retry succeeds without duplicate applicant creation.
- [ ] Temporary/local PII is deleted after success.
- [ ] Confirmation contains no sensitive data.

## 7. Application Mode B

- [ ] Every Apply link uses the correct Giig job ID/URL.
- [ ] Removed Giig jobs cannot be linked from live pages.
- [ ] `directApply` is false.
- [ ] No application-form PII or CV is stored solely because of a Mode B
  redirect; separately consented account/profile data is unaffected.

## 8. Contact and employer forms

- [ ] Required fields and consent validate.
- [ ] Turnstile failure is handled accessibly.
- [ ] Honeypot submissions are discarded.
- [ ] Rate limit returns a helpful message.
- [ ] Notifications route to the configured inbox.
- [ ] Reply-To uses the submitter email after validation.
- [ ] Email delivery failure is logged and alerted.
- [ ] Success pages are noindex.

## 9. Google Jobs

- [ ] Schema appears only on eligible single-job pages.
- [ ] Schema values match visible values.
- [ ] Hiring organization is accurate.
- [ ] Onsite fixture passes Rich Results Test.
- [ ] Hybrid fixture passes Rich Results Test.
- [ ] Fully remote fixture passes Rich Results Test.
- [ ] Salary range uses `minValue`/`maxValue`.
- [ ] `validThrough` matches local expiry.
- [ ] Jobs sitemap contains only live canonical jobs.
- [ ] Publish/update queues URL_UPDATED.
- [ ] Expiry/removal queues URL_DELETED.
- [ ] Indexing failure does not block publishing.

## 10. Google Reviews

- [ ] API key is server-side only.
- [ ] Field mask includes only required fields.
- [ ] Maximum five reviews are handled.
- [ ] Reviewer name is adjacent to review text.
- [ ] Author/profile/photo attribution is shown when supplied.
- [ ] Google and third-party attribution is shown.
- [ ] Google Maps link works.
- [ ] 24-hour cache works.
- [ ] Stale cache is served during API failure.
- [ ] Carousel hides cleanly when no cache exists.

## 11. Content and migration

- [ ] Services content includes permanent, temp-to-perm and retained support.
- [ ] Better Job Adverts has a dedicated page.
- [ ] Join Our Team preserves employed and partner offers.
- [ ] Team details and contact links are current.
- [ ] All Wix blog posts are migrated.
- [ ] Published dates and featured images are preserved.
- [ ] Legal/compliance pages are present.
- [ ] Existing URLs return 200 or a single 301 to a relevant page.
- [ ] No redirect chains or loops.
- [ ] No old Wix asset is required for critical rendering after launch.

## 12. Accessibility

- [ ] Automated scan has no serious/critical violations.
- [ ] Forms have programmatic labels and error relationships.
- [ ] Modal/drawer focus behavior works.
- [ ] Carousels expose purpose and control names.
- [ ] Contrast passes AA.
- [ ] Touch targets are at least 44 x 44px.
- [ ] Reduced-motion preference is respected.
- [ ] Screen-reader smoke test covers Home, Jobs, Single Job, Apply, Contact,
  candidate auth, Dashboard and Saved Jobs.

## 13. Performance and resilience

- [ ] Production mobile Lighthouse meets agreed thresholds.
- [ ] LCP image is optimized and preloaded only when appropriate.
- [ ] Fonts do not block rendering excessively.
- [ ] No Giig or Places browser-side request is required to render.
- [ ] Page cache works with jobs invalidation.
- [ ] Backup restoration has been tested on staging.
- [ ] Cron health is visible.
- [ ] Site remains usable during Giig and Places outages.

## 14. Launch

- [ ] Production backup taken.
- [ ] Independent penetration test final report received.
- [ ] Independent remediation retest/closure letter received.
- [ ] No release-blocking penetration-test finding remains open.
- [ ] Novamira users, tokens and connectors are absent from production.
- [ ] Elementor CSS/data was regenerated after the production migration.
- [ ] Theme Builder display conditions were checked on production.
- [ ] DNS rollback values recorded.
- [ ] SSL valid.
- [ ] Forms tested with approved synthetic data.
- [ ] Search Console verified.
- [ ] Sitemap submitted.
- [ ] GA4 fires only under the approved consent state.
- [ ] Robots/noindex rules checked.
- [ ] Development access tooling removed from production.
- [ ] Poolhall owns all accounts and credentials.

## 15. Elementor and Novamira workflow

- [ ] Every changed template/page has a dated `_elementor_data` backup.
- [ ] The exact template, element and shared-class IDs were inspected before
  editing.
- [ ] Shared-class changes were audited across all references.
- [ ] Elementor element, CSS and page-asset caches were cleared.
- [ ] Elementor CSS/data regeneration completed without errors.
- [ ] Host/object/CDN caches were purged where applicable.
- [ ] Shared templates were browser-verified at desktop and mobile widths.
- [ ] Elementor editor and frontend output match.
- [ ] A representative Elementor template restore was tested on staging.
- [ ] Every Loop Item has a template ID, usage inventory and backup.
- [ ] A Loop Item change was verified in every grid/carousel that consumes it.
- [ ] Shared variables/classes were audited before modification.

## 15A. Design system

- [ ] Standard content/navigation container is 72rem/1152px.
- [ ] Fluid typography and spacing use bounded `clamp()` formulas.
- [ ] Mobile-safe hero/drawer sizing uses `svh`/`dvh`, not unbounded `100vh`.
- [ ] No production text uses an unbounded viewport-only font size.
- [ ] Colors, type, spacing, radii, shadows and controls match
  `10-ELEMENTOR-DESIGN-SYSTEM.md`.
- [ ] Buttons include hover, focus, active, disabled and loading states.
- [ ] Forms include default, focus, invalid, disabled, submitting, success and
  service-failure states.
- [ ] Job, blog, sector, team and review Loop Items match their component
  contracts.
- [ ] Candidate-private lists are plugin widgets, not v3 Loop Grids.
- [ ] Iconography uses one approved outline family.
- [ ] Long titles, emails, empty data and errors do not overflow.
- [ ] No unexplained raw brand color, font or spacing values remain in
  templates.

## 16. Candidate authentication

- [ ] Candidate registration stores the correct role and no admin capability.
- [ ] Verification email token is hashed, expiring and single-use.
- [ ] Verification resend is throttled and invalidates prior tokens.
- [ ] Login, registration and reset responses do not enumerate accounts.
- [ ] Password-reset token is expiring and single-use.
- [ ] Password reset invalidates existing sessions.
- [ ] Candidate cannot access `/wp-admin/` or see the admin toolbar.
- [ ] Return URLs cannot redirect off-site.
- [ ] Auth/account routes are noindex and absent from sitemaps.
- [ ] Private responses bypass page, object-fragment and CDN shared caches.
- [ ] Rate limiting and Turnstile policy work without blocking normal recovery.

## 17. Candidate authorization and security

- [ ] Candidate A cannot read or mutate Candidate B profile, saves, alerts,
  applications, CV, sessions, export or deletion request.
- [ ] REST/AJAX mutations require cookie auth, valid nonce and ownership checks.
- [ ] Sensitive changes require current-password reauthentication.
- [ ] Email change verifies the new address and notifies the old address.
- [ ] Candidate can list and revoke active sessions.
- [ ] Logs and analytics contain no password, token, CV or direct candidate PII.
- [ ] Staff cannot view passwords or impersonate candidates.

## 18. Dashboard and saved jobs

- [ ] New-account, empty and partially-complete dashboard states are useful.
- [ ] Recommendations explain the matching preference and use no AI score.
- [ ] Recommendations exclude expired and already-applied jobs.
- [ ] Save/unsave requests are idempotent.
- [ ] Saved jobs survive normal job updates and use stable source IDs.
- [ ] Expired saved jobs remain labelled until removed and cannot be applied to.
- [ ] Clear-expired action requires confirmation.
- [ ] Save controls announce state changes accessibly.

## 19. Job alerts

- [ ] Alert can be created from current search filters.
- [ ] Immediate, daily and weekly schedules produce the expected matches.
- [ ] One alert/job/frequency window sends at most once.
- [ ] Expired/unpublished jobs are never included.
- [ ] Temporary mail failures retry without duplicate email.
- [ ] Candidate can edit, pause, resume and delete an alert.
- [ ] Per-alert unsubscribe and unsubscribe-all work without login.
- [ ] Alert consent remains separate from general marketing consent.
- [ ] Maximum active-alert limit is enforced accessibly.

## 20. Candidate applications and CV

- [ ] Mode A shows Submitted only after Giig acknowledgement.
- [ ] Mode A queued/failure states use Processing or Needs attention correctly.
- [ ] Mode B shows Redirected to Giig as activity, not a submitted application.
- [ ] Unsupported shortlisted/rejected/interview/hired labels never appear.
- [ ] Guest history claim requires a single-use link to the same verified email.
- [ ] Active CV is enabled only in Mode A.
- [ ] CV extension, MIME, size and authorization checks pass.
- [ ] CV is encrypted outside public uploads and has no predictable URL.
- [ ] Candidate can replace, authorized-download and delete the active CV.
- [ ] Replacement/deletion leaves no orphaned old file.

## 21. Candidate privacy and email

- [ ] Consent versions and timestamps are recorded.
- [ ] Data export is generated asynchronously and delivered by expiring link.
- [ ] Export contains only the requesting candidate's appropriate data.
- [ ] Account deletion revokes sessions and disables alerts immediately.
- [ ] Deletion grace/cancellation and completion behave as configured.
- [ ] Site CV/profile/saved/alert data is deleted or anonymized as documented.
- [ ] UI explains that Giig-held data requires a separate confirmed process.
- [ ] Verification, reset, security, alert and privacy emails have plain-text
  alternatives and no sensitive attachment.
- [ ] Hard bounce/complaint pauses alerts without suppressing password/security
  messages.
- [ ] Production sender passes SPF, DKIM and DMARC-aligned delivery checks.

## 22. Penetration-test gate

- [ ] Written authorisation and rules of engagement were signed before testing.
- [ ] Scope includes public site, candidate portal, custom REST/AJAX endpoints,
  WordPress/Elementor configuration, CV storage and email workflows.
- [ ] Anonymous, unverified, Candidate A, Candidate B, staff and administrator
  test accounts were supplied with synthetic data.
- [ ] Cross-account access was tested for profile, saves, alerts, application
  history, CVs, sessions, exports and deletion requests.
- [ ] Authentication, recovery, verification, session and rate-limit controls
  were manually tested.
- [ ] File-upload, private-file download and direct-object access were tested.
- [ ] Business-logic abuse, replay, duplicate submission, alert abuse and
  application-history claims were tested.
- [ ] API authorization, mass assignment, excessive data exposure, CORS, CSRF,
  caching and resource-consumption controls were tested.
- [ ] WordPress, Elementor, plugin, host and security-header configuration were
  reviewed.
- [ ] Findings include reproducible evidence, affected assets, business impact,
  severity and remediation guidance.
- [ ] Critical findings were notified immediately and testing stopped where the
  rules of engagement required it.
- [ ] All required fixes were independently retested.
- [ ] Production received only authorised low-impact delta verification.
