<?php

namespace App\Services;

use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the price of a procedure for a sede and/or a convenio (#290 S8,
 * #293 S3/S5).
 *
 * Cascade, every row active and in force on the reference date: convenio+sede,
 * convenio only (group scope), sede only, then the catalog list price. Within
 * the winning specificity the latest `valid_from` wins (an open start sorts
 * last), then the highest id. The result always names its source and the row
 * that applied, so a caller never has to guess whether a discount was applied.
 * Callers write the resolved price into the quotation or plan item (snapshot);
 * they never recalculate it later, which is what keeps a closed budget stable
 * when the tariff changes.
 */
class ProcedurePriceResolver
{
    public const SOURCE_PAYER_BRANCH = 'payer_branch';

    public const SOURCE_PAYER = 'payer';

    public const SOURCE_BRANCH = 'branch';

    public const SOURCE_CATALOG = 'catalog';

    /**
     * @return array{price: float, source: string, price_id: int|null, valid_from: string|null, valid_to: string|null}
     */
    public function resolve(
        ProcedureCatalog $procedure,
        ?int $branchId,
        ?CarbonInterface $on = null,
        ?int $payerId = null,
    ): array {
        return $this->resolveMany([$procedure], $branchId, $on, $payerId)[$procedure->id]
            ?? $this->catalogPrice($procedure);
    }

    /**
     * One query for the whole page, so a picker of 100 procedures stays at one
     * lookup instead of one per row.
     *
     * @param  iterable<int, ProcedureCatalog>                                                                                     $procedures
     * @return array<int, array{price: float, source: string, price_id: int|null, valid_from: string|null, valid_to: string|null}>
     */
    public function resolveMany(
        iterable $procedures,
        ?int $branchId,
        ?CarbonInterface $on = null,
        ?int $payerId = null,
    ): array {
        $resolved = [];

        /** @var array<int, ProcedureCatalog> $items */
        $items = [];

        foreach ($procedures as $procedure) {
            $items[$procedure->id] = $procedure;
            $resolved[$procedure->id] = $this->catalogPrice($procedure);
        }

        if ($items === [] || ($branchId === null && $payerId === null)) {
            return $resolved;
        }

        $date = ($on ?? Carbon::now())->toDateString();

        $prices = ProcedurePrice::query()
            ->active()
            ->inForceOn($date)
            ->whereIn('procedure_catalog_id', array_keys($items))
            ->where(function ($query) use ($branchId, $payerId) {
                if ($branchId !== null) {
                    $query->orWhere('branch_id', $branchId);
                }

                if ($payerId !== null) {
                    $query->orWhere('payer_id', $payerId);
                }
            })
            ->get();

        /** @var array<int, ProcedurePrice> $winning */
        $winning = [];
        /** @var array<int, int> $winningSpecificity */
        $winningSpecificity = [];

        foreach ($prices as $price) {
            $specificity = $this->specificity($price, $branchId, $payerId);

            if ($specificity === null) {
                continue;
            }

            $procedureId = $price->procedure_catalog_id;
            $current = $winning[$procedureId] ?? null;

            if ($current !== null && ! $this->outranks(
                $price,
                $specificity,
                $current,
                $winningSpecificity[$procedureId],
            )) {
                continue;
            }

            $winning[$procedureId] = $price;
            $winningSpecificity[$procedureId] = $specificity;
        }

        foreach ($winning as $procedureId => $price) {
            $resolved[$procedureId] = [
                'price' => round((float) $price->price, 2),
                'source' => $this->sourceOf($winningSpecificity[$procedureId]),
                'price_id' => $price->id,
                'valid_from' => $price->valid_from?->toDateString(),
                'valid_to' => $price->valid_to?->toDateString(),
            ];
        }

        return $resolved;
    }

    /**
     * Resolve against the sede of the authenticated user (or the given one).
     *
     * @return array{price: float, source: string, price_id: int|null, valid_from: string|null, valid_to: string|null}
     */
    public function resolveForUser(
        ProcedureCatalog $procedure,
        ?User $user = null,
        ?CarbonInterface $on = null,
        ?int $payerId = null,
    ): array {
        return $this->resolve($procedure, $this->branchOf($user), $on, $payerId);
    }

    /**
     * Sede of the authenticated user, for callers that resolve a page of prices.
     */
    public function currentBranchId(): ?int
    {
        return $this->branchOf(null);
    }

    /**
     * How specific a row is for the requested dimensions, or null when it does
     * not match them at all (a row of another sede or another convenio).
     */
    private function specificity(ProcedurePrice $price, ?int $branchId, ?int $payerId): ?int
    {
        $matchesBranch = $branchId !== null && $price->branch_id === $branchId;
        $matchesPayer = $payerId !== null && $price->payer_id === $payerId;

        if ($matchesBranch && $matchesPayer) {
            return 3;
        }

        if ($matchesPayer && $price->branch_id === null) {
            return 2;
        }

        if ($matchesBranch && $price->payer_id === null) {
            return 1;
        }

        return null;
    }

    private function sourceOf(int $specificity): string
    {
        return match ($specificity) {
            3 => self::SOURCE_PAYER_BRANCH,
            2 => self::SOURCE_PAYER,
            default => self::SOURCE_BRANCH,
        };
    }

    /**
     * A row beats the current pick when it is more specific, or equally
     * specific with a later start (an open start is the oldest window), or
     * equally specific and equally open with a higher id.
     */
    private function outranks(
        ProcedurePrice $candidate,
        int $candidateSpecificity,
        ProcedurePrice $current,
        int $currentSpecificity,
    ): bool {
        if ($candidateSpecificity !== $currentSpecificity) {
            return $candidateSpecificity > $currentSpecificity;
        }

        $start = $candidate->valid_from?->toDateString();
        $currentStart = $current->valid_from?->toDateString();

        if ($start === $currentStart) {
            return $candidate->id > $current->id;
        }

        if ($start === null) {
            return false;
        }

        if ($currentStart === null) {
            return true;
        }

        return $start > $currentStart;
    }

    /**
     * @return array{price: float, source: string, price_id: int|null, valid_from: string|null, valid_to: string|null}
     */
    private function catalogPrice(ProcedureCatalog $procedure): array
    {
        return [
            'price' => round((float) $procedure->default_cost, 2),
            'source' => self::SOURCE_CATALOG,
            'price_id' => null,
            'valid_from' => null,
            'valid_to' => null,
        ];
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
