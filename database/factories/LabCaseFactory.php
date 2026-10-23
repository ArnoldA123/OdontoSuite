<?php

namespace Database\Factories;

use App\Models\LabCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabCase>
 */
class LabCaseFactory extends Factory
{
    protected $model = LabCase::class;

    public function definition(): array
    {
        $sentAt = now()->subDays(fake()->numberBetween(1, 10));

        return [
            'patient_id' => Patient::factory(),
            'branch_id' => null,
            'professional_id' => User::factory()->state([
                'role' => 'odontologo',
                'is_active' => true,
            ]),
            'technician_id' => User::factory()->state([
                'role' => 'tecnico_dental',
                'is_active' => true,
            ]),
            'dental_piece_id' => null,
            'work' => 'Corona metal-cerámica',
            'material' => 'Zirconia',
            'sent_at' => $sentAt->toDateString(),
            'promised_at' => $sentAt->copy()->addDays(10)->toDateString(),
            'status' => LabCase::PHASE_IN_PREPARATION,
            'created_by' => null,
        ];
    }
}
