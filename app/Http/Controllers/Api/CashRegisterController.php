<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Requests\OpenCashRegisterRequest;
use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CashRegisterController extends Controller
{
    protected $cashRegisterService;

    public function __construct(CashRegisterService $cashRegisterService)
    {
        $this->cashRegisterService = $cashRegisterService;
    }

    /**
     * Open a new cash register session.
     */
    public function open(OpenCashRegisterRequest $request): JsonResponse
    {
        try {
            $session = $this->cashRegisterService->openSession($request->validated());

            return response()->json([
                'data' => $session,
                'meta' => [
                    'message' => 'Sesión de caja abierta exitosamente',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al abrir la sesión de caja',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Close cash register session.
     */
    public function close(CloseCashRegisterRequest $request): JsonResponse
    {
        try {
            $sessionId = $request->input('session_id');
            $session = $this->cashRegisterService->closeSession($sessionId, $request->validated());

            return response()->json([
                'data' => $session,
                'meta' => [
                    'message' => 'Sesión de caja cerrada exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cerrar la sesión de caja',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get current open session.
     */
    public function current(): JsonResponse
    {
        try {
            $session = $this->cashRegisterService->getCurrentSession();

            if (! $session) {
                return response()->json([
                    'data' => null,
                    'meta' => [
                        'message' => 'No hay sesión de caja abierta',
                    ],
                ]);
            }

            $summary = $this->cashRegisterService->getSessionSummary($session);

            return response()->json([
                'data' => [
                    'session' => $session,
                    'summary' => $summary,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('No se pudo obtener la sesión de caja actual', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener la sesión actual',
            ], 500);
        }
    }

    /**
     * Get sessions list.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'user_id', 'branch_id', 'status', 'date_from', 'date_to',
            ]);

            $result = $this->cashRegisterService->getSessions($filters);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('No se pudieron obtener las sesiones de caja', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener las sesiones de caja',
            ], 500);
        }
    }

    /**
     * Get specific session details.
     *
     * Issue #193: the session detail is scoped to its owner (or an
     * administrador) through CashRegisterPolicy::view, the same rule
     * CashMovementPolicy already applied.
     *
     * Cash follow-up S4: the lookup stays inside the try so any failure is
     * logged and answered with the generic envelope, while a missing id is
     * rethrown as a 404 before the catch-all.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $session = CashRegisterSession::query()->visibleToBranch()->with([
                'user', 'branch', 'movements', 'transactions',
            ])->findOrFail($id);

            if ($denied = $this->denyUnlessSessionViewable($request, $session)) {
                return $denied;
            }

            $summary = $this->cashRegisterService->getSessionSummary($session);

            return response()->json([
                'data' => $summary,
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('No se pudo obtener la sesión de caja', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener la sesión de caja',
            ], 500);
        }
    }

    /**
     * Get movements for a specific session (issue #193: owner or administrador).
     */
    public function movements(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = CashRegisterSession::query()->visibleToBranch()->findOrFail($sessionId);

            if ($denied = $this->denyUnlessSessionViewable($request, $session)) {
                return $denied;
            }

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
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('No se pudieron obtener los movimientos de la sesión de caja', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener los movimientos de la sesión',
            ], 500);
        }
    }

    /**
     * Enforce the session ownership rule (issue #193).
     *
     * The rule itself lives in CashRegisterPolicy::view; this only shapes the
     * 403 envelope the cash surfaces answer with.
     */
    private function denyUnlessSessionViewable(Request $request, CashRegisterSession $session): ?JsonResponse
    {
        if ($request->user()->can('view', $session)) {
            return null;
        }

        return response()->json([
            'message' => 'No tienes permisos para ver esta sesión de caja',
        ], 403);
    }

    /**
     * Get cash register summary for today.
     *
     * Issue #52: the session lookup is nullable (no session that day) and
     * used to be passed straight into the non-nullable
     * CashRegisterService::getSessionSummary(), producing a TypeError 500.
     * Without a session there is nothing to summarize, so a 200 envelope
     * with an empty summary is returned instead of an exception.
     *
     * Issue #194: the lookup took the latest session of the day without a
     * user scope, so a recepcionista could be answered with another user's
     * drawer. Administrador/finanzas may summarize any drawer and pass
     * user_id to scope it; every other role is always scoped to itself and
     * an explicit user_id is ignored. The branch default mirrors
     * CashRegisterService::getCurrentSession().
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $date = $request->input('date', now()->toDateString());
            $branchId = $request->input('branch_id');
            $userId = $request->input('user_id');
            $isCashManager = in_array($user->role, ['administrador', 'finanzas'], true);

            $query = \App\Models\CashRegisterSession::whereDate('opened_at', $date);

            if (! $isCashManager) {
                $query->where('user_id', $user->id);
            } elseif ($userId) {
                $query->where('user_id', $userId);
            }

            if ($branchId) {
                $query->where('branch_id', $branchId);
            } elseif ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }

            $session = $query->latest('opened_at')->first();

            if (! $session) {
                return response()->json([
                    'data' => $this->cashRegisterService->getEmptySummary(),
                    'meta' => ['message' => 'No hay sesión de caja para la fecha indicada'],
                ]);
            }

            $summary = $this->cashRegisterService->getSessionSummary($session);

            return response()->json([
                'data' => $summary,
                'meta' => ['message' => 'Resumen de caja generado exitosamente'],
            ]);
        } catch (\Exception $e) {
            Log::error('No se pudo obtener el resumen de caja', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener el resumen de caja',
            ], 500);
        }
    }

    /**
     * Generate closure report for a specific session (issue #193: owner or
     * administrador, same rule as the session detail).
     */
    public function closureReport(Request $request, int $sessionId)
    {
        $session = CashRegisterSession::query()->visibleToBranch()
            ->with(['user', 'branch', 'transactions', 'movements'])
            ->findOrFail($sessionId);

        if ($denied = $this->denyUnlessSessionViewable($request, $session)) {
            return $denied;
        }

        $summary = $this->cashRegisterService->getSessionSummary($session);

        $pdf = Pdf::loadView('reports.cash-closure', [
            'session' => $session,
            'summary' => $summary,
        ]);

        return $pdf->download("cierre-caja-{$sessionId}.pdf");
    }
}
