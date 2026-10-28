<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Events\AppointmentCreated;
use App\Events\AppointmentDeleted;
use App\Events\AppointmentUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Listeners\ClearDashboardCache;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Services\AppointmentService;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    // BF-024 (slice 11): standardise injection style with constructor
    // promotion (`private readonly`). Matches the pattern used in
    // ProcedureCatalogController, CashMovementController, etc.
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly ReminderService $reminderService,
    ) {}

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
            'updated_at',
        ])->with([
            'patient:id,first_name,last_name,document_number,email,phone',
            'user:id,name,specialty',
            'dentalChair:id,name,code',
            'appointmentType:id,name,default_duration_minutes,price,color',
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
            $query->where(function ($q) use ($startDate, $endDate) {
                // Citas que empiezan dentro del rango
                $q->whereBetween('scheduled_at', [$startDate, $endDate])
                  // O citas que terminan dentro del rango
                    ->orWhereBetween('ends_at', [$startDate, $endDate])
                  // O citas que abarcan todo el rango
                    ->orWhere(function ($subQ) use ($startDate, $endDate) {
                        $subQ->where('scheduled_at', '<=', $startDate)
                            ->where('ends_at', '>=', $endDate);
                    });
            });
        }

        // Filter by user (dentist)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // #395: la agenda acota por sillón como ya acota por profesional. Un
        // valor vacío no filtra: el cliente manda el parámetro incluso cuando
        // el filtro está limpio, y "sin filtro" no es "ningún sillón". El
        // alcance por sede de abajo sigue gobernando la población.
        if ($request->filled('dental_chair_id')) {
            $query->where('dental_chair_id', (int) $request->get('dental_chair_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by patient
        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->get('patient_id'));
        }

        // #283 S2: la sede autoriza, no sólo filtra. Un usuario restringido
        // queda acotado a su sede aunque no envíe el parámetro, y pedir otra
        // sede explícitamente responde 403.
        $branchFilter = BranchAccess::effectiveFilter(
            Auth::user(),
            $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
        );

        if ($branchFilter !== null) {
            $query->where('branch_id', $branchFilter);
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
                    'name' => $firstAppointment->patient->first_name.' '.$firstAppointment->patient->last_name,
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

        // Usar el servicio para crear la cita (valida reglas de datos,
        // participantes activos, conflictos y horarios).
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
            if (($slotConflict = $this->slotConflictResponse($e)) !== null) {
                Log::warning('Appointment slot taken while creating', [
                    'user_id' => Auth::id(),
                    'scheduled_at' => $validated['scheduled_at'] ?? null,
                ]);

                return $slotConflict;
            }

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
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al crear la cita: '.(config('app.debug') ? $e->getMessage() : 'Error interno del servidor'),
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
                'request_data' => $request->all(),
            ]);
        } catch (\Exception $e) {
            // Ignore logging errors
        }

        $validated = $request->validated();

        // Usar el servicio para actualizar la cita (valida reglas de datos,
        // participantes activos, conflictos y horarios).
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
            if (($slotConflict = $this->slotConflictResponse($e)) !== null) {
                Log::warning('Appointment slot taken while updating', [
                    'appointment_id' => $appointment->id,
                    'user_id' => Auth::id(),
                ]);

                return $slotConflict;
            }

            Log::channel('audit')->error('Error updating appointment', [
                'appointment_id' => $appointment->id,
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar la cita',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Move an existing appointment to a new date/time (calendar drag-and-drop).
     *
     * Conflict detection reuses AppointmentService::checkForConflicts (professional
     * + chair overlap, excluding the appointment being moved). On conflict the
     * response is 422 with a structured `conflict` payload for the UI toast.
     */
    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated();

        $scheduledAt = Carbon::parse($validated['scheduled_at'])->setTimezone(config('app.timezone'));
        $duration = (int) ($validated['duration_minutes'] ?? $appointment->duration_minutes ?? 60);

        $conflict = $this->appointmentService->checkForConflicts(
            $appointment->user_id,
            $appointment->dental_chair_id,
            $scheduledAt,
            $duration,
            $appointment->id
        );

        if ($conflict !== null) {
            return response()->json([
                'message' => $conflict['message'],
                'errors' => [
                    'scheduled_at' => [$conflict['message']],
                ],
                'conflict' => [
                    'type' => $conflict['type'],
                    'conflicting_appointment' => $this->conflictSummary($conflict['conflicting_appointment']),
                ],
            ], 422);
        }

        try {
            $appointment = $this->appointmentService->rescheduleAppointment($appointment, $scheduledAt, $duration);

            try {
                event(new AppointmentUpdated($appointment));
            } catch (\Exception $e) {
                Log::warning('Failed to emit AppointmentUpdated event', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }

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
                    'message' => 'Cita reprogramada exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if (($slotConflict = $this->slotConflictResponse($e)) !== null) {
                Log::warning('Appointment slot taken while rescheduling', [
                    'appointment_id' => $appointment->id,
                    'user_id' => Auth::id(),
                    'scheduled_at' => $scheduledAt->toDateTimeString(),
                ]);

                return $slotConflict;
            }

            Log::channel('audit')->error('Error rescheduling appointment', [
                'appointment_id' => $appointment->id,
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al reprogramar la cita',
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
                'trace' => $e->getTraceAsString(),
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
            'status' => 'required|in:scheduled,confirmed,arrived,in_consultation,completed,cancelled,no_show,rescheduled',
            'notes' => 'nullable|string|max:1000',
        ]);

        // F1 (#281): the custody statuses are not reachable through the generic
        // status endpoint. Each one has its own door, its own permission and its
        // own custody record; accepting them here let reception close the
        // clinical act and erased the difference between arrived and attending.
        $custodyDoors = [
            'arrived' => 'La llegada la registra recepción con POST /api/appointments/'.$appointment->id.'/check-in.',
            'in_consultation' => 'La atención la inicia el profesional con POST /api/appointments/'.$appointment->id.'/start-attention.',
            'completed' => 'El cierre del acto clínico se registra con POST /api/appointments/'.$appointment->id.'/complete.',
        ];

        if (isset($custodyDoors[$validated['status']])) {
            return response()->json(['message' => $custodyDoors[$validated['status']]], 422);
        }

        $oldValues = $appointment->toArray();
        $previousStatus = $appointment->status;

        DB::beginTransaction();

        try {
            $appointment->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $appointment->notes,
                'updated_by' => Auth::id(),
            ]);

            // Issue #154: this is the live cancellation path used by the UI,
            // so the pending reminders are cancelled in the same transaction;
            // reactivating the appointment puts them back on the pipeline.
            if ($validated['status'] === 'cancelled') {
                $this->reminderService->cancelReminders($appointment);
            } elseif ($previousStatus === 'cancelled') {
                $this->reminderService->rescheduleReminders($appointment);
            }

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

            if (($slotConflict = $this->slotConflictResponse($e)) !== null) {
                Log::warning('Appointment slot taken while changing status', [
                    'appointment_id' => $appointment->id,
                    'user_id' => Auth::id(),
                    'status' => $validated['status'] ?? null,
                ]);

                return $slotConflict;
            }

            Log::channel('audit')->error('Error updating appointment status', [
                'appointment_id' => $appointment->id,
                'user_id' => Auth::id(),
                'status' => $validated['status'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar el estado de la cita',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Translate a slot uniqueness violation into a conflict response.
     *
     * #392: the pre-check normally answers first, so this is the backstop for
     * the window it cannot cover — two requests clearing the pre-check at the
     * same time, and the reactivation of a cancelled appointment whose slot
     * was taken meanwhile. The engine rejected the write, so the door answers
     * 422 with the same `conflict` shape the pre-check publishes instead of
     * letting SQLSTATE 23000 become a 500.
     */
    private function slotConflictResponse(mixed $exception): ?JsonResponse
    {
        if (! $exception instanceof QueryException || ! $this->isSlotDuplicate($exception)) {
            return null;
        }

        $message = 'Ese horario ya está ocupado por otra cita activa. Actualiza la agenda y vuelve a intentarlo.';

        return response()->json([
            'message' => $message,
            'errors' => [
                'scheduled_at' => [$message],
            ],
            'conflict' => [
                'type' => 'slot_taken',
                'conflicting_appointment' => null,
            ],
        ], 422);
    }

    /**
     * A duplicate key on one of the two slot indexes, and nothing else: the
     * idempotency key is unique too and a collision there is not a slot
     * conflict.
     */
    private function isSlotDuplicate(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        $duplicateKey = (int) ($exception->errorInfo[1] ?? 0) === 1062
            || str_contains($message, 'Duplicate entry');

        if (! $duplicateKey) {
            return false;
        }

        return str_contains($message, 'unique_user_time_slot')
            || str_contains($message, 'unique_chair_time_slot');
    }

    /**
     * Summary of the appointment blocking a reschedule, shaped for UI toasts.
     */
    private function conflictSummary(Appointment $conflict): array
    {
        $conflict->load([
            'patient:id,first_name,last_name',
            'user:id,name',
            'dentalChair:id,name,code',
        ]);

        return [
            'id' => $conflict->id,
            'scheduled_at' => $conflict->scheduled_at?->toIso8601String(),
            'ends_at' => $conflict->ends_at?->toIso8601String(),
            'duration_minutes' => $conflict->duration_minutes,
            'status' => $conflict->status,
            'user_id' => $conflict->user_id,
            'dental_chair_id' => $conflict->dental_chair_id,
            'patient' => [
                'id' => $conflict->patient?->id,
                'full_name' => $conflict->patient?->full_name,
            ],
            'professional' => [
                'id' => $conflict->user?->id,
                'name' => $conflict->user?->name,
            ],
            'dental_chair' => [
                'id' => $conflict->dentalChair?->id,
                'name' => $conflict->dentalChair?->name,
                'code' => $conflict->dentalChair?->code,
            ],
        ];
    }
}
