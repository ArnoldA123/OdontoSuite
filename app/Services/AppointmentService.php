<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalChair;
use App\Models\AppointmentType;
use App\Models\AppointmentBlock;
use App\Models\WorkSchedule;
use App\Models\AuditLog;
use App\Models\WaitingList;
use App\Repositories\AppointmentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    protected AppointmentRepository $repository;

    public function __construct(AppointmentRepository $repository)
    {
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

            Log::info('AppointmentService::createAppointment - Data validated');

            // Parse scheduled_at
            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $duration = $data['duration_minutes'] ?? 60;

            Log::info('AppointmentService::createAppointment - Checking conflicts', [
                'user_id' => $data['user_id'],
                'dental_chair_id' => $data['dental_chair_id'],
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'duration' => $duration,
            ]);

            // Check for conflicts using repository
            $conflicts = $this->repository->findConflicts(
                $data['user_id'],
                $data['dental_chair_id'],
                $scheduledAt,
                $duration
            );

            Log::info('AppointmentService::createAppointment - Conflicts checked', [
                'conflicts_count' => $conflicts->count(),
            ]);

            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'scheduled_at' => ['Conflicto de horario detectado. Ya existe una cita en ese horario.'],
                ]);
            }

            Log::info('AppointmentService::createAppointment - Checking work schedule');

            // Check work schedule - Deshabilitado: profesionales trabajan 24/7
            // if (!$this->isWithinWorkSchedule($data['user_id'], $scheduledAt)) {
            //     throw ValidationException::withMessages([
            //         'scheduled_at' => ['El horario está fuera del horario de trabajo del profesional.'],
            //     ]);
            // }

            Log::info('AppointmentService::createAppointment - Checking blocks');

            // Check for blocks - Deshabilitado temporalmente para simplificar
            // if ($this->hasConflictingBlocks($data['user_id'], $data['dental_chair_id'], $scheduledAt, $duration)) {
            //     throw ValidationException::withMessages([
            //         'scheduled_at' => ['El horario está bloqueado (vacaciones, mantenimiento, etc.).'],
            //     ]);
            // }

            Log::info('AppointmentService::createAppointment - All validations passed, creating appointment');

            DB::beginTransaction();
            try {
                $endsAt = $scheduledAt->copy()->addMinutes($duration);
                
                $appointmentData = array_merge($data, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'ends_at' => $endsAt,
                ]);

                Log::info('AppointmentService::createAppointment - Appointment data prepared', [
                    'appointment_data' => $appointmentData,
                ]);

                $appointment = Appointment::create($appointmentData);

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

                DB::commit();
                
                Log::info('AppointmentService::createAppointment - Loading relationships');
                
                $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);
                
                Log::info('AppointmentService::createAppointment - Success', [
                    'appointment_id' => $appointment->id,
                ]);
                
                return $appointment;
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
        $oldValues = $appointment->toArray();

        // Check for conflicts if time is being changed
        if (isset($data['scheduled_at']) || isset($data['duration_minutes'])) {
            $scheduledAt = Carbon::parse($data['scheduled_at'] ?? $appointment->scheduled_at);
            $duration = $data['duration_minutes'] ?? $appointment->duration_minutes ?? 60;
            $userId = $data['user_id'] ?? $appointment->user_id;
            $chairId = $data['dental_chair_id'] ?? $appointment->dental_chair_id;

            $conflicts = $this->repository->findConflicts($userId, $chairId, $scheduledAt, $duration, $appointment->id);

            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'scheduled_at' => ['Conflicto de horario detectado. Ya existe una cita en ese horario.'],
                ]);
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

        DB::beginTransaction();
        try {
            // Update ends_at if scheduled_at or duration changed
            if (isset($data['scheduled_at']) || isset($data['duration_minutes'])) {
                $scheduledAt = Carbon::parse($data['scheduled_at'] ?? $appointment->scheduled_at);
                $duration = $data['duration_minutes'] ?? $appointment->duration_minutes ?? 60;
                $data['ends_at'] = $scheduledAt->copy()->addMinutes($duration);
            }

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

            DB::commit();
            return $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel an appointment and notify waiting list.
     */
    public function cancelAppointment(Appointment $appointment, string $reason = null): Appointment
    {
        $oldValues = $appointment->toArray();

        DB::beginTransaction();
        try {
            $appointment->update([
                'status' => 'cancelled',
                'notes' => $reason ? ($appointment->notes . "\n\nCancelación: " . $reason) : $appointment->notes,
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
    public function rescheduleAppointment(Appointment $appointment, Carbon $newDateTime, int $duration = null): Appointment
    {
        $oldValues = $appointment->toArray();
        $duration = $duration ?? $appointment->duration_minutes ?? 60;

        // Check for conflicts using repository
        $conflicts = $this->repository->findConflicts(
            $appointment->user_id,
            $appointment->dental_chair_id,
            $newDateTime,
            $duration,
            $appointment->id
        );

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Conflicto de horario detectado. Ya existe una cita en ese horario.'],
            ]);
        }

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

            DB::commit();
            return $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
                $query->where(function ($q) use ($dateTime, $endsAt) {
                    $q->whereBetween('starts_at', [$dateTime, $endsAt])
                      ->orWhereBetween('ends_at', [$dateTime, $endsAt])
                      ->orWhere(function ($q2) use ($dateTime, $endsAt) {
                          $q2->where('starts_at', '<=', $dateTime)
                             ->where('ends_at', '>=', $endsAt);
                      });
                });
            })
            ->first();

        return $block !== null;
        */
    }

    /**
     * Notify waiting list when an appointment is cancelled.
     */
    public function notifyWaitingList(Appointment $cancelledAppointment): void
    {
        $waitingListEntries = WaitingList::where('appointment_type_id', $cancelledAppointment->appointment_type_id)
            ->where('status', 'active')
            ->where(function ($query) use ($cancelledAppointment) {
                $query->whereNull('preferred_user_id')
                      ->orWhere('preferred_user_id', $cancelledAppointment->user_id);
            })
            ->where(function ($query) use ($cancelledAppointment) {
                $query->whereNull('preferred_date')
                      ->orWhere('preferred_date', $cancelledAppointment->scheduled_at->toDateString());
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
     * Get available time slots for a given date and user.
     */
    public function getAvailableTimeSlots(int $userId, Carbon $date, int $duration = 60): array
    {
        $slots = [];
        $workSchedule = WorkSchedule::where('user_id', $userId)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (!$workSchedule) {
            return $slots;
        }

        $startTime = $date->copy()->setTimeFromTimeString($workSchedule->start_time->format('H:i'));
        $endTime = $date->copy()->setTimeFromTimeString($workSchedule->end_time->format('H:i'));

        // Get existing appointments for the day
        $existingAppointments = Appointment::where('user_id', $userId)
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get();

        // Get blocks for the day
        $blocks = AppointmentBlock::where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date->toDateString())
                      ->where('end_date', '>=', $date->toDateString());
            })
            ->get();

        $currentTime = $startTime->copy();
        while ($currentTime->addMinutes($duration)->lte($endTime)) {
            $slotEnd = $currentTime->copy()->addMinutes($duration);

            // Check if slot conflicts with existing appointments
            $hasConflict = $existingAppointments->some(function ($appointment) use ($currentTime, $slotEnd) {
                return $currentTime->lt($appointment->ends_at) && $slotEnd->gt($appointment->scheduled_at);
            });

            // Check if slot conflicts with blocks
            $hasBlock = $blocks->some(function ($block) use ($currentTime, $slotEnd) {
                if ($block->is_all_day) {
                    return true;
                }

                $blockStart = $currentTime->copy()->setTimeFromTimeString($block->start_time->format('H:i'));
                $blockEnd = $currentTime->copy()->setTimeFromTimeString($block->end_time->format('H:i'));

                return $currentTime->lt($blockEnd) && $slotEnd->gt($blockStart);
            });

            if (!$hasConflict && !$hasBlock) {
                $slots[] = [
                    'start' => $currentTime->copy()->subMinutes($duration)->toISOString(),
                    'end' => $currentTime->toISOString(),
                    'formatted' => $currentTime->copy()->subMinutes($duration)->format('H:i') . ' - ' . $currentTime->format('H:i'),
                ];
            }

            $currentTime->addMinutes(15); // 15-minute intervals
        }

        return $slots;
    }

    /**
     * Validate appointment data.
     */
    private function validateAppointmentData(array $data, ?Appointment $appointment = null): void
    {
        $rules = [
            'patient_id' => 'required|exists:patients,id',
            'user_id' => 'required|exists:users,id',
            'dental_chair_id' => 'required|exists:dental_chairs,id',
            'appointment_type_id' => 'required|exists:appointment_types,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($appointment) {
            $rules['scheduled_at'] = 'sometimes|required|date';
            $rules['patient_id'] = 'sometimes|required|exists:patients,id';
            $rules['user_id'] = 'sometimes|required|exists:users,id';
            $rules['dental_chair_id'] = 'sometimes|required|exists:dental_chairs,id';
            $rules['appointment_type_id'] = 'sometimes|required|exists:appointment_types,id';
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
