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
                    ]
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
                'period' => 'this_week',
                'count' => $appointments->count(),
                'cached' => true
            ]
        ]);
    }
}
