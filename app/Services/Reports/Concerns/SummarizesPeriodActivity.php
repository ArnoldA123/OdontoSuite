<?php

namespace App\Services\Reports\Concerns;

use Illuminate\Support\Collection;

/**
 * Period activity of the tabular Business Intelligence reports (#447).
 *
 * Patients, professionals and utilization publish their whole population and
 * only bound the counters by the period, so the screen cannot learn whether the
 * period had activity from the row count. Each service sums the rows it already
 * materialized instead of issuing a second aggregate: `appointments` counts the
 * period's appointments and `revenue` is the net money those rows already carry.
 */
trait SummarizesPeriodActivity
{
    /**
     * @param  Collection<int, array<string, mixed>>    $rows
     * @return array{appointments: int, revenue: float}
     */
    protected function periodActivity(Collection $rows, string $revenueKey = 'total_revenue'): array
    {
        return [
            'appointments' => (int) $rows->sum('total_appointments'),
            'revenue' => round((float) $rows->sum($revenueKey), 2),
        ];
    }

    /**
     * A report with no rows still answers a zero summary, never a missing one.
     *
     * @return array{appointments: int, revenue: float}
     */
    protected function zeroPeriodActivity(): array
    {
        return ['appointments' => 0, 'revenue' => 0.0];
    }
}
