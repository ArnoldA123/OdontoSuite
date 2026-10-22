<?php

namespace App\Services\Reports;

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\CashRegisterService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        $paymentMethodSummary = $this->getSummaryByPaymentMethod($sessions);

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
            'summary_by_payment_method' => $paymentMethodSummary,
            'reconciliation' => $this->getReconciliation($paymentMethodSummary),
            'summary_by_hour' => $this->getSummaryByHour($sessions),
            'top_transactions' => $this->getTopTransactions($sessions),
        ];

        return $report;
    }

    /**
     * #291, S5 — the day reconciles by family: physical cash is counted in the
     * drawer, every other family settles against a bank, a POS or a wallet
     * statement. Both blocks add up to the same mix the method summary shows.
     *
     * @param  array<int, array<string, mixed>>                                                          $summary
     * @return array{cash: array{count: int, total: float}, electronic: array{count: int, total: float}}
     */
    private function getReconciliation(array $summary): array
    {
        $reconciliation = [
            'cash' => ['count' => 0, 'total' => 0.0],
            'electronic' => ['count' => 0, 'total' => 0.0],
        ];

        foreach ($summary as $row) {
            $bucket = ($row['type'] ?? PaymentMethod::TYPE_CASH) === PaymentMethod::TYPE_CASH
                ? 'cash'
                : 'electronic';

            $reconciliation[$bucket]['count'] += (int) $row['count'];
            $reconciliation[$bucket]['total'] += (float) $row['total'];
        }

        $reconciliation['cash']['total'] = round($reconciliation['cash']['total'], 2);
        $reconciliation['electronic']['total'] = round($reconciliation['electronic']['total'], 2);

        return $reconciliation;
    }

    /**
     * Get period cash report.
     *
     * Issue #197, decision A: the report has one population. Sales, the payment
     * methods analysis and the session block all follow completed transactions
     * created inside `[start, end]`; the block keeps the sessions that had
     * activity (a transaction or a movement) in that range, so it reconciles
     * with the sales block even when a session crosses midnight.
     */
    public function getPeriodReport(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $sessions = $this->getSessionsWithActivity($start, $end, $branchId);
        $flow = $this->getFlowTotals($start, $end, $branchId);

        $report = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'population' => [
                'basis' => 'transaction_date',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => 'Transacciones completadas creadas dentro del rango (ventas, mix y top) y sesiones con transacciones o movimientos dentro del rango.',
            ],
            'sessions_count' => $sessions->count(),
            'total_opening_amount' => $sessions->sum('opening_amount'),
            'total_closing_amount' => $sessions->sum('closing_amount'),
            'total_expected_amount' => $sessions->sum('expected_amount'),
            'total_difference' => $sessions->sum('difference_amount'),
            'average_difference' => $sessions->avg('difference_amount'),
            'total_income' => $flow['total_income'],
            'total_expenses' => $flow['total_expenses'],
            'daily_summary' => $this->getDailySummary($start, $end, $branchId, $sessions),
            'summary_by_branch' => $this->getSummaryByBranch($sessions),
            'summary_by_user' => $this->getSummaryByUser($sessions),
            'payment_methods_analysis' => $this->getPaymentMethodsAnalysis($start, $end, $branchId),
        ];

        return $report;
    }

    /**
     * Sessions with activity (a transaction or a movement) inside the range.
     *
     * One query with two `exists` subqueries keeps the session block at a
     * single round trip, whatever the number of sessions in the period.
     */
    private function getSessionsWithActivity(Carbon $start, Carbon $end, ?int $branchId = null): Collection
    {
        return CashRegisterSession::with(['user', 'branch'])
            ->where(function ($query) use ($start, $end) {
                $query->whereHas('transactions', function ($transactions) use ($start, $end) {
                    $transactions->whereBetween('created_at', [$start, $end]);
                })->orWhereHas('movements', function ($movements) use ($start, $end) {
                    $movements->whereBetween('created_at', [$start, $end]);
                });
            })
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();
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
     * Completed transactions of the given session block.
     *
     * @param Collection<int, CashRegisterSession> $sessions
     */
    private function completedTransactionsIn(Collection $sessions)
    {
        return Transaction::query()
            ->whereIn('cash_register_session_id', $sessions->pluck('id')->all())
            ->where('status', 'completed');
    }

    /**
     * Get summary by payment method. Percentages divide by the mix total.
     *
     * One grouped query for the whole block instead of one query per session
     * and payment method; every payment method keeps its row, used or not.
     */
    private function getSummaryByPaymentMethod(Collection $sessions): array
    {
        $totals = $this->completedTransactionsIn($sessions)
            ->selectRaw('payment_method_id, COUNT(*) as aggregate_count, COALESCE(SUM(amount), 0) as aggregate_total')
            ->groupBy('payment_method_id')
            ->get()
            ->keyBy('payment_method_id');

        $summary = PaymentMethod::all()
            ->map(function (PaymentMethod $method) use ($totals) {
                $row = $totals->get($method->id);

                return [
                    'payment_method' => $method->name,
                    'type' => $method->type,
                    'count' => (int) ($row->aggregate_count ?? 0),
                    'total' => (float) ($row->aggregate_total ?? 0),
                ];
            })
            ->all();

        $mixTotal = array_sum(array_column($summary, 'total'));

        return array_map(function (array $row) use ($mixTotal) {
            $row['percentage'] = $mixTotal > 0 ? round($row['total'] / $mixTotal * 100, 2) : 0.0;

            return $row;
        }, $summary);
    }

    /**
     * Get summary by hour, bucketed from completed transactions.
     *
     * One grouped query, and the 24 buckets are always returned in order so the
     * consumer can chart the shape of the day.
     */
    private function getSummaryByHour(Collection $sessions): array
    {
        $buckets = $this->completedTransactionsIn($sessions)
            ->selectRaw('HOUR(created_at) as bucket_hour, COUNT(*) as aggregate_count, COALESCE(SUM(amount), 0) as aggregate_total')
            ->groupByRaw('HOUR(created_at)')
            ->get()
            ->keyBy('bucket_hour');

        $hourlyData = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $bucket = $buckets->get($hour);

            $hourlyData[] = [
                'hour' => $hour,
                'count' => (int) ($bucket->aggregate_count ?? 0),
                'total' => round((float) ($bucket->aggregate_total ?? 0), 2),
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
     *
     * One query over the whole block; the ordering and the limit belong to the
     * database so only ten rows ever travel.
     */
    private function getTopTransactions(Collection $sessions): array
    {
        return $this->completedTransactionsIn($sessions)
            ->with(['patient', 'paymentMethod'])
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get daily summary.
     *
     * No query per day: two grouped queries build the whole range. The first
     * one groups the completed transactions by day and session, so it feeds
     * both the sales rows and the session activity; the second one adds the
     * days whose only activity was a cash movement. The snapshot amounts come
     * from the session block the period report already loaded.
     *
     * @param Collection<int, CashRegisterSession> $sessions
     */
    private function getDailySummary(Carbon $start, Carbon $end, ?int $branchId, Collection $sessions): array
    {
        $transactionDays = Transaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereHas('cashRegisterSession', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            })
            ->selectRaw(
                'DATE(created_at) as day, cash_register_session_id as session_id, '.
                "COALESCE(SUM(CASE WHEN type IN ('payment', 'income') THEN amount ELSE 0 END), 0) as total_income, ".
                "COALESCE(SUM(CASE WHEN type IN ('refund', 'expense') THEN amount ELSE 0 END), 0) as total_expenses"
            )
            ->groupByRaw('DATE(created_at), cash_register_session_id')
            ->get();

        $movementDays = CashMovement::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereHas('cashRegisterSession', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            })
            ->selectRaw('DATE(created_at) as day, cash_register_session_id as session_id')
            ->groupByRaw('DATE(created_at), cash_register_session_id')
            ->get();

        $sessionIdsByDay = [];

        foreach ($transactionDays as $row) {
            if ($row->session_id !== null) {
                $sessionIdsByDay[$row->day][(int) $row->session_id] = true;
            }
        }

        foreach ($movementDays as $row) {
            if ($row->session_id !== null) {
                $sessionIdsByDay[$row->day][(int) $row->session_id] = true;
            }
        }

        $salesByDay = $transactionDays->groupBy('day');
        $sessionsById = $sessions->keyBy('id');

        $dailyData = [];
        $current = $start->copy()->startOfDay();

        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');

            $daySessions = collect(array_keys($sessionIdsByDay[$date] ?? []))
                ->map(function (int $sessionId) use ($sessionsById) {
                    return $sessionsById->get($sessionId);
                })
                ->filter()
                ->values();

            $daySales = $salesByDay->get($date, collect());

            $dailyData[] = [
                'date' => $date,
                'sessions_count' => $daySessions->count(),
                'total_opening' => $daySessions->sum('opening_amount'),
                'total_closing' => $daySessions->sum('closing_amount'),
                'total_difference' => $daySessions->sum('difference_amount'),
                'total_income' => round((float) $daySales->sum('total_income'), 2),
                'total_expenses' => round((float) $daySales->sum('total_expenses'), 2),
            ];

            $current->addDay();
        }

        return $dailyData;
    }

    /**
     * Get summary by branch, read from the relations the block already loaded.
     */
    private function getSummaryByBranch(Collection $sessions): array
    {
        return $sessions->groupBy('branch_id')
            ->map(function (Collection $group) {
                return [
                    'branch' => $group->first()->branch?->name ?? 'Sucursal no encontrada',
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
     * Get summary by user, read from the relations the block already loaded.
     */
    private function getSummaryByUser(Collection $sessions): array
    {
        return $sessions->groupBy('user_id')
            ->map(function (Collection $group) {
                return [
                    'user' => $group->first()->user?->name ?? 'Usuario no encontrado',
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
                    'type' => $group->first()->paymentMethod?->type ?? PaymentMethod::TYPE_CASH,
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
