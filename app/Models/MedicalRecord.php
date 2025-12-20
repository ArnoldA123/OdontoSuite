<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'created_by',
        'record_number',
        'first_visit_date',
        'chief_complaint',
        'medical_history',
        'dental_history',
        'allergies',
        'medications',
        'systemic_conditions',
        'family_history',
        'social_history',
        'vital_signs',
        'clinical_examination',
        'diagnosis',
        'treatment_plan',
        'notes',
        'is_active'
    ];

    protected $casts = [
        'first_visit_date' => 'date',
        'vital_signs' => 'array',
        'is_active' => 'boolean'
    ];

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evolutions(): HasMany
    {
        return $this->hasMany(ClinicalEvolution::class);
    }

    public function attachments(): HasMany
    {
        // Attachments are related through clinical_evolutions
        // Get all attachment IDs from related evolutions
        return $this->hasManyThrough(
            ClinicalAttachment::class,
            ClinicalEvolution::class,
            'medical_record_id', // Foreign key on clinical_evolutions table
            'clinical_evolution_id', // Foreign key on clinical_attachments table
            'id', // Local key on medical_records table
            'id' // Local key on clinical_evolutions table
        );
    }

    // Scopes
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySpecialist($query, $specialistId)
    {
        return $query->where('created_by', $specialistId);
    }
}
