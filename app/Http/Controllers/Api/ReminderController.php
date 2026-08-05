<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReminderRequest;
use App\Http\Requests\UpdateReminderRequest;
use App\Models\ReminderSchedule;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slice 03 (BF-001): full CRUD on /api/reminders. Reuses ReminderService
 * (no new abstraction). Channel whitelist enforced via StoreReminderRequest.
 * Status transitions go through ReminderSchedule::transitionTo().
 */
class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min((int) $request->get('per_page', 25), 100);

        $query = ReminderSchedule::with(['appointment.patient:id,first_name,last_name', 'reminderTemplate:id,name,type']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($appointmentId = $request->get('appointment_id')) {
            $query->where('appointment_id', $appointmentId);
        }

        $items = $query->orderBy('scheduled_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'message' => 'Recordatorios cargados exitosamente',
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = ReminderSchedule::create(array_merge(
            $request->validated(),
            ['status' => $request->input('status', 'pending')]
        ));

        return response()->json([
            'data' => $reminder->load(['appointment.patient:id,first_name,last_name', 'reminderTemplate:id,name,type']),
            'meta' => ['message' => 'Recordatorio creado exitosamente'],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $reminder = ReminderSchedule::with(['appointment.patient:id,first_name,last_name', 'reminderTemplate:id,name,type'])
            ->findOrFail($id);

        return response()->json(['data' => $reminder]);
    }

    public function update(UpdateReminderRequest $request, string $id): JsonResponse
    {
        $reminder = ReminderSchedule::findOrFail($id);

        $data = $request->validated();

        if (isset($data['status'])) {
            $reminder->transitionTo($data['status']);
            unset($data['status']);
        }

        if (!empty($data)) {
            $reminder->fill($data)->save();
        }

        return response()->json([
            'data' => $reminder->fresh()->load(['appointment.patient:id,first_name,last_name', 'reminderTemplate:id,name,type']),
            'meta' => ['message' => 'Recordatorio actualizado exitosamente'],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $reminder = ReminderSchedule::findOrFail($id);
        $reminder->delete();

        return response()->json(null, 204);
    }

    /**
     * Send a reminder immediately. Delegated to ReminderService::sendReminder.
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
