<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalChair;
use App\Models\AppointmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Patient $patient;
    private DentalChair $dentalChair;
    private AppointmentType $appointmentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true
        ]);

        $this->patient = Patient::factory()->create([
            'is_active' => true
        ]);

        $this->dentalChair = DentalChair::factory()->create([
            'is_active' => true,
            'status' => 'active'
        ]);

        $this->appointmentType = AppointmentType::factory()->create([
            'is_active' => true,
            'default_duration_minutes' => 60
        ]);
    }

    /** @test */
    public function it_can_create_an_appointment()
    {
        $appointment = Appointment::create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => Carbon::now()->addHours(1),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'notes' => 'Test appointment'
        ]);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($this->user->id, $appointment->user_id);
        $this->assertEquals($this->patient->id, $appointment->patient_id);
        $this->assertEquals($this->dentalChair->id, $appointment->dental_chair_id);
        $this->assertEquals($this->appointmentType->id, $appointment->appointment_type_id);
        $this->assertEquals('scheduled', $appointment->status);
        $this->assertEquals('Test appointment', $appointment->notes);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->user->id
        ]);

        $this->assertInstanceOf(User::class, $appointment->user);
        $this->assertEquals($this->user->id, $appointment->user->id);
    }

    /** @test */
    public function it_belongs_to_a_patient()
    {
        $appointment = Appointment::factory()->create([
            'patient_id' => $this->patient->id
        ]);

        $this->assertInstanceOf(Patient::class, $appointment->patient);
        $this->assertEquals($this->patient->id, $appointment->patient->id);
    }

    /** @test */
    public function it_belongs_to_a_dental_chair()
    {
        $appointment = Appointment::factory()->create([
            'dental_chair_id' => $this->dentalChair->id
        ]);

        $this->assertInstanceOf(DentalChair::class, $appointment->dentalChair);
        $this->assertEquals($this->dentalChair->id, $appointment->dentalChair->id);
    }

    /** @test */
    public function it_belongs_to_an_appointment_type()
    {
        $appointment = Appointment::factory()->create([
            'appointment_type_id' => $this->appointmentType->id
        ]);

        $this->assertInstanceOf(AppointmentType::class, $appointment->appointmentType);
        $this->assertEquals($this->appointmentType->id, $appointment->appointmentType->id);
    }

    /** @test */
    public function it_can_scope_scheduled_appointments()
    {
        // Create appointments with different statuses
        $scheduledAppointment = Appointment::factory()->create([
            'status' => 'scheduled'
        ]);

        $completedAppointment = Appointment::factory()->create([
            'status' => 'completed'
        ]);

        $cancelledAppointment = Appointment::factory()->create([
            'status' => 'cancelled'
        ]);

        $scheduledAppointments = Appointment::scheduled()->get();

        $this->assertCount(1, $scheduledAppointments);
        $this->assertTrue($scheduledAppointments->contains('id', $scheduledAppointment->id));
        $this->assertFalse($scheduledAppointments->contains('id', $completedAppointment->id));
        $this->assertFalse($scheduledAppointments->contains('id', $cancelledAppointment->id));
    }

    /** @test */
    public function it_can_scope_completed_appointments()
    {
        // Create appointments with different statuses
        $scheduledAppointment = Appointment::factory()->create([
            'status' => 'scheduled'
        ]);

        $completedAppointment = Appointment::factory()->create([
            'status' => 'completed'
        ]);

        $cancelledAppointment = Appointment::factory()->create([
            'status' => 'cancelled'
        ]);

        $completedAppointments = Appointment::completed()->get();

        $this->assertCount(1, $completedAppointments);
        $this->assertTrue($completedAppointments->contains('id', $completedAppointment->id));
        $this->assertFalse($completedAppointments->contains('id', $scheduledAppointment->id));
        $this->assertFalse($completedAppointments->contains('id', $cancelledAppointment->id));
    }

    /** @test */
    public function it_can_scope_cancelled_appointments()
    {
        // Create appointments with different statuses
        $scheduledAppointment = Appointment::factory()->create([
            'status' => 'scheduled'
        ]);

        $completedAppointment = Appointment::factory()->create([
            'status' => 'completed'
        ]);

        $cancelledAppointment = Appointment::factory()->create([
            'status' => 'cancelled'
        ]);

        $cancelledAppointments = Appointment::cancelled()->get();

        $this->assertCount(1, $cancelledAppointments);
        $this->assertTrue($cancelledAppointments->contains('id', $cancelledAppointment->id));
        $this->assertFalse($cancelledAppointments->contains('id', $scheduledAppointment->id));
        $this->assertFalse($cancelledAppointments->contains('id', $completedAppointment->id));
    }

    /** @test */
    public function it_can_scope_appointments_for_date()
    {
        $date = Carbon::today();

        // Create appointments for today
        $todayAppointment = Appointment::factory()->create([
            'scheduled_at' => $date->copy()->setTime(9, 0)
        ]);

        // Create appointment for tomorrow
        $tomorrowAppointment = Appointment::factory()->create([
            'scheduled_at' => $date->copy()->addDay()->setTime(9, 0)
        ]);

        $todayAppointments = Appointment::forDate($date)->get();

        $this->assertCount(1, $todayAppointments);
        $this->assertTrue($todayAppointments->contains('id', $todayAppointment->id));
        $this->assertFalse($todayAppointments->contains('id', $tomorrowAppointment->id));
    }

    /** @test */
    public function it_can_scope_appointments_for_date_range()
    {
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(3);

        // Create appointments within range
        $inRangeAppointment = Appointment::factory()->create([
            'scheduled_at' => $startDate->copy()->addDays(1)->setTime(9, 0)
        ]);

        // Create appointment outside range
        $outOfRangeAppointment = Appointment::factory()->create([
            'scheduled_at' => $startDate->copy()->addDays(5)->setTime(9, 0)
        ]);

        $rangeAppointments = Appointment::forDateRange($startDate, $endDate)->get();

        $this->assertCount(1, $rangeAppointments);
        $this->assertTrue($rangeAppointments->contains('id', $inRangeAppointment->id));
        $this->assertFalse($rangeAppointments->contains('id', $outOfRangeAppointment->id));
    }

    /** @test */
    public function it_can_scope_appointments_for_user()
    {
        $otherUser = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true
        ]);

        // Create appointment for current user
        $userAppointment = Appointment::factory()->create([
            'user_id' => $this->user->id
        ]);

        // Create appointment for other user
        $otherUserAppointment = Appointment::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $userAppointments = Appointment::forUser($this->user->id)->get();

        $this->assertCount(1, $userAppointments);
        $this->assertTrue($userAppointments->contains('id', $userAppointment->id));
        $this->assertFalse($userAppointments->contains('id', $otherUserAppointment->id));
    }

    /** @test */
    public function it_can_scope_appointments_for_patient()
    {
        $otherPatient = Patient::factory()->create([
            'is_active' => true
        ]);

        // Create appointment for current patient
        $patientAppointment = Appointment::factory()->create([
            'patient_id' => $this->patient->id
        ]);

        // Create appointment for other patient
        $otherPatientAppointment = Appointment::factory()->create([
            'patient_id' => $otherPatient->id
        ]);

        $patientAppointments = Appointment::forPatient($this->patient->id)->get();

        $this->assertCount(1, $patientAppointments);
        $this->assertTrue($patientAppointments->contains('id', $patientAppointment->id));
        $this->assertFalse($patientAppointments->contains('id', $otherPatientAppointment->id));
    }

    /** @test */
    public function it_can_calculate_end_time()
    {
        $scheduledAt = Carbon::now()->setTime(9, 0);
        $duration = 90; // 90 minutes

        $appointment = Appointment::factory()->create([
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $duration
        ]);

        $expectedEndTime = $scheduledAt->copy()->addMinutes($duration);
        $actualEndTime = $appointment->end_time;

        $this->assertEquals($expectedEndTime, $actualEndTime);
    }

    /** @test */
    public function it_can_get_formatted_duration()
    {
        $appointment = Appointment::factory()->create([
            'duration_minutes' => 90
        ]);

        $this->assertEquals('1h 30m', $appointment->formatted_duration);
    }

    /** @test */
    public function it_can_get_formatted_scheduled_time()
    {
        $scheduledAt = Carbon::now()->setTime(14, 30);
        $appointment = Appointment::factory()->create([
            'scheduled_at' => $scheduledAt
        ]);

        $expected = $scheduledAt->format('d/m/Y H:i');
        $this->assertEquals($expected, $appointment->formatted_scheduled_time);
    }
}
