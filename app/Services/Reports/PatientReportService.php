<?php

namespace App\Services\Reports;

use App\Models\Patient;
use App\Services\Reports\Concerns\ResolvesBranchFilter;
use App\Services\Reports\Concerns\ResolvesMoneyTotals;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use App\Services\Reports\Concerns\SummarizesPeriodActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatientReportService
{
    use ResolvesBranchFilter;
    use ResolvesMoneyTotals;
    use ResolvesReportPeriod;
    use SummarizesPeriodActivity;

    /**
     * Patient report.
     *
     * Spend is money actually collected in the period minus refunds (D1, S4),
     * not catalog price, and the last appointment reads any status so a
     * cancelled visit still reports activity.
     *
     * The patient file is shared across sedes (ADR-0010), so a sede filter is
     * not a patient census: the population is the patients seen in that sede
     * during the period and the money is what its drawer collected (#303, L2).
     * A patient treated in two sedes appears in both, with each sede's figures.
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;
        $branchId = $this->branchFilter($filters);

        // `withCount` passes a builder and `with` a relation, so the shared
        // callback stays untyped.
        $appointments = function ($query) use ($startDate, $endDate, $professionalId, $environmentId, $branchId) {
            $query->whereBetween('scheduled_at', [$startDate, $endDate]);

            if ($professionalId) {
                $query->where('user_id', $professionalId);
            }

            if ($environmentId) {
                $query->where('dental_chair_id', $environmentId);
            }

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }
        };

        $query = Patient::withCount(['appointments' => $appointments])
            ->with(['appointments' => $appointments]);

        if ($branchId !== null) {
            $query->whereHas('appointments', $appointments);
        }

        $patients = $query->orderBy('last_name')->orderBy('first_name')->get();

        $patientIds = $patients->pluck('id')->all();

        $moneyByPatient = empty($patientIds) ? collect() : $this->filterByDrawerBranch(
            $this->moneyTransactions($startDate, $endDate)->whereIn('patient_id', $patientIds),
            $branchId
        )
            ->groupBy('patient_id')
            ->select(
                'patient_id',
                DB::raw($this->netTotalsSql().' as total_spent'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->get()
            ->keyBy('patient_id');

        $data = $patients->map(function ($patient) use ($moneyByPatient) {
            $lastAppointment = $patient->appointments->sortByDesc('scheduled_at')->first();
            $money = $moneyByPatient[$patient->id] ?? null;

            return [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'date_of_birth' => $patient->birth_date ? Carbon::parse($patient->birth_date)->format('d/m/Y') : 'No especificada',
                'age' => $patient->birth_date ? Carbon::parse($patient->birth_date)->age : 'No especificada',
                'total_appointments' => $patient->appointments_count,
                'total_spent' => round((float) ($money->total_spent ?? 0), 2),
                'refunds' => round((float) ($money->refunds ?? 0), 2),
                'last_appointment' => $lastAppointment ? Carbon::parse($lastAppointment->scheduled_at)->format('d/m/Y') : 'Nunca',
                'is_active' => $patient->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($patient->created_at)->format('d/m/Y H:i'),
            ];
        });

        return [
            'data' => $data->toArray(),
            'summary' => $this->periodActivity($data, 'total_spent'),
            'columns' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'first_name', 'label' => 'Nombre'],
                ['key' => 'last_name', 'label' => 'Apellido'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Teléfono'],
                ['key' => 'date_of_birth', 'label' => 'Fecha de Nacimiento'],
                ['key' => 'age', 'label' => 'Edad'],
                ['key' => 'total_appointments', 'label' => 'Total Citas'],
                ['key' => 'total_spent', 'label' => 'Total Neto (S/)'],
                ['key' => 'refunds', 'label' => 'Devoluciones (S/)'],
                ['key' => 'last_appointment', 'label' => 'Última Cita del Período'],
                ['key' => 'is_active', 'label' => 'Estado'],
                ['key' => 'created_at', 'label' => 'Fecha de Registro'],
            ],
        ];
    }
}
