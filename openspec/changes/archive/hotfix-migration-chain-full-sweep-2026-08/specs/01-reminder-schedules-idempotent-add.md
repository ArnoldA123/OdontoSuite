# Delta for reminder-schedules-idempotent-add

## MODIFIED Requirements

### Requirement: idempotent column-add for reminder_schedules

The migration `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` MUST add `channel` (varchar(20) nullable, after `scheduled_at`) and `error_message` (text nullable, after `status`) to `reminder_schedules` such that re-running `up()` on a partially-applied or fully-applied schema is a no-op.

Each column-add call inside the `Schema::table(...)` closure MUST be guarded by a preceding `if (! Schema::hasColumn('reminder_schedules', {column}))` check in the same file. The `down()` method MUST NOT call `dropColumn(...)` for a column that does not currently exist; it MUST filter the candidate list through `array_filter(['channel','error_message'], fn ($c) => Schema::hasColumn('reminder_schedules', $c))` and pass the filtered array to `dropColumn(...)` only when non-empty.

(Previously: `up()` unconditionally invoked `$table->text('error_message')->nullable()->after('status')` while `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php` line 22 already creates that column. On a clean MySQL/MariaDB, `php artisan migrate` aborted at this migration with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'`, blocking the chain before NEW-001 (`d811f1a`) and NEW-002 (`d4f34b2`) commits could apply.)

#### Scenario: chain completes the offending migration on clean MySQL (Lens D empirical)

- **WHEN** `php artisan migrate` is run against a virgin scratch MySQL database containing ONLY migrations dated `<= 2026_08_05_020000`
- **THEN** the migration `2026_08_05_020000_add_channel_and_error_to_reminder_schedules` completes without `SQLSTATE[42S21]` referencing `error_message` or `channel`
- **AND** `Schema::hasColumn('reminder_schedules', 'channel')` returns true
- **AND** `Schema::hasColumn('reminder_schedules', 'error_message')` returns true

#### Scenario: source has hasColumn guards in up()

- **WHEN** `grep -nE "Schema::hasColumn.*reminder_schedules" database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` is executed
- **THEN** the command returns at least two matches inside `up()`: one for `channel`, one for `error_message`
- **AND** the line immediately preceding `->string('channel'` and the line immediately preceding `->text('error_message'` is `if (! Schema::hasColumn(...)`

#### Scenario: source filters dropColumn in down()

- **WHEN** `grep -nE "array_filter" database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` is executed
- **THEN** the match occurs inside the `down()` method
- **AND** the matched expression contains `fn ($c) => Schema::hasColumn('reminder_schedules', $c)`

#### Scenario: replay safety on partially-applied schema

- **WHEN** `php artisan migrate` is executed twice in succession against the same MySQL database without `migrate:fresh` in between
- **THEN** both runs exit 0
- **AND** the second run does NOT raise `Duplicate column name 'channel'` or `Duplicate column name 'error_message'`

#### Scenario: down() handles partial state

- **WHEN** `php artisan migrate:rollback --step=1` is executed on a database that has only `channel` present (no `error_message`)
- **THEN** the rollback drops `channel`
- **AND** `Schema::hasColumn('reminder_schedules', 'channel')` returns false afterwards
- **AND** the rollback does NOT raise `Unknown column 'error_message' in 'reminder_schedules'`

## ADDED Requirements

### Requirement: re-add regression guard

The test suite MUST include `tests/Unit/SddCheckMigrationsTest.php::no_migration_re_adds_already_known_column`. The test MUST scan every `*.php` file under `database/migrations/` (no `GUARD_CUTOFF_PREFIX` gate — the bug class is retroactive). The test MUST fail when a file contains a column-add call (`->addColumn`, `->string`, `->text`, `->integer`, `->datetime`, etc.) inside a `Schema::table(...)` closure for a column that is also `CREATE`d (or added) in an earlier migration in the chain, AND the add is not preceded by a `Schema::hasColumn(...)` guard in the same closure.

The failure message MUST identify the offending filename and the column name.

#### Scenario: guard passes against the post-fix tree

- **WHEN** `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` is executed after the fix lands
- **THEN** the test passes

#### Scenario: guard fails when the unguarded line is reintroduced

- **WHEN** the `if (! Schema::hasColumn('reminder_schedules', 'error_message'))` guard in `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` is removed
- **THEN** `php artisan test --filter=SddCheckMigrationsTest::no_migration_re_adds_already_known_column` fails
- **AND** the failure message names `2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` and the column `error_message`

#### Scenario: guard runs cleanly on SQLite in-memory

- **WHEN** `php artisan test --filter=SddCheckMigrationsTest` is executed with the default phpunit.xml configuration (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- **THEN** `no_migration_re_adds_already_known_column` passes (pure string scan, no DB connection required)
- **AND** `no_migration_references_eloquent_models` (NEW-002 guard) still passes

#### Scenario: first-add columns are not flagged

- **WHEN** the guard scans `database/migrations/2026_06_11_001034_add_soft_deletes_to_patients_table.php` (which legitimately adds `patients.deleted_at` for the first time, with no prior CREATE in the chain)
- **THEN** no violation is reported
- **AND** the file passes the guard

## Evidence

- Broken migration line (pre-fix): `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php:21` — `$table->text('error_message')->nullable()->after('status')`.
- Pre-existing column source: `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php:22` — `$table->text('error_message')->nullable();`.
- Lens D empirical reproduction: on `odontosuite_migtest` (MariaDB 10.4, virgin scratch DB) without the guard, the chain aborts with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'` at the offending migration; with the `Schema::hasColumn` guard applied to both `channel` and `error_message`, all 108 migrations complete and `d811f1a` (NEW-001) and `d4f34b2` (NEW-002) hold without re-application.
- Pattern precedent: `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:35` — `if (! Schema::hasColumn('patients', 'document_number'))` (commit `d4f34b2`, NEW-002). Static-guard template: `tests/Unit/SddCheckMigrationsTest.php::no_migration_references_eloquent_models` reuses `allMigrations()` (line 70) and `$stripPatterns` (lines 241–246).
