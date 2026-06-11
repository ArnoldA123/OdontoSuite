<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Interconsultation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'from_specialist_id',
        'to_specialist_id',
        'appointment_id',
        'specialty_from',
        'specialty_to',
        'reason',
        'clinical_question',
        'clinical_data',
        'requested_studies',
        'priority',
        'status',
        'response',
        'recommendations',
        'follow_up_notes',
        'requested_date',
        'response_date',
        'follow_up_date'
    ];

    protected $casts = [
        'requested_date' => 'date',
        'response_date' => 'date',
        'follow_up_date' => 'date'
    ];

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromSpecialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_specialist_id');
    }

    public function toSpecialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_specialist_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // Scopes
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByFromSpecialist($query, $specialistId)
    {
        return $query->where('from_specialist_id', $specialistId);
    }

    public function scopeByToSpecialist($query, $specialistId)
    {
        return $query->where('to_specialist_id', $specialistId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getPriorityLabelAttribute()
    {
        $labels = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente'
        ];

        return $labels[$this->priority] ?? $this->priority;
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pendiente',
            'in_progress' => 'En Progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada'
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
