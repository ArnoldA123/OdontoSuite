# Delta for Migration Drift — Slice 05

Resolves migration drift findings: new migrations MUST be additive (new columns nullable, new tables, new indexes) and MUST NOT include destructive ALTER (no DROP COLUMN, no MODIFY COLUMN that changes types). Adds a CI guard so future migrations stay within the additive envelope.

## ADDED Requirements

### Requirement: All New Migrations Are Additive

The system MUST permit migrations that ADD a nullable column, ADD a new table, ADD an index, or ADD a foreign key. The system MUST reject (via CI gate) any migration containing `DROP COLUMN`, `MODIFY COLUMN` that changes type, `RENAME COLUMN` to a different type, or non-nullable column additions without a default.

Evidence: 28 pre-existing tests fail on SQLite local due to MySQL-only `MODIFY COLUMN`; we want to keep CI MySQL green while making new migrations portable.

#### Scenario: nullable column added cleanly

- WHEN a new migration adds `ALTER TABLE appointments ADD COLUMN procedure_id BIGINT UNSIGNED NULL`
- THEN migration applies on both SQLite and MySQL
- AND the new column is nullable in the schema

#### Scenario: destructive ALTER rejected by CI

- WHEN a new migration contains `ALTER TABLE appointments DROP COLUMN notes`
- THEN the CI guard step `php artisan sdd:check-migrations` exits non-zero
- AND the PR fails the lint job

Test obligation: Unit test for the CI guard `tests/Unit/SddCheckMigrationsTest.php`.

---

### Requirement: Schema Documentation Updated

The system MUST update `docs/database/schema.md` (or equivalent) on each migration add with the table, columns, indexes, and foreign keys introduced.

Evidence: No machine-checked link between migrations and docs.

#### Scenario: doc follows migration

- WHEN a new migration `2026_08_06_000001_add_procedure_id_to_appointments.php` is added
- THEN `docs/database/schema.md` includes a section for the change

Test obligation: Markdown link checker (custom script) + manual review.

---

### Requirement: Migration Drift Manifest

The system MUST publish `openspec/changes/bugfix-2026-08/findings-map.md` with a `migration-drift` subsection enumerating every new migration added in this change.

#### Scenario: manifest lists migrations

- WHEN the file is read
- THEN every new migration filename appears under `migration-drift`

Test obligation: Static check.

---

## MODIFIED Requirements

### Requirement: New Migrations Documented in ADR Index

(Previously: no central record; now linked from the change.)

#### Scenario: ADR index updated

- WHEN `docs/architecture/decisions/README.md` is read
- THEN the migration-drift subsection of bugfix-2026-08 is referenced

---

## REMOVED Requirements

None for this slice (migrations are additive; nothing to remove).

---

## Test Obligation Matrix

| Requirement | Test type | Path |
|---|---|---|
| All New Migrations Are Additive | Unit | `tests/Unit/SddCheckMigrationsTest.php` |
| Schema Documentation Updated | Markdown lint | custom script |
| Migration Drift Manifest | Static check | review |
| ADR Index Updated | Static check | review |
