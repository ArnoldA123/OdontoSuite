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

    public function markArrival(Appointment $appointment, User $actor): Appointment
    {
        if ($appointment->status === self::STATUS_ARRIVED) {
            return $appointment;
        }

        if (! in_array($appointment->status, self::ARRIVABLE_FROM, true)) {
            throw CustodyException::arrivalNotAllowed($appointment->status);
        }

        DB::transaction(function () use ($appointment, $actor) {
            $from = $appointment->status;

            $appointment->forceFill([
                'status' => self::STATUS_ARRIVED,
                'checked_in_at' => now(),
                'checked_in_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->record(
                $appointment,
                AppointmentCustodyLog::ACTION_ARRIVAL,
                $this->doorFor($actor),
                $from,
                self::STATUS_ARRIVED,
                $actor,
            );
        });

        $this->dispatchSafely(new AppointmentCheckedIn($appointment->refresh()));

        return $appointment->refresh();
    }

    public function startAttention(Appointment $appointment, User $actor): Appointment
    {
        if ($appointment->status === self::STATUS_IN_CONSULTATION) {
            return $appointment;
        }

        if ($appointment->status !== self::STATUS_ARRIVED) {
            throw CustodyException::attentionNotAllowed($appointment->status);
        }

        DB::transaction(function () use ($appointment, $actor) {
            $appointment->forceFill([
                'status' => self::STATUS_IN_CONSULTATION,
                'updated_by' => $actor->id,
            ])->save();

            $this->record(
                $appointment,
                AppointmentCustodyLog::ACTION_ATTENTION_START,
                AppointmentCustodyLog::DOOR_PROFESSIONAL,
                self::STATUS_ARRIVED,
                self::STATUS_IN_CONSULTATION,
                $actor,
            );
        });

        return $appointment->refresh();
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
