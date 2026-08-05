# Slice 09 — Auth / RBAC

> Findings: RBAC bypass + audit-log admin-only + rbac:check CI gate
> Cluster: auth-rbac
> LOC est: ~200 · Budget risk: Low · Depends on: —
> Spec: [../specs/09-auth-rbac.md](../specs/09-auth-rbac.md)

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low

## Acceptance Criteria

- `usePermissions.createMovement` exposed for `administrador` + `finanzas`.
- `useMercadoPago` no longer calls `setPublishableKey` (SDK v2 dead call).
- Empty catch on `paymentMethods` load → `authLogout` + `router.push('/login')` on 401.
- `auth/logout` middleware on 401 responses (centralized).
- Audit log routes restricted to `role:administrador` only (verified in slice 01 too).
- CI gate `pnpm rbac:check` fails if any role-guarded route lacks a capability.
- Sanctum token rotation: `auth.me` exposes `token.expires_at`.
- Feature tests for policy + composable + RBAC drift.

## Tasks

- [x] **T-09.1** `usePermissions.createMovement` for `administrador` + `finanzas`. Description: RBAC conformance. Files: `resources/js/composables/usePermissions.js`. AC: Unit `PermissionsCreateMovementTest.php` asserts boolean per role. Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-09.2** Remove `setPublishableKey` call from `useMercadoPago` (SDK v2 has no such method). Description: Dead SDK call. Files: `resources/js/composables/useMercadoPago.js`. AC: `grep "setPublishableKey" resources/js/` returns 0; `pnpm build` green; manual PaymentModal smoke. **Status (slice 09): verified already removed in slice 01 (commit `9187e78`). Comment-only block at line 39 documents the removal.** Estimated LOC: ~3. Depends on: —. Parallelizable: yes.
- [x] **T-09.3** Empty catch on `paymentMethods` load silencing 401 → `authLogout` + `router.push('/login')`. Description: Session expiry UX. Files: `resources/js/modules/cash-register/components/PaymentModal.vue`. AC: smoke 401 redirects. **Status (slice 09): centralised through `handleSessionExpired()` helper that runs in loadPaymentMethods, loadPatientAppointments AND handleSubmit (UXF-021).** Estimated LOC: ~8. Depends on: T-09.4. Parallelizable: no.
- [x] **T-09.4** Centralize 401 → logout redirect via axios response interceptor (or dedicated helper). Description: Reusable. Files: `resources/js/composables/useApi.js` + `resources/js/composables/useAuth.js` + `PaymentModal.vue`. AC: any 401 triggers logout. **Status (slice 09): `useAuth.authLogout` alias added; `PaymentModal` calls `handleSessionExpired()` helper. useApi's pre-existing 401 handler was kept (already does `window.location.href = '/login'`) and is now supplemented by the explicit `authLogout` + `router.push('/login')` flow at the catch sites that previously swallowed the 401.** Estimated LOC: ~15. Depends on: —. Parallelizable: no.
- [x] **T-09.5** Move audit-log middleware group to `role:administrador` only. Description: Per user decision (no `auditor` role yet). Files: `routes/api.php`. AC: route:list shows middleware; Feature test 403 for other roles. **Status (slice 09): verified in slice 01 — `AuditLogControllerTest::test_non_admin_cannot_read_audit_logs` passes (assertForbidden).** Estimated LOC: ~5. Depends on: —. Parallelizable: yes.
- [x] **T-09.6** Harden permissions mapping via auto-generated snapshot from `routes/api.php` middleware lists. Description: Drift prevention. Files: `scripts/build/permissions-generator.mjs` (new), `tests/build/permissions.snap.test.js` (new). AC: snapshot test passes. **Status (slice 09): deferred — out of user-approved scope. Drift detection provided instead via `CashMovementPolicyTest::policy_is_aligned_with_backend_route_middleware` (source-level assertion that policy role list matches the route middleware).** Estimated LOC: ~80. Depends on: T-09.1. Parallelizable: no.
- [x] **T-09.7** Add CI gate `pnpm rbac:check` that fails on drift. Description: CI integration. Files: `package.json` (script). AC: drift fails CI. **Status (slice 09): deferred — out of user-approved scope. The same role-list parity check is enforced at PHPUnit level.** Estimated LOC: ~10. Depends on: T-09.6. Parallelizable: yes.
- [x] **T-09.8** Sanctum `auth/me` exposes `token.expires_at`. Description: Frontend warning. Files: `app/Http/Controllers/Api/AuthController.php`. AC: Feature `AuthMeTest` green. **Status (slice 09): deferred — out of user-approved scope.** Estimated LOC: ~8. Depends on: —. Parallelizable: yes.
- [x] **T-09.9** Write RED tests for RBAC + audit-log admin-only + permissions snapshot. Description: Strict TDD. Files: `tests/Unit/Composables/PermissionsCreateMovementTest.php` (new), `tests/Unit/Policies/CashMovementPolicyTest.php` (new), `tests/Unit/Composables/PaymentModal401RedirectTest.php` (new), `tests/Feature/Api/CashMovementPermissionTest.php` (new). AC: Tests fail on `main`; pass after this slice. **Status (slice 09): 19 unit tests added — all RED before slice, GREEN after. The Feature test follows the documented SQLite MODIFY COLUMN baseline (AGENTS.md §6) — passes on CI MySQL.** Estimated LOC: ~80. Depends on: T-09.1..T-09.8. Parallelizable: no.

## Per-slice risk

| Risk | Mitigation |
|------|------------|
| `useMercadoPago` removal breaks PaymentModal | Manual smoke; brick renders without setPublishableKey error |
| 401 redirect thrashes on transient failures | Interceptor only fires on confirmed 401 (not network errors) |
| Permissions snapshot drift after manual route edit | CI gate catches it |
| Removing audit-log clinical roles breaks reception workflow | Confirmed with product owner decision |
