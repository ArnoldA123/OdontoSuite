<?php

use App\Authorization\Permissions;
use App\Http\Controllers\Api\AccountingLockController;
use App\Http\Controllers\Api\AiImageAnalysisController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentCustodyController;
use App\Http\Controllers\Api\AppointmentTypeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CashReportController;
use App\Http\Controllers\Api\ClinicalEncounterController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ContinuityController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DentalChairController;
use App\Http\Controllers\Api\DentalPieceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\MercadoPagoController;
use App\Http\Controllers\Api\PatientChargeController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientRecallController;
use App\Http\Controllers\Api\PatientStatementController;
use App\Http\Controllers\Api\PayerController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PendingPaymentsController;
use App\Http\Controllers\Api\ProcedureCatalogController;
use App\Http\Controllers\Api\ProcedureCatalogFavoriteController;
use App\Http\Controllers\Api\ProcedurePriceController;
use App\Http\Controllers\Api\ProcedureStatsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ReminderTemplateController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\SpecialtyRecordController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TreatmentPlanController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Authorization lives in App\Authorization\Permissions (#280). Every
| authenticated route declares the permission it needs through the
| `permission` middleware; a role list never appears here. The matrix
| role => permissions is the single source of truth and the SPA derives its
| `usePermissions` flags from the list returned by /api/auth/me.
|
| Route order matters: fixed segments (`/active`, `/search`, `/summary`,
| `/attachments`) are declared before the apiResource that would otherwise
| capture them as `{id}`.
|
*/

// Public routes (no authentication) with rate limiting.
// The `auth` group below provides /auth/login with the same handler and
// throttle.login. This root route stays for consumers that use /login instead
// of /auth/login.
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])
    ->middleware('throttle.login');

// Mercado Pago webhook (no auth — validated by HMAC signature in the controller).
Route::post('payments/webhooks/mercadopago', [MercadoPagoController::class, 'webhook']);

// Authentication routes.
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,10');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,10');

    Route::middleware('auth:sanctum')->group(function () {
        // Session infrastructure: every authenticated role must be able to read
        // its own payload and sign out, even if its role has no permission.
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Authenticated routes.
Route::middleware('auth:sanctum')->group(function () {
    // Slice 11 / BF-025: the inline broadcasting/auth closure was extracted
    // into App\Http\Controllers\Api\BroadcastingAuthController so the
    // handler can be unit-tested and tightened (503 on misconfig).
    Route::post('/broadcasting/auth', \App\Http\Controllers\Api\BroadcastingAuthController::class)
        ->middleware('permission:'.Permissions::AUTHORIZE_BROADCASTING);

    // Authenticated user payload.
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('permission:'.Permissions::VIEW_PROFILE);

    // Lookup endpoints consumed by every authenticated role.
    Route::get('users/active', [UserController::class, 'active'])
        ->middleware('permission:'.Permissions::VIEW_USER);
    Route::get('appointment-types/active', [AppointmentTypeController::class, 'active'])
        ->middleware('permission:'.Permissions::VIEW_APPOINTMENT_TYPE);
    Route::get('dental-chairs/active', [DentalChairController::class, 'active'])
        ->middleware('permission:'.Permissions::VIEW_DENTAL_CHAIR);

    // Business Intelligence reports (#55/#191: administrador and finanzas).
    Route::middleware('permission:'.Permissions::VIEW_REPORT)->group(function () {
        // #290 S7: consolidated revenue per sede and per professional.
        Route::get('reports/consolidated', [ReportController::class, 'consolidated']);
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/appointments', [ReportController::class, 'appointments']);
        Route::get('reports/patients', [ReportController::class, 'patients']);
        Route::get('reports/professionals', [ReportController::class, 'professionals']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/utilization', [ReportController::class, 'utilization']);
    });
    Route::get('reports/{reportType}/export', [ReportController::class, 'export'])
        ->middleware('permission:'.Permissions::EXPORT_REPORT);

    Route::get('branches/active', [BranchController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_BRANCH);

    // Active payment methods for cash dropdowns (Sprint 2).
    Route::get('payment-methods/active', [PaymentMethodController::class, 'active'])
        ->middleware('permission:'.Permissions::VIEW_PAYMENT_METHOD);

    // Dashboard (every authenticated role).
    Route::middleware('permission:'.Permissions::VIEW_DASHBOARD)->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        // BF-026 (slice 11): removed duplicate `dashboard/today` alias.
        Route::get('dashboard/appointments-today', [DashboardController::class, 'today']);
        Route::get('dashboard/upcoming', [DashboardController::class, 'upcoming']);
        Route::get('dashboard/pending', [DashboardController::class, 'pending']);
    });

    // Users (administrator only).
    Route::middleware('permission:'.Permissions::MANAGE_USER)->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Patients: read for every role; write follows the CREDENTIALS.md matrix.
    Route::middleware('permission:'.Permissions::VIEW_PATIENT)->group(function () {
        Route::get('patients/search', [PatientController::class, 'search']);
        Route::get('patients', [PatientController::class, 'index']);
        Route::get('patients/{patient}', [PatientController::class, 'show']);
    });
    Route::get('patients/{patient}/export', [PatientController::class, 'export'])
        ->middleware('permission:'.Permissions::EXPORT_PATIENT);

    // #287 (T3, S3/S9) — the patient statement. It reads the ledger of the
    // patient, which is shared between sedes (ADR-0010), so it carries no
    // `cash.session`: there is no drawer involved in reading a balance.
    Route::get('patients/{patient}/statement', [PatientStatementController::class, 'show'])
        ->middleware('permission:'.Permissions::VIEW_PATIENT_STATEMENT);
    Route::post('patients', [PatientController::class, 'store'])
        ->middleware('permission:'.Permissions::CREATE_PATIENT);
    Route::put('patients/{patient}', [PatientController::class, 'update'])
        ->middleware('permission:'.Permissions::UPDATE_PATIENT);
    Route::patch('patients/{patient}', [PatientController::class, 'update'])
        ->middleware('permission:'.Permissions::UPDATE_PATIENT);
    Route::delete('patients/{patient}', [PatientController::class, 'destroy'])
        ->middleware('permission:'.Permissions::DELETE_PATIENT);

    // Billing / payment hook (Sprint 3: administrador, finanzas, recepcion).
    Route::middleware('permission:'.Permissions::VIEW_BILLING)->group(function () {
        Route::get('appointments/ready-to-bill', [BillingController::class, 'readyToBill']);
        Route::get('appointments/{appointment}/payment-preview', [BillingController::class, 'paymentPreview']);
    });
    Route::post('appointments/{appointment}/generate-quotation', [BillingController::class, 'generateQuotation'])
        ->middleware('permission:'.Permissions::CREATE_QUOTATION_FROM_APPOINTMENT);

    // Appointments.
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->middleware('permission:'.Permissions::UPDATE_APPOINTMENT_STATUS);
    Route::middleware('permission:'.Permissions::MANAGE_APPOINTMENT)->group(function () {
        Route::patch('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::apiResource('appointments', AppointmentController::class);
    });

    // Reminder templates (administrator only; previously stacked inside the
    // six-role appointment group).
    Route::apiResource('reminder-templates', ReminderTemplateController::class)
        ->middleware('permission:'.Permissions::MANAGE_REMINDER_TEMPLATE);

    // Audit logs: read-only (BF-004, slice 01 restricts to administrator).
    Route::middleware('permission:'.Permissions::VIEW_AUDIT_LOG)->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::get('audit-logs/patient/{patientId}', [AuditLogController::class, 'byPatient']);
        Route::get('audit-logs/user/{userId}', [AuditLogController::class, 'byUser']);
        Route::get('audit-logs/dental-chair/{chairId}', [AuditLogController::class, 'byDentalChair']);
        Route::get('audit-logs/appointment-type/{typeId}', [AuditLogController::class, 'byAppointmentType']);
        Route::get('audit-logs/{id}', [AuditLogController::class, 'show']);
    });

    // Reminders.
    Route::middleware('permission:'.Permissions::MANAGE_REMINDER)->group(function () {
        Route::apiResource('reminders', ReminderController::class);
    });
    Route::post('reminders/{id}/send', [ReminderController::class, 'send'])
        ->middleware('permission:'.Permissions::SEND_REMINDER);

    // Consultation flow (post check-in).
    Route::middleware('permission:'.Permissions::VIEW_CONSULTATION)->group(function () {
        Route::get('appointments/{appointment}/consultation-context', [ConsultationController::class, 'context']);
    });
    // F1 (#281): each custody status has one door and one permission.
    Route::post('appointments/{appointment}/check-in', [AppointmentCustodyController::class, 'checkIn'])
        ->middleware('permission:'.Permissions::CHECK_IN_APPOINTMENT);
    Route::post('appointments/{appointment}/announce', [AppointmentCustodyController::class, 'announce'])
        ->middleware('permission:'.Permissions::ANNOUNCE_PATIENT);
    Route::post('appointments/{appointment}/start-attention', [AppointmentCustodyController::class, 'startAttention'])
        ->middleware('permission:'.Permissions::START_CONSULTATION);
    Route::get('my-day', [AppointmentCustodyController::class, 'myDay'])
        ->middleware('permission:'.Permissions::VIEW_MY_DAY);

    // F4 (#284): continuity between acts. The desk reads overdue recalls and
    // the conduct left at a closure; reception materialises it through the
    // agenda, never by writing an appointment directly.
    Route::middleware('permission:'.Permissions::VIEW_CONTINUITY)->group(function () {
        Route::get('continuity/queue', [ContinuityController::class, 'queue']);
        Route::get('continuity-requests/{continuityRequest}', [ContinuityController::class, 'show']);
        Route::get('patient-recalls', [PatientRecallController::class, 'index']);
        Route::get('patient-recalls/metrics', [PatientRecallController::class, 'metrics']);
        Route::get('patient-recalls/{recall}', [PatientRecallController::class, 'show']);
    });
    Route::middleware('permission:'.Permissions::MANAGE_CONTINUITY)->group(function () {
        Route::post('patient-recalls', [PatientRecallController::class, 'store']);
        Route::post('patient-recalls/{recall}/schedule', [PatientRecallController::class, 'schedule']);
        Route::post('patient-recalls/{recall}/complete', [PatientRecallController::class, 'complete']);
        Route::post('patient-recalls/{recall}/cancel', [PatientRecallController::class, 'cancel']);
        Route::post('continuity-requests/{continuityRequest}/schedule', [ContinuityController::class, 'schedule']);
        Route::post('continuity-requests/{continuityRequest}/dismiss', [ContinuityController::class, 'dismiss']);
    });
    // #280: closing a consultation writes ClinicalEvolution, treatment plan,
    // materials and attachments. Reception must not reach it.
    Route::post('appointments/{appointment}/complete', [ConsultationController::class, 'complete'])
        ->middleware('permission:'.Permissions::COMPLETE_APPOINTMENT);
    // F2 (#282): the act itself. Opening and voiding are clinical acts; the
    // urgency door invents no agenda row (S8). Reading it is clinical too: the
    // index is the only way to reach an act that has no appointment to be found
    // by.
    Route::get('encounters', [ClinicalEncounterController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_ENCOUNTER);
    Route::get('encounters/{encounter}', [ClinicalEncounterController::class, 'show'])
        ->middleware('permission:'.Permissions::VIEW_ENCOUNTER);
    Route::post('encounters', [ClinicalEncounterController::class, 'store'])
        ->middleware('permission:'.Permissions::CREATE_ENCOUNTER);
    Route::post('encounters/{encounter}/complete', [ClinicalEncounterController::class, 'complete'])
        ->middleware('permission:'.Permissions::CLOSE_ENCOUNTER);
    Route::post('encounters/{encounter}/void', [ClinicalEncounterController::class, 'void'])
        ->middleware('permission:'.Permissions::VOID_ENCOUNTER);

    // Product catalog (material autocomplete in the consultation wizard).
    Route::get('products/search', [ProductController::class, 'search'])
        ->middleware('permission:'.Permissions::VIEW_PRODUCT);

    // Warehouse (#295 S6): the read surface. `products.stock_quantity` is the
    // only truth of current stock and `stock_movements` is the ledger behind
    // it. Reception and the count adjustment write the ledger and arrive with
    // their own door.
    Route::middleware('permission:'.Permissions::VIEW_INVENTORY)->group(function () {
        Route::get('inventory/products', [InventoryController::class, 'products']);
        Route::get('inventory/products/{product}', [InventoryController::class, 'showProduct']);
        Route::get('inventory/movements', [InventoryController::class, 'movements']);
        Route::get('inventory/alerts', [InventoryController::class, 'alerts']);
    });
    // #295 S6: receiving a purchase and counting a shelf move stock and money,
    // so they are warehouse work and not clinical writing.
    Route::middleware('permission:'.Permissions::MANAGE_INVENTORY)->group(function () {
        Route::post('inventory/receipts', [InventoryController::class, 'storeReceipt']);
        Route::post('inventory/adjustments', [InventoryController::class, 'storeAdjustment']);
    });

    // Procedure catalog (every role reads; administrator edits).
    Route::middleware('permission:'.Permissions::VIEW_PROCEDURE_CATALOG)->group(function () {
        Route::get('procedure-catalog', [ProcedureCatalogController::class, 'index']);
        Route::get('procedure-catalog/summary', [ProcedureCatalogController::class, 'summary']);
        Route::get('procedure-catalog/active', [ProcedureCatalogController::class, 'active']);
        Route::get('procedure-catalog/search', [ProcedureCatalogController::class, 'search']);
        Route::get('procedure-catalog/for-me', [ProcedureCatalogController::class, 'forMe']);
        Route::get('procedure-catalog/{id}', [ProcedureCatalogController::class, 'show']);
        Route::get('procedure-catalog/{id}/versions', [ProcedureCatalogController::class, 'versions']);
    });
    Route::middleware('permission:'.Permissions::MANAGE_PROCEDURE_CATALOG)->group(function () {
        Route::post('procedure-catalog', [ProcedureCatalogController::class, 'store']);
        Route::put('procedure-catalog/{id}', [ProcedureCatalogController::class, 'update']);
        Route::delete('procedure-catalog/{id}', [ProcedureCatalogController::class, 'destroy']);
    });

    // Procedure favorites (clinical roles only).
    Route::middleware('permission:'.Permissions::MANAGE_PROCEDURE_FAVORITE)->group(function () {
        Route::get('procedure-catalog-favorites', [ProcedureCatalogFavoriteController::class, 'index']);
        Route::post('procedure-catalog/{id}/favorite', [ProcedureCatalogFavoriteController::class, 'store']);
        Route::delete('procedure-catalog/{id}/favorite', [ProcedureCatalogFavoriteController::class, 'destroy']);
        Route::put('procedure-catalog-favorites/reorder', [ProcedureCatalogFavoriteController::class, 'reorder']);
    });

    // Catalog usage statistics (Sprint 3 fix IM-5: administrator and finanzas).
    Route::get('admin/procedure-stats', [ProcedureStatsController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_PROCEDURE_STATS);
    Route::post('admin/procedure-catalog/import', [ProcedureCatalogController::class, 'import'])
        ->middleware('permission:'.Permissions::IMPORT_PROCEDURE_CATALOG);

    // Specialties master data.
    Route::middleware('permission:'.Permissions::VIEW_SPECIALTY)->group(function () {
        Route::get('specialties', [SpecialtyController::class, 'index']);
        Route::get('specialties/active', [SpecialtyController::class, 'active']);
        Route::get('specialties/{id}', [SpecialtyController::class, 'show']);
    });
    Route::middleware('permission:'.Permissions::MANAGE_SPECIALTY)->group(function () {
        Route::post('specialties', [SpecialtyController::class, 'store']);
        Route::put('specialties/{id}', [SpecialtyController::class, 'update']);
    });

    // Rooms and appointment types (administrator; the rest reads /active).
    Route::middleware('permission:'.Permissions::MANAGE_DENTAL_CHAIR)->group(function () {
        Route::get('dental-chairs/search', [DentalChairController::class, 'search']);
        Route::apiResource('dental-chairs', DentalChairController::class);
    });
    Route::middleware('permission:'.Permissions::MANAGE_APPOINTMENT_TYPE)->group(function () {
        Route::get('appointment-types/search', [AppointmentTypeController::class, 'search']);
        Route::apiResource('appointment-types', AppointmentTypeController::class);
    });

    // Branches (administrator only).
    // #283 S2: la sede administra su sede; el catálogo de sedes es alcance de
    // grupo (branch.global rechaza 403 a un administrador con sede asignada).
    // Branch tariff (#290 S8): the price one sede charges, inside its window.
    Route::prefix('branches/{branch}/procedure-prices')
        ->middleware('permission:'.Permissions::MANAGE_BRANCH_PRICE)
        ->group(function () {
            Route::get('/', [ProcedurePriceController::class, 'index']);
            Route::put('/', [ProcedurePriceController::class, 'update']);
            Route::delete('{priceId}', [ProcedurePriceController::class, 'destroy']);
        });

    // Convenios (#293 S3): the catalog the estimate reads, and the tariff agreed
    // with each one. `payers/active` is declared before the parameterised write
    // so a convenio called "active" can never shadow it.
    Route::get('payers/active', [PayerController::class, 'active'])
        ->middleware('permission:'.Permissions::VIEW_PAYER);
    Route::get('payers', [PayerController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_PAYER);
    Route::post('payers', [PayerController::class, 'store'])
        ->middleware('permission:'.Permissions::MANAGE_PAYER);
    Route::put('payers/{payer}', [PayerController::class, 'update'])
        ->middleware('permission:'.Permissions::MANAGE_PAYER);

    // Group scope (branch.global): a price agreed for a convenio is not a sede
    // decision, even when it carries an optional branch.
    Route::prefix('payers/{payer}/procedure-prices')
        ->middleware(['permission:'.Permissions::MANAGE_PAYER_PRICE, 'branch.global'])
        ->group(function () {
            Route::get('/', [ProcedurePriceController::class, 'payerIndex']);
            Route::put('/', [ProcedurePriceController::class, 'payerUpdate']);
            Route::delete('{priceId}', [ProcedurePriceController::class, 'payerDestroy']);
        });

    Route::apiResource('branches', BranchController::class)
        ->middleware(['permission:'.Permissions::MANAGE_BRANCH, 'branch.global']);

    // Accounting lock (F3, #283 S4/S5). One row per module and sede; the global
    // row (branch_id null) is group scope, so a sede administrator only freezes
    // their own sede.
    Route::middleware('permission:'.Permissions::MANAGE_ACCOUNTING_LOCK)->group(function () {
        Route::get('accounting-locks', [AccountingLockController::class, 'index']);
        Route::put('accounting-locks/{module}', [AccountingLockController::class, 'update']);
        Route::delete('accounting-locks/{module}', [AccountingLockController::class, 'destroy']);
    });

    // Treatment plans (clinical roles and administrator).
    Route::middleware('permission:'.Permissions::MANAGE_TREATMENT_PLAN)->group(function () {
        Route::get('treatment-plans/summary', [TreatmentPlanController::class, 'summary']);
        Route::get('treatment-plans/{id}/versions', [TreatmentPlanController::class, 'versions']);
        Route::get('treatment-plans/{id}/versions/{versionId}', [TreatmentPlanController::class, 'showVersion']);
        Route::apiResource('treatment-plans', TreatmentPlanController::class);
        Route::post('treatment-plans/{id}/change-status', [TreatmentPlanController::class, 'changeStatus']);
        Route::post('treatment-plans/{id}/duplicate', [TreatmentPlanController::class, 'duplicate']);
        Route::post('treatment-plans/{id}/add-item', [TreatmentPlanController::class, 'addItem']);
        Route::post('treatment-plans/items/{itemId}/alternatives', [TreatmentPlanController::class, 'addAlternative']);
        Route::post('treatment-plans/items/{itemId}/reject', [TreatmentPlanController::class, 'rejectItem']);
        Route::post('treatment-plans/alternatives/{alternativeId}/accept', [TreatmentPlanController::class, 'acceptAlternative']);
        Route::post('treatment-plans/alternatives/{alternativeId}/reject', [TreatmentPlanController::class, 'rejectAlternative']);
        Route::delete('treatment-plans/items/{itemId}', [TreatmentPlanController::class, 'removeItem']);
    });

    // Quotations: reception reads (#55), clinical roles, finanzas and
    // administrator write.
    Route::middleware('permission:'.Permissions::VIEW_QUOTATION)->group(function () {
        Route::get('quotations', [QuotationController::class, 'index']);
        Route::get('quotations/summary', [QuotationController::class, 'summary']);
        Route::get('quotations/patient/{patientId}', [QuotationController::class, 'byPatient']);
        Route::get('quotations/{id}/pdf', [QuotationController::class, 'downloadPDF']);
        Route::get('quotations/{id}', [QuotationController::class, 'show']);
    });
    Route::middleware('permission:'.Permissions::MANAGE_QUOTATION)->group(function () {
        Route::post('quotations', [QuotationController::class, 'store']);
        Route::put('quotations/{id}', [QuotationController::class, 'update']);
        Route::patch('quotations/{id}', [QuotationController::class, 'update']);
        Route::delete('quotations/{id}', [QuotationController::class, 'destroy']);
        Route::post('quotations/{id}/approve', [QuotationController::class, 'approve']);
        Route::post('quotations/{id}/reject', [QuotationController::class, 'reject']);
    });

    Route::middleware('permission:'.Permissions::MANAGE_EVOLUTION)->group(function () {
        Route::post('medical-records/{id}/evolutions', [MedicalRecordController::class, 'addEvolution']);
        Route::get('medical-records/{id}/evolutions', [MedicalRecordController::class, 'getEvolutions']);
        // The evolution addendum lives on the fixed `evolutions` segment, so
        // it is declared next to the other evolution routes and never reaches
        // the `{medical_record}` binding.
        Route::post('medical-records/evolutions/{evolution}/addenda', [MedicalRecordController::class, 'addEvolutionAddendum']);
    });

    // Medical records (clinical roles only). #292: a clinical history is
    // evidence, so it is neither edited in place nor deleted; the resource
    // exposes only read, alta and detalle, and the only later write is an
    // addendum.
    Route::middleware('permission:'.Permissions::MANAGE_MEDICAL_RECORD)->group(function () {
        Route::apiResource('medical-records', MedicalRecordController::class)
            ->only(['index', 'store', 'show']);
        Route::post('medical-records/{id}/addenda', [MedicalRecordController::class, 'addRecordAddendum']);
        Route::get('medical-records/patient/{patientId}/attachments', [MedicalRecordController::class, 'getAttachmentsByCategory']);
    });
    Route::get('medical-records/patient/{patientId}/stats', [MedicalRecordController::class, 'getStats'])
        ->middleware('permission:'.Permissions::VIEW_MEDICAL_RECORD_STATS);
    Route::middleware('permission:'.Permissions::MANAGE_ATTACHMENT)->group(function () {
        // Slice 01 / T-01.3 (API-001): DELETE attachment. Declared BEFORE
        // apiResource so the fixed `attachments` segment is not swallowed by
        // `{medical_record}` model binding.
        Route::delete('medical-records/attachments/{attachment}', [MedicalRecordController::class, 'deleteAttachment']);
        Route::post('medical-records/attachments', [MedicalRecordController::class, 'uploadAttachment']);
    });

    // Specialty records (specialist roles and administrator).
    Route::middleware('permission:'.Permissions::MANAGE_SPECIALTY_RECORD)->group(function () {
        Route::apiResource('specialty-records', SpecialtyRecordController::class);
        // The aggregate route MUST be declared before `{specialty}`: the
        // parameter matches the literal `all` and used to answer an empty list.
        Route::get('specialty-records/patient/{patientId}/all', [SpecialtyRecordController::class, 'getAllByPatient']);
        Route::get('specialty-records/patient/{patientId}/{specialty}/stats', [SpecialtyRecordController::class, 'getStats']);
        Route::get('specialty-records/patient/{patientId}/{specialty}', [SpecialtyRecordController::class, 'getByPatient']);
    });
    Route::get('dental-pieces', [DentalPieceController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_DENTAL_PIECE);

    // Cash register system. Issue #55: reception operates the register
    // (sessions and transactions, CRED-cash-recep / CRED-tx-recep). Issue #191:
    // pending payments are collected at the same counter. Movements, cash
    // reports and Mercado Pago stay with administrator/finanzas.
    // `override.accounting_lock` is consumed by AccountingLockService::guard(),
    // not by a gate of its own: it is declared here so every catalog permission
    // stays routed (RoutePermissionMatrixTest). The OR is inert today, because
    // both permissions are administrator-only, and the guard remains the only
    // place that decides whether a lock can be overridden.
    // #287, D7 — only a payment and its refund need an open drawer, and the
    // rule lives in TransactionService: a write-off or an adjustment is a
    // ledger entry and cannot demand a session it never writes to. A void keeps
    // its own drawer guard in the service (the session of the transaction must
    // still be open).
    Route::post('transactions/{transaction}/void', [TransactionController::class, 'void'])
        ->middleware('permission:'.Permissions::VOID_TRANSACTION.','.Permissions::OVERRIDE_ACCOUNTING_LOCK);

    // `receipt` keeps the drawer gate: it is a cash-register operation over a
    // transaction that already belongs to a session (slice 01 / T-01.1
    // registers it BEFORE apiResource so the fixed segment is not swallowed by
    // `{transaction}` model binding).
    Route::middleware(['permission:'.Permissions::CREATE_TRANSACTION, 'cash.session'])->group(function () {
        Route::post('transactions/{transaction}/receipt', [TransactionController::class, 'generateReceipt']);
    });
    Route::post('transactions', [TransactionController::class, 'store'])
        ->middleware('permission:'.Permissions::CREATE_TRANSACTION);
    Route::middleware(['permission:'.Permissions::MANAGE_TRANSACTION, 'cash.session'])->group(function () {
        Route::get('transactions/list', [TransactionController::class, 'list']);
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::match(['put', 'patch'], 'transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);
    });

    // Cash sessions: opening/closing does not require an active session (it
    // creates/ends it), so neither route carries cash.session.
    // IMPORTANT: fixed segments (active, closure-report) must precede the
    // apiResource so they are not captured by
    // GET /cash-register-sessions/{cash_register_session} -> show($id).
    Route::middleware('permission:'.Permissions::VIEW_CASH_REGISTER)->group(function () {
        Route::get('cash-register-sessions/active', [CashRegisterController::class, 'current']);
        Route::get('cash-register-sessions/{id}/closure-report', [CashRegisterController::class, 'closureReport']);
        Route::apiResource('cash-register-sessions', CashRegisterController::class)->only(['index', 'show']);
        // Cash follow-up S1: `{id}/close` ignored its `{id}` and delegated to
        // the generic close; not restored.
        Route::get('cash-register/current', [CashRegisterController::class, 'current']);
        Route::get('cash-register/sessions', [CashRegisterController::class, 'index']);
        Route::get('cash-register/sessions/{id}/movements', [CashRegisterController::class, 'movements']);
        // Slice 01 / T-01.1: 5 cash-register endpoints previously 404.
        Route::get('cash-register/summary', [CashRegisterController::class, 'summary']);
        Route::get('cash-register/sessions/{id}', [CashRegisterController::class, 'show']);
        Route::get('cash-register/sessions/{id}/closure-report', [CashRegisterController::class, 'closureReport']);
    });
    Route::post('cash-register/open', [CashRegisterController::class, 'open'])
        ->middleware('permission:'.Permissions::OPEN_CASH_REGISTER);
    Route::post('cash-register/close', [CashRegisterController::class, 'close'])
        ->middleware('permission:'.Permissions::CLOSE_CASH_REGISTER);

    // Pending payments (issue #191). The alias is consumed by the frontend
    // Cobros tab.
    Route::get('pending-payments', [PendingPaymentsController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_PENDING_PAYMENT);
    Route::post('pending-payments/{id}/pay', [PendingPaymentsController::class, 'pay'])
        ->middleware('permission:'.Permissions::PAY_PENDING_PAYMENT);
    Route::get('cash-register/pending-payments', [PendingPaymentsController::class, 'index'])
        ->middleware('permission:'.Permissions::VIEW_PENDING_PAYMENT);

    // #287 (T2, S3/S4) — the patient charge ledger. The closure writes the one
    // the act produced; this door records a charge no act produced. It carries
    // no `cash.session`: a charge is debt, not money in the drawer.
    Route::post('patient-charges', [PatientChargeController::class, 'store'])
        ->middleware('permission:'.Permissions::MANAGE_PATIENT_CHARGE);

    // Cash movements and reports.
    Route::apiResource('cash-movements', CashMovementController::class)
        ->middleware(['permission:'.Permissions::MANAGE_CASH_MOVEMENT, 'cash.session']);
    Route::middleware('permission:'.Permissions::VIEW_CASH_REPORT)->group(function () {
        Route::get('cash-reports/daily', [CashReportController::class, 'daily']);
        Route::get('cash-reports/period', [CashReportController::class, 'period']);
        Route::get('cash-register/reports/period', [CashReportController::class, 'period']);
    });
    Route::post('cash-register/reports/export/{format}', [CashReportController::class, 'export'])
        ->where('format', 'excel|pdf|csv')
        ->middleware('permission:'.Permissions::EXPORT_CASH_REPORT);

    // Payment methods CRUD (administrator only; the public /active lookup is
    // declared above).
    Route::apiResource('payment-methods', PaymentMethodController::class)
        ->middleware('permission:'.Permissions::MANAGE_PAYMENT_METHOD);

    // Mercado Pago (Sprint 3, plan #11).
    Route::post('payments/mercadopago/preference', [MercadoPagoController::class, 'createPreference'])
        ->middleware('permission:'.Permissions::MANAGE_PAYMENT_MERCADOPAGO);

    // AI assistance (odontologists and specialists).
    Route::post('ai-analysis/upload-and-analyze', [AiImageAnalysisController::class, 'analyzeUploadedImage'])
        ->middleware('permission:'.Permissions::CREATE_AI_ANALYSIS);
    Route::middleware('permission:'.Permissions::VIEW_AI_ANALYSIS)->group(function () {
        // Specific routes first (before parameter routes).
        Route::get('ai-analysis/stats', [AiImageAnalysisController::class, 'stats']);
        Route::get('ai-analysis/pending', [AiImageAnalysisController::class, 'pending']);
        Route::get('ai-analysis', [AiImageAnalysisController::class, 'index']);
        Route::get('ai-analysis/attachment/{attachmentId}', [AiImageAnalysisController::class, 'byAttachment']);
        Route::get('ai-analysis/patient/{patientId}', [AiImageAnalysisController::class, 'byPatient']);
        Route::get('ai-analysis/{id}', [AiImageAnalysisController::class, 'show']);
    });
    // Parameter routes last.
    Route::post('ai-analysis/analyze/{attachmentId}', [AiImageAnalysisController::class, 'analyze'])
        ->middleware('permission:'.Permissions::CREATE_AI_ANALYSIS);
    Route::post('ai-analysis/{id}/review', [AiImageAnalysisController::class, 'review'])
        ->middleware('permission:'.Permissions::REVIEW_AI_ANALYSIS);
    Route::post('ai-analysis/{id}/void', [AiImageAnalysisController::class, 'void'])
        ->middleware('permission:'.Permissions::VOID_AI_ANALYSIS);
});
