<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\Transaction;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    use ResolvesReportPeriod;

    private const SUMMARY_GROUP = 'Sin cita asociada';

    /**
     * Revenue report.
     *
     * Money means collected payments (D1) and each payment lands in exactly one
     * row of every cut, so the column adds up to the summary instead of double
     * counting the clinic total (D8).
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $totalRevenue = (float) $this->revenueQuery($startDate, $endDate, $professionalId, $environmentId)->sum('transactions.amount');
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
                $data[] = [
                    'type' => $type,
                    'category' => $row->category,
                    'appointments_count' => (int) $row->appointments_count,
                    'total_revenue' => round((float) $row->total_revenue, 2),
                    'average_per_appointment' => $row->appointments_count > 0
                        ? round((float) $row->total_revenue / (int) $row->appointments_count, 2)
                        : 0,
                ];
            }
        }

        // Residual: payments whose appointment no longer resolves (missing or
        // soft-deleted). Each cut above adds up to the attributed total on its
        // own, so the unlinked money is one extra group, never subtracted from a
        // cut that already balances.
        $attributed = (float) $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->sum('transactions.amount');
        $residual = round($totalRevenue - $attributed, 2);

        if (abs($residual) >= 0.01) {
            $data[] = [
                'type' => self::SUMMARY_GROUP,
                'category' => 'Pagos sin cita resoluble',
                'appointments_count' => 0,
                'total_revenue' => $residual,
                'average_per_appointment' => 0,
            ];
        }

        return [
            'data' => $data,
            'columns' => [
                ['key' => 'type', 'label' => 'Tipo'],
                ['key' => 'category', 'label' => 'Categoría'],
                ['key' => 'appointments_count', 'label' => 'Número de Citas'],
                ['key' => 'total_revenue', 'label' => 'Ingresos Cobrados (S/)'],
                ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
            ],
            'summary' => [
                'appointments_count' => $completedAppointments,
                'total_revenue' => round($totalRevenue, 2),
                'average_per_appointment' => $completedAppointments > 0
                    ? round($totalRevenue / $completedAppointments, 2)
                    : 0,
                'period' => $this->periodLabel($startDate, $endDate),
            ],
        ];
    }

    /**
     * Completed payments inside the period.
     */
    private function baseRevenueQuery(Carbon $startDate, Carbon $endDate): Builder
    {
        return Transaction::query()
            ->where('transactions.type', 'payment')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate, $endDate]);
    }

    private function revenueQuery(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): Builder
    {
        $query = $this->baseRevenueQuery($startDate, $endDate);

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
        return $this->baseRevenueQuery($startDate, $endDate)
            ->join('appointments', 'appointments.id', '=', 'transactions.appointment_id')
            ->when($professionalId, fn (Builder $q) => $q->where('appointments.user_id', $professionalId))
            ->when($environmentId, fn (Builder $q) => $q->where('appointments.dental_chair_id', $environmentId));
    }

    private function revenueByAppointmentType(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->join('appointment_types', 'appointment_types.id', '=', 'appointments.appointment_type_id')
            ->groupBy('appointment_types.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->select(
                'appointment_types.name as category',
                DB::raw('COUNT(DISTINCT transactions.appointment_id) as appointments_count'),
                DB::raw('SUM(transactions.amount) as total_revenue')
            )
            ->get()
            ->all();
    }

    private function revenueByProfessional(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->join('users', 'users.id', '=', 'appointments.user_id')
            ->groupBy('users.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->select(
                'users.name as category',
                DB::raw('COUNT(DISTINCT transactions.appointment_id) as appointments_count'),
                DB::raw('SUM(transactions.amount) as total_revenue')
            )
            ->get()
            ->all();
    }

    private function revenueByEnvironment(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->join('dental_chairs', 'dental_chairs.id', '=', 'appointments.dental_chair_id')
            ->groupBy('dental_chairs.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->select(
                'dental_chairs.name as category',
                DB::raw('COUNT(DISTINCT transactions.appointment_id) as appointments_count'),
                DB::raw('SUM(transactions.amount) as total_revenue')
            )
            ->get()
            ->all();
    }

    private function revenueByDay(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        return $this->joinedRevenueQuery($startDate, $endDate, $professionalId, $environmentId)
            ->groupBy(DB::raw('DATE(transactions.created_at)'))
            ->orderBy(DB::raw('DATE(transactions.created_at)'))
            ->select(
                DB::raw('DATE(transactions.created_at) as category'),
                DB::raw('COUNT(DISTINCT transactions.appointment_id) as appointments_count'),
                DB::raw('SUM(transactions.amount) as total_revenue')
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
