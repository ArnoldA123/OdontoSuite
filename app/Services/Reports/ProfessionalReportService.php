<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Reports\Concerns\ResolvesMoneyTotals;
use App\Services\Reports\Concerns\ResolvesReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProfessionalReportService
{
    use ResolvesMoneyTotals;
    use ResolvesReportPeriod;

    /**
     * Professional report.
     *
     * Population and money match the dashboard (D2, D7): the same five roles
     * and money attributed through `appointment_id`. Status counters come from
     * their own aggregate, never from a collection pre-filtered to `completed`.
     *
     * Money is net (S4): `total_revenue` is payments minus refunds and
     * `refunds` reports the positive refunded amount for the same professional.
     */
    public function getReportData(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($filters);
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $query = User::whereIn('role', DashboardService::PROFESSIONAL_ROLES);

        if ($professionalId) {
            $query->where('id', $professionalId);
        }

        $professionals = $query->orderBy('name')->get();

        if ($professionals->isEmpty()) {
            return ['data' => [], 'columns' => $this->columns()];
        }

        $ids = $professionals->pluck('id')->all();

        $counters = Appointment::select(
            'user_id',
            DB::raw('COUNT(*) as total_appointments'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments"),
            DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments"),
            DB::raw("SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments")
        )
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereIn('user_id', $ids)
            ->when($environmentId, fn ($q) => $q->where('dental_chair_id', $environmentId))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $moneyByProfessional = $this->moneyTransactions($startDate, $endDate)
            ->join('appointments', 'appointments.id', '=', 'transactions.appointment_id')
            ->whereIn('appointments.user_id', $ids)
            ->when($environmentId, fn ($q) => $q->where('appointments.dental_chair_id', $environmentId))
            ->groupBy('appointments.user_id')
            ->select(
                'appointments.user_id',
                DB::raw($this->netTotalsSql().' as total_revenue'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->get()
            ->keyBy('user_id');

        $data = $professionals->map(function ($professional) use ($counters, $moneyByProfessional) {
            $row = $counters[$professional->id] ?? null;
            $total = (int) ($row->total_appointments ?? 0);
            $completed = (int) ($row->completed_appointments ?? 0);
            $cancelled = (int) ($row->cancelled_appointments ?? 0);
            $noShow = (int) ($row->no_show_appointments ?? 0);
            $money = $moneyByProfessional[$professional->id] ?? null;
            $totalRevenue = round((float) ($money->total_revenue ?? 0), 2);

            return [
                'id' => $professional->id,
                'name' => $professional->name,
                'username' => $professional->username,
                'email' => $professional->email,
                'phone' => $professional->phone ?? 'No especificado',
                'specialty' => $professional->specialty ?? 'Sin especialidad',
                'total_appointments' => $total,
                'completed_appointments' => $completed,
                'cancelled_appointments' => $cancelled,
                'no_show_appointments' => $noShow,
                'total_revenue' => $totalRevenue,
                'refunds' => round((float) ($money->refunds ?? 0), 2),
                'average_per_appointment' => $completed > 0 ? round($totalRevenue / $completed, 2) : 0,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'is_active' => $professional->is_active ? 'Activo' : 'Inactivo',
                'created_at' => Carbon::parse($professional->created_at)->format('d/m/Y H:i'),
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
            ['key' => 'username', 'label' => 'Usuario'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Teléfono'],
            ['key' => 'specialty', 'label' => 'Especialidad'],
            ['key' => 'total_appointments', 'label' => 'Total Citas'],
            ['key' => 'completed_appointments', 'label' => 'Citas Completadas'],
            ['key' => 'cancelled_appointments', 'label' => 'Citas Canceladas'],
            ['key' => 'no_show_appointments', 'label' => 'No se Presentaron'],
            ['key' => 'total_revenue', 'label' => 'Ingresos netos (S/)'],
            ['key' => 'refunds', 'label' => 'Devoluciones (S/)'],
            ['key' => 'average_per_appointment', 'label' => 'Promedio por Cita (S/)'],
            ['key' => 'completion_rate', 'label' => 'Tasa de Finalización (%)'],
            ['key' => 'is_active', 'label' => 'Estado'],
            ['key' => 'created_at', 'label' => 'Fecha de Registro'],
        ];
    }
}
