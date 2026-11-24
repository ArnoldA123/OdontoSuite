<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * citas-privacidad (T4) — el ambiente pertenece a una sede.
 *
 * El índice acota por la sede efectiva del actor y publica la sede de cada fila;
 * el alta estampa la sede del actor restringido y la exige al alcance de grupo,
 * para que ningún ambiente nuevo quede sin sede; el detalle aplica el mismo
 * alcance de lectura que el binding del modelo. Nombrar otra sede es 403 (la
 * sede autoriza) o 404 en la lectura por id, nunca 500.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class DentalChairBranchTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, ?Branch $branch = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function chair(Branch $branch, string $code): DentalChair
    {
        return DentalChair::factory()->create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => 'Ambiente '.$code,
        ]);
    }

    /**
     * Codes of the returned rows, limited to the fixtures of this class so a
     * seeded database cannot change the assertion.
     *
     * @param  array<int, string> $only
     * @return array<int, string>
     */
    private function listedCodes($response, array $only): array
    {
        $codes = collect($response->json('data'))->pluck('code')->all();
        $listed = array_values(array_intersect($codes, $only));
        sort($listed);

        return $listed;
    }

    public function test_the_index_lists_only_the_actors_branch(): void
    {
        $trujillo = Branch::factory()->create(['name' => 'Trujillo']);
        $lima = Branch::factory()->create(['name' => 'Lima central']);

        $this->chair($trujillo, 'AMB-T4-IDX-1');
        $this->chair($lima, 'AMB-T4-IDX-2');

        $response = $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->getJson('/api/dental-chairs');

        $response->assertOk();
        $this->assertSame(
            ['AMB-T4-IDX-1'],
            $this->listedCodes($response, ['AMB-T4-IDX-1', 'AMB-T4-IDX-2'])
        );
    }

    public function test_group_scope_reads_every_branch_and_can_filter_by_one(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $this->chair($trujillo, 'AMB-T4-GRP-1');
        $this->chair($lima, 'AMB-T4-GRP-2');

        $group = $this->staff('administrador');

        $all = $this->actingAs($group, 'sanctum')->getJson('/api/dental-chairs');
        $all->assertOk();
        $this->assertSame(
            ['AMB-T4-GRP-1', 'AMB-T4-GRP-2'],
            $this->listedCodes($all, ['AMB-T4-GRP-1', 'AMB-T4-GRP-2'])
        );

        $filtered = $this->actingAs($group, 'sanctum')
            ->getJson("/api/dental-chairs?branch_id={$lima->id}");
        $filtered->assertOk();
        $this->assertSame(
            ['AMB-T4-GRP-2'],
            $this->listedCodes($filtered, ['AMB-T4-GRP-1', 'AMB-T4-GRP-2'])
        );
    }

    public function test_naming_a_foreign_branch_in_the_index_is_forbidden(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->getJson("/api/dental-chairs?branch_id={$lima->id}")
            ->assertForbidden();
    }

    public function test_every_row_publishes_its_branch(): void
    {
        $trujillo = Branch::factory()->create(['name' => 'Trujillo', 'code' => 'SC-TRU-01']);
        $chair = $this->chair($trujillo, 'AMB-T4-SHAPE');

        $response = $this->actingAs($this->staff('administrador'), 'sanctum')
            ->getJson('/api/dental-chairs');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $chair->id);

        $this->assertNotNull($row);
        $this->assertSame($trujillo->id, $row['branch_id']);
        $this->assertSame(
            ['id' => $trujillo->id, 'name' => 'Trujillo', 'code' => 'SC-TRU-01'],
            $row['branch']
        );
    }

    public function test_search_shares_the_branch_scope_of_the_index(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $this->chair($trujillo, 'AMB-T4-SRC-1');
        $this->chair($lima, 'AMB-T4-SRC-2');

        $response = $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->getJson('/api/dental-chairs/search?q=AMB-T4-SRC');

        $response->assertOk();
        $this->assertSame(
            ['AMB-T4-SRC-1'],
            $this->listedCodes($response, ['AMB-T4-SRC-1', 'AMB-T4-SRC-2'])
        );
    }

    public function test_show_is_scoped_to_the_actors_branch(): void
    {
        $trujillo = Branch::factory()->create(['name' => 'Trujillo']);
        $lima = Branch::factory()->create(['name' => 'Lima central']);

        $own = $this->chair($trujillo, 'AMB-T4-SHW-1');
        $foreign = $this->chair($lima, 'AMB-T4-SHW-2');

        $restricted = $this->staff('administrador', $trujillo);
        // El actor de grupo se crea antes de cambiar la sesión: nace sin sede y
        // el guard de `ScopesToBranch` del propio `User` ya no lo estampa.
        $group = $this->staff('administrador');

        $this->actingAs($restricted, 'sanctum')
            ->getJson("/api/dental-chairs/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $own->id)
            ->assertJsonPath('data.branch_id', $trujillo->id);

        // La ficha de otra sede no se abre: el mismo alcance de lectura que el
        // binding del modelo responde 404 y no filtra la fila.
        $this->actingAs($restricted, 'sanctum')
            ->getJson("/api/dental-chairs/{$foreign->id}")
            ->assertNotFound();

        // El alcance de grupo sí lee cualquier sede.
        $this->actingAs($group, 'sanctum')
            ->getJson("/api/dental-chairs/{$foreign->id}")
            ->assertOk()
            ->assertJsonPath('data.branch_id', $lima->id)
            ->assertJsonPath('data.branch.name', 'Lima central');
    }

    public function test_store_requires_the_branch_for_group_scope(): void
    {
        $this->actingAs($this->staff('administrador'), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Sin sede',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id'])
            ->assertJsonPath('errors.branch_id.0', 'La sede es obligatoria.');

        $this->assertSame(0, DentalChair::where('name', 'Sin sede')->count());
    }

    public function test_store_rejects_a_branch_that_does_not_exist(): void
    {
        $this->actingAs($this->staff('administrador'), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Sede fantasma',
                'status' => 'active',
                'branch_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_store_creates_with_the_declared_branch_for_group_scope(): void
    {
        $lima = Branch::factory()->create(['name' => 'Lima central']);

        $response = $this->actingAs($this->staff('administrador'), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Consultorio Lima',
                'status' => 'active',
                'branch_id' => $lima->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.branch_id', $lima->id)
            ->assertJsonPath('data.branch.name', 'Lima central');

        $this->assertDatabaseHas('dental_chairs', [
            'id' => $response->json('data.id'),
            'branch_id' => $lima->id,
        ]);
    }

    public function test_store_stamps_the_actors_branch_when_restricted_omits_it(): void
    {
        $trujillo = Branch::factory()->create(['name' => 'Trujillo']);

        $response = $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Consultorio Trujillo',
                'status' => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.branch_id', $trujillo->id)
            ->assertJsonPath('data.branch.name', 'Trujillo');

        $this->assertDatabaseHas('dental_chairs', [
            'id' => $response->json('data.id'),
            'branch_id' => $trujillo->id,
        ]);
    }

    public function test_store_rejects_another_branch_for_a_restricted_actor(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Ambiente prestado',
                'status' => 'active',
                'branch_id' => $lima->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, DentalChair::where('name', 'Ambiente prestado')->count());
    }

    public function test_update_rejects_another_branch_for_a_restricted_actor(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();
        $chair = $this->chair($trujillo, 'AMB-T4-UPD-1');

        $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->putJson("/api/dental-chairs/{$chair->id}", [
                'name' => 'Ambiente movido',
                'status' => 'active',
                'branch_id' => $lima->id,
            ])
            ->assertForbidden();

        $this->assertSame($trujillo->id, $chair->refresh()->branch_id);
        $this->assertSame('Ambiente AMB-T4-UPD-1', $chair->name);
    }

    public function test_update_keeps_the_branch_when_the_payload_does_not_send_it(): void
    {
        $trujillo = Branch::factory()->create();
        $chair = $this->chair($trujillo, 'AMB-T4-UPD-2');

        $response = $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->putJson("/api/dental-chairs/{$chair->id}", ['is_active' => false]);

        $response->assertOk()
            ->assertJsonPath('data.branch_id', $trujillo->id);

        $this->assertSame($trujillo->id, $chair->refresh()->branch_id);
        $this->assertFalse($chair->is_active);
    }

    public function test_update_rejects_a_null_branch_so_no_environment_loses_its_sede(): void
    {
        $trujillo = Branch::factory()->create();
        $chair = $this->chair($trujillo, 'AMB-T4-UPD-3');

        $this->actingAs($this->staff('administrador'), 'sanctum')
            ->putJson("/api/dental-chairs/{$chair->id}", ['branch_id' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);

        $this->assertSame($trujillo->id, $chair->refresh()->branch_id);
    }
}
