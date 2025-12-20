<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CalendarService;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\DentalChair;
use App\Models\AppointmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalendarService $calendarService;
    private User $user;
    private Patient $patient;
    private DentalChair $dentalChair;
    private AppointmentType $appointmentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendarService = new CalendarService();

        // Create test data
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
    public function it_can_get_day_appointments()
    {
        $date = Carbon::today();

        // Create appointments for today
        $appointment1 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $date->copy()->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointment2 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $date->copy()->setTime(14, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        // Create appointment for different day
        Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $date->copy()->addDay(),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointments = $this->calendarService->getDayAppointments($date);

        $this->assertCount(2, $appointments);
        $this->assertTrue($appointments->contains('id', $appointment1->id));
        $this->assertTrue($appointments->contains('id', $appointment2->id));
    }

    /** @test */
    public function it_can_get_week_appointments()
    {
        $startOfWeek = Carbon::now()->startOfWeek();

        // Create appointments for this week
        $appointment1 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startOfWeek->copy()->addDays(1)->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointment2 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startOfWeek->copy()->addDays(3)->setTime(14, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        // Create appointment for next week
        Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startOfWeek->copy()->addWeek(),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointments = $this->calendarService->getWeekAppointments($startOfWeek);

        $this->assertCount(2, $appointments);
        $this->assertTrue($appointments->contains('id', $appointment1->id));
        $this->assertTrue($appointments->contains('id', $appointment2->id));
    }

    /** @test */
    public function it_can_get_month_appointments()
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        // Create appointments for this month
        $appointment1 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startOfMonth->copy()->addDays(5)->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointment2 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startOfMonth->copy()->addDays(15)->setTime(14, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        // Create appointment for next month
        Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startOfMonth->copy()->addMonth(),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointments = $this->calendarService->getMonthAppointments($startOfMonth);

        $this->assertCount(2, $appointments);
        $this->assertTrue($appointments->contains('id', $appointment1->id));
        $this->assertTrue($appointments->contains('id', $appointment2->id));
    }

    /** @test */
    public function it_can_get_appointments_in_range()
    {
        $startDate = Carbon::now()->addDays(1);
        $endDate = Carbon::now()->addDays(5);

        // Create appointments within range
        $appointment1 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startDate->copy()->addDays(1)->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointment2 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startDate->copy()->addDays(3)->setTime(14, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        // Create appointment outside range
        Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startDate->copy()->addDays(10),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointments = $this->calendarService->getAppointmentsInRange($startDate, $endDate);

        $this->assertCount(2, $appointments);
        $this->assertTrue($appointments->contains('id', $appointment1->id));
        $this->assertTrue($appointments->contains('id', $appointment2->id));
    }

    /** @test */
    public function it_can_get_calendar_data_for_fullcalendar()
    {
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        $appointment = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startDate->copy()->addDays(1)->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'notes' => 'Test appointment'
        ]);

        $calendarData = $this->calendarService->getCalendarData($startDate, $endDate);

        $this->assertIsArray($calendarData);
        $this->assertCount(1, $calendarData);

        $event = $calendarData[0];
        $this->assertEquals($appointment->id, $event['id']);
        $this->assertEquals($appointment->scheduled_at->toISOString(), $event['start']);
        $this->assertEquals($appointment->scheduled_at->addMinutes(60)->toISOString(), $event['end']);
        $this->assertEquals($appointment->notes, $event['title']);
    }

    /** @test */
    public function it_can_get_calendar_statistics()
    {
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        // Create appointments with different statuses
        Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startDate->copy()->addDays(1)->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $startDate->copy()->addDays(2)->setTime(14, 0),
            'duration_minutes' => 60,
            'status' => 'completed'
        ]);

        $stats = $this->calendarService->getCalendarStats($startDate, $endDate);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_appointments', $stats);
        $this->assertArrayHasKey('scheduled_appointments', $stats);
        $this->assertArrayHasKey('completed_appointments', $stats);
        $this->assertArrayHasKey('cancelled_appointments', $stats);
        $this->assertEquals(2, $stats['total_appointments']);
        $this->assertEquals(1, $stats['scheduled_appointments']);
        $this->assertEquals(1, $stats['completed_appointments']);
    }

    /** @test */
    public function it_filters_appointments_by_user()
    {
        $otherUser = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true
        ]);

        $date = Carbon::today();

        // Create appointment for current user
        $appointment1 = Appointment::factory()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $date->copy()->setTime(9, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        // Create appointment for other user
        Appointment::factory()->create([
            'user_id' => $otherUser->id,
            'patient_id' => $this->patient->id,
            'dental_chair_id' => $this->dentalChair->id,
            'appointment_type_id' => $this->appointmentType->id,
            'scheduled_at' => $date->copy()->setTime(14, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled'
        ]);

        $appointments = $this->calendarService->getDayAppointments($date, $this->user->id);

        $this->assertCount(1, $appointments);
        $this->assertTrue($appointments->contains('id', $appointment1->id));
    }
}
