<?php

namespace App\Services;

use App\Http\Resources\AiImageAnalysisResource;
use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\User;
use App\Support\DateFilter;
use App\Support\PerPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AiImageAnalysisService
{
    protected $openaiConfig;

    public function __construct()
    {
        $this->openaiConfig = config('services.openai');
    }

    /**
     * Analizar imagen con IA.
     *
     * #296 — both creation doors funnel here. The clinical act is the
     * attachment's own act unless the request names one, and a named act must
     * belong to the same patient as the attachment. The declaration of the
     * patient notice is required by both controllers, so the row records its
     * author and its moment here, once, at creation.
     */
    public function analyzeImage(int $attachmentId, ?int $clinicalEncounterId = null): AiImageAnalysis
    {
        $attachment = ClinicalAttachment::with(['patient'])->findOrFail($attachmentId);

        // Validar que sea una imagen de radiografía
        if ($attachment->category !== 'radiografia' && $attachment->file_type !== 'image') {
            throw ValidationException::withMessages([
                'attachment' => ['Solo se pueden analizar imágenes de radiografías con IA.'],
            ]);
        }

        // Verificar si ya existe un análisis
        $existingAnalysis = AiImageAnalysis::where('clinical_attachment_id', $attachmentId)
            ->active()
            ->where('status', '!=', 'failed')
            ->first();

        if ($existingAnalysis) {
            return $existingAnalysis;
        }

        // One resolver for both doors: the act the analysis supports is the one
        // the request names, and it must describe the same patient.
        $clinicalEncounterId = $this->resolveClinicalEncounterId($attachment, $clinicalEncounterId);

        $this->ensureDailyLimitIsNotReached((int) $attachment->patient_id);

        DB::beginTransaction();

        try {
            // Crear registro de análisis
            $analysis = AiImageAnalysis::create([
                'clinical_attachment_id' => $attachmentId,
                'clinical_encounter_id' => $clinicalEncounterId,
                'patient_id' => $attachment->patient_id,
                'requested_by' => Auth::id(),
                'status' => 'pending',
                'model_used' => $this->openaiConfig['model'],
                'notice_acknowledged_at' => now(),
                'notice_acknowledged_by' => Auth::id(),
            ]);

            // Procesar análisis de forma asíncrona
            $this->processAnalysis($analysis);

            DB::commit();

            return $analysis->load([
                'clinicalAttachment',
                'clinicalEncounter',
                'patient',
                'requestedBy',
                'noticeAcknowledgedBy',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating AI analysis', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * #296 — the upload door stores the attachment before analysing it, so the
     * two refusals that depend only on the patient run first: a rejected upload
     * must not leave an orphan clinical attachment (and its file) behind.
     */
    public function assertAnalysisAllowed(int $patientId, ?int $clinicalEncounterId): void
    {
        if ($clinicalEncounterId !== null) {
            $this->assertEncounterBelongsToPatient($clinicalEncounterId, $patientId);
        }

        $this->ensureDailyLimitIsNotReached($patientId);
    }

    /**
     * Resolve the clinical act of the analysis: the named one when the request
     * names it, the attachment's own act otherwise.
     */
    protected function resolveClinicalEncounterId(
        ClinicalAttachment $attachment,
        ?int $clinicalEncounterId
    ): ?int {
        if ($clinicalEncounterId === null) {
            return $attachment->clinical_encounter_id !== null
                ? (int) $attachment->clinical_encounter_id
                : null;
        }

        $this->assertEncounterBelongsToPatient($clinicalEncounterId, (int) $attachment->patient_id);

        return $clinicalEncounterId;
    }

    /**
     * One definition of the ownership rule, shared by the pre-flight check of
     * the upload door and the resolver of the analysis.
     */
    protected function assertEncounterBelongsToPatient(int $clinicalEncounterId, int $patientId): void
    {
        $belongsToThePatient = ClinicalEncounter::query()
            ->whereKey($clinicalEncounterId)
            ->where('patient_id', $patientId)
            ->exists();

        if (! $belongsToThePatient) {
            throw ValidationException::withMessages([
                'clinical_encounter_id' => ['La atención seleccionada no pertenece al paciente.'],
            ]);
        }
    }

    /**
     * #296 — the daily usage cap is a clinic policy (`0` means no cap). It
     * counts analyses created today for the same patient; reusing an existing
     * analysis never reaches this check.
     */
    protected function ensureDailyLimitIsNotReached(int $patientId): void
    {
        $limit = (int) ($this->openaiConfig['daily_analysis_limit_per_patient'] ?? 0);

        if ($limit <= 0) {
            return;
        }

        $today = AiImageAnalysis::query()
            ->where('patient_id', $patientId)
            ->active()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($today >= $limit) {
            throw ValidationException::withMessages([
                'patient_id' => ['Se alcanzó el límite diario de análisis para este paciente.'],
            ]);
        }
    }

    /**
     * Optimizar imagen antes de enviar a OpenAI (sin GD).
     */
    protected function optimizeImage(string $imagePath): string
    {
        // Verificar que el archivo existe
        if (! file_exists($imagePath)) {
            throw new \Exception('Archivo de imagen no encontrado');
        }

        // Obtener información de la imagen
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            throw new \Exception('No se pudo obtener información de la imagen');
        }

        $mimeType = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $fileSize = filesize($imagePath);

        Log::info('Información de imagen original:', [
            'width' => $width,
            'height' => $height,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ]);

        // Si la imagen es muy grande (> 2MB), intentar reducirla
        if ($fileSize > 2 * 1024 * 1024) {
            Log::warning('Imagen muy grande, se enviará tal como está', [
                'file_size' => $fileSize,
                'max_recommended' => '2MB',
            ]);
        }

        // Leer el archivo directamente
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new \Exception('No se pudo leer el archivo de imagen');
        }

        // Si es WEBP, intentar convertir a JPEG usando GD si está disponible
        if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
            try {
                $image = imagecreatefromwebp($imagePath);
                if ($image !== false) {
                    ob_start();
                    imagejpeg($image, null, 90);
                    $imageData = ob_get_clean();
                    imagedestroy($image);
                    $mimeType = 'image/jpeg';
                    Log::info('Imagen WEBP convertida a JPEG');
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo convertir WEBP a JPEG, usando original', ['error' => $e->getMessage()]);
            }
        }

        // Convertir a base64
        $base64Data = base64_encode($imageData);

        Log::info('Imagen procesada:', [
            'base64_length' => strlen($base64Data),
            'original_size' => $fileSize,
        ]);

        return $base64Data;
    }

    /**
     * Procesar análisis con OpenAI.
     */
    protected function processAnalysis(AiImageAnalysis $analysis): void
    {
        try {
            $analysis->update(['status' => 'processing']);

            $attachment = $analysis->clinicalAttachment;
            $imagePath = Storage::disk('public')->path($attachment->file_path);

            // Verificar que el archivo existe
            if (! file_exists($imagePath)) {
                throw new \Exception('Archivo de imagen no encontrado');
            }

            // Validar que sea una imagen válida
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                throw new \Exception('El archivo no es una imagen válida');
            }

            // Verificar tamaño de archivo
            $fileSize = filesize($imagePath);
            $maxSize = 5 * 1024 * 1024; // 5 MB

            if ($fileSize > $maxSize) {
                Log::info('Imagen muy grande, será optimizada', [
                    'original_size' => $fileSize,
                    'max_size' => $maxSize,
                ]);
            }

            // Procesar imagen (sin optimización GD)
            $imageData = $this->optimizeImage($imagePath);
            $mimeType = $attachment->mime_type; // Usar MIME type original

            // Construir prompt especializado
            $prompt = $this->buildPrompt();

            // Llamar a OpenAI API
            $response = $this->callOpenAI($imageData, $mimeType, $prompt);

            // Procesar respuesta
            $this->processResponse($analysis, $response);

        } catch (\Exception $e) {
            Log::error('Error processing AI analysis', [
                'analysis_id' => $analysis->id,
                'error' => $e->getMessage(),
            ]);

            $analysis->update([
                'status' => 'failed',
                'raw_response' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Construir prompt especializado para análisis odontológico.
     */
    protected function buildPrompt(): string
    {
        return 'Eres un sistema de análisis de imágenes dentales para uso educativo y de investigación en odontología. Tu función es analizar radiografías dentales para fines académicos y de aprendizaje.

CONTEXTO: Esta es una imagen de radiografía dental que necesito analizar para un proyecto educativo en odontología. No es para diagnóstico médico real, sino para fines de aprendizaje y estudio.

Por favor, analiza esta imagen dental y proporciona información educativa en formato JSON:

{
  "findings": [
    {
      "diagnosis": "Hallazgo observado en la imagen",
      "location": "Ubicación en la boca",
      "confidence": 85,
      "description": "Descripción del hallazgo",
      "severity": "leve|moderado|severo"
    }
  ],
  "recommendations": [
    "Observación educativa",
    "Punto de estudio"
  ],
  "overall_assessment": "Evaluación general de lo observado",
  "confidence_score": 85
}

Observa y describe:
- Estructuras dentales visibles
- Anatomía normal
- Características de la imagen
- Elementos técnicos de la radiografía

Responde únicamente en formato JSON válido.';
    }

    /**
     * Llamar a OpenAI API.
     */
    protected function callOpenAI(string $imageData, string $mimeType, string $prompt): array
    {
        $response = Http::timeout((int) $this->openaiConfig['timeout'])
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->openaiConfig['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->openaiConfig['model'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => (int) $this->openaiConfig['max_tokens'],
                'temperature' => 0.3,
            ]);

        if (! $response->successful()) {
            throw new \Exception('Error en la API de OpenAI: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Procesar respuesta de OpenAI.
     */
    protected function processResponse(AiImageAnalysis $analysis, array $response): void
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];
        $tokens = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null;

        // Intentar parsear JSON
        $parsedData = $this->parseResponse($content);

        $analysis->update([
            'status' => 'completed',
            'prompt_sent' => $this->buildPrompt(),
            'findings' => $parsedData['findings'] ?? [],
            'recommendations' => $parsedData['recommendations'] ?? [],
            'confidence_score' => $parsedData['confidence_score'] ?? null,
            'raw_response' => $content,
            'tokens_used' => $tokens,
            'estimated_cost' => $this->estimateCost($tokens),
        ]);
    }

    /**
     * #296 — the provider cost of one analysis, in USD with four decimals.
     * Without a token count there is no cost to record; a failed analysis never
     * reaches this method, so its cost stays empty.
     */
    protected function estimateCost(?int $tokens): ?float
    {
        if ($tokens === null) {
            return null;
        }

        $pricePer1kTokens = (float) ($this->openaiConfig['price_per_1k_tokens'] ?? 0);

        return round(($tokens / 1000) * $pricePer1kTokens, 4);
    }

    /**
     * Parsear respuesta JSON de la IA.
     */
    protected function parseResponse(string $content): array
    {
        // Log de la respuesta para debugging
        Log::info('Respuesta de OpenAI:', ['content' => $content]);

        // Detectar rechazo de OpenAI
        if (stripos($content, 'no puedo ayudar') !== false ||
            stripos($content, "can't help") !== false ||
            stripos($content, 'cannot assist') !== false) {
            Log::warning('OpenAI rechazó el análisis de la imagen');

            return [
                'findings' => [
                    [
                        'diagnosis' => 'Análisis rechazado por IA',
                        'location' => 'No especificado',
                        'confidence' => 0,
                        'description' => 'La IA no pudo procesar esta imagen. Posibles causas: formato no compatible, tamaño muy grande, o contenido no reconocido como radiografía dental.',
                        'severity' => 'leve',
                    ],
                ],
                'recommendations' => [
                    'Verificar que la imagen sea una radiografía dental clara',
                    'Intentar con una imagen de menor tamaño o mejor calidad',
                    'Revisar manualmente la imagen',
                ],
                'confidence_score' => 0,
            ];
        }

        // Limpiar contenido para extraer JSON
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            Log::info('JSON extraído:', ['json' => $jsonString]);

            $decoded = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                Log::info('JSON parseado exitosamente:', ['decoded' => $decoded]);

                return $decoded;
            } else {
                Log::error('Error parseando JSON:', ['error' => json_last_error_msg()]);
            }
        } else {
            Log::error('No se encontró JSON válido en la respuesta');
        }

        // Fallback: crear estructura básica
        Log::warning('Usando fallback para respuesta de IA');

        return [
            'findings' => [
                [
                    'diagnosis' => 'Análisis no disponible',
                    'location' => 'No especificado',
                    'confidence' => 0,
                    'description' => 'No se pudo procesar la imagen correctamente',
                    'severity' => 'leve',
                ],
            ],
            'recommendations' => ['Revisar manualmente la imagen'],
            'confidence_score' => 0,
        ];
    }

    /**
     * Revisar análisis (aceptar/rechazar).
     */
    public function reviewAnalysis(int $analysisId, string $decision, ?string $notes = null): AiImageAnalysis
    {
        $analysis = AiImageAnalysis::findOrFail($analysisId);

        if ($analysis->isVoided()) {
            throw ValidationException::withMessages([
                'analysis' => ['No se puede revisar un análisis anulado.'],
            ]);
        }

        if ($analysis->reviewed) {
            throw ValidationException::withMessages([
                'analysis' => ['Este análisis ya ha sido revisado.'],
            ]);
        }

        $analysis->markAsReviewed(Auth::user(), $decision, $notes);

        return $analysis->load(['reviewedBy']);
    }

    /**
     * #296 — annul an analysis instead of deleting it. The row keeps its review
     * trace (`reviewed`, `reviewed_by`, `reviewed_at`, `review_decision`,
     * `review_notes`): that trace is what makes the analysis auditable after it
     * leaves the working set.
     */
    public function voidAnalysis(int $analysisId, string $reason): AiImageAnalysis
    {
        $analysis = AiImageAnalysis::findOrFail($analysisId);

        if ($analysis->isVoided()) {
            throw ValidationException::withMessages([
                'analysis' => ['Este análisis ya ha sido anulado.'],
            ]);
        }

        // #296: an analysis that a clinical document cites is part of the
        // record; annulling it would leave that document citing nothing.
        app(AiAnalysisCitationService::class)->assertNotCited($analysis);

        $analysis->update([
            'voided_at' => now(),
            'voided_by' => Auth::id(),
            'void_reason' => $reason,
        ]);

        return $analysis;
    }

    /**
     * Obtener análisis por adjunto.
     */
    public function getAnalysisByAttachment(int $attachmentId): ?AiImageAnalysis
    {
        return AiImageAnalysis::with(['clinicalAttachment', 'patient', 'requestedBy', 'reviewedBy'])
            ->active()
            ->where('clinical_attachment_id', $attachmentId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Obtener análisis por paciente.
     */
    public function getAnalysisByPatient(int $patientId, array $filters = [], ?User $viewer = null): array
    {
        $query = AiImageAnalysis::with(['clinicalAttachment', 'requestedBy', 'reviewedBy'])
            ->active()
            ->where('patient_id', $patientId);

        if ($viewer) {
            $query->visibleTo($viewer);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['reviewed']) && $filters['reviewed'] !== '') {
            $query->where('reviewed', filter_var($filters['reviewed'], FILTER_VALIDATE_BOOLEAN));
        }

        $dateFrom = DateFilter::normalize($filters['date_from'] ?? null);
        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = DateFilter::normalize($filters['date_to'] ?? null);
        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $analyses = $query->orderBy('created_at', 'desc')
            ->paginate(PerPage::resolve($filters['per_page'] ?? null));

        // The cost and the tokens describe the patient's active population,
        // not the page being returned; the date and status filters already
        // applied to the query are the same ones the rows obey.
        $metrics = $query->clone()->reorder();
        $totalTokens = (int) $metrics->sum('tokens_used');
        $estimatedCost = (float) $metrics->sum('estimated_cost');

        return [
            'data' => AiImageAnalysisResource::collection($analyses->items())->resolve(),
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total(),
                'total_tokens' => $totalTokens,
                'estimated_cost' => $estimatedCost,
            ],
        ];
    }

    /**
     * Obtener análisis pendientes de revisión.
     */
    public function getPendingAnalyses(array $filters = [], ?User $viewer = null): array
    {
        $query = AiImageAnalysis::with(['clinicalAttachment', 'patient', 'requestedBy'])
            ->active()
            ->where('status', 'completed')
            ->where('reviewed', false);

        if ($viewer) {
            $query->visibleTo($viewer);
        }

        if (isset($filters['requested_by']) && is_numeric($filters['requested_by'])) {
            $query->where('requested_by', (int) $filters['requested_by']);
        }

        if (isset($filters['patient_id']) && is_numeric($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        $analyses = $query->orderBy('created_at', 'desc')
            ->paginate(PerPage::resolve($filters['per_page'] ?? null));

        return [
            'data' => AiImageAnalysisResource::collection($analyses->items())->resolve(),
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total(),
            ],
        ];
    }

    /**
     * Obtener estadísticas de uso de IA.
     */
    public function getUsageStats(array $filters = [], ?User $viewer = null): array
    {
        // A failing metrics query must surface as a 500, never as a zeroed
        // dashboard: "0 analyses" and "the query broke" are different facts and
        // the surface already distinguishes empty from error.
        $query = AiImageAnalysis::query();

        if ($viewer) {
            $query->visibleTo($viewer);
        }

        if ($dateFrom = DateFilter::normalize($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = DateFilter::normalize($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Annulled rows keep their row but leave the working set; their count
        // is reported apart so the trace stays visible without inflating the
        // usage metrics.
        $voidedAnalyses = $query->clone()->voided()->count();
        $active = $query->clone()->active();

        $totalAnalyses = $active->count();
        $completedAnalyses = $active->clone()->where('status', 'completed')->count();
        $reviewedAnalyses = $active->clone()->where('reviewed', true)->count();
        $pendingReview = $active->clone()->where('status', 'completed')->where('reviewed', false)->count();
        $totalTokens = (int) $active->clone()->sum('tokens_used');
        $estimatedCost = (float) $active->clone()->sum('estimated_cost');

        return [
            'total_analyses' => $totalAnalyses,
            'completed_analyses' => $completedAnalyses,
            'reviewed_analyses' => $reviewedAnalyses,
            'pending_review' => $pendingReview,
            'voided_analyses' => $voidedAnalyses,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'completion_rate' => $totalAnalyses > 0 ? round(($completedAnalyses / $totalAnalyses) * 100, 2) : 0,
            'review_rate' => $completedAnalyses > 0 ? round(($reviewedAnalyses / $completedAnalyses) * 100, 2) : 0,
        ];
    }
}
