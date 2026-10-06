<?php

namespace Tests\Feature\Modules;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * dashboard-visual-coherence / T1: GET /api/dashboard/upcoming.
 *
 * Contract:
 * - The upcoming week strip starts at the beginning of TOMORROW (00:00 local),
 *   never at `now`. "Agenda de hoy" is the only surface that renders today.
 * - The window still ends at `endOfWeek()`, so on the last day of the week the
 *   range is empty and the response is an empty array (the section empty state
 *   is the correct outcome).
 * - Ordering, the 10-item limit, the resource shape and the branch scoping are
 *   unchanged.
 *
 * Runs against the MySQL harness because RefreshDatabase rebuilds every table:
 *   DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_USERNAME=root DB_PASSWORD=<from .env> \
 *     php artisan test --configuration=phpunit.mysql.xml tests/Feature/Modules/DashboardUpcomingTest.php
 */
class DashboardUpcomingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow();
        DB::table('appointments')->delete();
        DB::table('patients')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithRole(string $role): User
    {
        return User::create([
            'name' => 'Upcoming ' . ucfirst($role),
            'email' => 'upcoming.' . $role . '.' . uniqid('', true) . '@example.com',
            'username' => 'upcoming_' . $role . '_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function patient(string $firstName = 'Upcoming'): Patient
    {
        return Patient::create([
            'first_name' => $firstName,
            'last_name' => 'Patient',
            'email' => 'upcoming.patient.' . uniqid('', true) . '@example.com',
            'phone' => '+51 9' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'code' => substr('UP' . uniqid(), 0, 10),
            'address' => 'Av. Upcoming 123',
            'city' => 'Lima',
            'is_active' => true,
        ]);
    }

    private function chair(): DentalChair
    {
        return DentalChair::create([
            'name' => 'Upcoming Chair',
            'code' => substr('UC' . uniqid(), 0, 10),
            'is_active' => true,
        ]);
    }

    private function appointmentType(): AppointmentType
    {
        return AppointmentType::create([
            'name' => 'Upcoming Control',
            'default_duration_minutes' => 30,
            'price' => 25000,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);
    }

    private function appointment(
        User $doctor,
        Branch $branch,
        DentalChair $chair,
        AppointmentType $type,
        Patient $patient,
        Carbon $scheduledAt,
    ): Appointment {
        return Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => $chair->id,
            'branch_id' => $branch->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $doctor->id,
        ]);
    }

    public function test_today_appointments_are_excluded_from_upcoming(): void
    {
        // Wednesday 2026-10-07, 10:00 America/Lima (app timezone).
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 10, 0, 0));

        $admin = $this->userWithRole('administrador');
        $branch = $this->branch('Upcoming Branch');
        $chair = $this->chair();
        $type = $this->appointmentType();
        $patient = $this->patient('Hoy');

        $laterToday = $this->appointment(
            $admin,
            $branch,
            $chair,
            $type,
            $patient,
            Carbon::create(2026, 10, 7, 18, 0, 0),
        );

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/upcoming');

        $response->assertOk();
        $this->assertNotContains(
            $laterToday->id,
            array_column($response->json('data'), 'id'),
            'A later-today appointment belongs to the agenda and must not appear in the upcoming strip.',
        );
        $this->assertSame(0, $response->json('meta.count'));
        $this->assertSame('this_week', $response->json('meta.period'));
    }

    public function test_tomorrow_appointments_are_returned_in_scheduled_order(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 10, 0, 0));

        $admin = $this->userWithRole('administrador');
        $branch = $this->branch('Upcoming Branch');
        $chair = $this->chair();
        $type = $this->appointmentType();
        $patient = $this->patient('Mañana');

        $afternoon = $this->appointment(
            $admin,
            $branch,
            $chair,
            $type,
            $patient,
            Carbon::create(2026, 10, 8, 15, 0, 0),
        );
        $morning = $this->appointment(
            $admin,
            $branch,
            $chair,
            $type,
            $patient,
            Carbon::create(2026, 10, 8, 9, 0, 0),
        );

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/upcoming');

        $response->assertOk();
        $this->assertSame(
            [$morning->id, $afternoon->id],
            array_column($response->json('data'), 'id'),
            'Tomorrow is inside the window and items stay ordered by scheduled_at.',
        );
        $this->assertSame(2, $response->json('meta.count'));
        $this->assertSame('Mañana Patient', $response->json('data.0.patient.full_name'));
        $this->assertSame('Upcoming Control', $response->json('data.0.appointment_type.name'));
    }

    public function test_appointments_today_still_returns_todays_appointments(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 10, 0, 0));

        $admin = $this->userWithRole('administrador');
        $branch = $this->branch('Upcoming Branch');
        $chair = $this->chair();
        $type = $this->appointmentType();
        $patient = $this->patient('Agenda');

        $today = $this->appointment(
            $admin,
            $branch,
            $chair,
            $type,
            $patient,
            Carbon::create(2026, 10, 7, 18, 0, 0),
        );

        $todayResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/appointments-today');
        $todayResponse->assertOk();
        $this->assertContains(
            $today->id,
            array_column($todayResponse->json('data'), 'id'),
            'The exclusion from the upcoming strip must not over-filter the agenda endpoint.',
        );

        // Same fixture, opposite windows: today only in the agenda.
        $upcomingResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/upcoming');
        $upcomingResponse->assertOk();
        $this->assertSame([], $upcomingResponse->json('data'));
    }

    public function test_last_day_of_week_yields_an_empty_upcoming_window(): void
    {
        // Sunday 2026-10-11: endOfWeek() is this same day, so start-of-tomorrow
        // (Monday) is already past the window and the strip is empty.
        Carbon::setTestNow(Carbon::create(2026, 10, 11, 10, 0, 0));

        $admin = $this->userWithRole('administrador');
        $branch = $this->branch('Upcoming Branch');
        $chair = $this->chair();
        $type = $this->appointmentType();
        $patient = $this->patient('Domingo');

        $this->appointment(
            $admin,
            $branch,
            $chair,
            $type,
            $patient,
            Carbon::create(2026, 10, 11, 18, 0, 0),
        );
        $this->appointment(
            $admin,
            $branch,
            $chair,
            $type,
            $patient,
            Carbon::create(2026, 10, 12, 9, 0, 0),
        );

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/upcoming');

        $response->assertOk();
        $this->assertSame(
            [],
            $response->json('data'),
            'On the last day of the week the tomorrow-anchored window is empty, not carried into next week.',
        );
        $this->assertSame(0, $response->json('meta.count'));
    }
}
