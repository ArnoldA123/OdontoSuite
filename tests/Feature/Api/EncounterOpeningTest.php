<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\User;
use App\Services\ClinicalEncounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 (#282) — starting the attention opens the clinical act.
 *
 * The custody transfer and the opening of the encounter are one act, written in
 * the same transaction: `in_consultation` without an open encounter would mean
 * an appointment holding clinical documentation it does not own. Starting twice
 * is idempotent and MUST NOT open a second act.
 */
class EncounterOpeningTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function appointment(string $status, User $professional): Appointment
    {
        return Appointment::factory()->create([
            'status' => $status,
            'checked_in_at' => $status === 'arrived' ? now() : null,
            'checked_in_by' => $status === 'arrived'
                ? User::factory()->create(['role' => 'recepcionista', 'is_active' => true])->id
                : null,
            'scheduled_at' => now()->startOfDay()->addHours(9),
            'user_id' => $professional->id,
        ]);
    }

    public function test_starting_the_attention_opens_the_encounter_of_the_act(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment('arrived', $professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_consultation');

        $encounter = ClinicalEncounter::query()->sole();

        $this->assertSame(ClinicalEncounter::STATUS_OPEN, $encounter->status);
        $this->assertSame($appointment->patient_id, $encounter->patient_id);
        $this->assertSame($appointment->user_id, $encounter->user_id);
        $this->assertSame($appointment->id, $encounter->appointment_id);
        $this->assertNotNull($encounter->opened_at);
    }

    public function test_starting_twice_does_not_open_a_second_encounter(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment('arrived', $professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk();
        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk();

        $this->assertSame(1, ClinicalEncounter::query()->count());
        $this->assertSame(1, ClinicalEncounter::query()->open()->count());
    }

    public function test_an_appointment_that_has_not_arrived_opens_no_encounter(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment('scheduled', $professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertStatus(409)
            ->assertJsonPath('error.error_code', 'attention_not_allowed');

        $this->assertSame('scheduled', $appointment->fresh()->status);
        $this->assertSame(0, ClinicalEncounter::query()->count());
    }

    public function test_the_service_reuses_the_open_encounter_of_the_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment('arrived', $professional);
        $encounters = app(ClinicalEncounterService::class);

        $first = $encounters->openForAppointment($appointment, $professional);
        $second = $encounters->openForAppointment($appointment, $professional);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ClinicalEncounter::query()->count());
    }

    public function test_a_voided_act_leaves_room_for_the_next_one(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment('arrived', $professional);
        $encounters = app(ClinicalEncounterService::class);

        $first = $encounters->openForAppointment($appointment, $professional);
        $encounters->void($first, $professional, 'Apertura por error.');
        $second = $encounters->openForAppointment($appointment->fresh(), $professional);

        $this->assertFalse($first->is($second));
        $this->assertSame(2, ClinicalEncounter::query()->count());
        $this->assertSame(1, ClinicalEncounter::query()->open()->count());
    }
}
