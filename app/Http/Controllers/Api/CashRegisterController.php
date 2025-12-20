<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CashRegisterService;
use App\Http\Requests\OpenCashRegisterRequest;
use App\Http\Requests\CloseCashRegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\CashRegisterSession;
use Barryvdh\DomPDF\Facade\Pdf;

class CashRegisterController extends Controller
{
    protected $cashRegisterService;

    public function __construct(CashRegisterService $cashRegisterService)
    {
        $this->cashRegisterService = $cashRegisterService;
    }

    /**
     * Open a new cash register session
     */
    public function open(OpenCashRegisterRequest $request): JsonResponse
    {
        try {
            $session = $this->cashRegisterService->openSession($request->validated());

            return response()->json([
                'data' => $session,
                'meta' => [
                    'message' => 'Sesión de caja abierta exitosamente'
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al abrir la sesión de caja',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Close cash register session
     */
    public function close(CloseCashRegisterRequest $request): JsonResponse
    {
        try {
            $sessionId = $request->input('session_id');
            $session = $this->cashRegisterService->closeSession($sessionId, $request->validated());

            return response()->json([
                'data' => $session,
                'meta' => [
                    'message' => 'Sesión de caja cerrada exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cerrar la sesión de caja',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get current open session
     */
    public function current(): JsonResponse
    {
        try {
            $session = $this->cashRegisterService->getCurrentSession();

            if (!$session) {
                return response()->json([
                    'data' => null,
                    'meta' => [
                        'message' => 'No hay sesión de caja abierta'
                    ]
                ]);
            }

            $summary = $this->cashRegisterService->getSessionSummary($session);

            return response()->json([
                'data' => [
                    'session' => $session,
                    'summary' => $summary
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la sesión actual',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current session for viewing (read-only)
     */
    public function currentView(): JsonResponse
    {
        try {
            $session = $this->cashRegisterService->getCurrentSession();

            if (!$session) {
                return response()->json([
                    'data' => null,
                    'meta' => [
                        'message' => 'No hay sesión de caja abierta'
                    ]
                ]);
            }

            return response()->json([
                'data' => $session
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la sesión actual',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sessions list
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'user_id', 'branch_id', 'status', 'date_from', 'date_to'
            ]);

            $result = $this->cashRegisterService->getSessions($filters);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las sesiones de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific session details
     */
    public function show(int $id): JsonResponse
    {
        try {
            $session = \App\Models\CashRegisterSession::with([
                'user', 'branch', 'movements', 'transactions'
            ])->findOrFail($id);

            $summary = $this->cashRegisterService->getSessionSummary($session);

            return response()->json([
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la sesión de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get movements for a specific session
     */
    public function movements($sessionId): JsonResponse
    {
        try {
            $session = \App\Models\CashRegisterSession::findOrFail($sessionId);

            $movements = \App\Models\CashMovement::where('cash_register_session_id', $sessionId)
                ->with(['createdBy', 'transaction'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'data' => $movements->items(),
                'meta' => [
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los movimientos de la sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cash register summary for today
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $date = $request->input('date', now()->toDateString());
            $branchId = $request->input('branch_id');
            $userId = $request->input('user_id');

            $summary = $this->cashRegisterService->getSessionSummary(
                \App\Models\CashRegisterSession::whereDate('opened_at', $date)->first()
            );

            return response()->json([
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el resumen de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate closure report for a specific session
     */
    public function closureReport(int $sessionId)
    {
        $session = CashRegisterSession::with(['user', 'branch', 'transactions', 'movements'])
            ->findOrFail($sessionId);

        $summary = $this->cashRegisterService->getSessionSummary($session);

        $pdf = Pdf::loadView('reports.cash-closure', [
            'session' => $session,
            'summary' => $summary
        ]);

        return $pdf->download("cierre-caja-{$sessionId}.pdf");
    }
}

