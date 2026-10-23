<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicalEncounter;
use App\Models\ContinuityRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * F6 (#286) — the printable record of the act (NTS 139, S4/S5).
 *
 * The document is emitted from a closed act and carries the minimum content of
 * the norm: identification of the patient and of the act, the complaint, the
 * exam, the coded diagnosis, the treatment, the next appointment, the odontogram
 * findings of NTS 188 and the professional's signature with its verification
 * hash. Printing it is a clinical read, so it is audited like the history.
 */
class EncounterRecordPdfTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
            'professional_license' => 'COP 12345',
        ]);
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
                'procedures_performed' => 'Apertura y aislamiento.',
                'next_appointment_notes' => 'Control en dos semanas.',
            ],
        ], $this->compliancePayload(), $overrides);
    }

    private function closedEncounter(array $payloadOverrides = []): ClinicalEncounter
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload($payloadOverrides))
            ->assertOk();

        return ClinicalEncounter::where('appointment_id', $appointment->id)->sole();
    }

    public function test_the_record_is_emitted_as_a_pdf_named_after_the_act(): void
    {
        $encounter = $this->closedEncounter();

        $response = $this->actingAs($encounter->user, 'sanctum')
            ->get("/api/encounters/{$encounter->id}/record");

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            "registro-atencion-{$encounter->id}.pdf",
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_the_record_carries_the_minimum_blocks_of_the_norm(): void
    {
        $encounter = $this->closedEncounter([
            'next_appointment' => [
                'scheduled_at' => now()->addWeeks(2)->setTime(9, 0)->toDateTimeString(),
                'duration_minutes' => 30,
                'notes' => 'Control postoperatorio.',
            ],
        ]);

        $encounter->loadMissing([
            'patient',
            'user',
            'signedBy',
            'medicalRecord',
            'clinicalEvolutions',
            'diagnoses',
            'continuityRequests',
        ]);
        $next = ContinuityRequest::where('clinical_encounter_id', $encounter->id)->sole();

        $html = view('reports.encounter-record', [
            'encounter' => $encounter,
            'signatureVerified' => true,
            'nextAppointment' => $next,
        ])->render();

        $this->assertStringContainsString('REGISTRO DE ATENCIÓN ODONTOLÓGICA', $html);
        $this->assertStringContainsString('Motivo de consulta', $html);
        $this->assertStringContainsString('Dolor en la pieza 16 desde hace tres días.', $html);
        $this->assertStringContainsString('Examen clínico', $html);
        $this->assertStringContainsString('Caries oclusal profunda', $html);
        $this->assertStringContainsString('Diagnóstico (CIE-10)', $html);
        $this->assertStringContainsString('K02.1', $html);
        $this->assertStringContainsString('Caries de la dentina', $html);
        $this->assertStringContainsString('Obturación con resina compuesta.', $html);
        $this->assertStringContainsString('Próxima cita', $html);
        $this->assertStringContainsString('Control postoperatorio.', $html);
        $this->assertStringContainsString('Odontograma', $html);
        $this->assertStringContainsString('COP 12345', $html);
        $this->assertStringContainsString((string) $encounter->signature_hash, $html);
        $this->assertStringContainsString('el contenido coincide con lo firmado.', $html);
    }

    public function test_an_open_act_has_no_record_to_print(): void
    {
        $professional = $this->professional();
        $patient = Patient::factory()->create();

        $encounterId = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/encounters', ['patient_id' => $patient->id])
            ->assertCreated()
            ->json('data.id');

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters/{$encounterId}/record")
            ->assertStatus(422);

        $this->assertSame('encounter_not_closed', $response->json('error.error_code'));
    }

    public function test_reception_cannot_print_the_clinical_record(): void
    {
        $encounter = $this->closedEncounter();
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);

        $this->actingAs($receptionist, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}/record")
            ->assertForbidden();
    }

    public function test_printing_the_record_leaves_the_clinical_read_trail(): void
    {
        $encounter = $this->closedEncounter();

        $this->actingAs($encounter->user, 'sanctum')
            ->get("/api/encounters/{$encounter->id}/record")
            ->assertOk();

        $log = AuditLog::where('action', 'medical_record_viewed')->sole();

        $this->assertSame($encounter->medical_record_id, $log->auditable_id);
        $this->assertSame('App\Models\MedicalRecord', $log->auditable_type);
        $this->assertSame(['surface' => 'attention_record'], $log->metadata);
        $this->assertSame($encounter->user_id, $log->user_id);
    }
}
