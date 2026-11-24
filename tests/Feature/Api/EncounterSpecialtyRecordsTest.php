<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEncounter;
use App\Models\DentalPiece;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\Patient;
use App\Models\ProcedureCatalog;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 1 of «Registros de especialidad integrados a la atención y la historia».
 *
 * The act publishes the structured clinical records that belong to it. They
 * were already stored with their own columns — implant brand, canal count,
 * prosthesis material, surgical site — and the SOAP evolution never captured
 * them, so the encounter detail is where the professional reads them next to
 * the rest of the act.
 *
 * The read is link-driven: a record appears only when it names this encounter.
 * A record of another act, or one never linked, stays out.
 */
class EncounterSpecialtyRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $professional;

    private Patient $patient;

    private DentalPiece $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->professional = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
        $this->patient = Patient::factory()->create();
        $this->seed(DentalPieceSeeder::class);
        $this->piece = DentalPiece::query()->where('fdi_number', '16')->firstOrFail();
    }

    private function encounter(?Patient $patient = null): ClinicalEncounter
    {
        return ClinicalEncounter::factory()->create([
            'patient_id' => ($patient ?? $this->patient)->id,
            'user_id' => $this->professional->id,
        ]);
    }

    public function test_the_act_publishes_the_specialty_records_linked_to_it(): void
    {
        $encounter = $this->encounter();
        $procedure = ProcedureCatalog::factory()->create([
            'code' => 'IMPL-16',
            'name' => 'Implante unitario',
        ]);

        $implant = ImplantologyRecord::create([
            'patient_id' => $this->patient->id,
            'clinical_encounter_id' => $encounter->id,
            'procedure_id' => $procedure->id,
            'dental_piece_id' => $this->piece->id,
            'created_by' => $this->professional->id,
            'implant_brand' => 'Straumann',
            'implant_model' => 'BLT',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'L-778',
            'placement_date' => '2026-03-04',
            'status' => 'placed',
        ]);

        OrthodonticsRecord::create([
            'patient_id' => $this->patient->id,
            'clinical_encounter_id' => $encounter->id,
            'created_by' => $this->professional->id,
            'treatment_type' => 'brackets',
            'appliance_type' => 'metálicos',
            'treatment_start_date' => '2026-02-01',
            'treatment_phase' => 'active',
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertOk();

        $records = $response->json('data.specialty_records');

        $this->assertIsArray($records);
        $this->assertCount(2, $records);

        $implantRow = collect($records)->firstWhere('id', $implant->id);

        $this->assertNotNull($implantRow, 'The implant record MUST appear in the act.');
        $this->assertSame('implantologia', $implantRow['type']);
        $this->assertSame('Implantología', $implantRow['type_label']);
        $this->assertSame($encounter->id, $implantRow['clinical_encounter_id']);
        $this->assertSame('IMPL-16', $implantRow['procedure']['code']);
        $this->assertSame('Implante unitario', $implantRow['procedure']['name']);
        $this->assertSame('2026-03-04', $implantRow['date']);
        $this->assertSame('Colocación', $implantRow['date_label']);
        $this->assertSame('16', $implantRow['dental_piece']);
        $this->assertStringContainsString('#16', $implantRow['summary']);
        $this->assertStringContainsString('Straumann', $implantRow['summary']);
        $this->assertStringContainsString('L-778', $implantRow['summary']);
        $this->assertSame('placed', $implantRow['status']);

        $orthoRow = collect($records)->firstWhere('type', 'ortodoncia');

        $this->assertNotNull($orthoRow);
        $this->assertSame('2026-02-01', $orthoRow['date']);
        $this->assertStringContainsString('brackets', $orthoRow['summary']);
    }

    public function test_a_record_of_another_act_does_not_appear(): void
    {
        $encounter = $this->encounter();
        $otherEncounter = $this->encounter();

        OralSurgeryRecord::create([
            'patient_id' => $this->patient->id,
            'clinical_encounter_id' => $otherEncounter->id,
            'created_by' => $this->professional->id,
            'procedure_type' => 'extraccion',
            'surgery_site' => '38',
            'surgery_date' => '2026-03-01',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertOk();

        $this->assertSame([], $response->json('data.specialty_records'));
    }

    public function test_an_unlinked_record_does_not_appear_in_any_act(): void
    {
        $encounter = $this->encounter();

        ImplantologyRecord::create([
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'created_by' => $this->professional->id,
            'implant_brand' => 'Nobel',
            'implant_model' => 'Replace',
            'implant_diameter' => '4.3',
            'implant_length' => '11.5',
            'batch_number' => 'NB-1',
            'placement_date' => '2026-01-10',
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertOk();

        $this->assertSame([], $response->json('data.specialty_records'));
    }

    public function test_reading_the_act_does_not_write_the_state_machine(): void
    {
        $encounter = $this->encounter();

        ImplantologyRecord::create([
            'patient_id' => $this->patient->id,
            'clinical_encounter_id' => $encounter->id,
            'dental_piece_id' => $this->piece->id,
            'created_by' => $this->professional->id,
            'implant_brand' => 'Straumann',
            'implant_model' => 'BLT',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'L-778',
            'placement_date' => '2026-03-04',
        ]);

        $this->actingAs($this->professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}")
            ->assertOk();

        $this->assertSame(ClinicalEncounter::STATUS_OPEN, $encounter->fresh()->status);
        $this->assertNull($encounter->fresh()->closed_at);
    }
}
