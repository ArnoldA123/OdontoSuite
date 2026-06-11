<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\ProcedureStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 3 fix (IM-5): endpoint para el dashboard de uso de procedimientos.
 * Devuelve top procedimientos + resumen por especialidad en un periodo.
 * Solo accesible para admin y finanzas.
 */
class ProcedureStatsController extends Controller
{
    public function __construct(private readonly ProcedureStatsService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $stats = $this->service->getStats($validated);
            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas de procedimientos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
