<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WaitingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_type_id',
        'preferred_user_id',
        'preferred_date',
        'preferred_time_start',
        'preferred_time_end',
        'priority',
        'notes',
        'status',
        'notified_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'preferred_time_start' => 'datetime:H:i',
        'preferred_time_end' => 'datetime:H:i',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the patient that owns the waiting list entry.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the appointment type for the waiting list entry.
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    /**
     * Get the preferred user for the waiting list entry.
     */
    public function preferredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_user_id');
    }

    /**
     * Scope a query to only include active waiting list entries.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include waiting list entries by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * Scope a query to only include waiting list entries for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('preferred_date', $date);
    }

    /**
     * Check if the waiting list entry is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark as notified.
     */
    public function markAsNotified(): void
    {
        $this->update(['notified_at' => now()]);
    }
}
