<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewAiAnalysisRequest;
use App\Http\Resources\AiImageAnalysisResource;
use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Services\AiImageAnalysisService;
use App\Services\ClinicalAttachmentService;
use App\Support\DateFilter;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiImageAnalysisController extends Controller
{
    protected $aiAnalysisService;

    protected $clinicalAttachmentService;

    public function __construct(
        AiImageAnalysisService $aiAnalysisService,
        ClinicalAttachmentService $clinicalAttachmentService
    ) {
        $this->aiAnalysisService = $aiAnalysisService;
        $this->clinicalAttachmentService = $clinicalAttachmentService;
    }

    /**
     * Solicitar análisis de imagen.
     *
     * #296 — the door requires the patient notice; the clinical act is optional
     * and, when named, it is resolved by the service against the attachment's
     * patient.
     */
    public function analyze(Request $request, int $attachmentId): JsonResponse
    {
        $attachment = ClinicalAttachment::find($attachmentId);

        if (! $attachment) {
            return response()->json([
                'message' => 'Adjunto no encontrado',
            ], 404);
        }

        Gate::authorize('accessPatient', [AiImageAnalysis::class, (int) $attachment->patient_id]);

        $validated = $request->validate($this->analysisRequestRules(), $this->analysisRequestMessages());

        try {
            $analysis = $this->aiAnalysisService->analyzeImage(
                $attachmentId,
                isset($validated['clinical_encounter_id']) ? (int) $validated['clinical_encounter_id'] : null
            );

            return response()->json([
                'data' => new AiImageAnalysisResource($analysis->load([
                    'clinicalAttachment',
                    'clinicalEncounter',
                    'patient',
                    'requestedBy',
                    'reviewedBy',
                    'noticeAcknowledgedBy',
                ])),
                'meta' => [
                    'message' => 'Análisis solicitado exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AI analysis request failed', [
                'attachment_id' => $attachmentId,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al solicitar análisis',
            ], 500);
        }
    }

    /**
     * Obtener análisis específico.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $analysis = AiImageAnalysis::with([
                'clinicalAttachment',
                'clinicalEncounter',
                'patient',
                'requestedBy',
                'reviewedBy',
                'voidedBy',
                'noticeAcknowledgedBy',
            ])->find($id);
        } catch (\Exception $e) {
            Log::error('AI analysis lookup failed', ['id' => $id, 'exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener análisis',
            ], 500);
        }

        if (! $analysis) {
            return response()->json([
                'message' => 'Análisis no encontrado',
            ], 404);
        }

        Gate::authorize('view', $analysis);

        return response()->json([
            'data' => new AiImageAnalysisResource($analysis),
        ]);
    }

    /**
     * Obtener análisis por adjunto.
     */
    public function byAttachment(int $attachmentId): JsonResponse
    {
        try {
            $analysis = $this->aiAnalysisService->getAnalysisByAttachment($attachmentId);
        } catch (\Exception $e) {
            Log::error('AI analysis lookup by attachment failed', [
                'attachment_id' => $attachmentId,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener análisis',
            ], 500);
        }

        if (! $analysis) {
            // Answering "no analysis" before the patient check would let an
            // unauthorized caller tell an attachment with an analysis apart
            // from one without it.
            $attachment = ClinicalAttachment::find($attachmentId);

            if ($attachment) {
                Gate::authorize('accessPatient', [AiImageAnalysis::class, (int) $attachment->patient_id]);
            }

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'No se encontró análisis para este adjunto',
                ],
            ]);
        }

        Gate::authorize('view', $analysis);

        return response()->json([
            'data' => new AiImageAnalysisResource($analysis),
        ]);
    }

    /**
     * Obtener análisis por paciente.
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        Gate::authorize('accessPatient', [AiImageAnalysis::class, $patientId]);

        try {
            $filters = $request->only(['status', 'reviewed', 'date_from', 'date_to', 'per_page']);
            $result = $this->aiAnalysisService->getAnalysisByPatient($patientId, $filters, $request->user());

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('AI analysis lookup by patient failed', [
                'patient_id' => $patientId,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener análisis del paciente',
            ], 500);
        }
    }

    /**
     * Revisar análisis (aceptar/rechazar).
     */
    public function review(ReviewAiAnalysisRequest $request, int $id): JsonResponse
    {
        $analysis = AiImageAnalysis::find($id);

        if (! $analysis) {
            return response()->json([
                'message' => 'Análisis no encontrado',
            ], 404);
        }

        Gate::authorize('update', $analysis);

        try {
            $analysis = $this->aiAnalysisService->reviewAnalysis(
                $id,
                $request->validated()['decision'],
                $request->validated()['notes'] ?? null
            );

            return response()->json([
                'data' => new AiImageAnalysisResource($analysis->load([
                    'clinicalAttachment',
                    'patient',
                    'requestedBy',
                    'reviewedBy',
                ])),
                'meta' => [
                    'message' => 'Análisis revisado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AI analysis review failed', ['id' => $id, 'exception' => $e]);

            return response()->json([
                'message' => 'Error al revisar análisis',
            ], 500);
        }
    }

    /**
     * Obtener análisis pendientes de revisión.
     */
    public function pending(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AiImageAnalysis::class);

        try {
            $filters = $request->only(['requested_by', 'patient_id', 'per_page']);
            $result = $this->aiAnalysisService->getPendingAnalyses($filters, $request->user());

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('AI analysis pending lookup failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener análisis pendientes',
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de uso de IA.
     */
    public function stats(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AiImageAnalysis::class);

        try {
            $filters = $request->only(['date_from', 'date_to']);
            $stats = $this->aiAnalysisService->getUsageStats($filters, $request->user());

            return response()->json([
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('AI analysis stats failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener estadísticas',
            ], 500);
        }
    }

    /**
     * Obtener lista de análisis con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AiImageAnalysis::class);

        try {
            $query = AiImageAnalysis::with([
                'clinicalAttachment',
                'clinicalEncounter',
                'patient',
                'requestedBy',
                'reviewedBy',
                'voidedBy',
            ])->visibleTo($request->user());

            // #296 — an annulled analysis keeps its row: it leaves every list
            // by default and only `?voided=1` lists it, which is the surface
            // that makes the trace consultable.
            if ($request->boolean('voided')) {
                $query->voided();
            } else {
                $query->active();
            }

            $status = $request->input('status');
            if (is_string($status) && $status !== '') {
                $query->where('status', $status);
            }

            if ($request->filled('reviewed')) {
                $query->where('reviewed', $request->boolean('reviewed'));
            }

            if ($request->filled('patient_id')) {
                $query->where('patient_id', (int) $request->input('patient_id'));
            }

            if ($request->filled('requested_by')) {
                $query->where('requested_by', (int) $request->input('requested_by'));
            }

            if ($request->filled('clinical_encounter_id')) {
                $query->where('clinical_encounter_id', (int) $request->input('clinical_encounter_id'));
            }

            $dateFrom = DateFilter::normalize($request->input('date_from'));
            if ($dateFrom !== null) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            $dateTo = DateFilter::normalize($request->input('date_to'));
            if ($dateTo !== null) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $search = $request->input('search');
            if (is_string($search) && trim($search) !== '') {
                $term = '%'.mb_substr(trim($search), 0, 100).'%';
                $query->whereHas('patient', function ($patientQuery) use ($term) {
                    $patientQuery->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term]);
                });
            }

            $perPage = PerPage::resolve($request->input('per_page'));

            $analyses = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'data' => AiImageAnalysisResource::collection($analyses->items())->resolve(),
                'meta' => [
                    'current_page' => $analyses->currentPage(),
                    'last_page' => $analyses->lastPage(),
                    'per_page' => $analyses->perPage(),
                    'total' => $analyses->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AI analysis index failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener análisis',
            ], 500);
        }
    }

    /**
     * Anular análisis conservando la traza de revisión (#296).
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => 'El motivo de la anulación es obligatorio.',
            'reason.max' => 'El motivo de la anulación no puede exceder los 255 caracteres.',
        ]);

        $analysis = AiImageAnalysis::find($id);

        if (! $analysis) {
            return response()->json([
                'message' => 'Análisis no encontrado',
            ], 404);
        }

        Gate::authorize('void', $analysis);

        try {
            $analysis = $this->aiAnalysisService->voidAnalysis(
                $id,
                Str::limit(strip_tags($validated['reason']), 255)
            );

            return response()->json([
                'data' => new AiImageAnalysisResource($analysis->load([
                    'clinicalAttachment',
                    'patient',
                    'requestedBy',
                    'reviewedBy',
                    'voidedBy',
                ])),
                'meta' => [
                    'message' => 'Análisis anulado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AI analysis void failed', ['id' => $id, 'exception' => $e]);

            return response()->json([
                'message' => 'Error al anular análisis',
            ], 500);
        }
    }

    /**
     * Subir imagen y analizar en un solo paso.
     */
    public function analyzeUploadedImage(Request $request): JsonResponse
    {
        // The patient is authorized before the rest of the payload is
        // validated: an unauthorized caller learns nothing about its shape.
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
        ]);

        $patientId = (int) $request->input('patient_id');

        Gate::authorize('accessPatient', [AiImageAnalysis::class, $patientId]);

        $validated = $request->validate([
            'image' => 'required|image|max:20480', // 20MB
            'description' => 'nullable|string|max:500',
            'category' => 'required|in:radiografia,foto_clinica',
        ] + $this->analysisRequestRules(), $this->analysisRequestMessages());

        try {
            // #296: the encounter ownership and the daily cap are checked
            // before the file is stored, so a refused upload leaves no orphan
            // clinical attachment behind.
            $this->aiAnalysisService->assertAnalysisAllowed(
                $patientId,
                isset($validated['clinical_encounter_id']) ? (int) $validated['clinical_encounter_id'] : null
            );

            // 1. Guardar imagen como ClinicalAttachment
            $attachment = $this->clinicalAttachmentService->store([
                'patient_id' => $patientId,
                'clinical_encounter_id' => isset($validated['clinical_encounter_id'])
                    ? (int) $validated['clinical_encounter_id']
                    : null,
                'file' => $validated['image'],
                'category' => $validated['category'],
                'description' => $validated['description'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // 2. Analizar imagen inmediatamente
            $analysis = $this->aiAnalysisService->analyzeImage(
                $attachment->id,
                isset($validated['clinical_encounter_id']) ? (int) $validated['clinical_encounter_id'] : null
            );

            return response()->json([
                'data' => new AiImageAnalysisResource($analysis->load([
                    'clinicalAttachment',
                    'clinicalEncounter',
                    'patient',
                    'requestedBy',
                    'reviewedBy',
                    'noticeAcknowledgedBy',
                ])),
                'message' => 'Análisis completado exitosamente.',
                'meta' => [
                    'message' => 'Análisis completado exitosamente.',
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error analyzing uploaded image: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al analizar la imagen.',
            ], 500);
        }
    }

    /**
     * #296 — rules shared by both creation doors: the patient notice is a
     * declaration the professional makes, and the clinical act is optional.
     *
     * @return array<string, mixed>
     */
    private function analysisRequestRules(): array
    {
        return [
            'notice_acknowledged' => 'accepted',
            'clinical_encounter_id' => 'nullable|integer|exists:clinical_encounters,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function analysisRequestMessages(): array
    {
        return [
            'notice_acknowledged.accepted' => 'Debe declarar que informó al paciente que el resultado es apoyo asistido por IA.',
        ];
    }
}
