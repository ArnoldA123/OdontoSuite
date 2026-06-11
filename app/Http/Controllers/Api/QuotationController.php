<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
                'patient_id', 'status', 'created_by', 'date_from', 'date_to', 'expired', 'branch_id'
            ]);

            $result = $this->quotationService->getQuotations($filters);

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
                'message' => 'Error al obtener presupuestos',
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
                'treatment_plan_id' => 'nullable|exists:treatment_plans,id',
                'patient_id' => 'required|exists:patients,id',
                'quotation_date' => 'nullable|date',
                'valid_until' => 'nullable|date|after:quotation_date',
                'subtotal' => 'required|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_percentage' => 'nullable|numeric|min:0|max:100',
                'terms_conditions' => 'nullable|string',
                'notes' => 'nullable|string',
                'payment_terms' => 'nullable|array',
                'items' => 'nullable|array',
                'items.*.description' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|numeric|min:1',
                'items.*.unit_price' => 'required_with:items|numeric|min:0'
            ]);

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
                    'message' => 'Presupuesto creado exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear presupuesto',
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
            $quotation = \App\Models\Quotation::with([
                'patient',
                'treatmentPlan',
                'createdBy',
                'items',
                'approvals'
            ])->findOrFail($id);

            return response()->json([
                'data' => $quotation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Presupuesto no encontrado',
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
                'quotation_date' => 'nullable|date',
                'valid_until' => 'nullable|date|after:quotation_date',
                'subtotal' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_percentage' => 'nullable|numeric|min:0|max:100',
                'terms_conditions' => 'nullable|string',
                'notes' => 'nullable|string',
                'payment_terms' => 'nullable|array',
                'items' => 'nullable|array',
                'items.*.description' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|numeric|min:1',
                'items.*.unit_price' => 'required_with:items|numeric|min:0'
            ]);

            $quotation = $this->quotationService->updateQuotation($id, $validated);

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto actualizado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar presupuesto',
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
            $quotation = \App\Models\Quotation::findOrFail($id);

            // Solo permitir eliminar si está en estado draft
            if ($quotation->status !== 'draft') {
                return response()->json([
                    'message' => 'No se puede eliminar un presupuesto que no esté en estado borrador'
                ], 422);
            }

            $quotation->delete();

            return response()->json([
                'meta' => [
                    'message' => 'Presupuesto eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar presupuesto
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'notes' => 'nullable|string',
                'patient_signature' => 'nullable|string'
            ]);

            $quotation = $this->quotationService->approveQuotation($id, $validated);

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto aprobado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aprobar presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar presupuesto
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $quotation = $this->quotationService->rejectQuotation($id, $validated['reason']);

            return response()->json([
                'data' => $quotation,
                'meta' => [
                    'message' => 'Presupuesto rechazado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al rechazar presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar PDF del presupuesto
     */
    public function downloadPDF(int $id)
    {
        try {
            $pdfContent = $this->quotationService->generatePDF($id);
            $quotation = \App\Models\Quotation::findOrFail($id);

            $filename = 'presupuesto_' . $quotation->quotation_number . '.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener presupuestos por paciente
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
                    'total' => $quotations->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener presupuestos del paciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
