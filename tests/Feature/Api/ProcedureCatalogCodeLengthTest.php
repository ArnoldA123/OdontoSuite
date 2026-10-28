<?php

namespace Tests\Feature\Api;

use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #434 — the code contract is the column, not the request.
 *
 * `procedure_catalog.code` is `varchar(20)` while the two write doors accepted
 * up to 50 characters, so a longer code reached the database and the endpoint
 * answered 500. The single contract is 20 characters, declared by the API with
 * a 422 that names the limit.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureCatalogCodeLengthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum');
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => str_repeat('A', 20),
            'name' => 'Procedimiento con código largo',
            'default_cost' => 100,
            'default_duration_minutes' => 30,
        ], $overrides);
    }

    public function test_a_code_longer_than_the_column_is_rejected_with_422(): void
    {
        $response = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => str_repeat('B', 21),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath('errors.code.0', 'El codigo no puede exceder 20 caracteres.');

        $this->assertDatabaseMissing('procedure_catalog', [
            'code' => str_repeat('B', 21),
        ]);
        $this->assertSame(0, ProcedureCatalog::query()->count());
    }

    public function test_a_code_with_the_exact_column_length_is_accepted(): void
    {
        $response = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => str_repeat('C', 20),
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.code', str_repeat('C', 20));

        $this->assertDatabaseHas('procedure_catalog', [
            'code' => str_repeat('C', 20),
        ]);
    }

    public function test_updating_to_a_code_longer_than_the_column_is_rejected_with_422(): void
    {
        $procedure = ProcedureCatalog::create([
            'code' => 'ORIG-20',
            'name' => 'Procedimiento original',
            'default_cost' => 50,
            'default_duration_minutes' => 30,
        ]);

        $response = $this->putJson("/api/procedure-catalog/{$procedure->id}", [
            'code' => str_repeat('D', 21),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath('errors.code.0', 'El codigo no puede exceder 20 caracteres.');

        $this->assertDatabaseHas('procedure_catalog', [
            'id' => $procedure->id,
            'code' => 'ORIG-20',
        ]);
    }
}
