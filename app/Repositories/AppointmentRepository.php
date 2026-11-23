<?php

namespace App\Repositories;

use App\Models\Appointment;
use App\Models\DentalChair;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AppointmentRepository
{
    /**
     * #392 — the status that releases its slot.
     *
     * `appointments.slot_reservation` is a stored generated column that is NULL
     * for exactly this status and 1 for any other, and both unique indexes
     * (`unique_user_time_slot`, `unique_chair_time_slot`) include it. This is
     * the same rule, declared once for the pre-check so the query and the index
     * cannot drift apart again.
     */
    public const SLOT_RELEASED_STATUS = 'cancelled';

    /**
     * Half-open slot rule: `[inicio, fin)`.
     *
     * Two appointments overlap if and only if
     *
     *   existing.scheduled_at < new.ends_at   AND   existing.ends_at > new.scheduled_at
     *
     * Both bounds are strict on purpose. 09:00–09:30 and 09:30–10:00 are two
     * adjacent slots, not a clash, and the same holds for the closing edge of
     * a reschedule. `whereBetween` includes both ends, which is what made the
     * boundary a false conflict for the professional and for the chair.
     *
     * `ends_at` is a stored NOT NULL datetime and the three doors that write a
     * slot (create, update, reschedule) always set it, so the derivation in
     * the expression below is a safety net rather than a normal path: without
     * it, a row with a null end would compare as UNKNOWN, escape every
     * conflict check and be free to be double booked — the one outcome worse
     * than a false conflict. Deriving the end from `scheduled_at` +
     * `duration_minutes` (both NOT NULL) keeps a hypothetical legacy row
     * inside the control instead of reserving its slot forever.
     */
    private const SLOT_END_EXPRESSION = 'COALESCE(`ends_at`, TIMESTAMPADD(MINUTE, `duration_minutes`, `scheduled_at`))';

    /**
     * Find appointments with conflicts for a given user and time range.
     */
    public function findConflicts(
        int $userId,
        int $chairId,
        Carbon $scheduledAt,
        int $duration,
        ?int $excludeAppointmentId = null
    ): Collection {
        return $this->conflictQuery($userId, $chairId, $scheduledAt, $duration, $excludeAppointmentId)
            ->get();
    }

    /**
     * The half-open pre-check as the write doors must run it: under the slot
     * lock and reading the rows as they are now.
     *
     * Two locks and a read, in this order:
     *
     *  1. `users` row of the professional.
     *  2. `dental_chairs` row of the chair.
     *  3. the conflicting appointments, with `FOR UPDATE` so the read is a
     *     current read and sees whatever the transaction it waited on just
     *     committed, even under REPEATABLE READ.
     *
     * The order is fixed on purpose: every writer takes the professional before
     * the chair, so a crossing pair (professional A in chair B, professional C
     * in chair D) can never hold what the other waits for. Without the lock two
     * receptionists pass the read at the same time and only the unique index
     * answers, which keys the exact interval and therefore misses every partial
     * overlap (09:00-09:30 vs 09:15-09:45 are different keys).
     *
     * Must run inside a transaction: the locks are released on commit, which is
     * the moment the booking becomes visible to the waiter.
     */
    public function findConflictsForWrite(
        int $userId,
        ?int $chairId,
        Carbon $scheduledAt,
        int $duration,
        ?int $excludeAppointmentId = null
    ): Collection {
        $this->lockSlotOwners($userId, $chairId);

        return $this->conflictQuery($userId, $chairId, $scheduledAt, $duration, $excludeAppointmentId)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Serialize the writers of the same slot pair: professional first, chair
     * second. A missing row locks nothing and is caught by the validation that
     * runs before this.
     */
    public function lockSlotOwners(int $userId, ?int $chairId): void
    {
        User::query()->whereKey($userId)->lockForUpdate()->first();

        if ($chairId !== null) {
            DentalChair::query()->whereKey($chairId)->lockForUpdate()->first();
        }
    }

    /**
     * The read shared by the plain pre-check and the locking one.
     */
    private function conflictQuery(
        int $userId,
        ?int $chairId,
        Carbon $scheduledAt,
        int $duration,
        ?int $excludeAppointmentId
    ): Builder {
        $endsAt = $scheduledAt->copy()->addMinutes($duration);
        $overlapsSlot = $this->overlapsSlot($scheduledAt, $endsAt);

        return Appointment::query()
            ->where('status', '!=', self::SLOT_RELEASED_STATUS)
            ->when(
                $excludeAppointmentId !== null,
                fn ($query) => $query->where('id', '!=', $excludeAppointmentId)
            )
            ->where(function ($query) use ($userId, $chairId, $overlapsSlot) {
                $query->where(function ($professional) use ($userId, $overlapsSlot) {
                    $professional->where('user_id', $userId)->where($overlapsSlot);
                })->when($chairId !== null, function ($query) use ($chairId, $overlapsSlot) {
                    $query->orWhere(function ($chair) use ($chairId, $overlapsSlot) {
                        $chair->where('dental_chair_id', $chairId)->where($overlapsSlot);
                    });
                });
            });
    }

    /**
     * The overlap rule as a nested query group. The professional branch and the
     * chair branch each apply this same closure, so the rule is written once.
     */
    private function overlapsSlot(Carbon $scheduledAt, Carbon $endsAt): Closure
    {
        return function ($query) use ($scheduledAt, $endsAt) {
            $query->where('scheduled_at', '<', $endsAt)
                ->whereRaw(self::SLOT_END_EXPRESSION.' > ?', [$scheduledAt]);
        };
    }

    /**
     * Find appointments for a user within a date range.
     */
    public function findByUserAndDateRange(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?array $statuses = null
    ): Collection {
        $query = Appointment::where('user_id', $userId)
            ->whereBetween('scheduled_at', [$startDate, $endDate]);

        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        return $query->orderBy('scheduled_at')->get();
    }

    /**
     * Find appointments for a patient.
     */
    public function findByPatient(int $patientId, ?int $limit = null): Collection
    {
        $query = Appointment::where('patient_id', $patientId)
            ->orderBy('scheduled_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Find appointments for today.
     */
    public function findToday(?int $userId = null): Collection
    {
        $today = Carbon::today();

        $query = Appointment::whereDate('scheduled_at', $today);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderBy('scheduled_at')->get();
    }

    /**
     * Find upcoming appointments.
     */
    public function findUpcoming(Carbon $from, Carbon $to, ?int $limit = null): Collection
    {
        $query = Appointment::whereBetween('scheduled_at', [$from, $to])
            ->orderBy('scheduled_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Paginate appointments with filters.
     */
    public function paginate(
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Appointment::query();

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('scheduled_at', [
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date']),
            ]);
        }

        return $query->orderBy('scheduled_at', 'desc')->paginate($perPage);
    }
}
