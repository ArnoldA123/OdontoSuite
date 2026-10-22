<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 (#282) — the urgency act and its closure.
 *
 * An act may exist with no appointment (S5/S8): opening it invents no agenda
 * row, and closing it produces the same documentation, amount and quotation as
 * the appointment door, with `appointment_id` null and the encounter as the
 * owner of the money.
 */
class UrgentEncounterClosureTest extends TestCase
{
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function urgentEncounter(User $professional, ?Patient $patient = null): ClinicalEncounter
    {
        return ClinicalEncounter::factory()->create([
            'patient_id' => ($patient ?? Patient::factory()->create())->id,
            'user_id' => $professional->id,
            'appointment_id' => null,
            'mode' => ClinicalEncounter::MODE_URGENCY,
        ]);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'execution',
            'evolution' => [
                'subjective' => 'Traumatismo en incisivo central superior.',
                'objective' => 'Fractura coronaria sin exposición pulpar.',
                'assessment' => 'Fractura no complicada.',
                'plan' => 'Reconstrucción inmediata.',
            ],
            'treatment_plan' => [
                'title' => 'Urgencia 11',
                'items' => [
                    ['procedure_name' => 'Reconstrucción 11', 'unit_cost' => 250, 'quantity' => 1],
                ],
            ],
        ], $overrides);
    }

    public function test_the_urgency_act_is_closed_with_the_same_closure(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $encounter = $this->urgentEncounter($professional, $patient);

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->payload());

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.appointment_id', null)
            ->assertJsonPath('meta.final_amount', 250)
            ->assertJsonPath('meta.quotation_generated', true);

        $encounter->refresh();

        $this->assertTrue($encounter->isClosed());
        $this->assertTrue($encounter->isUnscheduled());
        $this->assertSame('250.00', $encounter->final_amount);
        $this->assertSame($professional->id, $encounter->closed_by);

        $evolution = ClinicalEvolution::where('clinical_encounter_id', $encounter->id)->sole();
        $this->assertNull($evolution->appointment_id);
        $this->assertSame($encounter->medical_record_id, $evolution->medical_record_id);

        $quotation = Quotation::sole();
        $this->assertSame($encounter->id, $quotation->clinical_encounter_id);
        $this->assertNull($quotation->appointment_id);
        $this->assertSame($patient->id, $quotation->patient_id);

        // S8: no agenda row was invented to hold the act.
        $this->assertSame(0, Appointment::where('patient_id', $patient->id)->count());
    }

    public function test_an_urgency_act_cannot_schedule_the_next_appointment(): void
    {
        $professional = $this->professional();
        $encounter = $this->urgentEncounter($professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->payload([
                'next_appointment' => ['scheduled_at' => now()->addWeek()->toDateTimeString()],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'next_appointment_requires_appointment');

        $this->assertTrue($encounter->fresh()->isOpen());
        $this->assertSame(0, Appointment::count());
    }

    public function test_a_voided_act_cannot_be_closed(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();
        $encounter = $this->urgentEncounter($professional, $patient);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/void", ['reason' => 'Paciente equivocado.'])
            ->assertOk();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'encounter_not_open');

        $this->assertSame(0, ClinicalEvolution::count());
    }

    public function test_the_closure_requires_a_documentation_mode(): void
    {
        $professional = $this->professional();
        $encounter = $this->urgentEncounter($professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->payload(['mode' => 'urgency']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');
    }

    public function test_reception_cannot_close_a_clinical_act(): void
    {
        $professional = $this->professional();
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $encounter = $this->urgentEncounter($professional);

        $this->actingAs($receptionist, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->payload())
            ->assertForbidden();

        $this->assertTrue($encounter->fresh()->isOpen());
    }
}
