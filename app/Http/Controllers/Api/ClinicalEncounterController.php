<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Consultation\ConsultationException;
use App\Exceptions\Encounter\EncounterException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseClinicalEncounterRequest;
use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\EncounterDiagnosis;
use App\Models\OdontogramRecord;
use App\Models\ProcedureMaterial;
use App\Models\Quotation;
use App\Services\ClinicalEncounterService;
use App\Services\ClinicalRecordAuditService;
use App\Services\ConsultationService;
use App\Support\ClinicalRecordSignature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2 (#282) — the encounter doors that do not belong to an appointment.
 *
 * `POST /api/encounters` opens the urgency act: no agenda row is invented to
 * hold it (S8). `POST /api/encounters/{id}/complete` closes it with the same
 * engine the appointment door uses — one closure, two doors. `POST
 * /api/encounters/{id}/void` is the way out of a mistaken opening, and it only
 * works while the act is still open. `GET /api/encounters` and `GET
 * /api/encounters/{id}` are the read surface of the act itself, which is what
 * makes an urgency act visible at all (S1/S5).
 */
class ClinicalEncounterController extends Controller
{
    public function __construct(
        private readonly ClinicalEncounterService $encounters,
        private readonly ConsultationService $consultations,
        private readonly ClinicalRecordAuditService $clinicalRecordAudit,
    ) {}

    /**
     * List the acts. An encounter without an appointment — the urgency act —
     * has no agenda row to be found by, so this index is its only entry point to
     * the record (S5).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => 'sometimes|integer',
            'appointment_id' => 'sometimes|integer',
            'status' => 'sometimes|in:open,closed,voided',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = ClinicalEncounter::query()->with(['patient', 'user', 'appointment']);

        if (isset($data['patient_id'])) {
            $query->forPatient((int) $data['patient_id']);
        }

        if (isset($data['appointment_id'])) {
            $query->where('appointment_id', (int) $data['appointment_id']);
        }

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        // Both edges of the range are days, so a single-day window returns the
        // acts of that day instead of an empty set.
        if (isset($data['from'])) {
            $query->whereDate('opened_at', '>=', $data['from']);
        }

        if (isset($data['to'])) {
            $query->whereDate('opened_at', '<=', $data['to']);
        }

        $encounters = $query
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => array_map(
                fn (ClinicalEncounter $encounter): array => $this->resource($encounter),
                $encounters->items(),
            ),
            'meta' => [
                'current_page' => $encounters->currentPage(),
                'last_page' => $encounters->lastPage(),
                'per_page' => $encounters->perPage(),
                'total' => $encounters->total(),
            ],
        ]);
    }

    /**
     * The detail of the act: everything the closure produced, read from the
     * encounter and not through the appointment projection (S3).
     */
    public function show(ClinicalEncounter $encounter): JsonResponse
    {
        $encounter->loadMissing([
            'patient',
            'user',
            'appointment',
            'medicalRecord',
            'treatmentPlan',
            'closedBy',
            'voidedBy',
            'clinicalEvolutions',
            'odontogramRecords.dentalPiece',
            'procedureMaterials.product',
            'attachments',
            'quotations',
            'aiImageAnalyses.reviewedBy',
            'diagnoses.catalog',
            'signedBy',
        ]);

        return response()->json([
            'data' => array_merge($this->resource($encounter), $this->detail($encounter)),
        ]);
    }

    /**
     * F6 (#286) — the printable record of the act (NTS 139).
     *
     * The document is emitted from a closed act, which is the signed one: an
     * open act has no record to print yet. Reading the record is a clinical
     * read, so it leaves the same trail as the history (#292).
     */
    public function record(ClinicalEncounter $encounter): Response|JsonResponse
    {
        if (! $encounter->isClosed()) {
            return response()->json([
                'error' => [
                    'error_code' => 'encounter_not_closed',
                    'message' => 'El registro de atención se emite sobre una atención cerrada.',
                    'context' => ['current_status' => $encounter->status],
                ],
            ], 422);
        }

        $encounter->loadMissing([
            'patient',
            'user',
            'signedBy',
            'medicalRecord',
            'appointment.appointmentType',
            'clinicalEvolutions',
            'diagnoses.catalog',
            'odontogramRecords.dentalPiece',
            'odontogramRecords.toothSurface',
            'procedureMaterials.product',
            'continuityRequests',
            'treatmentPlan.items',
        ]);

        if ($encounter->medicalRecord !== null) {
            $this->clinicalRecordAudit->recordViewed(
                $encounter->medicalRecord,
                ClinicalRecordAuditService::SURFACE_ATTENTION_RECORD,
            );
        }

        $pdf = Pdf::loadView('reports.encounter-record', [
            'encounter' => $encounter,
            'signatureVerified' => ClinicalRecordSignature::matches($encounter),
            'nextAppointment' => $encounter->continuityRequests->firstWhere('status', 'pending'),
        ]);

        return $pdf->download("registro-atencion-{$encounter->id}.pdf");
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            // F6 (#286): the urgency act records its own complaint at opening;
            // the close still requires it when the act arrived without one.
            'reason_for_consultation' => 'nullable|string|max:1000',
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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
            'reason_for_consultation' => $encounter->reason_for_consultation,
            'signed_at' => $encounter->signed_at?->toIso8601String(),
            'signed_by' => $encounter->signed_by,
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

    /**
     * The extra projection of `show()`. It stays apart from `resource()` so the
     * three write actions keep their exact response shape.
     *
     * @return array<string, mixed>
     */
    private function detail(ClinicalEncounter $encounter): array
    {
        return [
            'closed_by_user' => $encounter->closedBy?->name,
            // F6 (#286): the NTS 139 record of the act.
            'reason_for_consultation' => $encounter->reason_for_consultation,
            'diagnoses' => $encounter->diagnoses
                ->map(fn (EncounterDiagnosis $diagnosis): array => [
                    'id' => $diagnosis->id,
                    'diagnosis_catalog_id' => $diagnosis->diagnosis_catalog_id,
                    'cie_code' => $diagnosis->cie_code,
                    'name' => $diagnosis->name,
                    'type' => $diagnosis->type,
                    'notes' => $diagnosis->notes,
                ])
                ->values()
                ->all(),
            'signature' => [
                'signed' => $encounter->isSigned(),
                'signed_at' => $encounter->signed_at?->toIso8601String(),
                'signed_by' => $encounter->signed_by,
                'signed_by_user' => $encounter->signedBy?->name,
                'license' => $encounter->signer_license,
                'hash' => $encounter->signature_hash,
                'verified' => ClinicalRecordSignature::matches($encounter),
            ],
            'evolutions' => $encounter->clinicalEvolutions
                ->map(fn (ClinicalEvolution $evolution): array => [
                    'id' => $evolution->id,
                    'evolution_date' => $evolution->evolution_date?->toDateString(),
                    'specialty' => $evolution->specialty,
                    'assessment' => $evolution->assessment,
                    'plan' => $evolution->plan,
                ])
                ->values()
                ->all(),
            'odontogram_records' => $encounter->odontogramRecords
                ->map(fn (OdontogramRecord $record): array => [
                    'id' => $record->id,
                    'dental_piece_id' => $record->dental_piece_id,
                    'dental_piece' => $record->relationLoaded('dentalPiece')
                        ? $record->dentalPiece?->fdi_number
                        : null,
                    'condition_code' => $record->condition_code,
                    'condition_name' => $record->condition_name,
                    'diagnosis' => $record->diagnosis,
                ])
                ->values()
                ->all(),
            'materials' => $encounter->procedureMaterials
                ->map(fn (ProcedureMaterial $material): array => [
                    'id' => $material->id,
                    'product_id' => $material->product_id,
                    'product' => $material->product?->name,
                    'quantity_used' => (float) $material->quantity_used,
                    'total_cost' => $material->total_cost !== null ? (float) $material->total_cost : null,
                ])
                ->values()
                ->all(),
            'attachments' => $encounter->attachments
                ->map(fn (ClinicalAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'file_type' => $attachment->file_type,
                    'category' => $attachment->category,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'quotations' => $encounter->quotations
                ->map(fn (Quotation $quotation): array => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'status' => $quotation->status,
                    'total_amount' => (float) $quotation->total_amount,
                ])
                ->values()
                ->all(),
            // S4 (#296): an unreviewed analysis has not been incorporated into
            // the record and a voided one left it, so only reviewed, active
            // analyses appear in the act.
            'ai_analyses' => $encounter->aiImageAnalyses
                ->filter(fn (AiImageAnalysis $analysis): bool => (bool) $analysis->reviewed && ! $analysis->isVoided())
                ->map(fn (AiImageAnalysis $analysis): array => [
                    'id' => $analysis->id,
                    'review_decision' => $analysis->review_decision,
                    'reviewed_at' => $analysis->reviewed_at?->toIso8601String(),
                    'reviewed_by' => $analysis->reviewedBy?->name,
                    'confidence_score' => $analysis->confidence_score,
                    'created_at' => $analysis->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
