# Website UI Kit — Poolhall Recruitment

A high-fidelity, clickable recreation of the new Poolhall Recruitment website
(Option B, full custom WordPress build). React + Babel, styled with the design
system tokens in `../../colors_and_type.css` plus `kit.css`.

> These are **cosmetic recreations** for design + prototyping, not production
> code. There is no real Giig API integration, no real form submission, and the
> job data in `data.js` is illustrative.

## Run it
Open `index.html`. It loads React 18, Babel standalone, Lucide (icons) and the
JSX files below, then mounts a small state-based router.

## Screens (click-through)
| Route | File | What it shows |
|-------|------|----------------|
| Home | `Home.jsx` | Candidate-led hero + search, **featured-jobs carousel**, sectors, "how we work", stat strip, **Google Reviews carousel**, employer CTA |
| Find a Job | `Jobs.jsx` | Listing with sticky filter sidebar (sector / type / working pattern / salary), sort, pagination |
| Single job | `JobSingle.jsx` | Navy job header, full description, sticky apply aside, JobPosting JSON-LD preview, similar roles |
| Apply | `Apply.jsx` | Application form (name, email, phone, CV dropzone, consent) + submitting + success state |
| Employers | `Employers.jsx` | Distinct employer journey: services, why-Poolhall, sectors, enquiry form |
| Meet the Team | `Team.jsx` | Story + real team portraits (Matthew, Jay, Sam) with bios + join-us CTA |
| Contact | `Contact.jsx` | Contact details, map placeholder, enquiry form with submitting state |

## Real assets
- **Photography & portraits** are the client's own images, embedded as data URIs in `images.js` (`window.PH_PHOTO`). Office/culture shots feed the hero & feature bands; the three portraits feed the Team page.
- **Accreditations** (TEAM, BNI) render on white chips in the footer (`window.PH_ACCRED`).
- To swap any image, edit `images.js` (or the `PH_IMG` map in `data.js`).

## Components
- **`ui.jsx`** — `Icon` (Lucide wrapper), `Button` (primary / navy / ghost / ghost-light / link), `Header` (top bar, nav, **candidate ⇄ employer switch**), `Footer`.
- **`blocks.jsx`** — `SectionHead`, `JobCard`, `Carousel` (sideways swipe + prev/next), `ReviewCard`, `Stars`, `SectorTile`, `SearchBar`, `StatStrip`.
- **`data.js`** — sample jobs, sectors, reviews, stats + `PH_FMT.salary()`.

## Navigation
Every component receives a `go(route, payload)` callback. `go("job", jobObj)`
and `go("apply", jobObj)` carry the selected job. Use the **Candidates /
Employers** switch in the header to flip the primary journey.

## Conventions
- Icons: **Lucide** via CDN. Brand glyphs (LinkedIn/Instagram) are inline SVGs in the footer (Lucide dropped brand icons).
- Photography: a few **Unsplash** URLs stand in for real workplace/trade photography. Each `<img>` has an `onError` fallback to a navy block so the layout never breaks offline. **Swap these for Poolhall's own photography** in production.
- Spacing/colour/type come from tokens — avoid hard-coded hex; use `var(--…)`.
