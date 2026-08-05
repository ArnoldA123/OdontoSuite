# Delta for migration-portability

## MODIFIED Requirements

### Requirement: add_document_number_to_patients backfill portability

The migration `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` MUST run successfully on a fresh MySQL database whose schema state matches the migrations that precede it in the chain. The backfill MUST NOT depend on any column that the same migration does not itself create.

The `up()` method MUST use the Laravel Query Builder (`DB::table('patients')`) to read existing patient rows and write the generated `document_number`. The migration MUST NOT instantiate, reference, or static-call any class under the `App\Models\` namespace (including via `use` imports or fully-qualified names).

The generated `document_number` MUST equal `'DOC-' . str_pad($id, 8, '0', STR_PAD_LEFT)` for every existing patient whose `document_number` is `null` or empty string, exactly matching the prior Eloquent-based behavior.

(Previously: The migration called `\App\Models\Patient::whereNull('document_number')->orWhere('document_number', '')->get()` and iterated with `$patient->update([...])`. Because `App\Models\Patient` declares `use HasFactory, SoftDeletes;`, Eloquent appended `and patients.deleted_at is null` to the query. The `patients.deleted_at` column is created only by `2026_06_11_001034_add_soft_deletes_to_patients_table`, which runs later in the chain, so the migration failed on every fresh database with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'patients.deleted_at'`.)

#### Scenario: source no longer references Eloquent models

- **WHEN** `grep -nE 'App\\\\Models' database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` is executed
- **THEN** the command returns zero matches
- **AND** `grep -nE '^use App\\\\Models' database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` returns zero matches

#### Scenario: backfill uses Query Builder

- **WHEN** the `up()` method body is inspected
- **THEN** it calls `DB::table('patients')` to read rows
- **AND** it calls `DB::table('patients')->where('id', $row->id)->update([...])` to write `document_number`
- **AND** it iterates over `stdClass` rows (not Eloquent model instances)

#### Scenario: migration runs on fresh MySQL

- **WHEN** `php artisan migrate:fresh` is executed against a scratch MySQL database that contains ONLY migrations dated `<= 2025_10_25_030052`
- **THEN** the migration completes without `SQLSTATE[42S22]` referencing `patients.deleted_at`
- **AND** `Schema::hasColumn('patients', 'document_number')` is true after the migration

#### Scenario: backfill format preserved

- **WHEN** the migration runs on a fresh MySQL database seeded with patients whose IDs are `1`, `2`, `42`
- **THEN** the patients' `document_number` values equal `'DOC-00000001'`, `'DOC-00000002'`, `'DOC-00000042'` respectively
- **AND** the `document_number` column carries a `UNIQUE` constraint after the migration

#### Scenario: down() reverses cleanly

- **WHEN** `php artisan migrate:rollback` is executed after the migration runs
- **THEN** the `document_number` column is dropped from `patients`
- **AND** `Schema::hasColumn('patients', 'document_number')` is false afterwards

## ADDED Requirements

### Requirement: migration-portability Eloquent guard

The test suite MUST include a regression guard in `tests/Unit/SddCheckMigrationsTest.php` that fails if any file under `database/migrations/` (any date prefix, including historical migrations) contains the literal string `App\Models\` or the regex `^use\s+App\\Models\\`. The guard MUST scan every `*.php` file in the directory and MUST report the offending filename and line number on failure.

The guard is a source-inspection test (no DB connection) and MUST pass on `php artisan test` regardless of the configured database driver.

#### Scenario: guard passes after fix

- **WHEN** `php artisan test --filter=SddCheckMigrationsTest::no_migration_references_eloquent_models` is executed after the Eloquent reference is removed
- **THEN** the test passes

#### Scenario: guard fails if violation is reintroduced

- **WHEN** a hypothetical migration file under `database/migrations/` is created whose contents include `use App\Models\Patient;` on any line
- **THEN** `php artisan test --filter=SddCheckMigrationsTest::no_migration_references_eloquent_models` fails
- **AND** the failure message names the offending file and line number
- **AND** removing the `App\Models\` reference makes the test pass again

#### Scenario: full repo no longer references Eloquent in migrations

- **WHEN** `grep -rln 'App.Models' database/migrations/` is executed
- **THEN** the command returns zero files

## Evidence

- Culprit migration: `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:19` (Eloquent call) and `:21` (`$patient->update(...)`).
- Trait source: `app/Models/Patient.php:13` (`use HasFactory, SoftDeletes;`).
- `patients.deleted_at` added later by: `database/migrations/2026_06_11_001034_add_soft_deletes_to_patients_table.php`.
- Reproduction: Engram `#302` — 1st `php artisan migrate` on `odontosuite_migtest` (virgin MySQL) emits `SQLSTATE[42S22] Column not found: 1054 Unknown column 'patients.deleted_at'`. The 2nd-run `Duplicate column name 'document_number'` is a secondary symptom of MySQL's non-transactional DDL and is NOT the root cause.
- Scope bound: `grep -rln "Models" database/migrations/` matches exactly one file at the time of this change.
- Sibling unblock: `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`) cannot satisfy its `migrate:fresh --seed` exit-0 acceptance criterion until this lands.
