<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    /**
     * Monotonic within the process, because `medical_records.record_number` is
     * unique. Production builds it as `HC-<year>-<random>`
     * (MedicalRecordService::generateRecordNumber); the fixture keeps the shape
     * and replaces the random suffix with a counter so a failure is
     * reproducible.
     */
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `medical_records` declares four columns NOT NULL without a default --
        // patient_id, created_by, record_number and first_visit_date -- and no
        // factory existed at all, so every test that called
        // MedicalRecord::factory() died with "Class
        // Database\Factories\MedicalRecordFactory not found".
        return [
            'patient_id' => Patient::factory(),
            'created_by' => User::factory(),
            'record_number' => 'HC-'.now()->format('Y').'-'
                .str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'first_visit_date' => $this->faker->dateTimeBetween('-2 years')->format('Y-m-d'),
            'chief_complaint' => $this->faker->sentence(8),
            'is_active' => true,
        ];
    }
}
