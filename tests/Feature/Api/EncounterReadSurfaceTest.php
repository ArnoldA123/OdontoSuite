<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\DentalPiece;
use App\Models\OdontogramRecord;
use App\Models\Patient;
use App\Models\ProcedureMaterial;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F2 (#282) — the read surface of the clinical act.
 *
 * An urgency act has no agenda row to be reached by (S8), so the act itself
 * must be readable: the index is its entry point and the detail is where the
 * documentation the closure produced is visible (S1/S5). Reading it is a
 * clinical act, so reception and finance are refused on both doors.
 */
class EncounterReadSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function encounter(Patient $patient, array $attributes = []): ClinicalEncounter
    {
        return ClinicalEncounter::factory()->create(array_merge([
            'patient_id' => $patient->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function closurePayload(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'execution',
            'evolution' => [
                'subjective' => 'Dolor en pieza 16 desde hace tres días.',
                'objective' => 'Caries oclusal profunda.',
                'assessment' => 'Caries dentinaria.',
                'plan' => 'Obturación con resina compuesta.',
            ],
            'treatment_plan' => [
                'title' => 'Restauración 16',
                'items' => [
                    ['procedure_name' => 'Obturación composite', 'unit_cost' => 250, 'quantity' => 1],
                ],
            ],
        ], $overrides);
    }

    public function test_the_index_filters_by_patient_and_status(): void
    {
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();

        $this->encounter($patient, [
            'user_id' => $professional->id,
            'opened_at' => '2026-03-11 09:00:00',
        ]);
        $newest = $this->encounter($patient, [
            'user_id' => $professional->id,
            'opened_at' => '2026-03-12 15:00:00',
        ]);
        $this->encounter($patient, [
            'user_id' => $professional->id,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'opened_at' => '2026-03-13 10:00:00',
            'closed_at' => '2026-03-13 11:00:00',
        ]);
        $this->encounter($otherPatient, [
            'user_id' => $professional->id,
            'opened_at' => '2026-03-14 10:00:00',
        ]);

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters?patient_id={$patient->id}&status=open");

        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertSame($patient->id, $row['patient_id']);
            $this->assertSame(ClinicalEncounter::STATUS_OPEN, $row['status']);
        }

        // Opened descending: the most recent act of the patient comes first.
        $this->assertSame($newest->id, $response->json('data.0.id'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_the_date_window_is_inclusive_on_both_edges(): void
    {
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();

        $onTheEdge = $this->encounter($patient, ['opened_at' => '2026-04-10 23:30:00']);
        $outside = $this->encounter($patient, ['opened_at' => '2026-04-11 00:30:00']);

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters?patient_id={$patient->id}&from=2026-04-10&to=2026-04-10");

        $response->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($onTheEdge->id, $response->json('data.0.id'));
        $this->assertNotSame($outside->id, $response->json('data.0.id'));
    }

    public function test_the_meta_reflects_the_page_and_per_page_is_bounded(): void
    {
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();

        foreach (range(1, 3) as $index) {
            $this->encounter($patient, ['opened_at' => "2026-05-0{$index} 09:00:00"]);
        }

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters?patient_id={$patient->id}&per_page=2");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/encounters?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_the_detail_returns_what_the_closure_linked_to_the_act(): void
    {
        Storage::fake('public');

        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();
        $encounter = $this->encounter($patient, [
            'user_id' => $professional->id,
            'mode' => ClinicalEncounter::MODE_URGENCY,
        ]);

        $dentalPiece = DentalPiece::create([
            'fdi_number' => '16',
            'name' => 'Primer molar superior derecho',
            'type' => 'molar',
            'quadrant' => 'superior_derecho',
            'position' => 6,
        ]);

        $product = Product::create([
            'product_category_id' => ProductCategory::create(['name' => 'Restauración', 'code' => 'REST'])->id,
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'cost_price' => 20,
            'sale_price' => 35,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->closurePayload([
                'odontogram' => [[
                    'dental_piece_id' => $dentalPiece->id,
                    'condition_code' => 'caries',
                    'condition_name' => 'Caries',
                    'diagnosis' => 'Caries oclusal.',
                ]],
                'materials' => [[
                    'product_id' => $product->id,
                    'quantity_used' => 2,
                    'unit_cost' => 20,
                ]],
                'attachments' => [[
                    'category' => 'radiografia',
                    'file' => UploadedFile::fake()->image('rx16.jpg'),
                ]],
            ]))
            ->assertOk();

        // Every row the act produced hangs from the act itself.
        $this->assertSame(1, ClinicalEvolution::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame(1, OdontogramRecord::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame(1, ProcedureMaterial::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame(1, ClinicalAttachment::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame(1, Quotation::where('clinical_encounter_id', $encounter->id)->count());

        // The closure owns the row: the remaining assertions read what it wrote,
        // not what the factory created.
        $encounter->refresh();

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson("/api/encounters/{$encounter->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $encounter->id)
            ->assertJsonPath('data.status', ClinicalEncounter::STATUS_CLOSED)
            ->assertJsonPath('data.closed_by', $professional->id)
            ->assertJsonPath('data.closed_by_user', $professional->name)
            ->assertJsonPath('data.final_amount', 250)
            ->assertJsonPath('data.medical_record_id', $encounter->medical_record_id)
            ->assertJsonPath('data.treatment_plan_id', $encounter->treatment_plan_id);

        $response->assertJsonCount(1, 'data.evolutions')
            ->assertJsonPath('data.evolutions.0.assessment', 'Caries dentinaria.')
            ->assertJsonPath('data.evolutions.0.plan', 'Obturación con resina compuesta.');

        $response->assertJsonCount(1, 'data.odontogram_records')
            ->assertJsonPath('data.odontogram_records.0.dental_piece_id', $dentalPiece->id)
            ->assertJsonPath('data.odontogram_records.0.dental_piece', '16')
            ->assertJsonPath('data.odontogram_records.0.condition_code', 'caries')
            ->assertJsonPath('data.odontogram_records.0.diagnosis', 'Caries oclusal.');

        $response->assertJsonCount(1, 'data.materials')
            ->assertJsonPath('data.materials.0.product_id', $product->id)
            ->assertJsonPath('data.materials.0.product', 'Resina A2')
            ->assertJsonPath('data.materials.0.quantity_used', 2)
            ->assertJsonPath('data.materials.0.total_cost', 40);

        $response->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.original_name', 'rx16.jpg')
            ->assertJsonPath('data.attachments.0.category', 'radiografia');

        $response->assertJsonCount(1, 'data.quotations')
            ->assertJsonPath('data.quotations.0.total_amount', 250);

        $this->assertNotNull($response->json('data.attachments.0.created_at'));
    }

    public function test_reading_the_act_is_clinical_only(): void
    {
        $professional = $this->staff('odontologo');
        $encounter = $this->encounter(Patient::factory()->create(), ['user_id' => $professional->id]);

        foreach (['recepcionista', 'finanzas'] as $role) {
            $user = $this->staff($role);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/encounters')
                ->assertForbidden();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/encounters/{$encounter->id}")
                ->assertForbidden();
        }

        foreach (['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente'] as $role) {
            $user = $this->staff($role);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/encounters')
                ->assertOk();

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/encounters/{$encounter->id}")
                ->assertOk();
        }
    }

    public function test_an_unknown_encounter_is_not_found(): void
    {
        $professional = $this->staff('odontologo');

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/encounters/999999')
            ->assertNotFound();
    }
}
