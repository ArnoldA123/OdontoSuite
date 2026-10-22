<?php

namespace App\Services\Reports;

use App\Services\Reports\Concerns\ResolvesMoneyTotals;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consolidated revenue per sede and per professional (#290 S7).
 *
 * One population — completed payments minus completed refunds inside the period
 * — projected onto two cuts that carry the same money, so each cut adds up to
 * `summary` exactly: every transaction lands in exactly one row of each cut
 * because both joins are LEFT JOINs and the "sin sede" / "sin cita" rows absorb
 * the records that do not resolve. That is the invariant the acceptance
 * criterion asks for: «el reporte consolidado cuadra con la suma de las sedes».
 */
class ConsolidatedReportService
{
    use ResolvesMoneyTotals;
    use ResolvesReportPeriod;

    public const NO_BRANCH = 'Sin sede';

    public const NO_PROFESSIONAL = 'Sin cita asociada';

    /**
     * @param  array{start_date?: string, end_date?: string, branch_id?: int|null} $filters
     * @return array<string, mixed>
     */
    public function getReportData(array $filters = []): array
    {
        [$start, $end] = $this->resolvePeriod($filters);
        $branchId = $filters['branch_id'] ?? null;
        $branchId = $branchId === null ? null : (int) $branchId;

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'branch_id' => $branchId,
                'label' => $this->periodLabel($start, $end),
            ],
            'summary' => $this->totals($start, $end, $branchId),
            'by_branch' => array_map(
                fn ($row) => [
                    'branch_id' => $row->branch_id === null ? null : (int) $row->branch_id,
                    'branch' => $row->branch_name ?? self::NO_BRANCH,
                    'net' => round((float) $row->net, 2),
                    'refunds' => round((float) $row->refunds, 2),
                    'paid_appointments' => (int) $row->paid_appointments,
                ],
                $this->cut($start, $end, $branchId, 'branch'),
            ),
            'by_professional' => array_map(
                fn ($row) => [
                    'professional_id' => $row->professional_id === null ? null : (int) $row->professional_id,
                    'professional' => $row->professional_name ?? self::NO_PROFESSIONAL,
                    'net' => round((float) $row->net, 2),
                    'refunds' => round((float) $row->refunds, 2),
                    'paid_appointments' => (int) $row->paid_appointments,
                ],
                $this->cut($start, $end, $branchId, 'professional'),
            ),
        ];
    }

    /**
     * @return array{net: float, refunds: float, paid_appointments: int}
     */
    private function totals(\Carbon\Carbon $start, \Carbon\Carbon $end, ?int $branchId): array
    {
        $row = $this->baseQuery($start, $end, $branchId)
            ->select(
                DB::raw($this->netTotalsSql().' as net'),
                DB::raw($this->refundTotalsSql().' as refunds'),
                DB::raw($this->paidAppointmentsSql().' as paid_appointments'),
            )
            ->first();

        return [
            'net' => round((float) ($row->net ?? 0), 2),
            'refunds' => round((float) ($row->refunds ?? 0), 2),
            'paid_appointments' => (int) ($row->paid_appointments ?? 0),
        ];
    }

    /**
     * @return array<int, object>
     */
    private function cut(\Carbon\Carbon $start, \Carbon\Carbon $end, ?int $branchId, string $by): array
    {
        $select = [
            DB::raw($this->netTotalsSql().' as net'),
            DB::raw($this->refundTotalsSql().' as refunds'),
            DB::raw($this->paidAppointmentsSql().' as paid_appointments'),
        ];
        $groupBy = [];

        if ($by === 'branch') {
            $select[] = 'cash_register_sessions.branch_id as branch_id';
            $select[] = 'branches.name as branch_name';
            $groupBy = ['cash_register_sessions.branch_id', 'branches.name'];
        } else {
            $select[] = 'appointments.user_id as professional_id';
            $select[] = 'users.name as professional_name';
            $groupBy = ['appointments.user_id', 'users.name'];
        }

        return $this->baseQuery($start, $end, $branchId)
            ->select($select)
            ->groupBy($groupBy)
            ->orderByDesc(DB::raw($this->netTotalsSql()))
            ->get()
            ->all();
    }

    /**
     * The one population of the report. LEFT JOINs keep money that does not
     * resolve to a sede or to an appointment inside the totals, which is what
     * makes both cuts add up to the summary.
     */
    private function baseQuery(\Carbon\Carbon $start, \Carbon\Carbon $end, ?int $branchId): Builder
    {
        return $this->moneyTransactions($start, $end)
            ->leftJoin('cash_register_sessions', 'cash_register_sessions.id', '=', 'transactions.cash_register_session_id')
            ->leftJoin('branches', 'branches.id', '=', 'cash_register_sessions.branch_id')
            ->leftJoin('appointments', 'appointments.id', '=', 'transactions.appointment_id')
            ->leftJoin('users', 'users.id', '=', 'appointments.user_id')
            ->when($branchId, fn (Builder $q) => $q->where('cash_register_sessions.branch_id', $branchId));
    }
}
