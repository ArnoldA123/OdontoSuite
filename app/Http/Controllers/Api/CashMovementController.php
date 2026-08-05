<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CashMovementController extends Controller
{
    /**
     * Display a listing of cash movements
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CashMovement::with(['cashRegisterSession', 'transaction', 'createdBy']);

            // Filter by cash register session
            if ($request->has('cash_register_session_id')) {
                $query->where('cash_register_session_id', $request->input('cash_register_session_id'));
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            // Filter by current user's sessions if not admin
            if (!Auth::user()->isAdministrador()) {
                $userSessionIds = CashRegisterSession::where('user_id', Auth::id())
                    ->pluck('id');
                $query->whereIn('cash_register_session_id', $userSessionIds);
            }

            $perPage = $request->get('per_page', 15);
            $perPage = min($perPage, 100);

            $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
                'message' => 'Error al obtener los movimientos de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created cash movement
     *
     * Slice 02: validates with StoreCashMovementRequest (concept whitelist +
     * branch_id support). The FormRequest returns a 422 envelope directly
     * via Laravel's default validation flow; we only branch here for the
     * post-validation business checks (session open + permission).
     */
    public function store(\App\Http\Requests\StoreCashMovementRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Check if the cash register session is open
            $session = CashRegisterSession::findOrFail($validated['cash_register_session_id']);

            if ($session->status !== 'open') {
                return response()->json([
                    'message' => 'No se pueden registrar movimientos en una sesión de caja cerrada'
                ], 422);
            }

            // Check if user has permission to access this session
            if ($session->user_id !== Auth::id() && !Auth::user()->isAdministrador()) {
                return response()->json([
                    'message' => 'No tienes permisos para registrar movimientos en esta sesión de caja'
                ], 403);
            }

            $validated['created_by'] = Auth::id();

            $movement = CashMovement::create($validated);

            return response()->json([
                'data' => $movement->load(['cashRegisterSession', 'transaction', 'createdBy']),
                'meta' => [
                    'message' => 'Movimiento de caja registrado exitosamente'
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el movimiento de caja',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified cash movement
     */
    public function show(CashMovement $cashMovement): JsonResponse
    {
        try {
            // Check permissions
            if ($cashMovement->cashRegisterSession->user_id !== Auth::id() && !Auth::user()->isAdministrador()) {
                return response()->json([
                    'message' => 'No tienes permisos para ver este movimiento de caja'
                ], 403);
            }

            $cashMovement->load(['cashRegisterSession', 'transaction', 'createdBy']);

            return response()->json([
                'data' => $cashMovement
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el movimiento de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified cash movement
     */
    public function update(Request $request, CashMovement $cashMovement): JsonResponse
    {
        try {
            // Check permissions
            if ($cashMovement->cashRegisterSession->user_id !== Auth::id() && !Auth::user()->isAdministrador()) {
                return response()->json([
                    'message' => 'No tienes permisos para actualizar este movimiento de caja'
                ], 403);
            }

            // Check if session is still open
            if ($cashMovement->cashRegisterSession->status !== 'open') {
                return response()->json([
                    'message' => 'No se pueden actualizar movimientos de sesiones cerradas'
                ], 422);
            }

            $validated = $request->validate([
                'description' => 'sometimes|string|max:255',
                'notes' => 'nullable|string|max:500',
                'reference' => 'nullable|string|max:100'
            ]);

            $cashMovement->update($validated);

            return response()->json([
                'data' => $cashMovement->fresh()->load(['cashRegisterSession', 'transaction', 'createdBy']),
                'meta' => [
                    'message' => 'Movimiento de caja actualizado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el movimiento de caja',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified cash movement
     */
    public function destroy(CashMovement $cashMovement): JsonResponse
    {
        try {
            // Check permissions
            if ($cashMovement->cashRegisterSession->user_id !== Auth::id() && !Auth::user()->isAdministrador()) {
                return response()->json([
                    'message' => 'No tienes permisos para eliminar este movimiento de caja'
                ], 403);
            }

            // Check if session is still open
            if ($cashMovement->cashRegisterSession->status !== 'open') {
                return response()->json([
                    'message' => 'No se pueden eliminar movimientos de sesiones cerradas'
                ], 422);
            }

            // Don't allow deletion of system movements (opening/closing)
            if (in_array($cashMovement->type, ['opening', 'closing'])) {
                return response()->json([
                    'message' => 'No se pueden eliminar movimientos del sistema'
                ], 422);
            }

            $cashMovement->delete();

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'Movimiento de caja eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el movimiento de caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

