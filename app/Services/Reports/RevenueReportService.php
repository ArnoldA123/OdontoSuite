<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Services\Reports\Concerns\ResolvesMoneyTotals;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    use ResolvesMoneyTotals;
    use ResolvesReportPeriod;

    private const SUMMARY_GROUP = 'Sin cita asociada';

    /**
     * Revenue report.
     *
     * Money is net (D1, S4): completed payments minus completed refunds. Each
     * movement lands in exactly one row of every cut, so the columns add up to
     * the summary instead of double counting the clinic total (D8).
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $totals = $this->moneyTotals($this->revenueQuery($startDate, $endDate, $professionalId, $environmentId));
        $completedAppointments = $this->completedAppointments($startDate, $endDate, $professionalId, $environmentId);

        $data = [];

        $cuts = [
            'Por Tipo de Cita' => $this->revenueByAppointmentType($startDate, $endDate, $professionalId, $environmentId),
            'Por Profesional' => $this->revenueByProfessional($startDate, $endDate, $professionalId, $environmentId),
            'Por Ambiente' => $this->revenueByEnvironment($startDate, $endDate, $professionalId, $environmentId),
            'Por Día' => $this->revenueByDay($startDate, $endDate, $professionalId, $environmentId),
        ];

        foreach ($cuts as $type => $rows) {
            foreach ($rows as $row) {
                $appointments = (int) $row->appointments_count;
                $net = round((float) $row->total_revenue, 2);

                $data[] = [
                    'type' => $type,
                    'category' => $row->category,
                    'appointments_count' => $appointments,
                    'total_revenue' => $net,
                    'refunds' => round((float) $row->refunds, 2),
                    'average_per_appointment' => $appointments > 0
                        ? round($net / $appointments, 2)
                        : 0,
                ];
            }
        }

        // Residual: money whose appointment no longer resolves (missing or
        // soft-deleted). Each cut above adds up to the attributed total on its
        // own, so the unlinked money is one extra group, never subtracted from a
        // cut that already balances. Refunds follow the same key as payments,
        // so they can land here too.
        $attributed = $this->moneyTotals($this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId));
        $residualNet = round($totals['net'] - $attributed['net'], 2);
        $residualRefunds = round($totals['refunds'] - $attributed['refunds'], 2);

        if (abs($residualNet) >= 0.01 || abs($residualRefunds) >= 0.01) {
            $data[] = [
                'type' => self::SUMMARY_GROUP,
                'category' => 'Movimientos sin cita resoluble',
                'appointments_count' => 0,
                'total_revenue' => $residualNet,
                'refunds' => $residualRefunds,
                'average_per_appointment' => 0,
            ];
        }

        return [
            'data' => $data,
            'columns' => [
                ['key' => 'type', 'label' => 'Tipo'],
                ['key' => 'category', 'label' => 'Categoría'],
                ['key' => 'appointments_count', 'label' => 'Número de Citas'],
                ['key' => 'total_revenue', 'label' => 'Ingresos netos (S/)'],
                ['key' => 'refunds', 'label' => 'Devoluciones (S/)'],
                ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
            ],
            'summary' => [
                'appointments_count' => $completedAppointments,
                'total_revenue' => $totals['net'],
                'refunds' => $totals['refunds'],
                'average_per_appointment' => $completedAppointments > 0
                    ? round($totals['net'] / $completedAppointments, 2)
                    : 0,
                'period' => $this->periodLabel($startDate, $endDate),
            ],
        ];
    }

    private function revenueQuery(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): Builder
    {
        $query = $this->moneyTransactions($startDate, $endDate);

        if ($professionalId || $environmentId) {
            $query->whereHas('appointment', function ($subQ) use ($professionalId, $environmentId) {
                if ($professionalId) {
                    $subQ->where('user_id', $professionalId);
                }

                if ($environmentId) {
                    $subQ->where('dental_chair_id', $environmentId);
                }
            });
        }

        return $query;
    }

    private function joinedRevenueQuery(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): Builder
    {
        return $this->moneyTransactions($startDate, $endDate)
            ->join('appointments', 'appointments.id', '=', 'transactions.appointment_id')
            ->when($professionalId, fn (Builder $q) => $q->where('appointments.user_id', $professionalId))
            ->when($environmentId, fn (Builder $q) => $q->where('appointments.dental_chair_id', $environmentId));
    }

    private function revenueByAppointmentType(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->join('appointment_types', 'appointment_types.id', '=', 'appointments.appointment_type_id')
            ->groupBy('appointment_types.name')
            ->orderByDesc(DB::raw($this->netTotalsSql()))
            ->select(
                'appointment_types.name as category',
                DB::raw($this->paidAppointmentsSql().' as appointments_count'),
                DB::raw($this->netTotalsSql().' as total_revenue'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->get()
            ->all();
    }

    private function revenueByProfessional(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->join('users', 'users.id', '=', 'appointments.user_id')
            ->groupBy('users.name')
            ->orderByDesc(DB::raw($this->netTotalsSql()))
            ->select(
                'users.name as category',
                DB::raw($this->paidAppointmentsSql().' as appointments_count'),
                DB::raw($this->netTotalsSql().' as total_revenue'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->get()
            ->all();
    }

    private function revenueByEnvironment(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->join('dental_chairs', 'dental_chairs.id', '=', 'appointments.dental_chair_id')
            ->groupBy('dental_chairs.name')
            ->orderByDesc(DB::raw($this->netTotalsSql()))
            ->select(
                'dental_chairs.name as category',
                DB::raw($this->paidAppointmentsSql().' as appointments_count'),
                DB::raw($this->netTotalsSql().' as total_revenue'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->get()
            ->all();
    }

    private function revenueByDay(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        // The day cut feeds a time series, so it keeps chronological order;
        // the other cuts rank by money.
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->groupBy(DB::raw('DATE(transactions.created_at)'))
            ->orderBy(DB::raw('DATE(transactions.created_at)'))
            ->select(
                DB::raw('DATE(transactions.created_at) as category'),
                DB::raw($this->paidAppointmentsSql().' as appointments_count'),
                DB::raw($this->netTotalsSql().' as total_revenue'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->get()
            ->all();
    }

    /**
     * Completed appointments inside the period, the denominator of the average.
     */
    private function completedAppointments(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): int
    {
        $query = Appointment::whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'completed');

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        return $query->count();
    }
}
