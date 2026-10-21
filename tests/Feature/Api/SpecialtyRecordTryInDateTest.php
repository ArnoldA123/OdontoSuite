<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DentalPieceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Write and read contract for the rehabilitation try-in date (issue #246,
 * related note).
 *
 * The `try_in` status existed in the enum from the first migration, but no
 * column recorded when the try-in happened, so the state could not be placed
 * on a timeline.
 */
class SpecialtyRecordTryInDateTest extends TestCase
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
    private function payload(array $overrides = []): array
    {
        return [
            'specialty' => 'rehabilitacion',
            'patient_id' => $this->patient->id,
            'dental_piece_id' => $this->piece->id,
            'prosthesis_type' => 'crown',
            'material_type' => 'zirconia',
            ...$overrides,
        ];
    }

    public function test_store_persists_the_try_in_date(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload([
                'status' => 'try_in',
                'try_in_date' => '2026-06-18',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.try_in_date', fn ($value) => str_starts_with($value, '2026-06-18'));

        $this->assertDatabaseHas('rehabilitation_records', [
            'patient_id' => $this->patient->id,
            'try_in_date' => '2026-06-18',
        ]);
    }

    public function test_the_listing_returns_the_try_in_date(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload([
                'try_in_date' => '2026-06-18',
            ]))
            ->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/specialty-records?specialty=rehabilitacion&patient_id='.$this->patient->id)
            ->assertOk()
            ->assertJsonPath('data.0.try_in_date', fn ($value) => str_starts_with($value, '2026-06-18'));
    }

    public function test_update_persists_the_try_in_date(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload())
            ->assertCreated()
            ->json('data');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.$created['id'], [
                'specialty' => 'rehabilitacion',
                'try_in_date' => '2026-07-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.try_in_date', fn ($value) => str_starts_with($value, '2026-07-02'));

        $this->assertDatabaseHas('rehabilitation_records', [
            'id' => $created['id'],
            'try_in_date' => '2026-07-02',
        ]);
    }

    public function test_a_non_date_try_in_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload([
                'try_in_date' => 'segunda semana',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('try_in_date');

        $this->assertDatabaseCount('rehabilitation_records', 0);
    }
}
