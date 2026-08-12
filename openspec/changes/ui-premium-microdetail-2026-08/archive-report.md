# Archive Report: ui-premium-microdetail-2026-08

**Closed:** 2026-08-11 · **Status:** complete, delivered, verified

## Intent

The previous change (`ui-refresh-apple-clinical-2026-08`) swapped the palette and typography to a pure iOS clinical aesthetic. The user confirmed it improved but still did not read as premium, supplying two reference dashboards as the bar. A runtime audit found the reason was not colour: the palette and typography had landed, but the **material**, **numeric craft** and **micro-interaction** layers never had.

## What shipped

Five stacked PRs, all merged to `main` (tip `1800744`):

| PR | Slice | Diff |
|---|---|---|
| #7 | Token foundation | 656 code lines + SDD artifacts |
| #5 | Primitive interaction states | +571 / −89 |
| #6 | Dashboard period comparisons (API) | +805 / −3 |
| #8 | Dashboard KPI rebuild | +1166 / −158 |
| #9 | Login, 404, sidebar polish | +124 / −63 |

**Tests at close:** 137 passed / 1055 assertions (`--testsuite=Unit --filter="DesignSystem|UiRefresh"`); 15 passed / 108 assertions (`DashboardComparisonTest`, MySQL against `odontosuite_test`).

## Evidence that motivated the work

Measured at 1440×900 with computed-style probes:

- `document.body` computed `background-color: rgba(0,0,0,0)` while cards were also white — no canvas/surface contrast, so cards read as outlines rather than objects.
- Card borders computed `1px solid rgb(198,198,200)`: a hard opaque outline, not a hairline.
- Card shadows were Tailwind's pure-black `shadow-lg`, present on only 15 of 292 rendered elements.
- 55 of 292 elements carried a transition and **every one** used the Tailwind default `cubic-bezier(0.4, 0, 0.2, 1)`. The `ease-ios` curve already existed in the config and was applied almost nowhere.
- Press feedback existed on 3 of 33 primitives — and on Card and Avatar it was dead code, because no template ever bound the `data-clickable` attribute it keyed on.

## Capabilities promoted to `openspec/specs/`

- `premium-design-foundation`
- `dashboard-period-comparisons`

## Decisions worth carrying forward

- **Comparison periods.** Appointments compare against the same weekday last week, never day-over-day: a clinic runs on a weekly rhythm, so day-over-day would make every Monday read as a spike against Sunday. Month-to-date compares against the same day span of the previous month, never the previous full month.
- **`total_patients` is guarded.** The headline keeps its cumulative-active meaning; the comparison describes new registrations as a separate absolute quantity, never a percentage of the cumulative count. An earlier draft would have silently changed what the number meant.
- **Omission over fabrication.** `delta_label` is null when the previous period is zero or absent — but not when the current value is zero, because 0 today against 4 last Tuesday is precisely what a receptionist needs to see. The label is pre-formatted server-side, so `Infinity`/`100%` are structurally unreachable rather than merely forbidden.
- **Additive API shape.** Comparisons live in a sibling `data.comparisons` block. Nesting them under the stat keys would have turned integers into objects and broken every current client.
- **Elevation is hue-tinted** (`rgba(60,60,67,α)`, two layers from rung 2 up), not pure black. An untinted black shadow on a near-white canvas is the defect, not the fix.
- **Information architecture was frozen** throughout: nav labels and order, KPI card order and labels, quick-action labels, route slugs, and form field names and order are unchanged.

## Process lesson

Three separate defects shared one root cause — **a test that pins an example instead of the rule**:

1. PR1's Tailwind regex never matched, so the test asserted nothing at all.
2. PR3 hardcoded the month literal `"ago"`, correct only in August.
3. PR5 wrote a broken camelCase custom-property name **and** a test asserting that exact broken string.

All three passed green. When a spec gives an example string, assert the rule that produces it; the example is a sanity check, not the contract.

A second lesson: `sdd-spec` and `sdd-design` were run in parallel. The dependency graph permits it, but design never saw the spec and the two diverged on nearly every concrete value. A validator found 22 conformance problems and a single orchestrator ruling had to reconcile both artifacts. Run spec first, then design against the finished spec.

## Open items (not done — do not read this as complete)

1. **Per-KPI sparklines** — deferred. No per-day time series exists in the API; delivering one needs a new endpoint plus a cache strategy.
2. **Two-tone numerals** — rejected as decision D12, marked REVERSIBLE, pending explicit user override. In these cards the number is the clinical datum, so fading trailing digits costs legibility for no comprehension gain.
3. **Cosmetic** — the `vs mar 4 …` comparator label clips against the 48 px icon plate on the Citas Hoy card.
4. **Spec wording nit** — the banned-chevron scenario is written as a broad `rg` over the dashboard module, but the implementing test deliberately scopes to `<UiCard data-action="…">` blocks, so the glyph legitimately survives in two section-header buttons. Tighten the wording, not the test.
5. **Stale spec details** — the four-slice PR mapping was superseded by the five-slice plan, and one scenario dates 2026-08-11 as a Monday when it is a Tuesday.

## Pre-existing defect found, deliberately left out of scope

Migration `2025_10_25_000000_add_cash_register_fields_to_transactions_table.php` drops `transactions.type` while the composite index `idx_transactions_patient_type_status`, created in `2025_10_24_203226`, still references it. MySQL removes the column from the index automatically; SQLite refuses. Because `phpunit.xml` pins SQLite `:memory:`, **no `RefreshDatabase` Feature test can run on the repository default**, which is why database-backed tests here run against `odontosuite_test` on MySQL.

This was re-verified with MySQL confirmed running, so it is a schema-portability defect and not an environment issue. It predates this change and warrants its own.
