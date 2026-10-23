<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #321 (S1-S4) — how much of a payment settled a charge.
 *
 * The row is the imputation of one payment to one charge of the same patient.
 * It is a view over money that already exists in `transactions` and
 * `patient_charges`: the patient balance is still the rule of the statement
 * (#287, D3), and this table only answers which charge the money covered.
 *
 * The sums are capped by `PatientChargeAllocationService` when it writes: a
 * payment never allocates beyond its amount and a charge never receives beyond
 * its own. Legacy rows read as unallocated because no row exists for them.
 */
class PatientChargeAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_charge_id',
        'transaction_id',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function patientCharge(): BelongsTo
    {
        return $this->belongsTo(PatientCharge::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
