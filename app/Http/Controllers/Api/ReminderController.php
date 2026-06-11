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
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Enviar un recordatorio ahora (dispara ReminderService::sendReminder).
     * Sprint 0 fix: la ruta POST /api/reminders/{id}/send apuntaba a un método
     * inexistente -> 500. Implementación mínima que delega al service.
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
