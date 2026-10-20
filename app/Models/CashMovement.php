<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cash_register_session_id',
        'transaction_id',
        'created_by',
        'type',
        'amount',
        'description',
        'notes',
        'reference',
        'metadata',
        // Slice 02 / T-02.9 — optional branch association.
        'branch_id',
        'concept',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relaciones
    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reference this application stamps on the opening snapshot of a session.
     */
    public static function openingReference(int $sessionId): string
    {
        return 'OPEN-'.str_pad((string) $sessionId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Reference this application stamps on the closing snapshot of a session.
     */
    public static function closingReference(int $sessionId): string
    {
        return 'CLOSE-'.$sessionId;
    }

    /**
     * A drawer snapshot mirrors `opening_amount` or `closing_amount`: it is not
     * a cash flow, so the ledger excludes it and no edit may turn it into one.
     */
    public function isDrawerSnapshot(): bool
    {
        if ($this->transaction_id !== null) {
            return false;
        }

        $sessionId = (int) $this->cash_register_session_id;

        return in_array($this->reference, [
            self::openingReference($sessionId),
            self::closingReference($sessionId),
        ], true);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
