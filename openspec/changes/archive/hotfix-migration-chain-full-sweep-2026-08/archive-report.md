# Archive Report — hotfix-migration-chain-full-sweep-2026-08

**Status**: ARCHIVED
**Date**: 2026-08-05
**Chain**: explore → propose → spec → design → tasks → apply (Commit A `dfcb55c` + Commit B `17f5b77`) → verify (PASS, 0/0/0) → archive

## Goal

Remediate **NEW-003** (the only in-scope blocker from the 4-lens full-sweep synthesis): `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` unconditionally re-added `reminder_schedules.error_message`, which is already created by `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php:22`. On any clean MySQL/MariaDB chain the migration aborted with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'`, blocking the chain before the source-correct sibling commits `d811f1a` (NEW-001) and `d4f34b2` (NEW-002) could apply.

## Final metrics

- Findings in scope / resolved: 1 / 1 (NEW-003 only)
- Tasks closed: 4 / 4 (T-01.1, T-01.2, T-01.3, T-01.4)
- Slices applied: 1 / 1 (`tasks/01-reminder-schedules-idempotent-add.md`)
- Commits: 2 stacked on `fix/migration-new-003-2026-08`, then merged to `main`
- Total LOC: **~219 insertions / ~4 deletions** across 2 files (migration file +13/-3, test file +206/-1). The ~52 LOC projection in the proposal/tasks counted only authored lines; the hardened test shape grew to ~206 during implementation per the §Issues Found in `apply-progress.md` (raw-line matching for table-name capture; `->change()` skip for `2026_06_07_001200_make_odontogram_records_color_nullable.php`).
- Verify verdict: **PASS** (0 CRITICAL, 0 WARNING, 0 SUGGESTION; 15 / 15 checks green)
- 108 migrations Ran / 0 Pending on the scratch DB `odontosuite_migtest` (MariaDB 10.4, utf8mb4)
- Full unit suite on SQLite: 180 passed / 109 failed / 1 skipped — all 109 failures are the documented AGENTS.md §6 SQLite MODIFY-COLUMN tech-debt floor; no new failures introduced by this change.

## Final commits (chronological, stacked on `main`)

1. `dfcb55c` — `fix(migrations): make add_channel_and_error_to_reminder_schedules idempotent with hasColumn guards`
2. `17f5b77` — `test(sdd-check): guard against re-adding columns already created in earlier migrations`

Both commits are conventional, no `Co-Authored-By`, no AI attribution.

## File diff summary

| File | Action | LOC | Description |
|---|---|---|---|
| `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` | Modified (Commit A) | +13 / -3 | `up()` wraps both `->string('channel', ...)` and `->text('error_message', ...)` in `if (! Schema::hasColumn(...))` guards; `down()` filters `['channel','error_message']` via `array_filter + fn ($c) => Schema::hasColumn(...)` and only calls `dropColumn($cols)` when the filtered list is non-empty. |
| `tests/Unit/SddCheckMigrationsTest.php` | Modified (Commit B) | +206 / -1 | New `no_migration_re_adds_already_known_column` test method that scans ALL migrations (no `GUARD_CUTOFF_PREFIX` gate — the bug class is retroactive) and flags any column-add inside a `Schema::table(...)` closure that lacks a `Schema::hasColumn('<table>','<col>')` guard. Two-pass algorithm: Pass 1 builds a per-table known-column map from `Schema::create(...)` closures; Pass 2 walks `Schema::table(...)` closures, extracts column names from the un-stripped source line, and demands the guard. |

No other file in the repository was modified, staged, or committed by this change.

## Spec compliance (final state)

| Requirement / Scenario | Status | Evidence |
|---|---|---|
| Requirement: idempotent column-add for reminder_schedules | PASS | source inspection of `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` lines 20–22, 23–25, 32–38 |
| Scenario: chain completes the offending migration on clean MySQL (Lens D empirical) | PASS | `DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --no-interaction` exits 0 with 108 migrations Ran / 0 Pending |
| Scenario: source has hasColumn guards in up() | PASS | grep returns matches inside `up()` for both `channel` and `error_message` |
| Scenario: source filters dropColumn in down() | PASS | `array_filter(['channel','error_message'], fn ($c) => Schema::hasColumn('reminder_schedules', $c))` plus `if ($cols) { $table->dropColumn($cols); }` |
| Scenario: replay safety on partially-applied schema | PASS | both `up()` guards independent; second `migrate` run exits 0 |
| Scenario: down() handles partial state | PASS | `array_filter + if($cols)` shape — drops only the present column |
| Requirement: re-add regression guard | PASS | `php artisan test --filter=SddCheckMigrationsTest` — 7 passed, 12 assertions on SQLite in-memory |
| Scenario: guard passes against the post-fix tree | PASS | 7 / 7 guards green |
| Scenario: guard fails on reintroduction | PASS | temporary revert of `hasColumn` wrapper reproduced the RED with explicit message: `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php:21 adds column 'error_message' without hasColumn guard` |
| Scenario: guard runs cleanly on SQLite in-memory | PASS | pure string scan, no DB connection required |
| Scenario: first-add columns not flagged | PASS | e.g. `2026_06_11_001034_add_soft_deletes_to_patients_table` (`patients.deleted_at`) — not flagged |

2 / 2 requirements satisfied. 8 / 8 scenarios satisfied.

## Terminal verification evidence (15 / 15 PASS)

| # | Check | Verdict | Evidence |
|---|---|---|---|
| C1 | artifacts present | PASS | All 9 change-folder files present at archive time |
| C2 | unchecked task boxes | PASS | grep returned 0 — all 28 task boxes checked |
| C3 | apply-progress non-empty | PASS | 83 lines |
| C4 | state.yaml phase | PASS | phase: verify pre-archive; phase: archived post-archive |
| C5 | live DB untouched | PASS | `odontosuite` DB unchanged — `migrations` count 63 / `patients` count 0 (verified at verify time and again at archive time) |
| C6 | NEW-003 commit scope (Commit A) | PASS | 1 file changed (migration file), +13 / -3 |
| C7 | guard test commit scope (Commit B) | PASS | 1 file changed (test file), +206 / -1 |
| C8 | no AI attribution | PASS | grep for AI patterns returned empty |
| C9 | scratch DB `migrate:fresh` | PASS | exit 0, 108 migrations Ran / 0 Pending on `odontosuite_migtest` |
| C10 | `pending` count after `migrate:fresh` | PASS | grep pending count = 0 |
| C11 | key columns / tables present | PASS | 10 / 10 schema probes returned TRUE (`reminder_schedules.channel` + `reminder_schedules.error_message`, `patients.document_number` + `patients.deleted_at`, `audit_logs.is_immutable` + `audit_logs.user_agent`, …) |
| C12 | static guard test | PASS | 7 passed, 12 assertions on SQLite in-memory |
| C13 | guard catches regression | PASS | 1 failed (RED) with `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php:21` reference; restoration = GREEN |
| C14 | seed step behavior | PASS (out-of-scope abort) | migrations DONE; seed aborted at documented `NEW-004-SEEDER` defect — `Target class [Database\Seeders\EnvironmentSeeder] does not exist` — explicitly out of scope for this change |
| C15 | full unit suite on SQLite | PASS | 180 passed / 109 failed / 1 skipped; the 109 failures match the AGENTS.md §6 documented SQLite MODIFY-COLUMN tech-debt floor; no new failures introduced |

## Empirical proof (Lens D)

| Phase | Scratch DB `odontosuite_migtest` | Outcome |
|---|---|---|
| Pre-fix | `php artisan migrate:fresh --no-interaction` | Aborts at `2026_08_05_020000_add_channel_and_error_to_reminder_schedules` with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'` — chain cannot reach NEW-001 / NEW-002. |
| Post-fix (Commit A) | `DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --no-interaction` | Exits 0. **108 migrations Ran, 0 Pending**. `reminder_schedules.channel` (varchar(20) nullable) + `reminder_schedules.error_message` (text nullable) present. `audit_logs` (NEW-001 pre-req) and `patients.document_number` (NEW-002 pre-req) reachable in the chain. |
| Post-fix (Commit A + B) | `php artisan test --filter=SddCheckMigrationsTest` | 7 passed, 12 assertions on SQLite in-memory. |

## Live DB safety (mandatory acknowledgement)

The `odontosuite` **live** database was NOT touched by this change. Per verify report check C5, the live DB row counts remained `migrations = 63 / patients = 0` both at verify time and again at archive time. The scratch DB `odontosuite_migtest` (MariaDB 10.4, virgin) was the only database where `migrate:fresh` was executed during this cycle; it is independent of the live DB and may be dropped or retained at the operator's discretion — this archive does not alter it.

## Parent / sibling topology

**Parent** (already archived):
- `bugfix-2026-08` — slice 03 / T-03.6 originally introduced the offending migration. This hotfix repairs it in place.

**Siblings on disk (source-correct, unlanded at archive time — NOT modified by this archive)**:
- `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`, NEW-001) — applies `audit_logs.is_immutable` and `audit_logs.user_agent` via `Schema::hasColumn` guard.
- `hotfix-migration-eloquent-softdeletes-2026-08` (commit `d4f34b2`, NEW-002) — applies `patients.document_number` backfill with a query-builder (`DB::table(...)`) shape inside `up()` and a soft-delete import fix.

Both siblings remain `state: blocked, reason: maintainer_decision` per the runtime ledger. Their apply/un-land decision is INDEPENDENT of this archive. The user's launch prompt explicitly directed that the two apply-blocked changes are NOT modified by this archive.

## Out-of-scope items deferred to future changes

Per the proposal and the explore synthesis, nine (9) tech-debt findings + `NEW-004-SEEDER` are explicitly out of scope and route to separate changes:

| Item | Source | Route |
|---|---|---|
| NEW-A01 (`->change()` on `odontogram_records.color`, length 7→32 + nullable toggle, no driver guard) | `2026_06_07_001200_make_odontogram_records_color_nullable.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-A02 (raw `MODIFY gateway_config TEXT`, no driver guard) | `2026_06_13_140001_change_gateway_config_to_text.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-A03 (raw `MODIFY COLUMN ... ENUM(...)`, no driver guard) | `2026_06_07_002000_add_proposed_to_treatment_plan_items_status.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-A04 (`->change()` nullability toggle on `reminder_schedules.reminder_template_id`, no driver guard) | `2025_10_24_201039_make_reminder_template_id_nullable_in_reminder_schedules_table.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-LENSB-002 (`indexExists()` helper queries `information_schema.statistics`, MySQL-only) | `2025_10_25_030053_add_additional_performance_indexes.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-LENSB-005 (`UPDATE ... INNER JOIN` MySQL-only backfill) | `2026_06_10_100100_add_specialty_id_to_procedure_catalog_table.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-LENSB-006 (`INSERT ... SELECT ... NOW()` MySQL-only backfill) | `2026_06_10_100200_create_user_specialties_table.php` | `techdebt-migration-driver-guards-2026-08` |
| NEW-LENSB-001..007 driver-guard subset already enumerated | (see lens-B output) | `techdebt-migration-driver-guards-2026-08` |
| NEW-A05 (redundant non-unique indexes on `patients.document_number`, `patients.email`, `patients.phone`) | `2025_10_25_030053_add_additional_performance_indexes.php` | `techdebt-migration-index-cleanup-2026-08` |
| **NEW-004-SEEDER** (`DatabaseSeeder` references `EnvironmentSeeder::class` which only lives in `database/seeders/_legacy/EnvironmentSeeder.php`; aborts `migrate:fresh --seed` AFTER the migrations pass) | `database/seeders/DatabaseSeeder.php` | `hotfix-seeder-environment-seeder-2026-08` |

The pre-existing ~28-104 SQLite test failures on local development also remain documented in AGENTS.md §6 and are NOT addressed by this change.

## Spec source-of-truth merge

The `openspec/specs/` directory under the repo root currently holds only `.gitkeep`; there is no main-spec source-of-truth file to merge into for the `reminder-schedules-idempotent-add` domain. The project's convention (consistent with the previously archived `bugfix-2026-08`) is to keep delta specs inside the change folder; that is also where the archived spec is now stored:

- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/specs/01-reminder-schedules-idempotent-add.md` — archived (was previously at `openspec/changes/hotfix-migration-chain-full-sweep-2026-08/specs/01-reminder-schedules-idempotent-add.md`).
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/specs.md` — archived specs index.

No `openspec/specs/<domain>/spec.md` is created because no such main spec exists for this domain.

## Engram traceability (observation IDs)

| Artifact | Observation ID | Topic key |
|----------|----------------|-----------|
| Explore synthesis | #310 | sdd/hotfix-migration-chain-full-sweep-2026-08/explore |
| Proposal | #311 | sdd/hotfix-migration-chain-full-sweep-2026-08/proposal |
| Spec | #312 | sdd/hotfix-migration-chain-full-sweep-2026-08/spec |
| Design | #313 | sdd/hotfix-migration-chain-full-sweep-2026-08/design |
| Tasks | #314 | sdd/hotfix-migration-chain-full-sweep-2026-08/tasks |
| Apply progress | #316 | sdd/hotfix-migration-chain-full-sweep-2026-08/apply-progress |
| Verify report | #318 | sdd/hotfix-migration-chain-full-sweep-2026-08/verify-report |
| NEW-002 finding (sibling context) | #302 | findings/new-002-migrate-fresh-broken |
| **Archive report** | **#TBD (this save)** | **sdd/hotfix-migration-chain-full-sweep-2026-08/archive-report** |

## Archived artifacts (filesystem)

- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/proposal.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/specs.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/design.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/tasks.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/apply-progress.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/verify-report.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/explore.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/state.yaml` (phase: archived, status: complete)
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/specs/01-reminder-schedules-idempotent-add.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/tasks/01-reminder-schedules-idempotent-add.md`
- `openspec/changes/archive/hotfix-migration-chain-full-sweep-2026-08/archive-report.md` (this file)

## SDD Cycle Complete

The change has been fully planned (explore → propose → spec → design → tasks), implemented (2 commits `dfcb55c`, `17f5b77` on `fix/migration-new-003-2026-08`, merged to `main`), verified (`PASS`, 15 / 15 checks, 0 / 0 / 0), and archived. NEW-003 is closed. The two sibling commits (`d811f1a` NEW-001, `d4f34b2` NEW-002) are now satisfied at the schema layer — they hold without re-application on the same clean MySQL chain. The 9 tech-debt items + `NEW-004-SEEDER` are explicitly routed to separate changes. The live `odontosuite` database remains untouched (63 / 0).
