<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalChair;
use App\Models\AppointmentType;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Carbon\Carbon;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AppointmentService();
    }

    /**
     * Test that appointment service creates appointment successfully
     */
    public function test_creates_appointment_successfully(): void
    {
        $patient = Patient::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'role' => 'odontologo']);
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();

        $data = [
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => Carbon::now()->addDay()->toISOString(),
            'duration_minutes' => 60,
        ];

        $appointment = $this->service->createAppointment($data);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test that appointment service detects conflicts
     */
    public function test_detects_scheduling_conflicts(): void
    {
        $patient = Patient::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'role' => 'odontologo']);
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();

        $scheduledAt = Carbon::now()->addDay()->setTime(10, 0);

        // Create first appointment
        Appointment::factory()->create([
            'user_id' => $user->id,
            'dental_chair_id' => $chair->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(60),
            'duration_minutes' => 60,
        ]);

        // Try to create conflicting appointment
        $data = [
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $scheduledAt->toISOString(),
            'duration_minutes' => 60,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createAppointment($data);
    }
}
