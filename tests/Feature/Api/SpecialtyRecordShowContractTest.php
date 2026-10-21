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
 * Read contract for `GET /api/specialty-records/{id}` (issue #247 follow-up).
 *
 * `show()` eager-loaded `dentalPiece` for the five models, but
 * `orthodontics_records` has no `dental_piece_id` column and
 * `OrthodonticsRecord` does not define the relation: a DELETE-free read of an
 * orthodontic record answered 500. The relation is now loaded only where the
 * model defines it.
 */
class SpecialtyRecordShowContractTest extends TestCase
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
            'name' => 'Alineadores',
        ]);
    }

    /**
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function create(array $payload): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', [
                'patient_id' => $this->patient->id,
                ...$payload,
            ])
            ->assertCreated()
            ->json('data');
    }

    public function test_show_returns_an_orthodontic_record_instead_of_a_server_error(): void
    {
        $created = $this->create([
            'specialty' => 'ortodoncia',
            'procedure_id' => $this->procedure->id,
            'treatment_type' => 'brackets',
            'treatment_start_date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/specialty-records/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('data.procedure.id', $this->procedure->id);
    }

    public function test_show_keeps_the_dental_piece_where_the_table_has_it(): void
    {
        $created = $this->create([
            'specialty' => 'endodoncia',
            'dental_piece_id' => $this->piece->id,
            'tooth_number' => '16',
            'canal_count' => 3,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/specialty-records/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('data.dental_piece.id', $this->piece->id);
    }
}
