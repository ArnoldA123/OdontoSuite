# Proposal: bugfix-2026-08 — Audit-driven remediation sweep (138 findings)

## Intent

Resolve the 138 unique findings from the 2026-08-05 audit of OdontoSuiteV2 (10 critical, 23 high, 31 medium, 73 low). The audit exposes production blockers (audit-log 500s, RBAC bypass on cash movements, `setPublishableKey` calls into a non-existent SDK v2 method, 7 missing cash-register endpoints, broken `DELETE /medical-records/attachments/{id}`) and a long tail of UX/visual, contract, and stub-501 issues. Fixing them now restores trust in core cash-box, audit, and payment flows before any feature work lands.

## Scope

### In Scope

- All 138 findings (BF/FF/API/UXF/UXV/UXT ids) grouped by cluster.
- 11 stub-501 controllers: implement real handlers OR remove orphan routes (per decision table below).
- 10 critical bugs that block production.
- 60 API contract mismatches (frontend consumes endpoints/routes that do not exist or return wrong shape).
- 28 visual-flow fixes (focus traps, modals, empty states, payment error surfacing).
- 14 visual-token fixes (broken `tokens.js` link, hard-coded colors, spacing).
- 17 frontend correctness fixes (RBAC bypass, dead SDK call, broken DELETE).
- 28 backend fixes (missing fields in FormRequests, missing endpoints, audit-log 500).

### Out of Scope (Non-goals)

- Architectural refactors (Service/Controller split, DTO introduction, new DI container).
- The 28 pre-existing SQLite failures (TECH DEBT — MODIFY COLUMN not portable; CI MySQL passes).
- Wiring listeners for the 26 `@deprecated` events (documented debt; out unless a listener is required by a Critical fix).
- New features (dark mode, multi-language, mobile app).
- Removing or replacing Sanctum 4, Reverb, DomPDF, MercadoPago SDK 3.10.
- Performance/scale work (no N+1 sweeps, no caching layer).
- Test coverage enforcement (gates not adopted yet).

## Approach — Cluster Strategy

| Cluster | Count | Strategy |
|---|---|---|
| `api-mismatch` | 42 | Add missing routes/controllers (cash-register summary, sessions/{id}, closure-report, reports/export/{format}, reports/period, transactions/list, attachments DELETE). Fix FormRequest fields (`procedure_id`, `treatment_plan_id`, `branch_id`, `ends_at`, `payment_method_id`). Align response shape to `{data, meta.message}`. |
| `visual-flow` | 28 | Add Escape close + focus trap to all modals (WCAG 2.1.1). Surface 401 errors in `PaymentModal`. Replace `useApi.del` no-body calls with proper resource refs. Validate empty states. |
| `visual-tokens` | 14 | Recreate `resources/js/design-system/tokens.js` (AGENTS.md link is broken). Replace hard-coded hex/Tailwind palette with token references. |
| `stubs-501` | 11 | Decide per controller: implement if a real workflow exists (Reminder, ReminderTemplate, WaitingList); remove orphan routes if no spec/UI consumes them. |
| `rbac-bypass` | 2 | Expose `createMovement` on `usePermissions`; harden `permissions` route→method mapping. |
| `sdk-call` | 1 | Drop `useMercadoPago.setPublishableKey` call; key is server-side only in SDK v2. |
| `audit-log` | 1 | Replace `apiResource` on `AuditLogController` with explicit `index`+`show` (no store/update/destroy). |
| `extras` (low) | 39 | Ad-hoc tooltip, label, copy, and a11y fixes rolled into the slice that owns the module. |

## Key Decisions

| Decision | Options | Recommendation | Rationale |
|---|---|---|---|
| Stub-501 controllers | Implement vs Remove | **Per-controller** (table below) | Reminder/ReminderTemplate have UI consumed by reception; ReminderProvider is just a scheduled job; WaitingList has no UI yet. |
| `@deprecated` events | Wire listener vs Delete | **Delete if no listener exists** | 26 events already deprecated; deleting removes cognitive load. |
| Non-portable migrations | Document MySQL-only vs Abstract | **Document MySQL-only** | 28 SQLite failures are CI-bypassed; refactoring migrations is high-risk + high-churn. |
| AuditLog routes | Remove vs Implement show+byX | **Replace apiResource with index+show** | Audit logs are read-only; user requirement is "show me who did what" — byX filters are a v2 task. |
| Cash-register endpoints | New controller vs Add to existing | **New `CashRegisterReportController` + `CashRegisterSummaryController`** | SRP; matches existing controller granularity (one per resource family). |

### Stub-501 decision table

| Controller | Decision | Reason |
|---|---|---|
| `ReminderController` | Implement | Reception uses it for patient follow-ups. |
| `ReminderTemplateController` | Implement | Admin panel lists/edits templates. |
| `WaitingListController` | Remove | No UI consumes it; nothing in patient/prestation flow. |
| `ReminderProvider` (scheduled) | Implement | Cron job drives reminders; integration test must cover. |
| `AuditLogController` (501 partial) | Replace apiResource | See above. |
| Other 6 stubs (low priority) | Triage per-file during apply | Each touches <50 LOC. |

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `app/Http/Controllers/Api/*` | Modified | 7 missing routes implemented, 11 stubs resolved, FormRequests patched. |
| `routes/api.php` | Modified | New routes registered; orphan stubs removed. |
| `resources/js/composables/usePermissions.js` | Modified | `createMovement` exposed; mapping hardened. |
| `resources/js/composables/useMercadoPago.js` | Modified | `setPublishableKey` call removed. |
| `resources/js/design-system/tokens.js` | New | Replaces broken link from AGENTS.md. |
| `resources/js/components/ui/*Modal.vue` | Modified | Escape + focus trap standardized. |
| `resources/js/components/PaymentModal.vue` | Modified | 401 error surfaced. |
| `resources/js/composables/useApi.js` | Modified | `del` accepts body. |
| `app/Http/Requests/*` | Modified | Missing fields added. |
| `tests/Feature/Api/*` | New/Modified | Cover new endpoints + stub decision paths. |

## Slicing Strategy (chained PRs, 400-line budget)

Total estimated: ~4,200 LOC across 138 findings → 11 chained PRs (≤400 LOC each). Each slice is autonomous, reviewable, and rollback-able.

| # | Slice | Findings | LOC est. | Depends on | Rollback |
|---|---|---|---|---|---|
| 1 | `tokens.foundations` | UXV-001..014 (14) | ~180 | — | Revert commit; no API change. |
| 2 | `audit-log.readonly` | BF-004 (1) | ~120 | — | Revert audit route revert. |
| 3 | `cash-register.routes` | API-002,003,004,005,006 (5) | ~380 | — | Revert routes+controllers; no migration. |
| 4 | `attachments.delete` | API-001 (1) | ~140 | — | Revert route+policy. |
| 5 | `transactions.list` | API-007 (1) | ~120 | — | Pure reorder route declaration. |
| 6 | `rbac.permissions` | FF-001 (1) | ~160 | — | Revert composable. |
| 7 | `mercadopago.sdkv2` | FF-002 (1) | ~80 | — | Revert composable. |
| 8 | `formrequests.fields` | API-008..060 (high subset) | ~360 | — | FormRequest fields are additive. |
| 9 | `reminders.implemented` | ReminderController, ReminderTemplateController, ReminderProvider (3) | ~390 | — | Revert controllers+provider. |
| 10 | `stubs.removed` | WaitingListController + 6 others (7) | ~220 | — | Revert route removal. |
| 11 | `visual-flow.a11y` | UXV/UXF/UXT remainder (37) | ~380 | slices 1,6 | Revert modal/component changes. |

**Ordering rationale**: Pure-infrastructure slices (tokens, audit-log, routes) first because they have no frontend dep and unblock downstream tests. RBAC + SDK fixes land before visual flow because the visual-flow slice calls `usePermissions`. FormRequests ship mid-chain so per-endpoint tests can use the new fields. Stubs-implemented/removed last so they ride on the new routing.

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Cash-register new endpoints diverge from existing service contracts | High | Reuse `CashRegisterService` + `CashRegisterReportService`; add feature tests against current behavior. |
| FormRequest field additions break existing payloads | Medium | All new fields are nullable/optional; rollback is `git revert` with no migration. |
| Modal focus trap conflicts with FullCalendar event handlers | Medium | Trap scoped to `role="dialog"`; calendar remains active. |
| `tokens.js` recreation differs from broken link target | Low | Single source-of-truth tokens; replace `tailwind.config.js` palette to match. |
| Cache-key changes cascade beyond slice 11 | Low | No caching layer in this change. |
| Stub removal breaks hidden frontend consumers | Medium | Pre-remove `grep` across `resources/js/` for each orphaned route; abort if hit. |
| SQLite-local tests vs CI MySQL drift | Medium | New tests written against model contracts; CI gates. |

## Rollback Plan

- Each slice is a single PR → revert merges via `git revert <sha>` per slice.
- DB migrations: every new migration is **additive** (new table or new column, nullable). No destructive ALTER.
- New routes: removed by reapplying the inverse patch — no consumers persist across slices.
- Tokens replacement: revert `tailwind.config.js` palette + delete `tokens.js`; all consumers fall back to inline classes.
- Stub-implemented controllers: revert controller + provider; no DB model touched.
- Stub-removed controllers: re-add route file via PR-revert; original line count ≤30 LOC per file.

## Success Criteria

- All 138 findings have a corresponding PR linked from `openspec/changes/bugfix-2026-08/findings-map.md`.
- `php artisan test` exits 0 on CI MySQL for the new test cases (the 28 pre-existing SQLite failures remain unchanged).
- `pnpm lint:check && pnpm build` exits 0.
- No 10/23 high/critical bug reproducible via the documented reproduction.
- All 60 API contract mismatches verified by route smoke-test (sanctum auth + 200 response).
- CodeGraph impact delta under 5% (no architectural drift).

## Dependencies

- No external services. New routes are in-process.
- Local DB: SQLite-strict tests must remain green for non-migration slices (CI MySQL is the merge gate).
- Feature flag `audit_log_immutable` (planned in slice 2) does not yet exist; include migration in slice 2.

## Open Questions (require user input)

1. **Stub-501 batch plan**: approve the per-controller decisions (Reminder/ReminderTemplate/Provider implement; WaitingList remove; others triaged in apply)?
2. **Cadence**: ship all 11 slices in one PR-chain or pause after slice 5 for a stability window?
3. **Triage depth for low-severity (73) findings**: fix in the same slice as their parent module, or batch into a final "polish" slice #12?
4. **Audit log auth scope**: should `audit-log.show` be `role:admin` only, or include `auditor` role? (Confirm with product owner.)
5. **Tokens.js naming**: restore as `design-system/tokens.js` to match AGENTS.md, or rename AGENTS.md reference to the new path?
