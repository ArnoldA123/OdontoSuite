<?php

namespace App\Models;

use App\Exceptions\Clinical\ClinicalRecordImmutableException;
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
        'clinical_encounter_id',
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
        'follow_up_date',
    ];

    protected $casts = [
        'evolution_date' => 'date',
        'vital_signs' => 'array',
        'clinical_measurements' => 'array',
        'requires_follow_up' => 'boolean',
        'follow_up_date' => 'date',
    ];

    protected static function booted(): void
    {
        // #292: a documented visit is evidence; a correction is an addendum.
        static::deleting(fn () => throw ClinicalRecordImmutableException::clinicalEvolutionNotDeletable());
    }

    // Relaciones
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * F2 (#282): the clinical act that produced this evolution.
     */
    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Corrections signed against this visit (#292). The evolution itself is
     * never edited; each addendum carries its own author, date and reason.
     */
    public function addenda(): HasMany
    {
        return $this->hasMany(ClinicalAddendum::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        // Archived rows are the audit marker of a deleted binary (#244): the
        // row survives, the file does not. Serving them would give the client
        // a `/storage/...` URL that answers 404, so the relation exposes only
        // the attachments whose file still exists.
        return $this->hasMany(ClinicalAttachment::class)
            ->where('clinical_attachments.is_active', true);
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
