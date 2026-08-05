<?php

namespace App\Services;

use App\Events\PaymentRegistered;
use App\Events\CashMovementCreated;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Listeners\ClearDashboardCache;
use App\Models\Transaction;
use App\Models\CashRegisterSession;
use App\Models\CashMovement;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\TreatmentPlan;
use App\Models\PaymentMethod;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /**
     * Create a new transaction
     */
    public function createTransaction(array $data): Transaction
    {
        $this->validateTransactionData($data);

        // Check if there's an active cash session
        $cashSession = $this->getActiveCashSession();
        if (!$cashSession) {
            throw ValidationException::withMessages([
                'session' => ['No hay una sesión de caja abierta. Debe abrir la caja antes de registrar transacciones.'],
            ]);
        }

        DB::beginTransaction();
        try {
            // Calculate amounts
            $amounts = $this->calculateAmounts($data);

            // Create transaction
            $transaction = Transaction::create([
                'patient_id' => $data['patient_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'treatment_plan_id' => $data['treatment_plan_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'],
                'cash_register_session_id' => $cashSession->id,
                'created_by' => Auth::id(),
                'transaction_number' => $this->generateTransactionNumber(),
                'type' => $data['type'] ?? 'payment',
                'amount' => $amounts['total'],
                'subtotal' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount_amount'],
                'discount_type' => $data['discount_type'] ?? null,
                'discount_authorized_by' => $data['discount_authorized_by'] ?? null,
                'commission_amount' => $amounts['commission_amount'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'status' => 'completed',
                'processed_at' => now()
            ]);

            // Create cash movement
            $cashMovement = $this->createCashMovement($transaction, $cashSession);

            DB::commit();

            $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy']);

            // Emitir eventos de WebSocket
            event(new PaymentRegistered($transaction, $cashSession->id));
            event(new CashMovementCreated($cashMovement, $cashSession->id));
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
     * Void a transaction (only for administrators)
     */
    public function voidTransaction(int $transactionId, string $reason): Transaction
    {
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

        DB::beginTransaction();
        try {
            $transaction->update([
                'status' => 'voided',
                'notes' => $transaction->notes . "\n\nANULADA: " . $reason . " - " . now()->format('d/m/Y H:i:s')
            ]);

            // Create void cash movement
            if ($transaction->cash_register_session_id) {
                $this->createVoidCashMovement($transaction);
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
     * Get transactions with filters
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
            $query->whereHas('patient', fn($q) => $q->where('branch_id', $filters['branch_id']));
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(15);

        return [
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ];
    }

    /**
     * Generate receipt for transaction
     */
    public function generateReceipt(Transaction $transaction): array
    {
        $receiptData = [
            'transaction' => $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod']),
            'clinic' => [
                'name' => 'OdontoSuite',
                'address' => 'Dirección de la clínica',
                'phone' => 'Teléfono de la clínica',
                'ruc' => 'RUC de la clínica'
            ],
            'receipt_number' => $transaction->transaction_number,
            'date' => $transaction->created_at->format('d/m/Y H:i:s'),
            'total' => $transaction->amount,
            'subtotal' => $transaction->subtotal,
            'discount' => $transaction->discount_amount,
            'payment_method' => $transaction->paymentMethod->name ?? 'N/A'
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
                'ruc' => 'RUC de la clínica'
            ],
            'receipt_number' => $transaction->transaction_number,
            'date' => $transaction->created_at->format('d/m/Y H:i:s'),
            'total' => $transaction->amount,
            'subtotal' => $transaction->subtotal,
            'discount' => $transaction->discount_amount,
            'payment_method' => $transaction->paymentMethod->name ?? 'N/A'
        ];

        $pdf = Pdf::loadView('reports.receipt', ['receiptData' => $receiptData]);

        return $pdf->output();
    }

    /**
     * Validate transaction data
     */
    private function validateTransactionData(array $data): void
    {
        if (!isset($data['patient_id'])) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente es requerido.'],
            ]);
        }

        $patient = Patient::find($data['patient_id']);
        if (!$patient) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente seleccionado no es válido.'],
            ]);
        }

        // Validar que el paciente esté activo
        if (!$patient->is_active) {
            throw ValidationException::withMessages([
                'patient_id' => ['No se puede crear una transacción para un paciente inactivo.'],
            ]);
        }

        if (!isset($data['payment_method_id']) || !PaymentMethod::find($data['payment_method_id'])) {
            throw ValidationException::withMessages([
                'payment_method_id' => ['El método de pago seleccionado no es válido.'],
            ]);
        }

        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El monto debe ser mayor a 0.'],
            ]);
        }

        // Validate discount authorization
        if (isset($data['discount_amount']) && $data['discount_amount'] > 0) {
            $discountPercentage = ($data['discount_amount'] / $data['amount']) * 100;

            if ($discountPercentage > 10 && !isset($data['discount_authorized_by'])) {
                throw ValidationException::withMessages([
                    'discount' => ['Los descuentos mayores al 10% requieren autorización del administrador.'],
                ]);
            }
        }
    }

    /**
     * Get active cash session
     */
    private function getActiveCashSession(): ?CashRegisterSession
    {
        return CashRegisterSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();
    }

    /**
     * Calculate transaction amounts
     */
    private function calculateAmounts(array $data): array
    {
        $subtotal = $data['amount'];
        $discountAmount = $data['discount_amount'] ?? 0;
        $total = $subtotal - $discountAmount;

        // Calculate commission if applicable
        $paymentMethod = PaymentMethod::find($data['payment_method_id']);
        $commissionAmount = 0;

        if ($paymentMethod && $paymentMethod->commission_percentage > 0) {
            $commissionAmount = $total * ($paymentMethod->commission_percentage / 100);
        }

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'commission_amount' => $commissionAmount
        ];
    }

    /**
     * Generate unique transaction number
     */
    private function generateTransactionNumber(): string
    {
        $date = now()->format('Ymd');
        $lastTransaction = Transaction::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransaction ?
            (int)substr($lastTransaction->transaction_number, -4) + 1 : 1;

        return 'TXN-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create cash movement for transaction
     */
    private function createCashMovement(Transaction $transaction, CashRegisterSession $session): CashMovement
    {
        // Mapear tipos de transacción a tipos de movimiento de caja
        // 'payment' = ingreso, 'refund' = egreso, otros = según el tipo
        $movementType = 'income';
        if ($transaction->type === 'refund') {
            $movementType = 'expense';
        } elseif ($transaction->type === 'payment') {
            $movementType = 'income';
        } else {
            // Para 'discount' y 'adjustment', considerar como egreso
            $movementType = 'expense';
        }

        return CashMovement::create([
            'cash_register_session_id' => $session->id,
            'transaction_id' => $transaction->id,
            'created_by' => Auth::id(),
            'type' => $movementType,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'reference' => $transaction->transaction_number
        ]);
    }

    /**
     * Create void cash movement
     */
    private function createVoidCashMovement(Transaction $transaction): void
    {
        // Revertir el tipo: si era 'payment' (ingreso), crear movimiento de egreso
        // Si era 'refund' (egreso), crear movimiento de ingreso
        $movementType = 'expense';
        if ($transaction->type === 'refund') {
            $movementType = 'income';
        } elseif ($transaction->type === 'payment') {
            $movementType = 'expense';
        }

        \App\Models\CashMovement::create([
            'cash_register_session_id' => $transaction->cash_register_session_id,
            'transaction_id' => $transaction->id,
            'created_by' => Auth::id(),
            'type' => $movementType, // Reverse the type
            'amount' => $transaction->amount,
            'description' => 'Anulación de transacción: ' . $transaction->transaction_number,
            'notes' => 'Transacción anulada',
            'reference' => 'VOID-' . $transaction->transaction_number
        ]);
    }
}

