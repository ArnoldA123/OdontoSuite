<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DentalChairController;
use App\Http\Controllers\Api\AppointmentTypeController;
use App\Http\Controllers\Api\ProcedureCatalogController;
use App\Http\Controllers\Api\ProcedureCatalogFavoriteController;
use App\Http\Controllers\Api\ProcedureStatsController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\ReminderTemplateController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\TreatmentPlanController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\SpecialtyRecordController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MercadoPagoController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CashReportController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\PendingPaymentsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AiImageAnalysisController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas (sin autenticación) con rate limiting para seguridad
// El grupo de rutas 'auth' (más abajo) provee /auth/login con el mismo handler y throttle.login.
// Esta ruta raíz se mantiene por compatibilidad con consumers que usan /login en vez de /auth/login.
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])
    ->middleware('throttle.login'); // Rate limiting personalizado: 3/min, bloqueo 10min después de 5 errores

// Mercado Pago webhook (sin auth — validado por firma HMAC en el controller)
// Sprint 3 (plan #11): MP envia notificaciones POST a esta URL.
Route::post('payments/webhooks/mercadopago', [MercadoPagoController::class, 'webhook']);

// Grupo de rutas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle.login'); // Rate limiting personalizado
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,10'); // 3 intentos cada 10 minutos
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,10'); // 5 intentos cada 10 minutos

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});


// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {
    // Slice 11 / BF-025: the inline broadcasting/auth closure was extracted
    // into App\Http\Controllers\Api\BroadcastingAuthController so the
    // handler can be unit-tested and tightened (503 on misconfig).
    Route::post('/broadcasting/auth', \App\Http\Controllers\Api\BroadcastingAuthController::class);

    // Información del usuario autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout se expone como /auth/logout dentro del grupo prefix('auth') más arriba.
    // Esta ruta /logout a nivel raíz fue eliminada por duplicidad (C-5, Sprint 0).

    // Rutas /active para el frontend (accesibles para todos los roles autenticados)
    Route::get('users/active', [UserController::class, 'active']);
    Route::get('appointment-types/active', [AppointmentTypeController::class, 'active']);
    Route::get('dental-chairs/active', [DentalChairController::class, 'active']);

    // Rutas de reportes para BusinessIntelligencePage
    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/appointments', [ReportController::class, 'appointments']);
    Route::get('reports/patients', [ReportController::class, 'patients']);
    Route::get('reports/professionals', [ReportController::class, 'professionals']);
    Route::get('reports/revenue', [ReportController::class, 'revenue']);
    Route::get('reports/utilization', [ReportController::class, 'utilization']);
    Route::get('reports/{reportType}/export', [ReportController::class, 'export']);

    // Rutas de sucursales para el frontend (accesible para todos los roles autenticados)
    Route::get('branches/active', [BranchController::class, 'index']);

    // Metodos de pago activos (accesible para todos los roles autenticados).
    // Sprint 2: endpoint separado del CRUD admin para que recepcionistas
    // y finanzas puedan listar metodos en dropdowns de cobro sin role:admin.
    Route::get('payment-methods/active', [PaymentMethodController::class, 'index']);

    // Dashboard (todos los roles autenticados)
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    // BF-026 (slice 11): removed duplicate `dashboard/today` alias. Frontend
    // already uses the canonical `dashboard/appointments-today` endpoint.
    Route::get('dashboard/appointments-today', [DashboardController::class, 'today']);
    Route::get('dashboard/upcoming', [DashboardController::class, 'upcoming']);

    // Usuarios (solo administrador)
    Route::middleware('role:administrador')->group(function () {
        Route::apiResource('users', UserController::class);
    });




    // Pacientes (todos los roles)
    Route::get('patients/search', [PatientController::class, 'search']);
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/export', [PatientController::class, 'export']);

    // Citas (todos los roles excepto finanzas)
    Route::middleware('role:administrador,recepcionista,odontologo,implantologo,tecnico_dental,asistente')->group(function () {
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::apiResource('appointments', AppointmentController::class);
        Route::apiResource('dental-chairs', DentalChairController::class);
        Route::apiResource('appointment-types', AppointmentTypeController::class);
        Route::apiResource('reminder-templates', ReminderTemplateController::class)->middleware('role:administrador');

        // Audit logs: read-only (BF-004). Previously apiResource('audit-logs')
        // registered POST/PUT/PATCH/DELETE which 500'd because AuditLogController
        // does not implement store/update/destroy. Slice 01 restricts to admin
        // and exposes only the GET verbs explicitly.
        Route::middleware('role:administrador')->group(function () {
            Route::get('audit-logs', [AuditLogController::class, 'index']);
            Route::get('audit-logs/patient/{patientId}', [AuditLogController::class, 'byPatient']);
            Route::get('audit-logs/user/{userId}', [AuditLogController::class, 'byUser']);
            Route::get('audit-logs/dental-chair/{chairId}', [AuditLogController::class, 'byDentalChair']);
            Route::get('audit-logs/appointment-type/{typeId}', [AuditLogController::class, 'byAppointmentType']);
            Route::get('audit-logs/{id}', [AuditLogController::class, 'show']);
        });

        // Recordatorios
        Route::apiResource('reminders', ReminderController::class);
        Route::post('reminders/{id}/send', [ReminderController::class, 'send']);

        // Flujo de consulta (post check-in)
        Route::get('appointments/{appointment}/consultation-context', [ConsultationController::class, 'context']);
        Route::post('appointments/{appointment}/check-in', [ConsultationController::class, 'checkIn']);
        Route::post('appointments/{appointment}/complete', [ConsultationController::class, 'complete']);

        // Catalogo de productos (autocomplete para materiales en consulta)
        Route::get('products/search', [ProductController::class, 'search']);
    });

    // Catálogo de procedimientos (Sprint 2: clínicos, admin y recep consultan; solo admin edita)
    Route::get('procedure-catalog', [ProcedureCatalogController::class, 'index']);
    Route::get('procedure-catalog/active', [ProcedureCatalogController::class, 'active']);
    Route::get('procedure-catalog/search', [ProcedureCatalogController::class, 'search']);
    Route::get('procedure-catalog/for-me', [ProcedureCatalogController::class, 'forMe']);
    Route::get('procedure-catalog/{id}', [ProcedureCatalogController::class, 'show']);
    Route::middleware('role:administrador')->group(function () {
        Route::post('procedure-catalog', [ProcedureCatalogController::class, 'store']);
        Route::put('procedure-catalog/{id}', [ProcedureCatalogController::class, 'update']);
        Route::delete('procedure-catalog/{id}', [ProcedureCatalogController::class, 'destroy']);
    });

    // Favoritos de procedimientos (solo roles clínicos)
    Route::middleware('role:odontologo,implantologo,tecnico_dental,asistente')->group(function () {
        Route::get('procedure-catalog-favorites', [ProcedureCatalogFavoriteController::class, 'index']);
        Route::post('procedure-catalog/{id}/favorite', [ProcedureCatalogFavoriteController::class, 'store']);
        Route::delete('procedure-catalog/{id}/favorite', [ProcedureCatalogFavoriteController::class, 'destroy']);
        Route::put('procedure-catalog-favorites/reorder', [ProcedureCatalogFavoriteController::class, 'reorder']);
    });

    // Sprint 3 fix (IM-5): stats de uso del catalogo (admin y finanzas)
    Route::middleware('role:administrador,finanzas')->group(function () {
        Route::get('admin/procedure-stats', [ProcedureStatsController::class, 'index']);
        Route::post('admin/procedure-catalog/import', [ProcedureCatalogController::class, 'import']);
    });

    // Especialidades (maestro)
    Route::get('specialties', [SpecialtyController::class, 'index']);
    Route::get('specialties/active', [SpecialtyController::class, 'active']);
    Route::get('specialties/{id}', [SpecialtyController::class, 'show']);
    Route::middleware('role:administrador')->group(function () {
        Route::post('specialties', [SpecialtyController::class, 'store']);
        Route::put('specialties/{id}', [SpecialtyController::class, 'update']);
    });

    // Billing / hook de pago (Sprint 3)
    Route::middleware('role:administrador,recepcionista,odontologo,implantologo,tecnico_dental,asistente,finanzas')->group(function () {
        Route::get('appointments/ready-to-bill', [BillingController::class, 'readyToBill']);
        Route::get('appointments/{appointment}/payment-preview', [BillingController::class, 'paymentPreview']);
        Route::post('appointments/{appointment}/generate-quotation', [BillingController::class, 'generateQuotation']);
    });

    // Sucursales (solo administrador)
    Route::middleware('role:administrador')->group(function () {
        Route::apiResource('branches', BranchController::class);
    });


    // Planes de tratamiento (clínicos y admin)
    Route::middleware('role:administrador,odontologo,implantologo,tecnico_dental')->group(function () {
        Route::apiResource('treatment-plans', TreatmentPlanController::class);
        Route::post('treatment-plans/{id}/change-status', [TreatmentPlanController::class, 'changeStatus']);
        Route::post('treatment-plans/{id}/duplicate', [TreatmentPlanController::class, 'duplicate']);
        Route::post('treatment-plans/{id}/add-item', [TreatmentPlanController::class, 'addItem']);
        Route::delete('treatment-plans/items/{itemId}', [TreatmentPlanController::class, 'removeItem']);
    });

    // Presupuestos (clínicos, admin y finanzas)
    Route::middleware('role:administrador,finanzas,odontologo,implantologo')->group(function () {
        Route::apiResource('quotations', QuotationController::class);
        Route::post('quotations/{id}/approve', [QuotationController::class, 'approve']);
        Route::post('quotations/{id}/reject', [QuotationController::class, 'reject']);
        Route::get('quotations/{id}/pdf', [QuotationController::class, 'downloadPDF']);
        Route::get('quotations/patient/{patientId}', [QuotationController::class, 'byPatient']);
    });

    // Historias clínicas (solo clínicos)
    Route::middleware('role:administrador,odontologo,implantologo,tecnico_dental,asistente')->group(function () {
        Route::apiResource('medical-records', MedicalRecordController::class);
        Route::post('medical-records/{id}/evolutions', [MedicalRecordController::class, 'addEvolution']);
        Route::get('medical-records/{id}/evolutions', [MedicalRecordController::class, 'getEvolutions']);
        // Slice 01 / T-01.3 (API-001): DELETE attachment. Declared BEFORE
        // apiResource so the fixed `attachments` segment is not swallowed
        // by `{medical_record}` model binding.
        Route::delete('medical-records/attachments/{attachment}', [MedicalRecordController::class, 'deleteAttachment']);
        Route::post('medical-records/attachments', [MedicalRecordController::class, 'uploadAttachment']);
        Route::get('medical-records/patient/{patientId}/stats', [MedicalRecordController::class, 'getStats']);
        Route::get('medical-records/patient/{patientId}/attachments', [MedicalRecordController::class, 'getAttachmentsByCategory']);
    });


    // Registros de especialidades (solo especialistas)
    Route::middleware('role:administrador,odontologo,implantologo,tecnico_dental')->group(function () {
        Route::apiResource('specialty-records', SpecialtyRecordController::class); // Generic resource, specific methods handle specialty type
        Route::get('specialty-records/patient/{patientId}/{specialty}', [SpecialtyRecordController::class, 'getByPatient']);
        Route::get('specialty-records/patient/{patientId}/all', [SpecialtyRecordController::class, 'getAllByPatient']);
        Route::get('specialty-records/patient/{patientId}/{specialty}/stats', [SpecialtyRecordController::class, 'getStats']);
    });

    // Sistema de caja (finanzas y admin)
    Route::middleware('role:administrador,finanzas')->group(function () {
        // CRUD admin solo administrador (B-CASH-3, Sprint 2).
        // El endpoint publico /payment-methods/active esta en el
        // grupo sin role (linea ~201), Pattern L del skill.
        Route::middleware('role:administrador')->apiResource('payment-methods', PaymentMethodController::class);
        // Transacciones y movimientos requieren sesion de caja abierta.
        // La apertura/cierre de sesion NO requiere sesion (la crea/termina),
        // asi que se aplica el middleware cash.session solo a los resources
        // que necesitan caja ya abierta.
        // Slice 01 / T-01.1: register `transactions/list` BEFORE apiResource so
        // the fixed segment is not swallowed by `{transaction}` model binding.
        Route::middleware('cash.session')->group(function () {
            Route::get('transactions/list', [TransactionController::class, 'list']);
            Route::apiResource('transactions', TransactionController::class);
        });
        Route::middleware('cash.session')->apiResource('cash-movements', CashMovementController::class);

        // Sesiones de caja
        // IMPORTANTE: las rutas con segmentos fijos (active, closure-report) deben
        // ir ANTES del apiResource para que no sean pisadas por
        // GET /cash-register-sessions/{cash_register_session} -> show($id).
        Route::get('cash-register-sessions/active', [CashRegisterController::class, 'current']);
        Route::get('cash-register-sessions/{id}/closure-report', [CashRegisterController::class, 'closureReport']);
        Route::apiResource('cash-register-sessions', CashRegisterController::class);
        Route::post('cash-register-sessions/{id}/open', [CashRegisterController::class, 'open']);
        Route::post('cash-register-sessions/{id}/close', [CashRegisterController::class, 'close']);

        // Reportes de caja
        Route::get('cash-reports/daily', [CashReportController::class, 'daily']);
        Route::get('cash-reports/period', [CashReportController::class, 'period']);

        // Pagos pendientes
        Route::get('pending-payments', [PendingPaymentsController::class, 'index']);
        Route::post('pending-payments/{id}/pay', [PendingPaymentsController::class, 'pay']);

        // Mercado Pago (Sprint 3, plan #11)
        Route::post('payments/mercadopago/preference', [MercadoPagoController::class, 'createPreference']);

        // Rutas alias para compatibilidad con frontend
        Route::get('cash-register/pending-payments', [PendingPaymentsController::class, 'index']);
        Route::get('cash-register/current', [CashRegisterController::class, 'current']);
        Route::get('cash-register/sessions', [CashRegisterController::class, 'index']);
        Route::post('cash-register/open', [CashRegisterController::class, 'open']);
        Route::post('cash-register/close', [CashRegisterController::class, 'close']);
        Route::get('cash-register/sessions/{id}/movements', [CashRegisterController::class, 'movements']);

        // Slice 01 / T-01.1: 5 cash-register endpoints previously 404.
        Route::get('cash-register/summary', [CashRegisterController::class, 'summary']);
        Route::get('cash-register/reports/period', [CashReportController::class, 'period']);
        Route::post('cash-register/reports/export/{format}', [CashReportController::class, 'export'])
            ->where('format', 'excel|pdf|csv');
        Route::get('cash-register/sessions/{id}', [CashRegisterController::class, 'show']);
        Route::get('cash-register/sessions/{id}/closure-report', [CashRegisterController::class, 'closureReport']);
    });

    // IA Asistiva (solo odontólogos y especialistas)
Route::middleware('role:administrador,odontologo,implantologo,tecnico_dental')->group(function () {
    // Nuevo endpoint para subir y analizar en un solo paso
    Route::post('ai-analysis/upload-and-analyze', [AiImageAnalysisController::class, 'analyzeUploadedImage']);

    // Rutas específicas primero (antes de las rutas con parámetros)
    Route::get('ai-analysis/stats', [AiImageAnalysisController::class, 'stats']);
    Route::get('ai-analysis/pending', [AiImageAnalysisController::class, 'pending']);
    Route::get('ai-analysis', [AiImageAnalysisController::class, 'index']);
    Route::get('ai-analysis/attachment/{attachmentId}', [AiImageAnalysisController::class, 'byAttachment']);
    Route::get('ai-analysis/patient/{patientId}', [AiImageAnalysisController::class, 'byPatient']);

    // Rutas con parámetros al final
    Route::post('ai-analysis/analyze/{attachmentId}', [AiImageAnalysisController::class, 'analyze']);
    Route::post('ai-analysis/{id}/review', [AiImageAnalysisController::class, 'review']);
    Route::get('ai-analysis/{id}', [AiImageAnalysisController::class, 'show']);
    Route::delete('ai-analysis/{id}', [AiImageAnalysisController::class, 'destroy']);
});
});
