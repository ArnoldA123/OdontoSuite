<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'voided_at' => 'datetime',
        'final_amount' => 'decimal:2',
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
     * An encounter without an appointment is the urgency door (S5/S8).
     */
    public function isUnscheduled(): bool
    {
        return $this->appointment_id === null;
    }
}
