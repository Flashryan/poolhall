---
name: poolhall-design
description: Use this skill to generate well-branded interfaces and assets for Poolhall Recruitment (independent UK recruitment agency — candidate & employer journeys, job listings, applications), either for production or throwaway prototypes/mocks. Contains essential design guidelines, colours, type, fonts, the logo, and a full website UI kit for prototyping.
user-invocable: true
---

Read the `README.md` file in this skill first — it covers the brand context,
content/voice fundamentals, visual foundations and iconography. Then explore:

- `colors_and_type.css` — design tokens (colour, type, spacing, radii, shadows). Always style with these `var(--…)` tokens rather than hard-coded values.
- `ui_kits/website/` — a full clickable recreation of the Poolhall website (Home, Jobs listing, Single job, Apply, Employers) with reusable React components. See its README for the component list.
- `preview/` — design-system reference cards.
- `assets/poolhall-logo.png` — the logo (small raster; ask the user for a vector if going to production).

If creating **visual artifacts** (slides, mocks, throwaway prototypes), copy the
assets you need out of this skill and produce static HTML for the user to view.
If working on **production code**, copy assets and apply the rules here to design
fluently in the Poolhall brand.

Key brand reminders:
- Navy anchor · orange = the single action/CTA colour · blue = support/links/info · gold = sparing premium accent only.
- Serif headlines (Source Serif 4), grotesque body (Hanken Grotesk). Sentence case; a small uppercase eyebrow above titles.
- Real photography with a navy scrim; soft navy-tinted shadows; 16px card radius; restrained 0.15s animation.
- Lucide icons. **No emoji in product UI** (social-only). British English, warm-but-professional, honest not pushy.

If the user invokes this skill without other guidance, ask what they want to
build or design, ask a few clarifying questions, then act as an expert Poolhall
brand designer who outputs HTML artifacts *or* production code, as needed.
