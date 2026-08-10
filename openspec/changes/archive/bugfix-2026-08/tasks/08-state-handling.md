# Slice 08 — State Handling

> Findings: composable shape standardization + retry + focus refresh
> Cluster: state-handling
> LOC est: ~340 · Budget risk: Medium · Depends on: S04, S05
> Spec: [../specs/08-state-handling.md](../specs/08-state-handling.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Medium

## Acceptance Criteria

- Every data-owning composable exposes `{ data, loading, error, refresh }`.
- `useToast` returns `toasts` (Ref) not `toasts.value` (plain array).
- `useAiAnalysis.uploadAndAnalyze` `options.headers` no longer ignored.
- `useCashRegister` no longer double-subscribes WebSocket (singleton).
- `app.js` invokes `useEcho()` after auth, not before.
- `useNotifications.loadFromStorage()` SSR-guarded.
- `DashboardPage` WS listeners debounce `loadDashboardData` at 300ms.
- `router/auth.js` uses `useAuth().isAuthenticated` not raw localStorage.
- `CalendarPage` View Controls indented consistently (prettier).
- `bootstrap.js` axios import removed (unused).
- Spanish error messages; retry function on every composable.

## Tasks

- [x] **T-08.1** `useToast` returns `toasts` (Ref), not `toasts.value` (plain array). Description: Reactivity fix. Files: `resources/js/composables/useToast.js`. AC: `pnpm build` green; smoke shows reactive toast list. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-08.2** `useAiAnalysis.uploadAndAnalyze` `options.headers` ignored → remove unused param or wire it. Description: API hygiene. Files: `resources/js/composables/useAiAnalysis.js`. AC: grep unused options.headers gone. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-08.3** `useCashRegister` double WS subscription → module-level singleton. Description: State hygiene. Files: `resources/js/composables/useCashRegister.js`. AC: smoke connects once (verify via `console.log` count). Estimated LOC: ~10. Depends on: —. Parallelizable: yes.
- [x] **T-08.4** `app.js` invokes `useEcho()` before auth → move to post-login hook. Description: State hygiene. Files: `resources/js/app.js`. AC: `grep "useEcho" resources/js/app.js` returns 1 invocation, after auth check. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-08.5** `useNotifications.loadFromStorage()` top-level → move inside `useNotifications()` or `typeof window !== 'undefined'` guard. Description: SSR hygiene. Files: `resources/js/composables/useNotifications.js`. AC: SSR smoke doesn't crash. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-08.6** `DashboardPage` WS listeners duplicate `loadDashboardData` → debounce 300ms. Description: Performance. Files: `resources/js/pages/DashboardPage.vue`. AC: rapid WS events fire loadDashboardData once. Estimated LOC: ~8. Depends on: T-08.3. Parallelizable: no.
- [x] **T-08.7** `router/auth.js` requires token+user localStorage directly → use `useAuth().isAuthenticated`. Description: API hygiene. Files: `resources/js/router/auth.js`. AC: guard uses composable. Estimated LOC: ~6. Depends on: —. Parallelizable: yes.
- [x] **T-08.8** `CalendarPage` View Controls indentation → prettier. Description: Style only. Files: page. AC: prettier check passes. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-08.9** `bootstrap.js` imports axios without using it → remove import + devDependency if unused. Description: Dead code. Files: `resources/js/bootstrap.js`, `package.json`. AC: grep `axios` returns only intentional uses. Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-08.10** Standardize `{ data, loading, error, refresh }` on remaining composables (`useTransactions`, `useTreatmentPlans`, `useBranches`, `useSpecialties`, `useAiAnalysis`, `useCashRegister`). Description: Convention. Files: composables. AC: TypeScript-style doc-comment + lint rule. Estimated LOC: ~40. Depends on: —. Parallelizable: yes (per-composable). NOTE: `usePatients`/`useAppointments` do not exist as separate composables in this project; logic is inlined in their page components.
- [x] **T-08.11** Add `retry()` alias of `refresh()` to every data composable. Description: API parity. Files: composables. AC: composable exposes both. Estimated LOC: ~10. Depends on: T-08.10. Parallelizable: yes.
- [x] **T-08.12** Add `visibilitychange` auto-refresh (debounced 500ms) to list composables. Description: Stale data refresh. Files: composables. AC: tab refocus triggers refresh. Estimated LOC: ~30. Depends on: T-08.10. Parallelizable: yes. DEFERRED — exceeds the 400-LOC budget for slice 08; rolled into slice 11 (visual-flow.a11y). **(resuelto en slice 11 commit 1e402f9)**
- [x] **T-08.13** Optimistic update rollback on non-2xx mutation response. Description: UX resilience. Files: mutation composables. AC: failed POST reverts state + toast. Estimated LOC: ~50. Depends on: T-08.10. Parallelizable: yes. DEFERRED — exceeds budget; dependency on T-08.14. **(no-op documentado: ningún componente usa mutaciones optimistas)**
- [x] **T-08.14** `useApi` normalizes errors to `{ message, status }`. Description: Centralized. Files: `resources/js/composables/useApi.js`. AC: callers see normalized shape. Estimated LOC: ~20. Depends on: T-08.1. Parallelizable: no. DEFERRED — exceeds budget; pre-existing error contract already mostly Spanish. **(resuelto en slice 11 commit 1e402f9: useApi normalizeError helper)**
- [x] **T-08.15** Localize error messages to Spanish (response.data.message + meta.message fallback). Description: i18n. Files: composables. AC: errors display in Spanish. Estimated LOC: ~40. Depends on: T-08.14. Parallelizable: yes. DEFERRED — error messages are already in Spanish across the composables touched by this slice. **(no-op documentado: ya en español)**

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| `useToast` reactivity fix breaks consumers | Grep `useToast().toasts` callers; manual smoke — only `globalToasts` (separate export) consumes it directly and it remains the Ref |
| `visibilitychange` auto-refresh thrashes on multi-tab | Debounced 500ms; respects per-composable opt-out |
| Optimistic rollback race condition | Per-mutation lock + revert on next refresh |
| `bootstrap.js` axios removal breaks other entry | Grep usages first — confirmed: 3 hits (3 bootstrap.js lines) only |
