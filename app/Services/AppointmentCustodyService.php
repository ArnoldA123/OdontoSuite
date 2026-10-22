<?php

namespace App\Services;

use App\Events\AppointmentCheckedIn;
use App\Events\PatientReady;
use App\Exceptions\Custody\CustodyException;
use App\Models\Appointment;
use App\Models\AppointmentCustodyLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F1 (#281) — custody between the desks.
 *
 * The appointment status is the current custody position and this service is
 * the only writer of its transitions:
 *
 *   scheduled|confirmed --arrival(reception|professional)--> arrived
 *   arrived --attention_start(professional)--> in_consultation
 *
 * Announcement does not move the status (S2): it only notifies the
 * professional that the patient is ready. Every transition leaves one
 * appointment_custody_logs row with author, hour and door (S7).
 */
class AppointmentCustodyService
{
    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_IN_CONSULTATION = 'in_consultation';

    /** @var list<string> */
    private const ARRIVABLE_FROM = ['scheduled', 'confirmed'];

    /** @var list<string> */
    private const CLINICAL_ROLES = ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'];

    public function __construct(private readonly ClinicalEncounterService $encounters) {}

    public function markArrival(Appointment $appointment, User $actor): Appointment
    {
        // The status is re-read under a row lock inside the transaction: two
        // simultaneous check-ins used to pass the same guard and write two
        // custody rows for one transfer, which corrupts the trail S7 requires.
        [$changed, $current] = DB::transaction(function () use ($appointment, $actor) {
            $current = $this->lockAppointment($appointment);

            if ($current->status === self::STATUS_ARRIVED) {
                return [false, $current];
            }

            if (! in_array($current->status, self::ARRIVABLE_FROM, true)) {
                throw CustodyException::arrivalNotAllowed($current->status);
            }

            $from = $current->status;

            $current->forceFill([
                'status' => self::STATUS_ARRIVED,
                'checked_in_at' => now(),
                'checked_in_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->record(
                $current,
                AppointmentCustodyLog::ACTION_ARRIVAL,
                $this->doorFor($actor),
                $from,
                self::STATUS_ARRIVED,
                $actor,
            );

            return [true, $current];
        });

        if ($changed) {
            $this->dispatchSafely(new AppointmentCheckedIn($current));
        }

        return $current;
    }

    public function startAttention(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor) {
            $current = $this->lockAppointment($appointment);

            if ($current->status === self::STATUS_IN_CONSULTATION) {
                // Idempotent path: the act is already open and must not be
                // opened twice (the encounter service would reuse it anyway).
                return $current;
            }

            if ($current->status !== self::STATUS_ARRIVED) {
                throw CustodyException::attentionNotAllowed($current->status);
            }

            $current->forceFill([
                'status' => self::STATUS_IN_CONSULTATION,
                'updated_by' => $actor->id,
            ])->save();

            $this->record(
                $current,
                AppointmentCustodyLog::ACTION_ATTENTION_START,
                AppointmentCustodyLog::DOOR_PROFESSIONAL,
                self::STATUS_ARRIVED,
                self::STATUS_IN_CONSULTATION,
                $actor,
            );

            // F2 (#282): the custody transfer and the opening of the act are
            // one act, so they share the transaction. A failure here rolls the
            // status back instead of leaving an attention with no encounter.
            $this->encounters->openForAppointment($current, $actor);

            return $current;
        });
    }

    public function announce(Appointment $appointment, User $actor): Appointment
    {
        if ($appointment->status !== self::STATUS_ARRIVED) {
            throw CustodyException::announcementNotAllowed($appointment->status);
        }

        $this->dispatchSafely(new PatientReady(
            $appointment->loadMissing('patient', 'user', 'appointmentType', 'dentalChair'),
            $actor,
        ));

        return $appointment;
    }

    /**
     * The professional's queue: today's waiting and upcoming appointments plus
     * whatever act is still open, which is not limited to today because an
     * unfinished consultation must never disappear from the desk.
     *
     * @return array{waiting: Collection<int, Appointment>, attending: Collection<int, Appointment>, upcoming: Collection<int, Appointment>}
     */
    public function queueFor(User $user): array
    {
        $base = fn () => Appointment::query()
            ->with(['patient', 'user', 'appointmentType', 'dentalChair', 'checkedInBy'])
            ->where('user_id', $user->id);

        $today = now()->toDateString();

        return [
            'waiting' => $base()
                ->where('status', self::STATUS_ARRIVED)
                ->whereDate('scheduled_at', $today)
                ->orderBy('scheduled_at')
                ->get(),
            'attending' => $base()
                ->where('status', self::STATUS_IN_CONSULTATION)
                ->orderBy('checked_in_at')
                ->get(),
            'upcoming' => $base()
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->whereDate('scheduled_at', $today)
                ->orderBy('scheduled_at')
                ->get(),
        ];
    }

    /**
     * The door is the desk that performs the act, derived from the actor's
     * role: only clinical roles take custody as the professional; the
     * receptionist (and administration) act at reception.
     */
    public function doorFor(User $actor): string
    {
        return in_array($actor->role, self::CLINICAL_ROLES, true)
            ? AppointmentCustodyLog::DOOR_PROFESSIONAL
            : AppointmentCustodyLog::DOOR_RECEPTION;
    }

    /**
     * Serialize the transition on the appointment row. `lockForUpdate` is a
     * no-op on engines without row locks, and the DB-backed suite runs on
     * MySQL, where it is the guard that keeps one transfer to one row.
     */
    private function lockAppointment(Appointment $appointment): Appointment
    {
        return Appointment::query()
            ->whereKey($appointment->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function record(
        Appointment $appointment,
        string $action,
        string $door,
        string $from,
        string $to,
        User $actor,
    ): void {
        AppointmentCustodyLog::create([
            'appointment_id' => $appointment->id,
            'action' => $action,
            'door' => $door,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * AGENTS.md §7: a failing broadcast must never break the custody act.
     */
    private function dispatchSafely(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('No se pudo emitir el evento de custodia', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
