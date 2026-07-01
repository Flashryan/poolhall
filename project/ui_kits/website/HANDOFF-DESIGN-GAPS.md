# Design-gap handoff — prototype → WordPress build

For the Claude Code session working from `11-AS-BUILT-SCAFFOLD.md`. Every
design gap in scaffold §7 now has a designed reference in this prototype
(`project/ui_kits/website/`). Open `index.html` and use the routes/controls
below to see each state. As always: the prototype is the **visual contract**;
re-implement via `shared.css` ph-* classes + the builder scripts, don't port
the JSX.

| Scaffold gap | Where in the prototype | Notes for the build |
|---|---|---|
| 1. Better Job Adverts page | `BetterJobAdverts.jsx` (footer → "Better Job Adverts", or route `bja`) | Hero → 6 proposition cards (`.prop-card`) → price-TBC note (`.price-note`) → 3-step "how it works" → navy CTA band. **No price anywhere** — the pill says "Pricing confirmed when you enquire"; swap in the real fee once Ryan confirms. CTAs point at the employers enquiry anchor. |
| 2. Jobs archive filter/sort UX | `Jobs.jsx` | Working filters (sector/type/work-mode/salary-min/keyword/location), sort, **applied-filter chips** (`.applied-chip`) with per-chip remove + "Clear all", live result count, **empty state** (`.empty-state` — pick Insurance to see it), and the **mobile filter drawer** (`.drawer`, ≤900px; the fixed sidebar hides). Drawer footer = "Clear all" + "Show N roles". |
| 3. Mobile drawer upgrades | `ui.jsx` → `Header` | Burger (≤960px) opens a right-hand drawer: nav links → "I'm a…" audience switch → "Your account" sign-in link → full-width "Browse live jobs" button. Same `.drawer` shell as the filters drawer. |
| 4. Single job enrichment | `JobSingle.jsx` | Meta cluster in the navy header (location/type/work-mode/posted), featured badge, **expired state** (warm notice banner + "Role closed" disabled CTA + "Browse live roles" swap in the aside — use the bottom-right "prototype" pill to toggle), **save-job control** (bookmark ghost button → filled "Saved" state), similar-roles grid. |
| 5. Home reviews carousel | `Home.jsx` (already present) | `.review` card = `ph-card--review`. Section ships once the Places key exists. |
| 6. Featured card polish | `blocks.jsx` → `JobCard` | Meta cluster, summary clamp, featured badge top-right, **orange top border only when featured** (`.jobcard.feat`). |
| 7. Candidate portal visual pass | `Portal.jsx` (header "Sign in", or route `portal`) | All six built pages: login / register / verify-email / forgot / reset (centred `.auth-card` with logo, links between states) + **security** (authed layout: change-password card, active-sessions list with current-device badge, sign-out-all). The grey mono tab strip at the top is a prototype-only switcher — don't build it. |
| 8. Empty/edge states | `Jobs.jsx` empty state · `JobSingle.jsx` expired state · long-title wrapping is exercised by the sample data | Empty carousel on home: reuse `.empty-state` minus the dashed border, copy "New roles are added all the time — register your CV". |
| 9. Blog | **Not designed** — needs the Wix content allowlisted first. |

## Guardrails honoured (scaffold "do not invent" list)
- BJA price: omitted everywhere; neutral "confirmed when you enquire" pill.
- Team emails / LinkedIn: none added (existing footer socials are brand-level).
- No client names, consultant salary/commission, or partner terms anywhere.
- Combined-experience figure: not used on any NEW page (note: the older Home/Team
  stat strip still shows "50yrs" from the v1 prototype — confirm 30 vs 50 before build).

## Prototype-only affordances (do not build)
- `.proto-tabs` strip on the portal (screen switcher).
- `.proto-toggle` pill on single job (expired-state toggle).
