<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaitingList;
use App\Services\WaitingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WaitingListController extends Controller
{
    public function __construct(private readonly WaitingListService $waitingListService)
    {
    }

    /**
     * Sprint 0 fix (NF-1 + NF-6): el controller era stub (//) y devolvía 500.
     * Mismo patrón que ReminderController: rutas apiResource activas, métodos
     * 501 con mensaje claro mientras no se implemente el CRUD completo,
     * EXCEPTO store() que sí está implementado porque lo llama
     * WaitingListService.addToWaitingList (fix NF-6).
     */
    private function notImplemented(string $feature): JsonResponse
    {
        return response()->json([
            'message' => "Funcionalidad de {$feature} pendiente de implementacion.",
            'todo' => 'Ver plan-mejoras-futuras-2026-06.md, hallazgo NF-1.',
        ], 501);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = WaitingList::with(['patient', 'appointmentType', 'preferredUser', 'createdBy']);

            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }
            if ($request->filled('appointment_type_id')) {
                $query->where('appointment_type_id', $request->integer('appointment_type_id'));
            }
            if ($request->filled('patient_id')) {
                $query->where('patient_id', $request->integer('patient_id'));
            }

            $items = $query->orderBy('priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->paginate($request->integer('per_page', 15));

            return response()->json([
                'data' => $items->items(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar la lista de espera',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'appointment_type_id' => 'required|integer|exists:appointment_types,id',
            'preferred_user_id' => 'nullable|integer|exists:users,id',
            'preferred_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:0|max:10',
            'notes' => 'nullable|string|max:1000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            $waitingList = $this->waitingListService->addToWaitingList(
                $validated,
                Auth::id(),
            );

            return response()->json([
                'data' => $waitingList,
                'meta' => ['message' => 'Paciente agregado a la lista de espera.'],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar a la lista de espera',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $item = WaitingList::with(['patient', 'appointmentType', 'preferredUser', 'createdBy'])
                ->findOrFail($id);

            return response()->json(['data' => $item]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Entrada de lista de espera no encontrada.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la entrada',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->notImplemented('actualizacion de entrada de lista de espera');
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->notImplemented('eliminacion de entrada de lista de espera');
    }
}
