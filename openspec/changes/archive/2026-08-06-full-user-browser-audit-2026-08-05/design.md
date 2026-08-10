# Design: full-user-browser-audit-2026-08-05 — PR5 Bounded Corrections

> **Update-in-place of the existing 6-slice design** (see Engram `sdd/full-user-browser-audit-2026-08-05/design`, observation #333). Slices 01–06 remain in effect. This document **adds Slice 07** with three tightly-scoped, test-first corrections for the defects surfaced by `apply-progress` PR5 (Engram observation #336).
>
> Session preflight: `execution=auto, artifact_store=hybrid, delivery_strategy=auto-chain, review_budget_lines=400`. Strict TDD oracle: `php artisan test`.

## Intent

Three production defects were exposed by Feature tests under PR5 (`CatalogFilterTest`, `ReminderDispatchTest`) against the dev MySQL harness (`phpunit.mysql.xml`):

1. `App\Models\ReminderSchedule::$fillable` declares `type` and `anticipation_hours` — **these columns do not exist in `reminder_schedules`**. The canonical column is `hours_before` (migration `2025_09_20_082355_create_reminder_schedules_table.php`). `App\Services\ReminderService` writes `type` + `anticipation_hours` on every insert, producing `Unknown column 'type' in 'field list'` against MySQL strict mode. Schema history proves these phantom columns were never added by any migration.
2. `bootstrap/app.php` registers a catch-all `Throwable` renderer that returns **500 instead of 401** when Sanctum throws `Illuminate\Auth\AuthenticationException` for missing/expired bearer tokens. Every Feature test asserting strict 401 fails (`CatalogFilterTest`, `PatientControllerAgeTest`). Web request flow (`/login` redirect) must not break.
3. `Database\Factories\UserFactory::definition()` does **not emit `username`**, yet `users.username` is `string NOT NULL UNIQUE` (migration `2025_09_13_151927_add_username_and_role_to_users_table.php`). `User::factory()->create()` fails against MySQL strict mode.

This slice corrects all three with **bounded, reversible, test-first changes**.

## Scope

### In Scope

- Slice 07a: correct stale `ReminderSchedule::$fillable`; align `ReminderService` to write `hours_before`; add a regression test asserting the model + service agree with the schema. **No migration** — schema history proves `type` / `anticipation_hours` were never added to `reminder_schedules`.
- Slice 07b: add an explicit `Illuminate\Auth\AuthenticationException` renderer in `bootstrap/app.php` that returns the project's canonical 401 JSON envelope for API/JSON requests and defers (returns `null`) for HTML requests so Laravel's existing `Authenticate::redirectTo()` keeps redirecting to `route('login')`. Register the renderer **before** the catch-all `Throwable` renderer. Add a regression test asserting 401 + canonical envelope on a Sanctum-protected route, and tightening existing tests that previously asserted `>=400`.
- Slice 07c: extend `UserFactory::definition()` with `'username' => fake()->unique()->userName()`. The MySQL `users.username` UNIQUE constraint is then satisfied for every factory call. Add a regression test asserting the factory contract.

### Out of Scope (explicit non-goals)

- Migrations on `reminder_schedules` (schema is canonical; stale code is the defect). Adding `type` / `anticipation_hours` columns would be a feature change, not a bug fix.
- New permission policies, middleware, role definitions.
- Backend exception infrastructure beyond the specific `AuthenticationException` renderer.
- Mass refactor of `bootstrap/app.php` renderers (each renderer handles one exception class; ordering already correct after the insertion).
- Changes to `User` model, `users` migration, `auth.php` config, or Sanctum guard behaviour.
- Migration safety: any change to the `reminder_schedules` schema is explicitly disallowed in this slice.

## Technical Approach

Three sub-slices, each under 200 changed lines, RED → GREEN under strict TDD. `php artisan test` is the oracle. Each sub-slice is independently revertible in a single commit.

**Order of operations**:

1. **07a RED**: `tests/Unit/Models/ReminderScheduleFillableContractTest.php` — assert `(new ReminderSchedule)->getFillable()` does NOT contain `type` or `anticipation_hours` and DOES contain every column present in any migration touching `reminder_schedules`. Service-source assertion: `ReminderService.php` must not contain `\\'type\\' =>` or `\\'anticipation_hours\\' =>` and MUST contain `\\'hours_before\\' =>`. Run against current code → RED (factory + service write `type` and `anticipation_hours`).
2. **07a GREEN**: trim `ReminderSchedule::$fillable` to the real columns; update three `ReminderService` call sites to write `hours_before` and drop the redundant `type`. Delete `scopeOfType()` (queries a non-existent column; verified by grep, zero callers). Run → GREEN.
3. **07a GREEN**: extend `ReminderDispatchTest::test_24h_reminder_creates_one_schedule_at_minus_one_hour` to also assert `hours_before == 24`. Tighten `test_redispatch_does_not_duplicate_reminder` the same way (after 07c fixes the factory).
4. **07b RED**: `tests/Feature/Api/AuthenticationEnvelopeTest.php` — assert `getJson('/api/auth/me')` without bearer returns 401, body matches `{message: 'No autenticado.', error: null}`, and the response includes `WWW-Authenticate: Bearer ...`. Assert a non-SPA HTML route (mock with `Accept: text/html`) still receives Laravel's default redirect. Against current code → RED (returns 500).
5. **07b GREEN**: register `$exceptions->render(\\Illuminate\\Auth\\AuthenticationException::class, ...)` **before** the `Throwable` renderer in `bootstrap/app.php`. Body returns 401 JSON for `$request->expectsJson() || $request->is('api/*')`; returns `null` otherwise. Run → GREEN.
6. **07b TIGHTEN**: re-tighten `CatalogFilterTest::test_unauthenticated_request_is_rejected_with_401` and `PatientControllerAgeTest` to assert exact 401 + envelope shape + `WWW-Authenticate` header (previously asserted leniently).
7. **07c RED**: `tests/Unit/Database/UserFactoryContractTest.php` — assert `User::factory()->make()->username` is non-null + length ≤ 255; assert `User::factory()->create()` persists without DB exception; assert 5 sequential `User::factory()->create()` calls produce 5 distinct usernames; assert the `unverified()` state still emits a username. Against current code → RED (`MassAssignmentException`).
8. **07c GREEN**: add `'username' => fake()->unique()->userName()` to `UserFactory::definition()`. Run → GREEN.
9. **Final**: `php artisan test` whole suite green.

## Architecture Decisions

### Decision: Bounded code correction over a new migration (Slice 07a)

| Option | Tradeoff | Decision |
|--------|---------|----------|
| Add `type` and `anticipation_hours` columns via migration | Confirms the stale model claims but introduces dead columns the schema never had; contradicts migration history. | Rejected |
| Add a `type` column only (mirrors `ReminderTemplate.type`) | Restores the most-used column; leaves `anticipation_hours` orphaned; contradicts history. | Rejected |
| **Correct the model + service to use the canonical `hours_before`** | Aligns with documented schema; reversible in one revert; no schema drift; restores the 24h/48h/72h cadence that `hours_before` (default 24) was designed for. | **Accepted** |

**Rationale (verified against source)**: Migration history for `reminder_schedules` is exactly three files:

- `2025_09_20_082355_create_reminder_schedules_table.php` — original create: `id, appointment_id, reminder_template_id, hours_before, scheduled_at, sent_at, status, error_message, created_at, updated_at`. **No `type`, no `anticipation_hours`.**
- `2025_10_24_201039_make_reminder_template_id_nullable_in_reminder_schedules_table.php` — only modifies `reminder_template_id` nullability.
- `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` — adds `channel` (nullable) and `error_message` (already existed; idempotent guard).

No migration has ever added `type` or `anticipation_hours`. The columns are pure phantom state in the model. The fix is to delete them, not to materialise them. **Migration safety**: zero schema change; no rollback needed; `migrate:status` and `migrate:fresh` both unaffected.

### Decision: Renderer-based fix, not middleware rewrite (Slice 07b)

| Option | Tradeoff | Decision |
|--------|---------|----------|
| Define a new `Authenticate` middleware subclass | Reaches into Sanctum's chain; risks breaking session cookies; larger blast radius. | Rejected |
| Convert to Laravel 11-style `redirectGuestsTo` in `Authenticate` middleware | Sanctum-side; doesn't apply because Sanctum uses a different auth pipeline. | Rejected |
| **Register an explicit `AuthenticationException` renderer that returns the canonical 401 envelope for JSON/API requests and `null` for web** | Surgical, renderer-only change; matches Laravel 11 idiomatic style; preserves web redirect; stops short-circuiting before the catch-all `Throwable` returns 500. | **Accepted** |

**Rationale**: The catch-all `Throwable` renderer at `bootstrap/app.php:80–94` returns a non-null `Response` for **every** Throwable when the request expects JSON or matches `api/*`. Laravel's exception renderer chain runs registered renderers in registration order until one returns a non-null response — so the catch-all wins every time. `Illuminate\Auth\AuthenticationException` extends `Exception`, not `HttpException`, so neither the 401 `UnauthorizedHttpException` renderer (line 51) nor the 403 `AccessDeniedHttpException` renderer (line 60) catches it. The fix is to register an explicit renderer for the exact class **before** the catch-all (registration order matters).

### Decision: Extend `definition()` only — no seeder/concern changes (Slice 07c)

| Option | Tradeoff | Decision |
|--------|---------|----------|
| Override `definition()` in a `UserFactory` subclass and chain it | Adds indirection; every test must opt-in. | Rejected |
| Create a `WithUsername` trait or factory state | Adds indirection for one field. | Rejected |
| **Add `'username' => fake()->unique()->userName()` directly in `definition()`** | Single-line change; `fake()->unique()` produces distinct values for every call in the same process; satisfies the `users.username UNIQUE` constraint; preserves existing factory recipes (`verified()`, `unverified()`). | **Accepted** |

## Cross-references

- Proposal: `sdd/full-user-browser-audit-2026-08-05/proposal` (Engram #332).
- Spec: `sdd/full-user-browser-audit-2026-08-05/spec` (Engram #334).
- Tasks: `sdd/full-user-browser-audit-2026-08-05/tasks` (Engram #335).
- Apply progress (PR5 lessons learned): `sdd/full-user-browser-audit-2026-08-05/apply-progress` (Engram #336) — the source of the three defects addressed here.
- Verify (post-PR6): Engram #337 (PASS WITH CAVEATS, 1 CRITICAL).
- Verify (post-PR7d): Engram #342 (PASS, 0 CRITICAL, 74/74 scenarios).
- Archive report: `sdd/full-user-browser-audit-2026-08-05/archive-report` (this observation).
