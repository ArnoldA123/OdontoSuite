<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Services\TransactionService;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of transactions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id', 'type', 'payment_method_id', 'status',
                'date_from', 'date_to', 'cash_register_session_id'
            ]);

            // Si no se especifica sesión, usar la sesión activa del usuario
            if (!isset($filters['cash_register_session_id'])) {
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created transaction
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
                    'message' => 'Transacción registrada exitosamente'
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la transacción',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified transaction
     */
    public function show(Transaction $transaction): JsonResponse
    {
        try {
            $transaction->load(['patient', 'appointment', 'treatmentPlan', 'paymentMethod', 'createdBy']);

            return response()->json([
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la transacción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified transaction
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            // Only allow updates to certain fields for completed transactions
            if ($transaction->status === 'completed') {
                $allowedFields = ['notes', 'reference_number'];
                $data = $request->only($allowedFields);

                $transaction->update($data);
                $transaction->refresh();
                $transaction->load(['patient', 'appointment', 'paymentMethod', 'createdBy']);

                // Emitir evento de WebSocket
                event(new TransactionUpdated($transaction));

                return response()->json([
                    'data' => $transaction,
                    'meta' => [
                        'message' => 'Transacción actualizada exitosamente'
                    ]
                ]);
            }

            return response()->json([
                'message' => 'No se puede actualizar esta transacción'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la transacción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified transaction (soft delete)
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        try {
            if ($transaction->status === 'voided') {
                return response()->json([
                    'message' => 'La transacción ya ha sido anulada'
                ], 422);
            }

            // Only allow deletion of recent transactions
            if ($transaction->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'message' => 'No se puede eliminar transacciones mayores a 24 horas'
                ], 422);
            }

            $transaction->delete();

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'Transacción eliminada exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la transacción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Void a transaction
     */
    public function void(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $voidedTransaction = $this->transactionService->voidTransaction(
                $transaction->id,
                $request->input('reason')
            );

            return response()->json([
                'data' => $voidedTransaction,
                'meta' => [
                    'message' => 'Transacción anulada exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al anular la transacción',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Generate receipt for transaction
     */
    public function generateReceipt(Transaction $transaction): JsonResponse
    {
        try {
            $receiptData = $this->transactionService->generateReceipt($transaction);

            return response()->json([
                'data' => $receiptData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el comprobante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions list for dropdown/select
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $query = Transaction::with(['patient', 'paymentMethod'])
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

            $transactions = $query->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la lista de transacciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

