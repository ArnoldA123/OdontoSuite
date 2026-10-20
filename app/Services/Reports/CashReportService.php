<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use Carbon\Carbon;

class CashReportService
{
    public function __construct(private readonly CashRegisterService $cashRegisterService) {}

    /**
     * Get daily cash report.
     */
    public function getDailyReport(string $date, ?int $branchId = null, ?int $userId = null): array
    {
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        $query = CashRegisterSession::with(['user', 'branch', 'movements', 'transactions'])
            ->whereBetween('opened_at', [$startOfDay, $endOfDay]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $sessions = $query->get();

        $report = [
            'date' => $date,
            'sessions_count' => $sessions->count(),
            'total_opening_amount' => $sessions->sum('opening_amount'),
            'total_closing_amount' => $sessions->sum('closing_amount'),
            'total_expected_amount' => $sessions->sum('expected_amount'),
            'total_difference' => $sessions->sum('difference_amount'),
            'sessions' => $sessions->map(function ($session) {
                return $this->getSessionDetails($session);
            }),
            'summary_by_payment_method' => $this->getSummaryByPaymentMethod($sessions),
            'summary_by_hour' => $this->getSummaryByHour($sessions),
            'top_transactions' => $this->getTopTransactions($sessions),
        ];

        return $report;
    }

    /**
     * Get period cash report.
     */
    public function getPeriodReport(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $query = CashRegisterSession::with(['user', 'branch'])
            ->whereBetween('opened_at', [$start, $end]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $sessions = $query->get();
        $flow = $this->getFlowTotals($start, $end, $branchId);

        $report = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'sessions_count' => $sessions->count(),
            'total_opening_amount' => $sessions->sum('opening_amount'),
            'total_closing_amount' => $sessions->sum('closing_amount'),
            'total_expected_amount' => $sessions->sum('expected_amount'),
            'total_difference' => $sessions->sum('difference_amount'),
            'average_difference' => $sessions->avg('difference_amount'),
            'total_income' => $flow['total_income'],
            'total_expenses' => $flow['total_expenses'],
            'daily_summary' => $this->getDailySummary($start, $end, $branchId),
            'summary_by_branch' => $this->getSummaryByBranch($sessions),
            'summary_by_user' => $this->getSummaryByUser($sessions),
            'payment_methods_analysis' => $this->getPaymentMethodsAnalysis($start, $end, $branchId),
        ];

        return $report;
    }

    /**
     * Export cash data to Excel/PDF.
     */
    public function exportData(array $filters, string $format = 'excel'): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();
        $branchId = $filters['branch_id'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $data = $this->getPeriodReport($startDate, $endDate, $branchId);

        if ($format === 'excel') {
            return $this->prepareExcelData($data);
        } elseif ($format === 'pdf') {
            return $this->preparePdfData($data);
        }

        return $data;
    }

    /**
     * Get session details.
     */
    private function getSessionDetails(CashRegisterSession $session): array
    {
        $transactions = Transaction::where('cash_register_session_id', $session->id)
            ->where('status', 'completed')
            ->get();

        $summary = $this->cashRegisterService->getSessionSummary($session);

        return [
            'session' => $session,
            'opening_amount' => $session->opening_amount,
            'closing_amount' => $session->closing_amount,
            'expected_amount' => $session->expected_amount,
            'difference_amount' => $session->difference_amount,
            'transactions_count' => $transactions->count(),
            'transactions_total' => $transactions->sum('amount'),
            'movements_count' => $summary['movements_count'],
            'cash_in' => $summary['cash_in'],
            'cash_out' => $summary['cash_out'],
            'net_flow' => $summary['net_flow'],
            'by_payment_method' => $this->getSessionPaymentMethodSummary($transactions),
            'duration' => $session->closed_at ?
                $session->opened_at->diffInHours($session->closed_at) :
                $session->opened_at->diffInHours(now()),
        ];
    }

    /**
     * Get summary by payment method. Percentages divide by the mix total.
     */
    private function getSummaryByPaymentMethod($sessions): array
    {
        $paymentMethods = PaymentMethod::all();
        $summary = [];

        foreach ($paymentMethods as $method) {
            $total = 0;
            $count = 0;

            foreach ($sessions as $session) {
                $transactions = Transaction::where('cash_register_session_id', $session->id)
                    ->where('payment_method_id', $method->id)
                    ->where('status', 'completed')
                    ->get();

                $total += $transactions->sum('amount');
                $count += $transactions->count();
            }

            $summary[] = [
                'payment_method' => $method->name,
                'count' => $count,
                'total' => $total,
            ];
        }

        $mixTotal = array_sum(array_column($summary, 'total'));

        return array_map(function (array $row) use ($mixTotal) {
            $row['percentage'] = $mixTotal > 0 ? round($row['total'] / $mixTotal * 100, 2) : 0.0;

            return $row;
        }, $summary);
    }

    /**
     * Get summary by hour, bucketed from completed transactions.
     */
    private function getSummaryByHour($sessions): array
    {
        $buckets = array_fill(0, 24, ['count' => 0, 'total' => 0.0]);

        foreach ($sessions as $session) {
            $transactions = Transaction::where('cash_register_session_id', $session->id)
                ->where('status', 'completed')
                ->get();

            foreach ($transactions as $transaction) {
                $hour = (int) $transaction->created_at->format('H');
                $buckets[$hour]['count']++;
                $buckets[$hour]['total'] += (float) $transaction->amount;
            }
        }

        $hourlyData = [];

        foreach ($buckets as $hour => $bucket) {
            $hourlyData[] = [
                'hour' => $hour,
                'count' => $bucket['count'],
                'total' => round($bucket['total'], 2),
            ];
        }

        return $hourlyData;
    }

    /**
     * Completed sales split into income and expenses for the period.
     *
     * @return array{total_income: float, total_expenses: float}
     */
    private function getFlowTotals(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $row = Transaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereHas('cashRegisterSession', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            })
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type IN ('payment', 'income') THEN amount ELSE 0 END), 0) as total_income, ".
                "COALESCE(SUM(CASE WHEN type IN ('refund', 'expense') THEN amount ELSE 0 END), 0) as total_expenses"
            )
            ->first();

        return [
            'total_income' => round((float) ($row->total_income ?? 0), 2),
            'total_expenses' => round((float) ($row->total_expenses ?? 0), 2),
        ];
    }

    /**
     * Get top transactions.
     */
    private function getTopTransactions($sessions): array
    {
        $allTransactions = collect();

        foreach ($sessions as $session) {
            $transactions = Transaction::where('cash_register_session_id', $session->id)
                ->where('status', 'completed')
                ->with(['patient', 'paymentMethod'])
                ->get();

            $allTransactions = $allTransactions->merge($transactions);
        }

        return $allTransactions->sortByDesc('amount')->take(10)->values()->toArray();
    }

    /**
     * Get daily summary.
     */
    private function getDailySummary(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $dailyData = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $dayStart = $current->copy()->startOfDay();
            $dayEnd = $current->copy()->endOfDay();

            $query = CashRegisterSession::whereBetween('opened_at', [$dayStart, $dayEnd]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $sessions = $query->get();

            $dailyData[] = [
                'date' => $current->format('Y-m-d'),
                'sessions_count' => $sessions->count(),
                'total_opening' => $sessions->sum('opening_amount'),
                'total_closing' => $sessions->sum('closing_amount'),
                'total_difference' => $sessions->sum('difference_amount'),
            ];

            $current->addDay();
        }

        return $dailyData;
    }

    /**
     * Get summary by branch.
     */
    private function getSummaryByBranch($sessions): array
    {
        return $sessions->groupBy('branch_id')
            ->map(function ($group, $branchId) {
                $branch = Branch::find($branchId);

                return [
                    'branch' => $branch ? $branch->name : 'Sucursal no encontrada',
                    'sessions_count' => $group->count(),
                    'total_opening' => $group->sum('opening_amount'),
                    'total_closing' => $group->sum('closing_amount'),
                    'total_difference' => $group->sum('difference_amount'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get summary by user.
     */
    private function getSummaryByUser($sessions): array
    {
        return $sessions->groupBy('user_id')
            ->map(function ($group, $userId) {
                $user = User::find($userId);

                return [
                    'user' => $user ? $user->name : 'Usuario no encontrado',
                    'sessions_count' => $group->count(),
                    'total_opening' => $group->sum('opening_amount'),
                    'total_closing' => $group->sum('closing_amount'),
                    'total_difference' => $group->sum('difference_amount'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get payment methods analysis.
     */
    private function getPaymentMethodsAnalysis(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $query = Transaction::whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->with('paymentMethod');

        if ($branchId) {
            $query->whereHas('cashRegisterSession', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $transactions = $query->get();
        $mixTotal = (float) $transactions->sum('amount');

        return $transactions->groupBy('payment_method_id')
            ->map(function ($group) use ($mixTotal) {
                $total = (float) $group->sum('amount');

                return [
                    'payment_method' => $group->first()->paymentMethod?->name ?? 'Método no encontrado',
                    'count' => $group->count(),
                    'total' => $total,
                    'average' => $group->avg('amount'),
                    'percentage' => $mixTotal > 0 ? round($total / $mixTotal * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get session payment method summary.
     */
    private function getSessionPaymentMethodSummary($transactions): array
    {
        return $transactions->groupBy('payment_method_id')
            ->map(function ($group, $methodId) {
                $method = PaymentMethod::find($methodId);

                return [
                    'payment_method' => $method ? $method->name : 'N/A',
                    'count' => $group->count(),
                    'total' => $group->sum('amount'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Prepare data for Excel export.
     */
    private function prepareExcelData(array $data): array
    {
        // This would be implemented with Laravel Excel package
        return $data;
    }

    /**
     * Prepare data for PDF export.
     */
    private function preparePdfData(array $data): array
    {
        // This would be implemented with DomPDF
        return $data;
    }
}
