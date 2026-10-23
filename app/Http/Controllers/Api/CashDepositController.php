<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCashDepositRequest;
use App\Services\CashDepositService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * #287 (T4, S5/S7) — the drawer's deposits.
 *
 * Writing is counter work (`manage.cash_deposit`) and reading follows the
 * register grant (`view.cash_register`). The shape of the payload belongs to
 * the request and the business rules — open session, available cash, sede — to
 * the service.
 */
class CashDepositController extends Controller
{
    public function __construct(private readonly CashDepositService $deposits) {}

    /**
     * Paginated deposit list, filtered by date range and effective sede.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->deposits->index($request->only([
                'branch_id', 'date_from', 'date_to', 'per_page',
            ])));
        } catch (AuthorizationException|ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('CashDepositController@index: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al obtener los depósitos de caja'], 500);
        }
    }

    /**
     * Register a deposit and its drawer withdrawal in one transaction.
     */
    public function store(StoreCashDepositRequest $request): JsonResponse
    {
        try {
            $deposit = $this->deposits->register($request->validated());

            return response()->json([
                'data' => $deposit,
                'meta' => ['message' => 'Depósito registrado exitosamente'],
            ], 201);
        } catch (AuthorizationException|ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('CashDepositController@store: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al registrar el depósito'], 500);
        }
    }
}
