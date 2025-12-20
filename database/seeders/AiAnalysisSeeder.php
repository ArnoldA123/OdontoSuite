<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiAnalysisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener algunos pacientes y adjuntos existentes
        $patients = Patient::limit(5)->get();
        $attachments = ClinicalAttachment::where('category', 'radiografia')
            ->where('file_type', 'image')
            ->limit(10)
            ->get();

        $users = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental'])
            ->limit(3)
            ->get();

        if ($patients->isEmpty() || $attachments->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No hay suficientes datos para crear análisis de IA. Ejecuta primero los seeders de pacientes, adjuntos y usuarios.');
            return;
        }

        $analyses = [];

        // Crear análisis de ejemplo
        foreach ($attachments as $attachment) {
            $status = fake()->randomElement(['pending', 'processing', 'completed', 'failed']);
            $reviewed = $status === 'completed' ? fake()->boolean(70) : false;

            $analysis = [
                'clinical_attachment_id' => $attachment->id,
                'patient_id' => $attachment->patient_id,
                'requested_by' => $users->random()->id,
                'status' => $status,
                'prompt_sent' => $this->generatePrompt(),
                'findings' => $status === 'completed' ? $this->generateFindings() : null,
                'recommendations' => $status === 'completed' ? $this->generateRecommendations() : null,
                'confidence_score' => $status === 'completed' ? fake()->numberBetween(60, 95) : null,
                'raw_response' => $status === 'completed' ? $this->generateRawResponse() : null,
                'model_used' => 'gpt-4o',
                'tokens_used' => $status === 'completed' ? fake()->numberBetween(500, 1500) : null,
                'reviewed' => $reviewed,
                'reviewed_by' => $reviewed ? $users->random()->id : null,
                'reviewed_at' => $reviewed ? fake()->dateTimeBetween('-30 days', 'now') : null,
                'review_decision' => $reviewed ? fake()->randomElement(['accepted', 'rejected', 'partial']) : null,
                'review_notes' => $reviewed ? fake()->optional(0.7)->sentence() : null,
                'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
                'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
            ];

            $analyses[] = $analysis;
        }

        // Insertar análisis uno por uno para manejar JSON correctamente
        foreach ($analyses as $analysis) {
            AiImageAnalysis::create($analysis);
        }

        $this->command->info('Análisis de IA creados exitosamente: ' . count($analyses));
    }

    /**
     * Generar prompt de ejemplo
     */
    private function generatePrompt(): string
    {
        return "Eres un asistente de diagnóstico odontológico especializado. Analiza esta radiografía dental y proporciona un análisis detallado en formato JSON con la siguiente estructura:

{
  \"findings\": [
    {
      \"diagnosis\": \"Nombre del hallazgo clínico\",
      \"location\": \"Localización específica (pieza dental, cuadrante)\",
      \"confidence\": 85,
      \"description\": \"Descripción detallada del hallazgo\",
      \"severity\": \"leve|moderado|severo\"
    }
  ],
  \"recommendations\": [
    \"Recomendación de tratamiento específica\",
    \"Seguimiento recomendado\"
  ],
  \"overall_assessment\": \"Evaluación general de la salud dental\",
  \"confidence_score\": 85
}

IMPORTANTE:
- Identifica caries, problemas de raíz, implantes, restauraciones, etc.
- Especifica la pieza dental exacta (ej: 1.6, 2.3, etc.)
- Asigna nivel de confianza de 0-100 para cada hallazgo
- Proporciona recomendaciones de tratamiento específicas
- Responde SOLO en formato JSON válido";
    }

    /**
     * Generar hallazgos de ejemplo
     */
    private function generateFindings(): array
    {
        $findings = [
            [
                'diagnosis' => 'Caries dental',
                'location' => 'Pieza 1.6 (Primer molar superior derecho)',
                'confidence' => fake()->numberBetween(75, 95),
                'description' => 'Lesión cariosa en superficie oclusal con extensión hacia dentina',
                'severity' => fake()->randomElement(['leve', 'moderado', 'severo'])
            ],
            [
                'diagnosis' => 'Restauración existente',
                'location' => 'Pieza 2.4 (Primer premolar superior izquierdo)',
                'confidence' => fake()->numberBetween(85, 98),
                'description' => 'Amalgama de plata en superficie oclusal con buen sellado marginal',
                'severity' => 'leve'
            ],
            [
                'diagnosis' => 'Pérdida ósea',
                'location' => 'Región posterior mandibular',
                'confidence' => fake()->numberBetween(70, 90),
                'description' => 'Reducción de altura ósea alveolar en zona de molares',
                'severity' => fake()->randomElement(['moderado', 'severo'])
            ]
        ];

        return fake()->randomElements($findings, fake()->numberBetween(1, 3));
    }

    /**
     * Generar recomendaciones de ejemplo
     */
    private function generateRecommendations(): array
    {
        $recommendations = [
            'Tratamiento restaurador con resina compuesta',
            'Endodoncia y corona en pieza afectada',
            'Evaluación periodontal completa',
            'Radiografía de seguimiento en 6 meses',
            'Consulta con especialista en periodoncia',
            'Aplicación de flúor tópico',
            'Instrucciones de higiene oral mejorada',
            'Control de placa bacteriana'
        ];

        return fake()->randomElements($recommendations, fake()->numberBetween(2, 5));
    }

    /**
     * Generar respuesta cruda de ejemplo
     */
    private function generateRawResponse(): string
    {
        return json_encode([
            'findings' => $this->generateFindings(),
            'recommendations' => $this->generateRecommendations(),
            'overall_assessment' => fake()->randomElement([
                'Estado dental general bueno con algunas áreas que requieren atención',
                'Múltiples hallazgos que requieren tratamiento integral',
                'Estado dental estable con mínimas intervenciones necesarias',
                'Presencia de patologías que requieren tratamiento inmediato'
            ]),
            'confidence_score' => fake()->numberBetween(70, 95)
        ], JSON_PRETTY_PRINT);
    }
}
