<?php

namespace App\Services;

use App\Events\CashMovementCreated;
use App\Events\PaymentRegistered;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Listeners\ClearDashboardCache;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Support\MoneyVocabulary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly AccountingLockService $accountingLocks,
        private readonly PatientStatementService $statements,
    ) {}

    /**
     * Create a new transaction.
     *
     * #287, D4/D7 — the type decides the book the row belongs to. A payment and
     * its refund need a method and an open drawer and write a cash movement; a
     * write-off and an adjustment are ledger entries that touch neither the
     * method nor the drawer and always require a registered administrator
     * authorization.
     */
    public function createTransaction(array $data): Transaction
    {
        $type = $data['type'] ?? MoneyVocabulary::PAYMENT;

        if (! MoneyVocabulary::isCreatable($type)) {
            throw ValidationException::withMessages([
                'type' => ['El tipo de transacción no es válido. Use payment, refund, write_off o adjustment.'],
            ]);
        }

        $this->validateTransactionData($data, $type);
        $authorization = $this->resolveAuthorization($data, $type);

        // Only the cash types need the drawer open; the ledger entries cannot
        // demand one without inventing a session they do not use.
        $cashSession = null;

        if (MoneyVocabulary::movesCash($type)) {
            $cashSession = $this->getActiveCashSession();

            if (! $cashSession) {
                throw ValidationException::withMessages([
                    'session' => ['No hay una sesión de caja abierta. Debe abrir la caja antes de registrar transacciones.'],
                ]);
            }
        }

        DB::beginTransaction();

        try {
            // Two refunds of the same patient can never pass the cap together:
            // the patient row serializes the calculation with the insert, so
            // the amount the second one can return excludes the first one's.
            if ($type === MoneyVocabulary::REFUND) {
                Patient::query()->whereKey($data['patient_id'])->lockForUpdate()->first();
                $this->assertRefundable($data);
            }

            // #287 (T3, D3) — a write-off forgives debt, so it can never forgive
            // more than the patient still owes: without the cap the row invents
            // a pardon bigger than the ledger it is supposed to close.
            if ($type === MoneyVocabulary::WRITE_OFF) {
                $patient = Patient::query()->whereKey($data['patient_id'])->lockForUpdate()->first();
                $this->assertWriteOffWithinBalance($data, $patient);
            }

            // Calculate amounts
            $amounts = $this->calculateAmounts($data);

            // Create transaction
            $transaction = Transaction::create([
                'patient_id' => $data['patient_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'treatment_plan_id' => $data['treatment_plan_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'cash_register_session_id' => $cashSession?->id,
                'created_by' => Auth::id(),
                'transaction_number' => $this->generateTransactionNumber(),
                'type' => $type,
                'amount' => $amounts['total'],
                'subtotal' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount_amount'],
                'discount_type' => $data['discount_type'] ?? null,
                'discount_authorized_by' => $data['discount_authorized_by'] ?? null,
                'authorized_by' => $authorization['authorized_by'],
                'authorization_reason' => $authorization['authorization_reason'],
                'parent_transaction_id' => $data['parent_transaction_id'] ?? null,
                'commission_amount' => $amounts['commission_amount'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            // Only the cash types write a movement; the ledger entries do not.
            $cashMovement = null;

            if ($cashSession instanceof CashRegisterSession && MoneyVocabulary::movesCash($type)) {
                $cashMovement = $this->createCashMovement($transaction, $cashSession);
            }

            DB::commit();

            $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy', 'authorizedBy']);

            // Emitir eventos de WebSocket
            if ($cashMovement instanceof CashMovement) {
                event(new PaymentRegistered($transaction, $cashSession->id));
                event(new CashMovementCreated($cashMovement, $cashSession->id));
            }

            event(new TransactionCreated($transaction));

            // Limpiar cache del dashboard
            ClearDashboardCache::handle();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Void a transaction.
     *
     * Issue #192, decision B: only an administrador may void. The route already
     * answers 403 for the other cash roles; the guard here keeps the contract
     * when the service is called without going through that route.
     *
     * F3 (#283, S5): a transaction dated inside the accounting lock is refused
     * unless the actor registers an override reason; the guard runs before the
     * transaction so the blocked attempt survives it in `audit_logs`.
     */
    public function voidTransaction(int $transactionId, string $reason, ?string $lockOverrideReason = null): Transaction
    {
        if (Auth::user()?->role !== 'administrador') {
            throw new AuthorizationException('Solo un administrador puede anular transacciones.');
        }

        $transaction = Transaction::findOrFail($transactionId);

        if ($transaction->status === 'voided') {
            throw ValidationException::withMessages([
                'transaction' => ['La transacción ya ha sido anulada.'],
            ]);
        }

        if ($transaction->cash_register_session_id &&
            $transaction->cashRegisterSession->status !== 'open') {
            throw ValidationException::withMessages([
                'transaction' => ['No se puede anular una transacción de una sesión de caja cerrada.'],
            ]);
        }

        $this->accountingLocks->guard(
            AccountingLockService::CASH_TRANSACTIONS,
            $transaction->created_at,
            $transaction->cashRegisterSession?->branch_id,
            $transaction,
            'void',
            $lockOverrideReason,
        );

        DB::beginTransaction();

        try {
            $transaction->update([
                'status' => 'voided',
                'notes' => $transaction->notes."\n\nANULADA: ".$reason.' - '.now()->format('d/m/Y H:i:s'),
            ]);

            // Create void cash movement: only the flow the ledger recorded.
            $reversal = $this->reversalMovementType($transaction);

            if ($reversal !== null && $transaction->cash_register_session_id) {
                $this->createVoidCashMovement($transaction, $reversal);
            }

            DB::commit();

            $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy']);

            // Emitir evento de WebSocket
            event(new TransactionUpdated($transaction));

            // Limpiar cache del dashboard
            ClearDashboardCache::handle();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Get transactions with filters.
     */
    public function getTransactions(array $filters = []): array
    {
        $query = Transaction::with(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy']);

        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['cash_register_session_id'])) {
            $query->where('cash_register_session_id', $filters['cash_register_session_id']);
        }

        if (isset($filters['branch_id'])) {
            $query->whereHas('patient', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(15);

        return [
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ];
    }

    /**
     * Generate receipt for transaction.
     */
    public function generateReceipt(Transaction $transaction): array
    {
        $receiptData = [
            'transaction' => $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod']),
            'clinic' => [
                'name' => 'OdontoSuite',
                'address' => 'Dirección de la clínica',
                'phone' => 'Teléfono de la clínica',
                'ruc' => 'RUC de la clínica',
            ],
            'receipt_number' => $transaction->transaction_number,
            'date' => $transaction->created_at->format('d/m/Y H:i:s'),
            'total' => $transaction->amount,
            'subtotal' => $transaction->subtotal,
            'discount' => $transaction->discount_amount,
            'payment_method' => $transaction->paymentMethod->name ?? 'N/A',
        ];

        return $receiptData;
    }

    /**
     * Render the transaction receipt as a PDF binary string.
     * Verify-correction slice: aligns with the POST /transactions/{id}/receipt
     * route contract (Content-Type: application/pdf).
     */
    public function generateReceiptPdf(Transaction $transaction): string
    {
        $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy']);

        $receiptData = [
            'transaction' => $transaction,
            'clinic' => [
                'name' => 'OdontoSuite',
                'address' => 'Dirección de la clínica',
                'phone' => 'Teléfono de la clínica',
                'ruc' => 'RUC de la clínica',
            ],
            'receipt_number' => $transaction->transaction_number,
            'date' => $transaction->created_at->format('d/m/Y H:i:s'),
            'total' => $transaction->amount,
            'subtotal' => $transaction->subtotal,
            'discount' => $transaction->discount_amount,
            'payment_method' => $transaction->paymentMethod->name ?? 'N/A',
        ];

        $pdf = Pdf::loadView('reports.receipt', ['receiptData' => $receiptData]);

        return $pdf->output();
    }

    /**
     * Validate transaction data.
     *
     * #287, D4: the type decides which rules apply. A cash type needs a valid
     * method (and the reference its family demands); a ledger entry cannot
     * declare one, cannot carry a discount and, in the case of an adjustment,
     * cannot be zero.
     */
    private function validateTransactionData(array $data, string $type): void
    {
        if (! isset($data['patient_id'])) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente es requerido.'],
            ]);
        }

        $patient = Patient::find($data['patient_id']);
        if (! $patient) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente seleccionado no es válido.'],
            ]);
        }

        // Validar que el paciente esté activo
        if (! $patient->is_active) {
            throw ValidationException::withMessages([
                'patient_id' => ['No se puede crear una transacción para un paciente inactivo.'],
            ]);
        }

        if (MoneyVocabulary::movesCash($type)) {
            if (! isset($data['payment_method_id']) || ! $paymentMethod = PaymentMethod::find($data['payment_method_id'])) {
                throw ValidationException::withMessages([
                    'payment_method_id' => ['El método de pago seleccionado no es válido.'],
                ]);
            }

            // #291, S4: a method that leaves a voucher or an operation number behind
            // cannot book the charge without it. The guard lives in the service, so
            // both cash doors —the cash-register modal and the pending payment—
            // answer the same rule with the same message.
            if ($paymentMethod->requiresReference() && trim((string) ($data['reference_number'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'reference_number' => ["El método de pago {$paymentMethod->name} exige un número de referencia."],
                ]);
            }
        } elseif (! empty($data['payment_method_id'])) {
            throw ValidationException::withMessages([
                'payment_method_id' => ['Sólo el pago y la devolución llevan método de pago.'],
            ]);
        }

        if ($type !== MoneyVocabulary::PAYMENT) {
            $this->rejectDiscountFields($type, $data);
        }

        if (! isset($data['amount']) || ! is_numeric($data['amount'])) {
            throw ValidationException::withMessages([
                'amount' => ['El monto es requerido.'],
            ]);
        }

        $amount = (float) $data['amount'];

        if ($type === MoneyVocabulary::ADJUSTMENT) {
            // #287, D3: the adjustment is signed, so only zero is meaningless.
            if (round($amount, 2) === 0.0) {
                throw ValidationException::withMessages([
                    'amount' => ['El ajuste no puede ser cero.'],
                ]);
            }
        } elseif ($amount < 0.01) {
            throw ValidationException::withMessages([
                'amount' => ['El monto debe ser mayor a 0.'],
            ]);
        }

        if ($type === MoneyVocabulary::PAYMENT) {
            $this->validateDiscount($data, $amount);
        }
    }

    /**
     * A discount belongs to the charge, so only a payment may declare it.
     */
    private function rejectDiscountFields(string $type, array $data): void
    {
        foreach (['discount_amount', 'discount_type', 'discount_authorized_by'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            throw ValidationException::withMessages([
                $field => ["El descuento es un campo del cobro: no aplica a una transacción de tipo {$type}."],
            ]);
        }
    }

    /**
     * Discount rules of a charge: it can never exceed the amount it discounts,
     * and anything above 10% needs an administrator behind it.
     */
    private function validateDiscount(array $data, float $amount): void
    {
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if ($discountAmount > $amount) {
            throw ValidationException::withMessages([
                'discount_amount' => ['El descuento no puede superar el monto de la transacción.'],
            ]);
        }

        if ($discountAmount > 0 && $amount > 0) {
            $discountPercentage = ($discountAmount / $amount) * 100;

            if ($discountPercentage > 10 && ! isset($data['discount_authorized_by'])) {
                throw ValidationException::withMessages([
                    'discount' => ['Los descuentos mayores al 10% requieren autorización del administrador.'],
                ]);
            }
        }
    }

    /**
     * #287, D2: a refund, a write-off and an adjustment carry the administrator
     * who authorized them and the reason. When the actor is an administrator
     * and declares nobody, it authorizes itself.
     *
     * @return array{authorized_by: int|null, authorization_reason: string|null}
     */
    private function resolveAuthorization(array $data, string $type): array
    {
        if (! MoneyVocabulary::requiresAuthorization($type)) {
            return ['authorized_by' => null, 'authorization_reason' => null];
        }

        $authorizer = User::find($data['authorized_by'] ?? Auth::id());

        if (! $authorizer || $authorizer->role !== 'administrador') {
            throw ValidationException::withMessages([
                'authorized_by' => ['La operación requiere la autorización de un administrador.'],
            ]);
        }

        $reason = trim((string) ($data['authorization_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'authorization_reason' => ['El motivo de la autorización es requerido.'],
            ]);
        }

        return ['authorized_by' => (int) $authorizer->id, 'authorization_reason' => $reason];
    }

    /**
     * #287, D2/D3: a refund returns money that was actually collected. It is
     * capped by what the parent payment still owes and, globally, by everything
     * the patient has paid and not been given back.
     */
    private function assertRefundable(array $data): void
    {
        $amount = round((float) $data['amount'], 2);
        $patientId = (int) $data['patient_id'];
        $parentId = $data['parent_transaction_id'] ?? null;

        if ($parentId !== null) {
            $parent = Transaction::find($parentId);

            if (! $parent
                || $parent->type !== MoneyVocabulary::PAYMENT
                || $parent->status !== 'completed'
                || (int) $parent->patient_id !== $patientId) {
                throw ValidationException::withMessages([
                    'parent_transaction_id' => ['La devolución debe referirse a un pago completado del mismo paciente.'],
                ]);
            }

            $alreadyRefunded = (float) Transaction::query()
                ->where('parent_transaction_id', $parent->id)
                ->where('type', MoneyVocabulary::REFUND)
                ->where('status', 'completed')
                ->sum('amount');

            $remaining = round((float) $parent->amount - $alreadyRefunded, 2);

            if ($amount > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => ['La devolución no puede superar lo devolvible de ese pago ('.number_format($remaining, 2, '.', '').').'],
                ]);
            }
        }

        $collected = (float) Transaction::query()
            ->where('patient_id', $patientId)
            ->where('type', MoneyVocabulary::PAYMENT)
            ->where('status', 'completed')
            ->sum('amount');

        $refunded = (float) Transaction::query()
            ->where('patient_id', $patientId)
            ->where('type', MoneyVocabulary::REFUND)
            ->where('status', 'completed')
            ->sum('amount');

        $available = round($collected - $refunded, 2);

        if ($amount > $available + 0.001) {
            throw ValidationException::withMessages([
                'amount' => ['La devolución no puede superar el dinero cobrado no devuelto del paciente ('.number_format($available, 2, '.', '').').'],
            ]);
        }
    }

    /**
     * #287 (T3, D3): a write-off closes debt that exists. It is capped by the
     * patient's outstanding balance, so forgiving more than what is owed is
     * refused before anything is written.
     */
    private function assertWriteOffWithinBalance(array $data, ?Patient $patient): void
    {
        if (! $patient instanceof Patient) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente seleccionado no es válido.'],
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        $outstanding = round($this->statements->outstandingBalance($patient), 2);

        if ($amount > $outstanding + 0.001) {
            throw ValidationException::withMessages([
                'amount' => ['La condonación no puede superar el saldo pendiente del paciente ('.number_format($outstanding, 2, '.', '').').'],
            ]);
        }
    }

    /**
     * Get active cash session.
     */
    private function getActiveCashSession(): ?CashRegisterSession
    {
        return CashRegisterSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();
    }

    /**
     * Calculate transaction amounts.
     */
    private function calculateAmounts(array $data): array
    {
        $subtotal = $data['amount'];
        $discountAmount = $data['discount_amount'] ?? 0;
        $total = $subtotal - $discountAmount;

        // Calculate commission if applicable. A ledger entry has no method, so
        // it can never carry a commission.
        $paymentMethod = PaymentMethod::find($data['payment_method_id'] ?? null);
        $commissionAmount = 0;

        if ($paymentMethod && $paymentMethod->commission_percentage > 0) {
            $commissionAmount = $total * ($paymentMethod->commission_percentage / 100);
        }

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'commission_amount' => $commissionAmount,
        ];
    }

    /**
     * Generate unique transaction number.
     */
    private function generateTransactionNumber(): string
    {
        $date = now()->format('Ymd');
        $lastTransaction = Transaction::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransaction ?
            (int) substr($lastTransaction->transaction_number, -4) + 1 : 1;

        return 'TXN-'.$date.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create cash movement for a cash transaction.
     */
    private function createCashMovement(Transaction $transaction, CashRegisterSession $session): CashMovement
    {
        $movementType = MoneyVocabulary::cashMovementType($transaction->type);

        if ($movementType === null) {
            throw new \LogicException("Transaction type {$transaction->type} does not move cash.");
        }

        return CashMovement::create([
            'cash_register_session_id' => $session->id,
            'transaction_id' => $transaction->id,
            'created_by' => Auth::id(),
            'type' => $movementType,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'reference' => $transaction->transaction_number,
        ]);
    }

    /**
     * Flow a void has to reverse: the ledger row the transaction actually wrote,
     * or the vocabulary when no row exists (a transaction seeded without its
     * movement, or a legacy `income`/`expense` row). A non-cash entry reverses
     * nothing.
     */
    private function reversalMovementType(Transaction $transaction): ?string
    {
        $flow = CashMovement::query()
            ->where('transaction_id', $transaction->id)
            ->where('reference', $transaction->transaction_number)
            ->value('type');

        $flow ??= MoneyVocabulary::cashMovementType($transaction->type);

        if ($flow === null && in_array($transaction->type, ['income', 'expense'], true)) {
            $flow = $transaction->type;
        }

        return match ($flow) {
            'income' => 'expense',
            'expense' => 'income',
            default => null,
        };
    }

    /**
     * Create void cash movement.
     */
    private function createVoidCashMovement(Transaction $transaction, string $movementType): void
    {
        \App\Models\CashMovement::create([
            'cash_register_session_id' => $transaction->cash_register_session_id,
            'transaction_id' => $transaction->id,
            'created_by' => Auth::id(),
            'type' => $movementType, // Reverse the type
            'amount' => $transaction->amount,
            'description' => 'Anulación de transacción: '.$transaction->transaction_number,
            'notes' => 'Transacción anulada',
            'reference' => 'VOID-'.$transaction->transaction_number,
        ]);
    }
}
