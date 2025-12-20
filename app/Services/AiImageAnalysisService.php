<?php

namespace App\Services;

use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AiImageAnalysisService
{
    protected $openaiConfig;

    public function __construct()
    {
        $this->openaiConfig = config('services.openai');
    }

    /**
     * Analizar imagen con IA
     */
    public function analyzeImage(int $attachmentId): AiImageAnalysis
    {
        $attachment = ClinicalAttachment::with(['patient'])->findOrFail($attachmentId);

        // Validar que sea una imagen de radiografía
        if ($attachment->category !== 'radiografia' && $attachment->file_type !== 'image') {
            throw ValidationException::withMessages([
                'attachment' => ['Solo se pueden analizar imágenes de radiografías con IA.']
            ]);
        }

        // Verificar si ya existe un análisis
        $existingAnalysis = AiImageAnalysis::where('clinical_attachment_id', $attachmentId)
            ->where('status', '!=', 'failed')
            ->first();

        if ($existingAnalysis) {
            return $existingAnalysis;
        }

        DB::beginTransaction();
        try {
            // Crear registro de análisis
            $analysis = AiImageAnalysis::create([
                'clinical_attachment_id' => $attachmentId,
                'patient_id' => $attachment->patient_id,
                'requested_by' => Auth::id(),
                'status' => 'pending',
                'model_used' => $this->openaiConfig['model']
            ]);

            // Procesar análisis de forma asíncrona
            $this->processAnalysis($analysis);

            DB::commit();
            return $analysis->load(['clinicalAttachment', 'patient', 'requestedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating AI analysis', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Optimizar imagen antes de enviar a OpenAI (sin GD)
     */
    protected function optimizeImage(string $imagePath): string
    {
        // Verificar que el archivo existe
        if (!file_exists($imagePath)) {
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
            'file_size' => $fileSize
        ]);

        // Si la imagen es muy grande (> 2MB), intentar reducirla
        if ($fileSize > 2 * 1024 * 1024) {
            Log::warning('Imagen muy grande, se enviará tal como está', [
                'file_size' => $fileSize,
                'max_recommended' => '2MB'
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
            'original_size' => $fileSize
        ]);

        return $base64Data;
    }

    /**
     * Procesar análisis con OpenAI
     */
    protected function processAnalysis(AiImageAnalysis $analysis): void
    {
        try {
            $analysis->update(['status' => 'processing']);

            $attachment = $analysis->clinicalAttachment;
            $imagePath = Storage::disk('public')->path($attachment->file_path);

            // Verificar que el archivo existe
            if (!file_exists($imagePath)) {
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
                    'max_size' => $maxSize
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
                'error' => $e->getMessage()
            ]);

            $analysis->update([
                'status' => 'failed',
                'raw_response' => $e->getMessage()
            ]);
        }
    }

    /**
     * Construir prompt especializado para análisis odontológico
     */
    protected function buildPrompt(): string
    {
        return "Eres un sistema de análisis de imágenes dentales para uso educativo y de investigación en odontología. Tu función es analizar radiografías dentales para fines académicos y de aprendizaje.

CONTEXTO: Esta es una imagen de radiografía dental que necesito analizar para un proyecto educativo en odontología. No es para diagnóstico médico real, sino para fines de aprendizaje y estudio.

Por favor, analiza esta imagen dental y proporciona información educativa en formato JSON:

{
  \"findings\": [
    {
      \"diagnosis\": \"Hallazgo observado en la imagen\",
      \"location\": \"Ubicación en la boca\",
      \"confidence\": 85,
      \"description\": \"Descripción del hallazgo\",
      \"severity\": \"leve|moderado|severo\"
    }
  ],
  \"recommendations\": [
    \"Observación educativa\",
    \"Punto de estudio\"
  ],
  \"overall_assessment\": \"Evaluación general de lo observado\",
  \"confidence_score\": 85
}

Observa y describe:
- Estructuras dentales visibles
- Anatomía normal
- Características de la imagen
- Elementos técnicos de la radiografía

Responde únicamente en formato JSON válido.";
    }

    /**
     * Llamar a OpenAI API
     */
    protected function callOpenAI(string $imageData, string $mimeType, string $prompt): array
    {
        $response = Http::timeout((int) $this->openaiConfig['timeout'])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiConfig['api_key'],
                'Content-Type' => 'application/json'
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->openaiConfig['model'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => (int) $this->openaiConfig['max_tokens'],
                'temperature' => 0.3
            ]);

        if (!$response->successful()) {
            throw new \Exception('Error en la API de OpenAI: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Procesar respuesta de OpenAI
     */
    protected function processResponse(AiImageAnalysis $analysis, array $response): void
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        // Intentar parsear JSON
        $parsedData = $this->parseResponse($content);

        $analysis->update([
            'status' => 'completed',
            'prompt_sent' => $this->buildPrompt(),
            'findings' => $parsedData['findings'] ?? [],
            'recommendations' => $parsedData['recommendations'] ?? [],
            'confidence_score' => $parsedData['confidence_score'] ?? null,
            'raw_response' => $content,
            'tokens_used' => $usage['total_tokens'] ?? null
        ]);
    }

    /**
     * Parsear respuesta JSON de la IA
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
                        'severity' => 'leve'
                    ]
                ],
                'recommendations' => [
                    'Verificar que la imagen sea una radiografía dental clara',
                    'Intentar con una imagen de menor tamaño o mejor calidad',
                    'Revisar manualmente la imagen'
                ],
                'confidence_score' => 0
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
                    'severity' => 'leve'
                ]
            ],
            'recommendations' => ['Revisar manualmente la imagen'],
            'confidence_score' => 0
        ];
    }

    /**
     * Revisar análisis (aceptar/rechazar)
     */
    public function reviewAnalysis(int $analysisId, string $decision, ?string $notes = null): AiImageAnalysis
    {
        $analysis = AiImageAnalysis::findOrFail($analysisId);

        if ($analysis->reviewed) {
            throw ValidationException::withMessages([
                'analysis' => ['Este análisis ya ha sido revisado.']
            ]);
        }

        $analysis->markAsReviewed(Auth::user(), $decision, $notes);

        return $analysis->load(['reviewedBy']);
    }

    /**
     * Obtener análisis por adjunto
     */
    public function getAnalysisByAttachment(int $attachmentId): ?AiImageAnalysis
    {
        return AiImageAnalysis::with(['clinicalAttachment', 'patient', 'requestedBy', 'reviewedBy'])
            ->where('clinical_attachment_id', $attachmentId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Obtener análisis por paciente
     */
    public function getAnalysisByPatient(int $patientId, array $filters = []): array
    {
        $query = AiImageAnalysis::with(['clinicalAttachment', 'requestedBy', 'reviewedBy'])
            ->where('patient_id', $patientId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['reviewed'])) {
            $query->where('reviewed', $filters['reviewed']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $analyses = $query->orderBy('created_at', 'desc')->paginate(15);

        return [
            'data' => $analyses->items(),
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total(),
            ]
        ];
    }

    /**
     * Obtener análisis pendientes de revisión
     */
    public function getPendingAnalyses(array $filters = []): array
    {
        $query = AiImageAnalysis::with(['clinicalAttachment', 'patient', 'requestedBy'])
            ->where('status', 'completed')
            ->where('reviewed', false);

        if (isset($filters['requested_by'])) {
            $query->where('requested_by', $filters['requested_by']);
        }

        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        $analyses = $query->orderBy('created_at', 'desc')->paginate(15);

        return [
            'data' => $analyses->items(),
            'meta' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total(),
            ]
        ];
    }

    /**
     * Obtener estadísticas de uso de IA
     */
    public function getUsageStats(array $filters = []): array
    {
        try {
            $query = AiImageAnalysis::query();

            if (isset($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $totalAnalyses = $query->count();
            $completedAnalyses = $query->clone()->where('status', 'completed')->count();
            $reviewedAnalyses = $query->clone()->where('reviewed', true)->count();
            $pendingReview = $query->clone()->where('status', 'completed')->where('reviewed', false)->count();

            return [
                'total_analyses' => $totalAnalyses,
                'completed_analyses' => $completedAnalyses,
                'reviewed_analyses' => $reviewedAnalyses,
                'pending_review' => $pendingReview,
                'completion_rate' => $totalAnalyses > 0 ? round(($completedAnalyses / $totalAnalyses) * 100, 2) : 0,
                'review_rate' => $completedAnalyses > 0 ? round(($reviewedAnalyses / $completedAnalyses) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            Log::error('Error in getUsageStats: ' . $e->getMessage());
            return [
                'total_analyses' => 0,
                'completed_analyses' => 0,
                'reviewed_analyses' => 0,
                'pending_review' => 0,
                'completion_rate' => 0,
                'review_rate' => 0
            ];
        }
    }
}
