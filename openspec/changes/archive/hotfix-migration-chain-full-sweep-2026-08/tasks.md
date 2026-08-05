# Tasks — hotfix-migration-chain-full-sweep-2026-08

> Phase: tasks · Status: ready-for-apply · Artifact store: hybrid · Delivery strategy: ask-on-risk
> Stack: Laravel 12 + PHP 8.2 + MySQL 8.0 · Strict TDD · Backend gate: `php artisan test` (CI MySQL)
> Spec: [./specs.md](./specs.md) · Design: [./design.md](./design.md) · Finding: Engram NEW-003 (Lens A/B/C/D consensus; Lens D reproduced on `odontosuite_migtest`)
> Parent: `bugfix-2026-08` archived (slice 03 / T-03.6 introduced the offending migration)
> Siblings (NOT modified): `hotfix-audit-log-immutable-2026-08` (commit `d811f1a`), `hotfix-migration-eloquent-softdeletes-2026-08` (commit `d4f34b2`)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Total tasks | 4 |
| Total LOC | ~52 |
| Slices | 1 |
| Estimated changed lines | ~52 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR (2 commits stacked on `fix/migration-new-003-2026-08`, then merge to `main`) |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

> **No `size:exception` needed.** User-approved scope = 2 commits, ~52 LOC, single PR on `main`. Well under the 400-line budget; no chained PRs required.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Idempotent column-add + retroactive static guard | PR 1 (2 stacked commits on `fix/migration-new-003-2026-08`) | `php artisan test --filter=SddCheckMigrationsTest` | `php artisan migrate:fresh --force` on scratch MySQL DB `odontosuite_migtest` | Revert either commit independently — Commit A reversion breaks the chain AND triggers the guard test (self-blocking); Commit B reversion leaves the fix in place (still green) |

## Slice Index

| Slice | File | Findings | Cluster |
|-------|------|----------|---------|
| 01 | [tasks/01-reminder-schedules-idempotent-add.md](./tasks/01-reminder-schedules-idempotent-add.md) | NEW-003 (Lens A/B/C/D) | reminder-schedules-idempotent-add |

## Implementation Order (dependency rationale)

Two-commit plan matching design Decision 5 (fix-first, test-second) — keeps every commit boundary green:

- **T-01.1 GREEN** (Commit A — migration fix) — independent; idempotent guard on `up()` and `down()` for `channel` and `error_message`.
- **T-01.2 RED→GREEN** (Commit B — static guard test) — independent test; passes against the post-A fixed tree.
- **T-01.3 REINTRODUCTION PROOF** (Commit B follow-up) — revert the `hasColumn` wrapper locally, observe guard failure with filename + column + line number, restore the wrapper. Documents that the guard is not merely a place-holder.
- **T-01.4 VERIFY** (cross-slice) — full `php artisan test` plus scratch-DB `migrate:fresh --force` exit-0; confirm no new SQLite failures, sibling unlocks remain satisfiable.

## Risk Summary

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Static-guard regex false-positive on column-add shape inside a string literal or comment | Low | Mirror `$stripPatterns` already battle-tested in `no_migration_references_eloquent_models` (lines 241–246); scan-line walks use the same per-line strip |
| Guard fails on a first-add legitimate column (e.g. `patients.deleted_at` in `2026_06_11_001034_add_soft_deletes_to_patients_table`) | Low | Guard only flags re-adds that lack a `Schema::hasColumn(...)` guard in the same closure; first-add migrations are unaffected |
| `array_filter` shape inside `down()` is mistyped (e.g. misses `array_values(...)`) | Very Low | Pattern is verbatim from design §Decision 2; apply phase copies from design contract |
| Dev DB already has `reminder_schedules.channel` added but NOT `error_message` (half-applied state from prior aborted run) | Confirmed (NEW-003) | Both `up()` guards are independent; `down()` `array_filter` drops only present columns — partial-state-safe |
| The static guard test runs on SQLite in-memory and behaves differently | Very Low | Guard is pure string scan; no DB connection (extends `PHPUnit\Framework\TestCase`); matches existing guard style |
| Migration file already on disk in the "fixed" state (orchestrator preflight work) | Confirmed | Task T-01.1 documents the on-disk state as the GREEN reference; Commit A simply finalizes the on-disk content. No behavior divergence. |

## Task Writing Conventions

- Hierarchical IDs: `T-01.1`, `T-01.2`, `T-01.3`, `T-01.4`.
- Each task = one file or one logical unit; one session completable.
- Strict TDD where applicable: T-01.2 documents RED-on-broken-tree / GREEN-on-fixed-tree; T-01.3 is the reintroduction proof.
- Acceptance criteria MUST be verifiable (grep returns empty, test passes/fails, schema column exists).
- Estimated LOC added/modified on every task.

## Next Step

`/sdd-apply` — single PR, 2 stacked commits on `fix/migration-new-003-2026-08`. No chain decision needed; `ask-on-risk` does not require a user prompt because 400-line budget risk is Low and no `size:exception` is needed.
