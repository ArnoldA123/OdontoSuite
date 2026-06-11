<?php

namespace App\Services;

use App\Models\WaitingList;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\AppointmentType;
use App\Models\User;
use App\Events\WaitingListCreated;
use App\Events\WaitingListFilled;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WaitingListService
{
    /**
     * Add patient to waiting list.
     *
     * Sprint 0 fix (NF-6): $createdBy ahora se recibe como parámetro desde el
     * controller (que pasa auth()->id()) en vez de hardcodear 1.
     */
    public function addToWaitingList(array $data, ?int $createdBy = null): WaitingList
    {
        $this->validateWaitingListData($data);

        // Check if patient is already on waiting list for same type and date
        $existingEntry = WaitingList::where('patient_id', $data['patient_id'])
            ->where('appointment_type_id', $data['appointment_type_id'])
            ->where('preferred_date', $data['preferred_date'] ?? null)
            ->where('status', 'active')
            ->first();

        if ($existingEntry) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente ya está en la lista de espera para este tipo de cita y fecha.'],
            ]);
        }

        // Set default priority if not provided
        if (!isset($data['priority'])) {
            $data['priority'] = $this->getNextPriority($data['appointment_type_id'], $data['preferred_date'] ?? null);
        }

        // Set expiration date (default 30 days)
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = now()->addDays(30);
        }

        $data['created_by'] = $createdBy ?? \Illuminate\Support\Facades\Auth::id();
        $data['updated_by'] = $data['created_by'];

        $waitingList = WaitingList::create($data);
        $waitingList->load('patient', 'appointmentType', 'preferredUser');

        // Emitir evento de WebSocket
        event(new WaitingListCreated($waitingList));

        return $waitingList;
    }

    /**
     * Convert waiting list entry to appointment.
     *
     * Sprint 0 fix (NF-6): $createdBy se recibe como parámetro para que la cita
     * resultante quede asociada al usuario autenticado (antes hardcodeado en 1).
     */
    public function convertToAppointment(WaitingList $waitingList, array $appointmentData, ?int $createdBy = null): Appointment
    {
        if ($waitingList->status !== 'active') {
            throw ValidationException::withMessages([
                'waiting_list' => ['La entrada de la lista de espera no está activa.'],
            ]);
        }

        if ($waitingList->isExpired()) {
            throw ValidationException::withMessages([
                'waiting_list' => ['La entrada de la lista de espera ha expirado.'],
            ]);
        }

        $createdBy = $createdBy ?? \Illuminate\Support\Facades\Auth::id();

        DB::beginTransaction();
        try {
            // Create appointment
            $appointment = Appointment::create([
                'patient_id' => $waitingList->patient_id,
                'user_id' => $appointmentData['user_id'] ?? $waitingList->preferred_user_id,
                'dental_chair_id' => $appointmentData['dental_chair_id'],
                'appointment_type_id' => $waitingList->appointment_type_id,
                'scheduled_at' => $appointmentData['scheduled_at'],
                'duration_minutes' => $appointmentData['duration_minutes'] ?? 60,
                'notes' => $appointmentData['notes'] ?? $waitingList->notes,
                'status' => 'scheduled',
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
                'ends_at' => Carbon::parse($appointmentData['scheduled_at'])
                    ->addMinutes($appointmentData['duration_minutes'] ?? 60),
            ]);

            // Mark waiting list entry as converted
            $waitingList->update([
                'status' => 'converted',
                'notes' => $waitingList->notes . "\n\nConvertido a cita #{$appointment->id}",
            ]);

            // Notify patient
            $this->notifyPatient($waitingList->patient, $appointment);

            $appointment->load(['patient', 'user', 'dentalChair', 'appointmentType']);
            $waitingList->refresh();
            $waitingList->load('patient');

            // Emitir evento de WebSocket
            event(new WaitingListFilled($waitingList, $appointment));

            DB::commit();
            return $appointment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Notify patient about available appointment.
     */
    public function notifyPatient(Patient $patient, Appointment $appointment): void
    {
        // Here you would implement actual notification logic
        // For example, send SMS, email, or make a phone call

        Log::info("Patient {$patient->full_name} notified about appointment {$appointment->id}");
    }

    /**
     * Get next priority for waiting list entry.
     */
    public function getNextPriority(int $appointmentTypeId, ?string $preferredDate = null): int
    {
        $query = WaitingList::where('appointment_type_id', $appointmentTypeId)
            ->where('status', 'active');

        if ($preferredDate) {
            $query->where('preferred_date', $preferredDate);
        }

        $maxPriority = $query->max('priority') ?? 0;
        return $maxPriority + 1;
    }

    /**
     * Get waiting list entries by priority.
     */
    public function getWaitingListByPriority(int $appointmentTypeId, ?string $preferredDate = null): Collection
    {
        $query = WaitingList::with(['patient', 'appointmentType', 'preferredUser'])
            ->where('appointment_type_id', $appointmentTypeId)
            ->where('status', 'active')
            ->where('expires_at', '>', now());

        if ($preferredDate) {
            $query->where('preferred_date', $preferredDate);
        }

        return $query->orderBy('priority')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Auto-assign available appointments to waiting list.
     */
    public function autoAssignAppointments(): int
    {
        $assigned = 0;
        $waitingListEntries = WaitingList::with(['patient', 'appointmentType'])
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();

        foreach ($waitingListEntries as $entry) {
            try {
                $availableSlots = $this->findAvailableSlots($entry);

                if (!empty($availableSlots)) {
                    $slot = $availableSlots[0]; // Take the first available slot

                    $this->convertToAppointment($entry, [
                        'user_id' => $slot['user_id'],
                        'dental_chair_id' => $slot['dental_chair_id'],
                        'scheduled_at' => $slot['start'],
                        'duration_minutes' => $entry->appointmentType->default_duration_minutes,
                    ]);

                    $assigned++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to auto-assign appointment for waiting list entry {$entry->id}: " . $e->getMessage());
            }
        }

        return $assigned;
    }

    /**
     * Find available slots for a waiting list entry.
     */
    public function findAvailableSlots(WaitingList $waitingList): array
    {
        $appointmentService = new AppointmentService();
        $slots = [];

        // Get preferred user or all users
        $users = $waitingList->preferred_user_id
            ? User::where('id', $waitingList->preferred_user_id)->get()
            : User::where('role', 'odontologo')->get();

        // Check preferred date or next 30 days
        $startDate = $waitingList->preferred_date
            ? Carbon::parse($waitingList->preferred_date)
            : now();

        $endDate = $startDate->copy()->addDays(30);

        foreach ($users as $user) {
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $availableSlots = $appointmentService->getAvailableTimeSlots(
                    $user->id,
                    $currentDate,
                    $waitingList->appointmentType->default_duration_minutes
                );

                foreach ($availableSlots as $slot) {
                    $slots[] = [
                        'user_id' => $user->id,
                        'dental_chair_id' => 1, // You might want to implement chair selection logic
                        'start' => $slot['start'],
                        'end' => $slot['end'],
                    ];
                }

                $currentDate->addDay();
            }
        }

        return $slots;
    }

    /**
     * Clean up expired waiting list entries.
     */
    public function cleanupExpiredEntries(): int
    {
        return WaitingList::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Get waiting list statistics.
     */
    public function getWaitingListStats(): array
    {
        $total = WaitingList::where('status', 'active')->count();
        $expired = WaitingList::where('status', 'expired')->count();
        $converted = WaitingList::where('status', 'converted')->count();
        $expiringSoon = WaitingList::where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(3))
            ->count();

        return [
            'total_active' => $total,
            'expired' => $expired,
            'converted' => $converted,
            'expiring_soon' => $expiringSoon,
            'conversion_rate' => $total > 0 ? round(($converted / ($total + $converted)) * 100, 2) : 0,
        ];
    }

    /**
     * Update waiting list entry priority.
     */
    public function updatePriority(WaitingList $waitingList, int $newPriority): WaitingList
    {
        if ($newPriority < 1) {
            throw ValidationException::withMessages([
                'priority' => ['La prioridad debe ser mayor a 0.'],
            ]);
        }

        DB::beginTransaction();
        try {
            // Get current priority
            $currentPriority = $waitingList->priority;

            if ($newPriority > $currentPriority) {
                // Moving down in priority
                WaitingList::where('appointment_type_id', $waitingList->appointment_type_id)
                    ->where('status', 'active')
                    ->where('priority', '>', $currentPriority)
                    ->where('priority', '<=', $newPriority)
                    ->decrement('priority');
            } else {
                // Moving up in priority
                WaitingList::where('appointment_type_id', $waitingList->appointment_type_id)
                    ->where('status', 'active')
                    ->where('priority', '>=', $newPriority)
                    ->where('priority', '<', $currentPriority)
                    ->increment('priority');
            }

            $waitingList->update(['priority' => $newPriority]);

            DB::commit();
            return $waitingList;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate waiting list data.
     */
    private function validateWaitingListData(array $data): void
    {
        $rules = [
            'patient_id' => 'required|exists:patients,id',
            'appointment_type_id' => 'required|exists:appointment_types,id',
            'preferred_user_id' => 'nullable|exists:users,id',
            'preferred_date' => 'nullable|date|after:today',
            'preferred_time_start' => 'nullable|date_format:H:i',
            'preferred_time_end' => 'nullable|date_format:H:i|after:preferred_time_start',
            'priority' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
            'expires_at' => 'nullable|date|after:now',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
