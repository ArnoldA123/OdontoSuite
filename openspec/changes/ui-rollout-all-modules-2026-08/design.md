# Design: PR0 — Foundation Cross-Cutting Primitives (`ui-rollout-all-modules-2026-08`)

## Technical Approach

PR0 is the foundation layer for the entire rollout chain. It ships exactly three artefacts that the other 12 PRs (PR1..PR12) depend on: the new `<UiStatusBadge>` primitive at `resources/js/components/ui/StatusBadge.vue`, a one-line additive extension to `AppLayout.canvasRoutes` (currently `['/dashboard', '/login', '/404']`), and three PHPUnit tests — `AppLayoutCanvasRoutesTest`, `ModuleAppShellTestCase`, and `LegacyAliasForbiddenTest`. This document CONCRETIZES the proposal §7.2 PR0 row and the Domain 2 spec scenarios: the exact StatusBadge prop contract, the exact `canvasRoutes` final list, the exact test method signatures and rule-vs-literal style, and the precise scope rules that prevent category PRs from re-touching PR0's surface.

This revision applies the spec phase contract for Domain 2 verbatim. The PR0 contract is narrow, mechanical, and additive — there is no architectural creativity in PR0; the architecture was decided in `proposal.md` §6 OQ#5 (extract `<UiStatusBadge>`), §7.2 (PR0 scope), and the foundation primitives spec §2.1–§2.7.

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Date | 2026-08-11 |
| SDD phase | `design` (4 of 6) |
| Author | `sdd-design` sub-agent |
| Domain | 2 — Foundation Cross-Cutting Primitives (PR0 only) |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/design`) |
| Pace | `auto` |
| Delivery strategy | `auto-chain` (forward to `sdd-tasks` for chained PR realisation) |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` (forward to apply/verify) |
| PR scope | PR0 ONLY (`pr0-foundation-canvas-routes-and-status-badge`) |
| Line estimate | ~200 authored (StatusBadge.vue ~80, AppLayout.vue edit ~25, 3 tests ~95) |
| Companion design | `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` (the shape to follow) |
| Companion spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` (the 18 PR0 scenarios) |
| Sibling spec | `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` (Domain 1 — per-module rollout) |

### Preflight snapshot (verbatim from session preflight)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

### References

| Artifact | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` | The 18 PR0 scenarios (`APP-CORE-001..004`, `STATUS-PRIM-001..004`, `TEST-BASE-001..004`, `LEGACY-LIST-001..002`, `PAGIN-CONS-001..003`, `HOVER-LIFT-001`, `CANVAS-ROUTE-001`, `PER-MOD-001`) — the contract this design must satisfy. |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §7.2 | PR0 forecast (~200 LOC, single-line AppLayout edit + new primitive + 3 tests). |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` | Shape to follow (D1–D16 architecture decisions, build script emission plan, screen-level scope, slice boundaries). |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` §4 (DLR-R-* rules) | Cross-cutting rules that constrain PR0 (DLR-R-001..022); the rules are NOT modified by PR0 but the rule-asserting tests are introduced by PR0. |
| `resources/js/components/ui/Badge.vue` | Pre-existing primitive; StatusBadge is **lighter** (no border, ramp goes to *-50 not *-100); do NOT duplicate. |
| `resources/js/components/ui/StatusPill.vue` | Pre-existing appointment-status i18n mapping (`STATUS_MAP`); do NOT duplicate; StatusBadge is the generic primitive. |
| `resources/js/components/layout/AppLayout.vue` line 507 | The `canvasRoutes` literal (3 entries); PR0 extends to 21 entries with one-line additive change. |
| `tests/Unit/DesignSystem/PrimitivePressTest.php` lines 1–80 | Style for the rule-asserting PHPUnit tests (source-grep over `.vue` files). |
| `AGENTS.md` §5, §7 | 17-module inventory (§5) and `Ui*`-prefix convention for primitives (§7). |

---

## 1. Architectural intent

PR0 is the foundation layer that every other PR in the rollout chain depends on. It ships a new generic status-pill primitive (`<UiStatusBadge>`) that the 17 modules will consume to replace inline `bg-success-100 text-success-700` pills with tokenised ramps, extends `AppLayout.canvasRoutes` so the canvas surface (`bg-canvas` / `var(--color-canvas)`) renders on all 21 polished routes, and adds three PHPUnit tests that lock the contract: a route-list invariant (`AppLayoutCanvasRoutesTest`), a rule-asserting abstract base class (`ModuleAppShellTestCase`) that per-module tests will extend, and a forbidden-alias pin (`LegacyAliasForbiddenTest`) that catches every legacy class the rollout is replacing. PR0 enforces three zero-token-policy rules: no new tokens are added (`tokens.js` is frozen — `GeneratedTokensCssTest` is the standing witness), no pre-existing primitives are mutated (`Badge.vue` and `StatusPill.vue` are untouched), and no backend changes ship (the rollout is a frontend-only migration). Everything in PR0 is additive and independently revertible per `chained-pr` skill rules.

---

## 2. StatusBadge.vue — full API specification

### 2.1 File path and rationale

- **Path**: `resources/js/components/ui/StatusBadge.vue`
- **Naming**: `<UiStatusBadge>` per AGENTS.md §7 (`Ui*`-prefix convention for primitives). The file is `StatusBadge.vue` (no `Ui` prefix in filename — matches `Badge.vue`, `Card.vue`, etc.). The component name resolves to `UiStatusBadge` via the auto-import / explicit-import path.
- **Why a new primitive, not a wrapper around Badge**: `Badge.vue` is the iOS **filled-100** pattern (background `-100`, text `-700`, `border-system*-200`) with optional `dismissible` and `dot` shape (`Badge.vue` lines 69–98). StatusBadge is the iOS **filled-50** pattern (background `-50`, text `-700`, **no border**) with a fixed `rounded-full` pill shape and an optional status dot. They are visually different treatments for different surfaces: Badge for chips/cards, StatusBadge for inline status indicators (Quotation status, Patient active/inactive, ProcedureCatalog favorite).
- **Why not a wrapper around StatusPill**: `StatusPill.vue` is appointment-status-specific (the `STATUS_MAP` i18n mapping in lines 38–50: `scheduled`, `confirmed`, `in_consultation`, etc.). StatusBadge is generic — it accepts a `variant` prop and an arbitrary `label`. The first consumer (`QuotationStatusBadge` in PR2) is the test case for keeping StatusBadge generic.

### 2.2 Props

| Prop | Type | Default | Validator | Notes |
|---|---|---|---|---|
| `variant` | `String` | `'neutral'` | `value => ['success', 'warning', 'error', 'info', 'neutral'].includes(value)` | Maps to the five ramp colours below. |
| `label` | `String \| Number` | `null` | none | Display text. Overridden by the `default` slot if provided. |
| `size` | `String` | `'md'` | `value => ['sm', 'md'].includes(value)` | `sm` for table rows; `md` for inline badges. No `lg` (StatusBadge is a content pill, not a surface). |
| `showDot` | `Boolean` | `false` | none | When `true`, prepends a 6px coloured dot to the pill. |
| `as` | `String` | `'span'` | `value => ['span', 'div'].includes(value)` | Render-as escape hatch. `span` is the default (inline); `div` for block-level status pills in a card header. |

### 2.3 Slots

| Slot name | Required | Overrides | Notes |
|---|---|---|---|
| `default` | optional | `label` prop | When provided, the slot content is rendered instead of `label`. Use this when the label needs a `<i>` icon or a localised formatter. |
| `icon-left` | optional | none | Inserted between the dot and the label/slot. Use for the small Heroicons-mini (16px) that some modules attach (e.g. the `ExclamationCircleIcon` on error variants). |

### 2.4 Variant ramp table (per spec `STATUS-PRIM-002`)

The ramp values are the **lighter** iOS filled-50 pattern, NOT the Badge.vue filled-100 pattern. NO border.

| Variant | Background class | Text class | Dot class |
|---|---|---|---|
| `success` | `bg-systemGreen-50` | `text-systemGreen-700` | `bg-systemGreen-500` |
| `warning` | `bg-systemYellow-50` | `text-systemYellow-700` | `bg-systemYellow-500` |
| `error` | `bg-systemRed-50` | `text-systemRed-700` | `bg-systemRed-500` |
| `info` | `bg-systemBlue-50` | `text-systemBlue-700` | `bg-systemBlue-500` |
| `neutral` | `bg-systemGray-100` | `text-systemGray-700` | `bg-systemGray-500` |

These classes are derived from `resources/js/design-system/tokens.js` (the `systemGreen`, `systemYellow`, `systemRed`, `systemBlue`, `systemGray` ramps). They are NOT new tokens — they are existing `-50`, `-500`, `-700` steps from the existing colour ramps. The rollout does not introduce new colours.

### 2.5 Size table

| Size | Padding | Text size | Min height |
|---|---|---|---|
| `sm` | `px-2 py-0.5` | `text-xs` | `min-h-[20px]` |
| `md` | `px-2.5 py-1` | `text-xs` | `min-h-[24px]` |

No `lg`. StatusBadge is sized for content (table rows, inline indicators, modal headers). For larger status surfaces, use `<UiBadge size="lg">` (the existing primitive).

### 2.6 Shape

`rounded-full` always (the iOS pill treatment). StatusBadge is **not** generic-shape (Badge.vue has `shape` prop with `rounded | pill | square`). StatusBadge is pill-only because every status indicator in the codebase is a pill.

### 2.7 Interactions

- **Decorative transitions** (per spec `STATUS-PRIM-002` + the vertical-slice D10/D11 precedent in `archive/2026-08-11-ui-premium-microdetail-2026-08/design.md`):
  - `transition: background-color var(--motion-duration-normal) ease-out, color var(--motion-duration-normal) ease-out, box-shadow var(--motion-duration-normal) ease-out;` — colour washes only; no transform.
  - **No press transform** (`scale(0.98)` etc.) — StatusBadge is decorative, NOT a button. The use case is "show the user the current status of X", not "let the user click the status". For clickable status pills (e.g. filter chips), the consumer wraps `<UiStatusBadge>` in a `<button>` or uses `<UiBadge clickable>` (the proven primitive handles focus + hover + press).
- **Focus ring** (per spec `STATUS-PRIM-003`): `box-shadow: var(--focus-ring-default);` on `:focus-visible` inside `<style scoped>`. The focus ring is rendered only when the host element receives keyboard focus (the underlying `<component :is="as">` can be `tabindex="0"` if the consumer makes it interactive — the primitive itself does NOT add `tabindex`).
- **Reduced-motion fallback** (per spec `STATUS-PRIM-003` + vertical-slice D11): under `@media (prefers-reduced-motion: reduce)`, transition durations collapse to `200ms` max. Feedback survives; movement goes. Implemented as a scoped `<style>` block with the reduced-motion override.

### 2.8 Template shape (final)

```vue
<template>
  <component
    :is="as"
    :class="badgeClasses"
    role="status"
    :aria-label="ariaLabel"
    :data-variant="variant"
  >
    <span
      v-if="showDot"
      class="inline-block w-1.5 h-1.5 rounded-full"
      :class="dotClasses"
      aria-hidden="true"
    />
    <slot name="icon-left" />
    <slot>{{ label }}</slot>
  </component>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: value => ['success', 'warning', 'error', 'info', 'neutral'].includes(value)
  },
  label: { type: [String, Number], default: null },
  size: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md'].includes(value)
  },
  showDot: { type: Boolean, default: false },
  as: {
    type: String,
    default: 'span',
    validator: value => ['span', 'div'].includes(value)
  }
})

const SIZE_CLASSES = {
  sm: 'px-2 py-0.5 text-xs min-h-[20px]',
  md: 'px-2.5 py-1 text-xs min-h-[24px]'
}

const VARIANT_CLASSES = {
  success: 'bg-systemGreen-50 text-systemGreen-700',
  warning: 'bg-systemYellow-50 text-systemYellow-700',
  error: 'bg-systemRed-50 text-systemRed-700',
  info: 'bg-systemBlue-50 text-systemBlue-700',
  neutral: 'bg-systemGray-100 text-systemGray-700'
}

const DOT_CLASSES = {
  success: 'bg-systemGreen-500',
  warning: 'bg-systemYellow-500',
  error: 'bg-systemRed-500',
  info: 'bg-systemBlue-500',
  neutral: 'bg-systemGray-500'
}

const badgeClasses = computed(() => [
  'inline-flex items-center gap-1.5 rounded-full font-medium select-none',
  SIZE_CLASSES[props.size],
  VARIANT_CLASSES[props.variant]
].join(' '))

const dotClasses = computed(() => DOT_CLASSES[props.variant])

const ariaLabel = computed(() => props.label ? `Estado: ${props.label}` : undefined)
</script>

<style scoped>
/* Status badge — decorative transitions only. Per D10 of the vertical slice:
   no press transform (status badge is not a button). Per D11: reduced-motion
   caps transition durations to 200ms. Per STATUS-PRIM-003 of the spec:
   focus ring on :focus-visible via the composed token. */
.status-badge {
  transition:
    background-color var(--motion-duration-normal) ease-out,
    color var(--motion-duration-normal) ease-out,
    box-shadow var(--motion-duration-normal) ease-out;
}

.status-badge:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

@media (prefers-reduced-motion: reduce) {
  .status-badge {
    transition-duration: 200ms;
  }
}
</style>
```

> **Implementation note**: the template above is illustrative — the actual file applies `:class="badgeClasses"` to the root `<component>` (no explicit `.status-badge` class). The scoped styles are wired via `[data-variant="success"]:focus-visible` selectors (Vue scoped style applies the `data-attribute` attribute selector) OR a root-class hook. The final implementation chooses the cleanest path that the `PrimitivePressTest::test_focus_ring_uses_token` assertion can grep.

### 2.9 What this is NOT (negative space)

- NOT a wrapper around `Badge.vue`. Different ramp, no border, pill-only, no `dismissible`, no `shape` prop. Naming it `<UiStatusBadge>` makes the difference explicit.
- NOT a wrapper around `StatusPill.vue`. Different domain: StatusPill maps appointment statuses (`scheduled` → `Programado`); StatusBadge is generic (any label).
- NOT a button. No press transform. No `tabindex`. If the consumer needs a clickable filter chip, they wrap `<UiStatusBadge>` in a `<button>` or use `<UiBadge clickable>`.
- NOT a `<style scoped>`-heavy component. The scoped block is minimal (focus ring + reduced-motion override). The colour ramps are utility classes — no `@apply`, no hex literals, no `@keyframes`.

---

## 3. AppLayout.canvasRoutes extension

### 3.1 One-line additive change

The current literal at `resources/js/components/layout/AppLayout.vue` line 507:

```js
const canvasRoutes = ['/dashboard', '/login', '/404']
```

The PR0 final literal (21 routes total):

```js
// PR0 (ui-rollout-all-modules-2026-08) — canvas surface for every polished
// module route. Sources: foundation-primitives spec APP-CORE-001 + AGENTS.md
// §5 (17 modules). Order: already-polished first, then domain-grouped.
// This array is FROZEN at PR0 merge — category PRs MUST NOT extend or
// narrow it. The regression guard is tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php.
const canvasRoutes = [
  // Already polished (vertical slice)
  '/dashboard',
  '/login',
  '/404',
  // Pagos
  '/cash-register',
  '/cash-register/ready-to-bill',
  '/quotations',
  // Catálogo
  '/procedure-catalog',
  '/procedure-stats',
  // Admin
  '/professionals',
  '/environments',
  '/appointment-types',
  // Operación
  '/calendar',
  // Clínico
  '/patients',
  '/medical-records',
  '/specialty-records',
  '/treatment-plans',
  // Catálogo tail (PR3 cluster)
  '/my-procedures',
  '/reception-procedures',
  // Análisis
  '/ai-analysis',
  // BI
  '/business-intelligence',
  // Settings (canvas surface only — internals deferred per OQ#3)
  '/settings/branches',
  '/settings/payment-methods',
]
```

### 3.2 Inline comment (mandatory)

Immediately above the array literal, the comment documents:
- PR0 source
- Spec scenario ID (`APP-CORE-001`)
- The array is FROZEN at PR0 merge (the regression-guard test is the witness)
- AGENTS.md §5 reference (the 17-module inventory)

### 3.3 What is unchanged

- `isCanvasRoute` computed (line 508): `const isCanvasRoute = computed(() => canvasRoutes.includes(route.path))` — UNCHANGED. The gating mechanism is the only mechanism (per spec `APP-CORE-002`).
- The `<element :class="isCanvasRoute ? 'bg-canvas' : ''">` binding — UNCHANGED.
- No other AppLayout refactor (no sidebar re-design, no topbar changes, no new event listeners — per spec `APP-CORE-004`).
- No AppLayout `<script>` block change. The PR0 edit is template-of-the-`canvasRoutes`-constant only.

### 3.4 Scope rule (forwarded to category PRs)

Category PRs (PR1..PR12) **MUST NOT** touch this array literal. The reasoning is mechanical: the canvas surface is the foundation; if any category PR added a route, the route would render on canvas WITHOUT having been tokenised internally (canvas surface is just background colour, not the full module polish). The intent is that canvas extension is a one-shot operation in PR0, and the remaining work per module is the INTERNAL tokenisation (per spec `DLR-CORE-001..015`). Any category PR that needs a new route in `canvasRoutes` is signalling an out-of-scope module — escalate to spec review, not a silent edit.

The regression guard is `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` (see §4.1). The test fails if any expected route is missing from the array literal.

---

## 4. Three new PHPUnit tests

All three tests live in `tests/Unit/DesignSystem/`. They are source-grep PHPUnit tests (the same style as `PrimitivePressTest.php` and `DashboardAppShellTest.php` from the vertical slice): they read `.vue` files and assert on string patterns. There is no JS test runner here; the durable surface is the source code and the scoped `<style>` blocks.

### 4.1 `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`

**Purpose**: Pin the `canvasRoutes` array literal at `resources/js/components/layout/AppLayout.vue` line 507. The test is the standing regression guard for the entire rollout: removing a route surfaces as a test failure (per spec `CANVAS-ROUTE-001`).

**Path**: `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`

**Style**: rule-vs-literal hybrid. The test reads the literal array contents (a rule: the array contains every expected route) and does NOT pin a literal-class string or a literal-comment string (the comment above the array is free-form).

**What it asserts**:

| Test method | Rule | Pattern |
|---|---|---|
| `test_canvas_routes_file_exists` | `resources/js/components/layout/AppLayout.vue` exists | `assertFileExists` |
| `test_canvas_routes_array_present` | A `const canvasRoutes = [...]` literal exists in the file | regex `const\s+canvasRoutes\s*=\s*\[` |
| `test_each_expected_route_is_in_canvas_routes` (data-provider) | Every route in `EXPECTED_ROUTES` (21 entries from §3.1) appears as a quoted string in the array literal | regex `'/dashboard'`, `'/login'`, ... |
| `test_no_legacy_narrowing_to_vertical_slice_routes` (SENTINEL) | The array is NOT narrowed back to the vertical-slice set of 3 routes (`['/dashboard', '/login', '/404']` only) | counts the routes in the literal; fails if exactly 3 are present without the full 21 |

**Data provider**: `@dataProvider expectedRouteProvider()` exposes each expected route as a separate test case (`test_each_expected_route_is_in_canvas_routes` with `'/dashboard'`, `'/login'`, ..., `'/settings/payment-methods'`).

**Regex tolerance**: the array literal tolerates comments (single-line `//` and block `/* ... */`), whitespace, line-breaks, and trailing commas. The test uses a tolerant extractor: capture the array body (between `[` and `]`), then `preg_match_all("/'([^']+)'/", $body, $matches)` to enumerate the quoted strings.

**Sentinel test design**: the sentinel catches a sneaky regression where someone "simplifies" the array back to the 3 vertical-slice routes. It counts routes; if the count is exactly 3 AND none of the module routes beyond `/dashboard | /login | /404` are present, the sentinel fails with: "canvasRoutes was narrowed back to the vertical-slice set; PR0 extension is required."

### 4.2 `tests/Unit/DesignSystem/ModuleAppShellTestCase.php`

**Purpose**: Abstract base class for per-module structure tests. Every per-module `<Module>AppShellTest` extends this class and provides the module's `.vue` files (via `polishedFiles(): array`). The base class enforces the **rule** (NOT the literal string) per the vertical-slice archive-report lesson at lines 47–57: "a test that pins an example instead of the rule" caused 3 defects.

**Path**: `tests/Unit/DesignSystem/ModuleAppShellTestCase.php`

**Style**: rule-asserting. Every assertion checks that the file references a TOKEN, not that it contains a literal-class string. Example: the canvas surface rule asserts `bg-canvas` OR `var(--color-canvas)` is referenced, not that the file contains a specific Tailwind class.

**Abstract method**:

```php
abstract protected function polishedFiles(): array;
// Returns array of absolute paths to the module's polished .vue files.
```

**Rule assertions** (every rule fires against every file in `polishedFiles()`):

| Test method | Rule | Pattern |
|---|---|---|
| `test_page_references_canvas_token` | The file references the canvas token (`bg-canvas` OR `var(--color-canvas)` OR `rgb(242, 242, 247)`) | regex: `/bg-canvas\|var\(--color-canvas\)\|rgb\(242,\s*242,\s*247\)/` |
| `test_no_legacy_border_theme_literal` | The file does NOT contain the legacy `border-theme` literal (modifier variants like `border-theme-light` ARE forbidden in the PR0 list — see §4.3 for the PR0 exclusion) | regex: `/(?<![\w-])border-theme(?![\w-])/` (negative lookbehind + lookahead; the modifier variants contain `-light` etc., which match `[\w-]` after `border-theme`, so this regex excludes them) |
| `test_focus_ring_consumes_token` | If the file contains `:focus` or `:focus-visible`, it must consume `var(--focus-ring-default)` | conditional assertion: if `preg_match('/:focus(-visible)?/', $src)` is truthy, then `preg_match('/var\(--focus-ring-default\)/', $src)` must also be truthy |
| `test_no_legacy_focus_ring_alias` | The file does NOT contain `focus:ring-primary-500` or `focus:border-accent` | regex: `/(?<![\w-])focus:ring-primary-500(?![\w-])\|(?<![\w-])focus:border-accent(?![\w-])/` |
| `test_no_style_scoped` | The file does NOT contain a `<style scoped>` block | regex: `/\<style\s+scoped\>/` must NOT match |

**Why these five rules**:

- `test_page_references_canvas_token` — Rule `DLR-CORE-001` (canvas surface).
- `test_no_legacy_border_theme_literal` — Rule `DLR-R-002` (hairline, not border-theme). The PR0 list excludes `border-theme-light` from the immediate pin because AppLayout/Card/Sidebar/Topbar still use it heavily; the rule is added per-category as those files migrate.
- `test_focus_ring_consumes_token` — Rule `DLR-R-004` (composed focus ring).
- `test_no_legacy_focus_ring_alias` — Rule `DLR-R-004` (negative form: ban the legacy alias).
- `test_no_style_scoped` — Rule `DLR-CORE-008` / `DLR-R-021` (no new `<style scoped>` blocks). This is the standing guard for the 6 files flagged in spec `TEST-BASE-003` (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal, CreatePatientInline, TreatmentPlanModal).

**Subclass extension pattern**:

```php
class ProcedureStatsAppShellTest extends ModuleAppShellTestCase
{
    protected function polishedFiles(): array
    {
        return [
            // __DIR__ . '/../../../resources/js/modules/procedure-catalog/ProcedureStatsPage.vue'
            $this->projectRoot() . '/resources/js/modules/procedure-catalog/ProcedureStatsPage.vue',
        ];
    }
}
```

Per spec `PER-MOD-001`, per-module subclasses ship WITH their respective module PRs (not prophylactically in PR0 — per the proposal §4.4 lesson). The first subclass arrives with PR1 (ProcedureStats).

### 4.3 `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`

**Purpose**: Pin the forbidden legacy alias list. Every polished module file MUST contain zero matches against the `LEGACY_ALIASES` constant (per spec `LEGACY-LIST-001` + design-language-rollout rule `DLR-R-009`).

**Path**: `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php`

**Style**: rule-asserting. The constant is a list of canonical forbidden class strings; the test greps each polished file for whole-token matches. The whole-token match is critical: `bg-success-1000` must NOT match `bg-success-100`.

**What it asserts**:

| Test method | Rule | Pattern |
|---|---|---|
| `test_legacy_aliases_constant_is_non_empty` | `LEGACY_ALIASES` is non-empty | `assertNotEmpty` |
| `test_no_legacy_alias_in_polished_file` (data-provider) | Each file in `polishedFiles()` contains zero whole-token matches against `LEGACY_ALIASES` | whole-token regex per alias |
| `test_alias_patterns_are_whole_token` (sanity) | The whole-token regex (with negative lookbehind + lookahead) does NOT falsely match `bg-success-1000` against `bg-success-100` | unit test the regex against synthetic inputs |

**`LEGACY_ALIASES` constant** (per spec `LEGACY-LIST-001` + `DLR-R-009`):

```php
private const LEGACY_ALIASES = [
    // Success ramp legacy (replaced by <UiStatusBadge variant="success">)
    'bg-success-100',
    'bg-success-500',
    'bg-success-600',
    'bg-success-700',
    'text-success-700',
    // Warning ramp legacy (replaced by <UiStatusBadge variant="warning">)
    'bg-warning-100',
    'text-warning-700',
    // Error ramp legacy (replaced by <UiStatusBadge variant="error">)
    'bg-error-100',
    'text-error-700',
    'bg-error-600',
    // Accent / primary legacy (replaced by tokenised systemBlue-* ramps)
    'text-accent',
    'bg-accent',
    'hover:text-primary-700',
    'bg-primary-50',
    'bg-primary-100',
    'bg-primary-200',
    // Focus ring legacy (replaced by var(--focus-ring-default))
    'focus:ring-primary-500',
    'focus:border-accent',
];
```

**`border-theme` is EXCLUDED from the PR0 list**. The spec `LEGACY-LIST-001` includes `border-theme` and `border-theme-light`, but the rollout audit (per `proposal.md` §4.5) shows AppLayout + Card + Sidebar + Topbar still use `border-theme` heavily as of PR0 merge. Forcing `border-theme` into the PR0 list would fail the test immediately — a chicken-and-egg dead-end. The `ModuleAppShellTestCase::test_no_legacy_border_theme_literal` rule (§4.2) is the immediate pin for new module files; the `LegacyAliasForbiddenTest::LEGACY_ALIASES` array grows opportunistically per-category as AppLayout/Card/Sidebar/Topbar migrate. This is the mitigation recorded in spec `LEGACY-LIST-002`.

**Default `polishedFiles()`**:

```php
protected function polishedFiles(): array
{
    return [
        $this->projectRoot() . '/resources/js/components/ui/StatusBadge.vue',
        $this->projectRoot() . '/resources/js/components/layout/AppLayout.vue',
    ];
}
```

Categories override `polishedFiles()` and append their module files. The default returns only the 2 PR0-touched files; this validates that the new primitive and the AppLayout edit are clean against the forbidden list (the test fails if StatusBadge.vue accidentally introduces a `bg-success-100` class or AppLayout.vue reintroduces `focus:ring-primary-500`).

**Data provider**: `@dataProvider polishedFileProvider()` exposes each file as a separate test case.

**Whole-token regex pattern**: `/(?<![\\w-])ALIAS(?![\w-])/`. The negative lookbehind `(?<![\w-])` excludes matches preceded by a word character or hyphen (so `text-bg-success-100-foo` does NOT trigger — the leading `text-` is fine but the `bg-success-100` is preceded by `-`, which is in `[\w-]`); wait — actually `-` is in `[\w-]`, so `bg-success-100` preceded by `-` would NOT match. That's a bug. Re-think.

**Corrected whole-token regex** (handles the modifier variants correctly):

```php
$aliasEscaped = preg_quote($alias, '/');
$pattern = '/(?<![\w-])' . $aliasEscaped . '(?![\w-])/';
```

For `bg-success-100`:
- `text-bg-success-100` → `bg-success-100` is preceded by `-` (in `[\w-]`) → NO match. Correct (this is `text-` + `bg-success-100` joined with `-`, but it's a hypothetical — actual Tailwind classes don't concatenate like this).
- `bg-success-1000` → `bg-success-100` is followed by `0` (in `[\w-]`) → NO match. Correct (this is exactly the bug case from the spec).
- `bg-success-100 text-success-700` → `bg-success-100` is followed by ` ` (NOT in `[\w-]`) → MATCH. Correct.
- `border-bg-success-100` → `bg-success-100` is preceded by `-` (in `[\w-]`) → NO match. Correct (this is a hypothetical nested class).

The regex is correct. The whole-token match is enforced.

---

## 5. Cross-cutting rules applied to PR0

The cross-cutting rules are documented in full in the sibling spec `design-language-rollout/spec.md` §4 (`DLR-R-001..022`). The rules relevant to PR0 are:

| Rule ID | Applies to PR0 how? |
|---|---|
| `DLR-R-001` (canvas surface) | PR0 extends `canvasRoutes` so every polished module route renders on canvas. The regression guard is `AppLayoutCanvasRoutesTest` (per spec `CANVAS-ROUTE-001`). |
| `DLR-R-009` (legacy alias ban) | PR0 ships `LegacyAliasForbiddenTest` with the initial forbidden set (per spec `LEGACY-LIST-001`). Categories extend opportunistically. |
| `DLR-R-013` (no new dependencies) | PR0 ships zero new npm or composer dependencies. Vue 3 + Tailwind 3.3 + Vue Router only. |
| `DLR-R-017` (strict TDD) | The 3 tests ship with the implementation; tests fail before code lands (RED-GREEN). |
| `DLR-R-018` (PHPUnit invariants green) | All existing 6 `tests/Unit/DesignSystem/*` invariants remain green at PR0 merge (PR0 is additive — no existing test is touched). |
| `DLR-R-019` (CI gates) | `quality`, `backend-tests` (MySQL service), `frontend-build` (pnpm build) green at PR0 merge. |
| `DLR-R-020` (no dangling `var(--color-*)`) | PR0 StatusBadge.vue references ONLY `var(--focus-ring-default)`, `var(--motion-duration-normal)`, `var(--motion-easing-ios)` — all emitted by the existing `scripts/build-tokens-css.mjs` (per `GeneratedTokensCssTest`). No dangling references. |
| `DLR-R-021` (no `<style scoped>`) | StatusBadge.vue's `<style scoped>` block is minimal (focus ring + reduced-motion override); `ModuleAppShellTestCase::test_no_style_scoped` will catch any PR0 regression (StatusBadge is NOT in `polishedFiles()` for PR0 — the rule fires on per-module subclasses added with PR1+). |

### 5.1 `<script>` discipline

- **StatusBadge.vue**: gets a `<script setup>` with props only — no business logic, no composables, no event emissions, no watchers. The primitive is purely presentational.
- **AppLayout.vue**: UNCHANGED except the `canvasRoutes` array literal (one-line additive change). The `<script setup>` block is NOT touched. The `isCanvasRoute` computed is NOT touched.

### 5.2 Reduced-motion

StatusBadge.vue's scoped `<style>` block includes a `@media (prefers-reduced-motion: reduce)` rule that caps transition-duration to 200ms. No transform is introduced (D10 precedent: StatusBadge is decorative, NOT a button). The fallback is the rule's standard 200ms cap on colour wash transitions.

### 5.3 Strict TDD

The 3 tests ship with the implementation. Sequence:
1. Write `AppLayoutCanvasRoutesTest` (RED) — the test reads the current 3-route literal and asserts 21 routes → test fails because only 3 are present.
2. Edit `canvasRoutes` to 21 routes (GREEN).
3. Write `ModuleAppShellTestCase` (abstract) + `LegacyAliasForbiddenTest` (RED on PR0-touched files).
4. Ship `StatusBadge.vue` (the primitive is new, so no test fails-before; the `LegacyAliasForbiddenTest` against the default `polishedFiles()` validates the new file is clean against the forbidden list).
5. Run full PHPUnit suite — all 6 existing + 3 new invariants must be green.

### 5.4 What PR0 does NOT do

- **No new tokens** — `tokens.js` is frozen. `GeneratedTokensCssTest` is the standing witness.
- **No primitive modifications** — `Badge.vue`, `Card.vue`, `Button.vue`, `Input.vue`, `Avatar.vue`, `StatusPill.vue` etc. are all UNCHANGED.
- **No backend changes** — no migration, no controller, no event listener.
- **No FullCalendar / Chart.js scope** — those are PR7 / PR10 respectively.
- **No inline `<style scoped>` blocks in new code beyond the focus-ring + reduced-motion override** — StatusBadge's `<style scoped>` is 8 lines.
- **No AppLayout refactor** — only the `canvasRoutes` array literal is touched.
- **No per-module tests** — per-module `<Module>AppShellTest` subclasses ship WITH their respective PRs (PR1..PR12), NOT in PR0. Per the proposal §4.4 lesson: per-module tests are added only when a NEW primitive or pattern is introduced; otherwise the standing `ModuleAppShellTestCase` is enough.

---

## 6. Categories' dependency on PR0

Every category PR (PR1..PR12) depends on PR0 having landed. The dependency is mechanical:

### 6.1 Categories MUST NOT touch

| Surface | Why frozen | Witness |
|---|---|---|
| `AppLayout.canvasRoutes` array literal | PR0 extension is the one-shot operation; category PRs migrate internal pages, not the canvas surface | `AppLayoutCanvasRoutesTest::test_no_legacy_narrowing_to_vertical_slice_routes` |
| `StatusBadge.vue` source | The primitive is shipped in PR0 and immutable thereafter. Categories consume it; they don't extend it | `LegacyAliasForbiddenTest::test_no_legacy_alias_in_polished_file` (default `polishedFiles()` includes `StatusBadge.vue` so any forbidden-class edit fails) |
| The 3 new test files | The test infrastructure is shipped in PR0; categories append to `polishedFiles()` via subclass overrides, they don't edit the base classes | `LegacyAliasForbiddenTest` + `ModuleAppShellTestCase` are versioned in PR0 |
| `tokens.js` | Frozen for the entire rollout (`DLR-R-013` + proposal §3.6) | `TokensModuleTest` + `GeneratedTokensCssTest` |
| Existing primitives (`Badge.vue`, `Card.vue`, `Button.vue`, `Input.vue`, `Avatar.vue`, `StatusPill.vue`, `Modal.vue`, `Sheet.vue`, `ConfirmDialog.vue`, `Toast.vue`, `Select.vue`) | Inherited as-is per proposal §3.6 | `PrimitivePressTest` |

### 6.2 Categories MUST consume

| Surface | How | Where |
|---|---|---|
| `<UiStatusBadge variant="..." label="..." />` | Replace inline `bg-success-100 text-success-700` pills and the legacy `QuotationStatusBadge.vue` local classes | PR2 (Quotations) is the first consumer; PR3..PR12 follow |
| `ModuleAppShellTestCase` base class | Extend + provide `polishedFiles()` returning the module's polished `.vue` files | Each category PR ships a `<Module>AppShellTest.php` subclass |
| `LegacyAliasForbiddenTest::polishedFiles()` | Override and append the module's polished files | Each category PR appends to the default list |
| `<UiPagination>` | Replace `<PaginationComponent>` imports — consolidation rides PR3 | PR3 (Recepción procedimientos has the list-with-pagination pattern) |
| `AppLayout.canvasRoutes` consumer | The category PR's module page renders on canvas automatically (the array extension is done in PR0). No per-category work needed | N/A |

### 6.3 Sequencing summary

| Phase | Artefact produced | Consumer |
|---|---|---|
| PR0 (this design) | `<UiStatusBadge>`, `canvasRoutes` (21 entries), `AppLayoutCanvasRoutesTest`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest` | PR1..PR12 |
| PR1 | First per-module test (`ProcedureStatsAppShellTest` extending `ModuleAppShellTestCase`) | validates the base class works on a real module |
| PR2 | First `<UiStatusBadge>` consumer (`QuotationStatusBadge` → thin wrapper or removal) | validates the primitive API |
| PR3 | First `<UiPagination>` consumer + `PaginationComponent` removal | validates the consolidation pattern |
| PR4..PR12 | Per-category migration of CRUD / clinical / financial surfaces | composes the PR0 foundation into the full app |

---

## 7. PR0 estimation (mirrors proposal §7.15 row "PR0")

| File | Action | Estimated lines | Notes |
|---|---|---|---|
| `resources/js/components/ui/StatusBadge.vue` | Create | ~80 | `<template>` ~20, `<script setup>` ~40, `<style scoped>` ~8, blank lines / comments ~12 |
| `resources/js/components/layout/AppLayout.vue` | Modify | ~25 | Array literal expansion (~22 lines for 21 routes + inline comment) + a blank line above |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Create | ~45 | Test class boilerplate ~10, `expectedRouteProvider` ~5, sentinel test ~10, regex-tolerant extractor ~10, docblock ~10 |
| `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` | Create | ~30 | Abstract class boilerplate ~8, 5 rule assertions ~15, docblock ~7 |
| `tests/Unit/DesignSystem/LegacyAliasForbiddenTest.php` | Create | ~20 | `LEGACY_ALIASES` constant ~18, `polishedFileProvider` ~5, default `polishedFiles()` ~5, docblock ~5 (overlap reduces to ~20 total) |
| **Total** | | **~200** | All under the 400-line review budget per file |

The `tests/Unit/DesignSystem/PrimitivePressTest.php` (existing) is also extended to add `<UiStatusBadge>` to the primitive set (per spec `STATUS-PRIM-003`). This is an additive change to an existing test file: the existing test loops over `HEADLINE = ['Card.vue', 'Button.vue', 'Input.vue', 'Badge.vue', 'Avatar.vue']`; PR0 appends `'StatusBadge.vue'` to the array. Estimated +5 lines. Total PR0 estimated: **~205 lines**, still under the budget.

---

## 8. Risk register for PR0

| # | Risk | Blast radius | Mitigation |
|---|---|---|---|
| 1 | StatusBadge.vue ballooning past 80 lines (e.g. someone adds a complex `STATUS_MAP`, a `dismissible` mode, or a `shape` prop) | The primitive becomes a kitchen-sink and duplicates Badge.vue or StatusPill.vue; the "primitive immutability" guarantee weakens | Keep the prop API minimal (5 props + 2 slots); no `STATUS_MAP` (that's StatusPill's domain); no `dismissible` (Badge has it); no `shape` (pill-only). The apply phase enforces line budget via PR review. |
| 2 | AppLayoutCanvasRoutesTest regex parser breaks if AppLayout is refactored (e.g. someone refactors the canvasRoutes literal into a computed, an external constant, or a getter) | The test fails for the wrong reason; category PRs ship with broken tests | Tolerant regex tolerates comments + whitespace + trailing commas; sentinel test catches narrow regressions. If the refactor is unavoidable, the test is updated to read the array contents from the new shape — the test must remain a "the array contains every expected route" assertion, not a literal-string pin. |
| 3 | LegacyAliasForbiddenTest misses the user's actual legacy aliases (alias list incomplete) | A category PR introduces a forgotten alias and the test passes; the rollout misses a defect | The `LEGACY_ALIASES` array is **extended opportunistically** per spec `LEGACY-LIST-002`: when a defect is observed during a module PR, the pattern is added in the same PR. `border-theme` is the explicit exclusion in PR0 (re-included as AppLayout/Card/Sidebar/Topbar migrate). |
| 4 | AppLayout edit accidentally touches other AppLayout code | Per spec `APP-CORE-004`, the PR0 edit is the `canvasRoutes` array literal ONLY. Any other AppLayout change is a scope violation | Apply phase uses a tight PR diff: `git diff -- resources/js/components/layout/AppLayout.vue` must show ONLY the array literal + the inline comment. Any other change fails review. |
| 5 | The 21-route list misses a route that PR1+ needs | A category PR's module page does NOT render on canvas (no `bg-canvas`) → the module's canvas/surface contrast breaks | The list is exhaustive per spec `APP-CORE-001` + AGENTS.md §5. If a missing route is discovered during a category PR, ESCALATE to spec review (do NOT silently add to the array). The `AppLayoutCanvasRoutesTest` will fail and surface the gap. |
| 6 | StatusBadge ramp classes (`bg-systemGreen-50`, etc.) conflict with Tailwind purge config | The classes are stripped from production CSS and the pill renders as a transparent span | The Tailwind config (`tailwind.config.js`) already includes `resources/js/components/ui/*.vue` in its content paths (verified per the project AGENTS.md §3 conventions); the new file is automatically picked up. The `frontend-build` CI job is the standing witness. |
| 7 | ModuleAppShellTestCase rule assertions miss real defects (e.g. someone writes `bg-canvas` in a comment, satisfying the rule without actually using the token) | A module renders the wrong canvas/surface contrast | The rule is a regression guard for the COMMITTED file shape, not a behavioural test. The standing `DashboardAppShellTest` precedent (per spec `DLR-CORE-008`) uses the same source-grep style; the rule-vs-literal distinction is what `archive-report.md` lines 47–57 explicitly endorsed. The visual sweep (per `proposal.md` §4.3) is the behavioural witness. |
| 8 | `MVD-FALLBACK-USED` — design.md written under MVD fallback (sections 5–9 may be skipped if needed) | The design covers §-0 §-1 §-2 §-3 §-4 in full detail; §-5 §-6 §-7 §-8 §-9 are present but more compact | No MVD fallback was used in this revision; all sections are present. Flagged here only if apply/verify observes a missing section. |

---

## 9. References

### 9.1 Spec files (PR0 contract)

| File | Why read |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/foundation-primitives/spec.md` | The 18 PR0 scenarios (`APP-CORE-001..004`, `STATUS-PRIM-001..004`, `TEST-BASE-001..004`, `LEGACY-LIST-001..002`, `PAGIN-CONS-001..003`, `HOVER-LIFT-001`, `CANVAS-ROUTE-001`, `PER-MOD-001`) — the contract this design satisfies. |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Cross-cutting rules `DLR-R-001..022` + per-module scenarios `DLR-MOD-001..018` — the rules PR0 enforces via the rule-asserting PHPUnit tests. |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §7.2 | PR0 forecast: ~200 LOC, single-line AppLayout edit + new StatusBadge + 3 tests. |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §4.5 | Strict TDD contract — every UI replacement MUST come with a test. |
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` §6 (OQ#5, OQ#6, OQ#7) | StatusBadge extraction decision (OQ#5 — YES), `hover-lift` audit (OQ#6 — audit only in PR0), pagination consolidation (OQ#7 — rides PR3). |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/design.md` | Shape to follow: D1–D16 architecture decisions, D10 press mechanism, D11 reduced-motion fallback, D6 focus-ring composition. The StatusBadge scoped style block uses the same D11 + D6 patterns. |
| `openspec/changes/archive/2026-08-11-ui-premium-microdetail-2026-08/archive-report.md` lines 47–57 | The "test pins example vs rule" lesson — applied to `ModuleAppShellTestCase` and `LegacyAliasForbiddenTest`. |
| `openspec/specs/premium-design-foundation/spec.md` | The foundation this rollout CONSUMES. PR0 ships no new tokens; `tokens.js` is frozen. |
| `openspec/changes/ui-redesign-apple-claude-2026-08/exploration.md` | The known-bad alternative (cream + terracotta + Newsreader). DO NOT extend. |
| `AGENTS.md` §5 | 17-module inventory — the source of the 21 routes in `canvasRoutes` (17 modules + 2 settings + 2 vertical-slice polished). |
| `AGENTS.md` §7 | Conventions: `Ui*`-prefix for primitives (StatusBadge follows this), pnpm only (PR0 doesn't add deps), strict TDD. |

### 9.2 Source code (referenced for shape and patterns)

| File | Why read |
|---|---|
| `resources/js/components/ui/Badge.vue` | The pre-existing badge primitive. StatusBadge is the iOS **filled-50** pattern (no border); Badge is the iOS **filled-100** pattern (with border). Different treatments for different surfaces. |
| `resources/js/components/ui/StatusPill.vue` | The pre-existing appointment-status i18n mapping (`STATUS_MAP` lines 38–50). StatusBadge is generic (variant + label); StatusPill is appointment-specific. |
| `resources/js/components/layout/AppLayout.vue` line 507 | The `canvasRoutes` literal (3 entries) — PR0 extends to 21 entries with a one-line additive change. |
| `tests/Unit/DesignSystem/PrimitivePressTest.php` lines 1–80 | The PHPUnit style for source-grep rule-asserting tests. PR0's 3 new tests follow the same style. |
| `tests/Unit/DesignSystem/DashboardAppShellTest.php` | The proven PHPUnit pattern for `<Module>AppShellTest` (per spec `TEST-BASE-001` reference). |
| `resources/css/utilities.css` | `hover-lift` utility source (per spec `HOVER-LIFT-001` — PR0 audits reachability only). |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth. PR0 ships NO new tokens. |
| `resources/css/tokens.generated.css` | The generated CSS the rollout consumes. StatusBadge references `--focus-ring-default`, `--motion-duration-normal`, `--motion-easing-ios` — all emitted by the existing `scripts/build-tokens-css.mjs`. |
| `openspec/config.yaml` | Preflight cache + strict TDD + hybrid artifact store + per-phase rules. |

### 9.3 Process invariants (forwarded from the vertical slice)

1. **Test pins rule, not example** (archive-report lines 47–57): `ModuleAppShellTestCase` asserts `bg-canvas` OR `var(--color-canvas)` OR `rgb(242, 242, 247)` is referenced — NOT a specific literal string. The rule is the contract; the literal is the implementation.
2. **Run spec first, then design against the finished spec** (archive-report line 56): PR0's design operates against the finished `foundation-primitives/spec.md` (Domain 2) — the spec phase ran before the design phase.
3. **No grandfather clause for `<style scoped>`** (proposal OQ#9): StatusBadge's `<style scoped>` is minimal (focus ring + reduced-motion override, 8 lines). The standing `DashboardAppShellTest` precedent forbids `<style scoped>` for module pages; StatusBadge is a primitive, not a module page, so the rule does not apply to it.
4. **Strict TDD forward** (proposal §4.5): the 3 tests ship with the implementation; tests fail before code lands.

### 9.4 What this design does NOT do

- Does NOT add new tokens. `tokens.js` is frozen.
- Does NOT mutate `Badge.vue`, `Card.vue`, `Button.vue`, `Input.vue`, `Avatar.vue`, `StatusPill.vue`, `Modal.vue`, `Sheet.vue`, `ConfirmDialog.vue`, `Toast.vue`, `Select.vue`.
- Does NOT touch any backend code (controller, migration, event listener, model, service).
- Does NOT touch `tokens.js`, `tailwind.config.js`, `scripts/build-tokens-css.mjs`, or `tokens.generated.css`.
- Does NOT redesign the sidebar / topbar / PageHeader (vertical-slice PR5 ships those).
- Does NOT introduce dark mode (the proven language is light-only).
- Does NOT introduce a `<UiDataTable>` adoption rule (the rollout touches each module's existing table, not a wholesale table rewrite).
- Does NOT bundle the BI Chart.js JS-side mapping (that ships in PR10).
- Does NOT touch any module's `<script>` blocks (UI changes are template-level only).
- Does NOT ship per-module structure tests (those ship with PR1..PR12, not prophylactically in PR0 — per proposal §4.4 lesson).

---

*End of PR0 design.*
