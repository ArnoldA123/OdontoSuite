<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $service)
    {
    }

    /**
     * GET /api/appointments/ready-to-bill
     */
    public function readyToBill(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['patient_id', 'from', 'to', 'per_page']);
            $page = $this->service->readyToBill($filters);

            $items = collect($page->items())->map(function (Appointment $a) {
                $paid = (float) $a->transactions()
                    ->where('type', 'payment')
                    ->where('status', 'completed')
                    ->sum('amount');

                return [
                    'id' => $a->id,
                    'patient' => $a->patient?->only(['id', 'first_name', 'last_name', 'document_number']),
                    'appointment_type' => $a->appointmentType?->name,
                    'completed_at' => $a->completed_at?->toIso8601String(),
                    'final_amount' => (float) $a->final_amount,
                    'paid_amount' => round($paid, 2),
                    'balance' => round(max(0, (float) $a->final_amount - $paid), 2),
                    'has_quotation' => $a->quotations()->exists(),
                ];
            });

            return response()->json([
                'data' => $items,
                'meta' => [
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('BillingController@readyToBill: ' . $e->getMessage());
            return response()->json(['message' => 'Error al listar citas por cobrar'], 500);
        }
    }

    /**
     * GET /api/appointments/{id}/payment-preview
     */
    public function paymentPreview(Appointment $appointment): JsonResponse
    {
        try {
            return response()->json(['data' => $this->service->paymentPreview($appointment)]);
        } catch (\Throwable $e) {
            Log::error('BillingController@paymentPreview: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener el desglose'], 500);
        }
    }

    /**
     * POST /api/appointments/{id}/generate-quotation
     */
    public function generateQuotation(Appointment $appointment): JsonResponse
    {
        try {
            $appointment->loadMissing('treatmentPlan.items');

            if ((float) ($appointment->final_amount ?? 0) <= 0) {
                return response()->json([
                    'error' => [
                        'code' => 'NO_FINAL_AMOUNT',
                        'message' => 'La cita no tiene un monto final definido.',
                    ],
                ], 422);
            }

            $quotation = $this->service->generateQuotationFromAppointment($appointment);

            return response()->json([
                'data' => $quotation->load(['items', 'patient', 'treatmentPlan']),
                'meta' => [
                    'message' => 'Cotización generada.',
                    'pdf_url' => "/api/quotations/{$quotation->id}/pdf",
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('BillingController@generateQuotation: ' . $e->getMessage());
            return response()->json(['message' => 'Error al generar la cotización'], 500);
        }
    }
}
