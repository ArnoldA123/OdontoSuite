<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Consultation\ConsultationException;
use App\Exceptions\Encounter\EncounterException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseClinicalEncounterRequest;
use App\Models\ClinicalEncounter;
use App\Services\ClinicalEncounterService;
use App\Services\ConsultationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * F2 (#282) — the encounter doors that do not belong to an appointment.
 *
 * `POST /api/encounters` opens the urgency act: no agenda row is invented to
 * hold it (S8). `POST /api/encounters/{id}/complete` closes it with the same
 * engine the appointment door uses — one closure, two doors. `POST
 * /api/encounters/{id}/void` is the way out of a mistaken opening, and it only
 * works while the act is still open. Reading belongs to T4.
 */
class ClinicalEncounterController extends Controller
{
    public function __construct(
        private readonly ClinicalEncounterService $encounters,
        private readonly ConsultationService $consultations,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        // The professional of the act is the authenticated one. Naming another
        // professional is an administrative act: the actor must be able to
        // answer for the record it attributes.
        if (! $request->user()->isAdministrador()) {
            $data['user_id'] = $request->user()->id;
        }

        try {
            $encounter = $this->encounters->openUrgent($data, $request->user());
        } catch (EncounterException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        }

        return response()->json([
            'data' => $this->resource($encounter),
            'meta' => ['message' => 'Atención de urgencia abierta.'],
        ], 201);
    }

    /**
     * Close the act. The urgency door of the single closure: it produces the
     * evolution, findings, procedures, materials, attachments, amount and
     * quotation, and it never touches an agenda row because there is none.
     */
    public function complete(CloseClinicalEncounterRequest $request, ClinicalEncounter $encounter): JsonResponse
    {
        try {
            $result = $this->consultations->close($encounter, $request->validated());
        } catch (ConsultationException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        } catch (EncounterException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('Clinical encounter close failed', [
                'encounter_id' => $encounter->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al cerrar la atención.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        $closed = $result['encounter'];
        $quotation = $result['quotation'];

        return response()->json([
            'data' => $this->resource($closed),
            'quotation' => $quotation
                ? $quotation->loadMissing(['items', 'patient', 'treatmentPlan'])
                : null,
            'meta' => [
                'message' => 'Atención cerrada.',
                'final_amount' => (float) $closed->final_amount,
                'mode' => $closed->mode,
                'quotation_generated' => (bool) $quotation,
                'quotation_id' => $quotation?->id,
            ],
        ]);
    }

    public function void(Request $request, ClinicalEncounter $encounter): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $encounter = $this->encounters->void($encounter, $request->user(), $data['reason'] ?? null);
        } catch (EncounterException $e) {
            return response()->json(['error' => $e->toArray()], $e->httpStatus);
        }

        return response()->json([
            'data' => $this->resource($encounter),
            'meta' => ['message' => 'Atención anulada.'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(ClinicalEncounter $encounter): array
    {
        $encounter->loadMissing('patient', 'user');

        return [
            'id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'appointment_id' => $encounter->appointment_id,
            'user_id' => $encounter->user_id,
            'mode' => $encounter->mode,
            'status' => $encounter->status,
            'opened_at' => $encounter->opened_at?->toIso8601String(),
            'closed_at' => $encounter->closed_at?->toIso8601String(),
            'closed_by' => $encounter->closed_by,
            'medical_record_id' => $encounter->medical_record_id,
            'treatment_plan_id' => $encounter->treatment_plan_id,
            'final_amount' => $encounter->final_amount !== null ? (float) $encounter->final_amount : null,
            'voided_at' => $encounter->voided_at?->toIso8601String(),
            'voided_by' => $encounter->voided_by,
            'void_reason' => $encounter->void_reason,
            'notes' => $encounter->notes,
            'patient' => $encounter->patient !== null ? [
                'id' => $encounter->patient->id,
                'first_name' => $encounter->patient->first_name,
                'last_name' => $encounter->patient->last_name,
                'full_name' => $encounter->patient->full_name,
                'document_number' => $encounter->patient->document_number,
            ] : null,
            'user' => $encounter->user !== null ? [
                'id' => $encounter->user->id,
                'name' => $encounter->user->name,
                'role' => $encounter->user->role,
            ] : null,
        ];
    }
}
