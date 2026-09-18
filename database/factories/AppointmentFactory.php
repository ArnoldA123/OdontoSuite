<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `appointments` declares eight columns NOT NULL without a default:
        // patient_id, user_id, dental_chair_id, appointment_type_id,
        // scheduled_at, ends_at, duration_minutes and created_by. A factory that
        // defines none of them does not fail once: it makes every test that
        // overrides a subset fail on the first column it did not pass, which is
        // why this single gap was 26 of the 57 failures of the MySQL runner
        // under four different column names.
        return [
            'patient_id' => Patient::factory(),
            'user_id' => User::factory(),
            'dental_chair_id' => DentalChair::factory(),
            'appointment_type_id' => AppointmentType::factory(),
            'scheduled_at' => Carbon::now()->addDay()->startOfHour(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Appointment $appointment) {
            // `ends_at` is not a value of its own: it is a function of the pair
            // `scheduled_at` + `duration_minutes` (AppointmentService computes it
            // the same way). Deriving it here, after every override is applied,
            // is what keeps a fixture internally consistent for the ranged
            // queries that consume it -- hardcoding it beside the default
            // `scheduled_at` would insert a valid but incoherent record.
            $appointment->ends_at ??= $appointment->scheduled_at?->copy()
                ->addMinutes($appointment->duration_minutes ?? 60);

            $appointment->created_by ??= $appointment->user_id;
        });
    }
}
