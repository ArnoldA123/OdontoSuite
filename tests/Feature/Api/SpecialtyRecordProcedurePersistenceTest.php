<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\Patient;
use App\Models\ProcedureCatalog;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Persistence contract for the optional catalog procedure of a specialty
 * record (issue #247).
 *
 * `StoreSpecialtyRecordRequest` has validated `procedure_id` since slice 02
 * (T-02.10, BF-007), but none of the five tables had the column and
 * `validated()` dropped it silently: the API answered 201 while the clinical
 * link to the catalog was lost. These cases pin the write, the read and the
 * unchanged payload of a record without a procedure.
 */
class SpecialtyRecordProcedurePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Patient $patient;

    private DentalPiece $piece;

    private ProcedureCatalog $procedure;

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
        $this->procedure = ProcedureCatalog::factory()->create([
            'name' => 'Extracción simple',
        ]);
    }

    /**
     * @return array<string, array{specialty: string, table: string, payload: array<string, mixed>}>
     */
    private function specialties(): array
    {
        return [
            'implantologia' => [
                'specialty' => 'implantologia',
                'table' => 'implantology_records',
                'payload' => [
                    'dental_piece_id' => $this->piece->id,
                    'implant_brand' => 'Nobel Biocare',
                    'implant_model' => 'Replace Select',
                    'implant_diameter' => '4.1',
                    'implant_length' => '10.0',
                    'batch_number' => 'NB-1000',
                    'placement_date' => now()->subDay()->toDateString(),
                ],
            ],
            'ortodoncia' => [
                'specialty' => 'ortodoncia',
                'table' => 'orthodontics_records',
                'payload' => [
                    'treatment_type' => 'brackets',
                    'treatment_start_date' => now()->subMonth()->toDateString(),
                ],
            ],
            'endodoncia' => [
                'specialty' => 'endodoncia',
                'table' => 'endodontics_records',
                'payload' => [
                    'dental_piece_id' => $this->piece->id,
                    'tooth_number' => '16',
                    'canal_count' => 3,
                ],
            ],
            'rehabilitacion' => [
                'specialty' => 'rehabilitacion',
                'table' => 'rehabilitation_records',
                'payload' => [
                    'dental_piece_id' => $this->piece->id,
                    'prosthesis_type' => 'crown',
                    'material_type' => 'zirconia',
                ],
            ],
            'cirugia_oral' => [
                'specialty' => 'cirugia_oral',
                'table' => 'oral_surgery_records',
                'payload' => [
                    'procedure_type' => 'extraction',
                    'surgery_site' => 'Tercer molar inferior izquierdo',
                ],
            ],
        ];
    }

    public function test_each_specialty_persists_the_catalog_procedure(): void
    {
        foreach ($this->specialties() as $case) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson('/api/specialty-records', [
                    'specialty' => $case['specialty'],
                    'patient_id' => $this->patient->id,
                    'procedure_id' => $this->procedure->id,
                    ...$case['payload'],
                ])
                ->assertCreated();

            $this->assertDatabaseHas($case['table'], [
                'patient_id' => $this->patient->id,
                'procedure_id' => $this->procedure->id,
            ]);
        }
    }

    public function test_the_listing_returns_the_linked_procedure(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'specialty' => 'implantologia',
                'patient_id' => $this->patient->id,
                'procedure_id' => $this->procedure->id,
                ...$this->specialties()['implantologia']['payload'],
            ])
            ->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/specialty-records?specialty=implantologia&patient_id='.$this->patient->id)
            ->assertOk()
            ->assertJsonPath('data.0.procedure_id', $this->procedure->id)
            ->assertJsonPath('data.0.procedure.id', $this->procedure->id)
            ->assertJsonPath('data.0.procedure.name', 'Extracción simple');
    }

    public function test_an_unknown_procedure_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'specialty' => 'implantologia',
                'patient_id' => $this->patient->id,
                'procedure_id' => 999999,
                ...$this->specialties()['implantologia']['payload'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('procedure_id');

        $this->assertDatabaseCount('implantology_records', 0);
    }

    public function test_update_sets_and_clears_the_procedure(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'specialty' => 'endodoncia',
                'patient_id' => $this->patient->id,
                ...$this->specialties()['endodoncia']['payload'],
            ])
            ->assertCreated()
            ->json('data');

        $replacement = ProcedureCatalog::factory()->create(['name' => 'Endodoncia unirradicular']);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.$created['id'], [
                'specialty' => 'endodoncia',
                'procedure_id' => $replacement->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.procedure_id', $replacement->id);

        $this->assertDatabaseHas('endodontics_records', [
            'id' => $created['id'],
            'procedure_id' => $replacement->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.$created['id'], [
                'specialty' => 'endodoncia',
                'procedure_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.procedure_id', null);

        $this->assertDatabaseHas('endodontics_records', [
            'id' => $created['id'],
            'procedure_id' => null,
        ]);
    }

    public function test_a_record_without_a_procedure_keeps_working(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'specialty' => 'cirugia_oral',
                'patient_id' => $this->patient->id,
                ...$this->specialties()['cirugia_oral']['payload'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.procedure_id', null);

        $this->assertDatabaseHas('oral_surgery_records', [
            'patient_id' => $this->patient->id,
            'procedure_id' => null,
        ]);
    }
}
