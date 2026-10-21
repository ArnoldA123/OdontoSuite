<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Write and read contract for the oral surgery date (issue #246).
 *
 * `oral_surgery_records` had no date column, so the listing ordered the
 * clinical act by `created_at` — the capture time — and the status enum could
 * not be checked against a real date. These cases pin persistence, validation,
 * ordering and the update path.
 */
class SpecialtyRecordSurgeryDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->patient = Patient::factory()->create();
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'specialty' => 'cirugia_oral',
            'patient_id' => $this->patient->id,
            'procedure_type' => 'extraction',
            'surgery_site' => 'Tercer molar inferior izquierdo',
            ...$overrides,
        ];
    }

    public function test_store_persists_the_surgery_date(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload([
                'surgery_date' => '2026-05-04',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.surgery_date', fn ($value) => str_starts_with($value, '2026-05-04'));

        $this->assertDatabaseHas('oral_surgery_records', [
            'patient_id' => $this->patient->id,
            'surgery_date' => '2026-05-04',
        ]);
    }

    public function test_a_future_surgery_date_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload([
                'surgery_date' => now()->addDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('surgery_date');

        $this->assertDatabaseCount('oral_surgery_records', 0);
    }

    public function test_update_persists_the_surgery_date(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload())
            ->assertCreated()
            ->json('data');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/specialty-records/'.$created['id'], [
                'specialty' => 'cirugia_oral',
                'surgery_date' => '2026-04-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.surgery_date', fn ($value) => str_starts_with($value, '2026-04-02'));

        $this->assertDatabaseHas('oral_surgery_records', [
            'id' => $created['id'],
            'surgery_date' => '2026-04-02',
        ]);
    }

    public function test_the_listing_orders_oral_surgery_by_the_surgery_date(): void
    {
        // Insertion order is January, March, February, plus a record without
        // a date. Ordering by `created_at` would answer February, March,
        // January; only the surgical date produces March, February, January.
        $january = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload(['surgery_date' => '2026-01-10']))
            ->assertCreated()
            ->json('data.id');

        $march = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload(['surgery_date' => '2026-03-15']))
            ->assertCreated()
            ->json('data.id');

        $february = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload(['surgery_date' => '2026-02-01']))
            ->assertCreated()
            ->json('data.id');

        $undated = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload())
            ->assertCreated()
            ->json('data.id');

        $ids = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/specialty-records?specialty=cirugia_oral&patient_id='.$this->patient->id)
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$march, $february, $january, $undated], $ids);
    }

    public function test_a_record_without_a_surgery_date_keeps_working(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/specialty-records', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.surgery_date', null);

        $this->assertDatabaseHas('oral_surgery_records', [
            'patient_id' => $this->patient->id,
            'surgery_date' => null,
        ]);
    }
}
