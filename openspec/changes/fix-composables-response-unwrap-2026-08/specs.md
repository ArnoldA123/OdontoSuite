# Specs Index — fix-composables-response-unwrap-2026-08

## Change Summary

Frontend-only correctness fix for NEW-005. `useApi.handleResponse` at `resources/js/composables/useApi.js:74` returns the flat JSON envelope `{data: [...], meta: {...}}` returned by the backend without unwrapping. Nine composables and four pages then over-drill one level: `response.data?.data` / `response.data.data` / `response.data?.meta`. The second `.data` is always `undefined` because the backend never wraps in a second envelope. Verified empirical impact: Catálogo de Procedimientos renders 0 of 41 BD rows; Especialidades dropdown shows only "— Sin especialidad —" against 9 rows. Same anti-pattern repeats, unverified, across 7 more composables. This change enforces a single canonical unwrap rule across the codebase and adds a static PHPUnit guard so the anti-pattern cannot regress.

## Specs

| File | Domain | Type | Requirements | Scenarios |
|------|--------|------|--------------|-----------|
| [specs/01-response-unwrap-canonical.md](specs/01-response-unwrap-canonical.md) | response-unwrap-canonical | Delta | 2 ADDED | 12 |

## Coverage

- Happy paths: covered (Catálogo renders 41; Especialidades renders 9; Bi/DataDrill/Stats/Detail/CIP show their data; guard test exits 0).
- Edge cases: covered (non-envelope endpoints like action verbs still work because `useApi.handleResponse` already passes `response.json()` through verbatim).
- Error states: covered (error path `err.response.data.message` is explicitly excluded from the regex; the regex strips strings + comments before matching, mirroring `SddCheckMigrationsTest`).

## Trigger

- NEW-005 (Engram `#322`, Lens A reproduction) — orchestrator verified the broken state by calling `GET /api/procedure-catalog` against the live DB (41 procedures present, 0 rendered on Catálogo de Procedimientos page) and `GET /api/specialties` (9 specialties present, only "— Sin especialidad —" in the New Procedure modal dropdown).

## Parent

- `bugfix-2026-08` (archived) — domain: composables API consumer pattern; sibling of `hotfix-migration-chain-full-sweep-2026-08` (same codebase, different concern).

## Siblings (NOT modified by this change)

- `useApi.js` itself (unchanged — `handleResponse` is intentionally untouched to avoid adding opacity to every caller).
- Working composables `EnvironmentsPage.vue:359`, `AppointmentTypesPage.vue`, and any composable that already uses `response?.data` / `response.data` correctly.
