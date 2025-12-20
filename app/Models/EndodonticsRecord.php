<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndodonticsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'dental_piece_id',
        'created_by',
        'tooth_number',
        'canal_count',
        'canal_lengths',
        'canal_diameters',
        'working_length_method',
        'pulp_diagnosis',
        'periapical_diagnosis',
        'treatment_plan',
        'anesthesia_used',
        'access_cavity_notes',
        'canal_preparation_notes',
        'irrigation_protocol',
        'medication_used',
        'obturation_technique',
        'obturation_materials',
        'complications',
        'radiographic_measurements',
        'treatment_status',
        'treatment_completion_date',
        'follow_up_notes'
    ];

    protected $casts = [
        'canal_lengths' => 'array',
        'canal_diameters' => 'array',
        'radiographic_measurements' => 'array',
        'treatment_completion_date' => 'date'
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

    public function dentalPiece(): BelongsTo
    {
        return $this->belongsTo(DentalPiece::class);
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

    public function scopeByTooth($query, $toothNumber)
    {
        return $query->where('tooth_number', $toothNumber);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('treatment_status', $status);
    }

    public function scopeInProgress($query)
    {
        return $query->where('treatment_status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('treatment_status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('treatment_status', 'failed');
    }

    public function scopeRetreatment($query)
    {
        return $query->where('treatment_status', 'retreatment');
    }

    // Accessors
    public function getTreatmentStatusLabelAttribute()
    {
        $labels = [
            'in_progress' => 'En Progreso',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'retreatment' => 'Re-tratamiento'
        ];

        return $labels[$this->treatment_status] ?? $this->treatment_status;
    }

    public function getCanalCountLabelAttribute()
    {
        return $this->canal_count . ' conducto' . ($this->canal_count > 1 ? 's' : '');
    }
}
