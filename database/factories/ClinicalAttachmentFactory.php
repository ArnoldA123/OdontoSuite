<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalAttachment>
 */
class ClinicalAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `clinical_attachments` declares nine columns NOT NULL without a
        // default -- patient_id, created_by, file_name, original_name,
        // file_path, file_type, mime_type, file_size and category -- and no
        // factory existed, so the attachment tests died on the factory lookup
        // before they could reach the endpoint they were written for.
        // This was the last missing factory of the suite: a scan for every
        // `<Model>::factory()` call in tests against `database/factories/`
        // returns nothing else.
        //
        // `file_type` and `mime_type` come in pairs because
        // MedicalRecordService::getFileType derives the first from the second;
        // a fixture that pairs them incoherently would describe a file that
        // cannot exist. `category` uses the values the upload endpoint accepts.
        return [
            'patient_id' => Patient::factory(),
            'created_by' => User::factory(),
            'file_name' => $this->faker->uuid().'.jpg',
            'original_name' => $this->faker->word().'.jpg',
            'file_path' => 'clinical-attachments/'.$this->faker->uuid().'.jpg',
            'file_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(10_000, 5_000_000),
            'category' => $this->faker->randomElement([
                'radiografia',
                'foto_clinica',
                'documento',
                'otro',
            ]),
            'description' => $this->faker->sentence(),
        ];
    }
}
