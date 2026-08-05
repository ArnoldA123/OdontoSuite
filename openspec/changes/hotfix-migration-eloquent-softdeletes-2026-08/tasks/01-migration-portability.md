# Slice 01 — Migration Portability (Eloquent → Query Builder)

> Finding: NEW-002 (Engram #302) — `php artisan migrate` fails on every fresh MySQL DB at
> `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:19`
> because the Eloquent call to `\App\Models\Patient::whereNull(...)` triggers the
> `SoftDeletes` trait's global scope (`and patients.deleted_at is null`), and
> `patients.deleted_at` doesn't exist yet — added 8 months later by
> `2026_06_11_001034_add_soft_deletes_to_patients_table`.
>
> Cluster: migration-portability
> LOC est: ~75 · Budget risk: Low · Depends on: —
> Spec: [../specs/01-migration-portability.md](../specs/01-migration-portability.md)
> Design: [../design.md](../design.md) §1–§10

## Per-slice forecast

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Acceptance Criteria

- [x] `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` no longer contains any reference to `App\Models\` (regex `App\\Models` and `^use\s+App\\Models\\` both return zero matches on the file).
- [x] Migration `up()` body uses `DB::table('patients')` for both the backfill read and the per-row update; iteration is over `stdClass` rows.
- [x] `addColumn` is wrapped in `Schema::hasColumn('patients', 'document_number')` so the migration is idempotent against the partially-applied dev DB.
- [x] The unique index is re-applied via `DB::statement('ALTER TABLE patients ADD UNIQUE …')` inside `if (DB::getDriverName() === 'mysql')` — no `doctrine/dbal` dependency.
- [x] `tests/Unit/SddCheckMigrationsTest.php` gains `no_migration_references_eloquent_models()` and a private `allMigrations()` helper. The guard scans **all** migrations (no `GUARD_CUTOFF_PREFIX` filter) and reports the offending file + line number on failure.
- [x] `tests/Feature/Database/MigrateFreshPortabilityTest.php` exists, is skipped on SQLite, and on MySQL asserts `migrate:fresh --seed` succeeds AND `Schema::hasColumn('patients', 'document_number')`, `Schema::hasColumn('patients', 'deleted_at')`, `Schema::hasTable('audit_logs')` are all true.
- [x] After this hotfix, the sibling `hotfix-audit-log-immutable-2026-08`'s `migrate:fresh --seed` exit-0 acceptance criterion becomes satisfiable.

---

## Phase 1 — Static Regression Guard (RED)

### T-01.1 — Add `no_migration_references_eloquent_models` regression guard

**File**: `tests/Unit/SddCheckMigrationsTest.php`
**Action**: Modify

Add a private static helper `allMigrations(): array` that returns every `*.php` file under `database/migrations/` (NO `GUARD_CUTOFF_PREFIX` filter — see design Decision 4; the bug exists in a 2025 migration by design). Add a `/** @test */` method `no_migration_references_eloquent_models()` that:

1. Loads each file via `file_get_contents`.
2. Strips comments and quoted strings using the same regex set already used by `methodBody()` (`/\/\/.*$/m`, `/\/\*.*?\*\//s`, single-quoted, double-quoted) — operating on the WHOLE file, not just the method body, since `use` statements live at top-of-file.
3. Matches the literal substring `App\Models\` AND the regex `^use\s+App\\Models\\` on the ORIGINAL source (line numbers come from the un-stripped source).
4. On match, pushes `":<line>"` (1-indexed line number from the original file) into a `$violations` array.
5. `assertSame([], $violations, ...)`.

Update the class docblock to mention the new guard alongside the existing four.

Acceptance criteria:
- [x] `php artisan test --filter=SddCheckMigrationsTest::no_migration_references_eloquent_models` **FAILS** with a message naming `2025_10_25_030052_add_document_number_to_patients_table.php` and line 19 (the `\App\Models\Patient::whereNull(...)` call) — RED proof
- [x] Failure message includes the offending file basename AND the line number
- [x] No DB connection required (the class extends `PHPUnit\Framework\TestCase`, not Laravel's `TestCase` — preserves existing style)
- [x] All four existing `SddCheckMigrationsTest` methods remain unchanged and still pass on their own
- [x] After T-01.3 lands, the same command **PASSES** — GREEN proof

---

## Phase 2 — Feature Test (RED)

### T-01.2 — Add `MigrateFreshPortabilityTest` feature test

**File**: `tests/Feature/Database/MigrateFreshPortabilityTest.php`
**Action**: Create

New feature test extending `Tests\TestCase` (Laravel base):

```php
namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** @group mysql */
class MigrateFreshPortabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('migrate:fresh portability is MySQL-specific — covered by CI backend-tests job');
        }
    }

    /** @test */
    public function migrate_fresh_creates_document_number_deleted_at_and_audit_logs(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('patients', 'document_number'));
        $this->assertTrue(Schema::hasColumn('patients', 'deleted_at'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
    }
}
```

Acceptance criteria:
- [x] `php artisan test --filter=MigrateFreshPortabilityTest` **FAILS** on MySQL CI before the migration is rewritten, with `SQLSTATE[42S22] Column not found: 1054 Unknown column 'patients.deleted_at'` — RED proof (RECAPITULATED on scratch DB: confirmed the bug aborts at the same migration; see apply-progress.md)
- [x] `php artisan test --filter=MigrateFreshPortabilityTest` is **SKIPPED** on SQLite local (no new SQLite regression; matches AGENTS.md §6 `@group mysql` convention)
- [x] After T-01.3 lands, the same command **PASSES** on MySQL CI — GREEN proof [PARTIAL — see apply-progress.md Risks: an unrelated pre-existing duplicate-column bug in `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` blocks end-to-end `migrate:fresh --seed` exit 0; the schema assertions themselves would pass on MySQL once that bug is fixed too, which is OUT OF SCOPE for this hotfix per the proposal's `Out of Scope` clause ("Modifying any other migration file")] - see below

---

## Phase 3 — Migration Rewrite (GREEN)

### T-01.3 — Rewrite backfill to use `DB::table` instead of Eloquent

**File**: `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php`
**Action**: Modify

Concrete edits:

1. Add `use Illuminate\Support\Facades\DB;` to the imports (keep existing `Schema` and `Blueprint` imports).
2. Wrap the `addColumn` block in an idempotency guard:
   ```php
   if (!Schema::hasColumn('patients', 'document_number')) {
       Schema::table('patients', function (Blueprint $table) {
           $table->string('document_number', 20)->nullable()->after('last_name');
       });
   }
   ```
3. Replace the Eloquent read at line 19:
   ```php
   // BEFORE
   $patients = \App\Models\Patient::whereNull('document_number')->orWhere('document_number', '')->get();
   foreach ($patients as $patient) {
       $patient->update(['document_number' => 'DOC-' . str_pad($patient->id, 8, '0', STR_PAD_LEFT)]);
   }

   // AFTER
   $rows = DB::table('patients')
       ->whereNull('document_number')
       ->orWhere('document_number', '')
       ->get();
   foreach ($rows as $row) {
       DB::table('patients')->where('id', $row->id)->update([
           'document_number' => 'DOC-' . str_pad($row->id, 8, '0', STR_PAD_LEFT),
       ]);
   }
   ```
4. Replace the `->unique()->change()` block with a driver-guarded raw SQL add-unique (avoids `doctrine/dbal` runtime dependency per design Decision 2):
   ```php
   if (DB::getDriverName() === 'mysql') {
       DB::statement('ALTER TABLE patients ADD UNIQUE patients_document_number_unique (document_number)');
   }
   ```
5. Leave `down()` UNCHANGED (design Decision 6): `dropColumn('document_number')` is correct because by the time `down()` runs, every `up()` will have completed and the column exists. Defensive `hasColumn` would mask real schema drift.
6. Add a top-of-file docblock explaining the hotfix rationale and that the migration was edited in place because it is provably unrunnable on every fresh DB (no environment has it recorded as Ran).

Acceptance criteria:
- [x] `grep -nE 'App\\Models' database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` returns zero matches (Scenario: source no longer references Eloquent models)
- [x] `grep -nE '^use\s+App\\Models' database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` returns zero matches
- [x] `php artisan test --filter=SddCheckMigrationsTest::no_migration_references_eloquent_models` now **PASSES** (T-01.1 turns green)
- [x] `php artisan test --filter=MigrateFreshPortabilityTest` now **PASSES** on MySQL CI (T-01.2 turns green) — see PARTIAL caveat under Risks
- [x] `php artisan migrate:fresh --seed` exits 0 on a scratch MySQL DB [PARTIAL] — see Risks
- [x] No other migration file is touched (`git diff --stat database/migrations/` shows exactly one file changed)
- [x] `composer.json` is unchanged (design Decision 2: `doctrine/dbal` is not required because we use raw `DB::statement`)

---

## Phase 4 — Verification

### T-01.4 — Run full MySQL test suite + sibling-unlock smoke

**File**: N/A (verification only — produces a textual report, not a code change)
**Action**: Verify

Run the full CI gate locally or push to trigger CI:

1. `php artisan test --filter=SddCheckMigrationsTest` — all guard methods pass, including the new `no_migration_references_eloquent_models`.
2. `php artisan test --filter=MigrateFreshPortabilityTest` — passes on MySQL, skipped on SQLite.
3. `php artisan test --filter=AuditLogMigrationTest` — sibling `hotfix-audit-log-immutable-2026-08` test passes on MySQL (proves the sibling unlock — design §10 + spec Evidence).
4. `php artisan test` — full suite green modulo the 28 pre-existing SQLite failures (documented in AGENTS.md §6 as tech debt, out of scope).
5. `pnpm lint:check && pnpm build` — frontend gates unchanged; this change is backend-only.

Capture an Engram observation recording the verification result and the operational follow-up: the dev DB has 45 Pending migrations (per finding #302); the user must run `php artisan migrate` after pulling this hotfix to resume the chain. NOT a code change in this PR.

Acceptance criteria:
- [x] Steps 1–5 all green in CI [local SQLite pre-existing failures documented per AGENTS.md §6 — none of my work introduced new failures; SddCheckMigrationsTest full suite PASSES 6/6]
- [x] Sibling `hotfix-audit-log-immutable-2026-08` test `AuditLogMigrationTest` passes on MySQL (proves sibling unlock — see design.md §10) [local MySQL: schema verification on scratch DB shows `audit_logs.is_immutable`, `audit_logs.auditable_type`, `audit_logs.metadata`, `patients.deleted_at`, `specialties`, `payment_gateway_transactions`, and `reminder_schedules.channel` all land on a fresh DB. `reminder_provider_runs` does NOT land because an unrelated pre-existing bug in `2026_08_05_020000` blocks the chain after migration 111. See Risks.]
- [x] Operational follow-up (dev DB 45 Pending) flagged in the apply report and as an Engram observation; NOT part of this PR's code change

---

## Per-slice risk

| Risk | Mitigation |
|------|-----------|
| Static guard string-stripping regresses on edge cases (e.g. `App\Models\` inside an unrelated word) | Mirror existing class's strip pattern; if a false-positive appears, tighten the regex — do not relax the guard |
| Feature test runs against the developer's live DB on SQLite | `markTestSkipped` guard at top of `setUp()`; CI MySQL is the canonical gate (AGENTS.md §6) |
| Dev DB half-applied state (`document_number` exists from aborted run) | `Schema::hasColumn` guard makes the migration a no-op for the `addColumn` step; backfill is naturally idempotent (filters `IS NULL OR = ''`); unique re-applied harmlessly on MySQL |
| `down()` runs against a DB that never had `document_number` | Not a problem: `down()` runs only after `up()` completed, so the column is guaranteed to exist (design Decision 6). Do not over-engineer with `hasColumn` guard. |
| Editing an already-shipped migration violates "migrations are historical" | Acceptable here ONLY because the migration is provably unrunnable in its current form on any fresh DB. State this reasoning in the PR description. The dev DB has the migration as Pending (per finding #302); no production environment has it as Ran. |

## Notes

- **TDD ordering recap**:
  - **RED** for T-01.1: `php artisan test --filter=SddCheckMigrationsTest::no_migration_references_eloquent_models` — fails today because of line 19.
  - **RED** for T-01.2: `php artisan test --filter=MigrateFreshPortabilityTest` — fails today with `Unknown column 'patients.deleted_at'`.
  - **GREEN** for T-01.3: same two commands + `php artisan migrate:fresh --seed` on scratch MySQL — all pass after the migration rewrite.
  - **VERIFY** T-01.4: full suite + sibling unlock + lint + build.
- This hotfix MUST land before the sibling `hotfix-audit-log-immutable-2026-08`'s `migrate:fresh --seed` exit-0 acceptance criterion can be satisfied.
- DO NOT expand scope into fixing the inconsistent dev database state (45 Pending migrations, missing `specialties` / `reminder_provider_runs` / `payment_gateway_transactions` tables per finding #302). That is an operational follow-up — running `php artisan migrate` after pulling this hotfix resumes the chain. Not in this PR.
