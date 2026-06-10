# Penetration Test Plan and Release Gate

This plan is part of the build scope. It defines an authorised, independently
executed penetration test for the completed Poolhall WordPress, Elementor,
candidate portal and custom-plugin build.

A penetration test reduces uncertainty; it does not certify that a system is
invulnerable. Secure development, review, monitoring, patching and incident
response remain required after the test.

## 1. Standards and assurance target

The tester must use:

- OWASP Application Security Verification Standard 5.0.0, Level 2
- OWASP Web Security Testing Guide stable guidance, plus current relevant tests
- OWASP Top 10:2025
- OWASP API Security Top 10:2023
- WordPress-specific configuration, plugin and role/capability testing
- Tester judgement for business logic unique to Poolhall, Giig and candidate
  accounts

The engagement must be manually led. Automated DAST, dependency and
configuration scanners provide supporting coverage only.

## 2. Independence and competence

- The lead tester must not be the person who implemented the tested controls.
- Use a reputable independent application-security tester with demonstrable web,
  API, WordPress and authenticated-portal experience.
- The supplier signs confidentiality and data-processing terms appropriate for
  synthetic candidate data and security evidence.
- Record tester names, organisation, experience, dates and testing source IPs.
- Poolhall retains the final report and closure letter.

## 3. Required authorisation

Before testing, Poolhall, the host and the tester sign written rules of
engagement containing:

- Exact in-scope domains, subdomains, IPs and API base paths
- Staging and approved production-check windows with timezone
- Approved source IPs and user agents
- Named business, technical, hosting and emergency contacts
- Test-account roles and synthetic data
- Permitted active techniques
- Prohibited techniques
- Data handling, evidence retention and destruction terms
- Critical-finding notification method
- Stop/pause conditions
- Liability, safe-harbour and third-party boundaries

No active testing starts without written authorisation.

## 4. Environments

### Primary test environment

Use a production-like staging release candidate with:

- Final child theme, custom plugin and Elementor templates
- Production-equivalent WordPress/PHP versions and configuration
- Production-equivalent CDN/cache/security headers
- Production-equivalent private CV storage pattern
- Synthetic Giig responses/test credentials
- Transactional mail sandbox or controlled test inboxes
- Turnstile test configuration
- No real candidate records, CVs or production secrets

Freeze application code and configuration during the main test except for an
emergency security fix agreed with the tester. Record the commit/release ID and
configuration snapshot.

### Production

Production testing is limited to explicitly approved, low-impact delta checks:

- TLS and security headers
- Public information/configuration exposure
- Authentication and private-route cache behavior using test accounts
- Authorization regression checks that cannot affect another user
- Private-file access controls using tester-owned synthetic files
- Confirmation that debug/development access and Novamira are absent

No production active scan, password spraying, volume test, destructive upload,
email flood or third-party integration attack is permitted without a separate
written approval.

## 5. In-scope assets

- Public WordPress pages and job routes
- Candidate registration, verification, login, reset and logout
- Candidate dashboard, saved jobs, alerts, profile, CV, sessions and privacy
- Application Mode A and Mode B behavior
- Custom REST, AJAX, form and download endpoints
- WordPress roles, capabilities, admin restrictions and user exposure
- Elementor-rendered forms/templates and custom widgets
- Job sync/admin controls available to authorised staff
- File upload, encrypted storage and authorized download path
- Transactional email queue, unsubscribe and claim links
- Cache/CDN interaction with authenticated state
- Security headers, cookies, CORS, TLS and error handling
- Custom plugin dependencies and exposed configuration

Third-party systems are in scope only at the Poolhall integration boundary:

- Giig
- Google APIs
- Transactional email provider
- Cloudflare Turnstile/CDN
- Hosting/object storage

The tester may inspect Poolhall's request construction, credential handling,
validation, response handling and failure behavior. The tester must not attack
or scan a third-party provider.

## 6. Explicit exclusions

Unless separately approved in writing:

- Denial of service, stress or volumetric testing
- Destructive database/file operations
- Social engineering, phishing or physical security
- Malware deployment or persistence
- Testing with real candidate data
- Testing unrelated customer sites on shared infrastructure
- Attacks against Giig, Google, email, Cloudflare or host infrastructure
- Viewing more personal data than needed to prove an issue
- Downloading another candidate's real CV
- Publishing vulnerability details before remediation

A tester may use a safe proof against tester-owned synthetic accounts/files to
demonstrate impact.

## 7. Test identities and data

Provide:

- Anonymous visitor
- Unverified candidate
- Verified Candidate A
- Verified Candidate B
- Candidate pending deletion
- Candidate with saved jobs, alerts, application history and synthetic CV
- Recruitment support user
- WordPress administrator

Use unique synthetic inboxes and documents. Candidate A and Candidate B are
required for horizontal authorization testing. Staff/admin accounts are
required for vertical authorization testing.

## 8. Test execution script

Each case records: test ID, timestamp, tester, role, endpoint, request summary,
expected result, actual result, evidence reference and finding ID.

### PT-01 Reconnaissance and exposure

1. Enumerate only the authorised hosts, routes and APIs.
2. Review robots, sitemaps, headers, source maps, comments and client assets.
3. Check for debug output, directory listing, backup/configuration files,
   exposed logs, version leakage and unnecessary WordPress endpoints.
4. Confirm candidate users are absent from author archives, user sitemaps and
   public REST responses.
5. Verify Novamira, development users and temporary Elementor backups are not
   exposed on production.

Pass: no secret, private file, candidate identity, backup or actionable debug
information is publicly exposed.

### PT-02 Transport, headers, cookies and caching

1. Assess TLS configuration and HTTP-to-HTTPS behavior.
2. Verify auth/session cookies use appropriate Secure, HttpOnly and SameSite
   attributes.
3. Review HSTS, CSP, frame, MIME-sniffing, referrer and permissions policies.
4. Test CORS behavior for public and authenticated endpoints.
5. Verify candidate routes and responses bypass shared caches.
6. Confirm one candidate's header/dashboard state cannot be served to another
   user or an anonymous visitor.

Pass: transport and browser controls protect authenticated/private content with
no cross-user cache leakage.

### PT-03 Identity and account lifecycle

1. Test registration, duplicate email, verification resend and expiry.
2. Compare browser responses and timing for known/unknown accounts.
3. Test login throttling and controls against distributed/alternate endpoint
   bypass where safely possible.
4. Test reset-token entropy, expiry, reuse, invalidation and account enumeration.
5. Test verification, claim and unsubscribe tokens for tampering and replay.
6. Test return URLs for open redirect.
7. Verify password/email changes and recovery rotate sessions as specified.
8. Verify deleted/suspended/unverified candidates cannot access verified routes.

Pass: no account enumeration, token replay, authentication bypass, unsafe
redirect or session persistence after revocation.

### PT-04 Session management and CSRF

1. Test session fixation before/after login and privilege change.
2. Test logout and individual/all-session revocation.
3. Test authenticated state-changing requests without, with invalid and with
   another session's nonce.
4. Confirm nonces are backed by capability and ownership checks.
5. Test sensitive-action reauthentication timeout and bypass.
6. Test concurrent sessions and stale browser pages after password recovery.

Pass: sessions rotate/revoke correctly and no cross-site or replayed request can
perform an unauthorised state change.

### PT-05 Horizontal and vertical authorization

For Candidate A and Candidate B, change identifiers and request context for:

- Profile
- Saved jobs
- Job alerts and delivery history
- Application/activity history
- CV metadata, download, replacement and deletion
- Active sessions
- Data export
- Deletion request/cancellation
- Guest application claim

Then test candidate access to staff/admin endpoints and support-user access to
administrator-only operations.

Pass: every cross-account or over-privileged request is denied without leaking
whether the target object exists.

### PT-06 API and endpoint security

1. Inventory custom REST/AJAX/form endpoints and allowed methods.
2. Test object-level and property-level authorization.
3. Test mass assignment of role, verification, consent, status, source IDs and
   ownership fields.
4. Test excessive data exposure in JSON, errors and pagination.
5. Test unsupported methods/content types and malformed JSON/multipart bodies.
6. Test rate limits and resource consumption for login, registration, reset,
   save, alert, export, deletion, email and application actions.
7. Test CORS, CSRF, cache and nonce behavior.
8. Confirm no endpoint accepts credentials or tokens in insecure URLs/loggable
   locations where avoidable.

Pass: endpoints expose only necessary data and enforce authentication,
authorization, validation and bounded resource use.

### PT-07 Input validation and injection

Test relevant parameters and stored/displayed fields for:

- SQL injection
- Stored/reflected/DOM cross-site scripting
- HTML injection
- Header/email injection
- Path traversal
- Server-side request forgery where a server fetches a user-influenced URL
- Command/template injection where applicable
- Malformed Unicode, oversized values and parser ambiguity

Include job content received from Giig and rendered through `wp_kses`.

Pass: input is typed/validated, output is contextually escaped and no injected
content executes or changes backend queries/commands.

### PT-08 CV and file handling

Using tester-owned files:

1. Test allowed and disallowed extensions, MIME spoofing and double extensions.
2. Test malformed, oversized, empty and password-protected documents.
3. Test filename/path traversal and special-character handling.
4. Test direct URL guessing, object-ID tampering and expired download links.
5. Confirm files cannot execute or render active content from the application
   origin.
6. Confirm replacement/deletion removes old files and references.
7. Confirm malware-scanner failure follows the configured fail-safe behavior.
8. Verify logs, emails, exports and errors do not expose storage paths or CV
   contents.

Pass: only valid documents are accepted, files remain private and lifecycle
operations cannot expose or orphan a CV.

### PT-09 Candidate business logic

1. Replay save/unsave and application requests.
2. Attempt duplicate application creation and idempotency bypass.
3. Attempt to claim another guest application.
4. Attempt Mode B status escalation from `Redirected to Giig` to `Submitted`.
5. Manipulate recommendation/profile criteria and source job IDs.
6. Attempt alert duplicate delivery, frequency bypass, email flood and
   unsubscribe tampering.
7. Race profile/CV replacement, deletion cancellation and account deletion.
8. Attempt actions against expired/unpublished jobs.

Pass: workflows preserve evidence, ownership and idempotency without duplicate
applications/emails or invented status.

### PT-10 WordPress, Elementor and plugin controls

1. Review core/theme/plugin versions and known-vulnerability exposure.
2. Review role/capability registration and changes across activation/update.
3. Test `/wp-admin/`, admin bar, XML-RPC and application-password exposure
   against the approved configuration.
4. Test custom widgets, shortcodes and dynamic tags for unauthorized data
   retrieval and escaping failures.
5. Review file editor, debug, cron, REST discovery and error configuration.
6. Confirm Elementor templates/data cannot expose credentials/private values.
7. Test authenticated headers/templates for cache variation failures.

Pass: WordPress and Elementor expose only required functionality and custom
extensions enforce the same security boundaries as direct endpoints.

### PT-11 Integrations and secret handling

1. Review where Giig, Google, Turnstile, mail and storage credentials reside.
2. Confirm secrets are absent from HTML, JavaScript, REST responses, logs,
   database exports and repository history.
3. Test outbound request allowlists, timeouts, TLS validation and response
   validation.
4. Test safe behavior for malformed, delayed, replayed and incomplete provider
   responses using controlled mocks.
5. Verify webhook signatures, replay protection and idempotency where used.
6. Confirm a provider failure cannot trigger mass job expiry, lost application,
   false status or repeated email.

Pass: secrets remain server-side and unsafe third-party data/failure cannot
break local authorization, integrity or availability controls.

### PT-12 Privacy, logs and operational controls

1. Review logs, analytics, exports, backups and admin screens for excessive PII.
2. Test export and deletion authorization, token handling and data isolation.
3. Verify account deletion does not falsely claim Giig erasure.
4. Test support actions for auditability and privilege boundaries.
5. Confirm security alerts include useful correlation data without secrets.
6. Verify backup/restore does not make private CVs publicly accessible.

Pass: private data is minimized, isolated and auditable throughout operational
workflows.

## 9. Automated supporting checks

Run against the authorised staging target and code release:

- Dependency/composer vulnerability scan
- WordPress core/theme/plugin known-vulnerability scan
- Secret scan of repository and build artifacts
- Static analysis and security-focused code review
- Passive DAST crawl
- Authenticated DAST using Candidate A only
- Header/TLS configuration scan
- Malware/file-upload control checks with harmless industry test files approved
  in the rules of engagement

Any active scanner must use bounded concurrency/rate, exclude logout/deletion
and destructive routes, and run only in the approved window. Scanner results
must be manually validated.

## 10. Finding and evidence standard

Every confirmed finding contains:

- Unique ID and title
- Affected host, route, role and release ID
- Severity using CVSS v4.0 plus Poolhall business/privacy impact
- Relevant OWASP ASVS/WSTG/API reference
- Preconditions
- Minimal reproducible steps
- Sanitized request/response evidence
- Impact on candidate data, applications, CVs, alerts or operations
- Root-cause assessment
- Specific remediation guidance
- Retest status

Evidence must minimize personal data, be encrypted in transit/at rest and be
destroyed under the agreed retention schedule.

## 11. Notification and stop conditions

Notify the emergency contact immediately for:

- Authentication bypass
- Administrator or host compromise
- Cross-candidate CV/application/profile access
- Remote code execution
- Production secret exposure
- Destructive or widespread data-integrity impact

Pause the affected test path if continued testing could expose real data,
degrade service, send uncontrolled email, alter Giig records or affect a third
party. The business/technical contact decides whether and how to resume.

## 12. Remediation service levels

- Critical: contain immediately; target fix within 24 hours
- High: target fix within 3 business days
- Medium: target fix within 10 business days
- Low: schedule with an owner and due date
- Informational: record and assess

Dates are maximum targets, not permission to leave an exploitable system
exposed. Internet-facing Critical/High issues trigger launch suspension or
rollback until contained.

## 13. Mandatory independent retest

- Retest every Critical, High and Medium finding.
- Retest related controls, not only the exact original request.
- Confirm fixes did not introduce a bypass or regression.
- Update each finding to fixed, partially fixed, not fixed or risk accepted.
- Issue a signed closure/retest letter tied to the production release candidate.

Developer screenshots or local tests do not replace independent retesting.

## 14. Release-blocking gate

Production launch is blocked while any of these remains:

- Any Critical or High finding
- Any Medium finding affecting authentication, authorization, candidate PII,
  CVs, application integrity, password recovery, session security, file access
  or secret exposure
- Any untested fix to a Critical, High or Medium finding
- Any unexplained cross-user cache leakage
- Missing final report or independent closure letter
- Staging/production security configuration materially differs without delta
  verification

Other Medium findings require documented owner, remediation date and explicit
written risk acceptance by Poolhall's authorised business owner and technical
lead. Low/informational findings require tracking.

## 15. Deliverables

- Signed authorisation and rules of engagement
- Asset/account/data inventory used for testing
- Test log mapped to PT-01 through PT-12
- Executive summary
- Full technical report
- Immediate Critical/High notifications
- Remediation tracker
- Independent retest report/closure letter
- Low-impact production delta-check record
- Evidence-destruction confirmation

## 16. Post-launch cadence

Repeat independent testing:

- At least annually
- After a major candidate-portal/authentication redesign
- After changing CV storage or application integration
- After a serious security incident
- After a material hosting/CDN/authentication architecture change

Continue dependency monitoring, patching, logs/alerts and focused regression
tests between full engagements.
