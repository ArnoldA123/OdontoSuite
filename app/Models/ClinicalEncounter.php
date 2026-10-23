<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * F2 (#282) — the clinical act.
 *
 * The appointment is a schedule row; this is the act itself. A professional
 * opens one encounter per act, documents it (evolution, findings, procedures,
 * materials, attachments), and closes it once: the amount and the quotation
 * are produced by that closure. An encounter without an appointment is the
 * urgency door: no agenda row is invented to hold the act.
 *
 * State machine: `open → closed | voided`. `voided` is only reachable while the
 * act is still `open`; a closed encounter holds clinical documentation and is
 * never erased (domain rule of #292).
 */
class ClinicalEncounter extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_VOIDED = 'voided';

    public const MODE_CONSULTATION = 'consultation';

    public const MODE_EXECUTION = 'execution';

    public const MODE_PLAN_SESSION = 'plan_session';

    public const MODE_URGENCY = 'urgency';

    public const VALID_MODES = [
        self::MODE_CONSULTATION,
        self::MODE_EXECUTION,
        self::MODE_PLAN_SESSION,
        self::MODE_URGENCY,
    ];

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'user_id',
        'medical_record_id',
        'treatment_plan_id',
        'mode',
        'status',
        'opened_at',
        'closed_at',
        'closed_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'final_amount',
        'notes',
        // F6 (#286): the NTS 139 record. The complaint lives on the act, and the
        // signature block freezes who signed it and over which content.
        'reason_for_consultation',
        'signed_at',
        'signed_by',
        'signer_license',
        'signature_hash',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'voided_at' => 'datetime',
        'final_amount' => 'decimal:2',
        'signed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * The professional who performed the act.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * F6 (#286): the professional who signed the close.
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * F6 (#286): the CIE-10 diagnoses of the act, in the order they were
     * recorded. The record is signed over this order, so it never reorders.
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class, 'clinical_encounter_id')->orderBy('id');
    }

    public function clinicalEvolutions(): HasMany
    {
        return $this->hasMany(ClinicalEvolution::class);
    }

    public function odontogramRecords(): HasMany
    {
        return $this->hasMany(OdontogramRecord::class);
    }

    public function procedureMaterials(): HasMany
    {
        return $this->hasMany(ProcedureMaterial::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClinicalAttachment::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * #296: the AI analyses that support this act's decisions.
     */
    public function aiImageAnalyses(): HasMany
    {
        return $this->hasMany(AiImageAnalysis::class);
    }

    /**
     * F4 (#284): the plan items this act consumed.
     */
    public function planItemConsumptions(): HasMany
    {
        return $this->hasMany(TreatmentPlanItemConsumption::class);
    }

    /**
     * F4 (#284): the conduct this act left for reception.
     */
    public function continuityRequests(): HasMany
    {
        return $this->hasMany(ContinuityRequest::class);
    }

    /**
     * #287 (T2): the debt the closure of this act produced. Exactly one per
     * act, enforced by the unique index on `patient_charges`.
     */
    public function charge(): HasOne
    {
        return $this->hasOne(PatientCharge::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * F6 (#286): a closed act is signed. Historical acts of #282 predate the
     * rule, so the absence of a signature is readable instead of assumed.
     */
    public function isSigned(): bool
    {
        return $this->signed_at !== null && $this->signature_hash !== null;
    }

    /**
     * An encounter without an appointment is the urgency door (S5/S8).
     */
    public function isUnscheduled(): bool
    {
        return $this->appointment_id === null;
    }
}
