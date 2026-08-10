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
| PR3/PR4 | 03 | Specialty seeder RED + GREEN | Shipped (seeder code GREEN; see follow-up §) |
| PR5 | 04a | Catalog + Reminder module tests | Shipped (was 2/4 reminder tests RED at PR5 ship; whole slice GREEN post-07a) |
| PR6 | 07a | `reminder-schedule-write-contract` | Shipped |
| PR7b | 07b | `api-authentication-error-envelope` | Shipped — commit `8e525d7` |
| PR7c | 07c | `test-fixture-user-uniqueness` | Shipped — commit `66011f0` |
| PR6 (4b) | 04b | Module validation tests B (BI + Specialty + Cash) | Shipped — commit `4274ced` |
| PR7d | 07d | Pagination-meta test phone-unique collision fix | Shipped — commits `3965278` + `49c0b47` |

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

Cumulative regression (PR5 + 07a + 07b + 07c): 0 `username` errors, 0 green→red transitions.

---

## PR7d / Slice 07d — Pagination-Meta Test Phone-Unique Collision (this batch, 7d.1–7d.9)

**What**: Appended `uniqid()` suffix to the phone string in
`PatientControllerAgeTest::seedAdultPatient()` and
`seedPatientWithoutBirthDate()` so each helper invocation produces a
unique phone, closing verify-report-post-pr6 CRITICAL C1. The 20-row
bulk insert in `test_index_preserves_pagination_meta_envelope` was
tripping `patients_phone_unique` because both helpers hardcoded the
same phone value. Production `PatientController`, `PatientResource`,
and `Patient` model stay byte-identical.

**Why**: Verify-report #337 (post-PR6) marked the pagination-meta
Feature test as RED under MySQL harness (1 error / 70 + 1 in the
focused suite) despite the production behaviour being correct per live
curl evidence. The defect was test-infrastructure only — same class as
PR7c (UserFactory username), one table over.

**Where**:

| File | Action | LOC |
|------|--------|----:|
| `tests/Feature/Api/PatientControllerAgeTest.php` | Modified — `uniqid()` suffix on the phone value in both helpers + 4-line docblock on `seedAdultPatient` naming the slice | +5 effective lines (file is now 307 lines; the rest of the file was untracked prior to this slice, so the slice authored delta is +5) |
| `openspec/changes/.../tasks.md` | Modified — Phase 4c (cont.) Slice 07d, tasks 7d.1–7d.9 `[x]` | +18 / -1 |
| `openspec/changes/.../apply-progress.md` | Modified — this section | n/a |

**Authored slice size**: 5 effective code lines. By far the smallest
slice in the change; under the 400-line review budget.

### TDD Cycle Evidence (Strict TDD active)

| Task | Test | Layer | RED (captured) | GREEN (verified) | REFACTOR |
|------|------|-------|----------------|------------------|----------|
| 7d.1 + 7d.2 | `PatientControllerAgeTest::test_index_preserves_pagination_meta_envelope` | Feature, MySQL harness | `UniqueConstraintViolationException: 1062 Duplicate entry '+51 987 654 321' for key 'patients_phone_unique'` on `seedAdultPatient('Bulk1', …)` (the second of 20 calls) | `Tests: 1, Assertions: 9, OK` — pagination-meta test now green | n/a |
| 7d.3 | defensive: `seedPatientWithoutBirthDate()` | Feature, MySQL harness | n/a (no test bulk-seeds it) | Applied `uniqid()` suffix defensively so future bulk seeding cannot collide | n/a |
| 7d.4 + 7d.5 | full `PatientControllerAgeTest` | Feature, MySQL harness | n/a | `Tests: 8, Assertions: 43, OK` — all 8 scenarios green; the other 7 unaffected | n/a |
| 7d.6 | cumulative 12-class focused suite | Feature + Unit, MySQL harness | n/a | `Tests: 93, Assertions: 403, OK` (was 70 passes + 1 error = 71 in verify-report) | n/a |
| 7d.7 | prior-slice guard (`UserFactoryContractTest\|AuthenticationEnvelopeTest\|ReminderDispatchTest\|ReminderScheduleFillableContractTest\|CatalogFilterTest`) | Feature + Unit, MySQL harness | n/a | `Tests: 37, Assertions: 133, OK` — slices 07a/07b/07c/4a/4b unaffected | n/a |

### Byte-identity guard (production patient handling untouched)

`git hash-object`, identical before and after the slice:

| File | Hash |
|------|------|
| `app/Http/Controllers/Api/PatientController.php` | `13dcab2071a04c4fadde6252dc69fb1553a47538` |
| `app/Http/Resources/PatientResource.php` | `0403de6835a9ceb430edc161d4f5734088f40266` |
| `app/Models/Patient.php` | `a26d4e6bf5f04ee7c07d7390202a9120913ece7b` |

`git diff --stat` (post-commit): the only file in this slice is
`tests/Feature/Api/PatientControllerAgeTest.php`. Production
byte-identical.

### Native review ledger — NOT settled

`gentle-ai review mode status` → **`receipt-driven development: off (decided by global)`**. `gentle-ai review status --contract gentle-ai.review-integration/v2 --next-transition` reports `applicability: unrelated`, `receipt.status: not_applicable`. The global kill switch is OFF (user-owned). No review was started, no receipt exists. Delivery reports **`disabled/unmanaged`** per ordinary repository policy. **This is NOT an approval** — the user's instruction explicitly says to record the blocker and proceed with commit + apply-progress so the work is durable, and to NOT claim ledger success.

### Issues found (out of scope, filed as follow-ups)

1. **C1 closed by this slice.** The verify-report-post-pr6 CRITICAL C1 (pagination-meta Feature test RED on `patients_phone_unique`) is now GREEN. Spec scenario "Pagination meta envelope preserved" is proven at the Feature layer.
2. **C1 closure does not close the Phase 3b tasks.md drift.** Phase 3b tasks 3b.1–3b.7 remain `[ ]` on disk despite the seeder code being GREEN (per verify-report S1). Out of scope for this slice.
3. **Phase 5 cleanup checklist** (4 boxes) still un-checked; non-blocking.
4. **Native review ledger OFF** — no review receipt exists for any of the 10 PRs.
5. **`AppointmentServiceTest` constructor arity** (`tests/Unit/Services/AppointmentServiceTest.php:25` instantiates `new AppointmentService()` against a 1-arg constructor) — pre-existing test bug, unrelated to this slice.
6. **`AuditLogMigrationTest::test_migration_source_anchors_on_existing_user_agent_column`** remains red — pre-existing string-stripping bug, unchanged by this slice.

### Remaining tasks (change-wide)

- [x] 3b.1–3b.7 Specialty seeder GREEN rewrite (Phase 3) — **seeder code GREEN per verify-report**; filesystem tasks.md displayed `[ ]` for tasks 3b.1–3b.7 (drift, see #2 above). The change closes with this drift documented, not silently resolved.
- [ ] 5.1–5.4 Cleanup and final whole-suite run (Phase 5) — non-blocking documentation hygiene; the verify-report post-PR7d (#342) confirms all production surfaces are GREEN.

### Next recommended

`sdd-archive` — close the change. All 7 requirements and 74/74 scenarios are proven at the Feature layer per verify-report-post-pr7d (#342). The only outstanding drift (Phase 3b tasks.md checkboxes) is a follow-up not blocking the archive. Native review ledger stays disabled/unmanaged per global kill switch.
