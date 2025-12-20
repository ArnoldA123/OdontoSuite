<?php

namespace App\Services\Reports;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalChair;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Get dashboard data
     */
    public function getDashboardData(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->addDays(60)->toDateString();
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        // Create cache key based on filters
        $cacheKey = 'dashboard_data_' . md5(serialize([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'professional_id' => $professionalId,
            'environment_id' => $environmentId
        ]));

        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $professionalId, $environmentId) {
            return [
                'totalAppointments' => $this->getTotalAppointments($startDate, $endDate, $professionalId, $environmentId),
                'totalPatients' => $this->getTotalPatients($startDate, $endDate, $professionalId, $environmentId),
                'totalRevenue' => $this->getTotalRevenue($startDate, $endDate, $professionalId, $environmentId),
                'occupancyRate' => $this->getOccupancyRate($startDate, $endDate, $professionalId, $environmentId),
                'appointmentsByDay' => $this->getAppointmentsByDay($startDate, $endDate, $professionalId, $environmentId),
                'revenueByMonth' => $this->getRevenueByMonth($startDate, $endDate, $professionalId, $environmentId),
                'professionalPerformance' => $this->getProfessionalPerformance($startDate, $endDate, $professionalId, $environmentId)
            ];
        });
    }

    /**
     * Clear dashboard cache
     * This should be called when relevant data changes (appointments, patients, transactions, etc.)
     */
    public function clearCache(): void
    {
        // Clear all dashboard cache entries
        // Since we use a pattern 'dashboard_data_*', we need to clear all matching keys
        // For simplicity, we'll use Cache::flush() or a more targeted approach
        // Using tags if available, otherwise we'll need to track keys
        
        // Simple approach: clear all cache (can be optimized later with cache tags)
        Cache::flush();
        
        // Alternative: If using cache tags (Redis/Memcached)
        // Cache::tags(['dashboard'])->flush();
    }

    /**
     * Clear cache for specific filters
     */
    public function clearCacheForFilters(array $filters = []): void
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->addDays(60)->toDateString();
        $professionalId = $filters['professional_id'] ?? null;
        $environmentId = $filters['environment_id'] ?? null;

        $cacheKey = 'dashboard_data_' . md5(serialize([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'professional_id' => $professionalId,
            'environment_id' => $environmentId
        ]));

        Cache::forget($cacheKey);
    }

    /**
     * Get report data for export
     */
    public function getReportData(array $filters = []): array
    {
        $dashboardData = $this->getDashboardData($filters);

        // Si hay datos de rendimiento por profesional, exportarlos en formato tabular
        if (!empty($dashboardData['professionalPerformance'])) {
            $columns = [
                ['key' => 'profesional', 'label' => 'Profesional'],
                ['key' => 'citas', 'label' => 'Citas'],
                ['key' => 'ingresos', 'label' => 'Ingresos (S/)'],
                ['key' => 'promedio_por_cita', 'label' => 'Promedio por Cita (S/)']
            ];

            $data = [];
            foreach ($dashboardData['professionalPerformance'] as $professional) {
                $data[] = [
                    'profesional' => $professional['name'],
                    'citas' => $professional['appointments'],
                    'ingresos' => number_format($professional['revenue'], 2),
                    'promedio_por_cita' => number_format($professional['averagePerAppointment'], 2)
                ];
            }

            // Agregar resumen de KPIs al final
            $data[] = [
                'profesional' => '--- RESUMEN ---',
                'citas' => '',
                'ingresos' => '',
                'promedio_por_cita' => ''
            ];
            $data[] = [
                'profesional' => 'Total Citas',
                'citas' => $dashboardData['totalAppointments'],
                'ingresos' => '',
                'promedio_por_cita' => ''
            ];
            $data[] = [
                'profesional' => 'Total Pacientes',
                'citas' => $dashboardData['totalPatients'],
                'ingresos' => '',
                'promedio_por_cita' => ''
            ];
            $data[] = [
                'profesional' => 'Ingresos Totales',
                'citas' => '',
                'ingresos' => number_format($dashboardData['totalRevenue'], 2),
                'promedio_por_cita' => ''
            ];
            $data[] = [
                'profesional' => 'Tasa de Ocupación',
                'citas' => $dashboardData['occupancyRate'] . '%',
                'ingresos' => '',
                'promedio_por_cita' => ''
            ];
        } else {
            // Si no hay datos de profesionales, usar formato de métricas
            $columns = [
                ['key' => 'metric', 'label' => 'Métrica'],
                ['key' => 'value', 'label' => 'Valor']
            ];

            $data = [];
            $data[] = ['metric' => 'Total Citas', 'value' => $dashboardData['totalAppointments']];
            $data[] = ['metric' => 'Total Pacientes', 'value' => $dashboardData['totalPatients']];
            $data[] = ['metric' => 'Ingresos Totales (S/)', 'value' => number_format($dashboardData['totalRevenue'], 2)];
            $data[] = ['metric' => 'Tasa de Ocupación (%)', 'value' => $dashboardData['occupancyRate']];
        }

        return [
            'data' => $data,
            'columns' => $columns
        ];
    }

    /**
     * Get total appointments
     * Solo cuenta citas con pacientes activos para consistencia
     */
    private function getTotalAppointments(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): int
    {
        $query = Appointment::whereHas('patient', function ($q) {
            $q->where('is_active', true);
        })
        ->whereBetween('scheduled_at', [$startDate, $endDate]);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        return $query->count();
    }

    /**
     * Get total patients
     * Cuenta todos los pacientes activos persistentes en la BD
     * Si hay filtros de profesional/ambiente, filtra por pacientes con citas en ese rango
     */
    private function getTotalPatients(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): int
    {
        // Si hay filtros de profesional o ambiente, contar solo pacientes activos con citas en ese rango
        if ($professionalId || $environmentId) {
            $query = Patient::where('is_active', true)
                ->whereHas('appointments', function ($q) use ($startDate, $endDate, $professionalId, $environmentId) {
                    $q->whereBetween('scheduled_at', [$startDate, $endDate]);

                    if ($professionalId) {
                        $q->where('user_id', $professionalId);
                    }

                    if ($environmentId) {
                        $q->where('dental_chair_id', $environmentId);
                    }
                });
        } else {
            // Sin filtros: contar todos los pacientes activos persistentes en la BD
            $query = Patient::where('is_active', true);
        }

        return $query->count();
    }

    /**
     * Get total revenue
     * Usa TRANSACCIONES reales de la BD (Transaction model con status='completed' y type='payment')
     * Solo cuenta transacciones con pacientes activos para consistencia
     */
    private function getTotalRevenue(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $query = Transaction::where('type', 'payment')
            ->where('status', 'completed')
            ->whereHas('patient', function ($q) {
                $q->where('is_active', true);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Aplicar filtros por profesional/ambiente a través de appointments
        if ($professionalId || $environmentId) {
            $query->where(function ($q) use ($professionalId, $environmentId) {
                // Transacciones con appointment (filtradas por profesional/ambiente)
                $q->whereHas('appointment', function ($subQ) use ($professionalId, $environmentId) {
                    if ($professionalId) {
                        $subQ->where('user_id', $professionalId);
                    }

                    if ($environmentId) {
                        $subQ->where('dental_chair_id', $environmentId);
                    }
                });
            });
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get occupancy rate
     */
    private function getOccupancyRate(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $totalHours = $this->getTotalAvailableHours($startDate, $endDate, $professionalId, $environmentId);
        $usedHours = $this->getUsedHours($startDate, $endDate, $professionalId, $environmentId);

        if ($totalHours == 0) {
            return 0;
        }

        return round(($usedHours / $totalHours) * 100, 2);
    }

    /**
     * Get total available hours
     */
    private function getTotalAvailableHours(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): float
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
     * Get used hours
     * Solo cuenta citas con pacientes activos para consistencia
     */
    private function getUsedHours(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): float
    {
        $query = Appointment::whereHas('patient', function ($q) {
            $q->where('is_active', true);
        })
        ->whereBetween('scheduled_at', [$startDate, $endDate])
        ->where('status', '!=', 'cancelled');

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        return $query->sum('duration_minutes') / 60; // Convert minutes to hours
    }

    /**
     * Get appointments by day
     * Solo cuenta citas con pacientes activos para consistencia
     * Retorna formato: [{date: 'YYYY-MM-DD', count: number}]
     */
    private function getAppointmentsByDay(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Appointment::select(
            DB::raw('DATE(scheduled_at) as date'),
            DB::raw('COUNT(*) as count')
        )
        ->whereHas('patient', function ($q) {
            $q->where('is_active', true);
        })
        ->whereBetween('scheduled_at', [$startDate, $endDate])
        ->groupBy(DB::raw('DATE(scheduled_at)'))
        ->orderBy('date');

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($environmentId) {
            $query->where('dental_chair_id', $environmentId);
        }

        $results = $query->get();

        // Asegurar formato correcto: date como string 'YYYY-MM-DD', count como integer
        return $results->map(function ($item) {
            return [
                'date' => is_string($item->date) ? $item->date : Carbon::parse($item->date)->format('Y-m-d'),
                'count' => (int) $item->count
            ];
        })->toArray();
    }

    /**
     * Get revenue by month
     * Usa TRANSACCIONES reales agrupadas por mes
     * Solo cuenta transacciones con pacientes activos para consistencia
     */
    private function getRevenueByMonth(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        $query = Transaction::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('SUM(amount) as revenue')
        )
        ->where('type', 'payment')
        ->where('status', 'completed')
        ->whereHas('patient', function ($q) {
            $q->where('is_active', true);
        })
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
        ->orderBy('year')
        ->orderBy('month');

        // Aplicar filtros por profesional/ambiente a través de appointments
        if ($professionalId || $environmentId) {
            $query->where(function ($q) use ($professionalId, $environmentId) {
                // Solo transacciones con appointment (filtradas por profesional/ambiente)
                $q->whereHas('appointment', function ($subQ) use ($professionalId, $environmentId) {
                    if ($professionalId) {
                        $subQ->where('user_id', $professionalId);
                    }

                    if ($environmentId) {
                        $subQ->where('dental_chair_id', $environmentId);
                    }
                });
            });
        }

        $results = $query->get();

        return $results->map(function ($item) {
            return [
                'month' => Carbon::create($item->year, $item->month)->format('M Y'),
                'revenue' => round((float) $item->revenue, 2)
            ];
        })->toArray();
    }

    /**
     * Get professional performance
     * Incluye todos los roles profesionales y usa TRANSACCIONES reales
     */
    private function getProfessionalPerformance(string $startDate, string $endDate, ?int $professionalId, ?int $environmentId): array
    {
        // Incluir todos los roles profesionales
        $query = User::whereIn('role', ['odontologo', 'implantologo', 'dentista', 'tecnico_dental', 'asistente'])
            ->where('is_active', true);

        if ($professionalId) {
            $query->where('id', $professionalId);
        }

        $professionals = $query->get();

        return $professionals->map(function ($professional) use ($startDate, $endDate, $environmentId) {
            // Citas atendidas por el profesional en el rango (solo pacientes activos)
            // Incluir todas las citas programadas y completadas, no solo completadas
            $appointmentsQuery = Appointment::where('user_id', $professional->id)
                ->whereHas('patient', function ($q) {
                    $q->where('is_active', true);
                })
                ->whereBetween('scheduled_at', [$startDate, $endDate])
                ->whereIn('status', ['scheduled', 'completed', 'in_progress']);

            if ($environmentId) {
                $appointmentsQuery->where('dental_chair_id', $environmentId);
            }

            $appointments = $appointmentsQuery->with('appointmentType')->get();
            $appointmentIds = $appointments->pluck('id')->toArray();
            $patientIdsOfThisProfessional = $appointments->pluck('patient_id')->unique()->toArray();

            // INGRESOS 1: Transacciones ligadas a citas de este profesional
            $revenueFromAppointmentTransactions = Transaction::where('type', 'payment')
                ->where('status', 'completed')
                ->whereHas('patient', function ($q) {
                    $q->where('is_active', true);
                })
                ->whereIn('appointment_id', $appointmentIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');

            // INGRESOS 2: Transacciones SIN appointment_id, pero del paciente atendido por este profesional en el rango
            // (atribución por relación paciente->citas con este profesional en el mismo rango)
            $revenueFromPatientOnlyTransactions = 0.0;
            if (!empty($patientIdsOfThisProfessional)) {
                $revenueFromPatientOnlyTransactions = Transaction::where('type', 'payment')
                    ->where('status', 'completed')
                    ->whereNull('appointment_id')
                    ->whereIn('patient_id', $patientIdsOfThisProfessional)
                    ->whereHas('patient', function ($q) {
                        $q->where('is_active', true);
                    })
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');
            }

            // INGRESOS 3: Si no hay transacciones, usar el precio del tipo de cita como alternativa
            $revenueFromAppointmentTypes = 0.0;
            if ($revenueFromAppointmentTransactions == 0 && $revenueFromPatientOnlyTransactions == 0) {
                foreach ($appointments as $appointment) {
                    if ($appointment->appointmentType && $appointment->appointmentType->price) {
                        $revenueFromAppointmentTypes += (float) $appointment->appointmentType->price;
                    }
                }
            }

            $totalRevenue = (float) $revenueFromAppointmentTransactions + (float) $revenueFromPatientOnlyTransactions + (float) $revenueFromAppointmentTypes;

            $appointmentCount = $appointments->count();
            $averagePerAppointment = $appointmentCount > 0 ? (float) $totalRevenue / $appointmentCount : 0;

            return [
                'id' => $professional->id,
                'name' => $professional->name,
                'appointments' => $appointmentCount,
                'revenue' => round((float) $totalRevenue, 2),
                'averagePerAppointment' => round($averagePerAppointment, 2)
            ];
        })->toArray();
    }
}
