<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AppointmentBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dental_chair_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_all_day',
        'type',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_all_day' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the appointment block.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dental chair for the appointment block.
     */
    public function dentalChair(): BelongsTo
    {
        return $this->belongsTo(DentalChair::class);
    }

    /**
     * Scope a query to only include active appointment blocks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include appointment blocks for a specific date range.
     */
    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Check if a date and time conflicts with this block.
     */
    public function conflictsWith(Carbon $date, Carbon $startTime, Carbon $endTime): bool
    {
        if ($date->lt($this->start_date) || $date->gt($this->end_date)) {
            return false;
        }

        if ($this->is_all_day) {
            return true;
        }

        $blockStart = $date->copy()->setTimeFromTimeString($this->start_time->format('H:i'));
        $blockEnd = $date->copy()->setTimeFromTimeString($this->end_time->format('H:i'));

        return $startTime->lt($blockEnd) && $endTime->gt($blockStart);
    }
}
