<?php

namespace App\Services;

use App\Authorization\Permissions;
use App\Exceptions\Encounter\EncounterException;
use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F2 (#282) — the doors that open and void a clinical act.
 *
 * The appointment is the schedule row; the encounter is the act. Opening is
 * idempotent: an appointment has at most one act being documented, so a second
 * `start-attention` reuses the open encounter instead of stacking rows. Once an
 * act is `voided` the next opening creates a new one, because a voided act is
 * history and never reopened.
 *
 * The read surface belongs to the controller's read actions and is
 * deliberately absent here.
 */
class ClinicalEncounterService
{
    /**
     * Open (or reuse) the act of an appointment.
     *
     * The appointment row is re-read under a row lock, the same guard
     * AppointmentCustodyService uses: two simultaneous openings would otherwise
     * pass the same check and leave two open acts for one appointment. The
     * caller usually holds this lock already (start-attention); the nested
     * transaction is a savepoint in that case and re-locking an owned row is a
     * no-op.
     */
    public function openForAppointment(Appointment $appointment, User $actor): ClinicalEncounter
    {
        return DB::transaction(function () use ($appointment, $actor) {
            $current = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $open = $current->openClinicalEncounter()->first();

            if ($open !== null) {
                return $open;
            }

            return ClinicalEncounter::create([
                'patient_id' => $current->patient_id,
                'appointment_id' => $current->id,
                'user_id' => $current->user_id ?? $actor->id,
                // Same rule as encounters:backfill: the mode the appointment
                // recorded when it closed, or execution when it never did. The
                // wizard still sends the definitive mode at closure.
                'mode' => $current->consultation_mode ?? ClinicalEncounter::MODE_EXECUTION,
                'status' => ClinicalEncounter::STATUS_OPEN,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Open the urgency act. No agenda row is invented to hold it (S8).
     *
     * @param array<string, mixed> $data
     */
    public function openUrgent(array $data, User $actor): ClinicalEncounter
    {
        $professionalId = (int) ($data['user_id'] ?? $actor->id);

        // #282: an administrator may attribute the act to another professional,
        // but the record must name somebody who can hold a clinical act.
        $professional = User::find($professionalId);
        if ($professional === null || ! Permissions::roleHas($professional->role, Permissions::START_CONSULTATION)) {
            throw EncounterException::professionalNotClinical($professionalId);
        }

        return ClinicalEncounter::create([
            'patient_id' => $data['patient_id'],
            'appointment_id' => null,
            'user_id' => $professionalId,
            'mode' => ClinicalEncounter::MODE_URGENCY,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'opened_at' => now(),
            'notes' => $data['notes'] ?? null,
            // F6 (#286): the complaint is part of the act from the moment it is
            // opened, and the close may complete it.
            'reason_for_consultation' => $data['reason_for_consultation'] ?? null,
        ]);
    }

    /**
     * Void an act that never produced clinical documentation.
     *
     * Only `open` is voidable: a closed act holds the evolution, findings,
     * procedures, materials, attachments and amount, and the domain rule
     * (#292) is that the clinical record is never erased. The row lock keeps
     * two simultaneous voids from both passing the guard.
     */
    public function void(ClinicalEncounter $encounter, User $actor, ?string $reason): ClinicalEncounter
    {
        return DB::transaction(function () use ($encounter, $actor, $reason) {
            $current = ClinicalEncounter::query()
                ->whereKey($encounter->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $current->isOpen()) {
                throw EncounterException::voidNotAllowed($current->status);
            }

            $current->forceFill([
                'status' => ClinicalEncounter::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            return $current;
        });
    }
}
