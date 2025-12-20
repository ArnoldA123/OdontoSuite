<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TreatmentPlanService;
use App\Events\TreatmentPlanDeleted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TreatmentPlanController extends Controller
{
    protected $treatmentPlanService;

    public function __construct(TreatmentPlanService $treatmentPlanService)
    {
        $this->treatmentPlanService = $treatmentPlanService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id', 'status', 'created_by', 'date_from', 'date_to'
            ]);

            $result = $this->treatmentPlanService->getPlans($filters);

            return response()->json([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener planes de tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'title' => 'required|string|max:200',
                'description' => 'nullable|string',
                'estimated_duration_weeks' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',
                'notes' => 'nullable|string',
                'patient_notes' => 'nullable|string',
                'phases' => 'nullable|array',
                'requires_anesthesia' => 'boolean',
                'is_urgent' => 'boolean',
                'items' => 'nullable|array',
                'items.*.description' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|numeric|min:1',
                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.category' => 'nullable|string'
            ]);

            $plan = $this->treatmentPlanService->createPlan($validated);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Plan de tratamiento creado exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear plan de tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $plan = \App\Models\TreatmentPlan::with([
                'patient',
                'createdBy',
                'items',
                'quotations',
                'paymentPlans'
            ])->findOrFail($id);

            return response()->json([
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Plan de tratamiento no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:200',
                'description' => 'nullable|string',
                'estimated_duration_weeks' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'notes' => 'nullable|string',
                'patient_notes' => 'nullable|string',
                'phases' => 'nullable|array',
                'requires_anesthesia' => 'boolean',
                'is_urgent' => 'boolean',
                'items' => 'nullable|array',
                'items.*.description' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|numeric|min:1',
                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.category' => 'nullable|string'
            ]);

            $plan = $this->treatmentPlanService->updatePlan($id, $validated);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Plan de tratamiento actualizado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar plan de tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $plan = \App\Models\TreatmentPlan::findOrFail($id);

            // Solo permitir eliminar si está en estado draft
            if ($plan->status !== 'draft') {
                return response()->json([
                    'message' => 'No se puede eliminar un plan que no esté en estado borrador'
                ], 422);
            }

            $patientId = $plan->patient_id;
            $planId = $plan->id;
            $plan->delete();

            // Emitir evento de WebSocket
            event(new TreatmentPlanDeleted($planId, $patientId));

            return response()->json([
                'meta' => [
                    'message' => 'Plan de tratamiento eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar plan de tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado del plan
     */
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:draft,proposed,approved,in_progress,completed,cancelled'
            ]);

            $plan = $this->treatmentPlanService->changeStatus($id, $validated['status']);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Estado del plan actualizado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar estado del plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicar plan de tratamiento
     */
    public function duplicate(int $id): JsonResponse
    {
        try {
            $plan = $this->treatmentPlanService->duplicate($id);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Plan de tratamiento duplicado exitosamente'
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al duplicar plan de tratamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar item al plan
     */
    public function addItem(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string',
                'quantity' => 'required|numeric|min:1',
                'unit_price' => 'required|numeric|min:0',
                'category' => 'nullable|string'
            ]);

            $item = $this->treatmentPlanService->addItem($id, $validated);

            return response()->json([
                'data' => $item,
                'meta' => [
                    'message' => 'Procedimiento agregado exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar procedimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar item del plan
     */
    public function removeItem(int $itemId): JsonResponse
    {
        try {
            $this->treatmentPlanService->removeItem($itemId);

            return response()->json([
                'meta' => [
                    'message' => 'Procedimiento eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar procedimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
