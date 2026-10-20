<?php

namespace Database\Factories;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalEvolution>
 */
class ClinicalEvolutionFactory extends Factory
{
    protected $model = ClinicalEvolution::class;

    /**
     * `clinical_evolutions` declares five columns NOT NULL without a default
     * (patient_id, medical_record_id, created_by, evolution_date) and no
     * factory existed, so the evolution endpoints could only be exercised by
     * hand-building rows inside each test.
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'medical_record_id' => MedicalRecord::factory(),
            'created_by' => User::factory(),
            'evolution_date' => $this->faker->dateTimeBetween('-6 months')->format('Y-m-d'),
            'specialty' => 'odontologia_general',
            'subjective' => $this->faker->sentence(10),
            'objective' => $this->faker->sentence(10),
            'assessment' => $this->faker->sentence(6),
            'plan' => $this->faker->sentence(6),
            'requires_follow_up' => false,
        ];
    }

    /**
     * Attach the evolution to an existing record, keeping the patient aligned
     * with the record's patient so the aggregate endpoints never see a row
     * that belongs to two different patients.
     */
    public function forRecord(MedicalRecord $record): static
    {
        return $this->state(fn () => [
            'medical_record_id' => $record->id,
            'patient_id' => $record->patient_id,
        ]);
    }
}
