<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * citas-privacidad (T4) — el selector de profesionales del alta de cita.
 *
 * `GET /api/users/active` publica solo los profesionales de la sede efectiva del
 * actor: recepción y administración de Trujillo reciben Trujillo, el alcance de
 * grupo recibe todos o los de la sede que nombre. El payload conserva su forma y
 * suma `branch_id`, que es lo que permite contrastar la sede en el cliente.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class UserActiveBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, ?Branch $branch = null, string $suffix = ''): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
            'email' => 'active.branch'.($suffix === '' ? '' : '.'.$suffix).'@example.com',
        ]);
    }

    /**
     * Ids of the returned users, limited to the fixtures of this class.
     *
     * @param  array<int, int> $only
     * @return array<int, int>
     */
    private function listedIds($response, array $only): array
    {
        $ids = collect($response->json('data'))->pluck('id')->all();
        $listed = array_values(array_intersect($ids, $only));
        sort($listed);

        return $listed;
    }

    public function test_a_restricted_actor_receives_only_the_professionals_of_their_branch(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $own = $this->staff('odontologo', $trujillo, 'own');
        $foreign = $this->staff('odontologo', $lima, 'foreign');
        // Un profesional sin sede es dato heredado: queda fuera del alcance de
        // una sesión de sede y la migración de datos lo rellena.
        $unassigned = $this->staff('implantologo', null, 'unassigned');

        $response = $this->actingAs($this->staff('recepcionista', $trujillo), 'sanctum')
            ->getJson('/api/users/active');

        $response->assertOk();
        $this->assertSame(
            [$own->id],
            $this->listedIds($response, [$own->id, $foreign->id, $unassigned->id])
        );
    }

    public function test_a_restricted_actor_cannot_ask_for_another_branch(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $this->actingAs($this->staff('administrador', $trujillo), 'sanctum')
            ->getJson("/api/users/active?branch_id={$lima->id}")
            ->assertForbidden();
    }

    public function test_group_scope_receives_every_professional(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $first = $this->staff('odontologo', $trujillo, 'first');
        $second = $this->staff('odontologo', $lima, 'second');

        $response = $this->actingAs($this->staff('administrador'), 'sanctum')
            ->getJson('/api/users/active');

        $response->assertOk();
        $this->assertSame(
            [$first->id, $second->id],
            $this->listedIds($response, [$first->id, $second->id])
        );
    }

    public function test_group_scope_can_narrow_the_lookup_to_one_branch(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $first = $this->staff('odontologo', $trujillo, 'first');
        $second = $this->staff('odontologo', $lima, 'second');

        $response = $this->actingAs($this->staff('administrador'), 'sanctum')
            ->getJson("/api/users/active?branch_id={$lima->id}");

        $response->assertOk();
        $this->assertSame(
            [$second->id],
            $this->listedIds($response, [$first->id, $second->id])
        );
    }

    public function test_the_payload_publishes_the_branch_of_every_professional(): void
    {
        $trujillo = Branch::factory()->create();
        $professional = $this->staff('odontologo', $trujillo, 'shape');

        $response = $this->actingAs($this->staff('administrador'), 'sanctum')
            ->getJson('/api/users/active');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $professional->id);

        $this->assertNotNull($row);
        $this->assertSame($trujillo->id, $row['branch_id']);
        // La forma del payload no cambia: los tres campos de siempre siguen.
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('role', $row);
        $this->assertArrayHasKey('specialty', $row);
    }

    public function test_the_authorizer_lookup_keeps_its_role_filter_inside_the_branch(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $ownAdmin = $this->staff('administrador', $trujillo, 'own-admin');
        $foreignAdmin = $this->staff('administrador', $lima, 'foreign-admin');

        $response = $this->actingAs($this->staff('finanzas', $trujillo), 'sanctum')
            ->getJson('/api/users/active?role=administrador');

        $response->assertOk();
        $this->assertSame(
            [$ownAdmin->id],
            $this->listedIds($response, [$ownAdmin->id, $foreignAdmin->id])
        );
    }
}
