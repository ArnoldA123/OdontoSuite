<?php

namespace App\Models;

use App\Models\Concerns\ScopesToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #287 (T4, S5/S7) — the bank deposit of the drawer.
 *
 * The deposit is the operational record of money that left the gaveta towards
 * a bank account; its `withdrawal` cash movement is what keeps the session
 * expected amount true (D5). The row belongs to a sede: the drawer and its
 * reconciliation are operational surfaces (ADR-0010).
 */
class CashDeposit extends Model
{
    use HasFactory, ScopesToBranch;

    protected $fillable = [
        'branch_id',
        'cash_register_session_id',
        'cash_movement_id',
        'created_by',
        'amount',
        'deposited_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deposited_at' => 'datetime',
    ];

    // Relaciones
    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
