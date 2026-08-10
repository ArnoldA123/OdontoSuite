# Delta for audit-log-immutable

## MODIFIED Requirements

### Requirement: audit_logs.is_immutable column

The system MUST add a nullable boolean `is_immutable` column on the `audit_logs` table to flag write-protected rows. The column MUST be added in a position that depends only on columns that exist in the base `audit_logs` schema defined by `database/migrations/2025_09_20_082400_create_audit_logs_table.php`.

The migration MUST NOT reference any column absent from the base schema (e.g., `description`, `metadata`) in its `->after(...)` clause.

(Previously: The migration `database/migrations/2026_08_05_000000_add_audit_log_immutable.php` declared `->after('description')`, a column that does not exist in `audit_logs`. This caused `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'description'` on MySQL and blocked `php artisan migrate:fresh --seed` on every clean setup.)

#### Scenario: migration runs cleanly on MySQL

- **WHEN** `php artisan migrate:fresh` is run against a MySQL connection
- **THEN** the migration `2026_08_05_000000_add_audit_log_immutable` runs without errors
- **AND** the `audit_logs` table contains the `is_immutable` column

#### Scenario: column position is portable

- **WHEN** the migration's `up()` is executed
- **THEN** the `is_immutable` column is placed after a column that exists in the base `audit_logs` schema
- **AND** the placement MUST NOT reference any column missing from `2025_09_20_082400_create_audit_logs_table` (no `description`, no `metadata`)

#### Scenario: column type and nullability preserved

- **WHEN** the migration is applied
- **THEN** `is_immutable` is `boolean`, nullable, with default `false`
- **AND** the `down()` migration drops the column without error

## ADDED Requirements

### Requirement: migration-portability test gate

The test suite MUST include a regression test that detects migrations referencing non-existent columns before runtime. The test MUST assert that `php artisan migrate:fresh --seed` exits successfully against the configured MySQL connection.

#### Scenario: feature test verifies schema after migrate

- **WHEN** `tests/Feature/Api/AuditLogMigrationTest.php` runs against MySQL
- **THEN** `Schema::hasColumn('audit_logs', 'is_immutable')` returns true after the migration
- **AND** `Schema::hasColumn('audit_logs', 'description')` returns false, confirming the bad anchor never existed in the base schema

#### Scenario: full migrate:fresh runs to completion

- **WHEN** `php artisan migrate:fresh --seed` is executed
- **THEN** all migrations apply without error
- **AND** the seed completes successfully

## Evidence

- Broken anchor: `database/migrations/2026_08_05_000000_add_audit_log_immutable.php:18` — `->after('description')`
- Base schema: `database/migrations/2025_09_20_082400_create_audit_logs_table.php:14-31` — columns are `id, user_id, user_role, entity_type, entity_id, action, old_values, new_values, ip_address, user_agent, created_at, updated_at` (no `description`, no `metadata`)
- Finding source: Engram `#294` — NEW-001, agent-browser functional validation 2026-08-05
