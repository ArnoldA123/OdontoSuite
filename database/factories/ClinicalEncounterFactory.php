<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalEncounter>
 */
class ClinicalEncounterFactory extends Factory
{
    /**
     * `opened_at` is the only column without a default, and it is not a value
     * of its own: an act opens when the professional starts it. The factory
     * defaults it to now and lets each test override it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'user_id' => User::factory(),
            'mode' => ClinicalEncounter::MODE_EXECUTION,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'opened_at' => now(),
        ];
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn () => [
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'user_id' => $appointment->user_id,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
