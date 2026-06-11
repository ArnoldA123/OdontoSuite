<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReminderTemplateController extends Controller
{
    /**
     * Sprint 0 fix (NF-1): los bodies estaban vacios (//) y devolvian 500.
     * Las rutas apiResource quedan activas pero cada metodo responde 501
     * con un mensaje claro mientras no se implemente el CRUD completo.
     * El feature real queda documentado en docs/mejoras/plan-mejoras-futuras-2026-06.md
     * como Opcion B del hallazgo NF-1.
     */
    private function notImplemented(string $feature): JsonResponse
    {
        return response()->json([
            'message' => "Funcionalidad de {$feature} pendiente de implementacion.",
            'todo' => 'Ver plan-mejoras-futuras-2026-06.md, hallazgo NF-1.',
        ], 501);
    }

    public function index(): JsonResponse
    {
        return $this->notImplemented('listado de plantillas de recordatorio');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->notImplemented('creacion de plantilla de recordatorio');
    }

    public function show(string $id): JsonResponse
    {
        return $this->notImplemented('consulta de plantilla de recordatorio');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->notImplemented('actualizacion de plantilla de recordatorio');
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->notImplemented('eliminacion de plantilla de recordatorio');
    }
}
