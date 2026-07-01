# Website UI Kit — Poolhall Recruitment (v2 "Engineered")

A high-fidelity, clickable recreation of the Poolhall Recruitment website in the
**v2 "Engineered" direction**: industrial slate ground, engineering-steel
(employer) and hi-vis (candidate/action) signal accents, Archivo heavy display,
Source Sans body, and IBM Plex Mono for job data. React + Babel, styled with
`kit.css` (self-contained tokens that mirror `../../colors_and_type.css`).

> Cosmetic recreation for design + prototyping, not production code. No real Giig
> API, no real submission; `data.js` is illustrative. The visual output is the
> contract for the WordPress build.

## Run it
Open `index.html`. Loads React 18, Babel standalone, Lucide, then the JSX files
and mounts a small state-based router (`go(route, payload)`).

## Screens & routes
| Route(s) | File | What it shows |
|------|------|----------------|
| `home` | `Home.jsx` | **Four-stage animated hero** (Construction · Manufacturing · Digital · Team, ken-burns cross-fade, world markers, reduced-motion fallback), overlapping feature strip, featured-jobs carousel, Google reviews on photo band, sector snapshot, "what makes us different" collage, stat row, paired CTA bands |
| `jobs` | `Jobs.jsx` | Live jobs: filter bar (keyword/location/sector/work mode), applied-filter chips + clear-all, sort, result count, **empty state**, pagination |
| `job` | `JobSingle.jsx` | Sector-photo header, mono meta, full description, sticky apply aside, save-job toggle, **sticky mobile apply bar**, opens the **Apply modal**, similar roles |
| `apply` | `Apply.jsx` | Full apply page (deep-link/fallback) with the same validated form |
| `employers` | `Employers.jsx` | "More than just recruitment." — value points, 5 delivery options, services split, reviews, photo CTA |
| `candidate` | `Pages.jsx` → `CandidateHub` | "Find work that fits" — 3-step, guides grid |
| `delivery` | `Pages.jsx` → `Delivery` | 5 service cards (Temporary/Permanent/Scale/Pay Monthly/On-Site) |
| `sector-con/-man/-dig` | `Pages.jsx` → `SectorPage` | Sector template (Construction worked example): roles, how we help, live roles |
| `register` | `Apply.jsx` → `Register` | Candidate CV registration |
| `team` | `Team.jsx` | Story + real team portraits + Work-For-Us CTA |
| `contact` | `Contact.jsx` | Three routes (hire / find work / general), route-aware form |
| `blog` / `post` | `Pages.jsx` → `Blog`, `BlogPost` | Blog index with category filter + full post template (share, related) |
| `why-us` `bespoke` `bja` `hr` `commitment` | `Pages.jsx` → `Secondary` | Secondary pages (BJA price withheld) |
| `privacy` `terms` `cookies` | `Pages.jsx` → `Legal` | Long-form legal template |

## The two journeys (brief §5.3)
- **Employer cue = steel**, **Candidate cue = hi-vis**, carried through nav, CTA bands and page heads.
- Header: two persistent CTAs (**Find work** + **Hire talent**), **Employers ▾ / Sectors ▾ / Candidates ▾** dropdowns, visible phone. Mobile drawer keeps employer vs candidate **visibly separate**.

## Components
- **`ui.jsx`** — `Icon` (Lucide), `Button` (primary/steel/dark/ghost/ghost-light/text), `Header` (utility bar, dropdown nav, mobile drawer), `Footer` (multi-column + compliance + accreditation chips).
- **`blocks.jsx`** — `Hero` (4-stage), `FeatureStrip`, `SectionHead`/`Label` (mono indexed labels), `JobCard` (mono meta + hi-vis rule), `Carousel` (shared swipe pattern), `ReviewCard` (with date + Google glyph), `SectorTile` (image-led), `CtaBands`.
- **`Apply.jsx`** — `ApplyForm` (inline validation: required, email format, 5 MB CV guard, consent), used by both the **Apply page** and **`ApplyModal`** (opens over single job, Esc/overlay close).
- **`data.js`** — jobs, 3 sectors + `PH_SECTOR` map, reviews, stats, `PH_IMG` photo map, `PH_FMT.salary()`. **`images.js`** — embedded real Poolhall photos (`PH_PHOTO`) + accreditations (`PH_ACCRED`).

## Conventions
- Icons: **Lucide** via CDN; brand glyphs (LinkedIn/Instagram) inline SVG in footer.
- **Photography is §13-pending.** The four hero worlds + sector/blog images are tasteful **Unsplash placeholders** (`PH_IMG` in `data.js`); real Poolhall office/team shots are embedded in `images.js`. Every `<img>` has an `onError` fallback to a slate block. **Swap placeholders for Poolhall's own four hero + three sector images.**
- Tokens only — use `var(--…)`, avoid hard-coded hex.
- Copy: plain, active, sentence case, no em-dashes. BJA price never shown ("confirmed when you enquire"). Combined experience = **30 yrs**.
