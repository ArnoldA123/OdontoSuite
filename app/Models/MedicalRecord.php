<?php

namespace App\Models;

use App\Exceptions\Clinical\ClinicalRecordImmutableException;
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
        'is_active',
    ];

    protected $casts = [
        'first_visit_date' => 'date',
        'vital_signs' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // #292: the history is evidence. Removing the route is not enough; a
        // controller, a command or a test helper must not erase a signed row.
        static::deleting(fn () => throw ClinicalRecordImmutableException::medicalRecordNotDeletable());
    }

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

    /**
     * Corrections attached to the history or to any of its evolutions (#292).
     * The original fields are never rewritten, so this is the record's trail of
     * amendments.
     */
    public function addenda(): HasMany
    {
        return $this->hasMany(ClinicalAddendum::class)->orderBy('created_at');
    }

    public function attachments(): HasManyThrough
    {
        // Attachments are related through clinical_evolutions.
        //
        // Archived rows stay in the database as the audit marker (#244) but
        // their binary is gone from the public disk, so the relation — the
        // model's public contract — never offers one: a consumer that forgets
        // to filter would hand out a `/storage/...` URL that answers 404.
        return $this->hasManyThrough(
            ClinicalAttachment::class,
            ClinicalEvolution::class,
            'medical_record_id', // Foreign key on clinical_evolutions table
            'clinical_evolution_id', // Foreign key on clinical_attachments table
            'id', // Local key on medical_records table
            'id' // Local key on clinical_evolutions table
        )->where('clinical_attachments.is_active', true);
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
