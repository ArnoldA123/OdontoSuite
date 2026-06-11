<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'document_number',
        'email',
        'phone',
        'birth_date',
        'gender',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'medical_history',
        'allergies',
        'notes',
        'is_active',
        'branch_id',
        'dni',
        'blood_type',
        'insurance_provider',
        'insurance_number',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the full name attribute.
     */
    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the document number attribute.
     */
    public function getDocumentNumberAttribute()
    {
        return $this->attributes['document_number'] ?? $this->id;
    }

    /**
     * Get the appointments for the patient.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the waiting list entries for the patient.
     */
    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    /**
     * Get the audit logs for the patient.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get the treatment plans for the patient.
     */
    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    /**
     * Get the quotations for the patient.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Get the medical records for the patient.
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Get the odontograms for the patient.
     */
    public function odontograms(): HasMany
    {
        return $this->hasMany(Odontogram::class);
    }


    /**
     * Get endodontics records for the patient.
     */
    public function endodonticsRecords(): HasMany
    {
        return $this->hasMany(EndodonticsRecord::class);
    }

    /**
     * Get implantology records for the patient.
     */
    public function implantologyRecords(): HasMany
    {
        return $this->hasMany(ImplantologyRecord::class);
    }

    /**
     * Get orthodontics records for the patient.
     */
    public function orthodonticsRecords(): HasMany
    {
        return $this->hasMany(OrthodonticsRecord::class);
    }

    /**
     * Get rehabilitation records for the patient.
     */
    public function rehabilitationRecords(): HasMany
    {
        return $this->hasMany(RehabilitationRecord::class);
    }

    /**
     * Get oral surgery records for the patient.
     */
    public function oralSurgeryRecords(): HasMany
    {
        return $this->hasMany(OralSurgeryRecord::class);
    }

    /**
     * Get clinical attachments for the patient.
     */
    public function clinicalAttachments(): HasMany
    {
        return $this->hasMany(ClinicalAttachment::class);
    }

    /**
     * Get the transactions for the patient.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the patient's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Scope a query to only include active patients.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
