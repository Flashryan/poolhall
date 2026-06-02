# Poolhall Recruitment — Design System

A brand + product design system for **Poolhall Recruitment Limited**, built to
support the new custom WordPress website (the "Option B" build that pulls live
jobs from Giig Hire, runs distinct candidate & employer journeys, and becomes
eligible for Google for Jobs).

> **Version 0.2 — first design pass.** This system was created from the build
> spec + Poolhall's public brand presence. There were **no source brand files,
> codebase, or Figma** — so the visual language here is a *proposed refresh*
> grounded in Poolhall's existing logo and orange+blue social palette. Treat it
> as a starting point to react to, not a locked brand.

---

## The company
- **Poolhall Recruitment Ltd** — independent recruitment agency, founded April 2021, based in **Birmingham / West Midlands** (11 St Pauls Square, B3 1RB). MD: **Matthew Tonks**.
- **Sectors:** Construction & Skilled Trade, Manufacturing, Marketing & PR, Sales, Insurance, Automotive. (Historically also Education.)
- **Positioning:** "Quality and ethical" recruitment — *"state-of-the-art technology mixed with traditional principles and ethics."* Independent = a more personable service. ~50 years' combined experience. Permanent placement, temporary staffing, job-advertising services, plus a recruiter franchise/partnership offering.
- **Audiences:** **Candidates** (find a job) and **Employers** (hire talent / advertise roles). The new site leads with candidates.

## Design direction chosen (from client answers)
- **Palette:** keep **orange + blue**, refreshed and modernised; anchored by a deep **navy**, with the logo's **gold** as a sparing premium accent.
- **Personality:** *trustworthy & established* — clean, corporate, confident.
- **Type:** **serif headlines** + clean grotesque body.
- **Imagery:** real photography (workplaces, trades, people).
- **Homepage emphasis:** candidate-first; employers a clear secondary front door.
- **Scope:** full website UI kit (home, jobs listing, single job, apply, employers).

## Sources referenced (no access assumed — recorded for whoever has it)
- Existing site (Wix): https://www.poolhallrecruitment.co.uk — logo, sections, copy tone.
- Current jobs widget: `careers.giighire.com` (Giig Hire). Companies House #13319338.
- Public job feeds: CV-Library, Indeed, careerstructure — used for realistic sample roles/copy.
- LinkedIn / Instagram (`@poolhallrecruitment`) — orange+blue sector colour-coding.
- Design references the client called out: JDS Recruitment, fieldsolutionsgroup.co.uk, skilledcareers.co.uk (distinct candidate/employer paths; sideways-swipe featured jobs & Google reviews).
- The uploaded logo: `uploads/logo-1780339458469.avif` → converted to `assets/poolhall-logo.png`.

---

## Content fundamentals — how Poolhall writes
- **Voice:** warm, plain-spoken, professional. British English (*organise, programme, CV, £, "0121…"*). Confident but never salesy — they explicitly prize being *honest, ethical and personable* over pushy.
- **Person:** speaks to the reader as **"you"**, and as **"we"** for Poolhall. Candidate copy is encouraging ("your next dream job", "we put you first"); employer copy is reassuring ("we'll represent your business like it's our own").
- **Casing:** Sentence case for body and most headings. The legacy site uses some ALL-CAPS section labels (e.g. "CURRENT ROLES", "OUR MISSION") — in this refresh that's expressed as a small **uppercase eyebrow** above titles, not shouty headlines.
- **Headlines** are short and human: *"Find your next job with us"*, *"We match incredible roles with amazing people."*
- **Sectors** are written out in full: "Construction & Skilled Trade", "Marketing & PR".
- **Salaries** as ranges in GBP/year: *"£60,000–£75,000 / year"*. Job types: "Permanent". Working pattern: "Must be onsite", "Part Remote", "Fully Remote".
- **Emoji:** used informally on **social media** (🔸🔹 sector bullets, ☎ 🖥) — but **keep emoji OUT of the website UI**. The refreshed brand uses real icons instead. Don't carry social-post emoji into product copy.
- **CTAs:** verb-led and short — "View roles", "Apply now", "Get in touch", "Browse jobs", "Send enquiry".

## Visual foundations
- **Colour vibe:** professional and grounded. **Navy (#1B3052)** carries authority and all dark sections; **orange (#EC6F1E)** is the single energetic action colour (CTAs, hovers, eyebrows); **blue (#1F6FB2)** supports (links, info, hybrid-role tags); **gold (#C6A052)** appears only as small flourishes (the logo rule, "Featured" markers). One warm-slate neutral ramp. See `colors_and_type.css`.
- **Type:** **Source Serif 4** for display/headings (a refined transitional serif that echoes the PRL monogram — established, editorial, trustworthy); **Hanken Grotesk** for body/UI (clean, slightly warm grotesque); **IBM Plex Mono** only for code/schema/reference IDs. Headlines are tight (`-0.015em`, line-height ~1.1); body is generous (line-height 1.6). Loaded from Google Fonts (CDN) — see substitution note below.
- **Spacing:** 4px base scale (`--sp-1…10`). Sections breathe — 84px vertical rhythm on desktop. Max content width 1200px.
- **Backgrounds:** mostly solid — white surfaces on a soft `--paper` (#F7F8FA) page, alternating with navy "feature" bands. **No decorative gradients.** The *only* gradient is a functional left-to-right navy **protection scrim** over hero photography so white text stays legible. No textures, no patterns.
- **Imagery:** real photography — construction sites, workshops, manufacturing, offices, people at work. Cool-neutral, documentary feel (not over-saturated stock). On hero/feature images a navy overlay (~65%) plus the scrim. Always `object-fit: cover`; every image has a navy fallback so a missing photo never breaks layout.
- **Corner radii:** soft, not rounded-cartoonish. Inputs/buttons `6px`, cards `16px`, pills/chips/avatars full. Featured job cards add a 4px orange **top border** rather than a coloured left border.
- **Cards:** white fill, 1px `--cloud` border, soft shadow (`--shadow-sm`). Hover lifts (`translateY(-3px)` + `--shadow-md`). Never heavy borders or hard drop shadows.
- **Shadows:** soft and **navy-tinted** (`rgba(15,29,51,…)`), low opacity, generous blur, minimal spread. Four steps: xs (hairline) → lg (modals/popovers).
- **Borders & dividers:** hairline `--cloud` (#E3E7ED); `--mist` for internal dividers inside cards. A 2px **gold rule** is the logo's signature divider motif.
- **Transparency / blur:** the sticky header is translucent white with `backdrop-filter: blur` over content. Otherwise opacity is reserved for the hero scrim. Used sparingly.
- **Animation:** restrained and professional. Transitions 0.15s ease on colour/shadow/transform. Card hover-lift, button press (`translateY(1px)`), smooth carousel scroll. **No bounces, no parallax, no big motion.**
- **Hover states:** buttons darken (orange→`--orange-600`, navy→`--navy-600`); ghost buttons gain a navy border + light fill; links shift orange/blue; cards lift. **Press:** subtle 1px downward nudge.
- **Focus:** visible blue ring (`--shadow-focus`, `rgba(46,131,201,.35)`) for accessibility (WCAG 2.2 AA target).
- **Layout rules:** sticky header (top bar + nav). On single-job and apply pages a **sticky aside** holds salary/meta + the apply CTA. Carousels are horizontal scroll-snap with circular prev/next buttons placed *below* the track.

## Iconography
- **System:** **[Lucide](https://lucide.dev)** — consistent 2px-stroke, rounded-join line icons. Loaded via CDN (`unpkg.com/lucide`). This is a **substitution**: Poolhall has no existing icon set, and Lucide's clean, professional stroke style matches the "trustworthy & established" direction. **Flag:** confirm you're happy with Lucide before production, or supply a preferred set.
- **Usage:** functional, monochrome, inherit `currentColor`. Common icons: `search`, `map-pin`, `briefcase`, `house`, `clock`, `star` (featured/reviews), `arrow-right/left`, `chevron-*`, `sliders-horizontal`, `upload`, `check`, `shield-check`, plus sector icons (`hard-hat`, `factory`, `megaphone`, `trending-up`, `shield-check`, `car`).
- **Brand glyphs:** LinkedIn & Instagram are inline brand SVGs in the footer (Lucide removed brand icons).
- **Emoji:** **not used** in the product UI (social-only — see Content Fundamentals). **No unicode dingbats** as icons.
- **Logo:** `assets/poolhall-logo.png` — circular "PRL" serif monogram + gold rule + stacked wordmark. It's a small raster (153px, transparent circle). ⚠️ **Please supply a vector / hi-res logo** for production and any dark-background lockups.

---

## Index — what's in this system
| File / folder | What it is |
|---|---|
| `README.md` | This file — context, content & visual foundations, iconography, index |
| `colors_and_type.css` | All design tokens: colour palette, semantic roles, type families & scale, radii, spacing, shadows |
| `SKILL.md` | Agent-Skills front-matter so this system can be used in Claude Code |
| `assets/` | `poolhall-logo.png` (converted from the uploaded logo) |
| `preview/` | Design-system cards (colours, type, spacing, components, brand) — shown in the Design System tab |
| `ui_kits/website/` | **Full website UI kit** — Home, Jobs listing, Single job, Apply, Employers (see its README) |
| `uploads/` | Original uploaded logo (`.avif`) |
| `screenshots/` | Working screenshots captured during the build |

### Fonts — substitution flag
Source Serif 4, Hanken Grotesk and IBM Plex Mono are **Google Fonts loaded from
CDN** (no licensed brand fonts were provided). If Poolhall has preferred brand
typefaces, send them and I'll swap them into `colors_and_type.css`.
