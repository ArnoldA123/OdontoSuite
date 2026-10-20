<?php

namespace App\Services\Reports\Concerns;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Shared period arithmetic for every report service.
 *
 * Decision D3 of odd/tasks/business-intelligence-ui-redesign.md: a report
 * period is inclusive on both ends. Comparing a datetime column against a bare
 * `YYYY-MM-DD` end date silently drops every record after midnight of the last
 * day, which is why every range here is [startOfDay, endOfDay].
 */
trait ResolvesReportPeriod
{
    /**
     * Working hours assumed for one chair in one working day.
     */
    protected int $hoursPerWorkingDay = 8;

    /**
     * Resolve the applied period, defaulting to the historical window.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolvePeriod(array $filters, int $daysBack = 30, int $daysForward = 0): array
    {
        $start = Carbon::parse($filters['start_date'] ?? Carbon::now()->subDays($daysBack)->toDateString())
            ->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? Carbon::now()->addDays($daysForward)->toDateString())
            ->endOfDay();

        return [$start, $end];
    }

    /**
     * Count Monday-to-Friday days inside the period, both ends included.
     */
    protected function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;

        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $day) {
            if ($day->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Physical availability for the period: real chairs times real working days.
     */
    protected function availableHours(Carbon $start, Carbon $end, int $chairs): float
    {
        return (float) ($this->workingDays($start, $end) * $this->hoursPerWorkingDay * max($chairs, 0));
    }

    /**
     * Human-readable period label for exports and summaries.
     */
    protected function periodLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('d/m/Y').' - '.$end->format('d/m/Y');
    }
}
