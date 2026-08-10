# Apply Progress — hotfix-migration-chain-full-sweep-2026-08

> Change: hotfix-migration-chain-full-sweep-2026-08
> Phase: apply
> Status: completed
> Mode: Strict TDD
> Branch: `fix/migration-new-003-2026-08`
> Strategy: stacked-to-main (chain-style, but resolved to single PR per ask-on-risk with 400-line budget risk: Low)

## Completed Tasks

- [x] **T-01.1** — Modified `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`: added `Schema::hasColumn(...)` guards around both `up()` `addColumn` calls (channel + error_message); wrapped `down()` `dropColumn` in `array_filter + hasColumn` with an `if ($cols)` guard. Mirror of d4f34b2 precedent.
- [x] **T-01.2** — Added `no_migration_re_adds_already_known_column(): void` test method to `tests/Unit/SddCheckMigrationsTest.php`; updated class docblock to mention the new guard. Two-pass algorithm: Pass 1 builds a per-table known-column map by walking `Schema::create(...)` closures; Pass 2 walks `Schema::table(...)` closures and demands `Schema::hasColumn('<table>','<col>')` in the same closure for every re-add.
- [x] **T-01.3** — Reintroduction proof executed. With the test in place and the migration reverted to the pre-fix broken state, `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` failed with the exact assertion message:
  > `No migration may re-add a column that an earlier migration already created without a Schema::hasColumn(...) guard in the same closure (NEW-003 regression). Offending files: 2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php:21 adds column 'error_message' without hasColumn guard`
  After restoration, the test passes (GREEN).
- [x] **T-01.4** — Verification:
  - `php artisan test --filter=SddCheckMigrationsTest` — **7 passed, 12 assertions** (5 pre-existing + Eloquent + new re-add guard). Includes `no_migration_re_adds_already_known_column` on the SQLite in-memory default test connection.
  - `DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --no-interaction` — exit 0, **108 migrations Ran, 0 Pending** on a virgin scratch DB.
  - Schema verification: `reminder_schedules.channel` (varchar(20) nullable) + `reminder_schedules.error_message` (text nullable) present at expected `after(...)` positions; `patients.document_number` (varchar(20)) and `patients.deleted_at` (timestamp) present; `audit_logs.is_immutable` (tinyint(1)) and `audit_logs.user_agent` (text) present.
  - Seed stage abort captured at the documented `NEW-004-SEEDER` defect: `Target class [Database\Seeders\EnvironmentSeeder] does not exist` — out of scope (separate change `hotfix-seeder-environment-seeder-2026-08`).

## Files Changed

| File | Action | What |
|---|---|---|
| `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` | Modified (Commit A) | `Schema::hasColumn` guards in `up()` for both columns; `array_filter + hasColumn` + `if ($cols)` guard before `dropColumn` in `down()`. Diff: +13 / -3. |
| `tests/Unit/SddCheckMigrationsTest.php` | Modified (Commit B) | New `no_migration_re_adds_already_known_column` regression guard; updated class docblock. Diff: +206 / -1. |

Both commits are isolated to the change's scope. No unrelated files were staged/committed (resources/js/**, deleted `openspec/changes/bugfix-2026-08/tasks/*`, untracked `.codegraph/`, `.atl/`, etc. remain untouched on the working tree, per the project rule).

## Commits (branch `fix/migration-new-003-2026-08`)

```
17f5b77 test(sdd-check): guard against re-adding columns already created in earlier migrations
dfcb55c fix(migrations): make add_channel_and_error_to_reminder_schedules idempotent with hasColumn guards
```

Conventional commit format. No `Co-Authored-By`. No AI attribution.

## TDD Cycle Evidence (Strict TDD Mode)

| Task | RED | GREEN | REFACTOR |
|------|-----|-------|----------|
| T-01.1 (migration fix) | N/A — design Decision 5 fix-first (no commit-A RED possible without violating "each commit green" rule; demonstrated RED via T-01.3 reintroduction proof) | Verified: `migrate:fresh --no-interaction` exit 0, 108 migrations Ran, 0 Pending on `odontosuite_migtest`. | None needed — verbatim from design Decision 2. |
| T-01.2 (static guard) | Confirmed RED via T-01.3 reintroduction proof: with the migration reverted to the pre-fix broken shape, the new test fails with explicit filename + line + column identifier: `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php:21 adds column 'error_message' without hasColumn guard` | Verified: `php artisan test --filter=SddCheckMigrationsTest` — 7 passed, 12 assertions on the post-fix tree. | None — single-pass implementation aligned with design Decision 4. |
| T-01.3 (reintroduction proof) | Same as T-01.2 RED | Confirmed GREEN after restoring the `hasColumn` wrapper. Migration file diff at the end of the sequence matches the pre-revert state exactly (`git diff` is empty for the migration file between the broken mid-state and the restored end-state). | N/A — verification only. |
| T-01.4 (verification) | N/A | Verified: scratch-DB `migrate:fresh` exit 0; 108 Ran / 0 Pending; sibling unlocks (audit_logs, patients.document_number, reminder_schedules.{channel,error_message}) all confirmed; seed-stage abort at NEW-004-SEEDER captured (out of scope). | N/A — verification only. |

## Deviations from Design

**None.** Implementation matches design §Decision 1–5 exactly. The static guard test (T-01.2) was strengthened during implementation to:
1. Match `Schema::create` / `Schema::table` against the RAW (un-stripped) line so the table-name string survives the capture (the design assumed stripped would work — empirically it strips the table name too).
2. Search the guard against the RAW closure (un-stripped) so the `Schema::hasColumn('<table>','<col>')` arguments (which are themselves string literals) survive the lookup.
3. Skip column-add calls that include `->change()` in the same line, so legitimate column-modify operations like `2026_06_07_001200_make_odontogram_records_color_nullable.php` are not false-positive flagged (it adds nothing new, it modifies existing `color`).

None of these refinements change the design's intent or contract; they harden the implementation against test-empirical false positives.

## Issues Found

| Issue | Severity | Action |
|---|---|---|
| Initial Edit accidentally converted a closing single-quote to a double-quote on an existing `$stripPatterns` array element, which surfaced as a parse error: `syntax error, unexpected identifier "s", expecting "]"`. | Resolved | Fixed by re-issuing the Edit with the correct closing `'` and `,` (the array's 4th element). Re-ran `php -l` and `php artisan test --filter=SddCheckMigrationsTest` — both pass. |
| Initial test version stripped strings BEFORE matching `Schema::table(<table>)`, which removed the table-name string literal and left the regex without a target. Test passed silently on the broken migration. | Resolved | Updated to match against the RAW line for the table-name capture. RED was then demonstrable and GREEN was correct. |
| Initial test version over-flagged `2026_06_07_001200_make_odontogram_records_color_nullable.php` (a column MODIFY with `->change()`, not a re-ADD). | Resolved | Added a per-line `->change()` skip in the column-add detector. |

All three issues were resolved before any commit. The committed state is the final GREEN state.

## Next Step

- `git merge fix/migration-new-003-2026-08 main --no-ff -m "merge: NEW-003 reminder_schedules idempotent add + retroactive re-add guard"` (or fast-forward — pending choice). Both branches are currently 2 commits ahead of `main` (the 17 prior stacked commits remain untouched). Then run final verification on `main`.
- After merge, `sdd-verify` for change verification.

## Remaining Tasks

None — apply phase complete.

## Workload / PR Boundary

- Mode: **single PR**, 2 stacked commits on `fix/migration-new-003-2026-08`, then merge to `main`.
- Changed lines: ~52 LOC (projected) — actual: 219 insertions, 4 deletions across 2 files. The test file diff is the bigger of the two (guard body is ~206 lines); migration diff is ~13 lines.
- 400-line budget risk: **Low** (per the workload guard).
- Review Workload Forecast (from tasks/01): `Decision needed before apply: No`; `Chained PRs recommended: No`; `Chain strategy: stacked-to-main`.
