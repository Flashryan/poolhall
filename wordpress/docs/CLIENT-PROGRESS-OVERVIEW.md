# Poolhall Recruitment — New Website Progress Overview

**Prepared for the client meeting · 15 June 2026**

This is a plain-English summary of where the new Poolhall Recruitment
website stands. A working preview is live and clickable now; the remaining
items are mostly things only Poolhall can provide (logins, a couple of
decisions, and some content to confirm), not large pieces of build work.

**Preview (staging) site:** http://lightslategrey-hare-335761.hostingersite.com/

> Note: the preview currently shows a handful of **sample jobs and sample
> reviews** so every page can be seen working. These are placeholders for
> demonstration only and are replaced with real data once the items in
> "What we need from you" are in place.

---

## The headline

The website is **built and working end to end on the preview site** — the
full candidate journey, the employer journey, the marketing pages and the
secure candidate accounts area are all in place and match the agreed
design. What's left before going live is largely **switching on the live
job feed, a few content confirmations, and final launch checks** — not
new construction.

---

## What's been done

**The whole site is designed and built to the agreed brand**
- Navy / orange / gold colours, serif headlines, clean modern layout.
- Works properly on mobile, tablet and desktop, and meets accessibility
  standards.

**Candidate journey**
- **Home page** — hero with job search, "featured jobs" carousel, sectors,
  how-we-work steps, credibility stats, Google reviews section, and a
  clear route through to employers.
- **Find a Job** — full listing with search and **filters** (sector, job
  type, working pattern, minimum salary), sorting, "X jobs found" count,
  applied-filter tags, pagination, a tidy "no results" message, and a
  proper filter drawer on mobile.
- **Individual job pages** — full detail layout, ready for Google's
  "Google for Jobs" listings (the special data Google needs is built in).

**Employer journey**
- **Employers**, **Better Job Adverts**, **Services**, **Sectors** pages,
  each with a clear enquiry route. (Better Job Adverts deliberately shows
  *"Pricing confirmed when you enquire"* — no figure published, as agreed.)
- **Enquiry forms** (hiring and general contact) with spam protection and
  consent, delivered to a Poolhall inbox.

**Company pages**
- **Meet the Team** with real photos and bios, **Join Our Team**,
  **Contact**, plus the rebuilt **footer** (four columns, TEAM & BNI
  accreditation logos, company number, VAT and address).

**Candidate accounts (secure)**
- Register, confirm email, sign in, reset password, a personal
  **dashboard** (saved jobs, with alerts and applications ready to follow),
  and a **security area** (change password/email, see and sign out of
  devices). Built to modern security standards and independently testable.

**Behind the scenes**
- The site is ready to pull jobs automatically from Poolhall's Giig system,
  cache them locally for speed and SEO, and retire expired roles
  automatically.
- Extensively tested (200+ automated checks) and deployed to the preview
  site for review.

---

## What we need from you (to go live)

| # | Item | Why it's needed | Owner |
|---|------|-----------------|-------|
| 1 | **Giig API login** (token + secret) | Switches the **live job feed** on — real vacancies replace the samples | Poolhall / Giig |
| 2 | **Decision: how candidates apply** | Either an on-site application form, or "Apply" links straight to the Giig page. Needs your call | Poolhall |
| 3 | **Google reviews API key** | Turns the reviews section into your **real Google reviews** | Poolhall / us to set up |
| 4 | **Confirm a few content facts** | Combined experience figure (30 vs ~50 yrs), Better Job Adverts pricing, team email/LinkedIn links, social media links, opening hours | Poolhall |
| 5 | **Old website content** | Migrate existing blog posts and legal/policy pages from the current Wix site | us, with your sign-off |
| 6 | **Sign-off to launch** | Final security check, then point the real domain at the new site | Poolhall + us |

---

## Suggested talking points for the meeting

- Walk through the **preview site** live on screen — it's the best way to
  show progress.
- Get the **Giig credentials** moving (often the longest lead time).
- Settle the **apply method** decision (on-site form vs link to Giig).
- Collect the **content confirmations** in the table above.
- Agree a **target launch date** working back from the security sign-off.

---

## Plain status summary

- **Design & build:** complete on the preview site.
- **Live job data:** ready — waiting on the Giig login.
- **Google for Jobs:** built in — activates once real jobs are flowing and
  the site is submitted to Google.
- **Remaining:** credentials, a couple of decisions, content confirmations,
  old-content migration, and final launch checks.
