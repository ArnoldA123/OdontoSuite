# Explore: tipos-cita (ui-rollout-all-modules-2026-08)

> SDD phase: `sdd-explore`. Tipos de cita (appointment types) sub-category of the rollout.
> Read-only; no proposal, no design, no tasks. English (artifact convention).

## Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | `tipos-cita` (admin CRUD: list + detail) |
| Date | 2026-08-21 |
| Phase | explore (1 of 6) |
| Author | `sdd-explore` sub-agent |
| Artifact store | hybrid (Engram `sdd/ui-rollout-all-modules-2026-08/categories/tipos-cita/explore` saved; this filesystem write completed from orchestrator handoff) |
| Delivery strategy (cached) | `auto-chain` |
| Review budget | 400 authored lines / PR |
| Strict TDD | `true` |
| Risk tier | **Tier-1** (admin CRUD, 2 pages, small) — already substantially polished by prior `PR-citas-04` |
| Closest precedent | `archive/2026-08-12-ui-pacientes` (2-page admin CRUD pattern) |

### Preflight (inherited from global — do not re-ask)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain
review_budget_lines: 400
chain_strategy: not_cached
strict_tdd: true
```

---

## 1. File Inventory

| File | Lines | Size | `<style scoped>` | `@apply` | `@keyframes` | `<input>` | `<textarea>` | `<select>` | `<form>` |
|---|---|---|---|---|---|---|---|---|---|
| `AppointmentTypesPage.vue` | 641 | ~21.5 KB | **0** | 0 | 0 | **9** | **2** | **0** (uses `<UiSelect>`) | **2** |
| `AppointmentTypeDetailPage.vue` | 407 | ~14 KB | **0** | 0 | 0 | **0** | **0** | **0** | **0** |
| **Total** | **1048** | ~35.5 KB | **0** | **0** | **0** | **9** | **2** | **0** | **2** |

Module dir contains exactly 2 `.vue` files. No `components/` subdir; no module-local composables.

---

## 2. Current Visual State

### 2.1 List page — `AppointmentTypesPage.vue` (641 lines)

**Already polished (PR-citas-04)**: `<PageHeader class="bg-canvas mb-6">`; `<UiCard variant="glass">` filters + list table; `<UiInput>` search; `<UiSelect>` status filter; `<UiStatusBadge>` row pills; `<UiButton variant="ghost">` actions; `<UiModal>` chrome for New + Edit + View modals; `tabular-nums` on price; `divide-hairline`.

**Residual legacy patterns**:

| Pattern | Lines | Issue | Replacement |
|---|---|---|---|
| Raw `<input>` with hand-coded chrome | 230, 247, 259, 270, 275, 297, 314, 326, 337 (9 total) | No focus-ring token; no `<UiInput>` press mechanism | `<UiInput v-model="..." />` |
| Raw `<textarea>` | 239, 306 (2 total) | Same | `<UiTextarea v-model="..." />` |
| `border-b-2 border-accent` spinner | 85 | `border-accent` legacy alias | `<LoadingSpinner />` |
| Hand-rolled empty state with custom SVG | 89-104 | `<UiEmptyState>` already imported but unused | `<UiEmptyState title="..." description="..." />` |

### 2.2 Detail page — `AppointmentTypeDetailPage.vue` (407 lines)

**Already polished**: `<PageHeader class="bg-canvas mb-6">`; `<UiCard variant="glass">` info card + audit panel; `<UiStatusBadge>` for active/inactive + audit action variants; `tabular-nums` on price.

**Residual legacy patterns**:

| Pattern | Lines | Issue | Replacement |
|---|---|---|---|
| Raw `<button>` tab navigation | 84-100 | Custom `border-systemBlue-500 text-systemBlue-600` active indicator | `<UiTabs v-model="activeTab" :tabs="tabs" />` |
| Hand-rolled audit empty state with `bg-gradient-to-br` | **167** | **FORBIDDEN per global guard rail #10** (no gradients) | `<UiEmptyState title="No hay historial de auditoría" description="..." />` |
| Hand-rolled audit log row | 189-251 | Raw `<div class="border border-hairline rounded-lg p-4 hover:bg-theme-surface">` | `<UiCard variant="glass">` wrapper (pacientes precedent) |
| Raw spinner `border-4 border-primary-200 border-t-primary-600` | 161 | `border-primary-*` legacy alias | `<LoadingSpinner />` |
| `text-red-500` / `text-green-500` in audit diff | 225, 229 | Raw colour ramps | `text-systemRed-600` / `text-systemGreen-600` |

### 2.3 Status pill inventory

All status pills use the tokenised `bg-system*-50 text-system*-700` ramps via `<UiStatusBadge>`. **No extraction work needed** — PR0 primitive fully consumed.

### 2.4 Focus pattern audit

Both files contain **zero** focus-styling declarations. The 9 raw `<input>` + 2 raw `<textarea>` have NO focus styling — they fall back to the browser default. Migrating to `<UiInput>`/`<UiTextarea>` fixes this as a side-effect.

---

## 3. Gap Analysis

### 3.1 What the PR0 + global proposal already provided

| Need | Provided by PR0 | Current state |
|---|---|---|
| `bg-canvas` page surface | `AppLayout.canvasRoutes` | ✓ PASSES |
| `border-hairline` dividers | Tailwind utility | ✓ PASSES |
| Card elevation tokens | `<UiCard variant="glass">` | ✓ PASSES |
| Status pills | `<UiStatusBadge>` | ✓ PASSES |
| Focus ring | `var(--focus-ring-default)` via `<UiInput>` | ✗ FAILS in modals (raw `<input>`) — fixed by migration |
| `tabular-nums` on price | Tailwind utility | ✓ PASSES |
| No `<style scoped>` blocks | Per `DLR-R-021` | ✓ PASSES (0 blocks) |
| No legacy alias classes | Standard grep list | ✓ PASSES (0 matches for `border-theme`, `bg-success-100`, `text-success-700`, `focus:ring-primary-500`, `focus:border-accent`, `hover-lift`, `text-accent`, `bg-accent`, `bg-primary-*`, `text-primary-*`) |
| `<UiEmptyState>` | Primitive | ✗ Hand-rolled in both files |
| `<LoadingSpinner>` | Primitive | ✗ Hand-rolled in both files |
| `<UiTabs>` | Primitive | ✗ Raw `<button>` in detail page |

### 3.2 Negative space (MUST NOT introduce)

No new tokens, no new primitives, no `<style scoped>` blocks, no legacy alias classes, no gradients anywhere, no raw `text-red-*` / `text-green-*` colour ramps, no `<script>` block edits.

### 3.3 Gap items the rollout PRs must close

| # | Gap | Surface | Severity | Owner PR |
|---|---|---|---|---|
| 1 | 9 raw `<input>` in modals | New + Edit modals in list | Medium | PR-tipos-01 |
| 2 | 2 raw `<textarea>` in modals | New + Edit modals in list | Medium | PR-tipos-01 |
| 3 | Hand-rolled `border-b-2 border-accent` spinner | List line 85 | Low | PR-tipos-01 |
| 4 | Hand-rolled list empty state (`<UiEmptyState>` imported but unused) | List lines 89-104 | Low | PR-tipos-01 |
| 5 | Raw `<button>` tab navigation | Detail lines 84-100 | Medium | PR-tipos-02 |
| 6 | Hand-rolled audit empty state with **FORBIDDEN gradient** | Detail line 167 | **High** (global guard rail #10) | PR-tipos-02 |
| 7 | Hand-rolled audit log row | Detail lines 189-251 | Medium | PR-tipos-02 |
| 8 | Raw spinner with `border-primary-*` | Detail line 161 | Low | PR-tipos-02 |
| 9 | Raw `text-red-500` / `text-green-500` colour aliases | Detail lines 225, 229 | Low | PR-tipos-02 |

---

## 4. Risk Assessment (Tier-1 admin CRUD)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `<UiInput>`/`<UiTextarea>` migration removes a `v-model` binding | Low | Medium (silent data-loss) | `<script>` blocks untouched; `useApi` `post`/`put` calls keep same payload shape; Playwright form-submit smoke test |
| `<UiTabs>` adoption changes `activeTab` binding | Low | Low (cosmetic) | Match `tabs` array shape `{id, name, icon}`; keep ref name |
| `<UiEmptyState>` in audit tab removes gradient background | **None — removal is the GOAL** | n/a | Gradient is a defect |
| `<UiCard>` audit row changes visual density | Low | Low | `space-y-4` parent per pacientes precedent |
| `<LoadingSpinner>` adoption | Low | Low | Replace hand-rolled `<div>` with `<LoadingSpinner />` |
| `text-red-500`/`text-green-500` → `text-systemRed-600`/`text-systemGreen-600` | None | None | Pure colour token swap |
| `AppointmentTypesAppShellTest` regression | Low | Low | Existing 12 test methods pass; new rules are additive |

### 4.2 Realtime risk

**NONE.** Neither file subscribes to Reverb channels. Module is fully synchronous HTTP.

---

## 5. Suggested Slice Plan (2 PRs)

### 5.1 PR-tipos-01 — List + modals cleanup

| Field | Value |
|---|---|
| Scope | (1) 9 raw `<input>` → `<UiInput>` + 2 raw `<textarea>` → `<UiTextarea>` in New + Edit modals. (2) `border-b-2 border-accent` spinner → `<LoadingSpinner>`. (3) Hand-rolled empty state → `<UiEmptyState>` (already imported). (4) New test `AppointmentTypesListCleanupTest.php`. |
| Files | `AppointmentTypesPage.vue` + 1 new test |
| Risk | Low |
| Lines | ~200 |

### 5.2 PR-tipos-02 — Detail cleanup

| Field | Value |
|---|---|
| Scope | (1) Raw `<button>` tab nav → `<UiTabs v-model="activeTab" :tabs="tabs" />`. (2) Hand-rolled audit empty state WITH gradient → `<UiEmptyState>`. (3) Hand-rolled audit log row → `<UiCard variant="glass">`. (4) Raw spinner → `<LoadingSpinner>`. (5) `text-red-500` / `text-green-500` → `text-systemRed-600` / `text-systemGreen-600`. (6) Extend `AppointmentTypesAppShellTest.php` with 3 additive rules. |
| Files | `AppointmentTypeDetailPage.vue` + 1 test extension |
| Risk | Low-Medium |
| Lines | ~180 |

### 5.3 Ordering rationale

- **List first**: list page has more residual defects (9 inputs + 2 textareas + spinner + empty state) and is the higher-traffic admin surface.
- **Detail second**: detail page's work is smaller and more concentrated.
- **Both fit 400-line budget** comfortably (~200 + ~180). Chained PR delivery per `auto-chain`.
- **Independent** — could stack in either order.

---

## 6. Open Questions for Proposal Phase

1. **`<UiTabs>` icon slot shape.** Detail page's `tabs` array carries `{id, name, icon}` with template-string icon components. Does `<UiTabs>` accept a per-tab `icon` prop, or must icons move to inline `<svg>` in the tab slot?
2. **Audit log row `space-y-4` adoption.** Per pacientes precedent, audit log rows become `<UiCard variant="glass">` wrappers with `space-y-4` parent. Confirm: tipos-cita should match.
3. **Gradient removal packaging.** `bg-gradient-to-br` on `AppointmentTypeDetailPage.vue:167` is a known anti-pattern. PR-tipos-02 removes it. Keep bundled or extract as one-line hotfix PR? Default: keep bundled.
4. **`<UiEmptyState>` icon prop.** Use default icon (pacientes precedent) or pass custom SVG? Default: default icon.

---

## 7. References

- `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (§3.2 row 6 names tipos-cita)
- `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (§7.6 plans PR4 triplet)
- `openspec/changes/archive/2026-08-12-ui-pacientes/explore.md` (closest precedent for 2-page admin CRUD)
- `openspec/changes/archive/2026-08-12-ui-pacientes/design.md` (§3.5 `<UiTabs>`, §3.8 audit row `<UiCard>`)
- `tests/Unit/DesignSystem/ModuleAppShellTestCase.php` (abstract base; 5 inherited rules)
- `tests/Unit/DesignSystem/AppointmentTypesAppShellTest.php` (existing 409-line test, 7 PR-citas-04 rules + 5 inherited)
- `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php`
- `resources/js/modules/appointment-types/AppointmentTypesPage.vue` (641 lines)
- `resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue` (407 lines)
- `resources/js/components/layout/AppLayout.vue` (line 523-556 canvasRoutes)
- `resources/js/components/ui/StatusBadge.vue` (PR0 primitive)
- `resources/js/components/ui/EmptyState.vue` (default folder icon)
- `AGENTS.md` §3 + §5 + §7

### 7.2 Standing guard rails (inherited from global proposal §11.2)

1. `tokens.js` is frozen — no new tokens.
2. `systemBackground` `#ffffff` pinned; `canvas` `#F2F2F7` pinned.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`.
6. `font-feature-settings: var(--font-features-tabular-nums)` for numerics.
7. `<script>` blocks NEVER edited.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only.
10. **No gradients anywhere** (the `bg-gradient-to-br` on line 167 of detail page MUST be removed).
11. Code in English; conversation in Spanish (Peru).

---

## Key Learnings

1. Pre-polished modules require residual-only PRs rather than first-pass migration; the slice plan shrinks from a single 400-line PR to two small PRs (~200 + ~180 lines).
2. An import without template consumption (`<UiEmptyState>` at `AppointmentTypesPage.vue:427`) indicates the prior PR added the import but never wired it; consume it or remove it.
3. The `bg-gradient-to-br` audit-tab empty state at `AppointmentTypeDetailPage.vue:167` violates the global no-gradients guard rail and must be removed in PR-tipos-02.
4. Neither `AppointmentTypesPage.vue` nor `AppointmentTypeDetailPage.vue` subscribes to Reverb channels, so the rollout carries no realtime regression risk for this module.
5. The pacientes detail-page pattern (`<UiTabs>` + `<UiCard variant="glass">` audit row + `<UiEmptyState>` audit empty state) applies verbatim to tipos-cita in PR-tipos-02.

---

*End of tipos-cita explore. Next phase: `sdd-propose` for this category.*
