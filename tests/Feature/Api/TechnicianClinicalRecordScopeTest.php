<?php

namespace Tests\Feature\Api;

use App\Jobs\ExportPatientFileJob;
use App\Models\ClinicalEncounter;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * F5 (#285, S3/S5) — the laboratory desk does not read the clinical record.
 *
 * The prosthetic lab used to share `MEDICAL_ROLES`, so `tecnico_dental` could
 * read the history, the evolutions, the attachments and the act. The case is
 * now its own entity and the record leaves that desk: a lab technician works
 * from the work order, not from the patient's clinical history. The other four
 * clinical roles keep the record exactly as before.
 */
class TechnicianClinicalRecordScopeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_the_laboratory_is_forbidden_on_the_whole_clinical_record(): void
    {
        $technician = $this->staff('tecnico_dental');
        $patient = Patient::factory()->create();
        $record = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $this->staff('odontologo')->id,
        ]);
        $encounter = ClinicalEncounter::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $this->staff('odontologo')->id,
        ]);

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/medical-records')
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}/evolutions")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/attachments")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/stats")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/encounters')
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertForbidden();

        // The write door of the record is closed too: reading it and writing
        // it are the same surface.
        $this->actingAs($technician, 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'subjective' => 'Control de laboratorio',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    /**
     * The patient-file export is the record in one GET: the PDF and the ZIP
     * carry the evolutions, the odontograms, the specialty records and the
     * attachment binaries. It was reachable by `export.patient`, so the
     * laboratory desk left that capability too.
     */
    public function test_the_laboratory_cannot_export_the_patient_file(): void
    {
        Queue::fake();

        $technician = $this->staff('tecnico_dental');
        $patient = Patient::factory()->create();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/patients/{$patient->id}/export?format=pdf&async=1")
            ->assertForbidden();

        Queue::assertNothingPushed();

        foreach (['administrador', 'recepcionista', 'odontologo', 'implantologo', 'asistente'] as $role) {
            $this->actingAs($this->staff($role), 'sanctum')
                ->getJson("/api/patients/{$patient->id}/export?format=pdf&async=1")
                ->assertStatus(202);
        }

        Queue::assertPushed(ExportPatientFileJob::class, 5);
    }

    public function test_the_other_clinical_roles_keep_the_record_and_the_act(): void
    {
        $patient = Patient::factory()->create();
        $record = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $this->staff('odontologo')->id,
        ]);
        $encounter = ClinicalEncounter::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $this->staff('odontologo')->id,
        ]);

        foreach (['administrador', 'odontologo', 'implantologo', 'asistente'] as $role) {
            $user = $this->staff($role);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/medical-records')
                ->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/medical-records/{$record->id}")
                ->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/encounters/{$encounter->id}")
                ->assertOk();
        }
    }
}
