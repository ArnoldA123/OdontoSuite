<?php

namespace App\Repositories;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AppointmentRepository
{
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
        $endsAt = $scheduledAt->copy()->addMinutes($duration);

        return Appointment::where('status', '!=', 'cancelled')
            ->where(function ($query) use ($userId, $chairId, $scheduledAt, $endsAt, $excludeAppointmentId) {
                // User conflicts
                $query->where(function ($q) use ($userId, $scheduledAt, $endsAt, $excludeAppointmentId) {
                    $q->where('user_id', $userId)
                      ->where('id', '!=', $excludeAppointmentId)
                      ->where(function ($subQ) use ($scheduledAt, $endsAt) {
                          $subQ->whereBetween('scheduled_at', [$scheduledAt, $endsAt])
                               ->orWhereBetween('ends_at', [$scheduledAt, $endsAt])
                               ->orWhere(function ($q2) use ($scheduledAt, $endsAt) {
                                   $q2->where('scheduled_at', '<=', $scheduledAt)
                                      ->where('ends_at', '>=', $endsAt);
                               });
                      });
                })
                // Chair conflicts
                ->orWhere(function ($q) use ($chairId, $scheduledAt, $endsAt, $excludeAppointmentId) {
                    $q->where('dental_chair_id', $chairId)
                      ->where('id', '!=', $excludeAppointmentId)
                      ->where(function ($subQ) use ($scheduledAt, $endsAt) {
                          $subQ->whereBetween('scheduled_at', [$scheduledAt, $endsAt])
                               ->orWhereBetween('ends_at', [$scheduledAt, $endsAt])
                               ->orWhere(function ($q2) use ($scheduledAt, $endsAt) {
                                   $q2->where('scheduled_at', '<=', $scheduledAt)
                                      ->where('ends_at', '>=', $endsAt);
                               });
                      });
                });
            })->get();
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
                Carbon::parse($filters['end_date'])
            ]);
        }

        return $query->orderBy('scheduled_at', 'desc')->paginate($perPage);
    }
}

