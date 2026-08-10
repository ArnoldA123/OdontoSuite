# Specs Index — bugfix-2026-08

Delta specs covering all 138 audit findings (10 critical, 23 high, 31 medium, 73 low) split across 11 review-budget-safe slices.

## Executive Summary

| Severity | Count | Strategy |
|---|---|---|
| Critical | 10 | Slice 01 (Critical API Mismatch) — production blockers first |
| High | 23 | Slices 01, 02, 03 (Critical API, FormRequests, Stubs-Implement) |
| Medium | 31 | Slices 02, 03, 05, 09 (FormRequests, Stubs, Migration drift, RBAC) |
| Low | 73 | Slices 06, 07, 08, 10, 11 (Visual tokens/flow, State, Events, Docs/polish) |

## Slice Index

| # | Slice | Findings covered | Spec file |
|---|---|---|---|
| 1 | Critical API Mismatch | BF-001..010 + API-001..007 (highest priority: audit-log, RBAC bypass, SDK call, cash-register endpoints, attachments DELETE, response shape) | [specs/01-critical-api-mismatch.md](./specs/01-critical-api-mismatch.md) |
| 2 | FormRequests | API-008..060 (high subset: missing optional fields, localized errors, concept whitelists) | [specs/02-form-requests.md](./specs/02-form-requests.md) |
| 3 | Stubs-501 Implement | ReminderController, ReminderTemplateController, ReminderProvider (3 stub→real conversions) | [specs/03-stubs-501-implement.md](./specs/03-stubs-501-implement.md) |
| 4 | Stubs-501 Remove | WaitingListController + 6 other low-priority stubs (orphan route removal) | [specs/04-stubs-501-remove.md](./specs/04-stubs-501-remove.md) |
| 5 | Migration Drift | Additive-only migration policy, schema doc sync, CI guard | [specs/05-migration-drift.md](./specs/05-migration-drift.md) |
| 6 | UX Visual Tokens | UXV-001..014 (tokens.js recreation, hex replacement, palette coverage) | [specs/06-ux-visual-tokens.md](./specs/06-ux-visual-tokens.md) |
| 7 | UX Visual Flow | UXV/UXF remainder (Escape, focus trap, PaymentModal 401, useApi.del body, empty states) | [specs/07-ux-visual-flow.md](./specs/07-ux-visual-flow.md) |
| 8 | State Handling | Composable shape, error localization, retry, focus refresh, optimistic rollback | [specs/08-state-handling.md](./specs/08-state-handling.md) |
| 9 | Auth / RBAC | usePermissions.createMovement, audit-log admin-only, mapping hardening, CI RBAC gate | [specs/09-auth-rbac.md](./specs/09-auth-rbac.md) |
| 10 | Events / Orphans | 26 deprecated events removed, listener error isolation, coverage gate | [specs/10-events-orphans.md](./specs/10-events-orphans.md) |
| 11 | Docs Drift + Polish | AGENTS.md sync, seeders idempotency, low-priority polish bundle (39), visual baseline, i18n gate | [specs/11-docs-drift-polish.md](./specs/11-docs-drift-polish.md) |

## Coverage

- Happy paths: covered (every requirement has at least one When/Then scenario).
- Edge cases: covered (404/403/422/405 paths in every feature spec).
- Error states: covered (PaymentModal 401 surface, listener error isolation, optimistic rollback).

## Cluster-to-Slice Mapping

| Cluster | Findings | Slices |
|---|---|---|
| api-mismatch (42) | Backend routes, FormRequests | 01, 02 |
| stubs-501 (11) | Controllers to implement or remove | 03, 04 |
| migration-drift (~6) | Additive-only policy | 05 |
| visual-tokens (14) | tokens.js, hex literals | 06 |
| visual-flow (28) | Escape, focus trap, 401, empty states | 07 |
| state-handling (~9) | Composable shape, retry, focus refresh | 08 |
| auth-rbac (~3) | usePermissions, RBAC gate | 01, 09 |
| events-orphans (~26) | Deprecated events | 10 |
| docs-drift (~3) | AGENTS.md, scripts | 11 |
| misc / low (73) | Polish bundle, seeders | 11 |

## Test Discipline

- Strict TDD enabled (per `openspec/config.yaml:91`).
- Every requirement declares its test obligation in a matrix at the end of its spec file.
- New tests must pass on CI MySQL; pre-existing SQLite failures (28) are documented tech debt and out of scope.
- Backend test runner: `php artisan test`. Frontend: ESLint + Prettier + build; component tests where added use Vitest.
- No coverage enforcement; the test obligation matrices are advisory but required for each requirement.

## Conventions Applied

- RFC 2119 keywords (`MUST`, `SHALL`, `SHOULD`, `MAY`) on every requirement.
- `WHEN/THEN/AND` OpenSpec scenario syntax.
- Every requirement cites file:lines evidence from the audit.
- MODIFIED sections carry a `(Previously: …)` note.
- REMOVED sections carry `Reason:` and `Migration:` notes.
- No implementation details — specs describe WHAT, not HOW.

## Next Step

After `sdd-design` and gatekeeper pass, proceed to `sdd-tasks`. Each spec file maps to one chained-PR slice (≤400 LOC each).
