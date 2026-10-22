<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Services\QuotationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    protected $quotationService;

    public function __construct(QuotationService $quotationService)
    {
        $this->quotationService = $quotationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'q', 'patient_id', 'status', 'created_by', 'date_from', 'date_to', 'expired', 'branch_id',
            ]);

            // #283 S2: un usuario restringido no enumera otra sede aunque no
            // envíe el filtro; pedirla explícitamente responde 403.
            $filters['branch_id'] = BranchAccess::effectiveFilter(
                $request->user(),
                isset($filters['branch_id']) && $filters['branch_id'] !== '' ? (int) $filters['branch_id'] : null,
            );

            $result = $this->quotationService->getQuotations($filters);

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
            Log::error('No se pudieron obtener los presupuestos', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener presupuestos',
            ], 500);
        }
    }

    /**
     * Aggregated counters for the KPI strip.
     */
    public function summary(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->quotationService->summary()]);
        } catch (\Exception $e) {
            Log::error('No se pudo obtener el resumen de presupuestos', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener el resumen de presupuestos',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Si viene treatment_plan_id sin patient_id, derivar el patient del plan.
            // Esto preserva el path generateQuotation (Sprint 0 fix C-1) sin requerir
            // que el cliente envie ambos campos.
            if (empty($validated['patient_id']) && ! empty($validated['treatment_plan_id'])) {
                $plan = \App\Models\TreatmentPlan::find($validated['treatment_plan_id']);
                if ($plan) {
                    $validated['patient_id'] = $plan->patient_id;
                }
            }

            if (empty($validated['patient_id'])) {
                return response()->json([
                    'message' => 'El paciente es obligatorio (o debe venir via treatment_plan_id).',
                    'errors' => ['patient_id' => ['El paciente es obligatorio.']],
                ], 422);
            }

            if (isset($validated['treatment_plan_id'])) {
                $quotation = $this->quotationService->generateQuotation(
                    $validated['treatment_plan_id'],
                    $validated
                );
            } else {
                $quotation = $this->quotationService->createQuotation($validated);
            }

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto creado exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('No se pudo crear el presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al crear presupuesto',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $quotation = \App\Models\Quotation::with([
                'patient',
                'treatmentPlan',
                'createdBy',
                'items',
                'approvals',
            ])->findOrFail($id);

            return response()->json([
                'data' => $quotation,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Presupuesto no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('No se pudo obtener el presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener el presupuesto',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateQuotationRequest $request, int $id): JsonResponse
    {
        try {
            $payload = $request->validated();

            // #283 S4: la autorización registrada viaja como motivo explícito y
            // nunca se escribe como columna del presupuesto.
            $reason = $request->input('lock_override_reason');

            if (is_string($reason) && trim($reason) !== '') {
                $payload['lock_override_reason'] = trim($reason);
            }

            $quotation = $this->quotationService->updateQuotation($id, $payload);

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto actualizado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Presupuesto no encontrado'], 404);
        } catch (AuthorizationException $e) {
            // #283 S5: el bloqueo contable se responde 403, no 500.
            throw $e;
        } catch (\Exception $e) {
            Log::error('No se pudo actualizar el presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al actualizar presupuesto',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $quotation = \App\Models\Quotation::findOrFail($id);

            // Solo permitir eliminar si está en estado draft
            if ($quotation->status !== 'draft') {
                return response()->json([
                    'message' => 'No se puede eliminar un presupuesto que no esté en estado borrador',
                ], 422);
            }

            $quotation->delete();

            return response()->json([
                'meta' => [
                    'message' => 'Presupuesto eliminado exitosamente',
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Presupuesto no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('No se pudo eliminar el presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al eliminar presupuesto',
            ], 500);
        }
    }

    /**
     * Aprobar presupuesto.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'notes' => 'nullable|string',
                'patient_signature' => 'nullable|string',
            ]);

            $quotation = $this->quotationService->approveQuotation($id, $validated);

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto aprobado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Presupuesto no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('No se pudo aprobar el presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al aprobar presupuesto',
            ], 500);
        }
    }

    /**
     * Rechazar presupuesto.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $quotation = $this->quotationService->rejectQuotation($id, $validated['reason']);

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto rechazado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Presupuesto no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('No se pudo rechazar el presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al rechazar presupuesto',
            ], 500);
        }
    }

    /**
     * Descargar PDF del presupuesto.
     */
    public function downloadPDF(int $id)
    {
        try {
            $pdfContent = $this->quotationService->generatePDF($id);
            $quotation = \App\Models\Quotation::findOrFail($id);

            $filename = 'presupuesto_'.$quotation->quotation_number.'.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Presupuesto no encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('No se pudo generar el PDF del presupuesto', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al generar PDF',
            ], 500);
        }
    }

    /**
     * Obtener presupuestos por paciente.
     */
    public function byPatient(int $patientId): JsonResponse
    {
        try {
            $quotations = \App\Models\Quotation::with(['items', 'createdBy'])
                ->where('patient_id', $patientId)
                ->orderBy('quotation_date', 'desc')
                ->get();

            return response()->json([
                'data' => $quotations,
                'meta' => [
                    'total' => $quotations->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('No se pudieron obtener los presupuestos del paciente', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener presupuestos del paciente',
            ], 500);
        }
    }
}
