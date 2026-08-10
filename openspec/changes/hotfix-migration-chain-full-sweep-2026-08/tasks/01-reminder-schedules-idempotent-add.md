# Slice 01 — Reminder Schedules Idempotent Add

> Finding: NEW-003 — `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`
> unconditionally re-adds `reminder_schedules.error_message`, which is already
> created at `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php:22`.
> On a clean MySQL/MariaDB the chain aborts with
> `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'`, blocking the
> chain before NEW-001 (`d811f1a`) and NEW-002 (`d4f34b2`) commits can apply.
>
> Cluster: reminder-schedules-idempotent-add
> LOC est: ~52 · Budget risk: Low · Depends on: —
> Spec: [../specs/01-reminder-schedules-idempotent-add.md](../specs/01-reminder-schedules-idempotent-add.md)
> Design: [../design.md](../design.md) §Decision 1–5

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

## Acceptance Criteria (slice-level)

- [x] `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` `up()` contains two `Schema::hasColumn(...)` guards — one wrapping `->string('channel', ...)` and one wrapping `->text('error_message', ...)` — in that order.
- [x] `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` `down()` contains an `array_filter(['channel','error_message'], fn ($c) => Schema::hasColumn('reminder_schedules', $c))` expression and an `if ($cols)` guard before `dropColumn($cols)`.
- [x] `tests/Unit/SddCheckMigrationsTest.php` gains a `no_migration_re_adds_already_known_column()` test method that scans **all** migrations (via `allMigrations()`) and uses the existing `$stripPatterns` regex set.
- [x] Static guard reports `<filename>:<line> adds column '<col>' without hasColumn guard` on reintroduction (verified via T-01.3).
- [x] On scratch MySQL DB `odontosuite_migtest`, `php artisan migrate:fresh --force` exits 0 and reaches `audit_logs` (NEW-001) and `patients.document_number` (NEW-002).
- [x] Local `php artisan test --filter=SddCheckMigrationsTest` passes (pure-string scan, runs on SQLite in-memory with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`).
- [x] No other migration file is touched (`git diff --stat database/migrations/` shows exactly one file changed in Commit A).

---

## Phase 1 — Migration Fix (Commit A, GREEN)

### T-01.1 — Wrap `up()` and `down()` with `Schema::hasColumn` guards

**File**: `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php`
**Action**: Modify
**LOC**: ~12 added, ~2 modified

Concrete edits (mirror design §Decision 2 and design §Interfaces/Contracts):

1. `up()` body — replace the two unconditional `addColumn` calls with two independent `if (! Schema::hasColumn(...))` guards, in this order:
   ```php
   Schema::table('reminder_schedules', function (Blueprint $table) {
       if (! Schema::hasColumn('reminder_schedules', 'channel')) {
           $table->string('channel', 20)->nullable()->after('scheduled_at');
       }
       if (! Schema::hasColumn('reminder_schedules', 'error_message')) {
           $table->text('error_message')->nullable()->after('status');
       }
   });
   ```
2. `down()` body — replace unconditional `dropColumn(...)` with `array_filter + Schema::hasColumn`, plus a non-empty guard before `dropColumn`:
   ```php
   Schema::table('reminder_schedules', function (Blueprint $table) {
       $cols = array_values(array_filter(
           ['channel', 'error_message'],
           fn ($c) => Schema::hasColumn('reminder_schedules', $c)
       ));
       if ($cols) {
           $table->dropColumn($cols);
       }
   });
   ```
3. Preserve the existing top-of-file docblock; do not add a NEW-003 rationale comment unless the docblock is updated alongside Commit B (single-commit logic — keep edits minimal).
4. Do NOT modify any other migration file in this commit (`git diff --stat database/migrations/` should show exactly one file changed).

Acceptance criteria:
- [x] `grep -nE "Schema::hasColumn.*reminder_schedules" database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` returns at least two matches inside `up()` (one for `channel`, one for `error_message`)
- [x] `grep -nE "array_filter" database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` returns one match inside `down()` and that match contains `fn ($c) => Schema::hasColumn('reminder_schedules', $c)`
- [x] `php artisan migrate:fresh --force` exits 0 on scratch MySQL DB `odontosuite_migtest` (Lens D empirical — already verified on the orchestrator's on-disk working tree)
- [x] `Schema::hasColumn('reminder_schedules', 'channel')` and `Schema::hasColumn('reminder_schedules', 'error_message')` both return `true` after the fresh chain
- [x] On re-running `php artisan migrate` (no `migrate:fresh`) on the same DB, exit 0 and no `SQLSTATE[42S21]` is raised (replay safety)
- [x] Commit message: `fix(migration): guard reminder_schedules.error_message duplicate add` (matches design §Migration/Rollout)

---

## Phase 2 — Static Guard Test (Commit B, RED→GREEN)

### T-01.2 — Add `no_migration_re_adds_already_known_column` regression guard

**File**: `tests/Unit/SddCheckMigrationsTest.php`
**Action**: Modify
**LOC**: ~40 added

Add a new `/** @test */` method `no_migration_re_adds_already_known_column(): void` to the `SddCheckMigrationsTest` class, mirroring the structural shape of the existing `no_migration_references_eloquent_models` (lines 238–270). Update the class docblock (lines 7–30) to mention the new guard.

Concrete behavior:

1. Walk every file via `self::allMigrations()` (helper at line 70) — **no `GUARD_CUTOFF_PREFIX` filter** (design §Decision 3: the bug class is retroactive; the offending migration's date-prefix is the same as the cutoff, but the column it duplicates was created 11 months earlier).
2. Build a per-table map of "columns already added by an earlier migration" by walking all migrations in chronological order (sorted by filename) and recording every column declared in `$table-><type>('<col>'` inside `Schema::create(...)` blocks (the column types listed in step 3 below).
3. Walk lines once on the un-stripped source to detect column-add call shapes AND capture the column name from the same line via a regex with a capture group:
   ```php
   $addColumnCallRegex = '/->(?:string|text|integer|bigInteger|json|dateTime|timestamp|boolean|foreignId|addColumn)\s*\(\s*[\'"]([^\'"]+)[\'"]/i';
   ```
   The captured column name (group 1) is read from the un-stripped line so the column identifier is not lost. The closure body itself is then stripped with the existing `$stripPatterns` set (same as `no_migration_references_eloquent_models`) before the `Schema::hasColumn` check below — strings inside the closure that are NOT the column-identifier (e.g. an unrelated literal) must not pollute the guard lookup.
   ```php
   $stripPatterns = [
       '/\/\/.*$/m',
       '/\/\*.*?\*\//s',
       "/'(?:\\\\.|[^'\\\\])*'/s",
       '/"(?:\\\\.|[^"\\\\])*"/s",
   ];
   ```
4. For each detected `addColumn`-shaped call (with extracted `$col`), check that the same `Schema::table(...)` closure (brace-counted forward to the matching `})`) contains a `Schema::hasColumn('<table>', '<col>')` guard for that exact column. If not, append `<filename>:<line> adds column '<col>' without hasColumn guard` to `$violations`.
5. Only flag re-adds — a column-add for a column that is NOT in the per-table "already known" map is a legitimate first add (e.g. `patients.deleted_at` in `2026_06_11_001034_add_soft_deletes_to_patients_table`) and must NOT be flagged.
6. `assertSame([], $violations, "No migration may add a column that an earlier migration already created without a Schema::hasColumn(...) guard in the same closure. Offending files:\n" . implode("\n", $violations));`

Update the class docblock (top of file) to add the new guard to the existing bulleted list (after the Eloquent reference).

Acceptance criteria:
- [x] `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` passes against the post-T-01.1 tree (GREEN proof)
- [x] `php artisan test --filter=SddCheckMigrationsTest` passes (all 7 guards green: 5 existing + Eloquent + new re-add guard)
- [x] Test extends `PHPUnit\Framework\TestCase` (NOT Laravel's `TestCase`) — pure string scan, no DB connection, runs cleanly on SQLite in-memory with `phpunit.xml`'s `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` pinning
- [x] `no_migration_references_eloquent_models` (NEW-002 guard) still passes
- [x] No new SQLite test failures introduced (matches AGENTS.md §6 documented tech-debt floor)
- [x] Commit message: `test(migration): guard against unguarded column re-adds`

---

## Phase 3 — Reintroduction Proof

### T-01.3 — Demonstrate the guard catches the pre-A state

**File**: N/A (verification only — no persistent code change)
**Action**: Verify

Document and execute the reintroduction proof (design §Testing Strategy row 3 + design §Rollback row "Revert A only"):

1. Temporarily revert the `if (! Schema::hasColumn('reminder_schedules', 'error_message'))` wrapper in `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` to the unconditional `$table->text('error_message')->nullable()->after('status');` (the pre-A broken shape).
2. Run `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` — expect a failure with a message that names `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` AND the column `error_message` AND a line number near line 24 (the unconditional `->text('error_message')` line). Record the exact failure message in the apply report.
3. Restore the `if (! Schema::hasColumn(...))` wrapper.
4. Re-run `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` — expect GREEN.
5. Confirm `git diff database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` is unchanged (the temporary revert was fully restored).

Acceptance criteria:
- [x] Step 2 produces a failure message that contains both `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` and `error_message` and a 1-indexed line number
- [x] Step 4 produces GREEN
- [x] Step 5 confirms no leftover diff on the migration file
- [x] No other test in `SddCheckMigrationsTest` regresses during step 2

---

## Phase 4 — Verification

### T-01.4 — Run full MySQL chain + sibling-unlock smoke

**File**: N/A (verification only — produces a textual report, not a code change)
**Action**: Verify

Run the full CI gate locally on scratch MySQL DB `odontosuite_migtest`:

1. `php artisan test --filter=SddCheckMigrationsTest` — all 6 guard methods pass (4 existing + Eloquent + new re-add guard).
2. `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` — passes on SQLite in-memory (proves the guard is portable to the local dev test env).
3. `DB_DATABASE=odontosuite_migtest php artisan migrate:fresh --force` — exits 0 on MySQL, chain reaches the `audit_logs` migration (NEW-001 unlocks) and the `patients.document_number` migration (NEW-002 unlocks).
4. `php artisan test` — full suite green modulo the documented pre-existing SQLite failures (AGENTS.md §6 tech-debt floor; must not introduce new failures).
5. Capture an Engram observation (`findings/new-003-fixed`) recording the verification result: chain passes on `odontosuite_migtest`; both NEW-001 and NEW-002 are now reachable in the chain; the static guard is in place.

Acceptance criteria:
- [x] Steps 1–4 all green (modulo the documented pre-existing SQLite failures; no new failures)
- [x] `audit_logs` table exists post-migrate:fresh (NEW-001 sibling is satisfiable)
- [x] `patients.document_number` column exists post-migrate:fresh (NEW-002 sibling is satisfiable)
- [x] Both sibling hotfixes (`hotfix-audit-log-immutable-2026-08` `d811f1a`, `hotfix-migration-eloquent-softdeletes-2026-08` `d4f34b2`) hold without re-application
- [x] Engram observation recorded with chain-success outcome + sibling-unlock confirmation

---

## Per-slice risk

| Risk | Mitigation |
|------|-----------|
| Static-guard string-stripping regresses on edge cases (e.g. column name inside a comment) | Mirror existing `no_migration_references_eloquent_models` `$stripPatterns` set; per-line strip; the column name itself (the second regex capture group) is not stripped — only strings + comments |
| Guard false-positives a legitimate first-add migration | The "already-known per table" map is built by walking all migrations in order; only re-adds (column already in map) trigger the check |
| `array_filter` shape inside `down()` mistyped (e.g. misses `array_values(...)`) | Verbatim from design §Decision 2; apply phase copies from design contract; T-01.4 covers replay safety on partially-applied DB |
| Migration file already in fixed state on disk (orchestrator preflight work) | T-01.1 documents the on-disk state as the GREEN reference; Commit A finalizes that content. No behavior divergence between on-disk state and Commit A. |
| `phpunit.xml` SQLite pinning masks a real MySQL-only bug in the guard | Guard is pure string scan (no DB connection); the SQLite vs MySQL distinction is irrelevant for the guard itself |
| Inconsistent dev DB schema state (45 Pending migrations) | Out of scope; documented in AGENTS.md §6; this hotfix only repairs the SOURCE. Operational follow-up: `php artisan migrate` after pulling this hotfix resumes the chain. NOT in this PR. |

## Notes

- **TDD ordering recap**:
  - **T-01.1** GREEN: migration file already in fixed state on disk (orchestrator preflight work); Commit A formalizes it.
  - **T-01.2** RED→GREEN: the static guard test passes against the post-T-01.1 tree (GREEN proof). The RED proof requires reverting T-01.1's wrapper — this is T-01.3 (reintroduction proof).
  - **T-01.3** REINTRODUCTION: temporarily revert the `hasColumn` wrapper, observe guard failure, restore.
  - **T-01.4** VERIFY: full suite + sibling unlock + Engram observation.
- This hotfix MUST land before either sibling's `migrate:fresh --force` exit-0 acceptance criterion can be satisfied on a fresh MySQL DB.
- DO NOT expand scope into fixing the inconsistent dev database state, NEW-004-SEEDER (EnvironmentSeeder missing class), or any pre-2026_08_05 tech-debt item from the lens A/B sweep — those are documented in AGENTS.md §6 and are separate follow-ups.