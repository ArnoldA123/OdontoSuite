<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Issue #395 — the agenda narrows by chair, like it already narrows by
 * professional and branch.
 *
 * The calendar consumed `user_id` and `branch_id` but `dental_chair_id` was
 * accepted by the client and silently ignored by the API, so a receptionist
 * filtering by chair kept seeing every appointment of the range.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class AppointmentCalendarFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'administrador'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function appointmentFor(User $dentist, DentalChair $chair, Carbon $startsAt): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $startsAt,
            'duration_minutes' => 60,
        ]);
    }

    private function rangeArgs(Carbon $day): array
    {
        return [
            'start_date' => $day->copy()->startOfDay()->toDateTimeString(),
            'end_date' => $day->copy()->endOfDay()->toDateTimeString(),
        ];
    }

    public function test_the_range_read_narrows_by_chair(): void
    {
        $actor = $this->actor();
        $dentist = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $day = now()->addDays(2)->startOfDay();

        $chairA = DentalChair::factory()->create();
        $chairB = DentalChair::factory()->create();

        $onChairA = $this->appointmentFor($dentist, $chairA, $day->copy()->setTime(9, 0));
        $this->appointmentFor($dentist, $chairB, $day->copy()->setTime(11, 0));

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/appointments?'.http_build_query([
            ...$this->rangeArgs($day),
            'dental_chair_id' => $chairA->id,
        ]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$onChairA->id], $ids);
        $this->assertSame($chairA->id, (int) $response->json('data.0.dental_chair_id'));
    }

    public function test_the_chair_filter_composes_with_the_professional_filter(): void
    {
        $actor = $this->actor();
        $dentist = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $otherDentist = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $day = now()->addDays(2)->startOfDay();

        $chair = DentalChair::factory()->create();
        $otherChair = DentalChair::factory()->create();

        $wanted = $this->appointmentFor($dentist, $chair, $day->copy()->setTime(9, 0));
        $this->appointmentFor($otherDentist, $chair, $day->copy()->setTime(10, 0));
        $this->appointmentFor($dentist, $otherChair, $day->copy()->setTime(11, 0));

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/appointments?'.http_build_query([
            ...$this->rangeArgs($day),
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
        ]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$wanted->id], $ids);
    }

    public function test_an_empty_chair_filter_is_ignored_instead_of_matching_nothing(): void
    {
        $actor = $this->actor();
        $dentist = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $day = now()->addDays(2)->startOfDay();

        $first = $this->appointmentFor($dentist, DentalChair::factory()->create(), $day->copy()->setTime(9, 0));
        $second = $this->appointmentFor($dentist, DentalChair::factory()->create(), $day->copy()->setTime(11, 0));

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/appointments?'.http_build_query([
            ...$this->rangeArgs($day),
            'dental_chair_id' => '',
        ]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $expected = collect([$first->id, $second->id])->sort()->values()->all();
        $this->assertSame($expected, $ids);
    }
}
