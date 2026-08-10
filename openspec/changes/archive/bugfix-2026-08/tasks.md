# Tasks: bugfix-2026-08 — Audit-driven remediation sweep

> Phase: tasks · Status: in-review · Artifact store: hybrid · Delivery strategy: ask-on-risk
> Stack: Laravel 12 + Vue 3.3 + Tailwind 3.3 + Sanctum 4 + Reverb 1.6 + MySQL 8.0
> Strict TDD: enabled · Backend gate: `php artisan test` · Frontend gate: `pnpm lint:check && pnpm build`
> vitest is **not** installed; frontend tests are lint + snapshot only.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Total findings | 138 (10 critical / 23 high / 31 medium / 73 low) |
| Total tasks | ~120 |
| Estimated changed lines | ~4,200 |
| Slices total | 11 |
| Per-slice budget | 400 lines (`additions + deletions`) |
| Chained PRs recommended | Yes |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main (recommended) |
| 400-line budget risk | High (whole change) — Low/Medium per slice |

Decision needed before apply: **Yes**
Chained PRs recommended: **Yes**
Chain strategy: **stacked-to-main|feature-branch-chain|size-exception|pending** → `stacked-to-main` (recommended; each slice autonomous and reversible)
400-line budget risk: **High** (whole change) — per-slice risk table below

### Per-slice risk matrix

| Slice | LOC est | Budget risk | Parallelizable | Depends on |
|-------|---------|-------------|----------------|------------|
| 01 — Critical API Mismatch | ~380 | Medium | Partial (T-01.7/T-01.8 frontend-only) | — |
| 02 — FormRequests | ~360 | Low | Yes (all requests additive) | — |
| 03 — Stubs-501 Implement | ~390 | Low | No (single module) | S02 |
| 04 — Stubs-501 Remove | ~220 | Low | Yes (per-stub commits) | S02, S08 |
| 05 — Migration Drift | ~180 | Low | No (single audit pass) | — |
| 06 — UX Visual Tokens | ~180 | Low | No (token foundation) | — |
| 07 — UX Visual Flow | ~380 | Medium | Yes (modals parallel to list views) | S06, S11 partial |
| 08 — State Handling | ~340 | Medium | Yes (per composable) | S04, S05 |
| 09 — Auth/RBAC | ~200 | Low | No (single capability) | — |
| 10 — Events Orphans | ~280 | Medium | Yes (per-event) | S07 |
| 11 — Docs Drift + Polish | ~390 | High | Yes (per-module polish) | S01, S06 |

### Suggested Work Units (chained PRs)

| # | Slice | PR goal | Focused test command | Runtime harness | Rollback boundary |
|---|-------|---------|----------------------|-----------------|-------------------|
| 1 | 01 | Critical API endpoints + audit-log read-only | `php artisan test --filter=AuditLogControllerTest,CashRegisterSummaryTest,CashRegisterSessionDetailTest,CashRegisterClosureReportTest,ReportsExportTest,ReportsPeriodTest,TransactionsListTest,MedicalRecordAttachmentTest` | curl sanctum-auth smoke against `php artisan serve` | Revert commit; controllers/routes reversible |
| 2 | 02 | FormRequest optional fields | `php artisan test --filter=FormRequestFieldsTest,AppointmentValidationTest,CashMovementValidationTest` | seed + POST against `php artisan serve` | git revert; fields are nullable |
| 3 | 03 | Reminder/ReminderTemplate CRUD + scheduled provider | `php artisan test --filter=ReminderControllerTest,ReminderTemplateControllerTest,ReminderProviderTest,ReminderScheduleStateTest` | Bus::fake + ClockFake integration | Revert controllers + provider |
| 4 | 04 | Stub-501 removals + Appointment $fillable cleanup | `php artisan test --filter=WaitingListRemovedTest,AppointmentFillableTest` | `php artisan route:list \| grep waiting` → empty | Per-stub commits; surgical revert |
| 5 | 05 | Migration drift audit + CI gate + docs sync | `php artisan test --filter=SddCheckMigrationsTest` | `php artisan migrate:status --pretend` | Revert migration additions |
| 6 | 06 | tokens.js recreation + Tailwind re-source | `pnpm build && node tests/visual/tokens.smoke.mjs` | visual regression diff | Revert tokens.js + tailwind palette |
| 7 | 07 | useMercadoPago dead call + usePermissions createMovement | `pnpm build && grep -rn "setPublishableKey" resources/js/` → empty | build smoke + manual PaymentModal render | Revert composables |
| 8 | 08 | FormRequest fields (rolled here for cross-slice test reuse) | covered in PR 2 | covered in PR 2 | covered in PR 2 |
| 9 | 09 | RBAC hardening + audit-log admin-only + rbac:check CI | `pnpm rbac:check && pnpm test:build/permissions.snap` | `php artisan route:list` golden + grep gate | Revert middleware/composable |
| 10 | 10 | 26 deprecated events deleted + sdd:check-events CI | `php artisan sdd:check-events` | grep-gate for `Event::dispatch(` matches class count | Per-event commits |
| 11 | 11 | Modals (focus trap + Escape), PaymentModal 401 surfacing, useApi.del body, EmptyState, ConfirmDialog, low polish | `pnpm lint:check && pnpm build` + axe-core manual | jsdom smoke + manual a11y | Revert component changes |

Note: PR 8 is intentionally folded into PR 2 to avoid duplicate work; PR 7 above is the FF-002 fix from the original 11-slice design, here merged into slice 09 (Auth/RBAC) since both touch RBAC/composable surface.

### Implementation Order (dependency rationale)

Pure-infrastructure slices first (no frontend dep, unblock downstream tests):
- **01 → 02 → 05 → 06**: tokens, routes, form requests, migrations.
- **03 depends on S02** (clean audit baseline).
- **08 depends on S04, S05** (form requests after attachment DELETE and transactions.list reorder).
- **09 depends on S06** (RBAC reuses tokens).
- **07 depends on S06** (visual flow reads `usePermissions`).
- **10 depends on S08, S07** (event removal after FormRequest cleanup; SDK removal before stub removal).
- **11 depends on S01, S06** (visual flow uses tokens + RBAC).

### Decision needed before apply

Per delivery strategy `ask-on-risk`, the orchestrator MUST ask the user to confirm:
1. Chain strategy (`stacked-to-main` recommended).
2. Whether slice 8 (state-handling) merges with slice 7 (visual-flow) or stays separate.
3. Whether to install vitest for component tests or rely on lint+snapshot gates.

---

## Phase Index

| Slice | File | Findings | Cluster |
|-------|------|----------|---------|
| 01 | [tasks/01-critical-api-mismatch.md](./tasks/01-critical-api-mismatch.md) | 10 criticals + API-001..007 | api-mismatch / audit-log |
| 02 | [tasks/02-form-requests.md](./tasks/02-form-requests.md) | API-008..060 high | form-requests |
| 03 | [tasks/03-stubs-501-implement.md](./tasks/03-stubs-501-implement.md) | Reminder / ReminderTemplate / Provider | stubs-501 |
| 04 | [tasks/04-stubs-501-remove.md](./tasks/04-stubs-501-remove.md) | WaitingList + 6 stubs | stubs-501 |
| 05 | [tasks/05-migration-drift.md](./tasks/05-migration-drift.md) | additive-only + CI guard | migration-drift |
| 06 | [tasks/06-ux-visual-tokens.md](./tasks/06-ux-visual-tokens.md) | UXV-001..014 | visual-tokens |
| 07 | [tasks/07-ux-visual-flow.md](./tasks/07-ux-visual-flow.md) | UXV/UXF/UXT | visual-flow |
| 08 | [tasks/08-state-handling.md](./tasks/08-state-handling.md) | composable shape + retry | state-handling |
| 09 | [tasks/09-auth-rbac.md](./tasks/09-auth-rbac.md) | RBAC + audit-log admin + rbac:check | auth-rbac |
| 10 | [tasks/10-events-orphans.md](./tasks/10-events-orphans.md) | 26 deprecated events | events-orphans |
| 11 | [tasks/11-docs-drift-polish.md](./tasks/11-docs-drift-polish.md) | docs + seeders + 39 polish | docs-drift |

## Task Writing Conventions

- Hierarchical IDs: `T-01.1`, `T-01.2`, …, `T-11.N`.
- Each task = one file or one logical unit; one session completable.
- Strict TDD: RED test task → GREEN prod task → REFACTOR task when applicable.
- Acceptance criteria MUST be verifiable (grep returns empty, endpoint returns X, test passes).
- Estimated LOC added/modified on every task.
- Dependencies explicit; no implicit ordering.

## Risk Summary

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Stub removal breaks hidden FE consumer | High | Pre-remove `grep -r "waiting-list\|stub-route" resources/js/` gate |
| FormRequest additive fields break existing payloads | Medium | All nullable; git revert restores prior behaviour |
| Modal focus trap conflicts with FullCalendar | Medium | Trap scoped to `role="dialog"` only |
| `tokens.js` palette diverges from `tailwind.config.js` | Low | Snapshot test + `pnpm build` gate |
| SQLite 28 pre-existing failures vs CI MySQL drift | Medium | New tests written against model contracts; CI gates MySQL |
| vitest not installed → component tests skipped | High | Lint + snapshot + manual a11y gate (per user decision) |

---

## Next Step

`/sdd-apply` (after orchestrator confirms chain strategy and any blocked decisions).
