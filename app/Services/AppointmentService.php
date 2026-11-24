<?php

namespace App\Services;

use App\Exceptions\AppointmentSlotConflictException;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\AuditLog;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use App\Models\WaitingList;
use App\Models\WorkSchedule;
use App\Repositories\AppointmentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    protected AppointmentRepository $repository;

    public function __construct(
        AppointmentRepository $repository,
        private readonly ReminderService $reminderService
    ) {
        $this->repository = $repository;
    }

    /**
     * Create a new appointment with conflict validation.
     */
    public function createAppointment(array $data): Appointment
    {
        try {
            Log::info('AppointmentService::createAppointment - Starting', [
                'data' => $data,
                'user_id' => Auth::id(),
            ]);

            $this->validateAppointmentData($data);
            $this->assertParticipantsAreActive($data);

            Log::info('AppointmentService::createAppointment - Data validated');

            // Parse scheduled_at y convertir a timezone de la aplicación
            $scheduledAt = Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'));
            $duration = $data['duration_minutes'] ?? 60;

            // citas-privacidad (T1): la cita hereda la sede de sus
            // participantes. Un alta de gerencia (recepción sin sede) dejaba
            // `branch_id` nulo y el profesional con sede no la veía en su
            // agenda. Un valor explícito sigue mandando.
            $data['branch_id'] = $this->resolveBranchId(
                (int) $data['user_id'],
                isset($data['dental_chair_id']) ? (int) $data['dental_chair_id'] : null,
                isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            );

            // Check work schedule - Deshabilitado: profesionales trabajan 24/7
            // if (!$this->isWithinWorkSchedule($data['user_id'], $scheduledAt)) {
            //     throw ValidationException::withMessages([
            //         'scheduled_at' => ['El horario está fuera del horario de trabajo del profesional.'],
            //     ]);
            // }

            // Check for blocks - Deshabilitado temporalmente para simplificar
            // if ($this->hasConflictingBlocks($data['user_id'], $data['dental_chair_id'], $scheduledAt, $duration)) {
            //     throw ValidationException::withMessages([
            //         'scheduled_at' => ['El horario está bloqueado (vacaciones, mantenimiento, etc.).'],
            //     ]);
            // }

            Log::info('AppointmentService::createAppointment - All validations passed, creating appointment');

            // The slot pre-check runs inside the transaction that writes: the
            // lock on the professional and the chair is what makes the read
            // authoritative, and it is released by the commit that publishes
            // the booking to the waiter.
            DB::beginTransaction();

            try {
                Log::info('AppointmentService::createAppointment - Checking conflicts', [
                    'user_id' => $data['user_id'],
                    'dental_chair_id' => $data['dental_chair_id'],
                    'scheduled_at' => $scheduledAt->toDateTimeString(),
                    'duration' => $duration,
                ]);

                $conflicts = $this->repository->findConflictsForWrite(
                    $data['user_id'],
                    $data['dental_chair_id'],
                    $scheduledAt,
                    $duration
                );

                Log::info('AppointmentService::createAppointment - Conflicts checked', [
                    'conflicts_count' => $conflicts->count(),
                ]);

                if ($conflicts->isNotEmpty()) {
                    throw AppointmentSlotConflictException::forConflicts($conflicts, (int) $data['user_id']);
                }

                $endsAt = $scheduledAt->copy()->addMinutes($duration);

                // #394: el motivo no es campo de asignación masiva; se escribe
                // sobre el modelo y sólo cuando la cita nace cancelada.
                $cancellationReason = ($data['status'] ?? null) === 'cancelled'
                    ? ($data['cancellation_reason'] ?? null)
                    : null;
                unset($data['cancellation_reason']);

                $appointmentData = array_merge($data, [
                    'scheduled_at' => $scheduledAt,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'ends_at' => $endsAt,
                ]);

                Log::info('AppointmentService::createAppointment - Appointment data prepared', [
                    'appointment_data' => $appointmentData,
                ]);

                $appointment = Appointment::create($appointmentData);

                if ($cancellationReason !== null) {
                    $appointment->cancellation_reason = $cancellationReason;
                    $appointment->save();
                }

                Log::info('AppointmentService::createAppointment - Appointment created', [
                    'appointment_id' => $appointment->id,
                ]);

                // Log audit
                try {
                    AuditLog::log(
                        Auth::user(),
                        'appointment_created',
                        $appointment,
                        [],
                        $appointment->toArray()
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to log audit for appointment creation', [
                        'appointment_id' => $appointment->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Issue #154: the confirmation pipeline starts with the
                // reminder rows, so they commit or roll back with the
                // appointment instead of being scheduled after the fact.
                $this->reminderService->scheduleReminders($appointment);

                DB::commit();

                Log::info('AppointmentService::createAppointment - Loading relationships');

                $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);

                Log::info('AppointmentService::createAppointment - Success', [
                    'appointment_id' => $appointment->id,
                ]);

                return $appointment;
            } catch (ValidationException $e) {
                // A slot conflict is a business answer, not a server error:
                // release the lock and let the controller render the 422.
                DB::rollBack();

                throw $e;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('AppointmentService::createAppointment - Error in transaction', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('AppointmentService::createAppointment - Unexpected error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an appointment with conflict validation.
     */
    public function updateAppointment(Appointment $appointment, array $data): Appointment
    {
        $this->validateAppointmentData($data, $appointment);
        $this->assertParticipantsAreActive($data, $appointment);
        $data = $this->applyBranchAttribution($appointment, $data);
        $oldValues = $appointment->toArray();
        // Snapshot the reminder-relevant state before the update mutates the
        // model: the loaded relations stay untouched by `update()`.
        $previousScheduledAt = $appointment->scheduled_at?->copy();
        $previousTypeId = (int) $appointment->appointment_type_id;
        $previousStatus = $appointment->status;

        // #517: the slot is defined by the four fields below, so moving only
        // the professional or the chair has to clear the same check as moving
        // the clock. Comparing against the stored values keeps a no-op field
        // in the payload from acquiring the lock.
        $slotChanged = isset($data['scheduled_at'])
            || isset($data['duration_minutes'])
            || (array_key_exists('user_id', $data) && (int) $data['user_id'] !== (int) $appointment->user_id)
            || (array_key_exists('dental_chair_id', $data)
                && (int) ($data['dental_chair_id'] ?? 0) !== (int) ($appointment->dental_chair_id ?? 0));

        DB::beginTransaction();

        try {
            // Check for conflicts when the slot changes. The read runs under
            // the lock of the professional and the chair, inside the
            // transaction that writes, so two concurrent edits cannot both
            // clear it.
            if ($slotChanged) {
                $scheduledAt = Carbon::parse($data['scheduled_at'] ?? $appointment->scheduled_at)->setTimezone(config('app.timezone'));
                $duration = $data['duration_minutes'] ?? $appointment->duration_minutes ?? 60;
                $userId = $data['user_id'] ?? $appointment->user_id;
                $chairId = $data['dental_chair_id'] ?? $appointment->dental_chair_id;

                $conflicts = $this->repository->findConflictsForWrite($userId, $chairId, $scheduledAt, $duration, $appointment->id);

                if ($conflicts->isNotEmpty()) {
                    throw AppointmentSlotConflictException::forConflicts($conflicts, (int) $userId);
                }

                // Check work schedule - Deshabilitado: profesionales trabajan 24/7
                // if (!$this->isWithinWorkSchedule($userId, $scheduledAt)) {
                //     throw ValidationException::withMessages([
                //         'scheduled_at' => ['El horario está fuera del horario de trabajo del profesional.'],
                //     ]);
                // }

                // Check for blocks - Deshabilitado temporalmente para simplificar
                // if ($this->hasConflictingBlocks($userId, $chairId, $scheduledAt, $duration)) {
                //     throw ValidationException::withMessages([
                //         'scheduled_at' => ['El horario está bloqueado (vacaciones, mantenimiento, etc.).'],
                //     ]);
                // }
            }

            // Update ends_at if scheduled_at or duration changed
            if (isset($data['scheduled_at']) || isset($data['duration_minutes'])) {
                $scheduledAt = Carbon::parse($data['scheduled_at'] ?? $appointment->scheduled_at)->setTimezone(config('app.timezone'));
                $duration = $data['duration_minutes'] ?? $appointment->duration_minutes ?? 60;

                if (isset($data['scheduled_at'])) {
                    $data['scheduled_at'] = $scheduledAt;
                }

                $data['ends_at'] = $scheduledAt->copy()->addMinutes($duration);
            }

            // #394: el motivo de la cancelación vive en su propia columna y se
            // limpia cuando la cita deja de estar cancelada. Se escribe sobre el
            // modelo porque no es campo de asignación masiva: sólo esta regla lo
            // firma.
            $resultingStatus = $data['status'] ?? $appointment->status;
            $appointment->cancellation_reason = $resultingStatus === 'cancelled'
                ? ($data['cancellation_reason'] ?? $appointment->cancellation_reason)
                : null;
            unset($data['cancellation_reason']);

            $data['updated_by'] = Auth::id();
            $appointment->update($data);

            // Log audit
            AuditLog::log(
                Auth::user(),
                'appointment_updated',
                $appointment,
                $oldValues,
                $appointment->fresh()->toArray()
            );

            $this->syncRemindersAfterUpdate(
                $appointment,
                $previousScheduledAt,
                $previousTypeId,
                $previousStatus
            );

            DB::commit();

            return $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Cancel an appointment and notify waiting list.
     *
     * #394: the motive lives in the appointment's own column, like every other
     * cancellation door. It is not appended to `notes`, which any later edit
     * overwrites.
     */
    public function cancelAppointment(Appointment $appointment, ?string $reason = null): Appointment
    {
        $oldValues = $appointment->toArray();

        DB::beginTransaction();

        try {
            $appointment->cancellation_reason = $reason;
            $appointment->update([
                'status' => 'cancelled',
                'updated_by' => Auth::id(),
            ]);

            // Log audit
            AuditLog::log(
                Auth::user(),
                'appointment_cancelled',
                $appointment,
                $oldValues,
                $appointment->fresh()->toArray(),
                ['reason' => $reason]
            );

            // Notify waiting list
            $this->notifyWaitingList($appointment);

            DB::commit();

            return $appointment;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Reschedule an appointment.
     */
    public function rescheduleAppointment(Appointment $appointment, Carbon $newDateTime, ?int $duration = null): Appointment
    {
        $oldValues = $appointment->toArray();
        $duration = $duration ?? $appointment->duration_minutes ?? 60;

        // Check work schedule - Deshabilitado: profesionales trabajan 24/7
        // if (!$this->isWithinWorkSchedule($appointment->user_id, $newDateTime)) {
        //     throw ValidationException::withMessages([
        //         'scheduled_at' => ['El horario está fuera del horario de trabajo del profesional.'],
        //     ]);
        // }

        // Check for blocks - Deshabilitado temporalmente para simplificar
        // if ($this->hasConflictingBlocks($appointment->user_id, $appointment->dental_chair_id, $newDateTime, $duration)) {
        //     throw ValidationException::withMessages([
        //         'scheduled_at' => ['El horario está bloqueado (vacaciones, mantenimiento, etc.).'],
        //     ]);
        // }

        DB::beginTransaction();

        try {
            // The move lands on the lock like the other two doors: the target
            // slot is read under the professional's and the chair's lock, so a
            // second request cannot pass the same read.
            $conflicts = $this->repository->findConflictsForWrite(
                $appointment->user_id,
                $appointment->dental_chair_id,
                $newDateTime,
                $duration,
                $appointment->id
            );

            if ($conflicts->isNotEmpty()) {
                throw AppointmentSlotConflictException::forConflicts($conflicts, (int) $appointment->user_id);
            }

            $appointment->update([
                'scheduled_at' => $newDateTime,
                'ends_at' => $newDateTime->copy()->addMinutes($duration),
                'duration_minutes' => $duration,
                'updated_by' => Auth::id(),
            ]);

            // Log audit
            AuditLog::log(
                Auth::user(),
                'appointment_rescheduled',
                $appointment,
                $oldValues,
                $appointment->fresh()->toArray(),
                ['new_datetime' => $newDateTime->toISOString()]
            );

            // Issue #154: cancel the pending rows and recreate them for the
            // new time, inside the same transaction.
            $this->reminderService->rescheduleReminders($appointment);

            DB::commit();

            return $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * The sede an appointment belongs to, from the most specific fact to the
     * most general one (citas-privacidad, T1, contrato de sede):
     *
     *   explicit `branch_id` -> `users.branch_id` of the professional
     *   -> `dental_chairs.branch_id` -> the sede the row already had
     *
     * The last step is what makes an explicit `null` harmless on update: it
     * never erases a sede, it only lets the cascade speak. Returns null when
     * nobody declares one; `ScopesToBranch` then stamps the actor's sede, as
     * before.
     */
    public function resolveBranchId(
        int $professionalId,
        ?int $dentalChairId = null,
        ?int $explicitBranchId = null,
        ?Appointment $existing = null,
    ): ?int {
        if ($explicitBranchId !== null) {
            return $explicitBranchId;
        }

        $professionalBranch = User::query()->whereKey($professionalId)->value('branch_id');

        if ($professionalBranch !== null) {
            return (int) $professionalBranch;
        }

        if ($dentalChairId !== null) {
            $chairBranch = DentalChair::query()->whereKey($dentalChairId)->value('branch_id');

            if ($chairBranch !== null) {
                return (int) $chairBranch;
            }
        }

        return $existing?->branch_id !== null ? (int) $existing->branch_id : null;
    }

    /**
     * On update the sede is only recalculated when the professional or the
     * chair changes, or when the payload carries its own `branch_id`. A
     * payload `branch_id: null` is dropped instead of written, so it never
     * clears the sede of a stored appointment.
     */
    private function applyBranchAttribution(Appointment $appointment, array $data): array
    {
        $professionalChanged = array_key_exists('user_id', $data)
            && (int) $data['user_id'] !== (int) $appointment->user_id;
        $chairChanged = array_key_exists('dental_chair_id', $data)
            && (int) ($data['dental_chair_id'] ?? 0) !== (int) ($appointment->dental_chair_id ?? 0);
        $explicitBranchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        if (array_key_exists('branch_id', $data) && $explicitBranchId === null) {
            unset($data['branch_id']);
        }

        if ($explicitBranchId === null && ! $professionalChanged && ! $chairChanged) {
            return $data;
        }

        $chairId = array_key_exists('dental_chair_id', $data)
            ? ($data['dental_chair_id'] !== null ? (int) $data['dental_chair_id'] : null)
            : ($appointment->dental_chair_id !== null ? (int) $appointment->dental_chair_id : null);

        $data['branch_id'] = $this->resolveBranchId(
            (int) ($data['user_id'] ?? $appointment->user_id),
            $chairId,
            $explicitBranchId,
            $appointment,
        );

        return $data;
    }

    /**
     * Check for scheduling conflicts (delegates to repository).
     * Kept for backward compatibility.
     */
    public function checkForConflicts(
        int $userId,
        int $chairId,
        Carbon $scheduledAt,
        int $duration,
        ?int $excludeAppointmentId = null
    ): ?array {
        $conflicts = $this->repository->findConflicts($userId, $chairId, $scheduledAt, $duration, $excludeAppointmentId);

        if ($conflicts->isEmpty()) {
            return null;
        }

        $firstConflict = $conflicts->first();

        // Determine conflict type
        if ($firstConflict->user_id === $userId) {
            return [
                'type' => 'user_conflict',
                'conflicting_appointment' => $firstConflict,
                'message' => 'El profesional ya tiene una cita en ese horario',
            ];
        }

        return [
            'type' => 'chair_conflict',
            'conflicting_appointment' => $firstConflict,
            'message' => 'La silla dental ya está ocupada en ese horario',
        ];
    }

    /**
     * Check if time is within user's work schedule.
     * Si el profesional no tiene horario configurado, se permite (horario flexible).
     */
    public function isWithinWorkSchedule(int $userId, Carbon $dateTime): bool
    {
        $dayOfWeek = $dateTime->dayOfWeek;
        $time = $dateTime->format('H:i');

        // Buscar horarios de trabajo activos para este día
        $workSchedules = WorkSchedule::where('user_id', $userId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        // Si no hay horarios configurados, permitir la cita (horario flexible)
        if ($workSchedules->isEmpty()) {
            Log::info('No work schedule found for user, allowing appointment (flexible schedule)', [
                'user_id' => $userId,
                'date' => $dateTime->toDateString(),
                'day_of_week' => $dayOfWeek,
                'time' => $time,
            ]);

            return true;
        }

        // Verificar si el horario está dentro de algún rango de trabajo
        foreach ($workSchedules as $workSchedule) {
            $startTime = $workSchedule->start_time instanceof \Carbon\Carbon
                ? $workSchedule->start_time->format('H:i')
                : \Carbon\Carbon::parse($workSchedule->start_time)->format('H:i');

            $endTime = $workSchedule->end_time instanceof \Carbon\Carbon
                ? $workSchedule->end_time->format('H:i')
                : \Carbon\Carbon::parse($workSchedule->end_time)->format('H:i');

            if ($time >= $startTime && $time <= $endTime) {
                Log::info('Appointment time is within work schedule', [
                    'user_id' => $userId,
                    'date' => $dateTime->toDateString(),
                    'time' => $time,
                    'work_schedule_start' => $startTime,
                    'work_schedule_end' => $endTime,
                ]);

                return true;
            }
        }

        \Log::warning('Appointment time is outside work schedule', [
            'user_id' => $userId,
            'date' => $dateTime->toDateString(),
            'day_of_week' => $dayOfWeek,
            'time' => $time,
            'work_schedules' => $workSchedules->map(function ($ws) {
                return [
                    'start' => $ws->start_time instanceof \Carbon\Carbon
                        ? $ws->start_time->format('H:i')
                        : \Carbon\Carbon::parse($ws->start_time)->format('H:i'),
                    'end' => $ws->end_time instanceof \Carbon\Carbon
                        ? $ws->end_time->format('H:i')
                        : \Carbon\Carbon::parse($ws->end_time)->format('H:i'),
                ];
            })->toArray(),
        ]);

        return false;
    }

    /**
     * Check if there are conflicting blocks.
     * Deshabilitado: permite crear citas sin verificar bloques.
     */
    public function hasConflictingBlocks(int $userId, int $chairId, Carbon $dateTime, int $duration): bool
    {
        // Deshabilitado temporalmente - permite crear citas sin restricciones de bloques
        return false;

        // Código original comentado para referencia futura
        /*
        $endsAt = $dateTime->copy()->addMinutes($duration);

        $block = AppointmentBlock::where('is_active', true)
            ->where(function ($query) use ($userId, $chairId) {
                $query->where('user_id', $userId)
                      ->orWhere('dental_chair_id', $chairId);
            })
            ->where(function ($query) use ($dateTime, $endsAt) {
                // Same half-open `[inicio, fin)` rule as the slot pre-check:
                // the closing edge of a block does not collide.
                $query->where('starts_at', '<', $endsAt)
                      ->where('ends_at', '>', $dateTime);
            })
            ->first();

        return $block !== null;
        */
    }

    /**
     * Notify waiting list when an appointment is cancelled.
     *
     * The preferred window is expressed in whole days: `preferred_start_date`
     * covers its day from the first minute and `preferred_end_date` covers its
     * day to the last one, whether the stored time is midnight or any other
     * instant of that day. Both bounds are compared as plain column ranges
     * against the day boundaries, never through a date function, so the rule
     * stays index-friendly (issues #169, review R3-DateIndexWrap).
     */
    public function notifyWaitingList(Appointment $cancelledAppointment): void
    {
        $slot = $cancelledAppointment->scheduled_at;
        $slotDayStart = $slot->copy()->startOfDay();
        $slotDayEnd = $slotDayStart->copy()->addDay();

        $waitingListEntries = WaitingList::where('appointment_type_id', $cancelledAppointment->appointment_type_id)
            ->where('status', 'active')
            ->where(function ($query) use ($cancelledAppointment) {
                $query->whereNull('preferred_user_id')
                    ->orWhere('preferred_user_id', $cancelledAppointment->user_id);
            })
            ->where(function ($query) use ($slotDayEnd) {
                $query->whereNull('preferred_start_date')
                    ->orWhere('preferred_start_date', '<', $slotDayEnd);
            })
            ->where(function ($query) use ($slotDayStart) {
                $query->whereNull('preferred_end_date')
                    ->orWhere('preferred_end_date', '>=', $slotDayStart);
            })
            ->orderBy('priority')
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($waitingListEntries as $entry) {
            $entry->markAsNotified();
            // Here you would typically send an email or SMS notification
        }
    }

    /**
     * Keep the reminder rows in sync after an appointment update: a cancelled
     * appointment keeps no pending reminder, and a moved, retyped or
     * reactivated one gets its windows rebuilt for the current time.
     */
    private function syncRemindersAfterUpdate(
        Appointment $appointment,
        ?Carbon $previousScheduledAt,
        int $previousTypeId,
        ?string $previousStatus
    ): void {
        if ($appointment->status === 'cancelled') {
            if ($previousStatus !== 'cancelled') {
                $this->reminderService->cancelReminders($appointment);
            }

            return;
        }

        $timeChanged = $previousScheduledAt === null
            || ! $appointment->scheduled_at->equalTo($previousScheduledAt);
        $typeChanged = (int) $appointment->appointment_type_id !== $previousTypeId;

        if ($previousStatus === 'cancelled' || $timeChanged || $typeChanged) {
            $this->reminderService->rescheduleReminders($appointment);
        }
    }

    /**
     * Validate appointment data.
     *
     * The rule set lives in the FormRequests (single source of truth); this
     * method only reuses it so direct service callers get the same contract.
     */
    private function validateAppointmentData(array $data, ?Appointment $appointment = null): void
    {
        $rules = $appointment
            ? (new UpdateAppointmentRequest)->rules()
            : (new StoreAppointmentRequest)->rules();

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * Ensure the patient and professional referenced by the appointment are
     * active. Shared by the create and update paths.
     *
     * On update, only participants that actually change are checked, matching
     * the previous controller behaviour.
     */
    private function assertParticipantsAreActive(array $data, ?Appointment $appointment = null): void
    {
        if (array_key_exists('patient_id', $data)
            && ($appointment === null || $data['patient_id'] != $appointment->patient_id)) {
            $patient = Patient::find($data['patient_id']);
            if ($patient && ! $patient->is_active) {
                throw ValidationException::withMessages([
                    'patient_id' => ['El paciente seleccionado está inactivo.'],
                ]);
            }
        }

        if (array_key_exists('user_id', $data)
            && ($appointment === null || $data['user_id'] != $appointment->user_id)) {
            $user = User::find($data['user_id']);
            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'user_id' => ['El profesional seleccionado está inactivo.'],
                ]);
            }
        }
    }
}
