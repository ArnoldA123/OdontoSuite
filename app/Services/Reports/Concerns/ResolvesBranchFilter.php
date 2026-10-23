<?php

namespace App\Services\Reports\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * The sede filter of the Business Intelligence reports (#303, decision L2).
 *
 * A period report is attributed by the sede where the work happened, so each
 * surface answers with its own column: an appointment carries a sede, a chair
 * belongs to one, and money inherits the sede of the drawer that collected it.
 * The value is always the one the controller resolved with BranchAccess, so a
 * service can never widen the reach of a restricted user, and a null filter
 * means group scope and leaves every query untouched.
 */
trait ResolvesBranchFilter
{
    /**
     * The sede the report must answer, or null for the whole group.
     */
    protected function branchFilter(array $filters): ?int
    {
        $branchId = $filters['branch_id'] ?? null;

        return $branchId === null ? null : (int) $branchId;
    }

    /**
     * Narrow a surface that carries its own sede.
     */
    protected function filterBranch(Builder $query, string $column, ?int $branchId): Builder
    {
        return $branchId === null ? $query : $query->where($column, $branchId);
    }

    /**
     * Narrow money by the sede of the drawer that collected it.
     *
     * The join is LEFT so money without a drawer is a null branch instead of a
     * dropped row: naming a sede drops it, and the revenue cut publishes it in
     * the "Sin sede" row instead of losing it.
     */
    protected function filterByDrawerBranch(Builder $query, ?int $branchId): Builder
    {
        if ($branchId === null) {
            return $query;
        }

        return $query
            ->leftJoin('cash_register_sessions', 'cash_register_sessions.id', '=', 'transactions.cash_register_session_id')
            ->where('cash_register_sessions.branch_id', $branchId);
    }
}
