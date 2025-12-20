<?php

namespace App\Services\Reports;

use App\Models\DentalChair;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UtilizationReportService
{
    /**
     * Get utilization report data
     */
    public function getReportData(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $query = DentalChair::withCount(['appointments' => function ($q) use ($startDate, $endDate, $professionalId) {
            $q->whereBetween('scheduled_at', [$startDate, $endDate]);

            if ($professionalId) {
                $q->where('user_id', $professionalId);
            }
        }])
        ->with(['appointments' => function ($q) use ($startDate, $endDate, $professionalId) {
            $q->whereBetween('scheduled_at', [$startDate, $endDate]);

            if ($professionalId) {
                $q->where('user_id', $professionalId);
            }
        }]);

        if ($environmentId) {
            $query->where('id', $environmentId);
        }

        $environments = $query->get();

        $data = $environments->map(function ($environment) use ($startDate, $endDate) {
            $totalHours = $this->getTotalAvailableHours($startDate, $endDate);
            $usedHours = $environment->appointments->sum('duration_minutes') / 60; // Convert minutes to hours
            $utilizationRate = $totalHours > 0 ? round(($usedHours / $totalHours) * 100, 2) : 0;

            $completedAppointments = $environment->appointments->where('status', 'completed')->count();
            $cancelledAppointments = $environment->appointments->where('status', 'cancelled')->count();
            $noShowAppointments = $environment->appointments->where('status', 'no_show')->count();

            $totalRevenue = $environment->appointments->where('status', 'completed')->sum(function ($appointment) {
                return $appointment->appointmentType->price ?? 0;
            });

            return [
                'id' => $environment->id,
                'name' => $environment->name,
                'code' => $environment->code,
                'description' => $environment->description ?? 'Sin descripción',
                'status' => $this->getStatusText($environment->status),
                'total_appointments' => $environment->appointments_count,
                'completed_appointments' => $completedAppointments,
                'cancelled_appointments' => $cancelledAppointments,
                'no_show_appointments' => $noShowAppointments,
                'total_hours_used' => round($usedHours, 2),
                'total_hours_available' => $totalHours,
                'utilization_rate' => $utilizationRate,
                'total_revenue' => round($totalRevenue, 2),
                'average_per_appointment' => $completedAppointments > 0 ? round($totalRevenue / $completedAppointments, 2) : 0,
                'is_active' => $environment->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($environment->created_at)->format('d/m/Y H:i')
            ];
        });

        return [
            'data' => $data->toArray(),
            'columns' => [
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
                ['key' => 'total_revenue', 'label' => 'Ingresos Totales (S/)'],
                ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
                ['key' => 'is_active', 'label' => 'Activo'],
                ['key' => 'created_at', 'label' => 'Fecha de Creación']
            ]
        ];
    }

    /**
     * Get total available hours
     */
    private function getTotalAvailableHours(string $startDate, string $endDate): float
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end) + 1;

        // Assuming 8 hours per day, 5 days per week
        $hoursPerDay = 8;
        $workingDays = min($days, 30); // Cap at 30 days for calculation

        return $workingDays * $hoursPerDay;
    }

    /**
     * Get status text in Spanish
     */
    private function getStatusText(string $status): string
    {
        $statuses = [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'maintenance' => 'Mantenimiento'
        ];

        return $statuses[$status] ?? $status;
    }
}
