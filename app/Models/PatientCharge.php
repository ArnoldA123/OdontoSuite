<?php

namespace App\Models;

use App\Exceptions\Money\ImmutableLedgerEntryException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #287 (T2, S3/S4) — what a patient owes.
 *
 * The charge is the positive side of the patient ledger: the closure of an act
 * writes one (`source = encounter`) and the counter records the ones no act
 * produced (`source = manual`). Payments, refunds, discounts, write-offs and
 * adjustments live in `transactions` and reduce or move this debt (#287, D3).
 *
 * The ledger is immutable by contract: a wrong charge is corrected with an
 * adjustment, never edited or deleted, which is why no update or delete door
 * exists and the model itself refuses both verbs. The charge follows the
 * patient, so it carries no branch of its own (ADR-0010: the ficha is shared
 * between sedes).
 */
class PatientCharge extends Model
{
    use HasFactory;

    public const SOURCE_ENCOUNTER = 'encounter';

    public const SOURCE_MANUAL = 'manual';

    protected static function booted(): void
    {
        static::updating(fn () => throw ImmutableLedgerEntryException::chargeNotEditable());
        static::deleting(fn () => throw ImmutableLedgerEntryException::chargeNotDeletable());
    }

    protected $fillable = [
        'patient_id',
        'clinical_encounter_id',
        'source',
        'description',
        'amount',
        'charged_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The act that produced the charge; null for a manual charge.
     */
    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
