<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrthodonticsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'created_by',
        'treatment_type',
        'appliance_type',
        'treatment_start_date',
        'estimated_completion_date',
        'actual_completion_date',
        'treatment_phase',
        'treatment_objectives',
        'current_notes',
        'activation_notes',
        'elastic_configuration',
        'bracket_positions',
        'progress_notes',
        'complications',
        'measurements',
        'retention_plan'
    ];

    protected $casts = [
        'treatment_start_date' => 'date',
        'estimated_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'elastic_configuration' => 'array',
        'bracket_positions' => 'array',
        'measurements' => 'array'
    ];

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByTreatmentType($query, $treatmentType)
    {
        return $query->where('treatment_type', $treatmentType);
    }

    public function scopeByPhase($query, $phase)
    {
        return $query->where('treatment_phase', $phase);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('treatment_phase', ['initial', 'active']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('treatment_phase', 'completed');
    }

    public function scopeInRetention($query)
    {
        return $query->where('treatment_phase', 'retention');
    }

    // Accessors
    public function getTreatmentPhaseLabelAttribute()
    {
        $labels = [
            'initial' => 'Inicial',
            'active' => 'Activo',
            'retention' => 'Retención',
            'completed' => 'Completado'
        ];

        return $labels[$this->treatment_phase] ?? $this->treatment_phase;
    }

    public function getTreatmentDurationAttribute()
    {
        if ($this->actual_completion_date) {
            return $this->treatment_start_date->diffInDays($this->actual_completion_date);
        }

        if ($this->estimated_completion_date) {
            return $this->treatment_start_date->diffInDays($this->estimated_completion_date);
        }

        return $this->treatment_start_date->diffInDays(now());
    }
}
