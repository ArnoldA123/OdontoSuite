# Specs Index — hotfix-migration-chain-full-sweep-2026-08

## Change Summary

Hotfix for NEW-003: `database/migrations/2026_08_05_020000_add_channel_and_error_to_reminder_schedules.php` unconditionally re-adds `reminder_schedules.error_message`, which is already created at `database/migrations/2025_09_20_082355_create_reminder_schedules_table.php:22`. On a clean MySQL/MariaDB, the chain aborts with `SQLSTATE[42S21]: 1060 Duplicate column name 'error_message'`. The fix wraps both `channel` and `error_message` adds in `Schema::hasColumn(...)` guards (mirroring the `d4f34b2` NEW-002 precedent at `database/migrations/2025_10_25_030052_add_document_number_to_patients_table.php:35`) and adds a retroactive static guard test.

## Specs

| File | Domain | Type | Requirements | Scenarios |
|------|--------|------|--------------|-----------|
| [specs/01-reminder-schedules-idempotent-add.md](specs/01-reminder-schedules-idempotent-add.md) | reminder-schedules-idempotent-add | Delta | 1 MODIFIED + 1 ADDED | 8 |

## Coverage

- Happy paths: covered (migration applies; both columns present; chain reaches NEW-001 and NEW-002 commits).
- Edge cases: covered (partial-state replay; first-add columns are not flagged by the new guard).
- Error states: covered (unguarded re-add triggers the new guard failure with filename + column).

## Trigger

- NEW-003 (Engram `#311`, Lens A/B/C/D consensus) — Lens D reproduced the blocker on a virgin scratch DB `odontosuite_migtest` (MariaDB 10.4) on 2026-08-05.

## Parent

- `bugfix-2026-08` (archived) — slice 03 / T-03.6 introduced the offending migration; this hotfix repairs it in place.

## Siblings (NOT modified by this change)

- `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`, NEW-001) — source-correct, unlanded; chain aborts at NEW-003 before reaching it.
- `hotfix-migration-eloquent-softdeletes-2026-08` (commit `d4f34b2`, NEW-002) — source-correct, unlanded; provides the `hasColumn` precedent and the guard-test template this spec reuses.
