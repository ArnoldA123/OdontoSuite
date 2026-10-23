<?php

namespace App\Models;

use App\Authorization\BranchAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'treatment_plan_id',
        'quotation_id',
        'payment_method_id',
        'created_by',
        'cash_register_session_id',
        'transaction_number',
        'type',
        'amount',
        'subtotal',
        'discount_type',
        'discount_amount',
        'discount_authorized_by',
        'authorized_by',
        'authorization_reason',
        'parent_transaction_id',
        'tax_amount',
        'commission_amount',
        'description',
        'notes',
        'reference_number',
        'metadata',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
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

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    /**
     * #287, D2: the administrator who authorized a refund, a write-off or an
     * adjustment. The row records who asked and why, not just that it happened.
     */
    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    /**
     * #287, D2: the payment a refund gives money back from.
     */
    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    /**
     * Refunds charged against this payment.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    // Scopes
    /**
     * #283 S2/S3 — transactions carry no `branch_id`: the sede is the drawer's.
     *
     * A transaction without a cash register session belongs to no sede, so it is
     * group data and outside the reach of a restricted user, exactly like a row
     * with a null `branch_id` in the operational models.
     */
    public function scopeVisibleToBranch($query, ?User $user = null)
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! BranchAccess::isRestricted($user)) {
            return $query;
        }

        return $query->whereHas(
            'cashRegisterSession',
            fn ($q) => $q->where('branch_id', (int) $user->branch_id),
        );
    }

    /**
     * The sede this transaction belongs to, resolved through its drawer.
     */
    public function branchIdForScope(): ?int
    {
        $branchId = $this->cashRegisterSession?->branch_id;

        return $branchId === null ? null : (int) $branchId;
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
