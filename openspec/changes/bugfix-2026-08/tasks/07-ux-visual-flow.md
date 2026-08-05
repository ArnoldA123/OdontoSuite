# Slice 07 — UX Visual Flow

> Findings: UXV/UXF/UXT remainder (28 visual-flow fixes)
> Cluster: visual-flow
> LOC est: ~380 · Budget risk: Medium · Depends on: S06, S11 partial
> Spec: [../specs/07-ux-visual-flow.md](../specs/07-ux-visual-flow.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Medium

## Acceptance Criteria

- All 14 `*Modal.vue` register Escape keydown listener + focus trap scoped to `role="dialog"`.
- `Modal.vue` `titleId`/`descriptionId` use `useId()` (Vue 3.5+) or stable ref, not `Math.random()` per render.
- `PaymentModal` surfaces 401 from backend (`response.data.message` + `meta.message`) — no silent catch.
- `useApi.del(url, { data })` accepts body.
- 11 list views show `EmptyState` (icon + Spanish message + optional CTA).
- Loading skeletons replaced with `SkeletonList`.
- Confirmation dialog for destructive actions via `ConfirmDialog`.
- Pagination has `aria-label` + `role="navigation"`.
- Sidebar/UserMenu toggle has `aria-expanded`/`aria-controls`.
- WebSocket listener errors → `console.error` + toast.
- `MobileMenu` closes on route change.
- Native `<select>` → `UiSelect` in all pages with selects.
- Empty states have CTA buttons.
- `router` has 404 catch-all + lazy-chunk error handler.

## Tasks

- [x] **T-07.1** Forms without guard against double submit (AppointmentTypes, Environments, Professionals) → `UiButton :loading="submitting"`. Description: UX fix. Files: pages. AC: smoke double-click submits once. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-07.2** Replace `alert()` with `toast.success`/`toast.error` (Professionals, Environments). Description: UX fix. Files: pages. AC: `grep "alert(" resources/js/pages/Professionals.vue resources/js/pages/Environments.vue` returns 0. Estimated LOC: ~8. Depends on: —. Parallelizable: yes.
- [x] **T-07.3** Migrate overlays to `UiModal` with Escape key + focus trap + `tabindex="-1"` (18 files). Description: A11y + WCAG 2.1.1. Files: 18 `*Modal.vue` files. AC: Escape closes modal; focus stays inside; FullCalendar unaffected. Estimated LOC: ~120. Depends on: T-07.4. Parallelizable: yes (per-file).
- [x] **T-07.4** `Modal.vue` `titleId`/`descriptionId` with `Math.random()` → `useId()` Vue 3.5+ or ref generated once. Description: A11y fix (stable IDs needed for `aria-labelledby`). Files: `resources/js/components/ui/UiModal.vue`. AC: snapshot test confirms stable IDs across re-renders. Estimated LOC: ~6. Depends on: —. Parallelizable: no.
- [x] **T-07.5** Navigation "Volver" loses context → `router.back()` configurable. Description: UX fix. Files: pages. AC: Back button restores prior scroll + filter state. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-07.6** Login page "Centro Ayuda" button without action → wire to `/help` route or remove. Description: UX fix. Files: `resources/js/pages/LoginPage.vue`. AC: button either routes or hidden. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-07.7** WebSocket listener errors silently caught → `console.error` + toast. Description: UX fix. Files: `resources/js/composables/useReverb.js`. AC: simulated WS error logs + toast. Estimated LOC: ~8. Depends on: —. Parallelizable: yes.
- [x] **T-07.8** `MobileMenu` doesn't close on route change → `watch route.path` + close. Description: UX fix. Files: `resources/js/components/MobileMenu.vue`. AC: navigation collapses menu. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-07.9** `PaymentModal.loadPatientAppointments` TODO + empty catch → implement OR disable selector + explain why. Description: UX fix. Files: `resources/js/components/PaymentModal.vue`. AC: selector either loads or shows "no appointments" with reason. Estimated LOC: ~15. Depends on: T-07.3. Parallelizable: no.
- [x] **T-07.10** Forms without `toast.success` on mutations (Environments, SpecialtyRecord, CreatePatientInline). Description: UX feedback gap. Files: pages. AC: smoke shows success toast. Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-07.11** Pagination without `aria-label`/`role="navigation"`. Description: A11y. Files: `resources/js/components/Pagination.vue`. AC: snapshot test confirms attributes. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-07.12** Sidebar toggle without `aria-expanded`/`aria-controls`. Description: A11y. Files: sidebar component. AC: aria attrs present. Estimated LOC: ~4. Depends on: —. Parallelizable: yes.
- [x] **T-07.13** User menu without `aria-haspopup`/`aria-expanded` + Escape key handler. Description: A11y. Files: user menu. AC: aria attrs + Escape closes. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-07.14** Sidebar toggle persisted in localStorage without sanitization. Description: Security + UX. Files: sidebar. AC: input sanitized. Estimated LOC: ~4. Depends on: —. Parallelizable: yes.
- [x] **T-07.15** WebSocket indicator without `connecting` state. Description: UX fix. Files: indicator. AC: shows 3 states (connected/connecting/disconnected). Estimated LOC: ~6. Depends on: T-07.7. Parallelizable: no.
- [x] **T-07.16** Required marker without `aria-required` (ConsultationWizard). Description: A11y. Files: wizard. AC: `aria-required="true"` on required inputs. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-07.17** Empty states without CTA. Description: UX gap. Files: list pages. AC: every EmptyState has optional CTA slot. Estimated LOC: ~10. Depends on: T-07.20. Parallelizable: yes.
- [x] **T-07.18** `router` without 404 catch-all nor lazy-chunk error handler. Description: UX resilience. Files: `resources/js/router/index.js`. AC: unknown route renders NotFound; lazy-chunk error retries. Estimated LOC: ~12. Depends on: —. Parallelizable: yes.
- [x] **T-07.19** `CashRegisterPage` `:show` on modals instead of `modelValue` (unify). Description: Convention. Files: page. AC: `grep ":show=" CashRegisterPage.vue` returns 0. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-07.20** Empty states generic → `UiEmptyState` component. Description: Reusable component. Files: `resources/js/components/ui/UiEmptyState.vue` (new). AC: smoke + visual diff. Estimated LOC: ~30. Depends on: T-06.1. Parallelizable: no.
- [x] **T-07.21** Add `ConfirmDialog.vue` for destructive actions (delete buttons). Description: A11y + safety. Files: `resources/js/components/ui/ConfirmDialog.vue` (new). AC: snapshot test. Estimated LOC: ~30. Depends on: T-06.1. Parallelizable: yes.
- [x] **T-07.22** Add `SkeletonList.vue` for loading states. Description: UX polish. Files: `resources/js/components/ui/SkeletonList.vue` (new). AC: snapshot test. Estimated LOC: ~25. Depends on: T-06.1. Parallelizable: yes.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| Modal focus trap conflicts with FullCalendar | Trap scoped to `role="dialog"` only |
| `useId()` requires Vue 3.5+ | Verify Vue version; fallback to stable ref |
| 18 modal files = high churn | Per-file commits; lint + snapshot per file |
| Native `<select>` swap breaks form state | Snapshot + smoke per page |
