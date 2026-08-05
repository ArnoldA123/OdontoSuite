<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\AppointmentDeleted;
use App\Listeners\ClearDashboardCache;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalChair;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Services\AppointmentService;
use App\Http\Resources\AppointmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    // BF-024 (slice 11): standardise injection style with constructor
    // promotion (`private readonly`). Matches the pattern used in
    // ProcedureCatalogController, CashMovementController, etc.
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Log::info('AppointmentController::index - Request received', [
            'params' => $request->all(),
            'has_start_date' => $request->has('start_date'),
            'has_end_date' => $request->has('end_date'),
        ]);

        $query = Appointment::select([
            'id',
            'patient_id',
            'user_id',
            'dental_chair_id',
            'appointment_type_id',
            'scheduled_at',
            'ends_at',
            'duration_minutes',
            'status',
            'notes',
            'treatment_notes',
            'idempotency_key',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at'
        ])->with([
            'patient:id,first_name,last_name,document_number,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color'
        ]);

        $totalBeforeFilter = $query->count();
        Log::info('AppointmentController::index - Total appointments before filters', ['count' => $totalBeforeFilter]);

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->get('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->get('end_date'))->endOfDay();
            Log::info('AppointmentController::index - Applying date filter', [
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => $endDate->toDateTimeString(),
            ]);
            $query->where(function($q) use ($startDate, $endDate) {
                // Citas que empiezan dentro del rango
                $q->whereBetween('scheduled_at', [$startDate, $endDate])
                  // O citas que terminan dentro del rango
                  ->orWhereBetween('ends_at', [$startDate, $endDate])
                  // O citas que abarcan todo el rango
                  ->orWhere(function($subQ) use ($startDate, $endDate) {
                      $subQ->where('scheduled_at', '<=', $startDate)
                           ->where('ends_at', '>=', $endDate);
                  });
            });
        }

        // Filter by user (dentist)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by patient
        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->get('patient_id'));
        }

        // Multi-tenant: filtrar por branch_id si se envía
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $perPage = $request->get('per_page', 20);
        // Si es una solicitud del calendario (con start_date y end_date), permitir más items
        if ($request->has('start_date') && $request->has('end_date')) {
            $perPage = min($perPage, 1000);
        } else {
            $perPage = min($perPage, 100);
        }

        $appointments = $query->orderBy('scheduled_at')->paginate($perPage);

        $items = $appointments->items();
        Log::info('AppointmentController::index - Appointments found', [
            'total' => $appointments->total(),
            'count' => count($items),
            'per_page' => $appointments->perPage(),
            'current_page' => $appointments->currentPage(),
        ]);

        if (count($items) > 0) {
            $firstAppointment = $items[0];
            Log::info('AppointmentController::index - First appointment sample', [
                'id' => $firstAppointment->id,
                'scheduled_at' => $firstAppointment->scheduled_at?->toDateTimeString(),
                'ends_at' => $firstAppointment->ends_at?->toDateTimeString(),
                'patient' => $firstAppointment->patient ? [
                    'id' => $firstAppointment->patient->id,
                    'name' => $firstAppointment->patient->first_name . ' ' . $firstAppointment->patient->last_name,
                ] : null,
                'user' => $firstAppointment->user ? [
                    'id' => $firstAppointment->user->id,
                    'name' => $firstAppointment->user->name,
                ] : null,
                'appointment_type' => $firstAppointment->appointmentType ? [
                    'id' => $firstAppointment->appointmentType->id,
                    'name' => $firstAppointment->appointmentType->name,
                ] : null,
            ]);
        } else {
            Log::warning('AppointmentController::index - No appointments found with current filters');
        }

        return response()->json([
            'data' => AppointmentResource::collection($items),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Validar que el paciente esté activo
        $patient = Patient::findOrFail($validated['patient_id']);
        if (!$patient->is_active) {
            return response()->json([
                'message' => 'No se puede crear una cita para un paciente inactivo.',
                'errors' => [
                    'patient_id' => ['El paciente seleccionado está inactivo.'],
                ],
            ], 422);
        }

        // Validar que el usuario (profesional) esté activo
        $user = User::findOrFail($validated['user_id']);
        if (!$user->is_active) {
            return response()->json([
                'message' => 'No se puede crear una cita con un profesional inactivo.',
                'errors' => [
                    'user_id' => ['El profesional seleccionado está inactivo.'],
                ],
            ], 422);
        }

        // Usar el servicio para crear la cita (incluye validaciones de conflictos, horarios, etc.)
        try {
            Log::info('Creating appointment', [
                'user_id' => Auth::id(),
                'validated_data' => $validated,
            ]);
            
            $appointment = $this->appointmentService->createAppointment($validated);

            Log::info('Appointment created successfully', [
                'appointment_id' => $appointment->id,
            ]);

            // Emitir evento de WebSocket
            try {
                event(new AppointmentCreated($appointment));
            } catch (\Exception $e) {
                Log::warning('Failed to emit AppointmentCreated event', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Limpiar cache del dashboard
            try {
                ClearDashboardCache::handle();
            } catch (\Exception $e) {
                Log::warning('Failed to clear dashboard cache', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'data' => new AppointmentResource($appointment),
                'meta' => [
                    'message' => 'Cita creada exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error creating appointment', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating appointment', [
                'user_id' => Auth::id(),
                'patient_id' => $validated['patient_id'] ?? null,
                'user_id_appointment' => $validated['user_id'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al crear la cita: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor'),
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load([
            'patient',
            'user',
            'dentalChair',
            'appointmentType',
            'createdBy',
            'updatedBy',
            'recurrence',
            'reminderSchedules',
        ]);

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(\App\Http\Requests\UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        try {
            Log::info('Update appointment request', [
                'appointment_id' => $appointment->id,
                'request_data' => $request->all()
            ]);
        } catch (\Exception $e) {
            // Ignore logging errors
        }

        $validated = $request->validate([
            'patient_id' => 'sometimes|required|exists:patients,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'dental_chair_id' => 'sometimes|required|exists:dental_chairs,id',
            'appointment_type_id' => 'sometimes|required|exists:appointment_types,id',
            'scheduled_at' => 'sometimes|required|date',
            'duration_minutes' => 'sometimes|required|integer|min:15|max:480',
            'status' => 'sometimes|required|in:scheduled,confirmed,in_consultation,completed,cancelled,no_show,rescheduled',
            'notes' => 'nullable|string|max:1000',
            'treatment_notes' => 'nullable|string|max:2000',
        ]);

        // Validar que el paciente esté activo si se está cambiando
        if (isset($validated['patient_id']) && $validated['patient_id'] != $appointment->patient_id) {
            $patient = Patient::findOrFail($validated['patient_id']);
            if (!$patient->is_active) {
                return response()->json([
                    'message' => 'No se puede asignar una cita a un paciente inactivo.',
                    'errors' => [
                        'patient_id' => ['El paciente seleccionado está inactivo.'],
                    ],
                ], 422);
            }
        }

        // Validar que el usuario (profesional) esté activo si se está cambiando
        if (isset($validated['user_id']) && $validated['user_id'] != $appointment->user_id) {
            $user = User::findOrFail($validated['user_id']);
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'No se puede asignar una cita a un profesional inactivo.',
                    'errors' => [
                        'user_id' => ['El profesional seleccionado está inactivo.'],
                    ],
                ], 422);
            }
        }

        // Usar el servicio para actualizar la cita (incluye validaciones de conflictos, horarios, etc.)
        try {
            $appointment = $this->appointmentService->updateAppointment($appointment, $validated);

            // Emitir evento de WebSocket
            event(new AppointmentUpdated($appointment));
            
            // Limpiar cache del dashboard
            ClearDashboardCache::handle();

            return response()->json([
                'data' => new AppointmentResource($appointment),
                'meta' => [
                    'message' => 'Cita actualizada exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::channel('audit')->error('Error updating appointment', [
                'appointment_id' => $appointment->id,
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al actualizar la cita',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        DB::beginTransaction();
        try {
            $oldValues = $appointment->toArray();
            $appointmentId = $appointment->id;
            $appointment->delete();

            DB::commit();

            // Emitir evento de WebSocket (el listener se encargará de la auditoría)
            event(new AppointmentDeleted($appointmentId, $oldValues, $appointment));
            
            // Limpiar cache del dashboard
            ClearDashboardCache::handle();

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'Cita eliminada exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('audit')->error('Error deleting appointment', [
                'appointment_id' => $appointment->id,
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al eliminar la cita',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }


    /**
     * Update appointment status.
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,confirmed,in_consultation,completed,cancelled,no_show,rescheduled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldValues = $appointment->toArray();

        DB::beginTransaction();
        try {
            $appointment->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $appointment->notes,
                'updated_by' => Auth::id(),
            ]);

            // Log audit
            AuditLog::log(
                Auth::user(),
                'appointment_status_changed',
                $appointment,
                $oldValues,
                $appointment->fresh()->toArray(),
                ['status_change' => $validated['status']]
            );

            DB::commit();

            return response()->json([
                'data' => $appointment,
                'meta' => [
                    'message' => 'Estado de la cita actualizado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('audit')->error('Error updating appointment status', [
                'appointment_id' => $appointment->id,
                'user_id' => Auth::id(),
                'status' => $validated['status'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al actualizar el estado de la cita',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
