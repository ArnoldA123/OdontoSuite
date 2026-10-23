<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ContinuityRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F4 (#284) — continuity between acts.
 *
 * The professional leaves a conduct, reception turns it into an appointment.
 * The appointment is never written here directly: `AppointmentService` is the
 * only agenda door, so the conflict validation of the calendar applies to a
 * control appointment exactly as it applies to any other booking.
 */
class ContinuityService
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly RecallService $recalls,
    ) {}

    /**
     * @param array<string, mixed>|null $next
     */
    public function requestFromClosure(
        ClinicalEncounter $encounter,
        ?Appointment $appointment,
        ?array $next,
        ?int $userId,
    ): ?ContinuityRequest {
        if (! is_array($next) || empty($next['scheduled_at'])) {
            return null;
        }

        $from = Carbon::parse($next['scheduled_at'])->setTimezone(config('app.timezone'));
        $duration = (int) ($next['duration_minutes'] ?? 30);
        $encounter->loadMissing('patient');

        return ContinuityRequest::create([
            'patient_id' => $encounter->patient_id,
            'branch_id' => $appointment?->branch_id ?? $encounter->patient?->branch_id,
            'clinical_encounter_id' => $encounter->id,
            'treatment_plan_id' => $encounter->treatment_plan_id,
            'appointment_id' => $encounter->appointment_id,
            'appointment_type_id' => $next['appointment_type_id'] ?? $appointment?->appointment_type_id,
            'kind' => ContinuityRequest::KIND_NEXT_SESSION,
            'status' => ContinuityRequest::STATUS_PENDING,
            'preferred_from' => $from,
            'preferred_to' => $from->copy()->addMinutes($duration),
            'duration_minutes' => $duration,
            'notes' => $next['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>                                        $slot
     * @return array{request: ContinuityRequest, appointment: Appointment}
     */
    public function schedule(ContinuityRequest $request, array $slot, User $actor): array
    {
        return DB::transaction(function () use ($request, $slot) {
            $current = ContinuityRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $current->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ["Solo una solicitud pendiente se puede agendar (estado actual: {$current->status})."],
                ]);
            }

            $appointmentTypeId = $slot['appointment_type_id'] ?? $current->appointment_type_id;

            if ($appointmentTypeId === null) {
                throw ValidationException::withMessages([
                    'appointment_type_id' => ['La cita de control necesita un tipo de cita.'],
                ]);
            }

            $appointment = $this->appointments->createAppointment([
                'patient_id' => $current->patient_id,
                'user_id' => $slot['user_id'],
                'dental_chair_id' => $slot['dental_chair_id'],
                'appointment_type_id' => $appointmentTypeId,
                'branch_id' => $current->branch_id,
                'scheduled_at' => $slot['scheduled_at'],
                'duration_minutes' => $slot['duration_minutes'] ?? $current->duration_minutes ?? 30,
                'notes' => $slot['notes'] ?? $current->notes,
            ]);

            $current->update([
                'status' => ContinuityRequest::STATUS_SCHEDULED,
                'scheduled_appointment_id' => $appointment->id,
                'resolution_notes' => $slot['notes'] ?? null,
            ]);

            return ['request' => $current->refresh(), 'appointment' => $appointment];
        });
    }

    public function dismiss(ContinuityRequest $request, ?string $reason = null): ContinuityRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ["Solo una solicitud pendiente se puede descartar (estado actual: {$request->status})."],
            ]);
        }

        $request->update([
            'status' => ContinuityRequest::STATUS_DISMISSED,
            'resolution_notes' => $reason,
        ]);

        return $request->refresh();
    }

    /**
     * @return Collection<int, ContinuityRequest>
     */
    public function queue(?int $branchId = null): Collection
    {
        return ContinuityRequest::query()
            ->with(['patient', 'appointmentType', 'clinicalEncounter', 'treatmentPlan'])
            ->pending()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByRaw('preferred_from IS NULL')
            ->orderBy('preferred_from')
            ->orderBy('id')
            ->get();
    }

    /**
     * The reception desk reads both sources of pending continuity in one call:
     * overdue recalls and conduct requests still waiting for a slot.
     *
     * @return array<string, mixed>
     */
    public function forDesk(?int $branchId = null): array
    {
        $recalls = $this->recalls->queue($branchId);
        $requests = $this->queue($branchId);

        return [
            'recalls' => $recalls,
            'requests' => $requests,
            'counts' => [
                'recalls' => $recalls->count(),
                'requests' => $requests->count(),
                'total' => $recalls->count() + $requests->count(),
            ],
        ];
    }
}
