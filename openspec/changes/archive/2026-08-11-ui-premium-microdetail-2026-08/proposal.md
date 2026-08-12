# Proposal: Premium UI Microdetail and Honest KPI Comparisons

## Intent
OdontoSuite is readable but not premium because the dashboard canvas and cards both compute white, borders are opaque outlines, shadows are untinted Tailwind defaults, interactions mostly use generic easing, and KPI cards omit decision context. This change should make reception and administration read today’s operational state instantly without moving information architecture. “Citas Hoy” leads staffing/chair allocation; other metrics provide growth and volume context. Deltas must be genuine clinical-record data, not decoration.

## Scope
### In Scope
- Token-first foundation: canvas/surface separation, tinted elevation, alpha hairlines, nested radii, durations, focus ring, font features, generated CSS, additive PHPUnit invariants.
- Reusable primitive states using `ease-ios` and existing `useSpring` runtime; preserve reduced-motion/transparency/contrast behavior.
- Real API comparisons: appointments today vs same weekday last week; patients registered this month vs last month; month-to-date appointments vs the same day span last month. Omit chips when prior rows/value is zero; no Infinity/100% fabrication. Professionals and cash remain chipless. Add Feature tests.
- Polish acceptance screens (Login, Dashboard, 404), including fixed-slot KPI anatomy and optional delta chip.
- Preserve labels/order, routes, form fields/order, existing accessibility contracts, and all listed source/design invariants.

### Out of Scope
- Visual retouching of the other ~17 modules; they inherit the foundation later.
- Navigation information-architecture changes, new animation dependencies, dark mode, gradients, or fabricated/local-storage comparisons.

## Capabilities
### New Capabilities
- `dashboard-period-comparisons`: additive, period-aware KPI comparison data and omission rules.
- `premium-design-foundation`: reusable tokens, primitive interaction states, and exemplar application.
### Modified Capabilities
- None; existing API shape remains additive.

## Approach
Use a stacked-to-main chain, token → primitives → backend → exemplars, because it fixes propagation points before screen polish and isolates the data contract. Forecast each slice below 400 authored changed lines: PR1 foundation ~260; PR2 primitives ~300; PR3 backend/tests ~260; PR4 exemplars ~360. Generated CSS is reviewed for parity but excluded from authored-risk counting. PR3 uses calendar-safe same-weekday and same-day-span queries, with zero-history omission.

## Risks and Rollback
| Risk | Mitigation |
|---|---|
| Date boundaries/timezones distort comparisons | application clinic timezone, explicit inclusive ranges, Feature cases around month/weekday boundaries |
| Uneven KPI content returns | fixed reserved slots; optional chip never collapses layout |
| Premium styling harms accessibility/performance | source assertions, Playwright at exemplar screens, reduced-* fallbacks, one blur surface |
| Chain integration drift | additive contracts and independently reversible PRs |

Rollback is reverting the applicable stacked PR; backend fields are additive and safely ignored by older clients.

## Success Criteria
- [ ] PHPUnit source assertions preserve all existing invariants and add token/primitive/API coverage.
- [ ] API emits only the three defined comparisons, omits no-history/zero-baseline chips, and never emits non-finite percentages.
- [ ] Playwright confirms canvas/surface contrast, tinted elevation, fixed KPI baselines, coherent press/focus states, and recognizable unchanged IA on Login/Dashboard/404.
- [ ] Accessibility contracts and loading/empty/error behavior remain intact.
