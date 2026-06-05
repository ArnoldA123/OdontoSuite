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
        'frequency',
        'interval_value',
        'days_of_week',
        'day_of_month',
        'end_date',
        'max_occurrences',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNextOccurrence(Carbon $fromDate = null): ?Carbon
    {
        $fromDate = $fromDate ?? now();
        $baseDate = $this->appointment->scheduled_at;

        switch ($this->frequency) {
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
        $interval = $this->interval_value ?? 1;

        while ($nextDate->lte($fromDate)) {
            $nextDate->addDays($interval);
        }

        if ($this->end_date && $nextDate->gt($this->end_date)) {
            return null;
        }

        return $nextDate;
    }

    private function getNextWeeklyOccurrence(Carbon $fromDate, Carbon $baseDate): ?Carbon
    {
        $nextDate = $baseDate->copy();
        $interval = $this->interval_value ?? 1;
        $days = $this->days_of_week ?? [$baseDate->dayOfWeek];

        while ($nextDate->lte($fromDate)) {
            $nextDate->addWeeks($interval);
        }

        foreach ($days as $dayOfWeek) {
            $candidate = $nextDate->copy()->startOfWeek()->addDays($dayOfWeek);
            if ($candidate->gte($fromDate)) {
                if (!$this->end_date || $candidate->lte($this->end_date)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function getNextMonthlyOccurrence(Carbon $fromDate, Carbon $baseDate): ?Carbon
    {
        $nextDate = $baseDate->copy();
        $interval = $this->interval_value ?? 1;

        while ($nextDate->lte($fromDate)) {
            $nextDate->addMonths($interval);
        }

        if ($this->end_date && $nextDate->gt($this->end_date)) {
            return null;
        }

        return $nextDate;
    }
}
