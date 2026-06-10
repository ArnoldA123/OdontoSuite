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
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\ReminderTemplateController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\InterconsultationController;
use App\Http\Controllers\Api\TreatmentPlanController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\SpecialtyRecordController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\AppointmentBlockController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CashReportController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\PendingPaymentsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AiImageAnalysisController;
use App\Http\Controllers\Api\OdontogramController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\BillingController;

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
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])
    ->middleware('throttle.login'); // Rate limiting personalizado: 3/min, bloqueo 10min después de 5 errores
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register'])
    ->middleware('throttle:3,1'); // 3 intentos por minuto

// Grupo de rutas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle.login'); // Rate limiting personalizado
    Route::post('/register', [AuthController::class, 'register']);

    // Password recovery routes (public) con rate limiting
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
    // Broadcasting authentication (necesario para canales privados de WebSocket)
    // Esta ruta maneja la autenticación de canales privados usando las definiciones en routes/channels.php
    Route::post('/broadcasting/auth', function (Request $request) {
        try {
            // Verificar que el usuario esté autenticado
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Obtener parámetros de la solicitud
            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');
            
            if (!$channelName || !$socketId) {
                return response()->json(['message' => 'Invalid request'], 400);
            }

            // Remover el prefijo "private-" si existe (Laravel Echo lo agrega automáticamente)
            $cleanChannelName = preg_replace('/^private-/', '', $channelName);
            
            // Verificar autorización usando las definiciones en routes/channels.php
            $authorized = false;

            // Verificar según el tipo de canal definido en routes/channels.php
            if (preg_match('/^cash-session\.(\d+)$/', $cleanChannelName, $matches)) {
                $sessionId = $matches[1];
                // Usar la lógica definida en routes/channels.php: permitir si está autenticado
                $authorized = true;
            } elseif (preg_match('/^App\.Models\.User\.(\d+)$/', $cleanChannelName, $matches)) {
                $userId = $matches[1];
                // Solo el usuario puede acceder a su propio canal
                $authorized = (int) $user->id === (int) $userId;
            } elseif (preg_match('/^user\.(\d+)$/', $cleanChannelName, $matches)) {
                $userId = $matches[1];
                // Solo el usuario puede acceder a su propio canal
                $authorized = (int) $user->id === (int) $userId;
            } else {
                // Canal no reconocido
                \Log::warning('Broadcasting auth: Canal no reconocido', ['channel' => $cleanChannelName]);
                return response()->json(['message' => 'Channel not found'], 404);
            }

            if (!$authorized) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // Generar la firma de autenticación para Reverb/Pusher
            // Para canales PRIVADOS: string_to_sign = socket_id:channel_name
            // Para canales PRESENCE: string_to_sign = socket_id:channel_name:channel_data
            $secret = config('broadcasting.connections.reverb.secret');
            if (!$secret) {
                \Log::error('Broadcasting auth: REVERB_APP_SECRET no configurado');
                return response()->json(['message' => 'Server configuration error'], 500);
            }

            $key = config('broadcasting.connections.reverb.key');
            if (!$key) {
                \Log::error('Broadcasting auth: REVERB_APP_KEY no configurado');
                return response()->json(['message' => 'Server configuration error'], 500);
            }

            // Para canales privados, solo usar socket_id:channel_name
            // IMPORTANTE: Usar el channelName completo con el prefijo "private-" si existe
            $stringToSign = $socketId . ':' . $channelName;

            // Generar la firma HMAC
            $signature = hash_hmac('sha256', $stringToSign, $secret, false);

            // Formato de respuesta que espera Pusher/Reverb para canales privados
            // Solo necesita 'auth' con formato: key:signature
            $response = [
                'auth' => $key . ':' . $signature
            ];

            \Log::info('Broadcasting auth success', [
                'channel' => $channelName,
                'clean_channel' => $cleanChannelName,
                'user_id' => $user->id,
                'socket_id' => $socketId,
                'string_to_sign' => $stringToSign,
                'key' => $key,
                'signature_length' => strlen($signature)
            ]);

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Broadcasting auth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Internal server error'], 500);
        }
    });

    // Información del usuario autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);

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

    // Rutas de sucursales para el frontend (temporarily without auth for testing)
    Route::get('branches/active', [BranchController::class, 'index']);
    Route::get('branches', [BranchController::class, 'index']);

    // Dashboard (todos los roles autenticados)
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/today', [DashboardController::class, 'today']);
    Route::get('dashboard/appointments-today', [DashboardController::class, 'today']);
    Route::get('dashboard/upcoming', [DashboardController::class, 'upcoming']);

    // Usuarios (solo administrador)
    Route::middleware('role:administrador')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('roles', [RoleController::class, 'index']);
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
        Route::apiResource('work-schedules', WorkScheduleController::class);
        Route::apiResource('waiting-lists', WaitingListController::class);
        Route::apiResource('reminder-templates', ReminderTemplateController::class);
        Route::apiResource('audit-logs', AuditLogController::class);
        Route::get('audit-logs/patient/{patientId}', [AuditLogController::class, 'byPatient']);
        Route::get('audit-logs/user/{userId}', [AuditLogController::class, 'byUser']);
        Route::get('audit-logs/dental-chair/{chairId}', [AuditLogController::class, 'byDentalChair']);
        Route::get('audit-logs/appointment-type/{typeId}', [AuditLogController::class, 'byAppointmentType']);

        // Recordatorios
        Route::apiResource('reminders', ReminderController::class);
        Route::post('reminders/{id}/send', [ReminderController::class, 'send']);

        // Calendario
        Route::get('calendar/events', [CalendarController::class, 'getEvents']);
        Route::get('calendar/availability', [CalendarController::class, 'getAvailability']);

        // Bloques de citas
        Route::apiResource('appointment-blocks', AppointmentBlockController::class);

        // Odontogramas
        Route::apiResource('odontograms', OdontogramController::class);
        Route::get('odontograms/patient/{patientId}', [OdontogramController::class, 'index']);
        Route::get('odontograms/active', [OdontogramController::class, 'getActive']);
        Route::post('odontograms/{odontogram}/records', [OdontogramController::class, 'addRecord']);
        Route::put('odontograms/records/{record}', [OdontogramController::class, 'updateRecord']);
        Route::delete('odontograms/records/{record}', [OdontogramController::class, 'deleteRecord']);

        // Flujo de consulta (post check-in)
        Route::get('appointments/{appointment}/consultation-context', [ConsultationController::class, 'context']);
        Route::post('appointments/{appointment}/check-in', [ConsultationController::class, 'checkIn']);
        Route::post('appointments/{appointment}/complete', [ConsultationController::class, 'complete']);
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

    // Interconsultas (clínicos)
    Route::middleware('role:administrador,odontologo,implantologo,tecnico_dental')->group(function () {
        Route::apiResource('interconsultations', InterconsultationController::class);
        Route::post('interconsultations/{id}/respond', [InterconsultationController::class, 'respond']);
        Route::post('interconsultations/{id}/complete', [InterconsultationController::class, 'complete']);
        Route::get('my-interconsultations', [InterconsultationController::class, 'myInterconsultations']);
    });

    // Sistema de caja (finanzas y admin)
    Route::middleware('role:administrador,finanzas')->group(function () {
        Route::apiResource('payment-methods', PaymentMethodController::class);
        Route::apiResource('transactions', TransactionController::class);
        Route::apiResource('cash-movements', CashMovementController::class);

        // Sesiones de caja
        Route::apiResource('cash-register-sessions', CashRegisterController::class);
        Route::post('cash-register-sessions/{id}/open', [CashRegisterController::class, 'openSession']);
        Route::post('cash-register-sessions/{id}/close', [CashRegisterController::class, 'closeSession']);
        Route::get('cash-register-sessions/active', [CashRegisterController::class, 'getActiveSession']);
        Route::get('cash-register-sessions/{id}/closure-report', [CashRegisterController::class, 'closureReport']);

        // Reportes de caja
        Route::get('cash-reports/daily', [CashReportController::class, 'dailyReport']);
        Route::get('cash-reports/period', [CashReportController::class, 'periodReport']);

        // Pagos pendientes
        Route::get('pending-payments', [PendingPaymentsController::class, 'index']);
        Route::post('pending-payments/{id}/pay', [PendingPaymentsController::class, 'pay']);

        // Rutas alias para compatibilidad con frontend
        Route::get('cash-register/pending-payments', [PendingPaymentsController::class, 'index']);
        Route::get('cash-register/current', [CashRegisterController::class, 'current']);
        Route::get('cash-register/sessions', [CashRegisterController::class, 'index']);
        Route::post('cash-register/open', [CashRegisterController::class, 'open']);
        Route::post('cash-register/close', [CashRegisterController::class, 'close']);
        Route::get('cash-register/sessions/{id}/movements', [CashRegisterController::class, 'movements']);
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
