<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Events\TreatmentPlanDeleted;
use App\Http\Controllers\Controller;
use App\Services\TreatmentPlanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TreatmentPlanController extends Controller
{
    protected $treatmentPlanService;

    public function __construct(TreatmentPlanService $treatmentPlanService)
    {
        $this->treatmentPlanService = $treatmentPlanService;
    }

    /**
     * Convierte strings vacíos a null para que las reglas `nullable` funcionen.
     * Sin esto, un campo `<input>` vacío envía `""` y rompe reglas como `date`.
     */
    protected function normalizeEmptyStrings(Request $request): void
    {
        $fields = [
            'description', 'estimated_duration_weeks', 'start_date', 'end_date',
            'notes', 'patient_notes',
        ];

        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            $items = array_map(function ($item) {
                foreach (['description', 'procedure_name', 'specialty', 'category', 'phase_number', 'procedure_catalog_id', 'dental_piece_id', 'tooth_surface_id', 'site_code'] as $k) {
                    if (isset($item[$k]) && $item[$k] === '') {
                        $item[$k] = null;
                    }
                }

                return $item;
            }, $items);
            $request->merge(['items' => $items]);
        }
    }

    /**
     * Resumen agregado del módulo para la tira de KPIs.
     */
    public function summary(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->treatmentPlanService->summary()]);
        } catch (\Exception $e) {
            Log::error('TreatmentPlanController@summary: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener el resumen de planes de tratamiento',
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id', 'patient_name', 'status', 'statuses', 'overdue', 'created_by', 'date_from', 'date_to', 'branch_id',
            ]);

            // #283 S2: el plan es ficha del paciente (S9), pero el filtro por
            // sede no puede ensancharse por omisión.
            $filters['branch_id'] = BranchAccess::effectiveFilter(
                $request->user(),
                isset($filters['branch_id']) && $filters['branch_id'] !== '' ? (int) $filters['branch_id'] : null,
            );

            $result = $this->treatmentPlanService->getPlans($filters);

            return response()->json([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener planes de tratamiento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreTreatmentPlanRequest $request): JsonResponse
    {
        $this->normalizeEmptyStrings($request);

        try {
            $validated = $request->validated();

            $plan = $this->treatmentPlanService->createPlan($validated);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Plan de tratamiento creado exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear plan de tratamiento',
                'error' => $e->getMessage(),
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
                'paymentPlans',
                // #296: the decision-support material the plan cites.
                'citedAnalyses' => fn ($query) => $query->citationSummary(),
            ])->findOrFail($id);

            return response()->json([
                'data' => new \App\Http\Resources\TreatmentPlanResource($plan),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Plan de tratamiento no encontrado',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->normalizeEmptyStrings($request);

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
                'items.*.description' => 'nullable|string|max:500',
                'items.*.procedure_name' => 'required_with:items|nullable|string|max:200',
                'items.*.quantity' => 'required_with:items|numeric|min:0.01',
                'items.*.unit_cost' => 'required_with:items|numeric|min:0',
                'items.*.procedure_catalog_id' => 'nullable|exists:procedure_catalog,id',
                'items.*.dental_piece_id' => 'nullable|exists:dental_pieces,id',
                'items.*.tooth_surface_id' => 'nullable|exists:tooth_surfaces,id',
                'items.*.site_code' => 'nullable|string|max:30',
                'items.*.specialty' => 'nullable|string|max:50',
                'items.*.phase_number' => 'nullable|integer|min:1',
                'items.*.category' => 'nullable|string|max:50',
                // #296: only a reviewed analysis can be cited.
                'cited_analysis_ids' => 'sometimes|array|max:20',
                'cited_analysis_ids.*' => 'integer',
            ], [
                'title.required' => 'El título del plan es obligatorio',
                'items.*.procedure_name.required_with' => 'El nombre del procedimiento es obligatorio',
                'items.*.unit_cost.required_with' => 'El precio unitario es obligatorio',
                'items.*.unit_cost.min' => 'El precio no puede ser negativo',
                'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            ]);

            $plan = $this->treatmentPlanService->updatePlan($id, $this->withOverrideReason($request, $validated));

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Plan de tratamiento actualizado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            // #283 S5: el bloqueo contable se responde 403, no 500.
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar plan de tratamiento',
                'error' => $e->getMessage(),
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
                    'message' => 'No se puede eliminar un plan que no esté en estado borrador',
                ], 422);
            }

            $patientId = $plan->patient_id;
            $planId = $plan->id;
            $plan->delete();

            // Emitir evento de WebSocket
            event(new TreatmentPlanDeleted($planId, $patientId));

            return response()->json([
                'meta' => [
                    'message' => 'Plan de tratamiento eliminado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar plan de tratamiento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar estado del plan.
     */
    public function changeStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:draft,proposed,approved,in_progress,completed,cancelled',
            ]);

            $plan = $this->treatmentPlanService->changeStatus($id, $validated['status']);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Estado del plan actualizado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar estado del plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicar plan de tratamiento.
     */
    public function duplicate(int $id): JsonResponse
    {
        try {
            $plan = $this->treatmentPlanService->duplicate($id);

            return response()->json([
                'data' => $plan,
                'meta' => [
                    'message' => 'Plan de tratamiento duplicado exitosamente',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al duplicar plan de tratamiento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agregar item al plan.
     */
    public function addItem(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'procedure_name' => 'required|string|max:200',
                'description' => 'nullable|string|max:500',
                'quantity' => 'required|numeric|min:0.01',
                'unit_cost' => 'required|numeric|min:0',
                'procedure_catalog_id' => 'nullable|exists:procedure_catalog,id',
                'dental_piece_id' => 'nullable|exists:dental_pieces,id',
                'tooth_surface_id' => 'nullable|exists:tooth_surfaces,id',
                'site_code' => 'nullable|string|max:30',
                'specialty' => 'nullable|string|max:50',
                'phase_number' => 'nullable|integer|min:1',
                'category' => 'nullable|string|max:50',
            ], [
                'procedure_name.required' => 'El nombre del procedimiento es obligatorio',
                'unit_cost.required' => 'El precio unitario es obligatorio',
                'unit_cost.min' => 'El precio no puede ser negativo',
                'quantity.required' => 'La cantidad es obligatoria',
                'quantity.min' => 'La cantidad mínima es 0.01',
            ]);

            $item = $this->treatmentPlanService->addItem($id, $this->withOverrideReason($request, $validated));

            return response()->json([
                'data' => $item,
                'meta' => [
                    'message' => 'Procedimiento agregado exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar procedimiento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * #283 S4: the registered authorization travels as an explicit reason and
     * never reaches the model as a column.
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withOverrideReason(Request $request, array $payload): array
    {
        $reason = $request->input('lock_override_reason');

        if (is_string($reason) && trim($reason) !== '') {
            $payload['lock_override_reason'] = trim($reason);
        }

        return $payload;
    }

    /**
     * Eliminar item del plan.
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        try {
            $reason = $request->input('lock_override_reason');

            $this->treatmentPlanService->removeItem(
                $itemId,
                is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
            );

            return response()->json([
                'meta' => [
                    'message' => 'Procedimiento eliminado exitosamente',
                ],
            ]);
        } catch (AuthorizationException $e) {
            // #283 S5: el bloqueo contable se responde 403, no 500.
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar procedimiento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
