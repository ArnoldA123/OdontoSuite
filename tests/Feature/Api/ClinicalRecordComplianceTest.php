<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\EncounterDiagnosis;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicalRecordSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * F6 (#286) — NTS 139 on the close of the act.
 *
 * The close now carries the patient's complaint, at least one CIE-10 diagnosis
 * and the professional's signature. The signature is the simple electronic one:
 * the professional re-enters their own password and the close stores who signed,
 * when, with which licence and a SHA-256 over the frozen content. A close without
 * any of the three is refused before writing anything.
 */
class ClinicalRecordComplianceTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private function professional(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'odontologo',
            'is_active' => true,
            'professional_license' => 'COP 12345',
        ], $attributes));
    }

    private function inConsultation(User $professional): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(20),
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
                'subjective' => 'Dolor en pieza 16 desde hace tres días.',
                'objective' => 'Caries oclusal profunda, sin exposición pulpar.',
                'assessment' => 'Caries dentinaria.',
                'plan' => 'Obturación con resina compuesta.',
            ],
        ], $this->compliancePayload(), $overrides);
    }

    private function closeAs(User $actor, Appointment $appointment, array $payload)
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $payload);
    }

    public function test_a_close_without_the_complaint_is_refused_and_writes_nothing(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $payload = $this->payload();
        unset($payload['reason_for_consultation']);

        $this->closeAs($professional, $appointment, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason_for_consultation');

        $this->assertSame(0, ClinicalEvolution::count());
        $this->assertSame(0, MedicalRecord::count());
        $this->assertSame(0, ClinicalEncounter::whereNotNull('closed_at')->count());
    }

    public function test_a_close_without_a_coded_diagnosis_is_refused(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $payload = $this->payload();
        $payload['diagnoses'] = [];

        $this->closeAs($professional, $appointment, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('diagnoses');

        $this->assertSame(0, EncounterDiagnosis::count());
        $this->assertSame(0, ClinicalEvolution::count());
    }

    public function test_a_close_without_the_signature_is_refused(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $payload = $this->payload();
        unset($payload['signature']);

        $this->closeAs($professional, $appointment, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signature', 'signature.password']);

        $this->assertSame(0, ClinicalEvolution::count());
        $this->assertSame(0, ClinicalEncounter::whereNotNull('signed_at')->count());
    }

    public function test_a_wrong_password_does_not_sign_or_close_the_act(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $response = $this->closeAs($professional, $appointment, $this->payload([
            'signature' => ['password' => 'no-es-mi-clave'],
        ]))->assertStatus(422);

        $this->assertSame(
            'clinical_signature_invalid_credentials',
            $response->json('error.error_code'),
        );

        // The refusal happens before the act is opened, so the transaction has
        // nothing to leave behind.
        $this->assertSame(0, ClinicalEncounter::count());
        $this->assertSame(0, ClinicalEvolution::count());
    }

    public function test_only_the_professional_of_the_act_signs_it(): void
    {
        $professional = $this->professional();
        $administrator = User::factory()->create(['role' => 'administrador', 'is_active' => true]);
        $appointment = $this->inConsultation($professional);

        $response = $this->closeAs($administrator, $appointment, $this->payload())
            ->assertStatus(403);

        $this->assertSame(
            'clinical_signature_not_professional',
            $response->json('error.error_code'),
        );

        $this->assertSame(0, ClinicalEncounter::count());
        $this->assertSame(0, ClinicalEvolution::count());
    }

    public function test_the_signed_close_freezes_the_complaint_the_diagnoses_and_the_signature(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $this->closeAs($professional, $appointment, $this->payload())
            ->assertOk()
            ->assertJsonPath('meta.message', 'Consulta completada.');

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();
        $this->assertSame('Dolor en la pieza 16 desde hace tres días.', $encounter->reason_for_consultation);
        $this->assertSame($professional->id, $encounter->signed_by);
        $this->assertSame('COP 12345', $encounter->signer_license);
        $this->assertNotNull($encounter->signed_at);
        $this->assertSame(64, strlen((string) $encounter->signature_hash));

        $diagnosis = EncounterDiagnosis::where('clinical_encounter_id', $encounter->id)->sole();
        $this->assertSame('K02.1', $diagnosis->cie_code);
        $this->assertSame('Caries de la dentina', $diagnosis->name);
        $this->assertSame(EncounterDiagnosis::TYPE_DEFINITIVE, $diagnosis->type);
        $this->assertSame('Lesión oclusal.', $diagnosis->notes);

        $this->assertTrue(ClinicalRecordSignature::matches($encounter->fresh()));

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertOk()
            ->assertJsonPath('data.reason_for_consultation', 'Dolor en la pieza 16 desde hace tres días.')
            ->assertJsonPath('data.signature.signed', true)
            ->assertJsonPath('data.signature.verified', true)
            ->assertJsonPath('data.signature.license', 'COP 12345')
            ->assertJsonPath('data.diagnoses.0.cie_code', 'K02.1');
    }

    public function test_the_catalogue_entry_must_be_active_to_be_coded(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $retired = $this->diagnosisEntry(['is_active' => false]);

        $this->closeAs($professional, $appointment, $this->payload([
            'diagnoses' => [['diagnosis_catalog_id' => $retired->id]],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('diagnoses.0.diagnosis_catalog_id');

        $this->assertSame(0, EncounterDiagnosis::count());
        $this->assertSame(0, ClinicalEvolution::count());
    }

    public function test_the_same_diagnosis_cannot_be_coded_twice_on_one_act(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $entry = $this->diagnosisEntry();

        $this->closeAs($professional, $appointment, $this->payload([
            'diagnoses' => [
                ['diagnosis_catalog_id' => $entry->id],
                ['diagnosis_catalog_id' => $entry->id],
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('diagnoses.0.diagnosis_catalog_id');

        $this->assertSame(0, EncounterDiagnosis::count());
    }

    public function test_a_record_changed_after_the_signature_fails_verification(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $this->closeAs($professional, $appointment, $this->payload())->assertOk();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();
        $this->assertTrue(ClinicalRecordSignature::matches($encounter->fresh()));

        DB::table('clinical_evolutions')
            ->where('clinical_encounter_id', $encounter->id)
            ->update(['objective' => 'Texto alterado fuera del acto.']);

        $this->assertFalse(ClinicalRecordSignature::matches($encounter->fresh()));

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertOk()
            ->assertJsonPath('data.signature.verified', false);
    }

    public function test_the_urgency_door_records_the_complaint_and_requires_it_at_the_close(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();

        $opened = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/encounters', [
                'patient_id' => $patient->id,
                'reason_for_consultation' => 'Traumatismo en incisivo central.',
            ])
            ->assertCreated();

        $encounterId = $opened->json('data.id');
        $this->assertSame(
            'Traumatismo en incisivo central.',
            ClinicalEncounter::findOrFail($encounterId)->reason_for_consultation,
        );

        $payload = array_merge($this->payload(), ['mode' => 'execution', 'skip_materials' => true]);
        unset($payload['reason_for_consultation']);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounterId}/complete", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason_for_consultation');

        $this->assertSame(ClinicalEncounter::STATUS_OPEN, ClinicalEncounter::findOrFail($encounterId)->status);
    }
}
