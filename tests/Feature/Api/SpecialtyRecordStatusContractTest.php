<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status contract of the five specialty records.
 *
 * Every status column is NOT NULL with a database default. The store rules used
 * to accept `nullable`, so a client that sent `status: null` (the form did it
 * for an untouched select) reached the INSERT and answered 500 with an
 * integrity violation. A missing status must keep the default; an explicit null
 * must be rejected as validation.
 */
class SpecialtyRecordStatusContractTest extends TestCase
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
     * @return array<string, array{specialty: string, table: string, field: string, default: string, payload: array<string, mixed>}>
     */
    private function specialties(): array
    {
        return [
            'implantologia' => [
                'specialty' => 'implantologia',
                'table' => 'implantology_records',
                'field' => 'status',
                'default' => 'placed',
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
                'field' => 'treatment_phase',
                'default' => 'initial',
                'payload' => [
                    'treatment_type' => 'brackets',
                    'treatment_start_date' => now()->subMonth()->toDateString(),
                ],
            ],
            'endodoncia' => [
                'specialty' => 'endodoncia',
                'table' => 'endodontics_records',
                'field' => 'treatment_status',
                'default' => 'in_progress',
                'payload' => [
                    'dental_piece_id' => $this->piece->id,
                    'tooth_number' => '16',
                    'canal_count' => 3,
                ],
            ],
            'rehabilitacion' => [
                'specialty' => 'rehabilitacion',
                'table' => 'rehabilitation_records',
                'field' => 'status',
                'default' => 'impression',
                'payload' => [
                    'dental_piece_id' => $this->piece->id,
                    'prosthesis_type' => 'crown',
                    'material_type' => 'zirconia',
                ],
            ],
            'cirugia_oral' => [
                'specialty' => 'cirugia_oral',
                'table' => 'oral_surgery_records',
                'field' => 'status',
                'default' => 'scheduled',
                'payload' => [
                    'procedure_type' => 'extraction',
                    'surgery_site' => 'Tercer molar inferior izquierdo',
                ],
            ],
        ];
    }

    public function test_an_untouched_status_keeps_the_database_default(): void
    {
        foreach ($this->specialties() as $case) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson('/api/specialty-records', [
                    'specialty' => $case['specialty'],
                    'patient_id' => $this->patient->id,
                    ...$case['payload'],
                ])
                ->assertCreated();

            $this->assertDatabaseHas($case['table'], [
                'patient_id' => $this->patient->id,
                $case['field'] => $case['default'],
            ]);
        }
    }

    public function test_an_explicit_null_status_is_rejected_instead_of_failing_the_insert(): void
    {
        foreach ($this->specialties() as $case) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson('/api/specialty-records', [
                    'specialty' => $case['specialty'],
                    'patient_id' => $this->patient->id,
                    $case['field'] => null,
                    ...$case['payload'],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors($case['field']);

            $this->assertDatabaseCount($case['table'], 0);
        }
    }

    public function test_update_rejects_an_explicit_null_status_and_keeps_the_stored_one(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'specialty' => 'cirugia_oral',
                'patient_id' => $this->patient->id,
                ...$this->specialties()['cirugia_oral']['payload'],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.$created['id'], [
                'specialty' => 'cirugia_oral',
                'status' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.$created['id'], [
                'specialty' => 'cirugia_oral',
                'surgery_site' => 'Sitio corregido',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('oral_surgery_records', [
            'id' => $created['id'],
            'status' => 'scheduled',
            'surgery_site' => 'Sitio corregido',
        ]);
    }
}
