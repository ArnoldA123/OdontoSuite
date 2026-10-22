<?php

namespace App\Services;

use App\Models\ProcedureBranchPrice;
use App\Models\ProcedureCatalog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the price of a procedure for a sede (#290 S8, decision L6).
 *
 * Cascade: the branch row in force on the reference date, else the group list
 * price (`procedure_catalog.default_cost`). The result always names its source,
 * so a caller never has to guess whether a discount was applied. Callers write
 * the resolved price into the quotation or plan item (snapshot); they never
 * recalculate it later, which is what keeps a closed budget stable when the
 * tariff changes.
 */
class ProcedurePriceResolver
{
    public const SOURCE_BRANCH = 'branch';

    public const SOURCE_CATALOG = 'catalog';

    /**
     * @return array{price: float, source: string, branch_price_id: int|null, valid_from: string|null, valid_to: string|null}
     */
    public function resolve(ProcedureCatalog $procedure, ?int $branchId, ?CarbonInterface $on = null): array
    {
        return $this->resolveMany([$procedure], $branchId, $on)[$procedure->id]
            ?? $this->catalogPrice($procedure);
    }

    /**
     * One query for the whole page, so a picker of 100 procedures stays at one
     * lookup instead of one per row.
     *
     * @param  iterable<int, ProcedureCatalog>                                                                                            $procedures
     * @return array<int, array{price: float, source: string, branch_price_id: int|null, valid_from: string|null, valid_to: string|null}>
     */
    public function resolveMany(iterable $procedures, ?int $branchId, ?CarbonInterface $on = null): array
    {
        $resolved = [];

        /** @var array<int, ProcedureCatalog> $items */
        $items = [];

        foreach ($procedures as $procedure) {
            $items[$procedure->id] = $procedure;
            $resolved[$procedure->id] = $this->catalogPrice($procedure);
        }

        if ($branchId === null || $items === []) {
            return $resolved;
        }

        $date = ($on ?? Carbon::now())->toDateString();

        /** @var Collection<int, ProcedureBranchPrice> $prices */
        $prices = ProcedureBranchPrice::query()
            ->active()
            ->forBranch($branchId)
            ->inForceOn($date)
            ->whereIn('procedure_catalog_id', array_keys($items))
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->unique('procedure_catalog_id');

        foreach ($prices as $price) {
            $resolved[$price->procedure_catalog_id] = [
                'price' => round((float) $price->price, 2),
                'source' => self::SOURCE_BRANCH,
                'branch_price_id' => $price->id,
                'valid_from' => $price->valid_from?->toDateString(),
                'valid_to' => $price->valid_to?->toDateString(),
            ];
        }

        return $resolved;
    }

    /**
     * Resolve against the sede of the authenticated user (or the given one).
     *
     * @return array{price: float, source: string, branch_price_id: int|null, valid_from: string|null, valid_to: string|null}
     */
    public function resolveForUser(ProcedureCatalog $procedure, ?User $user = null, ?CarbonInterface $on = null): array
    {
        return $this->resolve($procedure, $this->branchOf($user), $on);
    }

    /**
     * @return array{price: float, source: string, branch_price_id: int|null, valid_from: string|null, valid_to: string|null}
     */
    private function catalogPrice(ProcedureCatalog $procedure): array
    {
        return [
            'price' => round((float) $procedure->default_cost, 2),
            'source' => self::SOURCE_CATALOG,
            'branch_price_id' => null,
            'valid_from' => null,
            'valid_to' => null,
        ];
    }

    /**
     * Sede of the authenticated user, for callers that resolve a page of prices.
     */
    public function currentBranchId(): ?int
    {
        return $this->branchOf(null);
    }

    private function branchOf(?User $user): ?int
    {
        $user ??= Auth::user();

        if (! $user instanceof User || $user->branch_id === null) {
            return null;
        }

        return (int) $user->branch_id;
    }
}
