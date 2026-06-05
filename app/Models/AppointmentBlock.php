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
        'reason',
        'starts_at',
        'ends_at',
        'type',
        'is_recurring',
        'recurrence_pattern',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dentalChair(): BelongsTo
    {
        return $this->belongsTo(DentalChair::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('starts_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
              ->orWhereBetween('ends_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('starts_at', '<=', $startDate->copy()->startOfDay())
                     ->where('ends_at', '>=', $endDate->copy()->endOfDay());
              });
        });
    }

    public function conflictsWith(Carbon $startTime, Carbon $endTime): bool
    {
        return $startTime->lt($this->ends_at) && $endTime->gt($this->starts_at);
    }
}
