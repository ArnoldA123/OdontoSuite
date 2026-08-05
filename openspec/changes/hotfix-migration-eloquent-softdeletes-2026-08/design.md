# Design: hotfix-migration-eloquent-softdeletes-2026-08

> Status: ready-for-tasks · Artifact store: hybrid · Delivery strategy: ask-on-risk · Review budget: 400 lines/slice
> Stack: Laravel 12 + PHP 8.2 + MySQL 8.0 · Strict TDD · Schema-touching hotfix

## 1. Technical Approach

Rewrite the backfill loop in `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` so it uses the Query Builder (`DB::table('patients')`) instead of the Eloquent model. The model change is local; the rest of the migration (column add, unique, down) is preserved. Add a sibling test method to `tests/Unit/SddCheckMigrationsTest.php` that scans **all** migrations for `App\Models\` references — extending the existing established pattern rather than introducing a new test file. Add a feature test gated on MySQL that asserts `migrate:fresh` exits 0 and the resulting schema includes `patients.deleted_at` and `patients.document_number`.

The migration must be idempotent: the dev DB is in a half-applied state (`patients.document_number` already exists from a previously aborted run; MySQL DDL is non-transactional). Guard the `addColumn` with `Schema::hasColumn`, and re-apply the unique constraint unconditionally (an `ADD UNIQUE` on an already-unique column is a no-op on MySQL).

## 2. Architecture Decisions

| # | Decision | Choice | Alternatives | Rationale |
|---|---|---|---|---|
| 1 | Backfill loop in `2025_10_25_030052_add_document_number_to_patients_table.php` | Query Builder rewrite (`DB::table('patients')`) | (a) delete the backfill entirely; (b) move backfill to a later migration | (a) leaves NULL/empty `document_number` rows in production and breaks the unique constraint that comes immediately after. (b) creates a chicken-and-egg with the unique constraint and complicates the migration chain. Rewrite is minimal (~3 lines), preserves the deterministic `DOC-{8-digit padded id}` output, and decouples the migration from the model's trait composition. |
| 2 | `->unique()->change()` at line 26 | Wrap with `DB::getDriverName() === 'mysql'` guard, then `DB::statement('ALTER TABLE patients ADD UNIQUE patients_document_number_unique (document_number)')` after the backfill | (a) leave `->unique()->change()` as-is; (b) split into two `Schema::table` blocks | (a) requires `doctrine/dbal` (only present transitively in `require-dev` via Laravel 12's testing harness — not in `require`, fragile on a production deploy that skips dev deps). (b) still requires doctrine/dbal. Raw `DB::statement` is portable across MySQL 5.7/8.0 and matches the project's existing pattern (no other migration uses `->change()` or `MODIFY COLUMN` except `2025_10_14_123001_fix_appointments_status_enum.php` and `2026_06_13_140001_change_gateway_config_to_text.php`, both already driver-conditional per `SddCheckMigrationsTest`). |
| 3 | Regression guard location | Add `no_migration_references_eloquent_models()` to existing `tests/Unit/SddCheckMigrationsTest.php` | New test file `tests/Unit/SddCheckMigrationsEloquentTest.php` | The existing test is the canonical CI guard for migration patterns (already covers dropColumn, MODIFY, DATE_SUB, dropUnique). Adding a sibling method follows the project's established source-inspection pattern; one failure surface, one place to extend. A new test file would split the guard and dilute the contract. |
| 4 | Regression guard scope | Scan **all** migrations (`glob *.php` no cutoff) | Reuse `GUARD_CUTOFF_PREFIX = '2026_08_05'` | The bug exists in a 2025 migration by design (the regression was retroactive). Cutoff-based guards cannot defend against the historical buggy file because it predates the cutoff. The new test method must scan unconditionally; the existing four cutoff-based guards keep their semantics. |
| 5 | `up()` idempotency | Guard `addColumn` with `Schema::hasColumn('patients', 'document_number')`; backfill selects rows where `document_number IS NULL OR document_number = ''` (already does); re-apply unique index unconditionally | (a) no guard; (b) guard backfill only | Dev DB has `document_number` already from MySQL's non-transactional DDL persistence after the first aborted run. Without `hasColumn` guard, migrate fails on `Duplicate column name 'document_number'`. Backfill is naturally idempotent (filters on `IS NULL OR = ''`). The unique index is re-applied harmlessly on MySQL (`ADD UNIQUE` is a no-op when the index exists). |
| 6 | `down()` correctness | Unchanged: `dropColumn('document_number')` | Wrap in `Schema::hasColumn` guard | `dropColumn` on a missing column throws on MySQL. BUT: by the time `down()` runs, every up() will have completed, so the column exists. The existing `down()` is correct. Defensive `hasColumn` is over-engineering and would mask a real schema drift. |
| 7 | Backfill row iteration | `DB::table('patients')->where(...)->get()` returns `stdClass`; iterate with `foreach`, write via `DB::table('patients')->where('id', $row->id)->update(['document_number' => …])` | `DB::table('patients')->where(...)->update(['document_number' => 'DOC-' . str_pad(DB::raw('id'), 8, '0', STR_PAD_LEFT)])` | The `update()` bulk version requires writing raw SQL expressions (`DB::raw`) to interpolate the padded id — error-prone, harder to read, and bypasses the casts/standards the Query Builder already exposes. The two-step read-then-write is 3 lines, explicit, and matches the original Eloquent intent one-for-one. Performance is irrelevant for a one-time migration backfill. |
| 8 | Feature test isolation | New `tests/Feature/Database/MigrateFreshPortabilityTest.php` gated on `DB::getDriverName() === 'mysql'` via `markTestSkipped` on SQLite | (a) Add to `tests/Feature/ExampleTest.php`; (b) Always run regardless of driver | `migrate:fresh` against SQLite would mask the bug because SQLite does not enforce `MODIFY COLUMN` semantics the same way. The bug is MySQL-specific (per the finding: 1st run error is `42S22` on MySQL). Skipping on SQLite is the only honest signal. CI runs MySQL 8.0 — the canonical gate. |
| 9 | Feature test scope | `Artisan::call('migrate:fresh', ['--seed' => true])` exits 0; assert `Schema::hasColumn('patients', 'document_number')` and `Schema::hasColumn('patients', 'deleted_at')` and `Schema::hasTable('audit_logs')` | Just check exit code | Exit code alone can be 0 with broken state if `migrate:fresh` silently skips. Asserting three concrete schema facts proves the chain reached the audit_log migration (which is the sibling unlock criterion). |

## 3. Data Flow

The migration is a one-shot schema/data transform executed linearly by `migrate` / `migrate:fresh --seed`:

```
Schema::hasColumn('patients', 'document_number') ?
  skip  :  Schema::table('patients', fn(t) => t->string('document_number', 20)->nullable()->after('last_name'))
DB::table('patients')->whereNull('document_number')->orWhere('document_number', '')->get()
  ↓ stdClass rows
foreach ($row as $r) {
  DB::table('patients')->where('id', $r->id)->update([
    'document_number' => 'DOC-' . str_pad($r->id, 8, '0', STR_PAD_LEFT)
  ]);
}
DB::getDriverName() === 'mysql' ?
  DB::statement('ALTER TABLE patients ADD UNIQUE patients_document_number_unique (document_number)') : null
```

Equivalent to the original Eloquent version byte-for-byte in outcome, but immune to the model's `SoftDeletes` trait because `DB::table` bypasses model events, scopes, and accessors.

## 4. File Changes

| File | Action | Description |
|---|---|---|
| `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` | Modify | Replace `Patient::whereNull(...)->orWhere(...)->get()` with `DB::table('patients')`. Convert `foreach` body to `DB::table('patients')->where('id', $row->id)->update(...)`. Wrap `addColumn` in `Schema::hasColumn` guard. Replace `->unique()->change()` with `DB::statement('ALTER TABLE ... ADD UNIQUE ...')` under `DB::getDriverName() === 'mysql'` guard. Add `use Illuminate\Support\Facades\DB;` import. |
| `tests/Unit/SddCheckMigrationsTest.php` | Modify | Add private helper `allMigrations()` (no cutoff) and test method `no_migration_references_eloquent_models()` that scans every file for `\\App\\Models\\` and `use App\\Models\\`. Update class doc-block to mention the new check. |
| `tests/Feature/Database/MigrateFreshPortabilityTest.php` | Create | `MarkTestSkipped` on SQLite. Otherwise: `Artisan::call('migrate:fresh', ['--seed' => true])`, assert exit 0, then `Schema::hasColumn('patients', 'document_number')`, `Schema::hasColumn('patients', 'deleted_at')`, `Schema::hasTable('audit_logs')`. |
| `composer.json` | No change | `doctrine/dbal` already present transitively via `laravel/framework` require-dev. |

## 5. Interfaces / Contracts

No new interfaces. The migration continues to satisfy `Illuminate\Database\Migrations\Migration` with `up()` and `down()` returning `void`. The new test file extends `Tests\TestCase` (Laravel base) and uses `Artisan` and `Schema` facades.

## 6. Testing Strategy

| Layer | What to Test | Approach | File |
|---|---|---|---|
| Static guard | No migration in `database/migrations/*.php` references `App\Models\` | PHPUnit source-string scan; `assertSame([], $violations)` | `tests/Unit/SddCheckMigrationsTest.php::no_migration_references_eloquent_models()` |
| Integration | `migrate:fresh --seed` exits 0 on MySQL and the resulting schema contains `document_number`, `deleted_at`, and `audit_logs` | `Artisan::call('migrate:fresh', ['--seed' => true])` + `Schema::hasColumn/hasTable` assertions; skip on SQLite | `tests/Feature/Database/MigrateFreshPortabilityTest.php` |
| Regression (manual) | CI `backend-tests` job (MySQL 8.0) reports green | No new CI changes; existing job discovers the new test via `php artisan test` | `.github/workflows/ci.yml` (unchanged) |

## 7. Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary is touched. The change is a database migration plus a static guard test.

## 8. Migration / Rollout

Single migration edit, single PR. No data migration step needed for end users:

- **Existing live DBs** (the partially-applied `odontosuite` dev DB): the new `Schema::hasColumn` guard makes the migration a no-op for the `addColumn` step; the backfill is naturally idempotent (filters on `IS NULL OR = ''`); the unique index is re-applied harmlessly. After the hotfix, run `php artisan migrate` to record the migration as Ran and resume the 45 Pending migrations from the dev DB.
- **Fresh DBs** (new developer, CI scratch, testing `migrate:fresh`): the migration now succeeds atomically; the bug is fixed.
- **Production**: `odontosuite` production schema is unknown; document that operators must run `php artisan migrate` after pulling the hotfix to record the migration as Ran. Once the change is on disk, the migration is recorded as Ran regardless of whether it had broken previously — this is the only path to resume the chain.

## 9. Open Questions

- [ ] **None.** All five design constraints from the orchestrator prompt are resolved. Decision 4 (idempotency guard) is the most consequential and is justified by the explicit dev DB state in the finding.

## 10. Review Budget

- Migration edit: ~10 LOC (3 lines changed + 5 added + 1 import + 1 `hasColumn` guard).
- New test method in `SddCheckMigrationsTest`: ~30 LOC.
- New feature test file: ~35 LOC.
- Total: ~75 LOC, well under the 400-line budget. **Low risk; single PR.**
