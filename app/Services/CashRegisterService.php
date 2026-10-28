<?php

namespace App\Services;

use App\Events\CashSessionClosed;
use App\Events\CashSessionOpened;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    /**
     * Open a new cash register session.
     */
    public function openSession(array $data): CashRegisterSession
    {
        if (! $this->userCanManageCashRegister()) {
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
                'status' => 'open',
            ]);

            // Create initial cash movement
            CashMovement::create([
                'cash_register_session_id' => $session->id,
                'created_by' => Auth::id(),
                'type' => 'deposit',
                'amount' => $data['opening_amount'],
                'description' => 'Apertura de caja',
                'notes' => isset($data['opening_notes']) ? substr($data['opening_notes'], 0, 500) : null,
                'reference' => 'OPEN-'.str_pad($session->id, 6, '0', STR_PAD_LEFT),
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
     * Close cash register session with arqueo.
     */
    public function closeSession(int $sessionId, array $data): CashRegisterSession
    {
        $session = CashRegisterSession::findOrFail($sessionId);

        if ($session->status !== 'open') {
            throw ValidationException::withMessages([
                'session' => ['La sesión de caja no está abierta.'],
            ]);
        }

        if (! $this->userCanManageCashRegister()) {
            throw ValidationException::withMessages([
                'session' => ['No tienes permisos para cerrar sesiones de caja.'],
            ]);
        }

        DB::beginTransaction();

        try {
            // #323 (S7) — the closure reads the drawer with the same lock the
            // deposit and the refund take, so a payment landing mid-close
            // cannot be left out of the arqueo.
            $session = CashRegisterSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'open') {
                throw ValidationException::withMessages([
                    'session' => ['La sesión de caja no está abierta.'],
                ]);
            }

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
                'status' => 'closed',
            ]);

            // Create closing cash movement
            CashMovement::create([
                'cash_register_session_id' => $session->id,
                'created_by' => Auth::id(),
                'type' => 'adjustment',
                'amount' => $data['closing_amount'],
                'description' => 'Cierre de caja',
                'notes' => $data['closing_notes'] ?? null,
                'reference' => 'CLOSE-'.$session->id,
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
        return $this->actorSessionQuery('open', $branchId)
            ->latest('opened_at')
            ->first();
    }

    /**
     * The actor's most recent session of the branch, whatever its status.
     *
     * #407 — `/cash-register/current` answers this when the actor has no open
     * drawer, so the screen can still say "Sesión cerrada" with its counts
     * instead of falling back to somebody else's open session.
     */
    public function getLatestSession(?int $branchId = null): ?CashRegisterSession
    {
        return $this->actorSessionQuery(null, $branchId)
            ->latest('opened_at')
            ->first();
    }

    /**
     * Scope a session read to the actor, and to their branch unless one is
     * named. The ownership rule is the same one CashRegisterPolicy::view
     * enforces (#193): a foreign drawer is read through
     * `GET /api/cash-register/sessions/{id}`, never through this lookup.
     */
    private function actorSessionQuery(?string $status, ?int $branchId): Builder
    {
        $query = CashRegisterSession::query()->where('user_id', Auth::id());

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif (Auth::user()?->branch_id) {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        return $query->with(['user', 'branch', 'movements']);
    }

    /**
     * Empty summary shape for days without a cash session (issue #52).
     * Mirrors the keys of getSessionSummary() so consumers can render
     * an empty state instead of handling a null payload.
     */
    public function getEmptySummary(): array
    {
        return [
            'session' => null,
            'opening_amount' => 0,
            'total_income' => 0,
            'total_expenses' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'net_flow' => 0,
            'expected_amount' => 0,
            'transactions_count' => 0,
            'movements_count' => 0,
            'by_payment_method' => [],
            'by_hour' => [],
        ];
    }

    /**
     * Get session summary.
     */
    public function getSessionSummary(CashRegisterSession $session): array
    {
        $transactions = Transaction::with('paymentMethod')
            ->where('cash_register_session_id', $session->id)
            ->where('status', 'completed')
            ->get();

        $paymentTransactions = $transactions->filter(function ($t) {
            return in_array($t->type, ['payment', 'income']);
        });
        $refundTransactions = $transactions->filter(function ($t) {
            return in_array($t->type, ['refund', 'expense']);
        });

        $flow = $this->cashFlow($session);

        $summary = [
            'session' => $session,
            'opening_amount' => $session->opening_amount ?? 0,
            'total_income' => round((float) $paymentTransactions->sum('amount'), 2),
            'total_expenses' => round((float) $refundTransactions->sum('amount'), 2),
            'cash_in' => $flow['cash_in'],
            'cash_out' => $flow['cash_out'],
            'net_flow' => round($flow['cash_in'] - $flow['cash_out'], 2),
            'expected_amount' => $this->calculateExpectedAmount($session),
            'transactions_count' => $transactions->count(),
            'movements_count' => $session->movements()->count(),
            'by_payment_method' => $this->getSummaryByPaymentMethod($transactions),
            'by_hour' => $this->getSummaryByHour($transactions),
        ];

        return $summary;
    }

    /**
     * Get sessions list with filters.
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
            ],
        ];
    }

    /**
     * Validate open session data.
     */
    private function validateOpenSession(array $data): void
    {
        if (! isset($data['branch_id']) || ! Branch::find($data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => ['La sucursal seleccionada no es válida.'],
            ]);
        }

        if (! isset($data['opening_amount']) || $data['opening_amount'] < 0) {
            throw ValidationException::withMessages([
                'opening_amount' => ['El monto de apertura debe ser mayor o igual a 0.'],
            ]);
        }
    }

    /**
     * Reference this service stamps on the opening snapshot.
     */
    private function openingReference(CashRegisterSession $session): string
    {
        return CashMovement::openingReference((int) $session->id);
    }

    /**
     * Reference this service stamps on the closing snapshot.
     */
    private function closingReference(CashRegisterSession $session): string
    {
        return CashMovement::closingReference((int) $session->id);
    }

    /**
     * Physical cash flows of the session: the manual movements plus the rows
     * TransactionService derives from transactions.
     *
     * The opening and closing rows are snapshots of the drawer, not flows:
     * `opening_amount` already counts the first and `closing_amount` records
     * the count, so neither may enter the equation. `adjustment` rows stay out
     * too: the closing snapshot is their only writer today and a manual
     * adjustment carries no defined sign.
     *
     * @return array{cash_in: float, cash_out: float}
     */
    private function cashFlow(CashRegisterSession $session): array
    {
        $snapshots = [$this->openingReference($session), $this->closingReference($session)];

        $flows = $session->movements()
            ->where(function ($query) use ($snapshots) {
                $query->whereNotIn('reference', $snapshots)->orWhereNull('reference');
            })
            ->get(['type', 'amount']);

        return [
            'cash_in' => round((float) $flows->whereIn('type', ['income', 'deposit'])->sum('amount'), 2),
            'cash_out' => round((float) $flows->whereIn('type', ['expense', 'withdrawal'])->sum('amount'), 2),
        ];
    }

    /**
     * Cash position the drawer should hold at closing.
     */
    private function calculateExpectedAmount(CashRegisterSession $session): float
    {
        $flow = $this->cashFlow($session);

        return round((float) $session->opening_amount + $flow['cash_in'] - $flow['cash_out'], 2);
    }

    /**
     * Only administrador, finanzas and recepcionista may open or close cash
     * register sessions (issue #55: recepcion opera caja).
     */
    private function userCanManageCashRegister(): bool
    {
        return in_array(Auth::user()?->role, ['administrador', 'finanzas', 'recepcionista'], true);
    }

    /**
     * Get summary by payment method.
     */
    private function getSummaryByPaymentMethod($transactions): array
    {
        return $transactions
            ->groupBy('payment_method_id')
            ->map(function ($group) {
                return [
                    'payment_method_id' => $group->first()->payment_method_id,
                    'payment_method' => $group->first()->paymentMethod?->name ?? 'Sin método',
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get summary by hour.
     */
    private function getSummaryByHour($transactions): array
    {
        return $transactions
            ->groupBy(function ($transaction) {
                return Carbon::parse($transaction->created_at)->format('H');
            })
            ->sortKeys()
            ->map(function ($group, $hour) {
                return [
                    'hour' => (string) $hour,
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->values()
            ->toArray();
    }
}
