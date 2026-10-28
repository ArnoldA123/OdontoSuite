<?php

namespace Tests\Feature\Api;

use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #433 — el filtro «Inactivos» del índice devuelve su propia población.
 *
 * El parámetro llega como cadena (`is_active=false`) y `(bool) 'false'` es
 * `true`, así que las dos ramas pedían la población activa. La regla del índice
 * es la del resto de sus filtros: cada valor devuelve lo suyo y la ausencia no
 * filtra.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureIndexActiveFilterTest extends TestCase
{
    use RefreshDatabase;

    private function procedure(string $code, bool $active): ProcedureCatalog
    {
        return ProcedureCatalog::factory()->create([
            'code' => $code,
            'name' => 'Procedimiento '.$code,
            'is_active' => $active,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function indexRows(string $query = '')
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
            'branch_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/procedure-catalog?per_page=100'.$query);

        $response->assertOk();

        return collect($response->json('data'));
    }

    public function test_false_returns_only_the_inactive_procedures(): void
    {
        $active = $this->procedure('ACT-'.substr(uniqid(), -8), true);
        $inactive = $this->procedure('INA-'.substr(uniqid(), -8), false);

        $rows = $this->indexRows('&is_active=false');

        $this->assertTrue($rows->contains('id', $inactive->id));
        $this->assertFalse($rows->contains('id', $active->id));
        $this->assertTrue($rows->every(fn (array $row) => $row['is_active'] === false));
    }

    public function test_true_returns_only_the_active_procedures(): void
    {
        $active = $this->procedure('ACT-'.substr(uniqid(), -8), true);
        $inactive = $this->procedure('INA-'.substr(uniqid(), -8), false);

        $rows = $this->indexRows('&is_active=true');

        $this->assertTrue($rows->contains('id', $active->id));
        $this->assertFalse($rows->contains('id', $inactive->id));
        $this->assertTrue($rows->every(fn (array $row) => $row['is_active'] === true));
    }

    public function test_without_the_filter_both_populations_are_returned(): void
    {
        $active = $this->procedure('ACT-'.substr(uniqid(), -8), true);
        $inactive = $this->procedure('INA-'.substr(uniqid(), -8), false);

        $rows = $this->indexRows();

        $this->assertTrue($rows->contains('id', $active->id));
        $this->assertTrue($rows->contains('id', $inactive->id));
    }
}
