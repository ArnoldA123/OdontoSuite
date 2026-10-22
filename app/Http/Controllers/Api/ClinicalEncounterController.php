<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Encounter\EncounterException;
use App\Http\Controllers\Controller;
use App\Models\ClinicalEncounter;
use App\Services\ClinicalEncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F2 (#282) — the encounter doors that do not belong to an appointment.
 *
 * `POST /api/encounters` opens the urgency act: no agenda row is invented to
 * hold it (S8). `POST /api/encounters/{id}/void` is the way out of a mistaken
 * opening, and it only works while the act is still open. Closing and reading
 * the encounter belong to T3/T4 and are not declared here.
 */
class ClinicalEncounterController extends Controller
{
    public function __construct(private readonly ClinicalEncounterService $encounters) {}

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
