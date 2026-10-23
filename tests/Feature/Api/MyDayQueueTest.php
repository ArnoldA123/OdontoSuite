<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\LabCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 (#281) — the professional's desk reads its own queue.
 *
 * `GET /api/my-day` returns the three buckets the screen renders: waiting
 * (arrived), attending (in_consultation) and upcoming (today's scheduled or
 * confirmed). Another professional's day never leaks into it.
 *
 * F5 (#285, S6) adds the prosthetic cases that came back from the workshop: a
 * delivered case of the authenticated professional is offered as ready, and a
 * case that is not delivered is not.
 */
class MyDayQueueTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function appointmentFor(User $professional, string $status, string $time = '09:00'): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'status' => $status,
            'checked_in_at' => $status === 'arrived' || $status === 'in_consultation' ? now() : null,
            'scheduled_at' => now()->startOfDay()->setTimeFromTimeString($time),
        ]);
    }

    public function test_it_returns_the_three_buckets_of_the_authenticated_professional(): void
    {
        $professional = $this->staff('odontologo');

        $waiting = $this->appointmentFor($professional, 'arrived', '09:00');
        $attending = $this->appointmentFor($professional, 'in_consultation', '08:00');
        $upcoming = $this->appointmentFor($professional, 'confirmed', '11:00');
        $this->appointmentFor($professional, 'cancelled', '12:00');

        $response = $this->actingAs($professional, 'sanctum')->getJson('/api/my-day');

        $response->assertOk()
            ->assertJsonPath('meta.counts.waiting', 1)
            ->assertJsonPath('meta.counts.attending', 1)
            ->assertJsonPath('meta.counts.upcoming', 1)
            ->assertJsonPath('data.waiting.0.id', $waiting->id)
            ->assertJsonPath('data.attending.0.id', $attending->id)
            ->assertJsonPath('data.upcoming.0.id', $upcoming->id);
    }

    public function test_another_professionals_day_is_not_included(): void
    {
        $professional = $this->staff('odontologo');
        $other = $this->staff('implantologo');

        $this->appointmentFor($professional, 'arrived', '09:00');
        $foreign = $this->appointmentFor($other, 'arrived', '10:00');

        $response = $this->actingAs($professional, 'sanctum')->getJson('/api/my-day');

        $response->assertOk()->assertJsonCount(1, 'data.waiting');
        $this->assertNotContains(
            $foreign->id,
            array_column($response->json('data.waiting'), 'id'),
        );
    }

    public function test_an_appointment_outside_today_is_not_offered_as_upcoming(): void
    {
        $professional = $this->staff('odontologo');

        Appointment::factory()->create([
            'user_id' => $professional->id,
            'status' => 'confirmed',
            'scheduled_at' => now()->addDays(2)->startOfDay()->addHours(9),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/my-day')
            ->assertOk()
            ->assertJsonCount(0, 'data.upcoming');
    }

    public function test_a_delivered_case_of_the_professional_is_offered_as_ready(): void
    {
        $professional = $this->staff('odontologo');

        $case = LabCase::factory()->create([
            'professional_id' => $professional->id,
            'status' => LabCase::PHASE_DELIVERED,
            'delivered_at' => now()->subHours(2),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/my-day')
            ->assertOk()
            ->assertJsonPath('meta.counts.ready_lab_cases', 1)
            ->assertJsonPath('data.ready_lab_cases.0.id', $case->id)
            ->assertJsonPath('data.ready_lab_cases.0.patient.id', $case->patient_id);
    }

    public function test_another_professionals_ready_case_is_not_in_the_desk(): void
    {
        $professional = $this->staff('odontologo');
        $other = $this->staff('implantologo');

        LabCase::factory()->create([
            'professional_id' => $other->id,
            'status' => LabCase::PHASE_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/my-day')
            ->assertOk()
            ->assertJsonCount(0, 'data.ready_lab_cases')
            ->assertJsonPath('meta.counts.ready_lab_cases', 0);
    }

    public function test_a_case_in_another_phase_is_not_offered_as_ready(): void
    {
        $professional = $this->staff('odontologo');

        LabCase::factory()->create([
            'professional_id' => $professional->id,
            'status' => LabCase::PHASE_TRY_IN,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/my-day')
            ->assertOk()
            ->assertJsonCount(0, 'data.ready_lab_cases');
    }

    public function test_reception_does_not_have_a_professional_desk(): void
    {
        $reception = $this->staff('recepcionista');

        $this->actingAs($reception, 'sanctum')
            ->getJson('/api/my-day')
            ->assertForbidden();
    }
}
