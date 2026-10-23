<?php

namespace App\Services;

use App\Authorization\BranchAccess;
use App\Models\CashDeposit;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #287 (T4, S5/S7) — the deposit and the reconciliation of the drawer.
 *
 * `register()` is the only writer: the deposit is money leaving the gaveta, so
 * it is only accepted while the session is open (a deposit is registered before
 * closing the drawer), for an amount the drawer actually holds, and it writes
 * the `withdrawal` cash movement that keeps the session expected amount true
 * (D5). Both rows are written in one transaction: a deposit without its
 * movement would leave the arqueo lying.
 */
class CashDepositService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CashRegisterService $cashRegister) {}

    /**
     * @param array<string, mixed> $data
     */
    public function register(array $data): CashDeposit
    {
        $session = CashRegisterSession::find($data['cash_register_session_id'] ?? null);

        if ($session === null) {
            throw ValidationException::withMessages([
                'cash_register_session_id' => ['La sesión de caja seleccionada no existe.'],
            ]);
        }

        // The deposit belongs to the sede of its drawer; naming another one is
        // a 403, not an empty result (ADR-0010).
        BranchAccess::assertBranch(
            Auth::user(),
            $session->branch_id === null ? null : (int) $session->branch_id,
        );

        if ($session->status !== 'open') {
            throw ValidationException::withMessages([
                'cash_register_session_id' => ['Solo se puede depositar en una sesión de caja abierta.'],
            ]);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El monto del depósito debe ser mayor que cero.'],
            ]);
        }

        $reference = trim((string) ($data['reference'] ?? ''));

        if ($reference === '') {
            throw ValidationException::withMessages([
                'reference' => ['La referencia bancaria del depósito es requerida.'],
            ]);
        }

        $depositedAt = isset($data['deposited_at'])
            ? Carbon::parse((string) $data['deposited_at'])
            : now();

        if ($depositedAt->copy()->startOfDay()->isAfter(today())) {
            throw ValidationException::withMessages([
                'deposited_at' => ['La fecha del depósito no puede ser futura.'],
            ]);
        }

        return DB::transaction(function () use ($session, $amount, $reference, $depositedAt, $data): CashDeposit {
            // Locking the drawer serializes concurrent deposits: the second one
            // reads the cash the first one already took out.
            $locked = CashRegisterSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'cash_register_session_id' => ['Solo se puede depositar en una sesión de caja abierta.'],
                ]);
            }

            $available = round(
                (float) $this->cashRegister->getSessionSummary($locked)['expected_amount'],
                2,
            );

            if ($amount > $available + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => [
                        'El monto del depósito no puede superar el efectivo disponible en la gaveta ('
                        .number_format($available, 2, '.', '').').',
                    ],
                ]);
            }

            $deposit = CashDeposit::create([
                'branch_id' => $locked->branch_id,
                'cash_register_session_id' => $locked->id,
                'created_by' => Auth::id(),
                'amount' => $amount,
                'deposited_at' => $depositedAt,
                'reference' => $reference,
                'notes' => $data['notes'] ?? null,
            ]);

            $movement = CashMovement::create([
                'cash_register_session_id' => $locked->id,
                'created_by' => Auth::id(),
                'branch_id' => $locked->branch_id,
                'type' => 'withdrawal',
                'concept' => 'deposit',
                'amount' => $amount,
                'description' => 'Depósito bancario',
                'notes' => $data['notes'] ?? null,
                'reference' => $reference,
            ]);

            $deposit->update(['cash_movement_id' => $movement->id]);

            return $deposit->load(['cashRegisterSession', 'branch', 'cashMovement', 'createdBy']);
        });
    }

    /**
     * Paginated deposit list, scoped to the effective sede.
     *
     * #320 (T1, S8) — `cash_register_session_id` restricts the list to a single
     * drawer, so the closure reads the deposits of its own turn. The sede filter
     * is applied on top: a session of another sede stays out of reach.
     *
     * @param  array<string, mixed>                                           $filters
     * @return array{data: array<int, CashDeposit>, meta: array<string, int>}
     */
    public function index(array $filters = []): array
    {
        $requested = isset($filters['branch_id']) && $filters['branch_id'] !== ''
            ? (int) $filters['branch_id']
            : null;

        $branchId = BranchAccess::effectiveFilter(Auth::user(), $requested);

        $query = CashDeposit::with(['cashRegisterSession', 'branch', 'cashMovement', 'createdBy']);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if (isset($filters['cash_register_session_id']) && $filters['cash_register_session_id'] !== '') {
            $query->where('cash_register_session_id', (int) $filters['cash_register_session_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('deposited_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('deposited_at', '<=', $filters['date_to']);
        }

        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $deposits = $query
            ->orderByDesc('deposited_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => $deposits->items(),
            'meta' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'per_page' => $deposits->perPage(),
                'total' => $deposits->total(),
            ],
        ];
    }
}
