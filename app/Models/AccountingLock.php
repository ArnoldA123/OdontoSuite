<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The accounting freeze date of one module (#283, S4/S5).
 *
 * A row without `branch_id` is the group-wide lock; a row with one is the
 * override for that sede. Resolution order lives in AccountingLockService:
 * branch row, then global row, then no lock.
 */
class AccountingLock extends Model
{
    public const SOURCE_BRANCH = 'branch';

    public const SOURCE_GLOBAL = 'global';

    public const SOURCE_NONE = 'none';

    protected $fillable = [
        'module',
        'branch_id',
        'locked_through',
        'set_by',
    ];

    protected $casts = [
        'locked_through' => 'date',
        'branch_id' => 'integer',
        'set_by' => 'integer',
    ];

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function source(): string
    {
        return $this->branch_id === null ? self::SOURCE_GLOBAL : self::SOURCE_BRANCH;
    }
}
