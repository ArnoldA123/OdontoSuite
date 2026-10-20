<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    use ResolvesReportPeriod;

    /**
     * Professional roles that attend appointments. Shared with the
     * professionals report so both surfaces describe the same population.
     */
    public const PROFESSIONAL_ROLES = ['odontologo', 'implantologo', 'dentista', 'tecnico_dental', 'asistente'];

    /**
     * Get dashboard data.
     *
     * @param bool $useCache false when a caller needs the applied period
     *                       fresh (the BI report); the dashboard keeps the
     *                       short TTL cache
     */
    public function getDashboardData(array $filters = [], bool $useCache = true): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters, 30, 60);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $cacheKey = 'dashboard_data_'.md5(serialize([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'professional_id' => $professionalId,
            'environment_id' => $environmentId,
        ]));

        $build = function () use ($startDate, $endDate, $professionalId, $environmentId) {
            return [
                'totalAppointments' => $this->getTotalAppointments($startDate, $endDate, $professionalId, $environmentId),
                'totalPatients' => $this->getTotalPatients($startDate, $endDate, $professionalId, $environmentId),
                'newPatients' => $this->getNewPatients($startDate, $endDate),
                'totalRevenue' => $this->getTotalRevenue($startDate, $endDate, $professionalId, $environmentId),
                'occupancyRate' => $this->getOccupancyRate($startDate, $endDate, $professionalId, $environmentId),
                'usedHours' => round($this->getUsedHours($startDate, $endDate, $professionalId, $environmentId), 2),
                'availableHours' => $this->getTotalAvailableHours($startDate, $endDate, $environmentId),
                'appointmentsByDay' => $this->getAppointmentsByDay($startDate, $endDate, $professionalId, $environmentId),
                'revenueByMonth' => $this->getRevenueByMonth($startDate, $endDate, $professionalId, $environmentId),
                'professionalPerformance' => $this->getProfessionalPerformance($startDate, $endDate, $professionalId, $environmentId),
            ];
        };

        return $useCache ? Cache::remember($cacheKey, 300, $build) : $build();
    }

    /**
     * Clear dashboard cache.
     *
     * Entries expire by TTL; a flush here would invalidate unrelated caches.
     */
    public function clearCache(): void
    {
        // Intentionally empty: see class-level note above.
    }

    /**
     * Clear cache for specific filters.
     */
    public function clearCacheForFilters(array $filters = []): void
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters, 30, 60);

        $cacheKey = 'dashboard_data_'.md5(serialize([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'professional_id' => $filters['professional_id'] ?? null,
            'environment_id' => $filters['environment_id'] ?? null,
        ]));

        Cache::forget($cacheKey);
    }

    /**
     * Get report data for export: KPIs, both time series and the table.
     */
    public function getReportData(array $filters = []): array
    {
        $dashboardData = $this->getDashboardData($filters, false);

        $columns = [
            ['key' => 'section', 'label' => 'Sección'],
            ['key' => 'concept', 'label' => 'Concepto'],
            ['key' => 'value', 'label' => 'Valor'],
        ];

        $data = [];

        $kpis = [
            'Citas del período' => $dashboardData['totalAppointments'],
            'Pacientes atendidos' => $dashboardData['totalPatients'],
            'Pacientes nuevos' => $dashboardData['newPatients'],
            'Ingresos cobrados (S/)' => number_format($dashboardData['totalRevenue'], 2, '.', ''),
            'Horas ocupadas' => number_format($dashboardData['usedHours'], 2, '.', ''),
            'Horas disponibles' => number_format($dashboardData['availableHours'], 2, '.', ''),
            'Tasa de ocupación (%)' => $dashboardData['occupancyRate'],
        ];

        foreach ($kpis as $concept => $value) {
            $data[] = ['section' => 'Resumen', 'concept' => $concept, 'value' => $value];
        }

        foreach ($dashboardData['appointmentsByDay'] as $day) {
            $data[] = ['section' => 'Citas por día', 'concept' => $day['date'], 'value' => $day['count']];
        }

        foreach ($dashboardData['revenueByMonth'] as $month) {
            $data[] = [
                'section' => 'Ingresos por mes',
                'concept' => $month['month'],
                'value' => number_format($month['revenue'], 2, '.', ''),
            ];
        }

        foreach ($dashboardData['professionalPerformance'] as $professional) {
            $data[] = [
                'section' => 'Rendimiento por profesional',
                'concept' => $professional['name'],
                'value' => $professional['appointments'].' citas / S/ '
                    .number_format($professional['revenue'], 2, '.', ''),
            ];
        }

        return [
            'data' => $data,
            'columns' => $columns,
        ];
    }

    /**
     * Appointments scheduled inside the period, every status.
     */
    private function getTotalAppointments(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): int
    {
        $query = Appointment::whereBetween('scheduled_at', [$startDate, $endDate]);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        return $query->count();
    }

    /**
     * Distinct patients seen inside the period (D6).
     */
    private function getTotalPatients(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): int
    {
        return Patient::whereHas('appointments', function ($q) use ($startDate, $endDate, $professionalId, $environmentId) {
            $q->whereBetween('scheduled_at', [$startDate, $endDate]);

            if ($professionalId) {
                $q->where('user_id', $professionalId);
            }

            if ($environmentId) {
                $q->where('dental_chair_id', $environmentId);
            }
        })->count();
    }

    /**
     * Patients registered inside the period.
     */
    private function getNewPatients(Carbon $startDate, Carbon $endDate): int
    {
        return Patient::whereBetween('created_at', [$startDate, $endDate])->count();
    }

    /**
     * Money actually collected in the period (D1).
     */
    private function getTotalRevenue(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $query = Transaction::where('type', 'payment')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

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

        return (float) $query->sum('amount');
    }

    /**
     * Get occupancy rate.
     */
    private function getOccupancyRate(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $totalHours = $this->getTotalAvailableHours($startDate, $endDate, $environmentId);
        $usedHours = $this->getUsedHours($startDate, $endDate, $professionalId, $environmentId);

        if ($totalHours <= 0) {
            return 0;
        }

        return round(($usedHours / $totalHours) * 100, 2);
    }

    /**
     * Physical availability: active chairs times working days (D4).
     */
    private function getTotalAvailableHours(Carbon $startDate, Carbon $endDate, ?int $environmentId): float
    {
        $chairs = $environmentId
            ? DentalChair::where('id', $environmentId)->count()
            : DentalChair::where('is_active', true)->count();

        return $this->availableHours($startDate, $endDate, $chairs);
    }

    /**
     * Hours the chairs were actually reserved: no cancelled, no no-show (D5).
     */
    private function getUsedHours(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $query = Appointment::whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'no_show']);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        return (float) $query->sum('duration_minutes') / 60;
    }

    /**
     * Appointments per day for the period chart.
     */
    private function getAppointmentsByDay(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Appointment::select(
            DB::raw('DATE(scheduled_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(scheduled_at)'))
            ->orderBy('date');

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        return $query->get()->map(function ($item) {
            return [
                'date' => is_string($item->date) ? $item->date : Carbon::parse($item->date)->format('Y-m-d'),
                'count' => (int) $item->count,
            ];
        })->toArray();
    }

    /**
     * Collected revenue per month for the period chart.
     */
    private function getRevenueByMonth(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Transaction::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('SUM(amount) as revenue')
        )
            ->where('type', 'payment')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->orderBy('year')
            ->orderBy('month');

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

        return $query->get()->map(function ($item) {
            return [
                'month' => Carbon::create($item->year, $item->month)->format('M Y'),
                'revenue' => round((float) $item->revenue, 2),
            ];
        })->toArray();
    }

    /**
     * Per-professional performance.
     *
     * Revenue is attributed once (D2): only payments linked through
     * `appointment_id` to an appointment of that professional. Payments without
     * an appointment stay in the clinic total and are never redistributed, so
     * the sum of this column cannot exceed the clinic's collected revenue.
     */
    private function getProfessionalPerformance(Carbon $startDate, Carbon $endDate, ?int $professionalId, ?int $environmentId): array
    {
        // No is_active filter: a professional who worked in the period appears
        // even after being deactivated, and the population matches the
        // professionals report exactly (D7).
        $query = User::whereIn('role', self::PROFESSIONAL_ROLES);

        if ($professionalId) {
            $query->where('id', $professionalId);
        }

        $professionals = $query->orderBy('name')->get();

        if ($professionals->isEmpty()) {
            return [];
        }

        $ids = $professionals->pluck('id')->all();

        $appointmentsByProfessional = Appointment::select('user_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereIn('user_id', $ids)
            ->when($environmentId, fn ($q) => $q->where('dental_chair_id', $environmentId))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $revenueByProfessional = Transaction::query()
            ->join('appointments', 'appointments.id', '=', 'transactions.appointment_id')
            ->where('transactions.type', 'payment')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->whereIn('appointments.user_id', $ids)
            ->when($environmentId, fn ($q) => $q->where('appointments.dental_chair_id', $environmentId))
            ->groupBy('appointments.user_id')
            ->select('appointments.user_id', DB::raw('SUM(transactions.amount) as revenue'))
            ->pluck('revenue', 'user_id');

        return $professionals->map(function ($professional) use ($appointmentsByProfessional, $revenueByProfessional) {
            $appointmentCount = (int) ($appointmentsByProfessional[$professional->id] ?? 0);
            $revenue = (float) ($revenueByProfessional[$professional->id] ?? 0);

            return [
                'id' => $professional->id,
                'name' => $professional->name,
                'appointments' => $appointmentCount,
                'revenue' => round($revenue, 2),
                'averagePerAppointment' => $appointmentCount > 0 ? round($revenue / $appointmentCount, 2) : 0,
            ];
        })->toArray();
    }
}
