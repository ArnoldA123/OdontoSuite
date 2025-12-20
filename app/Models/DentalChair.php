<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DentalChair extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'equipment',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the appointments for the dental chair.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the appointment blocks for the dental chair.
     */
    public function appointmentBlocks(): HasMany
    {
        return $this->hasMany(AppointmentBlock::class);
    }

    /**
     * Scope a query to only include active dental chairs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the audit logs for the dental chair.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
