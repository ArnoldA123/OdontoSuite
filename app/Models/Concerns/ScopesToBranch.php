<?php

namespace App\Models\Concerns;

use App\Authorization\BranchAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The operational branch dimension of a model (#283 S2/S3, #290 S6).
 *
 * Three hooks, one rule:
 *
 *  - `saving`: a restricted user may only write rows of their own sede. A new
 *    row without `branch_id` is stamped with the actor's sede — an appointment
 *    booked by reception belongs to reception's sede — and a row of another sede
 *    is rejected with 403. Rows saved with no authenticated user (console,
 *    queue, factories, seeders) are not guarded.
 *  - `deleting`: the same rule for the row being removed.
 *  - `resolveRouteBindingQuery`: route-model binding is scoped, so another
 *    sede's record answers 404 instead of leaking through `show`, `update` or
 *    `destroy` on any controller. Reads that list rows apply
 *    `visibleToBranch()`.
 *
 * Rows with a null `branch_id` predate this rule. They are data-integrity debt
 * and stay writable by group scope only; a restricted user never creates a new
 * one, because creation stamps their sede.
 *
 * The patient file deliberately does not use this trait: the patient, the
 * clinical history and the treatment plans are shared across sedes (S9).
 */
trait ScopesToBranch
{
    public static function bootScopesToBranch(): void
    {
        static::saving(function ($model): void {
            $user = Auth::user();

            if (! $user instanceof User || ! BranchAccess::isRestricted($user)) {
                return;
            }

            $branchId = $model->getAttribute('branch_id');
            $branchId = $branchId === null ? null : (int) $branchId;

            if (! $model->exists) {
                if ($branchId === null) {
                    $model->setAttribute('branch_id', (int) $user->branch_id);

                    return;
                }

                BranchAccess::assertBranch($user, $branchId);

                return;
            }

            if ($model->isDirty('branch_id')) {
                BranchAccess::assertBranch($user, $branchId);

                return;
            }

            // Legacy rows without a sede stay untouched by the rule; rows with
            // one are only writable from inside it.
            if ($branchId !== null) {
                BranchAccess::assertBranch($user, $branchId);
            }
        });

        static::deleting(function ($model): void {
            $user = Auth::user();

            if ($user instanceof User && $model->getAttribute('branch_id') !== null) {
                BranchAccess::assertBranch($user, (int) $model->getAttribute('branch_id'));
            }
        });
    }

    /**
     * Scope the rows a user may read.
     */
    public function scopeVisibleToBranch(Builder $query, ?User $user = null): Builder
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! BranchAccess::isRestricted($user)) {
            return $query;
        }

        return $query->where($query->qualifyColumn('branch_id'), (int) $user->branch_id);
    }

    /**
     * Route-model binding honours the same scope, so a foreign row is 404.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->visibleToBranch(Auth::user());
    }
}
