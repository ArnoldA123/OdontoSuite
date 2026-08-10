# Delta for Auth / RBAC — Slice 09

Resolves RBAC findings: harden the `permissions` route→method mapping, expose `createMovement` on `usePermissions`, restrict `audit-log.show` to `role:administrador` only (per user decision), and add a CI gate that catches mismatches between `routes/api.php` and the frontend `usePermissions` composable.

## ADDED Requirements

### Requirement: Audit Log Routes Restricted to Admin

The system MUST restrict every `/audit-logs/*` route to `role:administrador` only. Auditor and other roles MUST receive 403.

Evidence: User decision (auditor role not yet in product).

#### Scenario: admin can read

- WHEN `administrador` requests `GET /audit-logs`
- THEN response is 200

#### Scenario: non-admin forbidden

- WHEN `recepcionista` (or any other role) requests `GET /audit-logs`
- THEN response is 403

Test obligation: PHPUnit Feature.

---

### Requirement: usePermissions createMovement Exposed

The composable MUST expose a `createMovement` capability for users with `role:administrador` or `role:finanzas`. Other roles MUST get `false`.

Evidence: CashMovementCreate.vue mounted for users without the role; backend is correctly restricted but frontend exposed no signal.

#### Scenario: admin gets true

- WHEN user is `administrador`
- THEN `usePermissions().createMovement === true`

#### Scenario: recepcionista gets false

- WHEN user is `recepcionista`
- THEN `usePermissions().createMovement === false`

Test obligation: Unit test on `usePermissions.js`.

---

### Requirement: Permissions Mapping Hardened

The `permissions` object in `usePermissions.js` MUST cover every backend route group (`audit-logs`, `cash-movements`, `cash-register`, `reminders`, `reminder-templates`, `waiting-lists` removed). The mapping MUST be auto-generated from `routes/api.php` middleware lists at build time.

Evidence: Frontend mapping was hand-maintained; drift inevitable.

#### Scenario: build regenerates mapping

- WHEN `pnpm build` runs
- THEN `resources/js/composables/usePermissions.generated.js` reflects current routes

Test obligation: Build check + snapshot.

---

### Requirement: CI Gate for RBAC Drift

The system MUST run a CI step `pnpm rbac:check` that fails if any route in `routes/api.php` uses `role:` middleware but the corresponding capability is missing in `usePermissions.js` (or vice versa for declared capabilities with no backend route).

#### Scenario: missing capability fails CI

- WHEN a new `role:`-guarded route is added
- AND the corresponding capability is not declared in `usePermissions.js`
- THEN the CI gate fails

Test obligation: CI gate script.

---

### Requirement: Sanctum Token Rotation Visible

The system MUST expose a `token.expires_at` field on `GET /auth/me` so the frontend can warn the user before the token expires.

#### Scenario: me returns expiry

- WHEN user requests `GET /auth/me`
- THEN response includes `data.token.expires_at`

Test obligation: Feature.

---

## MODIFIED Requirements

### Requirement: Audit Log Middleware Group Is Admin-Only

The route group around `audit-logs` MUST be moved from `role:administrador,recepcionista,odontologo,implantologo,tecnico_dental,asistente` (current) to `role:administrador` (new).

(Previously: any authenticated clinical role could read.)

#### Scenario: role group narrowed

- WHEN `routes/api.php` is read
- THEN the audit-logs group has only `role:administrador`

Test obligation: Snapshot + route:list.

---

## REMOVED Requirements

None for this slice.

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| Audit Log Routes Restricted to Admin | Feature | `tests/Feature/Api/AuditLogControllerTest.php` |
| usePermissions createMovement Exposed | Unit | `tests/composables/usePermissions.spec.js` |
| Permissions Mapping Hardened | Build + snapshot | `tests/build/permissions.snap.test.js` |
| CI Gate for RBAC Drift | CI gate | `pnpm rbac:check` |
| Sanctum Token Rotation Visible | Feature | `tests/Feature/Api/AuthMeTest.php` |
| Audit Log Middleware Group Is Admin-Only | Snapshot | `route:list` |
