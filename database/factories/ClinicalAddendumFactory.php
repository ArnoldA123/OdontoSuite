<?php

namespace Database\Factories;

use App\Models\ClinicalAddendum;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalAddendum>
 */
class ClinicalAddendumFactory extends Factory
{
    protected $model = ClinicalAddendum::class;

    public function definition(): array
    {
        return [
            'medical_record_id' => MedicalRecord::factory(),
            'clinical_evolution_id' => null,
            'user_id' => User::factory(),
            'reason' => $this->faker->sentence(6),
            'content' => $this->faker->paragraph(),
        ];
    }

    /**
     * Attach the correction to one visit, keeping the record in sync with the
     * evolution so the addendum never points at two different histories.
     */
    public function forEvolution(ClinicalEvolution $evolution): static
    {
        return $this->state(fn () => [
            'medical_record_id' => $evolution->medical_record_id,
            'clinical_evolution_id' => $evolution->id,
        ]);
    }
}
