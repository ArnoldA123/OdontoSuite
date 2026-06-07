<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AppointmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'default_duration_minutes',
        'price',
        'color',
        'requires_confirmation',
        'is_active',
        'requires_materials',
        'is_consultation_mode',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_confirmation' => 'boolean',
        'is_active' => 'boolean',
        'requires_materials' => 'boolean',
        'is_consultation_mode' => 'boolean',
    ];

    /**
     * Get the appointments for the appointment type.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the waiting lists for the appointment type.
     */
    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    /**
     * Scope a query to only include active appointment types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the audit logs for the appointment type.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
