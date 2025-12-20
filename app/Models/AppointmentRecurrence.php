<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AppointmentRecurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_days',
        'recurrence_end_date',
        'recurrence_count',
        'is_active',
    ];

    protected $casts = [
        'recurrence_days' => 'array',
        'recurrence_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the appointment that owns the recurrence.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Scope a query to only include active recurrences.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate the next occurrence date based on the recurrence pattern.
     */
    public function getNextOccurrence(Carbon $fromDate = null): ?Carbon
    {
        $fromDate = $fromDate ?? now();
        $baseDate = $this->appointment->scheduled_at;

        switch ($this->recurrence_type) {
            case 'daily':
                return $this->getNextDailyOccurrence($fromDate, $baseDate);
            case 'weekly':
                return $this->getNextWeeklyOccurrence($fromDate, $baseDate);
            case 'monthly':
                return $this->getNextMonthlyOccurrence($fromDate, $baseDate);
            default:
                return null;
        }
    }

    private function getNextDailyOccurrence(Carbon $fromDate, Carbon $baseDate): ?Carbon
    {
        $nextDate = $baseDate->copy();
        $interval = $this->recurrence_interval ?? 1;

        while ($nextDate->lte($fromDate)) {
            $nextDate->addDays($interval);
        }

        if ($this->recurrence_end_date && $nextDate->gt($this->recurrence_end_date)) {
            return null;
        }

        return $nextDate;
    }

    private function getNextWeeklyOccurrence(Carbon $fromDate, Carbon $baseDate): ?Carbon
    {
        $nextDate = $baseDate->copy();
        $interval = $this->recurrence_interval ?? 1;
        $days = $this->recurrence_days ?? [$baseDate->dayOfWeek];

        while ($nextDate->lte($fromDate)) {
            $nextDate->addWeeks($interval);
        }

        // Find the next valid day of week
        foreach ($days as $dayOfWeek) {
            $candidate = $nextDate->copy()->startOfWeek()->addDays($dayOfWeek);
            if ($candidate->gte($fromDate)) {
                if (!$this->recurrence_end_date || $candidate->lte($this->recurrence_end_date)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function getNextMonthlyOccurrence(Carbon $fromDate, Carbon $baseDate): ?Carbon
    {
        $nextDate = $baseDate->copy();
        $interval = $this->recurrence_interval ?? 1;

        while ($nextDate->lte($fromDate)) {
            $nextDate->addMonths($interval);
        }

        if ($this->recurrence_end_date && $nextDate->gt($this->recurrence_end_date)) {
            return null;
        }

        return $nextDate;
    }
}
