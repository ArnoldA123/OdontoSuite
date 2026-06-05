<?php

namespace App\Services;

use App\Events\CashSessionOpened;
use App\Events\CashSessionClosed;
use App\Models\CashRegisterSession;
use App\Models\CashMovement;
use App\Models\Transaction;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    /**
     * Open a new cash register session
     */
    public function openSession(array $data): CashRegisterSession
    {
        if (!$this->userCanManageCashRegister()) {
            throw ValidationException::withMessages([
                'session' => ['No tienes permisos para abrir sesiones de caja.'],
            ]);
        }

        $this->validateOpenSession($data);

        // Check if there's already an open session for this user/branch
        $existingSession = CashRegisterSession::where('user_id', Auth::id())
            ->where('branch_id', $data['branch_id'])
            ->where('status', 'open')
            ->first();

        if ($existingSession) {
            throw ValidationException::withMessages([
                'session' => ['Ya existe una sesión de caja abierta para este usuario y sucursal.'],
            ]);
        }

        DB::beginTransaction();
        try {
            $session = CashRegisterSession::create([
                'user_id' => Auth::id(),
                'branch_id' => $data['branch_id'],
                'opening_amount' => $data['opening_amount'],
                'opened_at' => now(),
                'opening_notes' => isset($data['opening_notes']) ? substr($data['opening_notes'], 0, 500) : null,
                'status' => 'open'
            ]);

            // Create initial cash movement
            CashMovement::create([
                'cash_register_session_id' => $session->id,
                'created_by' => Auth::id(),
                'type' => 'deposit',
                'amount' => $data['opening_amount'],
                'description' => 'Apertura de caja',
                'notes' => isset($data['opening_notes']) ? substr($data['opening_notes'], 0, 500) : null,
                'reference' => 'OPEN-' . str_pad($session->id, 6, '0', STR_PAD_LEFT)
            ]);

            DB::commit();

            $session->load(['user', 'branch', 'movements']);

            // Emitir evento de WebSocket
            event(new CashSessionOpened($session));

            return $session;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Close cash register session with arqueo
     */
    public function closeSession(int $sessionId, array $data): CashRegisterSession
    {
        $session = CashRegisterSession::findOrFail($sessionId);

        if ($session->status !== 'open') {
            throw ValidationException::withMessages([
                'session' => ['La sesión de caja no está abierta.'],
            ]);
        }

        if (!$this->userCanManageCashRegister()) {
            throw ValidationException::withMessages([
                'session' => ['No tienes permisos para cerrar sesiones de caja.'],
            ]);
        }

        DB::beginTransaction();
        try {
            // Calculate expected amount
            $expectedAmount = $this->calculateExpectedAmount($session);

            // Calculate difference
            $difference = $data['closing_amount'] - $expectedAmount;

            $session->update([
                'closing_amount' => $data['closing_amount'],
                'expected_amount' => $expectedAmount,
                'difference_amount' => $difference,
                'closed_at' => now(),
                'closing_notes' => $data['closing_notes'] ?? null,
                'arqueo_data' => $data['arqueo'] ?? null,
                'status' => 'closed'
            ]);

            // Create closing cash movement
            CashMovement::create([
                'cash_register_session_id' => $session->id,
                'created_by' => Auth::id(),
                'type' => 'adjustment',
                'amount' => $data['closing_amount'],
                'description' => 'Cierre de caja',
                'notes' => $data['closing_notes'] ?? null,
                'reference' => 'CLOSE-' . $session->id
            ]);

            DB::commit();

            $session->load(['user', 'branch', 'movements']);

            // Emitir evento de WebSocket
            event(new CashSessionClosed($session));

            return $session;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getCurrentSession(?int $branchId = null): ?CashRegisterSession
    {
        $query = CashRegisterSession::where('status', 'open');

        if (!$this->userCanManageCashRegister()) {
            $query->where('user_id', Auth::id());
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif (Auth::user()?->branch_id) {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        return $query->with(['user', 'branch', 'movements'])
            ->latest('opened_at')
            ->first();
    }

    /**
     * Get session summary
     */
    public function getSessionSummary(CashRegisterSession $session): array
    {
        // Obtener todas las transacciones de la sesión (con logging para debug)
        $allTransactions = Transaction::where('cash_register_session_id', $session->id)->get();
        
        \Log::info('getSessionSummary: Transacciones encontradas', [
            'session_id' => $session->id,
            'total_transactions' => $allTransactions->count(),
            'transactions_by_type' => $allTransactions->groupBy('type')->map->count(),
            'transactions_by_status' => $allTransactions->groupBy('status')->map->count()
        ]);

        // Filtrar solo las completadas
        $transactions = $allTransactions->where('status', 'completed');

        \Log::info('getSessionSummary: Transacciones completadas', [
            'completed_count' => $transactions->count(),
            'by_type' => $transactions->groupBy('type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount')
                ];
            })
        ]);

        $movements = $session->movements;

        // Calcular totales de manera más explícita
        // Buscar tanto 'payment' como 'income' para compatibilidad
        $paymentTransactions = $transactions->filter(function($t) {
            return in_array($t->type, ['payment', 'income']);
        });
        $refundTransactions = $transactions->filter(function($t) {
            return in_array($t->type, ['refund', 'expense']);
        });
        
        $totalIncome = $paymentTransactions->sum('amount');
        $totalExpenses = $refundTransactions->sum('amount');

        \Log::info('getSessionSummary: Totales calculados', [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'payment_count' => $paymentTransactions->count(),
            'refund_count' => $refundTransactions->count()
        ]);

        $summary = [
            'session' => $session,
            'opening_amount' => $session->opening_amount ?? 0,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_movements' => $movements->sum('amount'),
            'expected_amount' => $this->calculateExpectedAmount($session),
            'transactions_count' => $transactions->count(),
            'movements_count' => $movements->count(),
            'by_payment_method' => $this->getSummaryByPaymentMethod($transactions),
            'by_hour' => $this->getSummaryByHour($transactions)
        ];

        return $summary;
    }

    /**
     * Get sessions list with filters
     */
    public function getSessions(array $filters = []): array
    {
        $query = CashRegisterSession::with(['user', 'branch', 'movements']);

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('opened_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('opened_at', '<=', $filters['date_to']);
        }

        $sessions = $query->orderBy('opened_at', 'desc')->paginate(15);

        return [
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ]
        ];
    }

    /**
     * Validate open session data
     */
    private function validateOpenSession(array $data): void
    {
        if (!isset($data['branch_id']) || !Branch::find($data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => ['La sucursal seleccionada no es válida.'],
            ]);
        }

        if (!isset($data['opening_amount']) || $data['opening_amount'] < 0) {
            throw ValidationException::withMessages([
                'opening_amount' => ['El monto de apertura debe ser mayor o igual a 0.'],
            ]);
        }
    }

    /**
     * Calculate expected amount for closing.
     * Uses cash movements only (transactions already create movements via TransactionService).
     */
    private function calculateExpectedAmount(CashRegisterSession $session): float
    {
        $openingAmount = (float) $session->opening_amount;

        $incomeMovements = (float) $session->movements()
            ->where('type', 'income')
            ->sum('amount');

        $expenseMovements = (float) $session->movements()
            ->where('type', 'expense')
            ->sum('amount');

        $withdrawalMovements = (float) $session->movements()
            ->where('type', 'withdrawal')
            ->sum('amount');

        return round($openingAmount + $incomeMovements - $expenseMovements - $withdrawalMovements, 2);
    }

    /**
     * Only administrador and finanzas may open or close cash register sessions.
     */
    private function userCanManageCashRegister(): bool
    {
        return in_array(Auth::user()?->role, ['administrador', 'finanzas'], true);
    }

    /**
     * Get summary by payment method
     */
    private function getSummaryByPaymentMethod($transactions): array
    {
        return $transactions->groupBy('payment_method_id')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount')
                ];
            })
            ->toArray();
    }

    /**
     * Get summary by hour
     */
    private function getSummaryByHour($transactions): array
    {
        return $transactions->groupBy(function ($transaction) {
                return Carbon::parse($transaction->created_at)->format('H');
            })
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount')
                ];
            })
            ->toArray();
    }
}

