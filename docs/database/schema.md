# Database Schema Reference — OdontoSuite

> Generated as part of `bugfix-2026-08` slice 05 (migration-drift).
> This file documents the additive migrations introduced by the change.

## Slice 05 migrations

### 2026_08_05_030000_drop_legacy_specialties_json_column.php

- **Table**: `users`
- **Action**: drops the legacy `specialties` JSON column (added by `2025_10_24_202936_add_multi_sede_fields_to_existing_tables.php` but never populated).
- **Reversible**: yes — `down()` re-adds the column as nullable JSON.
- **Source-of-truth**: the `user_specialties` pivot (ADR-0007) remains the only path application code reads specialty data from.
- **Preserved**: `users.specialty` (legacy string, ADR-0007) is untouched.

## Policy: additive-only migrations

All migrations added on or after 2026-08-05 MUST follow the additive-only policy
enforced by `tests/Unit/SddCheckMigrationsTest.php`:

| Allowed | Forbidden |
|---|---|
| `Schema::table` with `->nullable()` additions | `DROP COLUMN` outside `down()` |
| `Schema::create` for new tables | `MODIFY COLUMN` (raw ALTER) without `DB::getDriverName() === 'mysql'` guard |
| `Schema::table` with `->unique()` recreations | `DATE_SUB` / `DATE_ADD` without driver guard |
| `Schema::table` with `->index()` | `dropUnique()` without immediate `->unique()` recreate |

The only carve-out is the `drop_legacy_*` filename prefix, which signals a
reversible legacy cleanup (e.g. column was never used, `down()` re-adds it).

## Driver-conditional guard

When a migration contains MySQL-only constructs (`MODIFY COLUMN`, `DATE_SUB`,
`DATE_ADD`), wrap the body in:

```php
public function up(): void
{
    if (DB::getDriverName() === 'sqlite') {
        return;
    }
    // ... MySQL-only logic ...
}
```

This keeps SQLite local tests passing while preserving MySQL semantics in CI.
The precedent was established in `2025_10_14_123001_fix_appointments_status_enum.php`
(slice 02) and extended in `2026_06_02_173228_fix_appointments_timezone_offset.php`
(slice 05).

## CI guard

`tests/Unit/SddCheckMigrationsTest.php` enforces the policy by scanning
`database/migrations/` for files dated `>= 2026_08_05` and flagging forbidden
patterns. Pre-existing migrations are exempt (debt documented in AGENTS.md §6).

## Source-of-truth summary

| Concern | Source-of-truth | Legacy (deprecated) |
|---|---|---|
| User specialty | `user_specialties` pivot (ADR-0007) | `users.specialty` string, `users.specialties` JSON (REMOVED slice 05) |
| Procedure specialty | `procedure_catalog.specialty_id` FK (ADR-0008) | `procedure_catalog.legacy_specialty` string |