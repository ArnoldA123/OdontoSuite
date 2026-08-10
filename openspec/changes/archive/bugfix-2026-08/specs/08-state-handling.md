# Delta for State Handling — Slice 08

Resolves state-handling findings: every composable that owns server-derived state MUST expose `loading`, `error`, `data`, and `refresh`; every async action MUST handle rejection with a user-visible message.

## ADDED Requirements

### Requirement: Composable State Shape Standardized

The system MUST expose the following shape on every data-owning composable: `{ data: Ref<T|null>, loading: Ref<boolean>, error: Ref<string|null>, refresh: () => Promise<void> }`. Consumers MUST be able to render any of the four states without extra plumbing.

Evidence: 9 composables exposed inconsistent shapes (some only `data`, some missed `error`).

#### Scenario: shape contract

- WHEN `usePatients().refresh()` is called
- THEN `loading` becomes true, then false on completion
- AND `error` is null on success or a Spanish message on failure
- AND `data` is populated with the fetched payload

Test obligation: Unit test `tests/composables/usePatients.spec.js` + similar per composable.

---

### Requirement: Error Messages Localized

Error messages rendered to the user MUST be in Spanish. The composable MUST derive the message from the backend response (`response.data.message` or `response.data.meta.message`) when available, falling back to a generic message only if the backend provides nothing.

#### Scenario: backend message wins

- WHEN the backend returns `{ message: 'Sesión expirada' }`
- THEN the composable's `error.value` is exactly `Sesión expirada`

Test obligation: Unit + integration.

---

### Requirement: Error Retry Exposed

Every data-owning composable MUST expose a `retry` function (alias of `refresh`) so failed fetches can be re-attempted from a UI button.

#### Scenario: retry works

- WHEN the initial fetch fails
- AND the user clicks "Reintentar"
- THEN `retry()` re-runs the fetch
- AND on success the error clears

Test obligation: Component test.

---

### Requirement: Stale Data Refresh on Focus

Composables that own list data MUST auto-refresh when the window regains focus (after `visibilitychange`). The refresh MUST be debounced (500ms) to avoid stampedes.

#### Scenario: refresh on focus

- WHEN the user switches back to the tab after 30 seconds
- THEN the composable refetches
- AND `loading` is true during the refetch

Test obligation: Unit test with fake timers + jsdom.

---

### Requirement: Optimistic Update Rollback

Composables that support mutation MUST revert the optimistic change if the server returns a non-2xx response. The composable MUST restore the prior `data` value and surface the error.

#### Scenario: rollback on failure

- WHEN an optimistic mutation fails (e.g. 422)
- THEN `data` reverts to the pre-mutation value
- AND `error` is populated

Test obligation: Unit test.

---

## MODIFIED Requirements

### Requirement: useApi Normalizes Errors

`useApi.js` MUST wrap every axios call so that rejections produce a `{ message, status }` shape consumable by composables. The raw axios error MUST NOT leak.

(Previously: composables had to handle axios's error shape manually.)

#### Scenario: normalized error

- WHEN axios throws
- THEN the wrapper produces `{ message: '...', status: 4xx|5xx }`

Test obligation: Unit test on `useApi`.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Composable State Shape | Unit | per composable |
| Error Messages Localized | Unit + integration | per composable |
| Error Retry Exposed | Component | per consumer |
| Stale Data Refresh on Focus | Unit | `tests/composables/focusRefresh.spec.js` |
| Optimistic Update Rollback | Unit | per mutation composable |
| useApi Normalizes Errors | Unit | `tests/composables/useApi.spec.js` |
