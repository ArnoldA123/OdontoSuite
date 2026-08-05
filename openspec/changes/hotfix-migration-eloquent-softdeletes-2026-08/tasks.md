# Tasks — hotfix-migration-eloquent-softdeletes-2026-08

> Phase: tasks · Status: ready-for-apply · Artifact store: hybrid · Delivery strategy: ask-on-risk
> Stack: Laravel 12 + PHP 8.2 + MySQL 8.0 · Strict TDD · Backend gate: `php artisan test` (CI MySQL)
> Spec: [./specs.md](./specs.md) · Design: [./design.md](./design.md) · Finding: Engram #302 (NEW-002)
> Sibling: `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`) — this hotfix MUST land first to satisfy its `migrate:fresh --seed` exit-0 acceptance criterion.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Total tasks | 4 |
| Total LOC | ~75 |
| Slices | 1 |
| Estimated changed lines | ~75 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending (single PR justified by design §10) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Static guard + feature test + migration rewrite | PR 1 | `php artisan test --filter=SddCheckMigrationsTest::no_migration_references_eloquent_models,MigrateFreshPortabilityTest` | `php artisan migrate:fresh --seed` on scratch MySQL DB (Docker per AGENTS.md §6) | Revert the single hotfix commit; `down()` already calls `dropColumn('document_number')` so `migrate:rollback` is clean on a freshly-applied DB |

## Slice Index

| Slice | File | Findings | Cluster |
|-------|------|----------|---------|
| 01 | [tasks/01-migration-portability.md](./tasks/01-migration-portability.md) | NEW-002 (Engram #302) | migration-portability |

## Implementation Order (dependency rationale)

Strict TDD order — RED tests before the GREEN migration edit:

- **T-01.1 RED** (static guard) — independent of the migration fix; asserts the bug exists today by scanning the historical culprit file.
- **T-01.2 RED** (feature test) — independent of the migration fix; asserts `migrate:fresh --seed` reaches the `audit_logs` migration and lands the `deleted_at` + `document_number` columns.
- **T-01.3 GREEN** (migration rewrite) — single edit that turns both REDs green; idempotent against the half-applied dev DB (Decision 5).
- **T-01.4 VERIFY** — confirm the sibling unlock (`audit_logs.is_immutable` now lands via the sibling hotfix's own test) and that the 28 pre-existing SQLite failures are unchanged.

## Risk Summary

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Static guard false-positive on `App\Models`-containing strings inside comments | Low | Mirror existing `methodBody()` strip pattern (regex strip `//`, `/* */`, single-quoted, double-quoted); test against current source first |
| Feature test runs against real DB on SQLite local | Confirmed (AGENTS.md §6) | `markTestSkipped` on `DB::getDriverName() === 'sqlite'`; CI MySQL is the canonical gate |
| Dev DB has `document_number` half-applied (from prior aborted run) | Confirmed (finding #302) | Migration wrapped in `Schema::hasColumn` guard; backfill naturally idempotent (`IS NULL OR = ''`); unique index re-applied harmlessly on MySQL |
| `ALTER TABLE … ADD UNIQUE` on already-unique column fails on MySQL | Very Low | MySQL tolerates `ADD UNIQUE` on a column whose values are already unique; documented in design Decision 5 |
| Inconsistent dev DB schema state (45 Pending migrations) | Operational, out of scope | Documented in finding #302; the hotfix only repairs the SOURCE. Resuming the chain is an operational follow-up: `php artisan migrate` after pulling this hotfix. NOT in this PR. |

## Task Writing Conventions

- Hierarchical IDs: `T-01.1`, `T-01.2`, `T-01.3`, `T-01.4`.
- Each task = one file or one logical unit; one session completable.
- Strict TDD: RED test task → RED test task → GREEN prod task → VERIFY.
- Acceptance criteria MUST be verifiable (grep returns empty, test passes/fails, schema column exists).
- Estimated LOC added/modified on every task.

## Next Step

`/sdd-apply` (no chain decision needed; single-PR, low-budget hotfix; `ask-on-risk` does not require a user prompt because 400-line budget risk is Low).
