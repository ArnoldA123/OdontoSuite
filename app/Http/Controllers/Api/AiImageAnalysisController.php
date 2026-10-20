<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewAiAnalysisRequest;
use App\Models\AiImageAnalysis;
use App\Services\AiImageAnalysisService;
use App\Services\ClinicalAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
     */
    public function analyze(int $attachmentId): JsonResponse
    {
        try {
            $analysis = $this->aiAnalysisService->analyzeImage($attachmentId);

            return response()->json([
                'data' => $analysis,
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
                'patient',
                'requestedBy',
                'reviewedBy',
            ])->find($id);

            if (! $analysis) {
                return response()->json([
                    'message' => 'Análisis no encontrado',
                ], 404);
            }

            return response()->json([
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('AI analysis lookup failed', ['id' => $id, 'exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener análisis',
            ], 500);
        }
    }

    /**
     * Obtener análisis por adjunto.
     */
    public function byAttachment(int $attachmentId): JsonResponse
    {
        try {
            $analysis = $this->aiAnalysisService->getAnalysisByAttachment($attachmentId);

            if (! $analysis) {
                return response()->json([
                    'data' => null,
                    'meta' => [
                        'message' => 'No se encontró análisis para este adjunto',
                    ],
                ]);
            }

            return response()->json([
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('AI analysis lookup by attachment failed', [
                'attachment_id' => $attachmentId,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener análisis',
            ], 500);
        }
    }

    /**
     * Obtener análisis por paciente.
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'reviewed', 'date_from', 'date_to']);
            $result = $this->aiAnalysisService->getAnalysisByPatient($patientId, $filters);

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
        try {
            $analysis = $this->aiAnalysisService->reviewAnalysis(
                $id,
                $request->validated()['decision'],
                $request->validated()['notes']
            );

            return response()->json([
                'data' => $analysis,
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
        try {
            $filters = $request->only(['requested_by', 'patient_id']);
            $result = $this->aiAnalysisService->getPendingAnalyses($filters);

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
        try {
            $filters = $request->only(['date_from', 'date_to']);
            $stats = $this->aiAnalysisService->getUsageStats($filters);

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
        try {
            $query = AiImageAnalysis::with([
                'clinicalAttachment',
                'patient',
                'requestedBy',
                'reviewedBy',
            ]);

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

            $dateFrom = $request->input('date_from');
            if (is_string($dateFrom) && $dateFrom !== '') {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            $dateTo = $request->input('date_to');
            if (is_string($dateTo) && $dateTo !== '') {
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

            $perPage = $request->filled('per_page') ? (int) $request->input('per_page') : 15;
            $perPage = max(1, min(100, $perPage));

            $analyses = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'data' => $analyses->items(),
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
     * Eliminar análisis.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $analysis = AiImageAnalysis::find($id);

            if (! $analysis) {
                return response()->json([
                    'message' => 'Análisis no encontrado',
                ], 404);
            }

            // Solo permitir eliminar si no ha sido revisado
            if ($analysis->reviewed) {
                return response()->json([
                    'message' => 'No se puede eliminar un análisis que ya ha sido revisado',
                ], 422);
            }

            $analysis->delete();

            return response()->json([
                'meta' => [
                    'message' => 'Análisis eliminado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AI analysis delete failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al eliminar análisis',
            ], 500);
        }
    }

    /**
     * Subir imagen y analizar en un solo paso.
     */
    public function analyzeUploadedImage(Request $request): JsonResponse
    {
        // Log de datos recibidos para debugging
        Log::info('Datos recibidos en analyzeUploadedImage:', [
            'patient_id' => $request->patient_id,
            'category' => $request->category,
            'description' => $request->description,
            'has_image' => $request->hasFile('image'),
            'image_size' => $request->file('image') ? $request->file('image')->getSize() : null,
        ]);

        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'image' => 'required|image|max:20480', // 20MB
            'description' => 'nullable|string|max:500',
            'category' => 'required|in:radiografia,foto_clinica',
        ]);

        try {
            // 1. Guardar imagen como ClinicalAttachment
            $attachment = $this->clinicalAttachmentService->store([
                'patient_id' => $request->patient_id,
                'file' => $request->file('image'),
                'category' => $request->category,
                'description' => $request->description,
                'created_by' => Auth::id(),
            ]);

            // 2. Analizar imagen inmediatamente
            $analysis = $this->aiAnalysisService->analyzeImage($attachment->id);

            return response()->json([
                'data' => $analysis,
                'message' => 'Análisis completado exitosamente.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error analyzing uploaded image: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al analizar la imagen.',
            ], 500);
        }
    }
}
