<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\DentalPiece;
use App\Models\Patient;
use App\Models\ProcedureCatalog;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 1 of «Registros de especialidad integrados a la atención y la historia».
 *
 * The five specialty tables hold structured clinical data the SOAP evolution
 * does not capture, but they lived isolated from the act that produced them and
 * from the plan line they execute. This contract fixes the two links:
 *
 *  - `store`/`update` accept `clinical_encounter_id` and
 *    `treatment_plan_item_id` and refuse a link that contradicts the record
 *    (another patient, another appointment, another procedure);
 *  - `show` and `GET /patient/{id}/all` publish both ids;
 *  - a manual record with no link keeps working exactly as before.
 */
class SpecialtyRecordEncounterLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Patient $patient;

    private DentalPiece $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->patient = Patient::factory()->create();
        $this->seed(DentalPieceSeeder::class);
        $this->piece = DentalPiece::query()->where('fdi_number', '16')->firstOrFail();
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function implantPayload(array $overrides = []): array
    {
        return array_merge([
            'specialty' => 'implantologia',
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'implant_brand' => 'Straumann',
            'implant_model' => 'BLT',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'L-778',
            'placement_date' => now()->subDay()->toDateString(),
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function encounter(array $attributes = []): ClinicalEncounter
    {
        return ClinicalEncounter::factory()->create(array_merge([
            'patient_id' => $this->patient->id,
            'user_id' => $this->admin->id,
        ], $attributes));
    }

    private function booking(int $hour = 9): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => $this->patient->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'scheduled_at' => now()->startOfDay()->addHours($hour),
        ]);
    }

    private function planItemFor(?Patient $patient = null, ?ProcedureCatalog $procedure = null): TreatmentPlanItem
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => ($patient ?? $this->patient)->id,
            'created_by' => $this->admin->id,
        ]);

        return TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_catalog_id' => $procedure?->id,
            'procedure_name' => $procedure?->name ?? 'Implante unitario',
            'quantity' => 1,
            'unit_cost' => 100,
            'total_cost' => 100,
            'phase_number' => 1,
            'status' => 'pending',
        ]);
    }

    public function test_store_persists_the_encounter_and_plan_item_links(): void
    {
        $encounter = $this->encounter();
        $item = $this->planItemFor();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'clinical_encounter_id' => $encounter->id,
                'treatment_plan_item_id' => $item->id,
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('implantology_records', [
            'id' => $response->json('data.id'),
            'clinical_encounter_id' => $encounter->id,
            'treatment_plan_item_id' => $item->id,
        ]);
    }

    public function test_a_manual_record_without_links_keeps_working(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload())
            ->assertCreated();

        $this->assertDatabaseHas('implantology_records', [
            'id' => $response->json('data.id'),
            'clinical_encounter_id' => null,
            'treatment_plan_item_id' => null,
        ]);
    }

    public function test_store_refuses_an_encounter_of_another_patient(): void
    {
        $foreign = $this->encounter(['patient_id' => Patient::factory()->create()->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'clinical_encounter_id' => $foreign->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('clinical_encounter_id');

        $this->assertDatabaseCount('implantology_records', 0);
    }

    public function test_store_refuses_an_encounter_of_another_appointment(): void
    {
        $booking = $this->booking(9);
        $otherBooking = $this->booking(11);

        $encounter = $this->encounter([
            'appointment_id' => $booking->id,
            'mode' => ClinicalEncounter::MODE_CONSULTATION,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'clinical_encounter_id' => $encounter->id,
                'appointment_id' => $otherBooking->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('clinical_encounter_id');

        $this->assertDatabaseCount('implantology_records', 0);
    }

    public function test_store_accepts_the_encounter_of_the_declared_appointment(): void
    {
        $booking = $this->booking();
        $encounter = $this->encounter([
            'appointment_id' => $booking->id,
            'mode' => ClinicalEncounter::MODE_CONSULTATION,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'appointment_id' => $booking->id,
                'clinical_encounter_id' => $encounter->id,
            ]))
            ->assertCreated();
    }

    public function test_store_refuses_a_plan_item_of_another_patient(): void
    {
        $item = $this->planItemFor(Patient::factory()->create());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'treatment_plan_item_id' => $item->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('treatment_plan_item_id');

        $this->assertDatabaseCount('implantology_records', 0);
    }

    public function test_store_refuses_a_plan_item_of_another_procedure(): void
    {
        $item = $this->planItemFor(null, ProcedureCatalog::factory()->create());
        $procedure = ProcedureCatalog::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'procedure_id' => $procedure->id,
                'treatment_plan_item_id' => $item->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('procedure_id');
    }

    public function test_store_accepts_the_plan_item_without_a_catalog_procedure(): void
    {
        $item = $this->planItemFor(null, null);
        $procedure = ProcedureCatalog::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'procedure_id' => $procedure->id,
                'treatment_plan_item_id' => $item->id,
            ]))
            ->assertCreated();
    }

    public function test_store_refuses_an_unknown_encounter_or_plan_item(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'clinical_encounter_id' => 999999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('clinical_encounter_id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'treatment_plan_item_id' => 999999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('treatment_plan_item_id');
    }

    public function test_update_persists_and_clears_the_links(): void
    {
        $encounter = $this->encounter();
        $item = $this->planItemFor();

        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload())
            ->assertCreated();

        $id = $created->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$id}", [
                'specialty' => 'implantologia',
                'clinical_encounter_id' => $encounter->id,
                'treatment_plan_item_id' => $item->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('implantology_records', [
            'id' => $id,
            'clinical_encounter_id' => $encounter->id,
            'treatment_plan_item_id' => $item->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$id}", [
                'specialty' => 'implantologia',
                'clinical_encounter_id' => null,
                'treatment_plan_item_id' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('implantology_records', [
            'id' => $id,
            'clinical_encounter_id' => null,
            'treatment_plan_item_id' => null,
        ]);
    }

    public function test_update_refuses_an_encounter_of_another_patient(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload())
            ->assertCreated();

        $foreign = $this->encounter(['patient_id' => Patient::factory()->create()->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/specialty-records/{$created->json('data.id')}", [
                'specialty' => 'implantologia',
                'clinical_encounter_id' => $foreign->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('clinical_encounter_id');

        $this->assertDatabaseHas('implantology_records', [
            'id' => $created->json('data.id'),
            'clinical_encounter_id' => null,
        ]);
    }

    public function test_show_publishes_the_links_and_their_relations(): void
    {
        $encounter = $this->encounter();
        $item = $this->planItemFor();

        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'clinical_encounter_id' => $encounter->id,
                'treatment_plan_item_id' => $item->id,
            ]))
            ->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/specialty-records/{$created->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.clinical_encounter_id', $encounter->id)
            ->assertJsonPath('data.treatment_plan_item_id', $item->id)
            ->assertJsonPath('data.clinical_encounter.id', $encounter->id)
            ->assertJsonPath('data.treatment_plan_item.id', $item->id);
    }

    public function test_the_patient_aggregate_publishes_the_links_of_every_specialty(): void
    {
        $encounter = $this->encounter();
        $item = $this->planItemFor();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->implantPayload([
                'clinical_encounter_id' => $encounter->id,
                'treatment_plan_item_id' => $item->id,
            ]))
            ->assertCreated();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/specialty-records/patient/{$this->patient->id}/all")
            ->assertOk();

        $record = $response->json('data.implantologia.0');

        $this->assertSame($encounter->id, $record['clinical_encounter_id']);
        $this->assertSame($item->id, $record['treatment_plan_item_id']);
    }
}
