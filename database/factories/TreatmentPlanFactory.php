<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TreatmentPlan>
 */
class TreatmentPlanFactory extends Factory
{
    protected $model = \App\Models\TreatmentPlan::class;

    public function definition(): array
    {
        return [
            'patient_id' => \App\Models\Patient::factory(),
            'created_by' => \App\Models\User::factory(),
            'plan_number' => 'TP-' . $this->faker->unique()->bothify('####-####'),
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => 'draft',
            'total_cost' => 0,
            'discount_amount' => 0,
            'final_cost' => 0,
        ];
    }
}
