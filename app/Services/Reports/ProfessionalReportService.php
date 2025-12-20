<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfessionalReportService
{
    /**
     * Get professional report data
     */
    public function getReportData(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $query = User::where('role', 'odontologo')
            ->withCount(['appointments' => function ($q) use ($startDate, $endDate, $environmentId) {
                $q->whereBetween('scheduled_at', [$startDate, $endDate]);

                if ($environmentId) {
                    $q->where('dental_chair_id', $environmentId);
                }
            }])
            ->with(['appointments' => function ($q) use ($startDate, $endDate, $environmentId) {
                $q->with('appointmentType')
                    ->whereBetween('scheduled_at', [$startDate, $endDate])
                    ->where('status', 'completed');

                if ($environmentId) {
                    $q->where('dental_chair_id', $environmentId);
                }
            }]);

        if ($professionalId) {
            $query->where('id', $professionalId);
        }

        $professionals = $query->get();

        $data = $professionals->map(function ($professional) {
            $totalRevenue = $professional->appointments->sum(function ($appointment) {
                return $appointment->appointmentType->price ?? 0;
            });

            $completedAppointments = $professional->appointments->where('status', 'completed')->count();
            $cancelledAppointments = $professional->appointments->where('status', 'cancelled')->count();
            $noShowAppointments = $professional->appointments->where('status', 'no_show')->count();

            $averagePerAppointment = $completedAppointments > 0 ? $totalRevenue / $completedAppointments : 0;

            return [
                'id' => $professional->id,
                'name' => $professional->name,
                'username' => $professional->username,
                'email' => $professional->email,
                'phone' => $professional->phone ?? 'No especificado',
                'specialty' => $professional->specialty ?? 'Sin especialidad',
                'total_appointments' => $professional->appointments_count,
                'completed_appointments' => $completedAppointments,
                'cancelled_appointments' => $cancelledAppointments,
                'no_show_appointments' => $noShowAppointments,
                'total_revenue' => round($totalRevenue, 2),
                'average_per_appointment' => round($averagePerAppointment, 2),
                'completion_rate' => $professional->appointments_count > 0 ? round(($completedAppointments / $professional->appointments_count) * 100, 2) : 0,
                'is_active' => $professional->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($professional->created_at)->format('d/m/Y H:i')
            ];
        });

        return [
            'data' => $data->toArray(),
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'name', 'label' => 'Nombre'],
                ['key' => 'username', 'label' => 'Usuario'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Teléfono'],
                ['key' => 'specialty', 'label' => 'Especialidad'],
                ['key' => 'total_appointments', 'label' => 'Total Citas'],
                ['key' => 'completed_appointments', 'label' => 'Citas Completadas'],
                ['key' => 'cancelled_appointments', 'label' => 'Citas Canceladas'],
                ['key' => 'no_show_appointments', 'label' => 'No se Presentaron'],
                ['key' => 'total_revenue', 'label' => 'Ingresos Totales (S/)'],
                ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
                ['key' => 'completion_rate', 'label' => 'Tasa de Finalización (%)'],
                ['key' => 'is_active', 'label' => 'Estado'],
                ['key' => 'created_at', 'label' => 'Fecha de Registro']
            ]
        ];
    }
}
