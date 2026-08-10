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

**Auth threat boundary preservation**:

- Bearer tokens still required for `auth:sanctum` routes (`routes/api.php:69`, `:77`).
- Token-mismatch and missing-token paths both raise `AuthenticationException` through Sanctum — both will hit the new renderer.
- Session/cookie web requests (HTML pages) on routes guarded by `web` + `auth` continue to redirect to `route('login')` via Laravel's `Authenticate::redirectTo()` because the renderer returns `null` for non-JSON, non-`api/*` requests.
- Rate limiting (`throttle.login`, `throttle:3,10`, `throttle:5,10`) untouched.
- CSRF middleware for web routes untouched.
- No new public endpoint, no new anonymous surface.

### Decision: Extend `definition()` only — no seeder/concern changes (Slice 07c)

| Option | Tradeoff | Decision |
|--------|---------|----------|
| Override `definition()` in a `UserFactory` subclass and chain it | Adds indirection; every test must opt-in. | Rejected |
| Create a `WithUsername` trait or factory state | Adds indirection for one field. | Rejected |
| **Add `'username' => fake()->unique()->userName()` directly in `definition()`** | Single-line change; `fake()->unique()` produces distinct values for every call in the same process; satisfies the `users.username UNIQUE` constraint; preserves existing factory recipes (`verified()`, `unverified()`). | **Accepted** |

**Rationale**: The factory's responsibility is to produce a model that satisfies the migration contract. Once `username` is part of `definition()`, every `User::factory()->create([...])` call becomes valid against MySQL strict mode, including the 22 existing `User::factory()` call sites across `tests/Feature` and `tests/Unit`. `Faker\\Provider\\Internet::userName()` emits ASCII strings ≤ 32 chars, well within `varchar(255)`. `unique()` is reset between test processes and respects Laravel's `Factory::reset()` calls in setUp/tearDown.

**Factory conservation**: No existing call site that overrides `username` will lose behaviour — `factory()->create(['username' => 'x'])` still wins.

## Data Flow

### Slice 07a — Reminder correction

```
ReminderDispatchTest::test_24h_reminder_creates_one_schedule_at_minus_one_hour
  -> ReminderService::scheduleReminder($appt, '24h', 24)
  -> ReminderTemplate::where(type='24h')->active()->first()       (unchanged)
  -> scheduledAt = $appt->scheduled_at - 24h                       (unchanged)
  -> ReminderSchedule::create([
       appointment_id        => $appt->id,
       reminder_template_id  => $template->id,
       scheduled_at          => $scheduledAt,
       status                => 'pending',
       hours_before          => 24,         // was: 'type' (bogus) + 'anticipation_hours' (bogus)
     ])
  -> INSERT INTO reminder_schedules (..., hours_before, scheduled_at, status)
     VALUES (..., 24, ?, 'pending')                                // GREEN
```

`ReminderSchedule::scopeOfType($query, string $type)` is **deleted** (queries the non-existent `type` column). Verified by grep — no callers in `app/`, `tests/`, or `database/`.

`ReminderService::cancelReminders / rescheduleReminders / getReminderStats / processDueReminders / cleanupOldReminders / getUpcomingReminders` continue to work because they filter by `status`, `sent_at`, `scheduled_at` — all real columns.

### Slice 07b — Auth envelope

```
GET /api/auth/me  (no Bearer token, X-Requested-With: XMLHttpRequest, Accept: application/json)
  -> Sanctum guard returns null user
  -> Authenticate middleware calls unauthenticated()
  -> unauthenticated() throws Illuminate\\Auth\\AuthenticationException
  -> bootstrap/app.php render chain (in registration order):
     1. ValidationException                       -> no match
     2. ModelNotFoundException                    -> no match
     3. NotFoundHttpException                     -> no match (different class)
     4. UnauthorizedHttpException                 -> no match (different class)
     5. AccessDeniedHttpException                 -> no match (different class)
     6. HttpException 403                         -> no match
     7. NEW \\Illuminate\\Auth\\AuthenticationException  -> MATCH
        -> $request->expectsJson() is true
        -> return json({message: 'No autenticado.', error: null}, 401)
                  ->header('WWW-Authenticate', 'Bearer realm="api"')
        -> CHAIN STOPS
  -> SPA receives the 401 canonical envelope.
```

Web route (HTML, no `Accept: application/json`):

```
GET /dashboard  (session cookie missing, Accept: text/html)
  -> Authenticate middleware calls unauthenticated() (web guard)
  -> new AuthenticationException renderer:
     -> $request->expectsJson() is false, $request->is('api/*') is false
     -> return null  (defer)
  -> catch-all Throwable renderer:
     -> $request->expectsJson() is false
     -> return null  (defer to Laravel default)
  -> Laravel default AuthenticationException::toResponse redirects to route('login')
  -> Browser gets 302 to /login.
```

### Slice 07c — UserFactory username

```
UserFactory::definition()
  -> return [
       name              => fake()->name(),
       email             => fake()->unique()->safeEmail(),
       email_verified_at => now(),
       password          => static::$password ??= Hash::make('password'),
       remember_token    => Str::random(10),
       username          => fake()->unique()->userName(),   // NEW
     ];

User::factory()->create([role: 'administrador'])
  -> INSERT INTO users (name, email, ..., username, role, ...)
     VALUES (..., 'jsmith42', 'administrador', ...)                 // GREEN
```

The `fake()->unique()` modifier guarantees no duplicate within a single process; tests run inside `RefreshDatabase`, so the previous-process singleton is gone between tests.

## File Changes

| File | Action | LOC est. | Slice |
|------|--------|---------:|------:|
| `app/Models/ReminderSchedule.php` | Modify — drop `type` and `anticipation_hours` from `$fillable`; drop `scopeOfType()`. Add docblock noting the canonical column is `hours_before`. | ~-12 | 07a |
| `app/Services/ReminderService.php` | Modify — replace `'type' => $type, 'anticipation_hours' => $hoursBefore` with `'hours_before' => $hoursBefore` in three call sites (`scheduleReminder` L62, `createCustomReminder` L212, `sendImmediateReminder` L234). | ~-3 | 07a |
| `tests/Unit/Models/ReminderScheduleFillableContractTest.php` | New — asserts `$fillable` matches the union of columns declared by the three migrations touching `reminder_schedules`; asserts the service source writes `hours_before` and not the phantom columns. Mirrors `SpecialtyRecordSeederFieldContractTest` parser-based pattern. | ~120 | 07a |
| `tests/Feature/Modules/ReminderDispatchTest.php` | Modify — replace the direct `User::create(...)` workaround helpers with `User::factory()->create([role: 'odontologo'])` (now safe after 07c); tighten `test_24h_reminder_...` to also assert `hours_before == 24`; tighten `test_redispatch_does_not_duplicate_reminder` the same way. | ~+20 | 07a |
| `bootstrap/app.php` | Modify — insert one new `$exceptions->render(\\Illuminate\\Auth\\AuthenticationException::class, ...)` block between the existing `AccessDeniedHttpException` block (line 67) and the 403 `HttpException` block (line 70), so it short-circuits the catch-all `Throwable`. Body: `[if JSON/api] return 401 canonical JSON with `WWW-Authenticate: Bearer` header; [else] return null to defer to Laravel's web redirect`. | ~+18 | 07b |
| `tests/Feature/Api/AuthenticationEnvelopeTest.php` | New — covers (1) `GET /api/auth/me` no-bearer → 401 + canonical envelope + `WWW-Authenticate: Bearer`; (2) `GET /api/patient` with invalid token → 401 + canonical envelope; (3) HTML request missing session on a web route → Laravel's default 302 redirect to `/login`. | ~90 | 07b |
| `tests/Feature/Modules/CatalogFilterTest.php` | Modify — tighten `test_unauthenticated_request_is_rejected_with_401` to assert exact 401 + envelope + `WWW-Authenticate` (currently lenient `assertGreaterThanOrEqual(400)`). | ~+5 | 07b |
| `tests/Feature/Api/PatientControllerAgeTest.php` | Modify — tighten the unauthenticated assertion the same way (currently lenient). | ~+5 | 07b |
| `database/factories/UserFactory.php` | Modify — add `'username' => fake()->unique()->userName()` to `definition()`. | ~+1 | 07c |
| `tests/Unit/Database/UserFactoryContractTest.php` | New — asserts: `User::factory()->make()->username` is non-null + ≤ 255 chars; `User::factory()->create()` succeeds without DB exception; `User::factory()->count(5)->create()->pluck('username')->unique()->count() === 5`; `unverified()` state still emits a username. | ~80 | 07c |
| `phpunit.mysql.xml` | No change (existing harness for MySQL strict mode continues to catch 07a / 07b failures). | 0 | — |
| `openspec/changes/full-user-browser-audit-2026-08-05/design.md` | Modify (this file) — adds Slice 07; prior slices 01–06 unchanged. | n/a | — |
| `openspec/changes/full-user-browser-audit-2026-08-05/specs/module-validation-tests/spec.md` | Add a new requirement `auth-envelope` describing the canonical 401 shape (Slice 07b). | ~+30 | 07b |

**Per-sub-slice authored line totals**: 07a ≈ 135, 07b ≈ 168, 07c ≈ 81. Each sub-slice lands well under 400 lines.

## Interfaces / Contracts

### ReminderSchedule fillable after Slice 07a

```php
protected $fillable = [
    'appointment_id',
    'reminder_template_id',
    'hours_before',          // canonical per migration 2025_09_20
    'scheduled_at',
    'sent_at',
    'channel',
    'status',
    'error_message',
];
```

Assertions enforced by `ReminderScheduleFillableContractTest`:

```php
// 1. Every key in $fillable is a real column.
$declared = (new ReminderSchedule)->getFillable();
$expected = ['appointment_id', 'reminder_template_id', 'hours_before', 'scheduled_at',
             'sent_at', 'channel', 'status', 'error_message'];
$this->assertEqualsCanonicalizing($expected, $declared);

// 2. Service writes a real column.
public function test_service_writes_hours_before_not_anticipation_hours(): void
{
    $source = file_get_contents(base_path('app/Services/ReminderService.php'));
    $this->assertStringNotContainsString("'anticipation_hours' =>", $source,
        'ReminderService must not write the phantom anticipation_hours column.');
    $this->assertStringContainsString("'hours_before' =>", $source,
        'ReminderService must write hours_before, the canonical column.');
    $this->assertStringNotContainsString("'type' =>", $source,
        'ReminderSchedule no longer carries a type column; remove the redundant write.');
}

// 3. Scope ofType is gone.
$this->assertFalse(method_exists(ReminderSchedule::class, 'scopeOfType'),
    'scopeOfType queried the removed type column and must be deleted.');
```

### AuthenticationException renderer

```php
// bootstrap/app.php — inserted between line 67 and line 70
$exceptions->render(function (\\Illuminate\\Auth\\AuthenticationException $e, \\Illuminate\\Http\\Request $request) {
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json([
            'message' => 'No autenticado.',
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], 401)->header('WWW-Authenticate', 'Bearer realm="api"');
    }
    // HTML requests: defer to Laravel's default redirect (Authenticate::redirectTo -> route('login')).
});
```

Order in the file matters: this block sits BEFORE the catch-all `Throwable` renderer at `bootstrap/app.php:80` so the chain short-circuits at 401 instead of falling through to 500. The 401 envelope matches the existing `UnauthorizedHttpException` envelope at `bootstrap/app.php:51–58` exactly, so JSON consumers keyed on `message` continue to work.

### UserFactory username after Slice 07c

```php
public function definition(): array
{
    return [
        'name'              => fake()->name(),
        'email'             => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password'          => static::$password ??= Hash::make('password'),
        'remember_token'    => Str::random(10),
        'username'          => fake()->unique()->userName(), // NEW
    ];
}
```

## Testing Strategy

| Layer | File | Approach | TDD colour |
|-------|------|----------|------------|
| Unit | `tests/Unit/Models/ReminderScheduleFillableContractTest.php` (NEW) | Reflection on model + grep on service source; no DB. | RED → GREEN on Slice 07a |
| Feature | `tests/Feature/Modules/ReminderDispatchTest.php` (EXTEND) | Re-run the 4 scenarios that surfaced the defect; add `hours_before` assertion. | RED 2/4 → GREEN 4/4 on Slice 07a |
| Feature | `tests/Feature/Api/AuthenticationEnvelopeTest.php` (NEW) | 401 canonical body + `WWW-Authenticate` header; HTML redirect preserved. | RED → GREEN on Slice 07b |
| Feature | `tests/Feature/Modules/CatalogFilterTest.php` (TIGHTEN) | Existing `test_unauthenticated_...` asserts `>=400`; tighten to exact 401 + envelope + header. | Already GREEN; precise assertion |
| Feature | `tests/Feature/Api/PatientControllerAgeTest.php` (TIGHTEN) | Same tightening as above. | Already GREEN; precise assertion |
| Unit | `tests/Unit/Database/UserFactoryContractTest.php` (NEW) | Model factory in-memory + persisted uniqueness. | RED → GREEN on Slice 07c |
| Integration | `php artisan test` full suite | Confirms no regression in 138 prior fixes. | GREEN end-to-end |

Per-slice test command:

- 07a: `php artisan test --filter='ReminderScheduleFillableContractTest|ReminderDispatchTest'`
- 07b: `php artisan test --filter='AuthenticationEnvelopeTest|ReminderDispatchTest|CatalogFilterTest|PatientControllerAgeTest'`
- 07c: `php artisan test --filter='UserFactoryContractTest'` then full `php artisan test` to confirm no existing factory test broke.

TDD strictness:

- Each RED test MUST fail with a message that names the exact offender (column name, renderer class, factory field).
- Each GREEN step MUST end with `php artisan test --filter=<slice>` all green; the full suite is re-run only after all three slices are green.
- `markTestSkipped` is forbidden — every documented scenario must pass or the slice is blocked.

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary changes. The exception renderer is HTTP-layer only. The model fillable correction is PHP-only. The factory extension is database-only.

## Migration / Rollout

**No data migration. No schema migration.** All three changes are within the existing schema and configuration envelope.

**Feature flags**: None.

**Backwards compatibility**:

- 07a: `ReminderSchedule` rows produced after PR5 carry phantom `type` and `anticipation_hours` values; these inserts failed at the DB layer (no rows persisted) so there are zero post-PR5 rows with these columns. After 07a, every new INSERT writes only real columns. Production has zero historical rows affected because the columns never existed. **Idempotent** by construction.
- 07b: existing 401 responses (`UnauthorizedHttpException` at `bootstrap/app.php:51`) already return `{message: 'No autenticado.', error: ...}`. The new renderer uses the exact same body shape. JSON consumers keyed on `message` are unaffected.
- 07c: every existing `User::factory()->create([...])` call gains a `username` value. The only behaviour delta is positive: those calls stop throwing `MassAssignmentException`-adjacent errors.

**Rollout (chained PRs under auto-chain)**:

| PR | Sub-slice | Files | Approx LOC | Risk |
|----|----------|-------|-----------:|------|
| 1 | currency dedup (prior, SHIPPED) | 2 Vue | ~10 | Low |
| 2 | patient age (prior, SHIPPED) | 1 PHP + 2 Vue | ~40 | Low |
| 3 | seeder RED (prior, SHIPPED) | 1 test | ~80 | Low |
| 4 | seeder GREEN (prior, SHIPPED) | 2 seeders + chain | ~170 | Med |
| 5 | PR5 module tests A (prior, partial — 2/4 reminder tests RED) | 2 tests | ~364 | Med |
| **6** | **Slice 07a RED+GREEN combined** | `ReminderSchedule.php`, `ReminderService.php`, +1 test, tighten 1 test | **~135** | **Low** |
| **7a** | **Slice 07b RED+GREEN combined** | `bootstrap/app.php`, +1 test, tighten 2 tests, spec amend | **~168** | **Low (renderer ordering preserved)** |
| **7b** | **Slice 07c RED+GREEN combined** | `UserFactory.php`, +1 test | **~81** | **Low** |

PR 6, 7a, 7b are well under the 400-line budget. Per the orchestrator's `delivery_strategy=auto-chain`, they may be stacked-to-main or chained off PR5 in the order 07a → 07b → 07c.

**Rollback per sub-slice**:

- 07a: revert `ReminderSchedule::$fillable` and the three `ReminderService` call sites. The contract test reverts with it. No DB schema change to revert.
- 07b: revert the `AuthenticationException` renderer block in `bootstrap/app.php`. Web redirect is preserved at all times; the test reverts with the renderer. The catch-all `Throwable` returns 500 again — same pre-PR5 behaviour.
- 07c: revert the `username` field in `UserFactory::definition()`. Any test that uses `User::factory()` reverts to the pre-PR5 broken state (exactly what PR5 apply-progress documented). The contract test reverts.

Each rollback is a single commit; no chained migrations to undo; no data loss.

## Open Questions

- [ ] Does any feature flag or test currently call `ReminderSchedule::scopeOfType`? Verified by grep → no callers in `app/`, `tests/`, `database/`. Deletion is safe.
- [ ] After 07b, does any external non-SPA API client depend on a `Content-Type: text/html` 401 body? The renderer only changes JSON / `api/*` requests; HTML requests still get Laravel's default redirect. No risk identified.
- [ ] After 07c, do any current tests deliberately rely on `User::factory()` failing to insert `username`? Verified → no such tests exist. Every `User::factory()` call site either omits `username` (now gets a fresh one) or asserts the factory succeeds.
- [ ] Should `07a` keep the `ReminderService` $logger call lines untouched? Yes — only the INSERT payload changes; logging is unrelated.
- [ ] Does `PatientControllerAgeTest` currently run against MySQL or SQLite? The pre-PR5 evidence shows it ran RED on MySQL with `>=400` leniency. After 07b it tightens to strict 401; verified safe to run.

## Reviewer-Ready Slices (under 400 changed lines)

| Slice | PR | Files | LOC est. | Risk |
|-------|----|-------|---------:|------|
| 07a | 6 | 2 PHP (model + service) + 1 new test + 1 tightened test | ~135 | Low |
| 07b | 7a | 1 PHP (bootstrap) + 1 new test + 2 tightened tests + spec amendment | ~168 | Low |
| 07c | 7b | 1 PHP (factory) + 1 new test | ~81 | Low |

`400-line budget risk: Low` per `sdd-phase-common.md` §E.
Chained PRs recommended: Yes (continues the existing PR5 chain into PR6 → PR7).
Chain strategy: stacked-to-main.

## Cross-references

- Proposal: `sdd/full-user-browser-audit-2026-08-05/proposal` (Engram #332).
- Spec: `sdd/full-user-browser-audit-2026-08-05/spec` (Engram #334).
- Tasks: `sdd/full-user-browser-audit-2026-08-05/tasks` (Engram #335).
- Apply progress (PR5 lessons learned): `sdd/full-user-browser-audit-2026-08-05/apply-progress` (Engram #336) — the source of the three defects addressed here.
