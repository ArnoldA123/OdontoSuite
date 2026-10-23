<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PatientRecall;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F4 (#284, S1/S4) — the recall domain.
 *
 * A recall is born pending with a due date computed from its interval. It is
 * materialised into a real appointment only through `AppointmentService`, the
 * agenda door that already validates professional and chair conflicts, and the
 * recall plus its appointment are written in the same transaction: a conflict
 * leaves the recall pending and no appointment behind.
 */
class RecallService
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): PatientRecall
    {
        $interval = (int) $data['interval_months'];

        return PatientRecall::create([
            'patient_id' => $data['patient_id'],
            'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $actor->branch_id,
            'interval_months' => $interval,
            'reason' => $data['reason'],
            'due_date' => $data['due_date'] ?? Carbon::today()->addMonths($interval)->toDateString(),
            'status' => PatientRecall::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Materialise a pending recall as a control appointment.
     *
     * @param  array<string, mixed>                                   $slot scheduled_at, user_id, dental_chair_id, appointment_type_id, duration_minutes, notes
     * @return array{recall: PatientRecall, appointment: Appointment}
     */
    public function schedule(PatientRecall $recall, array $slot, User $actor): array
    {
        if ($recall->status !== PatientRecall::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'recall' => ['Solo se puede agendar un recall pendiente. Estado actual: '.$recall->status.'.'],
            ]);
        }

        return DB::transaction(function () use ($recall, $slot): array {
            $appointment = $this->appointments->createAppointment([
                'patient_id' => $recall->patient_id,
                'branch_id' => $recall->branch_id,
                'scheduled_at' => $slot['scheduled_at'],
                'duration_minutes' => $slot['duration_minutes'],
                'user_id' => $slot['user_id'],
                'dental_chair_id' => $slot['dental_chair_id'],
                'appointment_type_id' => $slot['appointment_type_id'],
                'notes' => $slot['notes'] ?? null,
            ]);

            $recall->update([
                'status' => PatientRecall::STATUS_SCHEDULED,
                'scheduled_appointment_id' => $appointment->id,
            ]);

            return ['recall' => $recall->refresh(), 'appointment' => $appointment];
        });
    }

    public function complete(PatientRecall $recall, User $actor): PatientRecall
    {
        if ($recall->status !== PatientRecall::STATUS_SCHEDULED) {
            throw ValidationException::withMessages([
                'recall' => ['Solo se puede completar un recall agendado. Estado actual: '.$recall->status.'.'],
            ]);
        }

        $recall->update([
            'status' => PatientRecall::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $recall->refresh();
    }

    public function cancel(PatientRecall $recall, User $actor): PatientRecall
    {
        if (! in_array($recall->status, [PatientRecall::STATUS_PENDING, PatientRecall::STATUS_SCHEDULED], true)) {
            throw ValidationException::withMessages([
                'recall' => ['Solo se puede cancelar un recall pendiente o agendado. Estado actual: '.$recall->status.'.'],
            ]);
        }

        $recall->update([
            'status' => PatientRecall::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $recall->refresh();
    }

    /**
     * The reception queue: pending recalls whose due date has arrived.
     *
     * @return Collection<int, PatientRecall>
     */
    public function queue(?int $branchId): Collection
    {
        return PatientRecall::query()
            ->due()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->with('patient')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{total: int, pending: int, scheduled: int, completed: int, cancelled: int, overdue: int, compliance_rate: float|null}
     */
    public function metrics(?int $branchId, ?string $from, ?string $to): array
    {
        $base = fn () => PatientRecall::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($from !== null, fn ($query) => $query->whereDate('due_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('due_date', '<=', $to));

        $completed = $base()->where('status', PatientRecall::STATUS_COMPLETED)->count();
        $overdue = $base()->where('status', PatientRecall::STATUS_PENDING)
            ->whereDate('due_date', '<=', Carbon::today()->toDateString())
            ->count();

        $denominator = $completed + $overdue;

        return [
            'total' => $base()->count(),
            'pending' => $base()->where('status', PatientRecall::STATUS_PENDING)->count(),
            'scheduled' => $base()->where('status', PatientRecall::STATUS_SCHEDULED)->count(),
            'completed' => $completed,
            'cancelled' => $base()->where('status', PatientRecall::STATUS_CANCELLED)->count(),
            'overdue' => $overdue,
            'compliance_rate' => $denominator === 0
                ? null
                : round($completed / $denominator, 2),
        ];
    }
}
