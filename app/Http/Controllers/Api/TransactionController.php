<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\AccountingLockService;
use App\Services\TransactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(
        TransactionService $transactionService,
        private readonly AccountingLockService $accountingLocks,
    ) {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of transactions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id', 'type', 'payment_method_id', 'status',
                'date_from', 'date_to', 'cash_register_session_id', 'branch_id',
            ]);

            // #283 S2: la sede autoriza también en la consulta de dinero. Un
            // usuario restringido queda acotado a su sede y pedir otra responde
            // 403 antes de llegar aquí.
            $branchFilter = BranchAccess::effectiveFilter(
                Auth::user(),
                $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            );

            if ($branchFilter === null) {
                unset($filters['branch_id']);
            } else {
                $filters['branch_id'] = $branchFilter;
            }

            // Si no se especifica sesión, usar la sesión activa del usuario
            if (! isset($filters['cash_register_session_id'])) {
                $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
                    ->where('status', 'open')
                    ->first();

                if ($activeSession) {
                    $filters['cash_register_session_id'] = $activeSession->id;
                }
            }

            $result = $this->transactionService->getTransactions($filters);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las transacciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createTransaction($request->validated());

            // El evento TransactionCreated ya se emite en TransactionService
            // No es necesario emitirlo aquí nuevamente

            return response()->json([
                'data' => $transaction,
                'meta' => [
                    'message' => 'Transacción registrada exitosamente',
                ],
            ], 201);
        } catch (ValidationException $e) {
            // #291, S4: the rule of the payment method is a field error, so it
            // answers the standard 422 envelope instead of the generic one.
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la transacción',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        try {
            // #283 S3: otra sede responde 404, como el resto de modelos
            // operativos; la sede de una transacción es la de su cajón.
            $transaction = Transaction::query()->visibleToBranch()->findOrFail($transaction->id);
            $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy']);

            return response()->json([
                'data' => $transaction,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // #283 S3: la transacción de otra sede no se sirve, se responde 404.
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la transacción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified transaction.
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            // Only allow updates to certain fields for completed transactions
            if ($transaction->status === 'completed') {
                $allowedFields = ['notes', 'reference_number'];
                $data = $request->only($allowedFields);

                $this->accountingLocks->guard(
                    AccountingLockService::CASH_TRANSACTIONS,
                    $transaction->created_at,
                    $transaction->cashRegisterSession?->branch_id,
                    $transaction,
                    'update',
                    $request->input('lock_override_reason'),
                );

                $transaction->update($data);
                $transaction->refresh();
                $transaction->load(['patient', 'appointment', 'paymentMethod', 'createdBy']);

                // Emitir evento de WebSocket
                event(new TransactionUpdated($transaction));

                return response()->json([
                    'data' => $transaction,
                    'meta' => [
                        'message' => 'Transacción actualizada exitosamente',
                    ],
                ]);
            }

            return response()->json([
                'message' => 'No se puede actualizar esta transacción',
            ], 422);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la transacción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified transaction (soft delete).
     */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            if ($transaction->status === 'voided') {
                return response()->json([
                    'message' => 'La transacción ya ha sido anulada',
                ], 422);
            }

            // Only allow deletion of recent transactions
            if ($transaction->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'message' => 'No se puede eliminar transacciones mayores a 24 horas',
                ], 422);
            }

            $this->accountingLocks->guard(
                AccountingLockService::CASH_TRANSACTIONS,
                $transaction->created_at,
                $transaction->cashRegisterSession?->branch_id,
                $transaction,
                'delete',
                $request->input('lock_override_reason'),
            );

            $transaction->delete();

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'Transacción eliminada exitosamente',
                ],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la transacción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void a transaction.
     */
    public function void(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $voidedTransaction = $this->transactionService->voidTransaction(
                $transaction->id,
                $request->input('reason'),
                $request->input('lock_override_reason')
            );

            return response()->json([
                'data' => $voidedTransaction,
                'meta' => [
                    'message' => 'Transacción anulada exitosamente',
                ],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al anular la transacción',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate receipt for transaction (PDF).
     */
    public function generateReceipt(Transaction $transaction)
    {
        try {
            $pdfContent = $this->transactionService->generateReceiptPdf($transaction);

            $filename = 'comprobante_'.$transaction->transaction_number.'.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el comprobante',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transactions list for dropdown/select.
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $query = Transaction::query()->visibleToBranch()->with(['patient', 'paymentMethod'])
                ->where('status', 'completed');

            if ($request->has('patient_id')) {
                $query->where('patient_id', $request->input('patient_id'));
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $branchFilter = BranchAccess::effectiveFilter(
                Auth::user(),
                $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            );

            if ($branchFilter !== null) {
                $query->whereHas('patient', fn ($q) => $q->where('branch_id', $branchFilter));
            }

            $transactions = $query->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la lista de transacciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
