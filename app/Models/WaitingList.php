<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_type_id',
        'preferred_user_id',
        'preferred_dental_chair_id',
        'preferences',
        'priority',
        'notes',
        'status',
        'preferred_start_date',
        'preferred_end_date',
        'notified_at',
        'created_by',
    ];

    protected $casts = [
        'preferred_start_date' => 'datetime',
        'preferred_end_date' => 'datetime',
        'notified_at' => 'datetime',
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
     * Mark as notified.
     */
    public function markAsNotified(): void
    {
        $this->update([
            'status' => 'notified',
            'notified_at' => now(),
        ]);
    }
}
