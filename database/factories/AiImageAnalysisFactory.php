<?php

namespace Database\Factories;

use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiImageAnalysis>
 */
class AiImageAnalysisFactory extends Factory
{
    protected $model = AiImageAnalysis::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The attachment is resolved in `configure()` so it always belongs
            // to the same patient and requester as the analysis: an analysis
            // whose attachment points at another patient describes a file the
            // system cannot produce.
            'clinical_attachment_id' => null,
            'patient_id' => Patient::factory(),
            'requested_by' => User::factory(),
            'status' => 'completed',
            'prompt_sent' => 'Describe the findings of this radiograph.',
            'findings' => [],
            'recommendations' => [],
            'confidence_score' => 72.5,
            'raw_response' => '{"findings":[],"confidence":0.72}',
            'model_used' => 'gpt-4o',
            'tokens_used' => 1200,
            'reviewed' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AiImageAnalysis $analysis): void {
            if ($analysis->clinical_attachment_id !== null) {
                return;
            }

            $attachment = ClinicalAttachment::factory()->create([
                'patient_id' => $analysis->patient_id,
                'created_by' => $analysis->requested_by,
            ]);

            $analysis->clinical_attachment_id = $attachment->id;
        });
    }
}
