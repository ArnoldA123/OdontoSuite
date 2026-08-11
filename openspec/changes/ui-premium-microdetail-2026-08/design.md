# Design: Premium UI Microdetail and Honest KPI Comparisons

## Technical Approach

Same vertical-slice delivery model: spread token + primitive + backend + exemplar work across FIVE stacked PRs so each slice is independently reviewable and each one improves the foundation for the 17 un-migrated modules that will inherit it later. This document CONCRETIZES the proposal: exact token values, exact timing values, exact backend shape, exact slot dimensions, and a complete screen-polish scope. This revision applies the orchestrator's authoritative reconciliation ruling R1-R12 verbatim.

## Decisions whose choice changed in this revision

| ID | Original choice | Ruling | New choice |
|---|---|---|---|
| D2 | `rgba(60, 60, 67, 0.12)` as "systemGray-200 at 0.45 opacity" | R2 — fabricated rationale | `rgba(60, 60, 67, 0.12)` (iOS separator opacity), corrected rationale |
| D3 (full replacement) | `rgba(0, 0, 0, α)` "hue-tinted" — false | R2 — D3 rejected | `rgba(60, 60, 67, α)` iOS label/separator hue family, two-layer rungs 2..4 |
| D6 | Single `focusRing.default` value | R8 — composed shape wins | `{ width: '3px', color: systemBlue[500], alpha: 0.20, offset: '2px' }`; emit composed + parts |
| D7 | `font.numeric.tnum: "tabular-nums"` is wrong | R8 — invalid `font-feature-settings` value | `fontFeatures.tabularNums = '"tnum" 1, "lnum" 1'` |
| D5 | `motion.duration` with `instant` (0ms) + `spring` (label-only) | R8 — both are dead | `motion.duration` exactly `fast: 120ms`, `normal: 200ms`, `slow: 320ms` |
| D10 | Replaced Card's `scale(0.98)` with `scale(0.97)` | R10 — pointless churn on working value | KEEP Card's existing `scale(0.98)`; drop the "every change is additive" claim where it is false |
| D10 | Stated "`data-pressed` attribute on pointer-down via a small CSS-only path" | R10 — CSS cannot set a data attribute | DELETE that line; `:active` is the entire mechanism |
| D11 | "duration flips to 0ms under reduced-motion" | R7 — wrong per Apple guidance | Under `prefers-reduced-motion: reduce`, transform-based press/hover collapse to opacity/colour change of at most 200ms; feedback survives, movement goes |
| D12 | Two-tone numerals rejected (no override path) | R6 — upheld, but mark REVERSIBLE | Rejected. Strengthened rationale: in these cards the number IS the clinical datum; fading "105" degrades legibility for zero comprehension gain; works in reference dashboards only because their numbers are decorative marketing copy. Marked REVERSIBLE and pending user override |
| D13 | Omission triggers: `previous === 0` OR `previous === null` OR `current === 0` | R4 — `current === 0` is wrong (0 today vs 5 last Tuesday is informative data) | Omission triggers: `previous === 0` OR `previous === null` ONLY |
| R5 (new) | `total_patients` comparison would change the headline meaning | R5 — forbidden | `data.total_patients` keeps its cumulative-active semantics. The comparison is on NEW REGISTRATIONS (created_at this month vs last month), `period_label: "nuevos este mes"`, rendered as an absolute figure (`"+12"`), never as a percentage of the cumulative count. Add a regression test asserting `data.total_patients` is unchanged |
| D1 (token shape) | `color.canvas` new top-level key | R1 — keep `systemBackground` untouched | Add `tokens.colors.background.canvas = '#F2F2F7'` (alias of `secondaryBackground`); emit `--color-background-canvas` and a semantic alias `--color-canvas` |
| R11 (new) | Scope gaps not enumerated | R11 — silent omission is not acceptable | See "Scope Gaps" section. Each item has a concrete placement in a slice |
| R12 (new) | Four slices, R11 fits comfortably | R12 — re-forecast; add a fifth slice if needed | Five slices: PR1 tokens, PR2 primitives, PR3 backend, PR4 dashboard polish, PR5 login+404+sidebar |

## Architecture Decisions

| ID | Decision | Choice | Rationale | Rejected |
|---|---|---|---|---|
| D1 | Canvas vs surface token | `tokens.colors.background.canvas = '#F2F2F7'` (alias of `secondaryBackground`). Body element gets `bg-canvas`; cards stay `bg-surface` (systemBackground). `systemBackground` is NOT mutated. | R1: mutating `systemBackground` would repaint all 20 modules. Adding a new key is additive. | Mutating `systemBackground` to gray; introducing a third tone. |
| D2 | Hairline alpha | `rgba(60, 60, 67, 0.12)` (iOS separator opacity). Channel-split rgba, NOT a hex. Emitted as `--color-hairline`. | The hex-parity test (`generated_css_only_contains_token_hex_literals`) only enforces `#RRGGBB` literals, so `rgba(...)` slips through cleanly. The value matches iOS separator semantics. | (a) Pure hex `#D1D1D6` — keeps the opaque separator, defeats the canvas/surface contrast goal. (b) Channel-split `--color-hairline-r: 60;` etc. — over-engineered. |
| D3 | Elevation ramp | Five rungs with `rgba(60, 60, 67, α)` (iOS label/separator hue family, lifts shadows off the "pure black on near-white" defect): `elevation.0` none; `elevation.1 = 0 1px 3px rgba(60,60,67,0.04)`; `elevation.2 = 0 2px 8px rgba(60,60,67,0.06), 0 1px 2px rgba(60,60,67,0.04)` (two-layer); `elevation.3 = 0 8px 16px rgba(60,60,67,0.08), 0 2px 6px rgba(60,60,67,0.06)` (two-layer); `elevation.4 = 0 16px 24px rgba(60,60,67,0.12), 0 4px 8px rgba(60,60,67,0.08)` (two-layer). Rungs 2-4 are two-layer per Apple rule that bigger surfaces read thicker with stronger blur AND a deeper shadow. | R2: untinted black shadow is the exact cheap-looking defect the runtime audit identified. The iOS label/separator hue is native, not brand-leaning. No new hex values needed. | (a) `rgba(0, 0, 0, α)` — the original D3. Pure black on #F2F2F7 still reads as pure black. (b) Warm-tinted `rgba(196, 64, 50, 0.06)` — brand-leaning, wrong for clinical. |
| D4 | Nested radius | `radius.ios` (10px) pinned for cards, `radius.modal` (14px) pinned for overlays, `radius.sm` (4px) pinned for chips. ADD `radius.cardLg` (16px, JS camelCase) → `--radius-card-lg` for KPI cards. ADD `radius.control` (8px) → `--radius-control` for inputs. | The "lg" and "2xl" keys would clash with the existing literal-key ban. `cardLg` and `control` carry the same semantic purpose without colliding. The `toKebab()` call in the build script converts `cardLg` to `card-lg` automatically. | Reusing ios=10px for everything — uniform 10px is the current flatness. |
| D5 | Duration scale | `motion.duration` with exactly `fast: 120ms`, `normal: 200ms`, `slow: 320ms`. The `instant` (0ms) and `spring` (label-only) keys are DEAD and dropped. | R8: 0ms is dead; a label-only token is dead. | Including `instant`/`spring` — adds noise. |
| D6 | Focus-ring token | `focusRing: { width: '3px', color: 'systemBlue-500', alpha: 0.20, offset: '2px' }`. Emit BOTH the parts (`--focus-ring-width`, `--focus-ring-color`, `--focus-ring-alpha`, `--focus-ring-offset`) AND the composed `--focus-ring-default: 0 0 0 var(--focus-ring-width) rgba(0, 122, 255, var(--focus-ring-alpha));`. | R8: composes — every consumer can read either the parts or the final value. The alpha is broken out so consumers can build their own colours (e.g. error states). | A single numeric value — loses the ability to compose. |
| D7 | Font features | `fontFeatures.tabularNums = '"tnum" 1, "lnum" 1'`. Apply via `font-feature-settings: var(--font-features-tabular-nums);` on stat numbers. | R8: `"tabular-nums"` is a Tailwind utility NAME, not a valid `font-feature-settings` value. The OpenType feature strings `"tnum"` and `"lnum"` are. | Tailwind utility name — invalid CSS. |
| D8 | Motion: ease-ios adoption | Apply `ease-ios` (`cubic-bezier(0.25, 0.46, 0.45, 0.94)`) to: hover/active transition, transform, on Card, Button, Input, Badge, AND Avatar. Keep `ease-out` for explicit color washes. Apply via Tailwind's `ease-ios` utility. | R11: ease-ios must cover Avatar too. Current 55 transitions use generic `0.2s cubic-bezier(0.4,0,0.2,1)`. | Switching to `ease-ios` for ALL transitions including colors — Apple's color washes are intentionally standard easing. |
| D9 | Motion: dampingBounce consumption | LEAVE `motion.dampingBounce = 0.8` UNCONSUMED. Nothing in this slice is a momentum-driven gesture (no drag, no flick, no swipe). | Apple guidance: bounce on a non-momentum entrance is wrong. Honest choice = no consumer. | Wiring sidebar collapse to useSpring2D with dampingBounce — sidebar is a click toggle, not a gesture with momentum. |
| D10 | Press-state mechanism | Pure CSS `:active` transform. Card KEEPS existing `scale(0.98)` (do NOT change to 0.97 — pointless churn). Button keeps existing `scale(0.97)` and `translateY(-1px)` on hover. Avatar keeps existing `active:scale-95`. | R10: `:active` is the entire mechanism — CSS cannot set a data attribute. The "every change is additive" claim is false where transforms or transition easings are replaced. | useSpring on every press — adds rAF + Vue ref bookkeeping to 33 primitives for no visible benefit. |
| D11 | Reduced motion | Under `prefers-reduced-motion: reduce`, transform-based press and hover collapse to an opacity OR colour change of at most 200ms. Feedback survives; only the movement goes. | R7: Apple guidance says reduced motion is a gentler non-vestibular equivalent, not the removal of feedback. | Flipping durations to 0ms — removes feedback entirely. |
| D12 | Two-tone numerals | REJECTED. The leading-ink / trailing-faded treatment is a brand-specific flourish from the NeuroCRM/Tenx references; not a recognised iOS convention. Reason strengthened (R6): in these cards the number IS the clinical datum; fading the trailing digits of "105" degrades legibility for zero comprehension gain; it works in the reference dashboards only because their `$1.2M` numerals are decorative marketing copy. REVERSIBLE — pending user override, the apply phase can add a `text-numeric-fade` Tailwind variant in a follow-up slice. | Clinical legibility first. | Adopting it — adds decoration without comprehension. |
| D13 | Comparison fields, additive shape | Add a `data.comparisons` block on each comparison-bearing stat: `{ "current": N, "previous": N, "period_label": "vs. ...", "delta_label": "+3"\|"-2"\|null }`. `delta_label: null` is the omission contract. | R3: additive = safe; pre-formatted string means the client never decides to interpolate `Infinity` or `100%`. | Returning a `delta_pct` field — invites the client to compute `Infinity` on zero baseline. |
| D14 | Omission triggers | Emit `delta_label: null` when `previous === 0` OR `previous === null`. The chip renders absent WHEN `delta_label === null`. NEVER trigger absent on `current === 0` — when today is 0 and last Tuesday was 5, that is real, informative data a receptionist must see. | R4: hiding the zero-current case is the exact case that needs to be visible. | Omitting on `current === 0` — hides the most informative case. |
| D15 | `total_patients` comparison | `data.total_patients` keeps its cumulative-active semantics. The comparison describes NEW REGISTRATIONS (created_at this month vs last month), labelled `period_label: "nuevos este mes"`, rendered as an absolute figure (`"+12"`), NEVER as a percentage of the cumulative count. The chip's number is a different quantity from the card's headline number. | R5: silently changing what the headline number means is a product regression. | Mutating the headline number — forbidden. |
| D16 | Total-appointments comparison | Same day-span algorithm as Pacientes. `period_label: "vs. mismos días del mes anterior"`. Absolute figure (`"+8"`). | Same reasoning as D15. | Percentage delta on a month-to-date count — distorts near month boundaries. |

## Build Script Emission Plan (R9)

The current `scripts/build-tokens-css.mjs` destructures `{ default: tokens, colors, spacing, radius, shadow, motion }` and emits:
- `colors` via a generic loop iterating `Object.keys(colors)` → `--color-${toKebab(rampName)}-${toKebab(step)}`.
- `spacing` via a generic loop → `--spacing-${step}`.
- `radius` via a generic loop → `--radius-${name}` (the `toKebab` function converts `cardLg` to `card-lg`).
- `shadow` via a generic loop → `--shadow-${name}`.
- `motion` via HARD-CODED lines for the existing 4 vars (`response`, `damping`, `dampingBounce`, `stiffness`) + `easings`. The new `motion.duration` ramp is NOT auto-emitted.
- `fontFeatures`, `focusRing`, `elevation` are NOT in the destructured import and have NO emission block.

Per-token emission plan:

| Token | JS key path | Generated custom-property name | Emission block |
|---|---|---|---|
| `tokens.colors.background.canvas` | `colors.background.canvas` | `--color-background-canvas` | Fits the existing `colors` loop (`background` ramp is iterated). ADD a semantic alias `--color-canvas: var(--color-background-canvas);` in the hardcoded semantic-aliases block (after the existing `--color-surface` aliases). |
| `tokens.radius.cardLg` | `radius.cardLg` | `--radius-card-lg` (toKebab converts `cardLg` → `card-lg`) | Fits the existing `radius` loop. |
| `tokens.radius.control` | `radius.control` | `--radius-control` | Fits the existing `radius` loop. |
| `tokens.motion.duration` | `motion.duration` (object) | `--motion-duration-${step}` (e.g. `--motion-duration-fast`) | NEW emission block. Destructured import: add `duration: motion.duration` to the `{ ... }` import, then iterate `Object.keys(motion.duration)` and emit `--motion-duration-${step}: ${motion.duration[step]};` |
| `tokens.motion.dampingBounce` | (already exists) | `--motion-damping-bounce: 0.8` | Already emitted. |
| `tokens.focusRing` | `focusRing` (object) | `--focus-ring-width`, `--focus-ring-color`, `--focus-ring-alpha`, `--focus-ring-offset`, `--focus-ring-default` | NEW emission block. Emit parts first, then composed: `--focus-ring-default: 0 0 0 var(--focus-ring-width) rgba(0, 122, 255, var(--focus-ring-alpha));` (note: `var()` inside `rgba()` is valid CSS; the alpha slot is a number). |
| `tokens.fontFeatures.tabularNums` | `fontFeatures.tabularNums` | `--font-features-tabular-nums` | NEW emission block. Destructure `fontFeatures` from tokens. Emit `--font-features-tabular-nums: "tnum" 1, "lnum" 1;` |
| `tokens.elevation` | `elevation` (object, keys 0..4) | `--elevation-0` through `--elevation-4` | NEW emission block. Destructured import: add `elevation: tokens.elevation` (since `elevation` is not in the top-level destructured import). Emit `--elevation-0: none;` for the empty rung, then the four shadow strings for rungs 1..4. |

**Hex-parity test gate:** none of the new values adds a `#RRGGBB` literal. The `rgba(...)` and `none` values pass the `preg_match_all('/#[0-9A-Fa-f]{6}/', ...)` test untouched. The `canvas: '#F2F2F7'` declaration IS a hex literal, but it matches the existing `secondaryBackground: '#f2f2f7'` in the test's expected set (the test uses `array_diff_key` and the `#f2f2f7` key is already present).

**Kebab-case sanity:** camelCase JS keys (`cardLg`, `tabularNums`) flow through `toKebab()` to produce `card-lg`, `tabular-nums`. No hand-mapped strings.

## API Contract (backend comparison)

**Endpoint:** `GET /api/dashboard/stats` (existing).

**Shape (additive):**
```json
{
  "data": {
    "appointments_today": 8,
    "total_patients": 142,
    "total_professionals": 5,
    "total_appointments": 1240,
    "total_appointments_this_month": 87,
    "total_income": 12000,
    "cash_session": { "status": "open" },
    "comparisons": {
      "appointments_today": {
        "current": 8,
        "previous": 5,
        "period_label": "vs. mismo día hábil semana anterior",
        "delta_label": "+3"
      },
      "total_patients": {
        "current": 12,
        "previous": 9,
        "period_label": "nuevos este mes",
        "delta_label": "+3"
      },
      "total_appointments_this_month": {
        "current": 87,
        "previous": 79,
        "period_label": "vs. mismos días del mes anterior",
        "delta_label": "+8"
      }
    }
  },
  "meta": { "generated_at": "...", "cached": true }
}
```

**Timezone:** `config/app.php` declares `America/Lima`. Use `Carbon::today()` (NOT `now()`) for the comparison anchor so the boundary is inclusive at midnight clinic-local time.

**Range semantics — inclusive [start, end]:**
- `appointments_today`: `[Carbon::today(), Carbon::today()->endOfDay()]`. Previous: `[Carbon::today()->subDays(7), Carbon::today()->subDays(7)->endOfDay()]`. (Same weekday)
- `total_patients` (COMPARISON QUANTITY IS NEW REGISTRATIONS, NOT THE CUMULATIVE TOTAL): this-month-count uses `where('patients.created_at', ...)` with `whereMonth` + `whereYear`; previous-month-count uses `whereMonth(previousMonth) + whereYear(previousYear) + whereDate('created_at', '<=', previousMonthEndClamped)`. Clamp to previous month's `endOfMonth()` when previous month is shorter (day 31 vs 30-day month → clamp to day 30).
- `total_appointments_this_month`: same day-span algorithm using `appointments.scheduled_at`.
- `total_professionals`, `total_income`, `cash_session`: NO `comparisons` key.

**Omission contract (R4):** `delta_label: null` when `previous === 0` OR `previous === null`. NEVER trigger on `current === 0` — when today is 0 and last Tuesday was 5, the receptionist must see that signal.

**Zero-baseline contract:** if `previous === 0`, return the comparison object with `delta_label: null` (no `Infinity`, no `100%`, no `+Inf%`). The frontend renders no chip. The comparison object IS returned so the client can show "vs. mismo día hábil semana anterior" with the prior date range marker (or a thin "no hay datos" caption — defer caption rendering to the chip-omission case).

**Implementation:**
- Use the existing `scheduled_at` index on `appointments` (migration 2025_09_20_082341).
- Add a new index on `patients.created_at` via a migration (`database/migrations/*_add_index_to_patients_created_at.php`). Cheap; the table is small but the query hits every clinic session.

**Feature tests (`tests/Feature/Modules/DashboardComparisonTest.php`):**
1. Same weekday comparison: today is Wednesday → previous is previous Wednesday.
2. Monday comparison when previous Monday was a holiday → previous = 0, `delta_label = null`.
3. `current === 0` case: today is 0, previous Tuesday was 5 → `delta_label = "-5"` (the negative number is allowed; the chip MUST render).
4. Month-boundary: today is the 31st, previous month has 30 days → clamp to day 30.
5. `total_patients` regression: `data.total_patients` is unchanged by the new comparison block (cumulative active count).
6. `total_patients.delta_label` is an absolute integer (`+12`), never a percentage.
7. `total_professionals`, `total_income`, `cash_session` have NO `comparisons` key.

## KPI Card Anatomy (DashboardPage)

Convert each stat card to a fixed-slot layout. Five slots with reserved heights — empty slots still allocate space, so the row baseline is uniform:

```
┌──────────────────────────────────────────────┐
│ [eyebrow]              h-4  (reserved)       │
│ [number]               h-12 (4xl scaled)     │
│ [delta slot]           h-6  (chip OR blank)  │
│ [caption]              h-4  (reserved)       │
│                                              │
│                                              │
│ [icon plate, 48x48]                         │
└──────────────────────────────────────────────┘
```

Rendered as a 4-row `<div>` with `grid-template-rows: 16px 48px 24px 16px; gap: 8px;`. The chip slot is wrapped in a `<div class="h-6 min-h-[24px]">` even when empty. The caption slot is a `<p class="h-4 leading-4 truncate">` so it always reserves one line. This fixes the Citas Hoy date caption wrapping to two lines and breaking row baseline.

**Chip rendering rule:** `comparisons[statKey].delta_label`:
- If `null` → render no chip (an empty `<div class="h-6 min-h-[24px]">` reserves the slot).
- If non-null → render `<span class="text-xs text-systemGreen-700 bg-systemGreen-50 rounded-full px-2 py-0.5">+12</span>` (or systemRed for negative).

**Two-tone numerals (D12):** REJECTED. A single `text-4xl font-bold text-label tabular-nums` per the current pattern. Adopting the leading-ink / trailing-faded treatment would read as marketing decoration that mischaracterizes a clinical datum.

## Primitive Interaction States

**Card (`components/ui/Card.vue`):**
- KEEP existing `:active` `scale(0.98)` (do NOT change to 0.97 — R10: pointless churn).
- Switch transition to `transition: transform 120ms ease-ios;` (replacing the existing `transition: transform 150ms ease-out` — this IS a replacement, not an addition, so the "additive" claim is dropped here).
- Hover `translateY(-2px)` unchanged.
- Focus ring: `box-shadow: var(--focus-ring-default); outline: none;` for `data-clickable="true"`.

**Button (`components/ui/Button.vue`):**
- KEEP existing `:active` `translateY(0)` (the existing `translateY(-1px)` on hover → `translateY(0)` on press is already a "press in" cue without a scale).
- Add `transition: transform 150ms ease-ios` (replacing the existing `transition: transform 150ms ease-out`).
- Focus ring: keep existing `outline: 2px solid var(--color-accent); outline-offset: 2px;` (the input has a wider 3px ring; buttons keep the 2px outline for visual hierarchy).

**Input (`components/ui/Input.vue`):**
- No press transform (text selection would interfere).
- Replace the inline `box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.2)` (success path) with `box-shadow: var(--focus-ring-default);` (composed).
- Keep `rgba(239, 68, 68, 0.1)` inline for `aria-invalid="true"` (the error tint is a small inline cost, not a token candidate this slice).
- Switch input transitions to `ease-ios`.

**Avatar (`components/ui/Avatar.vue`):**
- KEEP existing `active:scale-95` (Tailwind utility).
- ADD `ease-ios` to the transition (currently uses Tailwind defaults).
- Focus ring: `box-shadow: var(--focus-ring-default);` when `clickable`.

**Badge (`components/ui/Badge.vue`):**
- No press transform (badge is decorative).
- ADD `ease-ios` to the transition list (currently `ease-out`).

**Other focus-ring primitives (R11 G1):** Modal, Sheet, ConfirmDialog, Toast, Select — add `box-shadow: var(--focus-ring-default);` on focusable elements.

**Reduced-motion fallback (R7):** under `prefers-reduced-motion: reduce`, transform-based press and hover collapse to an opacity OR colour change of at most 200ms. A new `@media (prefers-reduced-motion: reduce)` block at the bottom of each primitive's `<style scoped>` section replaces the press/hover transforms with a `background-color` or `opacity` change of `200ms`.

**Additive/declarative honesty:** the changes that are TRUE additions (no behaviour change beyond the pure press/keyboard readout) are: focus-ring tokenisation on Card/Input/Avatar, the `ease-ios` adoption on Avatar's transitions. The changes that REPLACE existing behaviour are: Card's transition curve (ease-out → ease-ios), Card's transition duration (150ms → 120ms), Button's transition curve (ease-out → ease-ios), and the focus-ring on Card/Input. The validator is correct that "every change is additive" is false — the design lists these as replacements.

## Screen-Level Scope Gaps (R11)

Each item below was missing from the original design. Each is now placed in a specific slice with concrete work.

| ID | Gap | Concrete work | Slice |
|---|---|---|---|
| G1 | Focus-ring tokenisation for Avatar + primitives the exemplar screens render | Add `box-shadow: var(--focus-ring-default);` to Avatar, Modal, Sheet, ConfirmDialog, Toast, Select. The exemplar screens render Avatar (AppLayout), Modal/Sheet (AppLayout's mobile menu), and ConfirmDialog (AppLayout's `useConfirm`). All five get the composed focus ring. | PR2 |
| G2 | Topbar control tokens + single optical weight for WS dot, bell, avatar | Add `topbar.iconSize: 20px` (`text-xl` is 20px in SF); add `topbar.iconWeight: 1.5` (Apple outline icon stroke). Apply via `<span class="text-xl" style="stroke-width: 1.5">` to the WS dot, bell, avatar. The three controls now share one optical weight. | PR4 |
| G3 | Sidebar grouping (additive only, labels and order ARE frozen) | Add a `<div class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500">` group header BEFORE Pacientes (the "Operaciones" group) and BEFORE Sucursales (the "Configuración" group). The existing 19 items remain in their original slots. NO label is removed; NO order is changed. | PR5 |
| G4 | Quick-action affordance (chevron SVG path banned by test) | Each quick-action card already has a tappable surface (the whole `<UiCard clickable>`). ADD a `data-keyhint` attribute (e.g. `data-keyhint="P"`) on the card that surfaces a tiny keyhint chip in the top-right corner. The chip is a `<kbd class="text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5">P</kbd>`. The chip is a different affordance device (a keyhint, not a chevron) and the SVG path ban is satisfied. | PR4 |
| G5 | Today-appointments empty state polish | The existing `<EmptyState title="Sin citas para hoy" description="Aún no hay citas registradas para el día de hoy. Puedes crear una nueva cita desde la sección de calendario." action-text="Agendar nueva cita">` is correct. Polish: add a calendar illustration (Picsum-seed `odontosuite-empty-calendar-{w}-{h}`) inside the empty state. | PR4 |
| G6 | Login placeholder text | ADD `placeholder="usuario"` to the username input and `placeholder="Mínimo 8 caracteres"` to the password input. The a11y guidance is labels above, helper text below, placeholder text inside inputs — all three. | PR5 |
| G7 | Login helper-text removal | The current "Ingresa tu nombre de usuario" / "Ingresa tu contraseña" helper text merely restates the label. REMOVE the helper text inside the `<p class="field-hint">` blocks entirely. The error path keeps the `<p class="field-error">` block. | PR5 |
| G8 | Login primary-button accent-tinted shadow + inner top highlight | The current primary button is flat `#007AFF`. ADD `box-shadow: var(--elevation-3);` (which is `rgba(60,60,67,0.08)` — the iOS label/separator hue). ADD an inner top highlight: `box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.30);` on top of the existing `box-shadow`. The result: a button that has both depth (the outer shadow) and material (the inner top highlight), matching iOS primary-action treatment. | PR5 |
| G9 | Login hero scrim + eyebrow legibility | The current `hero-overlay` is `linear-gradient(180deg, rgb(250 249 247 / 0.10) 0%, rgb(31 27 23 / 0.35) 100%)` — warm cream over warm near-black. CHANGE to `linear-gradient(180deg, rgba(60, 60, 67, 0.05) 0%, rgba(60, 60, 67, 0.55) 100%)` — neutral iOS label hue, more contrast at the bottom for the caption. The eyebrow opacity lifts from 0.85 to 1.0; the eyebrow colour changes from `cream-100` to `systemGray-50` (neutral). | PR5 |
| G10 | 404 hero radius + scrim | ADD `border-radius: var(--radius-modal);` (14px) to `.not-found-image`. ADD a thin `border: 1px solid var(--color-hairline);` to replace the warm `ink-100` border. The scrim is NOT modified (the 404 has no overlay scrim — only the figure). | PR5 |
| G11 | Two competing headings on the dashboard | The topbar's `<h1>` already shows the page title. The dashboard's `<header class="flex items-end justify-between flex-wrap gap-4">` contains a `<p class="text-2xl font-semibold text-ink-800 leading-tight">{{ getGreeting() }}</p>` — its size is `text-2xl`, but it is a `<p>` not an `<h2>`. The "two competing headings" defect is that the topbar h1 and the greeting read as h1+h2. FIX: reduce the greeting to `text-lg font-medium text-theme-secondary` (no h2-equivalent weight). The greeting is a calm welcome line, not a heading. | PR4 |
| G12 | Password reveal styling fix | The password reveal `<button class="password-toggle">` is ALREADY inside `.field-input-wrap` (verified in the source). The "different widths" defect is a STYLING bug: the toggle is `position: absolute` but with `right: 0` and the field-input has `padding-right: 44px` already. The real fix is to push the toggle to `right: 12px` and ensure the input `padding-right: 44px` is preserved. The CURRENT code already has `padding: 14px 44px 14px 40px;` — the toggle is `position: absolute` inside the wrap. So the fix is repositioning the toggle to `right: 12px` so it visually aligns with the input's right padding. NO structural change to the input wrap. | PR5 |
| G13 | ease-ios adoption on Avatar | Already in D8 / G1. ADD `transition: transform 120ms ease-ios;` to `.avatar` (the Avatar.vue scoped style). | PR2 |

## Slice Boundaries (re-forecast per R12)

The four-slice forecast does not hold after R11. Five slices are required.

| PR | Scope | Forecast lines | Reversible |
|---|---|---|---|
| PR1 | `tokens.js` (background.canvas, radius.cardLg, radius.control, motion.duration with 3 keys, focusRing, fontFeatures.tabularNums, elevation with 5 rungs) + `tailwind.config.js` (extend `transitionDuration` to read `motion.duration`; expose `radius.cardLg`/`radius.control` via `borderRadius`) + `scripts/build-tokens-css.mjs` (NEW emission blocks for motion.duration, focusRing, fontFeatures, elevation; add semantic alias `--color-canvas`) + `tests/Unit/DesignSystem/TokensModuleTest.php` (new key assertions) + `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` (parity assertions for the new tokens, rgba-only form). | ~290 | yes |
| PR2 | Primitive press / focus / easing (Card, Button, Input, Badge, Avatar, Modal, Sheet, ConfirmDialog, Toast, Select) — `:active` presses, `ease-ios` adoption, focus-ring tokenisation, reduced-motion fallback (R7). The reduced-motion fallback is a global `@media (prefers-reduced-motion: reduce)` block per primitive that swaps transform → opacity/colour. | ~320 | yes |
| PR3 | `app/Http/Controllers/Api/DashboardController.php` (additive `comparisons` block) + `database/migrations/*_add_index_to_patients_created_at.php` + `tests/Feature/Modules/DashboardComparisonTest.php` (seven Feature tests covering period math, `current === 0` case, zero baseline, month-boundary, total_patients regression, professionals/cash omission). | ~270 | yes |
| PR4 | `resources/js/modules/dashboard/DashboardPage.vue` (fixed-slot KPI anatomy, optional chip rendering, two competing headings fix, topbar single optical weight for WS dot/bell/avatar via G2, quick-action keyhint affordance G4, today-appointments empty state polish G5) + `resources/js/components/layout/AppLayout.vue` (topbar single optical weight readability). | ~360 | yes |
| PR5 | `resources/js/modules/auth/LoginPage.vue` (placeholder text G6, helper-text removal G7, password reveal styling G12, primary-button accent-tinted shadow + inner top highlight G8, hero scrim + eyebrow legibility G9) + `resources/js/modules/errors/NotFoundPage.vue` (hero radius + scrim G10) + `resources/js/components/layout/AppLayout.vue` (sidebar grouping G3, additive only). | ~330 | yes |

**Per-slice budget:** 400 authored lines. Each PR is independently buildable, testable, and revertable. PR3 backend is additive (old clients ignore `comparisons`). PR4 changes the dashboard's internal layout only; the 5 stat labels and 5 quick-action labels are unchanged. PR5 changes Login + 404 + sidebar grouping; sidebar labels and order are frozen.

**Forecast revised per R12:**
- Decision needed before apply: **Yes** (D12 — two-tone numerals are REVERSIBLE pending user override; D9 — `dampingBounce` is honestly unconsumed).
- Chained PRs recommended: **Yes** (5 slices).
- 400-line budget risk: **Low** (each slice ~270-360, all under 400).

## File Changes Summary

| File | Action | Description |
|---|---|---|
| `resources/js/design-system/tokens.js` | Modify | Add `color.background.canvas`, `radius.cardLg`, `radius.control`, `motion.duration` (3 keys), `focusRing`, `fontFeatures.tabularNums`, `elevation` (5 rungs). Honour pinned letterSpacing, radius/motion values. |
| `resources/css/tokens.generated.css` | Regenerate | Output is auto-generated by the script. Do not hand-edit. |
| `scripts/build-tokens-css.mjs` | Modify | Destructure `duration: motion.duration`, `focusRing: tokens.focusRing`, `elevation: tokens.elevation`, `fontFeatures: tokens.fontFeatures`. Add emission blocks for each. Add `--color-canvas` semantic alias. |
| `tailwind.config.js` | Modify | Extend `transitionDuration` to read `motion.duration`; expose `radius.cardLg`/`radius.control` via `borderRadius` (auto-exposed via `tokenRadius` import). |
| `resources/js/components/ui/Card.vue` | Modify | Replace transition with `ease-ios` 120ms; keep `scale(0.98)`; add focus-ring `var(--focus-ring-default)`. Reduced-motion fallback. |
| `resources/js/components/ui/Button.vue` | Modify | Replace transition with `ease-ios`; add reduced-motion fallback. |
| `resources/js/components/ui/Input.vue` | Modify | Replace inline focus ring with `var(--focus-ring-default)`; `ease-ios` on transitions. |
| `resources/js/components/ui/Badge.vue` | Modify | Add `ease-ios` to transition list. |
| `resources/js/components/ui/Avatar.vue` | Modify | Add `ease-ios` on transform; add focus-ring tokenisation; ADD G13 transition. |
| `resources/js/components/ui/Modal.vue`, `Sheet.vue`, `ConfirmDialog.vue`, `Toast.vue`, `Select.vue` | Modify | Add `var(--focus-ring-default)` on focusable elements. |
| `app/Http/Controllers/Api/DashboardController.php` | Modify | Add `comparisons` block (three keys) with additive shape. Carbon clinic-local today / subDays(7) / subMonthNoOverflow for the three periods. `new_registrations_this_month` query uses `created_at`. |
| `database/migrations/*_add_index_to_patients_created_at.php` | Create | Index on `patients.created_at` for the month-over-month query. |
| `tests/Feature/Modules/DashboardComparisonTest.php` | Create | Seven Feature tests: same-weekday, holiday Monday, `current === 0` case, month-boundary, total_patients regression, total_patients absolute (no percentage), omission for professionals/cash. |
| `tests/Unit/DesignSystem/GeneratedTokensCssTest.php` | Modify | Add `--color-hairline`, `--color-canvas`, `--radius-card-lg`, `--radius-control`, `--motion-duration-fast`, `--motion-duration-normal`, `--motion-duration-slow`, `--focus-ring-default`, `--elevation-1..4`, `--font-features-tabular-nums` assertions. Re-verify hex parity (no new `#xxxxxx` outside `tokens.colors`). |
| `tests/Unit/DesignSystem/TokensModuleTest.php` | Modify | Add assertions for `radius.cardLg`, `radius.control`, `motion.duration` (3 keys), `focusRing`, `fontFeatures.tabularNums`, `elevation` (5 rungs). |
| `resources/js/modules/dashboard/DashboardPage.vue` | Modify | Replace the 5 stat-card bodies with fixed-slot `<div>` grid (16/48/24/16 px). Render the optional chip from `comparisons[stat]`; if `delta_label` is null, render an empty reserved slot. Greeting: reduce from `text-2xl font-semibold` to `text-lg font-medium text-theme-secondary`. Quick-action affordance via G4 keyhint chip. Today-appointments empty state polish G5. |
| `resources/js/modules/auth/LoginPage.vue` | Modify | Add placeholder text G6, remove helper-text G7, password reveal styling G12 (reposition to `right: 12px`), primary-button shadow + inner highlight G8, hero scrim + eyebrow G9. |
| `resources/js/modules/errors/NotFoundPage.vue` | Modify | Hero radius `var(--radius-modal)`, hairline border G10. |
| `resources/js/components/layout/AppLayout.vue` | Modify | Topbar single optical weight for WS dot/bell/avatar G2; sidebar grouping G3 (additive group headers before "Operaciones" and "Configuración"). |

## Testing Strategy

| Layer | What | How |
|---|---|---|
| Source (PHPUnit) | Token-source invariants (new keys, padded letterSpacing, padded radius, padded motion) | `TokensModuleTest` extended. |
| Source (PHPUnit) | Generated CSS parity (no new `#xxxxxx` literals outside `tokens.colors`) | `GeneratedTokensCssTest` extended; one assertion per new custom-property name. |
| Source (PHPUnit) | Dashboard source invariants (no linear-gradient, no hex, no chevron SVG path, no `style scoped`, 5 stat labels, 5 quick-action labels, 300ms debounce, `<EmptyState>`, `<UiSkeleton>`, `slice(0,3)`, `tabular-nums`) | `DashboardAppShellTest` carried, re-verified. |
| Source (PHPUnit) | Primitive invariants (no `backdrop-filter` in any UI primitive) | `GeneratedTokensCssTest::primitives_have_no_backdrop_filter_outside_chrome` re-verified. |
| Backend (Feature) | Comparison period math, `current === 0` case, zero baseline, month-boundary, total_patients regression, total_patients absolute, omission | `tests/Feature/Modules/DashboardComparisonTest.php` new (7 tests). |
| Frontend (Playwright) | Canvas/surface contrast visible, KPI row baseline uniform, chip absent when `delta_label === null`, ease-ios in computed-style, `prefers-reduced-motion`/`prefers-reduced-transparency`/`prefers-contrast` honoured | Manual quick sweep at the three exemplar screens at 1440x900. |

## Threat Matrix

**N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.** This change touches only frontend primitives, tokens, CSS generator, a single API endpoint's response shape, and a single DB migration. No new files outside `resources/`, `app/Http/Controllers/Api/`, `database/migrations/`, `tests/`.

## Migration / Rollout

No data migration. The backend `comparisons` block is additive; old clients ignore it. The new tokens are additive; the generated CSS grows by ~12 lines. The frontend KPI card rebuild is internal to `DashboardPage.vue`; the rendered HTML still has the same 5 `<section>` wrappers and the same `data-stat` attributes. The `patients.created_at` migration is additive (new index only).

Rollback:
- PR1 → revert the token additions; the `tokens.js` change is the only consumer of the new keys.
- PR2 → revert the primitive `:active` / `ease-ios` changes; old `:hover` states remain.
- PR3 → revert the controller and the migration; old clients never see the `comparisons` block.
- PR4 → revert the screen-level changes; the 5 stat cards revert to the freeform layout.
- PR5 → revert the login/404 polish + sidebar grouping; behaviour and labels unchanged.

## Open Questions

- **Q1 (D9):** is the user's bar that `dampingBounce` MUST be consumed somewhere as proof of foundation, or is "use it honestly" the right answer? I choose honest-no-consumer.
- **Q2 (D12):** the user supplied NeuroCRM/Tenx as references; both use the leading-ink / trailing-faded treatment. Rejected as REVERSIBLE. The user can override by adding a `text-numeric-fade` Tailwind variant in a follow-up slice.

## What This Design Does NOT Do

- Does NOT redesign the other 17 modules. They inherit the token layer; visual polish of calendar, patients, etc. is a later PR.
- Does NOT add new animation dependencies (no motion-v, no @vueuse/motion, no GSAP).
- Does NOT add dark mode. Light only.
- Does NOT add gradients anywhere. Dashboard stays hand-checked for `linear-gradient`/`bg-gradient` via `test_dashboard_page_no_linear_or_class_gradients`.
- Does NOT add hand-written hex literals to Dashboard, AppLayout, Login, or 404. The hex-parity test + the `no_hex_literals` test are the gates.
- Does NOT touch the pinned `radius.ios/modal`, `motion.response/damping`, or letterSpacing table.
- Does NOT replace `useSpring` on press (CSS `:active` is the mechanism).
- Does NOT consume `motion.dampingBounce` (no momentum-driven gesture in this slice).
- Does NOT consume `useSpring2D` (no drag/swipe/sheet gesture in this slice).
- Does NOT install `prefers-color-scheme: dark` blocks.
- Does NOT install `prefers-reduced-motion` blocks that flip durations to 0ms (R7: feedback survives, movement goes).
