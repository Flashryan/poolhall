# Poolhall Elementor Design System

This is the authoritative visual and Elementor implementation specification.
Claude or another implementation agent must use it with the live prototype,
`01-UX-UI-CONTENT-SPEC.md` and `07-NOVAMIRA-ELEMENTOR-WORKFLOW.md`.

The objective is fidelity to https://poolhall.vercel.app/ with corrected mobile
behavior, real content, working account features and production accessibility.
Do not introduce a new visual direction.

## 1. Build policy

### Elementor v4 by default

Use Elementor v4 Atomic elements wherever the installed release supports the
required behavior reliably:

- Flexbox, grid and div-block layout elements
- Heading and paragraph
- Button and link
- Image and SVG/icon
- Divider
- Variables
- Global classes
- Components
- Responsive variants
- Simple entrance interactions

Build normal page sections from Atomic elements and shared classes. Do not use a
classic container/widget merely because it is familiar.

### Elementor v3 for dynamic loops

Use Elementor Pro v3/classic Loop Grid, Loop Carousel and Loop Item templates
for WordPress-query-backed repeated content:

- Featured jobs
- Jobs archive results
- Similar jobs
- Blog cards and related posts
- Sector taxonomy cards when taxonomy-driven
- Team cards when implemented as a Team CPT
- Review cards when reviews are cached as queryable local records

The Loop Grid/Carousel wrapper and each Loop Item template are v3/classic. Their
surrounding page section, heading, controls and layout shell remain v4 Atomic.

Do not use v3 Loop Grid for private candidate data. Saved jobs,
recommendations, applications, alerts and account content use authorized custom
plugin widgets because their query and caching rules are user-specific.

### Other approved exceptions

| Need | Preferred implementation | Fallback |
|---|---|---|
| Main navigation | v4 structure with accessible WordPress menu integration | v3 Nav Menu widget |
| Contact/employer/join forms | v4 Atomic Form delegated to plugin handler | v3 Elementor Form delegated to plugin |
| Application and candidate forms | Custom plugin widget | No Elementor form fallback |
| Dynamic job fields in Loop Items | v3 dynamic widgets/tags or plugin dynamic tags | Custom plugin widget |
| Dynamic carousels | v3 Loop Carousel | Accessible plugin-rendered list |
| Complex private UI | Custom plugin widget in v4 shell | Server-rendered plugin template |

No exception may move credentials, authorization, queries or business rules
into Elementor document data.

## 2. Prototype measurements

The following were measured from the prototype on 2026-06-10.

### Desktop reference: 1280 x 720

- Browser content width observed: 1265px
- Main container: 1152px, approximately 56px side space
- Contact strip: 38px
- Main navigation: 74px
- Home hero content region: approximately 512px high
- Home H1: 56px, 1.08 line height, weight 500
- Standard H2: 34px, 1.15 line height, weight 500
- Primary navigation CTA: 46px high
- Search action: 53px high
- Search input: 48px high
- Job card radius: 16px
- Job card padding: 22px 24px
- Job card shadow: `0 2px 6px rgba(15, 29, 51, 0.08)`

### Mobile reference: 390 x 844

- Page gutter: 24px
- Home H1: approximately 38.4px, 1.08 line height
- Search panel inner width: approximately 299px
- Inputs stack at 48px high
- Search action remains 53px high
- Prototype job cards overflow the viewport; production cards must use
  `inline-size: 100%` and `min-inline-size: 0`
- Prototype contact strip and navigation collide; production mobile header must
  use the specified mobile states below

The measured main container is 72rem/1152px. Use 75rem/1200px only for an
explicit wide-media section, never as the normal text or navigation container.

## 3. Elementor variables

Create these in Elementor v4 Variables Manager where the installed control
supports the value. Keep the variable IDs stable after page construction starts.
Sync color and typography variables to classic Global Colors/Fonts so v3 Loop
Items use the same source values. Compound fluid formulas that the editor cannot
store safely must be named CSS custom properties in the child theme and applied
only through the declared global classes.

### Color variables

| Variable | Value | Use |
|---|---|---|
| `ph-color-navy-950` | `#0B1626` | Deep overlays and footer |
| `ph-color-navy-900` | `#0F1D33` | Contact strip and hero |
| `ph-color-navy-800` | `#14233F` | Primary navy surface |
| `ph-color-navy-700` | `#1B3052` | Headings on light surfaces |
| `ph-color-orange-700` | `#B9510E` | Darker interactive state |
| `ph-color-orange-600` | `#D45F12` | Hover/focus action |
| `ph-color-orange-500` | `#EC6F1E` | Primary action |
| `ph-color-orange-50` | `#FDF1E7` | Icon and badge tint |
| `ph-color-blue-700` | `#155389` | Informational dark |
| `ph-color-blue-600` | `#1F6FB2` | Informational accent |
| `ph-color-gold-500` | `#C6A052` | Featured/review accent |
| `ph-color-paper` | `#F7F8FA` | Site background |
| `ph-color-mist` | `#EEF1F5` | Input and quiet surface |
| `ph-color-white` | `#FFFFFF` | Cards and reversed text |
| `ph-color-border` | `#E3E7ED` | Default border |
| `ph-color-ink` | `#16202F` | Primary text |
| `ph-color-muted` | `#586375` | Secondary text |
| `ph-color-success` | `#1F8A5B` | Success state |
| `ph-color-warning` | `#A56800` | Warning text |
| `ph-color-error` | `#C9382E` | Error state |
| `ph-color-focus` | `#1F6FB2` | Focus ring |

### Typography variables

| Variable | Family | Weight | Fluid size | Line height |
|---|---|---:|---|---:|
| `ph-type-display` | Source Serif 4 | 500 | `clamp(2.4rem, 1.7rem + 2.8vw, 3.5rem)` | 1.08 |
| `ph-type-h1` | Source Serif 4 | 500 | `clamp(2.25rem, 1.75rem + 2vw, 2.75rem)` | 1.12 |
| `ph-type-h2` | Source Serif 4 | 500 | `clamp(2rem, 1.65rem + 1.5vw, 2.125rem)` | 1.15 |
| `ph-type-h3` | Source Serif 4 | 500 | `clamp(1.35rem, 1.15rem + 0.65vw, 1.5rem)` | 1.2 |
| `ph-type-h4` | Hanken Grotesk | 700 | `clamp(1.05rem, 0.98rem + 0.3vw, 1.125rem)` | 1.3 |
| `ph-type-body-lg` | Hanken Grotesk | 400 | `clamp(1.05rem, 0.98rem + 0.35vw, 1.125rem)` | 1.65 |
| `ph-type-body` | Hanken Grotesk | 400 | `1rem` | 1.6 |
| `ph-type-small` | Hanken Grotesk | 500 | `0.875rem` | 1.45 |
| `ph-type-eyebrow` | Hanken Grotesk | 700 | `0.75rem` | 1.3 |
| `ph-type-data` | IBM Plex Mono | 500 | `0.8125rem` | 1.4 |

Rules:

- Display and heading letter spacing: `-0.005em`.
- Eyebrow letter spacing: `0.14em`; uppercase.
- Data/reference text uses IBM Plex Mono only where it improves scanning.
- Never use viewport-only font sizes such as `5vw`; always cap them with
  `clamp()`.
- Do not use Source Serif for form controls, navigation, badges or dense data.

### Spacing variables

| Variable | Value |
|---|---|
| `ph-space-1` | `0.25rem` |
| `ph-space-2` | `0.5rem` |
| `ph-space-3` | `0.75rem` |
| `ph-space-4` | `1rem` |
| `ph-space-5` | `clamp(1.125rem, 1rem + 0.35vw, 1.5rem)` |
| `ph-space-6` | `clamp(1.5rem, 1.25rem + 0.8vw, 2rem)` |
| `ph-space-8` | `clamp(2rem, 1.5rem + 1.8vw, 3.5rem)` |
| `ph-space-10` | `clamp(2.5rem, 1.75rem + 2.8vw, 4.5rem)` |
| `ph-space-section` | `clamp(3.75rem, 2.5rem + 4vw, 5.25rem)` |
| `ph-space-section-lg` | `clamp(4.5rem, 2.5rem + 6vw, 7rem)` |

### Size and shape variables

| Variable | Value |
|---|---|
| `ph-container` | `72rem` |
| `ph-container-wide` | `75rem` |
| `ph-container-narrow` | `48rem` |
| `ph-page-gutter` | `clamp(1.5rem, 1rem + 2vw, 3.5rem)` |
| `ph-radius-sm` | `0.375rem` |
| `ph-radius-md` | `0.625rem` |
| `ph-radius-lg` | `1rem` |
| `ph-radius-pill` | `999px` |
| `ph-control-sm` | `2.875rem` |
| `ph-control` | `3rem` |
| `ph-control-lg` | `3.3125rem` |
| `ph-touch-min` | `2.75rem` |
| `ph-border` | `1px solid var(--ph-color-border)` |
| `ph-shadow-card` | `0 2px 6px rgba(15, 29, 51, 0.08)` |
| `ph-shadow-raised` | `0 12px 30px rgba(15, 29, 51, 0.12)` |
| `ph-focus-ring` | `0 0 0 3px rgba(31, 111, 178, 0.28)` |

## 4. Viewport sizing rules

Use viewport units deliberately:

- Use `svh` for mobile-safe minimum hero heights.
- Use `dvh` only for open drawers/modals that must fill the current viewport.
- Use `vw` inside `clamp()` for fluid interpolation.
- Do not set normal content, cards or text to unbounded `vw` widths.
- Avoid fixed full-screen `100vh` sections; browser chrome makes them unstable
  on mobile.

Approved examples:

```css
min-block-size: clamp(32rem, 64svh, 40rem);
padding-block: clamp(3.75rem, 2.5rem + 4vw, 5.25rem);
font-size: clamp(2.4rem, 1.7rem + 2.8vw, 3.5rem);
inline-size: min(100% - (2 * var(--ph-page-gutter)), var(--ph-container));
```

Disallowed examples:

```css
font-size: 5vw;
height: 100vh;
width: 80vw;
margin-left: 10vw;
```

unless they are capped, justified and verified at all required viewports.

## 5. Breakpoints

Configure Elementor custom breakpoints to match:

| Name | Range | Intent |
|---|---|---|
| Mobile | up to 639px | One-column, mobile navigation and drawers |
| Tablet | 640-899px | Two-column where content permits |
| Laptop | 900-1199px | Desktop navigation and compact multi-column |
| Desktop | 1200px+ | Full 72rem container |

The CSS/class system is mobile-first. Add desktop variants only when the layout
requires them. Verify 360, 390, 768, 1024, 1280 and 1440 widths.

## 6. Global class registry

Create these as v4 Global Classes. The class ID is immutable after use. Use the
same label and ID where Elementor permits.

### Layout

- `ph-site`
- `ph-container`
- `ph-container--wide`
- `ph-container--narrow`
- `ph-section`
- `ph-section--tight`
- `ph-section--flush`
- `ph-section--paper`
- `ph-section--white`
- `ph-section--navy`
- `ph-stack-xs`
- `ph-stack-sm`
- `ph-stack-md`
- `ph-stack-lg`
- `ph-cluster`
- `ph-grid-2`
- `ph-grid-3`
- `ph-grid-auto`
- `ph-split`
- `ph-sidebar-layout`
- `ph-full-bleed`

`ph-container`:

```css
inline-size: min(100% - (2 * var(--ph-page-gutter)), var(--ph-container));
margin-inline: auto;
min-inline-size: 0;
```

`ph-section`:

```css
padding-block: var(--ph-space-section);
```

`ph-grid-auto`:

```css
display: grid;
grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr));
gap: var(--ph-space-6);
```

### Typography

- `ph-display`
- `ph-h1`
- `ph-h2`
- `ph-h3`
- `ph-h4`
- `ph-lede`
- `ph-body`
- `ph-small`
- `ph-eyebrow`
- `ph-data`
- `ph-link`
- `ph-link--arrow`
- `ph-text-muted`
- `ph-text-reversed`

### Actions

- `ph-button`
- `ph-button--primary`
- `ph-button--secondary`
- `ph-button--ghost`
- `ph-button--inverse`
- `ph-button--danger`
- `ph-button--full`
- `ph-icon-button`

### Surfaces and status

- `ph-card`
- `ph-card--interactive`
- `ph-card--job`
- `ph-card--review`
- `ph-card--team`
- `ph-card--portal`
- `ph-panel`
- `ph-panel--navy`
- `ph-badge`
- `ph-badge--featured`
- `ph-badge--success`
- `ph-badge--warning`
- `ph-badge--error`
- `ph-chip`
- `ph-divider`
- `ph-empty-state`
- `ph-alert`

### Forms

- `ph-form`
- `ph-form-grid`
- `ph-field`
- `ph-field__label`
- `ph-field__control`
- `ph-field__help`
- `ph-field__error`
- `ph-field--invalid`
- `ph-checkbox`
- `ph-file-upload`

### Utilities

- `ph-visually-hidden`
- `ph-no-overflow`
- `ph-sticky-card`
- `ph-mobile-only`
- `ph-desktop-only`

Do not create page-specific classes for values already expressed by these
classes. A one-off local class is acceptable only when the design requirement
is genuinely unique.

## 7. Atomic component inventory

Create reusable v4 Components where the installed version supports stable
component editing:

- Section heading
- Primary/secondary button
- Eyebrow
- Icon fact
- Badge/chip
- Empty state
- Alert banner
- Breadcrumb shell
- Contact-method row
- Statistic
- Process step
- Portal page header
- Portal navigation item

Components contain structure and style only. Dynamic/private values come from
plugin widgets or dynamic tags.

## 8. Header system

### Contact strip

- Surface: Navy 900
- Height: 38px desktop
- Text: 13px Hanken Grotesk, muted white
- Container: 72rem
- Left cluster: phone and email
- Right: office location
- Icons: 18px outline, orange or muted white
- Links have underline on hover and visible focus

Mobile:

- Do not preserve the overlapping three-item prototype.
- Below 640px hide location.
- Phone and email may remain in one horizontally scroll-free row if each fits.
- At 360px, show phone and a shortened email action label if necessary.
- Minimum height may grow to content; never clip or absolutely position text.

### Main navigation

- White surface, 74px desktop
- Logo lockup at left
- Primary links centered/right
- Audience switch and Browse jobs at right
- Active link uses orange underline, not color alone
- Header is sticky only if testing shows no repeated capture/jump issue

Mobile:

- Logo, Browse jobs and menu button
- Hide desktop links and audience segmented control
- Menu button is at least 44px
- Drawer uses `100dvh`, focus trap, Escape close and focus return
- Drawer contains candidate account state and audience switch

## 9. Hero system

### Image hero

Use on Home and Team.

- Full-bleed background image
- `min-block-size: clamp(32rem, 64svh, 40rem)` for Home
- Compact variant: `clamp(24rem, 50svh, 32rem)`
- Content aligned to `ph-container`
- Text width: `min(100%, 43rem)`
- Background position: center; adjust attachment focal point, not arbitrary crop
- Overlay:

```css
linear-gradient(
  90deg,
  rgba(11, 22, 38, 0.97) 0%,
  rgba(11, 22, 38, 0.88) 42%,
  rgba(11, 22, 38, 0.62) 100%
)
```

Mobile overlay becomes more even:

```css
rgba(11, 22, 38, 0.88)
```

### Solid hero

Use on Contact and Single Job.

- Navy 900 surface
- Slim page hero: `padding-block: clamp(3rem, 3vw + 2rem, 5rem)`
- Single-job hero: `padding-block: clamp(3.5rem, 4vw + 2rem, 6rem)`
- Content width is capped for readable title wrapping

### Hero content order

1. Eyebrow or featured badge
2. H1/display
3. Lede
4. Primary action/search, where applicable
5. Proof/meta row

Do not center desktop hero copy. Mobile remains left aligned to match the
prototype unless a specific portal empty state requires centered content.

## 10. Buttons and links

### Primary

- Orange 500 surface, white text
- Hanken Grotesk 700
- Min height 46px; large actions 53px
- Padding inline: `clamp(1.125rem, 1rem + 0.5vw, 1.75rem)`
- Radius 6px
- Hover: Orange 600
- Active: Orange 700
- Focus: 3px focus ring plus 2px white separation on dark surfaces
- Disabled: 45% opacity; no shadow; cursor default
- Loading: keep width stable and show text plus progress indicator

### Secondary

- White or transparent surface
- Navy 800 text/border
- Hover: Mist surface

### Text link

- Orange 700 text
- Underline appears on hover/focus
- Arrow icon moves at most 2px; disabled under reduced motion

Never use orange text on white below AA contrast. Use Orange 700 for text and
Orange 500 for filled surfaces.

## 11. Search module

### Home search

- White outer panel, radius 16px
- Padding: `clamp(0.75rem, 0.5rem + 0.6vw, 0.875rem)`
- Shadow: card shadow
- Max width: 40rem
- Desktop: three fields plus action
- Field surface: Mist
- Field/input height: 48px
- Action height: 53px
- Icon: 18px outline

Mobile:

- Full container width
- One control per row
- Gap 8px
- Action full width
- No nested horizontal scroll

### Archive search

- Full 72rem width
- White panel on Paper background
- Desktop columns: `1.4fr 1fr 1fr auto`
- Tablet: two columns
- Mobile: one column

The custom Job Search widget owns values and submission behavior. Elementor
classes control appearance.

## 12. v3 Loop Item templates

Create and name Loop Items exactly:

### `PH Loop - Job Featured Card`

Use for Home Featured Jobs.

- White vertical card
- 16px radius
- 22px 24px padding
- 3px orange top border only when featured
- Sector eyebrow
- H3 title, maximum three visual lines
- Featured badge aligned top/right when present
- Meta cluster with location, type, work mode and posted date
- Summary clamped to three lines
- Salary/footer separated by border
- Whole card or title is one accessible canonical link
- Equal-height behavior without fixed card height

Loop Carousel:

- Query: published, unexpired jobs; manually featured then newest
- Maximum six
- 3 visible desktop, 2 tablet, 1 mobile
- Gap: 24px
- No autoplay
- Keyboard and touch controls
- Partial next-card cue only when it does not create overflow

### `PH Loop - Job Result Row`

Use for Jobs archive.

- One column
- White card with 16px radius
- Orange top rule for featured jobs
- Desktop horizontal information hierarchy
- Mobile becomes a vertical card
- Title and salary remain prominent
- Summary is optional on mobile but not hidden from assistive technology through
  duplicated DOM
- View job link at lower right desktop, normal flow mobile

Loop Grid:

- 10 per page
- One column at every breakpoint
- Query ID: `poolhall_jobs_archive`
- Pagination: Previous, numbered pages, Next
- Filters and sorting are owned by the custom results widget/query service
- Server-render the first result page

### `PH Loop - Job Compact`

Use for Similar Roles and small recommendation areas that are public.

- Reduced summary or no summary
- Salary, location and title retained
- 2 columns desktop, 1 mobile
- Maximum three items

### `PH Loop - Blog Card`

- Image ratio 16:9
- Category/eyebrow
- H3 title
- Date and author
- Excerpt clamped to three lines
- One canonical link
- 3/2/1 columns; 9 posts per page on archive

### `PH Loop - Sector Card`

Use only when backed by `poolhall_sector`.

- Image or quiet color surface
- Sector name
- Current live role count
- Candidate and employer route context
- 3/2/1 grid

### `PH Loop - Team Card`

Use only if Team members are a CPT. Otherwise build the three approved profiles
as v4 Atomic cards.

- Portrait 4:5 with focal-point-safe crop
- Name, title, specialisms, biography
- LinkedIn and email actions
- 3/2/1 grid

### `PH Loop - Review Card`

Use when cached reviews are queryable local records.

- Review text, reviewer name, context and Google attribution
- No autoplay
- Maximum five source reviews
- 2 visible desktop, 1 mobile

### Loop Item class rule

v3 widgets use synced Global Colors/Fonts plus stable CSS classes mirroring the
v4 class registry. Do not copy raw color values into each Loop Item.

Every Loop Item change through Novamira requires:

1. Template ID and usage inventory
2. `_elementor_data` backup
3. Minimal v3 data edit
4. CSS/data regeneration and cache purge
5. Desktop/mobile verification in every Loop Grid that uses the item

## 13. Job archive

Desktop:

- Breadcrumb strip on Mist
- Search panel
- Main layout: `minmax(15rem, 17.5rem) minmax(0, 1fr)`
- Gap: `clamp(2rem, 3vw, 3rem)`
- Filter panel is white with border/radius
- Results header aligns H1/count left and sort right

Mobile:

- Filter sidebar is removed from normal flow
- Filter jobs button opens an accessible `100dvh` drawer
- Sort remains above results
- Result cards use `min-inline-size: 0`
- No 380px fixed card width from the prototype

Filter controls use real labels, selected counts and clear-all. Chips appear
above results after filters are applied.

## 14. Single job

- Breadcrumb strip
- Navy hero with badge, sector, title and meta cluster
- Main content uses sidebar layout:
  - Content: `minmax(0, 1fr)`
  - Apply card: `minmax(18rem, 22rem)`
- Sidebar is sticky only above 900px and must stop before Similar Roles
- Salary uses Source Serif 4
- Data labels use Hanken/Mono, not display type
- Job body max readable line length: 72ch
- Lists use orange markers or subtle icons
- Privacy note is a quiet bordered panel

Mobile:

- Apply CTA appears after hero metadata and again after role content
- Sidebar becomes normal flow
- Save state remains visible and accessible
- No sticky element obscures content

## 15. Cards and repeated static components

### Base card

- White surface
- 1px border
- 16px radius
- Card shadow
- Padding: `clamp(1.25rem, 1rem + 0.8vw, 1.75rem)`

### Interactive card

- Entire card never contains competing nested links
- Hover: translate at most -2px and use raised shadow
- Focus-within matches hover
- Reduced motion removes translation

### Sector card

- Large image/colored area
- Title is Source Serif
- Live count is a small pill
- Use strong contrast over images

### Process step

- Number in orange-tinted circle or simple data marker
- H4 title
- Body copy
- Three-column desktop, stacked mobile
- Build static three steps with v4 Atomic grid, not a Loop Grid

### Statistic

- Large Source Serif value
- Small Hanken label
- Four columns desktop, two tablet, two mobile
- Do not animate counters unless the real value is server-rendered first

### Review card

- Quote in readable body text
- Reviewer initials/photo and name adjacent
- Google attribution visible
- Do not reproduce prototype sample reviews

## 16. Forms

- White card on Paper surface where appropriate
- Two-column form grid desktop, one column mobile
- Label above control; placeholder is not a label
- Control min height: 48px
- Text area min height: `clamp(8rem, 16svh, 12rem)`
- Border: `#C9D0DA`; focus border: Blue 600
- Invalid border: Error with text and summary
- Required marker is textually announced
- Help and errors remain below the related control
- Submit action aligns left; mobile full width

Form states:

- Default
- Hover
- Focus
- Filled
- Invalid
- Disabled
- Submitting
- Success
- Provider/service failure

Candidate/apply forms use plugin widgets. Elementor may supply only the outer
section, heading and presentation class controls.

## 17. Candidate portal design

### Portal shell

- Paper page surface
- Container 72rem
- Desktop grid: `clamp(15rem, 18vw, 17.5rem) minmax(0, 1fr)`
- Gap: `clamp(1.5rem, 2.5vw, 3rem)`
- Main content min width 0
- Portal sections use white cards with 16px radius

### Account navigation

- White panel
- Active item uses Orange 50 surface, Orange 700 left rule and bold text
- Icons 18px outline
- Mobile becomes a labelled disclosure/sheet, not horizontal tabs

### Dashboard

- Page header with greeting and next action
- Summary grid uses `repeat(auto-fit, minmax(min(100%, 15rem), 1fr))`
- Recommended/saved jobs use plugin-rendered cards matching Job Compact
- Application activity is a semantic list, not a decorative timeline
- Status badge always includes readable text

### Auth card

- Max width 30rem
- Centered within `min-block-size: clamp(34rem, 70svh, 46rem)`
- Logo/heading, form, help links and security note
- Do not use a full photographic background behind dense form controls

### Empty states

- Quiet icon in Orange 50 tile
- H3, one-sentence explanation and one primary action
- Never display a blank dashboard card

Private portal components are custom plugin widgets inside v4 shells. Do not
use v3 Loop Items for candidate-specific lists.

## 18. Contact and team

### Contact

- Slim navy hero
- Content section: contact details 1fr, form card 1.8fr
- Contact rows use orange-tinted 44px icon tile
- Form card uses raised shadow and 16px radius
- Static map has accessible text and external map link

### Team

- Compact image hero
- Story split section with image accent
- Team profiles use v4 cards unless CPT-backed
- Portraits use `aspect-ratio: 4 / 5`, `object-fit: cover`
- Preserve faces with media focal points; never crop heads

## 19. Footer

- Navy 950 surface
- `padding-block: clamp(3.5rem, 4vw, 5rem)`
- Four-column desktop: brand, candidate, employer, company
- Membership marks in a distinct quiet row
- Legal/company line below divider
- Tablet: two columns
- Mobile: one column, visible headings, no accordion required
- Links use muted white and become white/orange on hover/focus

## 20. Iconography

Use one outline icon family matching the prototype, preferably Lucide through an
approved library/asset workflow.

- Default size: 18px
- Feature size: 20-24px
- Stroke: visually equivalent to 1.5-2px
- Orange for actions/highlights; muted navy/white for metadata
- Use consistent icons for location, job type, work mode, time, search, phone,
  email, account, save, alert, upload and external link

Do not mix multiple icon families, use emoji, draw icons with CSS or paste
unmanaged inline SVG into Elementor HTML widgets.

## 21. Imagery

- Use approved Poolhall office/team photography
- Hero source should support at least 1920px wide display
- Generate responsive WordPress sizes and WebP/AVIF
- Hero image uses `object-position`/background position based on subject
- Team portraits: 4:5
- Blog: 16:9
- Sector media: 4:3 or 3:2 consistently
- Decorative images use empty alt
- Informative images use concise, factual alt
- Do not use unrelated generic recruitment stock if approved real imagery exists

## 22. Motion

Use v4 Interactions only for editorial entrances:

- Fade/translate 8-16px
- Duration 180-280ms for UI state
- Duration 500-750ms for first-view editorial entrances
- Stagger 60-100ms, maximum four items
- No autoplay carousel
- No parallax
- No scroll-jacking
- No movement of focused controls

`prefers-reduced-motion: reduce` removes non-essential entrance and transform
motion.

## 23. Responsive safeguards

Apply to all flex/grid children:

```css
min-inline-size: 0;
max-inline-size: 100%;
```

Rules:

- No fixed card width below its desktop context
- No negative margins for core alignment
- No absolute positioning for body copy or form controls
- Use `overflow-wrap: anywhere` for emails/URLs
- Tables become semantic stacked records or horizontal scroll regions with a
  visible label
- Drawers/modals use `max-block-size: 100dvh`
- Sticky elements require safe top offsets and must be disabled on mobile
- Test long job titles, long email addresses, empty data and error messages

## 24. Accessibility contracts

- One H1 per page
- Heading order follows content hierarchy
- 44px minimum interactive target
- Focus visible on every interactive element
- Text contrast meets WCAG 2.2 AA
- Orange 500 is not used for small text on white
- Cards expose one clear primary link/action
- Carousel controls are labelled and keyboard usable
- Filters/drawers trap and restore focus
- Form errors have summary and field relationships
- Saved/alert actions announce state changes
- Color is never the only status indicator
- Touch and keyboard behavior is verified independently from hover

## 25. Page composition map

### Home

1. v4 Header
2. v4 Image Hero + custom Job Search widget
3. v4 proof cluster
4. v4 section shell + v3 Featured Job Loop Carousel
5. v4 section shell + v3 Sector Loop or v4 static sector cards
6. v4 Process Steps
7. v4 Statistics
8. v4 section shell + v3 Review Loop Carousel
9. v4 Employer CTA
10. v4 Footer

### Jobs archive

1. v4 Header/Breadcrumb/Search shell
2. Custom filter/results controller
3. v3 Job Result Loop Grid using plugin query ID
4. v4 Footer

### Single job

1. v4 Header/Breadcrumb/Hero
2. v4 content shell with plugin dynamic tags
3. Custom Apply/Save actions
4. v4 section shell + v3 Similar Job Loop Grid
5. v4 Footer

### Marketing pages

Use v4 Atomic sections/components. Add v3 loops only for actual dynamic
collections.

### Candidate portal

Use v4 Atomic shells and custom authorized widgets. No v3 private-data loops.

## 26. Novamira implementation sequence

1. Inspect installed Elementor version and enabled v4 features.
2. Create/sync v4 variables and v3 Global Colors/Fonts.
3. Create the global class registry.
4. Build a style-guide page containing every token, class and state.
5. Build v4 Header, Footer and base page sections.
6. Build v3 Loop Item templates and record every template ID.
7. Build pages/templates in `07-NOVAMIRA-ELEMENTOR-WORKFLOW.md` order.
8. Back up `_elementor_data` before every write.
9. Regenerate Elementor CSS/data and clear caches after every shared-class,
   variable, component or Loop Item change.
10. Verify every shared component at desktop and mobile.

Novamira must make minimal schema-aware edits. Atomic class references belong in
`settings.classes.value`; local Atomic styles belong in
`styles.<style_id>.variants`. v3 Loop Items retain classic widget structures.

## 27. Design system acceptance

- A dedicated style-guide page exists on staging and is excluded from search.
- Every declared variable/class/component is represented and documented.
- No unexplained raw color, font or spacing values remain in page templates.
- v4 Atomic elements are used for normal structure/content.
- v3 is limited to approved loops and documented compatibility exceptions.
- Every Loop Item has an ID, usage inventory and desktop/mobile screenshot.
- Variables are synced so v3 and v4 output match.
- Header, forms, job cards, portal cards and buttons match the prototype.
- No horizontal overflow at required widths.
- Editor and frontend output match after regeneration.
- The implementation passes `04-ACCEPTANCE-QA.md`.
