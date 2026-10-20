<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\DentalChair;
use App\Models\Transaction;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UtilizationReportService
{
    use ResolvesReportPeriod;

    /**
     * Chair utilization report.
     *
     * Availability is the chair's real working days (D4), the numerator counts
     * reservations that actually occupied the chair (D5), and revenue is money
     * collected against that chair's appointments (D1).
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $query = DentalChair::query();

        if ($environmentId) {
            $query->where('id', $environmentId);
        }

        $environments = $query->orderBy('name')->get();

        if ($environments->isEmpty()) {
            return ['data' => [], 'columns' => $this->columns()];
        }

        $ids = $environments->pluck('id')->all();

        $counters = Appointment::select(
            'dental_chair_id',
            DB::raw('COUNT(*) as total_appointments'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments"),
            DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments"),
            DB::raw("SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments"),
            DB::raw("SUM(CASE WHEN status NOT IN ('cancelled', 'no_show') THEN duration_minutes ELSE 0 END) as used_minutes")
        )
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereIn('dental_chair_id', $ids)
            ->when($professionalId, fn ($q) => $q->where('user_id', $professionalId))
            ->groupBy('dental_chair_id')
            ->get()
            ->keyBy('dental_chair_id');

        $revenueByChair = Transaction::query()
            ->join('appointments', 'appointments.id', '=', 'transactions.appointment_id')
            ->where('transactions.type', 'payment')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->whereIn('appointments.dental_chair_id', $ids)
            ->when($professionalId, fn ($q) => $q->where('appointments.user_id', $professionalId))
            ->groupBy('appointments.dental_chair_id')
            ->select('appointments.dental_chair_id', DB::raw('SUM(transactions.amount) as total_revenue'))
            ->pluck('total_revenue', 'dental_chair_id');

        $data = $environments->map(function ($environment) use ($counters, $revenueByChair, $startDate, $endDate) {
            $row = $counters[$environment->id] ?? null;
            $usedHours = (int) ($row->used_minutes ?? 0) / 60;
            $completed = (int) ($row->completed_appointments ?? 0);
            $totalRevenue = (float) ($revenueByChair[$environment->id] ?? 0);
            // D4: a chair that is not active contributes no physical
            // availability, even if it still has historical appointments.
            $availableHours = $environment->is_active
                ? $this->availableHours($startDate, $endDate, 1)
                : 0.0;

            return [
                'id' => $environment->id,
                'name' => $environment->name,
                'code' => $environment->code,
                'description' => $environment->description ?? 'Sin descripción',
                'status' => $this->getStatusText($environment->status),
                'total_appointments' => (int) ($row->total_appointments ?? 0),
                'completed_appointments' => $completed,
                'cancelled_appointments' => (int) ($row->cancelled_appointments ?? 0),
                'no_show_appointments' => (int) ($row->no_show_appointments ?? 0),
                'total_hours_used' => round($usedHours, 2),
                'total_hours_available' => round($availableHours, 2),
                'utilization_rate' => $availableHours > 0 ? round(($usedHours / $availableHours) * 100, 2) : 0,
                'total_revenue' => round($totalRevenue, 2),
                'average_per_appointment' => $completed > 0 ? round($totalRevenue / $completed, 2) : 0,
                'is_active' => $environment->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($environment->created_at)->format('d/m/Y H:i'),
            ];
        });

        return [
            'data' => $data->toArray(),
            'columns' => $this->columns(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function columns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Nombre'],
            ['key' => 'code', 'label' => 'Código'],
            ['key' => 'description', 'label' => 'Descripción'],
            ['key' => 'status', 'label' => 'Estado'],
            ['key' => 'total_appointments', 'label' => 'Total Citas'],
            ['key' => 'completed_appointments', 'label' => 'Citas Completadas'],
            ['key' => 'cancelled_appointments', 'label' => 'Citas Canceladas'],
            ['key' => 'no_show_appointments', 'label' => 'No se Presentaron'],
            ['key' => 'total_hours_used', 'label' => 'Horas Utilizadas'],
            ['key' => 'total_hours_available', 'label' => 'Horas Disponibles'],
            ['key' => 'utilization_rate', 'label' => 'Tasa de Utilización (%)'],
            ['key' => 'total_revenue', 'label' => 'Ingresos Cobrados (S/)'],
            ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
            ['key' => 'is_active', 'label' => 'Activo'],
            ['key' => 'created_at', 'label' => 'Fecha de Creación'],
        ];
    }

    /**
     * Get status text in Spanish.
     */
    private function getStatusText(string $status): string
    {
        $statuses = [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'maintenance' => 'Mantenimiento',
        ];

        return $statuses[$status] ?? $status;
    }
}
