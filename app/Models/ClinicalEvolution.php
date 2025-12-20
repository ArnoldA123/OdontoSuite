<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalEvolution extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'medical_record_id',
        'created_by',
        'evolution_date',
        'specialty',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'procedures_performed',
        'materials_used',
        'prescriptions',
        'recommendations',
        'next_appointment_notes',
        'vital_signs',
        'clinical_measurements',
        'requires_follow_up',
        'follow_up_date'
    ];

    protected $casts = [
        'evolution_date' => 'date',
        'vital_signs' => 'array',
        'clinical_measurements' => 'array',
        'requires_follow_up' => 'boolean',
        'follow_up_date' => 'date'
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

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClinicalAttachment::class);
    }

    // Scopes
    public function scopeByPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeBySpecialty($query, $specialty)
    {
        return $query->where('specialty', $specialty);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('evolution_date', $date);
    }

    public function scopeRequiresFollowUp($query)
    {
        return $query->where('requires_follow_up', true);
    }
}
