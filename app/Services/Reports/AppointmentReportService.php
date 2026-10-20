<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;

class AppointmentReportService
{
    use ResolvesReportPeriod;

    /**
     * Get appointment report data.
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $query = Appointment::with(['patient', 'user', 'dentalChair', 'appointmentType'])
            ->whereBetween('scheduled_at', [$startDate, $endDate]);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        $appointments = $query->orderBy('scheduled_at', 'desc')->get();

        $data = $appointments->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'patient_name' => $appointment->patient->first_name.' '.$appointment->patient->last_name,
                'patient_email' => $appointment->patient->email,
                'patient_phone' => $appointment->patient->phone,
                'professional_name' => $appointment->user->name,
                'professional_specialty' => $appointment->user->specialty ?? 'Sin especialidad',
                'environment_name' => $appointment->dentalChair->name,
                'appointment_type' => $appointment->appointmentType->name,
                'scheduled_date' => Carbon::parse($appointment->scheduled_at)->format('d/m/Y'),
                'scheduled_time' => Carbon::parse($appointment->scheduled_at)->format('H:i'),
                'duration_minutes' => $appointment->duration_minutes,
                'status' => $this->getStatusText($appointment->status),
                'price' => $appointment->appointmentType->price ?? 0,
                'notes' => $appointment->notes ?? '',
                'created_at' => Carbon::parse($appointment->created_at)->format('d/m/Y H:i'),
            ];
        });

        return [
            'data' => $data->toArray(),
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'patient_name', 'label' => 'Paciente'],
                ['key' => 'patient_email', 'label' => 'Email'],
                ['key' => 'patient_phone', 'label' => 'Teléfono'],
                ['key' => 'professional_name', 'label' => 'Profesional'],
                ['key' => 'professional_specialty', 'label' => 'Especialidad'],
                ['key' => 'environment_name', 'label' => 'Ambiente'],
                ['key' => 'appointment_type', 'label' => 'Tipo de Cita'],
                ['key' => 'scheduled_date', 'label' => 'Fecha'],
                ['key' => 'scheduled_time', 'label' => 'Hora'],
                ['key' => 'duration_minutes', 'label' => 'Duración (min)'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'price', 'label' => 'Precio de Lista (S/)'],
                ['key' => 'notes', 'label' => 'Observaciones'],
                ['key' => 'created_at', 'label' => 'Fecha de Creación'],
            ],
        ];
    }

    /**
     * Get status text in Spanish.
     */
    private function getStatusText(string $status): string
    {
        $statuses = [
            'scheduled' => 'Programada',
            'confirmed' => 'Confirmada',
            'in_progress' => 'En Consulta',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No se presentó',
        ];

        return $statuses[$status] ?? $status;
    }
}
