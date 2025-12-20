<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\AppointmentType;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RevenueReportService
{
    /**
     * Get revenue report data
     */
    public function getReportData(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        // Get revenue by appointment type
        $revenueByType = $this->getRevenueByAppointmentType($startDate, $endDate, $professionalId, $environmentId);

        // Get revenue by professional
        $revenueByProfessional = $this->getRevenueByProfessional($startDate, $endDate, $professionalId, $environmentId);

        // Get revenue by environment
        $revenueByEnvironment = $this->getRevenueByEnvironment($startDate, $endDate, $professionalId, $environmentId);

        // Get daily revenue
        $dailyRevenue = $this->getDailyRevenue($startDate, $endDate, $professionalId, $environmentId);

        $data = collect();

        // Add summary data
        $data->push([
            'type' => 'Resumen',
            'category' => 'Total General',
            'appointments_count' => $this->getTotalAppointments($startDate, $endDate, $professionalId, $environmentId),
            'total_revenue' => $this->getTotalRevenue($startDate, $endDate, $professionalId, $environmentId),
            'average_per_appointment' => $this->getAveragePerAppointment($startDate, $endDate, $professionalId, $environmentId),
            'period' => Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y')
        ]);

        // Add revenue by type
        foreach ($revenueByType as $item) {
            $data->push([
                'type' => 'Por Tipo de Cita',
                'category' => $item['appointment_type'],
                'appointments_count' => $item['count'],
                'total_revenue' => $item['revenue'],
                'average_per_appointment' => $item['count'] > 0 ? round($item['revenue'] / $item['count'], 2) : 0,
                'period' => ''
            ]);
        }

        // Add revenue by professional
        foreach ($revenueByProfessional as $item) {
            $data->push([
                'type' => 'Por Profesional',
                'category' => $item['professional_name'],
                'appointments_count' => $item['count'],
                'total_revenue' => $item['revenue'],
                'average_per_appointment' => $item['count'] > 0 ? round($item['revenue'] / $item['count'], 2) : 0,
                'period' => ''
            ]);
        }

        // Add revenue by environment
        foreach ($revenueByEnvironment as $item) {
            $data->push([
                'type' => 'Por Ambiente',
                'category' => $item['environment_name'],
                'appointments_count' => $item['count'],
                'total_revenue' => $item['revenue'],
                'average_per_appointment' => $item['count'] > 0 ? round($item['revenue'] / $item['count'], 2) : 0,
                'period' => ''
            ]);
        }

        return [
            'data' => $data->toArray(),
            'columns' => [
                ['key' => 'type', 'label' => 'Tipo'],
                ['key' => 'category', 'label' => 'Categoría'],
                ['key' => 'appointments_count', 'label' => 'Número de Citas'],
                ['key' => 'total_revenue', 'label' => 'Ingresos Totales (S/)'],
                ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
                ['key' => 'period', 'label' => 'Período']
            ]
        ];
    }

    /**
     * Get revenue by appointment type
     */
    private function getRevenueByAppointmentType(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Appointment::with('appointmentType')
            ->select(
                'appointment_type_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(appointment_types.price) as revenue')
            )
            ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('appointments.status', 'completed')
            ->groupBy('appointment_type_id');

        if ($professionalId) {
            $query->where('appointments.user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('appointments.dental_chair_id', $environmentId);
        }

        $results = $query->get();

        return $results->map(function ($item) {
            return [
                'appointment_type' => $item->appointmentType->name,
                'count' => $item->count,
                'revenue' => round($item->revenue, 2)
            ];
        })->toArray();
    }

    /**
     * Get revenue by professional
     */
    private function getRevenueByProfessional(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Appointment::with('user')
            ->select(
                'user_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(appointment_types.price) as revenue')
            )
            ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('appointments.status', 'completed')
            ->groupBy('user_id');

        if ($professionalId) {
            $query->where('appointments.user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('appointments.dental_chair_id', $environmentId);
        }

        $results = $query->get();

        return $results->map(function ($item) {
            return [
                'professional_name' => $item->user->name,
                'count' => $item->count,
                'revenue' => round($item->revenue, 2)
            ];
        })->toArray();
    }

    /**
     * Get revenue by environment
     */
    private function getRevenueByEnvironment(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Appointment::with('dentalChair')
            ->select(
                'dental_chair_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(appointment_types.price) as revenue')
            )
            ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('appointments.status', 'completed')
            ->groupBy('dental_chair_id');

        if ($professionalId) {
            $query->where('appointments.user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('appointments.dental_chair_id', $environmentId);
        }

        $results = $query->get();

        return $results->map(function ($item) {
            return [
                'environment_name' => $item->dentalChair->name,
                'count' => $item->count,
                'revenue' => round($item->revenue, 2)
            ];
        })->toArray();
    }

    /**
     * Get daily revenue
     */
    private function getDailyRevenue(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Appointment::select(
            DB::raw('DATE(scheduled_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(appointment_types.price) as revenue')
        )
        ->join('appointment_types', 'appointments.appointment_type_id', '=', 'appointment_types.id')
        ->whereBetween('scheduled_at', [$startDate, $endDate])
        ->where('appointments.status', 'completed')
        ->groupBy(DB::raw('DATE(scheduled_at)'))
        ->orderBy('date');

        if ($professionalId) {
            $query->where('appointments.user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('appointments.dental_chair_id', $environmentId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get total appointments
     */
    private function getTotalAppointments(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): int
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

    /**
     * Get total revenue
     */
    private function getTotalRevenue(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $query = Appointment::with('appointmentType')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'completed');

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        $appointments = $query->get();

        return $appointments->sum(function ($appointment) {
            return $appointment->appointmentType->price ?? 0;
        });
    }

    /**
     * Get average per appointment
     */
    private function getAveragePerAppointment(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $totalAppointments = $this->getTotalAppointments($startDate, $endDate, $professionalId, $environmentId);
        $totalRevenue = $this->getTotalRevenue($startDate, $endDate, $professionalId, $environmentId);

        return $totalAppointments > 0 ? round($totalRevenue / $totalAppointments, 2) : 0;
    }
}
