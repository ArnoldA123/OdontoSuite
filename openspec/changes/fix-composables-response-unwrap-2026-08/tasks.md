# Tasks — fix-composables-response-unwrap-2026-08

> Phase: tasks · Status: ready-for-apply · Artifact store: hybrid · Delivery strategy: ask-on-risk
> Stack: Laravel 12 + Vue 3 + PHP 8.2 · Frontend-only correctness fix (no backend change) · Strict TDD
> Spec: [./specs.md](./specs.md) · Design: [./design.md](./design.md) · Finding: Engram NEW-005 (Lens A reproduction on `odontosuite` live DB — 0/41 procedures rendered, 0/9 specialties in dropdown)
> Parent: `bugfix-2026-08` archived (sibling of `hotfix-migration-chain-full-sweep-2026-08`, `hotfix-audit-log-immutable-2026-08`, `hotfix-migration-eloquent-softdeletes-2026-08`)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Total tasks | 4 |
| Total LOC | ~50 (14 file patches + 1 new test file) |
| Slices | 1 |
| Estimated changed lines | ~50 (additions + deletions) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR, single commit on `main` |
| Delivery strategy | ask-on-risk |
| Chain strategy | size:exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size:exception
400-line budget risk: Low

> **No `size:exception` needed.** ~50 LOC, single defect class (NEW-005), single commit on `main`. Well under the 400-line budget; no chained PRs required.
>
> **File-count note**: The user's launch prompt said "14 files (9 composables + 4 pages + 1 test)". The design's File Changes table adds a 5th page missed in the proposal — `resources/js/components/procedures/ImportCsvModal.vue:110` — for a true total of **15 files** in scope (9 composables + 5 Vue pages + 1 new test file). The tasks below honor the design's authoritative scope. The user count of "14 files / ~50 LOC" remains a sound rough estimate; the precise tally lands at 9 composables + 5 pages + 1 test = 15 files, ~50 LOC.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | One-level unwrap across 9 composables + 5 Vue pages + NEW static guard test | PR 1 (single commit on `main`) | `vendor/bin/phpunit tests/Unit/SddCheckJsComposablesTest.php` | agent-browser reload of Catálogo de Procedimientos (expect 41 rows) and Especialidades dropdown (expect 9 entries); `php -l` syntax check on patched JS via `pnpm lint:check` and `pnpm build` (Vite) for CI parity | `git revert <sha>` — UI returns to current broken-but-noisy state, no schema drift; guard test asserts presence of the anti-pattern, so reverting either half triggers the guard and catches the regression |

## Slice Index

| Slice | File | Findings | Cluster |
|-------|------|----------|---------|
| 01 | [tasks/01-response-unwrap-canonical.md](./tasks/01-response-unwrap-canonical.md) | NEW-005 (Lens A empirical repro) | response-unwrap-canonical |

## Implementation Order (dependency rationale)

Single-commit plan matching design Decision 2 (one defect class → one commit). Phases run sequentially inside the commit; phases can be replayed independently during verification.

- **T-01.1 Composables patch** (Phase 1) — 9 file edits; independent of phases 2–4.
- **T-01.2 Vue pages patch** (Phase 2) — 5 file edits (4 in-scope + `ImportCsvModal.vue:110` from design); independent of phases 1, 3, 4.
- **T-01.3 RED→GREEN guard test** (Phase 3) — new `tests/Unit/SddCheckJsComposablesTest.php`; the RED proof requires reverting T-01.1 or T-01.2 (see reintroduction T-01.4).
- **T-01.4 Reintroduction proof + manual smoke** (Phase 4) — proves the guard catches the pre-fix shape AND that the Catálogo / Especialidades pages render against the live DB.

## Risk Summary

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Vue dev server cache; manual verify fails until rebuild | Low | `pnpm build` (or `pnpm dev --force`) before manual smoke; `pnpm lint:check && pnpm build` is CI gate per AGENTS.md §10 |
| Guard regex false-positives on legitimate `err.response.data.message` (error path) | Low | Pattern anchored on literal `response.` prefix (mirrors `SddCheckMigrationsTest` lines 452–456 anchor recipe); string + comment `$stripPatterns` strip the literal before match |
| Guard false-positives on a JS string literal or comment that happens to spell `response.data.data` | Low | `$stripPatterns` set (single quotes, double quotes, `//` line comments, `/* */` block comments) matches the `SddCheckMigrationsTest` precedent at lines 247–252 |
| Vue dev server not running; manual verify via agent-browser fails on `pnpm dev` connection | Low | Check `pnpm dev` status before invoking agent-browser; rebuild `public/build/` once if HMR is unavailable |
| Static guard test fails locally because `resources/js/modules/**/*.{vue,js}` globs recurse into `node_modules` or other unintended paths | Very Low | AGENTS.md §3 keeps `resources/js/` frontend-only and excludes `vendor/`; the `node_modules` sibling is not under `resources/js/` so the glob is clean |
| `migrate:fresh` accidentally run against live odontosuite DB | Confirmed (hotfix precedent) | Out of scope. Test runs on SQLite in-memory via `phpunit.xml` pinning (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`); no artisan command is invoked by this change |
| `useBranches.js:50–52` dead `else if` branch removal changes a runtime path | Very Low | Design Decision 1 + extension: branch is unreachable when `response.data` is already the flat envelope; reviewer confirms by reading the surrounding `if/else` |
| `useAuditLogs.js` simplification drops a working fallback | Very Low | Pattern `response.data?.data \|\| response.data` collapses to `response.data`; semantics identical (the `||` branch never fires when `useApi.handleResponse` returns the flat envelope — verified pre-fix) |

## Task Writing Conventions

- Hierarchical IDs: `T-01.1`, `T-01.2`, `T-01.3`, `T-01.4`.
- Each task = one file or one logical unit; one session completable.
- Strict TDD: T-01.3 is RED→GREEN; T-01.4 contains a reintroduction proof.
- Acceptance criteria MUST be verifiable (grep returns empty, phpunit passes/fails with file:line, browser counts 41/9).
- Estimated LOC added/modified on every task.
- Pattern prefix `T-XX.Y` matches the archive precedent (`01-reminder-schedules-idempotent-add.md`).

## Next Step

`/sdd-apply` — single PR, single commit on `main`. No chain decision needed; `ask-on-risk` does not require a user prompt because 400-line budget risk is Low and no `size:exception` is needed.
