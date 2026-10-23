<?php

namespace App\Models;

use App\Authorization\BranchAccess;
use App\Authorization\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #305 — the request to move a user between sedes.
 *
 * `users.branch_id` is the affiliation (ADR-0010) and `null` is group scope.
 * This row copies that affiliation when the user asks (`from_branch_id`) and
 * names the sede asked for (`to_branch_id`); only an approved request writes
 * `users.branch_id`. While the request is `pending` nothing changes: the user
 * keeps operating in the sede they already have.
 *
 * The entity has no `branch_id` of its own, so `ScopesToBranch` does not apply:
 * a request has two sedes (the one it leaves and the one it enters) and its
 * visibility is the union of both, which `scopeVisibleTo()` expresses.
 */
class BranchTransferRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'from_branch_id',
        'to_branch_id',
        'status',
        'reason',
        'requested_at',
        'decided_at',
        'decided_by',
        'decision_notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), self::STATUS_PENDING);
    }

    /**
     * The rows a user may read (S5).
     *
     *  - gerencia (group scope with the decision grant) reads every request;
     *  - a sede administrator reads the ones entering or leaving their sede,
     *    plus their own;
     *  - everyone else reads only their own.
     *
     * This scope answers reads. The decision doors check the same rule to
     * answer 403 instead of the 404 a read would get (S10).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (BranchAccess::isGlobal($user)) {
            return $query;
        }

        if ($user->hasPermission(Permissions::DECIDE_BRANCH_TRANSFER) && BranchAccess::isRestricted($user)) {
            $branchId = (int) $user->branch_id;

            return $query->where(function (Builder $visible) use ($user, $branchId): void {
                $visible->where('user_id', $user->id)
                    ->orWhere('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId);
            });
        }

        return $query->where('user_id', $user->id);
    }
}
