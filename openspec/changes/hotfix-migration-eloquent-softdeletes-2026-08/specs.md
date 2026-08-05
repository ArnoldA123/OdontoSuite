# Specs Index — hotfix-migration-eloquent-softdeletes-2026-08

## Change Summary

Hotfix for NEW-002: the migration `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php` calls `\App\Models\Patient::whereNull(...)->orWhere(...)->get()` to backfill data. `App\Models\Patient` uses the `SoftDeletes` trait, so Eloquent injects `and patients.deleted_at is null` into the query — but `patients.deleted_at` is not created until migration `2026_06_11_001034_add_soft_deletes_to_patients_table` runs 8 months later in the chain. The migration is provably unrunnable on every fresh MySQL database with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'patients.deleted_at'`. The fix replaces the Eloquent query with `DB::table('patients')` and adds a regression guard.

## Specs

| File | Domain | Type | Requirements | Scenarios |
|------|--------|------|--------------|-----------|
| [specs/01-migration-portability.md](specs/01-migration-portability.md) | migration-portability | Delta | 1 MODIFIED + 1 ADDED | 7 |

## Coverage

- Happy paths: covered (migration runs; backfill produces `DOC-{8-digit-padded-id}`; `document_number` is unique).
- Edge cases: covered (migration no longer depends on `deleted_at`; stdClass iteration replaces Eloquent).
- Error states: covered (regression guard fails on any future migration referencing `App\Models\`; migration does not emit `Unknown column 'patients.deleted_at'`).

## Trigger

- NEW-002 (Engram `#302`) — reproduced on virgin scratch DB `odontosuite_migtest` on 2026-08-05; live `odontosuite` DB was NOT modified.

## Parent

- `bugfix-2026-08` (archived) — this is the second extracted hotfix from the same audit cycle.

## Sibling

- `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`) — its `migrate:fresh --seed` acceptance criterion becomes satisfiable once this lands. This hotfix MUST land first.
