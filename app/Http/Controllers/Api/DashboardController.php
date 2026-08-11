<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\CashRegisterSession;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            $branchId = $request->input('branch_id');
            $cacheKey = 'dashboard_stats_' . Auth::id() . '_' . ($branchId ?? 'all');

            $stats = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($branchId) {
                $today = Carbon::today();

                // Citas de hoy (datos reales)
                $todayAppointments = Appointment::whereDate('scheduled_at', $today)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->count();

                // Total de pacientes (datos reales) - Solo pacientes activos para consistencia
                $totalPatients = Patient::where('is_active', true)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->count();

                // Total de profesionales activos (datos reales)
                $totalProfessionals = User::where('is_active', true)
                    ->whereIn('role', ['odontologo', 'implantologo', 'dentista'])
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->count();

                // Total de citas (datos reales)
                $totalAppointments = Appointment::when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->count();

                // Total de citas este mes (datos reales)
                $totalAppointmentsThisMonth = Appointment::whereMonth('scheduled_at', $today->month)
                    ->whereYear('scheduled_at', $today->year)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->count();

                // Ingresos totales (datos reales)
                $totalIncome = Transaction::where('type', 'payment')
                    ->where('status', 'completed')
                    ->when($branchId, fn($q) => $q->whereHas('patient', fn($subQ) => $subQ->where('branch_id', $branchId)))
                    ->sum('amount');

                // Estado de caja actual (datos reales) - No cachear esto, debe ser en tiempo real
                $currentCashSession = CashRegisterSession::where('status', 'open')
                    ->where('user_id', Auth::id())
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->first();

                // Comparison block — strictly additive. Three keys, additive shape only.
                $comparisons = [
                    'appointments_today' => $this->appointmentsTodayComparison($todayAppointments, $today, $branchId),
                    'total_patients' => $this->totalPatientsComparison($today, $branchId),
                    'total_appointments_this_month' => $this->totalAppointmentsThisMonthComparison($totalAppointmentsThisMonth, $today, $branchId),
                ];

                return [
                    'appointments_today' => $todayAppointments,
                    'total_patients' => $totalPatients,
                    'total_professionals' => $totalProfessionals,
                    'total_appointments' => $totalAppointments,
                    'total_appointments_this_month' => $totalAppointmentsThisMonth,
                    'total_income' => $totalIncome,
                    'cash_session' => $currentCashSession ? [
                        'status' => 'open',
                        'id' => $currentCashSession->id,
                        'opening_amount' => $currentCashSession->opening_amount,
                        'opened_at' => $currentCashSession->opened_at,
                        'user_id' => $currentCashSession->user_id
                    ] : [
                        'status' => 'closed'
                    ],
                    'comparisons' => $comparisons,
                ];
            });

            // Actualizar cash_session en tiempo real (no cachear)
            $currentCashSession = CashRegisterSession::where('status', 'open')
                ->where('user_id', Auth::id())
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->first();

            $stats['cash_session'] = $currentCashSession ? [
                'status' => 'open',
                'id' => $currentCashSession->id,
                'opening_amount' => $currentCashSession->opening_amount,
                'opened_at' => $currentCashSession->opened_at,
                'user_id' => $currentCashSession->user_id
            ] : [
                'status' => 'closed'
            ];

            return response()->json([
                'data' => $stats,
                'meta' => [
                    'message' => 'Estadísticas del dashboard cargadas exitosamente',
                    'generated_at' => now()->toISOString(),
                    'cached' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en DashboardController@stats: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener datos del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Citas Hoy comparison: today's count vs the same weekday (Mon..Sun) seven days before.
     * A dental clinic runs on a weekly rhythm, so day-over-day would make every Monday read as
     * a spike against Sunday and distort every day after a holiday.
     */
    private function appointmentsTodayComparison(int $current, Carbon $today, $branchId): array
    {
        $previousDate = $today->copy()->subDays(7);
        $previous = Appointment::whereDate('scheduled_at', $previousDate)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        $weekdayShort = $this->weekdayShort($previousDate);
        $monthShort = $this->monthShort($previousDate);
        $periodLabel = "vs {$weekdayShort} {$previousDate->day} {$monthShort}";

        return [
            'current' => $current,
            'previous' => $previous,
            'period_label' => $periodLabel,
            'delta_label' => $this->deltaLabel($current, $previous),
        ];
    }

    /**
     * Pacientes comparison: NEW registrations in the current month vs previous month, derived
     * from `patients.created_at`. This is a different quantity from the headline
     * `data.total_patients` (which stays the cumulative active count, untouched).
     */
    private function totalPatientsComparison(Carbon $today, $branchId): array
    {
        $current = Patient::whereYear('created_at', $today->year)
            ->whereMonth('created_at', $today->month)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        // Use subMonthNoOverflow so e.g. May 31 → April 30 (not May 1, which subMonth would return).
        $previousMonth = $today->copy()->subMonthNoOverflow();
        $previous = Patient::whereYear('created_at', $previousMonth->year)
            ->whereMonth('created_at', $previousMonth->month)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'period_label' => 'nuevos este mes',
            'delta_label' => $this->deltaLabel($current, $previous),
        ];
    }

    /**
     * Total Citas comparison: month-to-date vs the SAME DAY SPAN of the previous month.
     * If today is the 12th, compare days 1-12 against days 1-12. Clamp with
     * min(today->day, prevMonth->daysInMonth) so day 31 against a 30-day previous month
     * does not produce an out-of-range date.
     */
    private function totalAppointmentsThisMonthComparison(int $current, Carbon $today, $branchId): array
    {
        // subMonthNoOverflow ensures e.g. Jul 31 → June 30 (not July 1, which subMonth would return).
        $previousMonth = $today->copy()->subMonthNoOverflow();
        $clampedDay = min($today->day, $previousMonth->daysInMonth);

        $start = $previousMonth->copy()->startOfMonth()->startOfDay();
        $end = $previousMonth->copy()->day($clampedDay)->endOfDay();

        $previous = Appointment::whereBetween('scheduled_at', [$start, $end])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        // Label names the PREVIOUS month (where the count window lives), NOT today.
        $monthShort = $this->monthShort($previousMonth);
        $dayWord = $clampedDay === 1 ? 'día' : 'días';
        $periodLabel = "vs {$monthShort} {$clampedDay} ({$clampedDay} {$dayWord})";

        return [
            'current' => $current,
            'previous' => $previous,
            'period_label' => $periodLabel,
            'delta_label' => $this->deltaLabel($current, $previous),
        ];
    }

    /**
     * Spanish weekday abbreviation indexed by Carbon's dayOfWeek (0 = Sunday).
     */
    private function weekdayShort(Carbon $date): string
    {
        return ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][$date->dayOfWeek];
    }

    /**
     * Spanish month abbreviation indexed by Carbon's month (1 = January).
     * The August token happens to spell "ago" — which is why a hardcoded "ago"
     * literal silently passed every August-anchored test in PR3.
     */
    private function monthShort(Carbon $date): string
    {
        return ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][$date->month - 1];
    }

    /**
     * D14 — omission trigger. `delta_label: null` when `previous === 0 || previous === null`.
     * Never trigger on `current === 0`: a zero-today / positive-prior result is real,
     * informative data a receptionist must see, so we render the absolute drop.
     */
    private function deltaLabel(int $current, ?int $previous): ?string
    {
        if ($previous === null || $previous === 0) {
            return null;
        }

        $diff = $current - $previous;
        if ($diff > 0) {
            return '+' . $diff;
        }
        if ($diff < 0) {
            return (string) $diff; // already includes the leading "-"
        }
        return '+0';
    }

    public function today(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $branchId = $request->input('branch_id');
        $cacheKey = 'dashboard_today_' . Auth::id() . '_' . $today->toDateString() . '_' . ($branchId ?? 'all');

        $appointments = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($today, $branchId) {
            return Appointment::with(['patient', 'appointmentType', 'user'])
                ->whereDate('scheduled_at', $today)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderBy('scheduled_at')
                ->get();
        });

        return response()->json([
            'data' => AppointmentResource::collection($appointments),
            'meta' => [
                'message' => 'Citas de hoy cargadas exitosamente',
                'date' => $today->toDateString(),
                'count' => $appointments->count(),
                'cached' => true
            ]
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $endOfWeek = $now->copy()->endOfWeek();
        $branchId = $request->input('branch_id');
        $cacheKey = 'dashboard_upcoming_' . Auth::id() . '_' . $now->format('Y-W') . '_' . ($branchId ?? 'all');

        $appointments = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($now, $endOfWeek, $branchId) {
            return Appointment::with(['patient', 'appointmentType', 'user'])
                ->whereBetween('scheduled_at', [$now, $endOfWeek])
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderBy('scheduled_at')
                ->limit(10)
                ->get();
        });

        return response()->json([
            'data' => AppointmentResource::collection($appointments),
            'meta' => [
                'message' => 'Próximas citas cargadas exitosamente',
                'period' => 'this_week',
                'count' => $appointments->count(),
                'cached' => true
            ]
        ]);
    }
}
