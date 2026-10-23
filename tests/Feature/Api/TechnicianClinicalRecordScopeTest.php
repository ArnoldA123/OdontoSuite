<?php

namespace Tests\Feature\Api;

use App\Jobs\ExportPatientFileJob;
use App\Models\ClinicalEncounter;
use App\Models\DentalPiece;
use App\Models\ImplantologyRecord;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
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
 *
 * #313 (decision D1) closes the second half of the same boundary: the specialty
 * record is a clinical act with surgical narrative, so the laboratory leaves its
 * module whole and keeps only the case and the treatment plan as prosthetic
 * input.
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

        // `finanzas` keeps the catalog grant it had, but `PatientPolicy::export()`
        // is the effective gate and never included it: the answer stays 403.
        $this->actingAs($this->staff('finanzas'), 'sanctum')
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

    /**
     * #313 (decision D1) — the specialty record is a clinical act with surgical
     * narrative (`surgical_notes`, `post_surgical_notes`, `complications`,
     * `follow_up_notes`), not prosthetic input. Reading and writing are the same
     * permission, so the laboratory desk leaves the module whole: every read and
     * the write door answer 403 and nothing is written.
     */
    public function test_the_laboratory_is_forbidden_on_the_specialty_record_module(): void
    {
        $technician = $this->staff('tecnico_dental');
        $patient = Patient::factory()->create();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/specialty-records?patient_id={$patient->id}&specialty=implantologia")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/specialty-records/1')
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/specialty-records/patient/{$patient->id}/all")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/specialty-records/patient/{$patient->id}/implantologia/stats")
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->postJson('/api/specialty-records', [
                'specialty' => 'implantologia',
                'patient_id' => $patient->id,
                'implant_brand' => 'Straumann',
                'implant_model' => 'BLT',
                'implant_diameter' => '4.1',
                'implant_length' => '10.0',
                'batch_number' => 'L-1',
                'placement_date' => now()->subDay()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('implantology_records', 0);
    }

    /**
     * The grant #313 keeps: administration and the two clinician roles read and
     * write the specialty record exactly as before, and reading it stays a read.
     */
    public function test_administration_and_the_clinicians_keep_the_specialty_record(): void
    {
        $this->seed(DentalPieceSeeder::class);

        $patient = Patient::factory()->create();
        $piece = DentalPiece::query()->where('fdi_number', '16')->firstOrFail();
        $record = ImplantologyRecord::create([
            'patient_id' => $patient->id,
            'dental_piece_id' => $piece->id,
            'created_by' => $this->staff('odontologo')->id,
            'implant_brand' => 'Straumann',
            'implant_model' => 'BLT',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'L-1',
            'placement_date' => now()->subDays(3)->toDateString(),
        ]);

        foreach (['administrador', 'odontologo', 'implantologo'] as $role) {
            $user = $this->staff($role);

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/specialty-records?patient_id={$patient->id}&specialty=implantologia")
                ->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/specialty-records/{$record->id}")
                ->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/specialty-records/patient/{$patient->id}/all")
                ->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/specialty-records/patient/{$patient->id}/implantologia/stats")
                ->assertOk();
        }

        $this->assertDatabaseCount('implantology_records', 1);
    }
}
