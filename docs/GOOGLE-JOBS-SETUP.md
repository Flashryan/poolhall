# Getting Poolhall roles into Google Jobs

Google Jobs is the box of job listings that appears at the top of Google when
someone searches "HVAC engineer jobs Birmingham". Appearing there is free —
Google reads hidden data the website already publishes on every live role.

The website does the technical half automatically. This guide covers the half
that needs a person, once.

---

## What the website already does for you

Every time a role syncs from Giig, the site publishes machine-readable job data
(title, location, salary, employment type, closing date, reference, employer)
that Google Jobs reads. When a role expires it is removed from the sitemap and
hidden from search, so Google stops advertising roles nobody can apply for.

One deliberate rule: if a role is missing something Google requires, the site
publishes **no** job data for it rather than guessing. A wrong salary or an
invented location can get the whole site penalised. The **Google Jobs** admin
screen shows you exactly which roles are affected and why.

---

## One-time setup (about 30 minutes)

### 1. Check the readiness screen

In WordPress, go to **Poolhall Jobs → Google Jobs**. Work down the checklist at
the top. Every line needs a green tick.

The two that usually need action on a new site:

- **Search engines can reach the site** — while the site is in staging it is
  password protected, which makes it invisible to Google. This must be switched
  off at launch.
- **Hiring organisation is set** — fill in the Organisation details form on the
  same screen (name, website, logo URL). Google shows this as the employer on
  every listing.

### 2. Fix any roles marked "Not listed"

The table lower down lists every live role with a verdict. Anything marked
**Not listed** has a plain-English reason next to it — almost always a missing
location, description or closing date.

**Fix these in Giig, not in WordPress.** The website re-reads Giig on a
schedule, so corrections flow through automatically at the next sync (or press
*Sync now* on the Poolhall Jobs screen to pull them immediately).

Under "Optional improvements" you may also see suggestions like a missing salary
range or job type. These do not stop a listing, but candidates filter Google
Jobs by salary and contract type — roles without them are invisible to those
filters. Adding them in Giig is one of the cheapest wins available.

### 3. Verify the site in Google Search Console

Search Console is Google's free dashboard for site owners, and Google Jobs
reporting lives inside it.

1. Go to <https://search.google.com/search-console> and sign in with the
   business Google account (the same one used for the Google Business Profile,
   ideally).
2. Choose **Add property → URL prefix** and enter the live website address.
3. Google will ask you to prove you own the site. The simplest route on this
   hosting is **HTML tag** verification: Google gives you a `<meta>` tag —
   send it to your developer to add to the site header, then press Verify.
4. Access can be shared later via **Settings → Users and permissions**.

### 4. Submit the sitemap

Still in Search Console, open **Sitemaps** in the left menu and submit:

```
wp-sitemap.xml
```

This is the list of pages Google should crawl, and the website keeps it up to
date on its own. You only submit it once.

### 5. Test one role

On the **Google Jobs** screen, press **Test with Google** next to any role
marked *Listed*. This opens Google's own Rich Results Test. You want to see
**JobPosting** detected with no errors. Warnings about optional fields are fine.

> While the site is still password protected this test will fail, because
> Google cannot reach the page. Run it after launch.

---

## What happens next

Google does not list roles instantly. Typical timings:

| When | What to expect |
|---|---|
| A few days | Google crawls the site and finds the roles |
| 1–2 weeks | Roles start appearing in Google Jobs searches |
| Ongoing | New roles are usually picked up within a few days of syncing |

In Search Console you can watch progress under **Indexing → Pages**, and once
listings appear there is a dedicated **Job postings** report showing impressions
and clicks.

---

## Keeping it working

- **Close roles in Giig when they are filled.** The site then removes them from
  Google automatically. Leaving dead roles live is the single fastest way to
  lose Google Jobs eligibility.
- **Check the Google Jobs screen after adding a batch of roles** — it takes ten
  seconds and catches a missing location before Google does.
- **Write real descriptions.** Google ranks on the advert text itself. Roles
  with a genuine description of the work, the team and the requirements beat
  three-line adverts every time.

---

## If roles are not appearing

1. Is the site still password protected, or is *Discourage search engines*
   ticked under **Settings → Reading**? Either makes listings impossible.
2. Does the **Google Jobs** screen show the role as *Listed*?
3. Does **Test with Google** report a valid JobPosting?
4. Has the sitemap been submitted in Search Console, and does the coverage
   report show the job pages as indexed?
5. Has it been less than two weeks? Google is not instant.

If all five are fine and listings still do not appear, Search Console's
**Job postings** report will usually name the reason directly.

---

## Optional: faster listing via the Indexing API

Google offers an Indexing API specifically for job postings, which pushes new
and closed roles to Google within minutes instead of days. It needs a Google
Cloud service account to be created and connected, which is a developer task.

It is not required — normal crawling works — but it is worth doing if roles are
often filled within a week or two, because it also removes closed roles from
Google far more quickly. Ask your developer to set this up once the basics
above are live and working.
