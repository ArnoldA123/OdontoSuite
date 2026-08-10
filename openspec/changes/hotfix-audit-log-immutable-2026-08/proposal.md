# Proposal: hotfix-audit-log-immutable-2026-08

## Intent

Remediate NEW-001: the migration `database/migrations/2026_08_05_000000_add_audit_log_immutable.php` (introduced in change `bugfix-2026-08` slice 01 / T-01.10) references `->after('description')`, but `audit_logs` has no `description` column. This blocks `php artisan migrate --seed` on any clean setup (CI or new install) with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'description'`. The flag `is_immutable` was a future-proof hook that slice 02 never wired, so the column has no app/model callers.

## Scope

### In Scope

- Replace `->after('description')` with `->after('user_agent')` in the existing migration file (additive-only; column stays nullable boolean default `false`).
- Add a migration-portability feature test that asserts `Schema::hasColumn('audit_logs', 'is_immutable')` after `migrate:fresh`.
- Add a regression assertion in CI that `migrate:fresh --seed` exits 0.
- Single PR, ≤30 LOC.

### Out of Scope

- Wiring `is_immutable` into any model, policy, controller, or seeder (no consumer exists; deferred until audit-log hardening ships).
- Reworking other audit-log filters or RBAC (covered by change `bugfix-2026-08`).
- Touching the SQLite-only MODIFY COLUMN portability debt (still bypassed by CI-MySQL gate).
- New migrations, new columns, or destructive ALTER.

## Capabilities

### New Capabilities

None — additive column already introduced in slice 01; this change only repairs it.

### Modified Capabilities

None at the spec level. The fix is mechanical: align the migration's `->after(...)` clause to the real schema. No requirements change.

## Approach

| Step | File | Action |
|---|---|---|
| 1 | `database/migrations/2026_08_05_000000_add_audit_log_immutable.php` | Change `->after('description')` → `->after('user_agent')`. Keep nullable + default false + dropColumn in `down()`. |
| 2 | `tests/Feature/Database/AuditLogMigrationTest.php` (new) | Two assertions: `migrate:fresh` succeeds; `Schema::hasColumn('audit_logs', 'is_immutable')` is true. |
| 3 | `php artisan migrate:fresh --seed` | Smoke run on local MySQL + CI MySQL. |

The migration is unreleased — every `migrate --seed` failed at it, so no environment has a successful half-applied state. We correct the file in place rather than adding a corrective migration. If a partial apply is found later (extremely unlikely), rollback is `git revert` + manual `ALTER TABLE`.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `database/migrations/2026_08_05_000000_add_audit_log_immutable.php` | Modified | `->after('description')` → `->after('user_agent')`. |
| `tests/Feature/Database/AuditLogMigrationTest.php` | New | Migration-portability regression test. |
| CI pipeline (`migrate:fresh --seed` job) | Implicit | Now green on clean checkout. |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Partial apply on a dev machine (column created without `after`, but `description` missing) | Very Low | Migration uses `->after('user_agent')` which is always present; no-op on re-run. Manual `ALTER TABLE … DROP COLUMN is_immutable` then re-migrate is the only fallback. |
| Test brittleness — `migrate:fresh` inside feature test touches the real DB | Low | Gate the test to MySQL via `RefreshDatabase` trait; skip on SQLite to avoid the 28 pre-existing SQLite failures. |
| Drift between test and CI run-order | Low | Same `php artisan test` command in both; no parallel migrations. |

## Rollback Plan

`git revert <sha>` of the hotfix commit. Because `down()` already calls `dropColumn('is_immutable')`, the next `migrate:rollback` cleanly removes the column. No data loss (column was nullable default `false` with no writers).

## Dependencies

- No new packages.
- Local MySQL + CI MySQL must remain the merge gate (SQLite bypass unchanged).

## Success Criteria

- [ ] `php artisan migrate:fresh --seed` exits 0 on a clean MySQL DB.
- [ ] `php artisan test` exits 0 (no regression on existing 178 passed / 104 pre-existing SQLite fails).
- [ ] `pnpm lint:check && pnpm build` exits 0.
- [ ] New `AuditLogMigrationTest` covers `Schema::hasColumn('audit_logs', 'is_immutable')`.
- [ ] CodeGraph impact delta = 0 (only the migration body changed; no callers exist).
