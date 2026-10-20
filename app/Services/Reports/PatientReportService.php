<?php

namespace App\Services\Reports;

use App\Models\Patient;
use App\Models\Transaction;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatientReportService
{
    use ResolvesReportPeriod;

    /**
     * Patient report.
     *
     * Spend is money actually collected in the period (D1), not catalog price,
     * and the last appointment reads any status so a cancelled visit still
     * reports activity.
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
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
                $q->whereBetween('scheduled_at', [$startDate, $endDate]);

                if ($professionalId) {
                    $q->where('user_id', $professionalId);
                }

                if ($environmentId) {
                    $q->where('dental_chair_id', $environmentId);
                }
            }]);

        $patients = $query->orderBy('last_name')->orderBy('first_name')->get();

        $patientIds = $patients->pluck('id')->all();

        $spendByPatient = empty($patientIds) ? collect() : Transaction::query()
            ->where('type', 'payment')
            ->where('status', 'completed')
            ->whereIn('patient_id', $patientIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('patient_id')
            ->select('patient_id', DB::raw('SUM(amount) as total_spent'))
            ->pluck('total_spent', 'patient_id');

        $data = $patients->map(function ($patient) use ($spendByPatient) {
            $lastAppointment = $patient->appointments->sortByDesc('scheduled_at')->first();

            return [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'date_of_birth' => $patient->birth_date ? Carbon::parse($patient->birth_date)->format('d/m/Y') : 'No especificada',
                'age' => $patient->birth_date ? Carbon::parse($patient->birth_date)->age : 'No especificada',
                'total_appointments' => $patient->appointments_count,
                'total_spent' => round((float) ($spendByPatient[$patient->id] ?? 0), 2),
                'last_appointment' => $lastAppointment ? Carbon::parse($lastAppointment->scheduled_at)->format('d/m/Y') : 'Nunca',
                'is_active' => $patient->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($patient->created_at)->format('d/m/Y H:i'),
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
                ['key' => 'total_spent', 'label' => 'Total Cobrado (S/)'],
                ['key' => 'last_appointment', 'label' => 'Última Cita del Período'],
                ['key' => 'is_active', 'label' => 'Estado'],
                ['key' => 'created_at', 'label' => 'Fecha de Registro'],
            ],
        ];
    }
}
