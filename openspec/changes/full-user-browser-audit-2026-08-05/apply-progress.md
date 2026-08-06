# Apply Progress — full-user-browser-audit-2026-08-05

> Cumulative apply-progress across all shipped batches. Hybrid artifact store:
> this file mirrors Engram topic `sdd/full-user-browser-audit-2026-08-05/apply-progress`.
> Session preflight: `execution=auto, artifact_store=hybrid, delivery_strategy=auto-chain,`
> `review_budget_lines=400`, chain strategy `stacked-to-main`. Strict TDD active.

## Batch index

| PR | Slice | Scope | Status |
|----|-------|-------|--------|
| PR1 | 01 | Currency `S/` dedup via `formatPENLabel` | Shipped |
| PR2 | 02 | Patient `age` accessor + controller rewire | Shipped |
| PR3/PR4 | 03 | Specialty seeder RED + GREEN | Partial (3b.1–3b.7 pending) |
| PR5 | 04a | Catalog + Reminder module tests | Shipped (2/4 reminder tests RED at the time) |
| PR6 | 07a | `reminder-schedule-write-contract` | Shipped |
| PR7b | 07b | `api-authentication-error-envelope` | Shipped — `8e525d7` |
| **PR7c** | **07c** | **`test-fixture-user-uniqueness`** | **Shipped — this batch** |

---

## PR7a / Slice 07a — Reminder Schedule Write Contract (recap)

Trimmed `ReminderSchedule::$fillable` to the canonical columns (dropped phantom
`type` / `anticipation_hours`, added `hours_before`), deleted `scopeOfType()`
(zero callers), and rewrote three `ReminderService` write sites to
`updateOrCreate` on `(appointment_id, hours_before)`. No migration.

Evidence: `vendor/bin/phpunit -c phpunit.mysql.xml --filter='ReminderScheduleFillableContractTest|ReminderDispatchTest'`
→ 15 tests / 58 assertions, OK. Tasks 7a.1–7a.14 complete.

## PR7b / Slice 07b — AuthenticationException Renderer (recap)

Registered an explicit `Illuminate\Auth\AuthenticationException` render handler
**before** the catch-all `Throwable` handler in `bootstrap/app.php`. Sanctum 401
now returns the canonical 401 JSON envelope with `WWW-Authenticate: Bearer realm="api"`;
web (non-JSON, non-`api/*`) requests still defer to Laravel's default redirect.

Evidence: `AuthenticationEnvelopeTest` 9/9, 33 assertions; focused regression
20/20, 81 assertions; runtime `curl` against `php artisan serve --port=18000`
returned `HTTP 401` + canonical envelope + `WWW-Authenticate`. Tasks 7b.1–7b.10
complete. Commit `8e525d7`.

---

## PR7c / Slice 07c — UserFactory Username (this batch)

**What**: Added `'username' => fake()->unique()->userName()` to
`Database\Factories\UserFactory::definition()` so `User::factory()->create()`
satisfies `users.username varchar(255) NOT NULL UNIQUE` (migration
`2025_09_13_151927_add_username_and_role_to_users_table.php`) under MySQL strict
mode. Added `tests/Unit/Database/UserFactoryContractTest.php` (15 tests) as the
regression guard.

**Why**: PR5 surfaced `SQLSTATE[HY000]: General error: 1364 Field 'username'
doesn't have a default value` on every `RefreshDatabase` test calling
`User::factory()`. Slices 07a and 07b explicitly deferred it (tasks 7a.13, 7b.9).

**Where**:

| File | Action | LOC |
|------|--------|----:|
| `database/factories/UserFactory.php` | Modified — `username` key + docblock naming the migration and the NOT NULL UNIQUE constraint | +6 / -0 |
| `tests/Unit/Database/UserFactoryContractTest.php` | New — 15 contract tests | 220 |
| `openspec/changes/.../tasks.md` | Modified — Phase 4c (cont.) Slice 07c, tasks 7c.1–7c.14 `[x]` | ~+18 |
| `openspec/changes/.../apply-progress.md` | Modified — this cumulative artifact | n/a |

**Authored slice size**: ~226 code lines (220 test + 6 factory) plus artifact
updates. Under the 400-line review budget.

### TDD Cycle Evidence (Strict TDD active)

| Task | Test | Layer | RED (captured) | GREEN (verified) | REFACTOR |
|------|------|-------|----------------|------------------|----------|
| 7c.1 + 7c.2 | `UserFactoryContractTest` (15 tests) | Unit/Database, MySQL harness | `Tests: 15, Assertions: 18, Errors: 5, Failures: 4`. The 9 contract assertions fail with `1364 Field 'username' doesn't have a default value` or on a null/empty `username`. The 6 unchanged-behaviour guards pass — correct shape: guards must be green before AND after. | (none yet) | n/a |
| 7c.3 + 7c.4 | same | same | (same RED) | `Tests: 15, Assertions: 35`, OK — 15/15 green | Width assertion tightened during RED (`assertGreaterThan(0, mb_strlen(...))`) so a null username can no longer pass it vacuously |
| 7c.6 | `tests/Feature` | Feature, MySQL | 147 tests / 63 green / 84 red / 154 `username` errors | 147 tests / 108 green / 39 red / 0 `username` errors | Green-set name diff: **zero** green→red transitions |
| 7c.7 | `tests/Feature/Api` | Feature/Api, MySQL | 54 green / 83 red / 179 assertions | 99 green / 38 red / 288 assertions | zero regressions, +45 newly green |
| 7c.8 | `tests/Unit` | Unit, MySQL | 210 tests / 185 green / 25 red / 44 `username` errors | 225 tests / 200 green / 25 red / 0 `username` errors | same 25 pre-existing red; zero regressions |
| 7c.9 | prior-slice guards | Feature + Unit | n/a | 37/37, 133 assertions, OK | 07a and 07b unaffected |
| 7c.10 | real `artisan tinker` + MySQL | Runtime | n/a | 5 created / 5 distinct / 0 null / maxlen 18 / role `user`; rolled back to 0 rows | — |
| 7c.11 | `db:seed --class=RoleBasedUsersSeeder` | Runtime (production path) | n/a | 15 users, explicit usernames present, 0 null; rolled back | — |

### Work Unit Evidence

| Evidence | Value |
|----------|-------|
| Focused test command + result | `vendor/bin/phpunit -c phpunit.mysql.xml --filter='UserFactoryContractTest' --testdox` → **15/15 GREEN, 35 assertions**. RED before the factory edit: `Errors: 5, Failures: 4`. |
| Broadest regression + result | `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature` → **108 green / 39 red (was 63 / 84)**, 331 assertions (was 222). `tests/Feature/Api` → 99 green / 38 red (was 54 / 83). `tests/Unit` → 200 green / 25 red (was 185 / 25). Green-set name diff shows **zero regressions** in all three suites. |
| Runtime harness + result | Real `php artisan tinker` bootstrap (outside PHPUnit) against MySQL `odontosuite_test`: `User::factory()->count(5)->create()` → 5 rows, 5 distinct usernames (`nikolaus.jaren, graham.keith, robert07, kuphal.darren, kennedy.swaniawski`), 0 null, maxlen 18 ≤ 255, role `user`; transaction rolled back to 0 rows. Production path: `Artisan::call('db:seed', ['--class' => 'RoleBasedUsersSeeder'])` → 15 users with explicit usernames, 0 null, rolled back. |
| Rollback boundary | Revert the 6 added lines in `database/factories/UserFactory.php` and delete `tests/Unit/Database/UserFactoryContractTest.php`. No migration, no schema change, no seeder, no controller, no permission. `User::create(['username' => ...])` fixtures are unaffected either way. |

### Byte-identity guard (production user creation untouched)

`git hash-object`, identical before and after the slice:

| File | Hash |
|------|------|
| `app/Http/Controllers/Api/AuthController.php` | `45df5622825a8db33950933ad7789b77fa7e6f4c` |
| `database/seeders/RoleBasedUsersSeeder.php` | `3141c499ee0882148c3ceb88cc5f58b5120205ee` |
| `app/Http/Controllers/Api/UserController.php` | `faac40eebe524fe97602e3dca7d5ce5047089bdc` |

`git diff --numstat` production side: `6  0  database/factories/UserFactory.php` — nothing else.

### Deviations

1. **Spec names two artifacts that do not exist.** `test-fixture-user-uniqueness/spec.md` requires `AuthController::register` and `database/seeders/UserSeeder.php` to stay byte-identical. `AuthController` has only `login`, `logout`, `me`, `forgotPassword`, `resetPassword` — there is no `register`. `database/seeders/UserSeeder.php` does not exist (legacy user seeders live under `database/seeders/_legacy/`). The real production user-creation paths are `UserController::store()` (`User::create($validated)`, line 69) and `RoleBasedUsersSeeder` (`User::firstOrCreate`). The spec's INTENT was honoured by guarding the paths that actually exist.
2. **Test file name.** The spec's scenario 4 names the regression test `UserFactoryUsernameTest`; the design names it `tests/Unit/Database/UserFactoryContractTest.php`. The design name was used (single class, no duplicated coverage). `--filter='UserFactory'` matches either intent.
3. **Test placed in `tests/Unit/Database` but uses `RefreshDatabase`.** The design specifies this path and describes it as "model factory in-memory + persisted uniqueness", so the DB-touching assertions live in the Unit tree by design. Unlike `ReminderScheduleFillableContractTest` (pure unit, SQLite-safe), this one requires a real DB and therefore runs under `phpunit.mysql.xml`.
4. **Docblock added beyond the design's `~+1` LOC estimate.** The design predicted a one-line change; 5 docblock lines were added so a future reader sees why the key is mandatory. Behaviourally still a one-key change.

### Issues found (out of scope, filed as follow-ups)

1. **`patients.first_name` has no default and no working factory path.** With the `username` blocker removed, `AppointmentTest` / `CalendarServiceTest` now fail on `SQLSTATE[HY000]: 1364 Field 'first_name' doesn't have a default value` — the insert emits only `is_active` + timestamps. Same class of defect as slice 07c, one table over. Candidate slice 07d.
2. **`AppointmentServiceTest` constructor arity.** `tests/Unit/Services/AppointmentServiceTest.php:25` instantiates `new AppointmentService()` but `AppointmentService::__construct()` requires 1 argument. Pre-existing test bug, unrelated to this slice.
3. **`apply-progress.md` was committed empty (0 bytes) in `8e525d7`.** The PR7a/PR7b content existed only in Engram #336. This batch restores the filesystem mirror with a cumulative recap so the hybrid store is durable on both sides.
4. **`AuditLogMigrationTest::test_migration_source_anchors_on_existing_user_agent_column`** remains red — pre-existing string-stripping bug, unchanged by this slice (already documented in 7a.12 and 7b.10).
5. **Uncommitted working-tree changes predate this batch.** `PatientController`, `PatientResource`, `OralSurgeryRecord`, `RehabilitationRecord`, `ReminderSchedule`, `ReminderService`, several seeders and Vue files were already modified before this batch started. They were deliberately NOT staged; this commit contains only slice 07c files.

### Remaining tasks (change-wide)

- [ ] 3b.1–3b.7 Specialty seeder GREEN rewrite (Phase 3)
- [ ] 4b.1–4b.3 BI / Specialty round-trip / Cash-close module tests (Phase 4)
- [ ] 5.1–5.4 Cleanup and final whole-suite run (Phase 5)
