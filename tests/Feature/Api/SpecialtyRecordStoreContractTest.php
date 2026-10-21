<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end write contract for the five specialty records (issue #212).
 *
 * The redesigned interface creates one record per specialty, so every POST the
 * form can send must succeed. Two rules were broken before this test existed:
 * `rehabilitacion` required a `restoration_type` column that does not exist and
 * `cirugia_oral` required a `surgery_date` column that does not exist, while
 * implantology, endodontics and rehabilitation skipped the NOT NULL
 * `dental_piece_id`.
 *
 * Each case posts the minimal payload the form produces (piece selector
 * included) and asserts the row landed in its own table.
 */
class SpecialtyRecordStoreContractTest extends TestCase
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
     * @param array<string, mixed> $payload
     */
    private function create(array $payload): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'patient_id' => $this->patient->id,
                ...$payload,
            ])
            ->assertCreated();
    }

    public function test_implantology_accepts_the_dental_piece_and_its_columns(): void
    {
        $this->create([
            'specialty' => 'implantologia',
            'dental_piece_id' => $this->piece->id,
            'implant_brand' => 'Nobel Biocare',
            'implant_model' => 'Replace Select',
            'implant_diameter' => '4.1',
            'implant_length' => '10.0',
            'batch_number' => 'NB-1000',
            'placement_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertDatabaseHas('implantology_records', [
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'implant_brand' => 'Nobel Biocare',
        ]);
    }

    public function test_orthodontics_accepts_its_treatment_columns(): void
    {
        $this->create([
            'specialty' => 'ortodoncia',
            'treatment_type' => 'brackets',
            'treatment_start_date' => now()->subMonth()->toDateString(),
        ]);

        $this->assertDatabaseHas('orthodontics_records', [
            'patient_id' => $this->patient->id,
            'treatment_type' => 'brackets',
        ]);
    }

    public function test_endodontics_accepts_the_tooth_and_the_dental_piece(): void
    {
        $this->create([
            'specialty' => 'endodoncia',
            'dental_piece_id' => $this->piece->id,
            'tooth_number' => '16',
            'canal_count' => 3,
        ]);

        $this->assertDatabaseHas('endodontics_records', [
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'tooth_number' => '16',
        ]);
    }

    public function test_rehabilitation_accepts_prosthesis_and_material(): void
    {
        $this->create([
            'specialty' => 'rehabilitacion',
            'dental_piece_id' => $this->piece->id,
            'prosthesis_type' => 'crown',
            'material_type' => 'zirconia',
        ]);

        $this->assertDatabaseHas('rehabilitation_records', [
            'patient_id' => $this->patient->id,
            'prosthesis_type' => 'crown',
            'material_type' => 'zirconia',
        ]);
    }

    public function test_oral_surgery_accepts_the_site_instead_of_a_missing_surgery_date(): void
    {
        $this->create([
            'specialty' => 'cirugia_oral',
            'procedure_type' => 'extraction',
            'surgery_site' => 'Tercer molar inferior izquierdo',
        ]);

        $this->assertDatabaseHas('oral_surgery_records', [
            'patient_id' => $this->patient->id,
            'procedure_type' => 'extraction',
            'surgery_site' => 'Tercer molar inferior izquierdo',
        ]);
    }

    public function test_a_missing_dental_piece_is_rejected_where_the_schema_requires_it(): void
    {
        foreach (['implantologia', 'endodoncia', 'rehabilitacion'] as $specialty) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson('/api/specialty-records', [
                    'specialty' => $specialty,
                    'patient_id' => $this->patient->id,
                    'implant_brand' => 'X',
                    'implant_model' => 'X',
                    'implant_diameter' => '4.0',
                    'implant_length' => '10',
                    'batch_number' => 'L1',
                    'placement_date' => now()->subDay()->toDateString(),
                    'tooth_number' => '16',
                    'canal_count' => 2,
                    'prosthesis_type' => 'crown',
                    'material_type' => 'zirconia',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('dental_piece_id');
        }

        foreach (['implantology_records', 'endodontics_records', 'rehabilitation_records'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
