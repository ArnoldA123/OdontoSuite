<?php

namespace App\Services\Reports;

use App\Models\Patient;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PatientReportService
{
    /**
     * Get patient report data
     */
    public function getReportData(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $query = Patient::withCount(['appointments' => function ($q) use ($startDate, $endDate, $professionalId, $environmentId) {
            $q->whereBetween('scheduled_at', [$startDate, $endDate]);

            if ($professionalId) {
                $q->where('user_id', $professionalId);
            }

            if ($environmentId) {
                $q->where('dental_chair_id', $environmentId);
            }
        }])
        ->with(['appointments' => function ($q) use ($startDate, $endDate, $professionalId, $environmentId) {
            $q->with('appointmentType')
                ->whereBetween('scheduled_at', [$startDate, $endDate])
                ->where('status', 'completed');

            if ($professionalId) {
                $q->where('user_id', $professionalId);
            }

            if ($environmentId) {
                $q->where('dental_chair_id', $environmentId);
            }
        }]);

        $patients = $query->get();

        $data = $patients->map(function ($patient) {
            $totalSpent = $patient->appointments->sum(function ($appointment) {
                return $appointment->appointmentType->price ?? 0;
            });

            $lastAppointment = $patient->appointments->sortByDesc('scheduled_at')->first();

            return [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'date_of_birth' => $patient->date_of_birth ? Carbon::parse($patient->date_of_birth)->format('d/m/Y') : 'No especificada',
                'age' => $patient->date_of_birth ? Carbon::parse($patient->date_of_birth)->age : 'No especificada',
                'total_appointments' => $patient->appointments_count,
                'total_spent' => round($totalSpent, 2),
                'last_appointment' => $lastAppointment ? Carbon::parse($lastAppointment->scheduled_at)->format('d/m/Y') : 'Nunca',
                'is_active' => $patient->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($patient->created_at)->format('d/m/Y H:i')
            ];
        });

        return [
            'data' => $data->toArray(),
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'first_name', 'label' => 'Nombre'],
                ['key' => 'last_name', 'label' => 'Apellido'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Teléfono'],
                ['key' => 'date_of_birth', 'label' => 'Fecha de Nacimiento'],
                ['key' => 'age', 'label' => 'Edad'],
                ['key' => 'total_appointments', 'label' => 'Total Citas'],
                ['key' => 'total_spent', 'label' => 'Total Gastado (S/)'],
                ['key' => 'last_appointment', 'label' => 'Última Cita'],
                ['key' => 'is_active', 'label' => 'Estado'],
                ['key' => 'created_at', 'label' => 'Fecha de Registro']
            ]
        ];
    }
}
