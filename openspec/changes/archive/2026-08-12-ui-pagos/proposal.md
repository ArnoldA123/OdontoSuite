# Proposal: UI Rollout — PAGOS category (`ui-rollout-all-modules-2026-08`)

## 0. Metadata

| Key | Value |
|---|---|
| Change | `ui-rollout-all-modules-2026-08` |
| Category | PAGOS (cash register, payment capture, transactions, ready-to-bill, quotations, payment-method catalog) |
| Date | 2026-08-12 |
| Phase | propose (2 of 6) — category slice |
| Author | `sdd-propose` sub-agent (PAGOS) |
| Pace | `auto` |
| Artifact store | `hybrid` (this file + Engram `sdd/ui-rollout-all-modules-2026-08/categories/pagos/proposal`) |
| Parent artifacts | `proposal.md` (596 lines), `explore.md` (496 lines), `categories/pagos/explore.md` (133 lines), `specs/design-language-rollout/spec.md` |
| Sibling categories | (none yet — this is the first category slice) |
| Global PR mapping | PAGOS = global PR9 (`pr9-cash-register-reverb-isolation`) + portions of PR2 (`pr2-quotations-tokenise-and-migrate-status-badge`) + optional PR11 (`pr11-settings-branches-and-payment-methods-optional`) per global proposal §7 |
| Delivery strategy | Inherits `auto-chain` from the global proposal; PAGOS sub-PRs `pr-pagos-01..05` stack inside that chain |
| Review budget | 400 authored lines / PR (per global proposal §7.15) |
| Strict TDD | `true` (forward to apply/verify) |
| Vertical slice baseline | `ui-premium-microdetail-2026-08` — closed 2026-08-11; tokens, primitives, easing, focus-ring, canvas/surface separation, `tabular-nums` all inherited as-is |

### Preflight snapshot (verbatim from global proposal)

```yaml
pace: auto
artifact_store: hybrid
delivery_strategy: auto-chain      # chained PRs auto-activate; do NOT re-ask
review_budget_lines: 400
chain_strategy: not_cached         # recommend stacked-to-main at sdd-tasks time
strict_tdd: true
```

---

## 1. Intent

PAGOS is the operational heart of OdontoSuite: every peso and sol that enters or leaves the practice flows through these screens. The cashier opens a session, takes payments (manual or Mercado Pago Bricks), records movements, issues refunds ("Egreso"), closes the session with an arqueo, and at month-end exports reports. The ready-to-bill screen identifies appointments with a balance due. Quotations are the pre-payment approval flow that feeds into transactions. The payment-method catalog governs what gateways the cashier may use.

The proven Apple language landed on Dashboard, Login, and 404. PAGOS still reads as legacy: opaque `border-theme` outlines, deprecated `bg-success-100` / `text-accent` / `focus:ring-primary-500` ramps, hardcoded `bg-primary-50` patient banner in `TransactionModal`, raw `<input>` borders in `TransactionList`/`MovementList`/`SessionList`, gradient cards in `CashReports`, and a hand-built `<Teleport>` modal with `bg-black bg-opacity-60` in `ReadyToBillPage`. A `formatCurrency` helper is reimplemented in 4+ files with subtly different signatures. The cashier's UX today reads as "the most important screen in the product was left behind."

This proposal scopes the rollout to **only** the pagos interfaces inventoried in `categories/pagos/explore.md`. It inherits every rule from the global proposal (token discipline, primitive contract, focus-ring composition, `tabular-nums`, canvas/surface separation, no `<style scoped>` grandfather clause) and applies them mechanically. The result: a cashier landing on `/cash-register` reads the same product as a clinician landing on `/dashboard`. Real-time Echo channels, the `useCashRegister` contract, the MercadoPago Bricks integration, and the `TransactionService` business rules stay byte-for-byte untouched — UI changes are template-level class-string replacement only.

**Why now:** the foundation tokens are settled, the PHPUnit invariants are wired, and the global proposal's chain has Caja isolated as PR9 (last by risk). The pagos work splits cleanly into 5 sub-PRs (see §6) that stay inside the 400-line review budget and don't disturb the chain order. The user's stated intent — extend the proven language to every module — applies with extra weight to pagos, because the cashier workflow is the highest-touch operational surface.

---

## 2. In-Scope

### 2.1 Pages / routes (4)

1. `/cash-register` — `resources/js/modules/cash-register/CashRegisterPage.vue` (the hub: tabs Pagos / Transacciones / Movimientos / Historial / Reportes).
2. `/cash-register/ready-to-bill` — `resources/js/modules/cash-register/ReadyToBillPage.vue` (appointments with pending balance + breakdown modal).
3. `/quotations` — `resources/js/modules/quotations/QuotationsPage.vue` (pre-payment approvals that feed transactions).
4. `/settings/payment-methods` — `resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue` (admin CRUD for Efectivo, Yape, Plin, MercadoPago, etc.).

All four routes are pinned in `AppLayout.canvasRoutes` (per global PR0, already landed on 2026-08-11). Surface pickup is automatic; this proposal touches the visible internals.

### 2.2 Cash-register components (11)

| # | File | Touch scope |
|---|---|---|
| 1 | `resources/js/modules/cash-register/components/PaymentModal.vue` | Large. Tab strip → `<UiTabs>`, panels → tokenised surface, error styling → focus-ring, disabled affordance → `LoadingSpinner`. |
| 2 | `resources/js/modules/cash-register/components/MercadoPagoCheckout.vue` | Small. Add Apple motion on Bricks-loading state; surface wraps `UiButton` + `LoadingSpinner`. |
| 3 | `resources/js/modules/cash-register/components/TransactionModal.vue` | Medium. `bg-primary-50` patient banner → `<UiCard>`; raw borders → hairline; `animate-spin` → `LoadingSpinner`. |
| 4 | `resources/js/modules/cash-register/components/MovementModal.vue` | Small. Already uses `CurrencyInput`; finish the chrome. |
| 5 | `resources/js/modules/cash-register/components/OpenCashModal.vue` | Small. Already uses `CurrencyInput` + `EmptyState`; finish the chrome. |
| 6 | `resources/js/modules/cash-register/components/CloseCashModal.vue` | Medium. Desglose table → hairline + `tabular-nums`; status pills → `<UiStatusBadge>`. |
| 7 | `resources/js/modules/cash-register/components/TransactionList.vue` | Medium. Raw `<input>` + `<select>` → `<UiInput>` / `<UiSelect>`; `border-theme` table → hairline. |
| 8 | `resources/js/modules/cash-register/components/MovementList.vue` | Medium. Same raw-control pattern as TransactionList. |
| 9 | `resources/js/modules/cash-register/components/SessionList.vue` | Medium. Same raw-control pattern; status pills → `<UiStatusBadge>`. |
| 10 | `resources/js/modules/cash-register/components/CashReports.vue` | Medium. Gradient cards → tokenised `Card`; filter card → hairline + focus ring. |
| 11 | `resources/js/modules/cash-register/components/PendingPaymentsList.vue` | Small. Raw `<input>` borders → hairline; custom spinner → `LoadingSpinner`. |

### 2.3 Quotations components (6 — part of global PR2)

1. `resources/js/modules/quotations/components/QuotationCard.vue`
2. `resources/js/modules/quotations/components/QuotationModal.vue`
3. `resources/js/modules/quotations/components/QuotationDetail.vue`
4. `resources/js/modules/quotations/components/QuotationStatusBadge.vue` (migrated to consume `<UiStatusBadge>` internally per global spec `DLR-MOD-010`)
5. `resources/js/modules/quotations/components/QuotationApprovalModal.vue`
6. `resources/js/modules/quotations/QuotationsPage.vue` (the page itself)

### 2.4 Payment-methods admin CRUD

- `resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue` (page; legacy counters + custom borders)
- `resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue` (create/edit modal; if not yet on disk, apply phase extracts it inline as part of the PR)

### 2.5 Cross-cutting primitives touched (PAGOS-side; PR0 owns the primitive itself)

- `resources/js/components/ui/CurrencyInput.vue` — confirm it's the sole money input on every pagos surface (verify by grep). NO formatting fork.
- `resources/js/components/ui/ReceiptPreview.vue` — tokenise chrome; uses `<UiButton>` + hairline borders.
- `resources/js/components/ui/{Card,Button,Modal,Tabs,EmptyState,LoadingSpinner}.vue` — already tokenised in PR2 of the vertical slice; PAGOS only consumes them.

### 2.6 Tests

| Test file | Action |
|---|---|
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Keep green. The array literal now includes `/cash-register`, `/cash-register/ready-to-bill`, `/quotations`, `/settings/payment-methods`. |
| `tests/Unit/Composables/PaymentModal401RedirectTest.php` (UXF-021) | Keep green. 401 from `PaymentModal` MUST still tear down session and redirect to `/login`. |
| `tests/Unit/Composables/FormatPENLabelTest.php` | Extend: assert the consolidated `formatCurrency` helper (single location, see §5 + §6) is the only money formatter used. |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | Keep green. `useCashRegister` / `useTransactions` / `usePaymentMethods` contracts preserved. |
| `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; asserts `CashRegisterPage` + `ReadyToBillPage` + `PaymentModal` reference the proven tokens and contain no legacy aliases / no `<style scoped>` block. |
| `tests/Unit/DesignSystem/QuotationsAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; same contract for the 6 Quotations files. |
| `tests/Unit/DesignSystem/PaymentMethodsAppShellTest.php` | NEW. Extends `ModuleAppShellTestCase`; same contract for `PaymentMethodsPage` + `PaymentMethodFormModal`. |

### 2.7 Shared helper consolidation (one deliverable, see PR-pagos-05)

- `formatCurrency` (currently reimplemented in 4+ files per `categories/pagos/explore.md` §"Known gotchas") SHALL be consolidated to **exactly one location** at `resources/js/composables/useFormatters.js` (or a new `resources/js/utils/formatCurrency.js` if apply phase prefers a dedicated module). Wrapper signatures stay identical to the most-common current signature `(amount, options) => string` so no call site changes beyond the import line.

---

## 3. Out-of-Scope

The following look pagos-related but are explicitly excluded. They may be raised in a follow-up change once this rollout lands.

1. **Backend business logic.** No changes to `TransactionService.createTransaction`, void logic, refund ("Egreso") flows, `MercadoPagoService` SDK calls, or `TransactionService` subtotal/discount/commission/tax computation. UI-only.
2. **Webhook idempotency / retry semantics.** `ProcessMercadoPagoWebhook` (`tries=3, backoff=[60,300,900]`, `unique(external_id, event_type)`) stays verbatim.
3. **Payment gateway provider switching.** MercadoPago is the only gateway. No Niubiz, Izipay, Culqi, or stripe additions.
4. **Currency conversion / multi-currency.** PEN only. The `Intl.NumberFormat('es-PE', { currency: 'PEN' })` formatter stays single-currency.
5. **New payment methods or new transaction types.** No new gateway types, no new `Transaction.type` values, no new `PaymentMethod` flags beyond what already exists in the seeders.
6. **Insurance claim flows.** Insurance `PatientDetailPage` claims section is out (covered by the Pacientes category slice, not PAGOS).
7. **Quotation template editor.** PDF layout, terms text, branding — all untouched. The Quotations rollout is the LIST/DETAIL/APPROVAL chrome only.
8. **BI revenue dashboard visuals.** `/business-intelligence` consumes transactions but is its own category slice; not PAGOS.
9. **Cash-register `<script>` blocks.** NEVER edited in any PR. UI changes are template-level class-string replacement only (`useCashRegister` reactivity contract, debounce, channel subscription, cleanup all preserved verbatim — see global proposal §5 risk #1).
10. **`PaymentMethod.gateway_config` raw exposure.** The admin CRUD form must redact the encrypted blob (`Crypt::encryptString` at APP_KEY); the rollout SHALL add a `data-redacted="true"` attribute assertion in `PaymentMethodsAppShellTest` so raw `gateway_config` never echoes to the DOM.
11. **Anything in the global proposal but not in the pagos inventory.** Patients, Profesionales, Ambientes, Calendario, BI, AI Analysis, Treatment Plans, Medical Records, Specialty Records, Procedure Catalog, My Procedures, Reception Procedures, Procedure Stats — all other category slices, all OUT of PAGOS.

---

## 4. Approach

Reuse the proven language as-is; no new tokens, no new primitives except `<UiStatusBadge>` (extracted in global PR0; PAGOS consumes it). Replace legacy alias classes one-by-one inside each pagos `.vue` file using the global proposal §4.1 mapping table verbatim. Reuse `CurrencyInput` as the sole money input — NO formatting fork. Consolidate `formatCurrency` to a single helper (one canonical implementation, multiple call sites import). Touch scope ordering: pages first (highest-traffic surfaces), then modals, then list/report views, then admin CRUD. The Caja split into 4 sub-PRs (PR-pagos-01..04) keeps each PR inside the 400-line budget; PR-pagos-05 is the shared-helper consolidation + cross-cutting tests (separate, rides any of the four).

The 4 pagos routes already receive the canvas surface via the global `canvasRoutes` extension (PR0). The PR0 `LegacyAliasForbiddenTest` pins the alias list (`border-theme`, `bg-success-100`, `text-accent`, `focus:ring-primary-500 focus:border-accent`, `bg-theme-surface-elevated` on the page surface, `disabled:opacity-30` patterns in `ReadyToBillPage`, etc.); `PaymentMethodsAppShellTest` / `CashRegisterAppShellTest` / `QuotationsAppShellTest` extend `ModuleAppShellTestCase` and assert the rule (token reference exists, alias absent), not a literal string (per the archive-report lesson). Visual verification per module: playwright-cli snapshot at 1440x900, plus 390x844 for `/cash-register` (cashier mobile path) and `/cash-register/ready-to-bill`. Credentials: `finanzas@test.com` for Caja / Quotations / Payment Methods; `recep@test.com` for Recepción-ready-to-bill path (per `CREDENTIALS.md`).

Strict TDD discipline: every UI replacement MUST come with a test that proves the new behaviour (RED-GREEN per project policy). The visual sweep is documented verification, not a CI gate.

---

## 5. Capabilities (contract with sdd-spec)

The sdd-spec phase reads this section to know exactly which spec files to create or update. Research `openspec/specs/` first to use the existing capability names.

### New Capabilities (none)

The PAGOS rollout does NOT introduce new capability specs. It exercises the global capability `premium-design-foundation` (persisted at `openspec/specs/premium-design-foundation/spec.md`) and the global delta spec `design-language-rollout` (at `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md`). The PAGOS requirements live as additional rows in the global spec's module table.

### Modified Capabilities (delta rows added to existing global delta spec)

For the `design-language-rollout` delta spec (sibling file `specs/design-language-rollout/spec.md`), add PAGOS-specific rows to the Module scenarios table:

- `DLR-MOD-007` — Caja: template-only class-string replacement; `<script>` blocks and `useCashRegister` contract preserved verbatim; `PaymentModal` 401 redirect (UXF-021) stays green; `formatCurrency` consolidated to single helper; no Reverb regression. (Inherits from global spec; PAGOS clarifies the `formatCurrency` consolidation + 401 redirect contract + `gateway_config` redaction.)
- `DLR-MOD-010` — Quotations: first consumer of `<UiStatusBadge>`; `QuotationStatusBadge` becomes a thin wrapper; currency columns use `tabular-nums`. (Inherits from global spec.)
- `DLR-MOD-020` — Payment Methods admin CRUD: `PaymentMethodsPage` + `PaymentMethodFormModal` tokenised; `PaymentMethod.gateway_config` redacted from DOM (`data-redacted="true"`); counter uses `tabular-nums`. (NEW row added by PAGOS.)
- `DLR-MOD-021` — Ready-to-bill: `ReadyToBillPage` + the desglose modal tokenised; hand-built `<Teleport>` modal replaced by `<UiModal>`; disabled affordance uses `LoadingSpinner` (NOT legacy `disabled:opacity-30`). (NEW row added by PAGOS.)
- `DLR-XCUT-007` — `formatCurrency` consolidation: exactly one location, called from every pagos surface; legacy 4+ reimplementations are FORBIDDEN. (NEW cross-cutting row added by PAGOS.)

If sdd-spec chooses to extract PAGOS into a sibling delta spec (`specs/pagos-rollout/spec.md`), that is allowed — the global proposal does not forbid per-category specs. Recommendation: extend the global spec to keep traceability simple. Discuss with the orchestrator at spec phase.

---

## 6. Deliverables

Five PRs. Each fits inside the 400-line budget. Each is independently buildable, testable, and revertible.

### PR-pagos-01 — `/cash-register` page polish (Caja hub)

| Field | Value |
|---|---|
| Name | `pr-pagos-01-cash-register-hub` |
| Scope | `resources/js/modules/cash-register/CashRegisterPage.vue` — tabs Pagos / Transacciones / Movimientos / Historial / Reportes, real-time cards, top of module. Rewrite the legacy `<style scoped>` block to plain utility classes. Status pills → `<UiStatusBadge>`. Hover-lift → `<UiCard clickable>`. Replace `*:transition` global selector (legacy `CashRegisterPage.vue` pattern). |
| Files | 1 page + extend `CashRegisterAppShellTest` |
| Risk | Medium (touches the most-visited pagos surface) |
| Dependencies | Global PR0 (already landed: `canvasRoutes`, `<UiStatusBadge>`, `ModuleAppShellTestCase`, `LegacyAliasForbiddenTest`) |
| Line estimate | ~320 |
| Reversibility | `git revert <merge-sha>`; CashRegisterPage UI reverts to legacy look but `<script>` untouched |

### PR-pagos-02 — `PaymentModal` + `MercadoPagoCheckout` + 401 redirect test

| Field | Value |
|---|---|
| Name | `pr-pagos-02-payment-modal-and-mercadopago` |
| Scope | `resources/js/modules/cash-register/components/PaymentModal.vue` (22.3 KB, largest single component) + `MercadoPagoCheckout.vue`. Tab strip → `<UiTabs>`; error styling → focus-ring; MercadoPago Bricks-loading state gets Apple motion (`var(--motion-duration-normal) var(--motion-easing-ios)` on the loading pill). UI changes are template-only; the 401-redirect-on-createTransaction-failure code path stays verbatim (UXF-021 must remain green). |
| Files | 2 components + verify `PaymentModal401RedirectTest` stays green |
| Risk | High (real-money UX) |
| Dependencies | PR-pagos-01 (so PaymentModal lives on the tokenised hub page) |
| Line estimate | ~390 (right at the budget; split into 02a + 02b if reviewer flags) |
| Reversibility | `git revert <merge-sha>`; PaymentModal reverts to legacy look but `<script>` untouched |

### PR-pagos-03 — `ReadyToBillPage` + payment + transaction + movement + session modals

| Field | Value |
|---|---|
| Name | `pr-pagos-03-ready-to-bill-and-modals` |
| Scope | `ReadyToBillPage.vue` (replace hand-built `<Teleport>` modal with `<UiModal>`; fix `disabled:opacity-30` affordance with `LoadingSpinner`) + `TransactionModal.vue` (`bg-primary-50` patient banner → `<UiCard>`) + `MovementModal.vue` + `OpenCashModal.vue` + `CloseCashModal.vue`. All modals are template-only. |
| Files | 5 files + extend `CashRegisterAppShellTest` |
| Risk | Medium |
| Dependencies | PR-pagos-01 |
| Line estimate | ~340 |
| Reversibility | Same as 01 |

### PR-pagos-04 — Quotations + Payment Methods admin CRUD

| Field | Value |
|---|---|
| Name | `pr-pagos-04-quotations-and-payment-methods` |
| Scope | Quotations: `QuotationsPage.vue` + 5 components; `QuotationStatusBadge` migrated to consume `<UiStatusBadge>` (first consumer per global spec `DLR-MOD-010`). Payment Methods: `PaymentMethodsPage.vue` + `PaymentMethodFormModal.vue`; counters → `tabular-nums`; `PaymentMethod.gateway_config` echoed only via `data-redacted="true"` attribute (never raw). |
| Files | 6 Quotations + 2 Payment Methods + new `QuotationsAppShellTest` + new `PaymentMethodsAppShellTest` |
| Risk | Medium |
| Dependencies | PR-pagos-01..03 (so PR-pagos-05 can assume all surfaces tokenised) |
| Line estimate | ~380 (right at the budget; consider splitting if reviewer flags) |
| Reversibility | Same as 01 |

### PR-pagos-05 — `formatCurrency` consolidation + cross-cutting tests

| Field | Value |
|---|---|
| Name | `pr-pagos-05-format-currency-consolidation` |
| Scope | Consolidate `formatCurrency` to exactly one location (preferred: `resources/js/composables/useFormatters.js`; fallback: `resources/js/utils/formatCurrency.js`). Update all 4+ call sites to import from the canonical location. Add `tests/Unit/Composables/FormatPENLabelTest.php` assertion that `formatCurrency` exists at exactly one location. Wrap with regression assertion that `CurrencyInput` formatter is unchanged (no formatting fork). |
| Files | 1 helper file + 4+ import-line updates + 1 extended test |
| Risk | Low (mechanical; signatures preserved) |
| Dependencies | PR-pagos-01..04 |
| Line estimate | ~120 |
| Reversibility | Single-file revert restores the old `formatCurrency` reimplementations |

### Deliverable-to-PR mapping (verifies the global chain)

| Global PR | PAGOS PRs that ride it |
|---|---|
| Global PR2 (`pr2-quotations-tokenise-and-migrate-status-badge`) | PR-pagos-04 (Quotations half) |
| Global PR9 (`pr9-cash-register-reverb-isolation`) | PR-pagos-01 + 02 + 03 (Caja half) |
| Global PR11 (`pr11-settings-branches-and-payment-methods-optional`) | PR-pagos-04 (Payment Methods half) — only if global PR11 fires |
| (any) | PR-pagos-05 can ride any of the four; default is to land after PR-pagos-04 |

---

## 7. Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/js/modules/cash-register/**` | Modified | 12 Vue files: 1 page + 1 ready-to-bill page + 11 components |
| `resources/js/modules/quotations/**` | Modified | 6 Vue files: 1 page + 5 components |
| `resources/js/modules/settings/payment-methods/**` | Modified | 1-2 Vue files (page + form modal if on disk) |
| `resources/js/components/ui/CurrencyInput.vue` | Unchanged | Consumed as-is; verified to be sole money input on pagos |
| `resources/js/components/ui/ReceiptPreview.vue` | Modified | Tokenised chrome only |
| `resources/js/composables/useFormatters.js` | Modified | Add canonical `formatCurrency` export (PR-pagos-05) |
| `tests/Unit/Composables/FormatPENLabelTest.php` | Extended | Assert single-location `formatCurrency` |
| `tests/Unit/DesignSystem/CashRegisterAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| `tests/Unit/DesignSystem/QuotationsAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| `tests/Unit/DesignSystem/PaymentMethodsAppShellTest.php` | New | Extends `ModuleAppShellTestCase` |
| Backend (`app/Http/Controllers/Api/*`, `app/Services/*`, `app/Models/*`, `database/migrations/*`) | Unchanged | Out of scope |
| `resources/js/modules/cash-register/composables/useCashRegister.js` (or equivalent) | Unchanged | `<script>` blocks untouched |
| `app/Jobs/ProcessMercadoPagoWebhook.php` | Unchanged | Retry/idempotency untouched |

---

## 8. Risks

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | Apple motion (`var(--motion-duration-normal) var(--motion-easing-ios)`) on `MercadoPagoCheckout` Bricks-loading state may make the redirect feel slower than the legacy instant-snap. | Medium | Keep the Bricks-loading state minimal (single `<UiLoadingSpinner>` + label); don't over-animate. Visual verification per PR-pagos-02 with cashier role at 1440x900. |
| 2 | `formatCurrency` consolidation could break callers with subtly different signatures (some return `S/ X.XX`, some return `X.XX`, some return localised strings). | Medium | Wrapper signature is `(amount, options) => string` matching the most-common current signature; apply phase audits all 4+ call sites BEFORE consolidating. `FormatPENLabelTest` extended to assert canonical output for representative inputs (PEN with 2 decimals, zero, negative). |
| 3 | Real-time Echo channels (`cash-register`, `.cash-session.opened/closed`, `.payment.registered`, `.cash-movement.created`) must keep working after primitive swap. | Low | Apply phase scope rule: `<script>` blocks are NEVER touched in any PAGOS PR. Visual smoke test: cashier role sees the cash-status pill on `/dashboard` update live after a `PaymentRegistered` event. |
| 4 | `PaymentMethod.gateway_config` is encrypted at rest (`Crypt::encryptString`, APP_KEY); admin UI must never echo the raw blob. | Medium | Explicit `data-redacted="true"` attribute on the form field; `PaymentMethodsAppShellTest::test_gateway_config_redacted` asserts the attribute is present and the raw value is absent from any rendered text node. |
| 5 | Chained PRs may exceed the 400-line review budget. | Medium | PR-pagos-02 (PaymentModal) and PR-pagos-04 (Quotations + Payment Methods) are both near the budget. If a PR's diff exceeds 400 lines, split per `chained-pr` skill: PR-pagos-02a (PaymentModal) + 02b (MercadoPagoCheckout); PR-pagos-04a (Quotations) + 04b (Payment Methods). |
| 6 | The Caja `useCashRegister` contract has a 300ms debounce; a UI change that accidentally introduces a heavier re-render storm could lag the cashier. | Low | Apply phase: `<script>` blocks NEVER touched; UI changes are template-level only. If a regression surfaces, isolate via `git revert <pr-sha>` and re-do. Tag the Caja merge commit with `cash-register-revert-rationale` per global proposal §8. |
| 7 | Hand-built `<Teleport>` modal in `ReadyToBillPage` (uses `bg-black bg-opacity-60`) must be replaced by `<UiModal>` without changing the modal-open / close behaviour. | Low | Apply phase: preserve the `open` / `close` emit contract byte-for-byte; only swap the wrapper markup. Extend `CashRegisterAppShellTest::test_ready_to_bill_modal_uses_ui_modal` to assert `<UiModal>` wrapper is present. |

---

## 9. Rollback Plan

- **Per-PR revert:** each PR-pagos-NN is independently revertible via `git revert <merge-sha>` because the global `stacked-to-main` strategy keeps every commit reachable.
- **PR-pagos-01, 02, 03 (Caja half):** revert restores the legacy class strings on `CashRegisterPage.vue` / `ReadyToBillPage.vue` / `PaymentModal.vue`. `<script>` blocks untouched, so `useCashRegister` reactivity contract is preserved. The cashier role's verified screenshot baseline at `.playwright-cli/screenshots-rollout/cash-register-1440x900.png` is the regression witness.
- **PR-pagos-04 (Quotations + Payment Methods half):** revert restores legacy status badges and counters. The `QuotationStatusBadge` wrapper file MAY be deleted by PR-pagos-04; if so, revert restores the wrapper. The `<UiStatusBadge>` primitive itself stays (it's owned by global PR0 and may be used by other categories).
- **PR-pagos-05 (formatCurrency consolidation):** revert restores the 4+ reimplementations. The canonical helper stays (it's a no-op import when unused). `FormatPENLabelTest` extension reverts.
- **No destructive schema/data migrations.** All backend controllers / services / models / migrations are byte-for-byte unchanged. No destructive operation anywhere.

---

## 10. Success Criteria

The PAGOS rollout is considered complete when ALL of the following hold:

- [ ] **All 4 pagos routes render on Apple canvas without legacy `hover-lift`, `bg-theme-surface` (on the page surface), `border-theme`, `bg-success-100`, `text-accent`, `focus:ring-primary-500 focus:border-accent`, `disabled:opacity-30`, or `*:transition` global selectors in the visible content area.** `AppLayoutCanvasRoutesTest` green; `CashRegisterAppShellTest`, `QuotationsAppShellTest`, `PaymentMethodsAppShellTest` each green.
- [ ] **`PaymentModal` and `TransactionModal` use `<UiTabs>` + `<UiButton>` + `<UiModal>` primitives exclusively.** Grep-verified: no raw `<button class="...">` for tab strip, no raw `<div class="modal">`.
- [ ] **`CurrencyInput` is the only money input on pagos screens.** Grep-verified: no raw `<input type="number" v-model="amount">` patterns outside `CurrencyInput.vue` itself.
- [ ] **`Intl.NumberFormat('es-PE', { currency: 'PEN' })` is the only currency formatter.** `formatCurrency` lives at exactly one location (`resources/js/composables/useFormatters.js` or `resources/js/utils/formatCurrency.js`). Grep-verified: no `S/ ${n.toFixed(2)}` or `Intl.NumberFormat` outside the canonical helper. `FormatPENLabelTest` extended to assert this.
- [ ] **401 from `PaymentModal` still redirects to `/login`.** `PaymentModal401RedirectTest` (UXF-021) stays green at every PR-pagos-NN boundary.
- [ ] **`PaymentMethod.gateway_config` never echoes raw.** `PaymentMethodsAppShellTest::test_gateway_config_redacted` green; the DOM contains `data-redacted="true"` and never the decrypted blob.
- [ ] **All transactions modals / lists / reports use `<UiStatusBadge>`** (no inline `bg-success-100 text-success-700` etc.).
- [ ] **All CashRegister / ReadyToBill / Quotations / PaymentMethods files have zero `<style scoped>` blocks.** `ModuleAppShellTestCase::test_no_style_scoped` green per module.
- [ ] **Real-time Echo channels keep firing.** Manual smoke test: cashier role sees `payment.registered` update `/dashboard` cash-status pill within 1 second.
- [ ] **`useCashRegister` / `useTransactions` / `usePaymentMethods` contracts are unchanged.** `ComposablesStandardizationTest` green at every PR-pagos-NN boundary.
- [ ] **Playwright snapshots saved to `.playwright-cli/screenshots-rollout/{cash-register,ready-to-bill,quotations,payment-methods}-{1440x900,390x844}.png`** (mobile required for Caja + Ready-to-Bill only).
- [ ] **All `tests/Unit/DesignSystem/*` PHPUnit invariants stay green** (`TokensModuleTest`, `GeneratedTokensCssTest`, `PrimitivePressTest`, `DashboardAppShellTest`, `LoginPageRenderTest`, `UseSpringMathTest`, `AppLayoutCanvasRoutesTest`, `LegacyAliasForbiddenTest`, `ModuleAppShellTestCase`-derived tests).
- [ ] **CI green:** `quality`, `backend-tests` (MySQL), `frontend-build` (pnpm).
- [ ] **Test count delta ≥ +30** vs PR0 baseline (167 / 1158). Budget: +30 from the three new `*AppShellTest` files + extended `FormatPENLabelTest` + per-PR RED-GREEN pairs.
- [ ] **Chain integrity:** every PR-pagos-NN is independently buildable, testable, and revertible per `chained-pr` skill rules.

---

## 11. References

### 11.1 Source artifacts (read for this proposal)

| File | Why it matters |
|---|---|
| `openspec/changes/ui-rollout-all-modules-2026-08/proposal.md` (596 lines) | Global intent, scope, OQ resolutions, PR chain, success criteria |
| `openspec/changes/ui-rollout-all-modules-2026-08/explore.md` (496 lines) | Module inventory, per-module visual state, complexity tiers, PR chain ordering rationale |
| `openspec/changes/ui-rollout-all-modules-2026-08/categories/pagos/explore.md` (133 lines) | **PRIMARY INPUT.** Pagos inventory, controllers/services/jobs/models inventory, test coverage surface, known gotchas |
| `openspec/changes/ui-rollout-all-modules-2026-08/specs/design-language-rollout/spec.md` | Global MUST/SHOULD language; PAGOS sub-PRs map onto global PR2 / PR9 / PR11 |
| `openspec/changes/ui-rollout-all-modules-2026-08/design.md` (47 KB) | Token names, primitive API, motion durations, focus rings |
| `openspec/specs/premium-design-foundation/spec.md` (404 lines) | The archived capability PAGOS inherits (tokens, primitives, easing) |
| `openspec/config.yaml` | Preflight cache + strict TDD + pnpm-only + 400-line budget + CI MySQL |
| `AGENTS.md` §2, §4, §5, §6, §7 | Project context, stack, 17-module inventory, conventions, troubleshooting |
| `resources/js/design-system/tokens.js` | The proven token source-of-truth |
| `resources/css/tokens.generated.css` | Generated CSS (369 lines) |
| `resources/js/components/layout/AppLayout.vue` line 507 | `canvasRoutes` gate (global PR0) |
| `resources/js/modules/cash-register/**` (13 files) | PAGOS PR-pagos-01..03 inventory |
| `resources/js/modules/quotations/**` (6 files) | PAGOS PR-pagos-04 Quotations half |
| `resources/js/modules/settings/payment-methods/**` (1-2 files) | PAGOS PR-pagos-04 Payment Methods half |
| `resources/js/components/ui/CurrencyInput.vue` | Sole canonical money input across PAGOS |
| `resources/js/components/ui/ReceiptPreview.vue` | Receipt chrome tokenisation |
| `resources/js/composables/useCashRegister.js` (or equivalent) | `<script>` block preserved verbatim |
| `resources/js/composables/useFormatters.js` | Target location for canonical `formatCurrency` (PR-pagos-05) |
| `tests/Unit/Composables/PaymentModal401RedirectTest.php` | UXF-021 regression guard |
| `tests/Unit/Composables/FormatPENLabelTest.php` | Extended by PR-pagos-05 |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useCashRegister` / `useTransactions` / `usePaymentMethods` contract guard |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pin the `canvasRoutes` array literal (includes 4 pagos routes) |
| `CREDENTIALS.md` | `finanzas@test.com`, `recep@test.com` for visual verification |

### 11.2 Standing guard rails (inherited from the global proposal)

This proposal does NOT relax any of:

1. `tokens.js` is the only source of truth for tokens.
2. `systemBackground` (`#ffffff`) is pinned; canvas = `#F2F2F7`.
3. Elevation rungs 1..4 use `rgba(60, 60, 67, α)`, NOT `rgba(0, 0, 0, α)`.
4. Hairline is `rgba(60, 60, 67, 0.12)`, NOT `#D1D1D6`.
5. Focus ring is the COMPOSED `var(--focus-ring-default)`, NOT a single value.
6. `font-feature-settings: var(--font-features-tabular-nums)`, NOT literal `tabular-nums` utility name.
7. `<script>` blocks of every PAGOS module are NEVER edited in any PR.
8. `useApi()` wrapper only; NO axios direct.
9. pnpm only; NEVER npm/yarn.
10. Code in English; conversation in Spanish (Peru).

### 11.3 Process invariant (forwarded from the vertical-slice archive-report)

The archive-report at lines 47–57 names three defects that all shared one root cause: **a test that pins an example instead of the rule**. PAGOS's standing posture is to assert rules, not literals:

- `CashRegisterAppShellTest`, `QuotationsAppShellTest`, `PaymentMethodsAppShellTest` extend `ModuleAppShellTestCase` — they assert the rule (`--color-canvas` reference exists, `border-theme` absent, `<style scoped>` absent), not a literal string.
- `LegacyAliasForbiddenTest` (global PR0) pins the list of forbidden patterns, not a single example.
- `FormatPENLabelTest` (extended in PR-pagos-05) asserts the rule (`formatCurrency` exists at exactly one location), not the literal output of one example.

---

## 12. What This Proposal Does NOT Do

- Does NOT redesign any pagos surface — it ROLLOUTS the proven language.
- Does NOT add new tokens, primitives, or components (except consuming `<UiStatusBadge>` from global PR0).
- Does NOT add dark mode.
- Does NOT add gradients anywhere.
- Does NOT touch the backend (no controller, no service, no listener, no migration, no job).
- Does NOT relax any standing guard rail from §11.2.
- Does NOT introduce `<style scoped>` blocks (or carry them as grandfathered).
- Does NOT touch `<script>` blocks in any PAGOS module — UI changes are template-level only.
- Does NOT change `useCashRegister` reactivity contract, debounce, or channel subscription.
- Does NOT change `PaymentModal` 401-redirect-on-createTransaction-failure behaviour (UXF-021 stays green).
- Does NOT touch `ProcessMercadoPagoWebhook` retry / idempotency.
- Does NOT expose `PaymentMethod.gateway_config` raw.
- Does NOT add a new payment gateway (Niubiz, Izipay, Culqi, Stripe, etc.).
- Does NOT add multi-currency or currency conversion.
- Does NOT touch insurance claims, quotation template editor, or BI revenue dashboard visuals.

---

*End of PAGOS proposal.*