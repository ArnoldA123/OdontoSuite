# Explore — pagos (ui-rollout-all-modules-2026-08)

## Scope

This category covers every interface in OdontoSuite that moves or records money: cash register sessions (open/close/arqueo), payment capture (manual + Mercado Pago gateway), transactions (payment + refund egreso), transaction voids, receipts, cash movements, ready-to-bill appointments, payment method catalog, and Mercado Pago webhooks. Out of scope: clinical/billing configuration not involving actual money capture (insurance claim forms, quotation templates without payment execution), generic patient/staff admin, and accounting-only reports that consume transactions but never display a payment capture screen.

## Inventory — Frontend (Vue)

PR0 already added `/cash-register`, `/cash-register/ready-to-bill`, and `/quotations` to `AppLayout.canvasRoutes`. Other pagos routes listed below were not pinned in PR0 — their view surface still uses the old `bg-systemBackground` and untouched panels.

| Route (URL) | Component file | Purpose | Apple-language status | Touch scope |
| --- | --- | --- | --- | --- |
| `/cash-register` | `resources/js/modules/cash-register/CashRegisterPage.vue` | Caja hub: tabs Pagos/Transacciones/Movimientos/Historial/Reportes; real-time cards | `canvasRoutes` pinned but visual still legacy (`hover-lift`, hardcoded green/red badges, `*:transition` global) | large |
| `/cash-register/ready-to-bill` | `resources/js/modules/cash-register/ReadyToBillPage.vue` | Citas completadas con saldo pendiente + desglose modal | `canvasRoutes` pinned, but component is a custom hand-built `<Teleport>` modal with `bg-black bg-opacity-60`, plain `<table>`, raw `<input>` borders | medium |
| `/quotations` | `resources/js/modules/quotations/QuotationsPage.vue` | Presupuestos (pre-pagos, generate-from-appointment, approve flow) | `canvasRoutes` pinned; legacy `bg-theme-surface` cards + custom `form-input` classes | medium |
| `/settings/payment-methods` | `resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue` | Admin CRUD métodos de pago (Efectivo, Yape, Plin, MercadoPago, etc.) | `canvasRoutes` pinned (surface only — internals deferred per OQ#3); legacy counters + custom borders | medium |

Modal/modal-adjacent components inside the cash-register module:

| Component file | Purpose | Apple-language status | Touch scope |
| --- | --- | --- | --- |
| `resources/js/modules/cash-register/components/PaymentModal.vue` | Cobro manual + Mercado Pago tabs; patient/concept/amount/method/reference/notes | Untouched: `bg-theme-surface` panels, custom `<select>` borders, `border-red-500` error styling, raw `<button>` tab strip | large |
| `resources/js/modules/cash-register/components/MercadoPagoCheckout.vue` | Mercado Pago Bricks container + success/error/processing states | Untouched; uses `UiButton` + `LoadingSpinner` but no Apple motion/states | small |
| `resources/js/modules/cash-register/components/TransactionModal.vue` | Modal transaccion generica (Ingreso/Egreso) con paciente buscador + cita/plan | Untouched: `bg-primary-50` custom block, raw borders, `animate-spin` spinner | medium |
| `resources/js/modules/cash-register/components/MovementModal.vue` | Movimiento de caja (income/expense/withdrawal/deposit/adjustment) | Untouched; uses `CurrencyInput` | small |
| `resources/js/modules/cash-register/components/OpenCashModal.vue` | Apertura de sesion de caja (sucursal + monto + notas) | Untouched; uses `CurrencyInput` + `EmptyState` | small |
| `resources/js/modules/cash-register/components/CloseCashModal.vue` | Cierre + arqueo (desglose por metodo) | Untouched | medium |
| `resources/js/modules/cash-register/components/TransactionList.vue` | Lista filtrable de transacciones + export Excel/PDF | Untouched: raw `<input>` + `<select>`, `border-theme` table | medium |
| `resources/js/modules/cash-register/components/MovementList.vue` | Lista filtrable de movimientos de caja + export | Untouched: same raw-control pattern | medium |
| `resources/js/modules/cash-register/components/SessionList.vue` | Historial de sesiones (apertura/cierre/usuario/fecha) | Untouched: same raw-control pattern | medium |
| `resources/js/modules/cash-register/components/CashReports.vue` | Reportes de caja (diario, periodo, resumen ejecutivo) | Untouched: gradient cards, custom filters | medium |
| `resources/js/modules/cash-register/components/PendingPaymentsList.vue` | Pagos pendientes con busqueda y filtros | Untouched: raw `<input>` borders, custom spinner | small |

Cross-cutting components also touched by pagos:

| Component file | Pagos use |
| --- | --- |
| `resources/js/components/ui/CurrencyInput.vue` | Sole canonical money input across all cash-register modals — `S/` prefix, decimal, validation |
| `resources/js/components/ui/ReceiptPreview.vue` | Comprobante (boleta) preview for print/download |
| `resources/js/components/ui/Card.vue`, `Button.vue`, `Modal.vue`, `Tabs.vue`, `EmptyState.vue`, `LoadingSpinner.vue` | Reused by every pagos screen and component |

## Inventory — Backend

Controllers (all under `app/Http/Controllers/Api/`):

| File | Role |
| --- | --- |
| `TransactionController.php` | apiResource `transactions` + `list`, `void`, `receipt`, `generateReceipt` |
| `MercadoPagoController.php` | `createPreference`, `webhook` (HMAC-validated, async dispatch via `ProcessMercadoPagoWebhook` job) |
| `CashRegisterController.php` | `current`, `index`, `show`, `open`, `close`, `summary`, `closureReport`, `movements` for `/cash-register-sessions/*` |
| `CashMovementController.php` | CRUD for `cash_movements` |
| `CashReportController.php` | `period`, `export/{format}` reports |
| `PaymentMethodController.php` | Admin apiResource `payment-methods` + public `payment-methods/active` (gateway_type aware) |
| `PendingPaymentsController.php` | `index`, `pay` (encola el cobro de una cita pendiente) |
| `BillingController.php` | `readyToBill`, `paymentPreview`, `generateQuotation` (links appointments to quotations) |

Services:

| File | Role |
| --- | --- |
| `app/Services/TransactionService.php` | createTransaction, void, receipt; computes `subtotal/discount/commission/tax`; enforces active cash session |
| `app/Services/CashRegisterService.php` | Session lifecycle + summary aggregation |
| `app/Services/BillingService.php` | `readyToBill` query (sum payments vs final_amount), quotation generation |
| `app/Services/MercadoPagoService.php` | SDK mercadopago/dx-php v3.10 wrapper; preference creation + HMAC webhook signature validation |
| `app/Services/Reports/CashReportService.php` | Period/summary report aggregation |
| `app/Services/Reports/RevenueReportService.php` | Revenue aggregations (consumes `transactions`) |

Jobs/events:

| File | Role |
| --- | --- |
| `app/Jobs/ProcessMercadoPagoWebhook.php` | Async webhook processing, idempotency, retry policy `tries=3, backoff=[60,300,900]` |
| `app/Events/PaymentReceived.php`, `PaymentRegistered.php`, `TransactionCreated.php`, `TransactionUpdated.php`, `CashMovementCreated.php` | Broadcast events consumed by `useCashRegister`/`useEcho` for real-time UI updates |
| `app/Listeners/LogPaymentReceived.php` | Audit log listener |

Form requests under `app/Http/Requests/`: `StoreTransactionRequest.php`, `OpenCashRegisterRequest.php`, `CloseCashRegisterRequest.php`, `StoreCashMovementRequest.php`.

Models under `app/Models/`: `Transaction.php`, `PaymentMethod.php`, `PaymentPlan.php`, `PaymentGatewayTransaction.php`, `PaymentGatewayWebhookEvent.php`, `CashRegisterSession.php`, `CashMovement.php`, `Quotation.php`.

## Database touchpoints

| File | Touch |
| --- | --- |
| `database/migrations/2025_10_24_202537_create_transactions_table.php` | Base transactions table |
| `database/migrations/2025_10_24_202521_create_payment_methods_table.php` | Payment methods catalog |
| `database/migrations/2025_10_24_202553_create_payment_plans_table.php` | Installment plans |
| `database/migrations/2025_10_25_000000_add_cash_register_fields_to_transactions_table.php` | session_id linkage |
| `database/migrations/2026_06_08_110000_add_appointment_id_to_quotations_and_quotation_id_to_transactions.php` | Quotation-payment linkage |
| `database/migrations/2026_06_11_001035_add_soft_deletes_to_transactions_table.php` | Soft-deletes |
| `database/migrations/2026_06_11_001910_add_soft_deletes_to_payment_methods_table.php` | Soft-deletes |
| `database/migrations/2026_06_11_001911_add_soft_deletes_to_payment_plans_table.php` | Soft-deletes |
| `database/migrations/2026_06_13_120000_add_is_system_to_payment_methods_table.php` | System/custom flag |
| `database/migrations/2026_06_13_140000_add_gateway_fields_to_payment_methods_table.php` | gateway_type, gateway_config (encrypted at app layer) |
| `database/migrations/2026_06_13_150000_create_payment_gateway_transactions_table.php` | Per-gateway transaction record |
| `database/migrations/2026_06_13_150001_create_payment_gateway_webhook_events_table.php` | Webhook idempotency log |

Reporting views or admin grids that render these tables: `BillingController@readyToBill`, `CashReportController@period`, `TransactionController@index`, `PaymentMethodController@index`, dashboard `cash-status` card.

## Test coverage surface

| File | Coverage |
| --- | --- |
| `tests/Feature/Api/TransactionEndpointsTest.php` | apiResource transactions + list/void/receipt endpoints |
| `tests/Feature/Api/TransactionVoidAndReceiptTest.php` | Void + receipt PDF flow |
| `tests/Feature/Api/CashRegisterEndpointsTest.php` | open/close/summary/movements/closureReport endpoints |
| `tests/Feature/Api/CashRegisterValidationTest.php` | Validation rules |
| `tests/Feature/Api/CashMovementPermissionTest.php` | Per-role authorization |
| `tests/Feature/Modules/CashCloseAndClosureReportTest.php` | E2E close + closure report |
| `tests/Unit/Services/TransactionServiceTest.php` | TransactionService.createTransaction unit |
| `tests/Unit/Events/PaymentReceivedChannelTest.php` | Broadcast channel authorization for `.payment.received` |
| `tests/Unit/Listeners/LogPaymentReceivedTest.php` | Audit listener |
| `tests/Unit/Composables/PaymentModal401RedirectTest.php` | UXF-021: 401 must tear down session and bounce to /login |
| `tests/Unit/Middleware/RequireActiveCashSessionTest.php` | Middleware enforcing open session for transactions |
| `tests/Unit/Composables/FormatPENLabelTest.php` | Currency formatting |
| `tests/Unit/Composables/ComposablesStandardizationTest.php` | `useCashRegister`/`useTransactions`/`usePaymentMethods` standard contracts |
| `tests/Unit/Composables/PermissionsCreateMovementTest.php` | Permission gate for cash movements |
| `tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php` | Pins `canvasRoutes` array literal (includes 4 pagos routes) — must remain green |

No test file was found for `MercadoPagoController` or `ProcessMercadoPagoWebhook`; coverage gap noted but out of scope for visual polish.

## Known gotchas

- **Idempotency on webhooks**: `ProcessMercadoPagoWebhook` retries with backoff `[60, 300, 900]`. UI must never show "paid" twice for the same `external_id`; webhook log table has `unique(external_id, event_type)`.
- **Double-charge on resubmit**: `PaymentModal.handleSubmit` and `switchToMercadoPago` both call `createTransaction`; disabled state on `submit` is enforced via `:disabled="!canSubmit"` and `:loading="loading"` but visual disabled affordance uses legacy `disabled:opacity-30` patterns in `ReadyToBillPage`.
- **Money formatting**: always `Intl.NumberFormat('es-PE', { currency: 'PEN' })`; manual `S/ ${n.toFixed(2)}` is used in `ReadyToBillPage` — must remain consistent with `CurrencyInput` (`S/` prefix + 2 decimals) and any polished surface cannot use a different prefix.
- **Encryption at rest**: `PaymentMethod.gateway_config` is encrypted via `Crypt::encryptString` (APP_KEY). Any new admin UI must never echo the raw config back to the DOM.
- **Active session enforcement**: `TransactionService.createTransaction` throws `ValidationException` if no active session; UI must keep this visible to the user.
- **Soft-deletes**: `Transaction`, `PaymentMethod`, `PaymentPlan`, `CashRegisterSession`, `CashMovement` all use `SoftDeletes`. Reporting views must not show deleted rows.
- **Refund direction**: `Transaction.type` supports `payment` and `refund`; UI uses "Egreso" for refund in `TransactionModal` and `MovementList`. Vocabulary must stay aligned.
- **Currency precision**: all monetary columns cast to `decimal:2`; display layer must never show >2 decimals.
- **Real-time updates**: `useCashRegister` listens on Echo channels `cash-register`, `.cash-session.opened/closed`, `.payment.registered`, `.cash-movement.created`. New pagos screens must reuse the existing channel subscriptions.
- **Accessibility of financial data**: tabular numerics on lists use raw `<table>` markup; screen readers must still expose row headers, currency context, ARIA roles. Current code does not.
- **Currency formatting helpers**: `formatCurrency` is reimplemented in nearly every modal/page (4+ copies). Polish should consolidate or at least keep signatures identical.

## Out-of-scope

- Patient billing configuration (insurance claims forms, treatment plan pricing rules)
- Quotation template editor (PDF layout, terms text editor)
- Standalone revenue BI dashboard visuals (`/business-intelligence` already pinned in PR0)
- `DashboardPage` `cash-status` pill — already wired into PR0 (`data-cash-pill-state`)
- `ProcessMercadoPagoWebhook` async job retry semantics
- `BroadcastingAuthController` and WebSocket channel auth