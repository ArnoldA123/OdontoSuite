<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 (#282) — the urgency door: a clinical act with no agenda row.
 *
 * S5 and S8 are one assertion: the encounter exists, `appointment_id` is null
 * and no appointment was invented to hold it. Only clinical roles (and the
 * administrator) open acts; reception does not. Voiding is the way out of a
 * mistaken opening and only works while the act is still open.
 */
class UrgentEncounterTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_a_professional_opens_an_urgency_encounter_without_an_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/encounters', [
                'patient_id' => $patient->id,
                'notes' => 'Dolor agudo en pieza 46.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.appointment_id', null)
            ->assertJsonPath('data.user_id', $professional->id)
            ->assertJsonPath('data.mode', ClinicalEncounter::MODE_URGENCY)
            ->assertJsonPath('data.status', ClinicalEncounter::STATUS_OPEN)
            ->assertJsonPath('data.notes', 'Dolor agudo en pieza 46.')
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.user.id', $professional->id)
            ->assertJsonPath('meta.message', 'Atención de urgencia abierta.');

        $encounter = ClinicalEncounter::query()->sole();

        $this->assertNull($encounter->appointment_id);
        $this->assertNotNull($encounter->opened_at, 'The opening hour MUST be stored.');
        $this->assertSame(
            0,
            Appointment::query()->where('patient_id', $patient->id)->count(),
            'An urgency act MUST NOT invent an agenda row (S8).',
        );
    }

    public function test_reception_cannot_open_a_clinical_act(): void
    {
        $reception = $this->staff('recepcionista');
        $patient = Patient::factory()->create();

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/encounters', ['patient_id' => $patient->id])
            ->assertForbidden();

        $this->assertSame(0, ClinicalEncounter::query()->count());
    }

    public function test_the_act_requires_an_existing_patient(): void
    {
        $professional = $this->staff('odontologo');

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/encounters', ['patient_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');

        $this->assertSame(0, ClinicalEncounter::query()->count());
    }

    public function test_voiding_an_open_encounter_records_the_audit_and_the_second_void_is_refused(): void
    {
        $professional = $this->staff('odontologo');
        $encounter = ClinicalEncounter::factory()->create();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/void", ['reason' => 'Paciente equivocado.'])
            ->assertOk()
            ->assertJsonPath('data.status', ClinicalEncounter::STATUS_VOIDED)
            ->assertJsonPath('meta.message', 'Atención anulada.');

        $encounter->refresh();

        $this->assertSame(ClinicalEncounter::STATUS_VOIDED, $encounter->status);
        $this->assertNotNull($encounter->voided_at, 'The void hour MUST be stored.');
        $this->assertSame($professional->id, $encounter->voided_by);
        $this->assertSame('Paciente equivocado.', $encounter->void_reason);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/void", ['reason' => 'Otra vez.'])
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'encounter_void_not_allowed');

        $this->assertSame('Paciente equivocado.', $encounter->fresh()->void_reason);
    }

    public function test_a_closed_encounter_cannot_be_voided(): void
    {
        $professional = $this->staff('odontologo');
        $encounter = ClinicalEncounter::factory()->closed()->create();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/void", ['reason' => 'Se anuló por error.'])
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'encounter_void_not_allowed')
            ->assertJsonPath('error.context.current_status', ClinicalEncounter::STATUS_CLOSED);

        $encounter->refresh();

        $this->assertSame(ClinicalEncounter::STATUS_CLOSED, $encounter->status);
        $this->assertNull($encounter->voided_at);
    }
}
