<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderSchedule;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService)
    {
    }

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
        return $this->notImplemented('listado de recordatorios');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->notImplemented('creacion de recordatorios');
    }

    public function show(string $id): JsonResponse
    {
        return $this->notImplemented('consulta de recordatorio');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->notImplemented('actualizacion de recordatorio');
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->notImplemented('eliminacion de recordatorio');
    }

    /**
     * Enviar un recordatorio ahora (dispara ReminderService::sendReminder).
     * Sprint 0 fix del plan de inconsistencias: la ruta POST /api/reminders/{id}/send
     * apuntaba a un metodo inexistente -> 500. Implementacion minima que delega al service.
     */
    public function send(string $id): JsonResponse
    {
        try {
            $reminder = ReminderSchedule::findOrFail($id);
            $this->reminderService->sendReminder($reminder);

            return response()->json([
                'data' => ['id' => $reminder->id, 'status' => 'sent'],
                'meta' => ['message' => 'Recordatorio enviado'],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Recordatorio no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar el recordatorio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
