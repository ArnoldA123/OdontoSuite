# Specs Index — hotfix-audit-log-immutable-2026-08

## Change Summary

Hotfix for NEW-001: migration `2026_08_05_000000_add_audit_log_immutable` referenced the non-existent `description` column on `audit_logs`, blocking `migrate:fresh --seed` on every clean MySQL setup with `SQLSTATE[42S22]`. The fix aligns the migration's `->after(...)` clause to a real column (`user_agent`) and adds a regression test.

## Specs

| File | Domain | Type | Requirements | Scenarios |
|------|--------|------|--------------|-----------|
| [specs/01-audit-log-immutable.md](specs/01-audit-log-immutable.md) | audit-log-immutable | Delta | 1 MODIFIED + 1 ADDED | 5 |

## Coverage

- Happy paths: covered (migration applies, column exists, down drops it).
- Edge cases: covered (column position references only real schema columns).
- Error states: covered (regression test asserts `migrate:fresh --seed` exits 0).

## Trigger

- NEW-001 (Engram `#294`) — discovered by post-bugfix functional validation with agent-browser on 2026-08-05.

## Parent

- `bugfix-2026-08` — slice 01 / T-01.10 introduced the broken migration; this hotfix repairs it in place.
