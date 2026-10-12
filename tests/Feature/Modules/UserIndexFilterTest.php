<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2 — Professionals Interface Redesign (odd/tasks/professionals-interface-redesign.md).
 *
 * Contract for `GET /api/users` (UserController@index) role filtering as the
 * single data source of the clinical team list (D2/D9):
 *   - `role` accepts a comma-separated list of roles
 *   - a single `role` value keeps today's exact behavior
 *   - no `role` returns every user
 *   - unknown role values return `{data: [], meta: {total: 0}}` with 200
 *   - the envelope stays exactly `{data: [...], meta: {total: n}}`
 *
 * Run against the dev MySQL harness (phpunit.mysql.xml), the canonical oracle
 * for this suite (AGENTS.md section 6).
 */
class UserIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private const CLINICAL_ROLES = ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'];

    private function makeUser(string $role): User
    {
        $suffix = uniqid();

        return User::create([
            'name' => "User {$suffix}",
            'email' => "uif.{$suffix}@example.com",
            'username' => "uif_{$suffix}",
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return $this->makeUser('administrador');
    }

    private function makeTeamFixtures(): array
    {
        return [
            'odontologo_a' => $this->makeUser('odontologo'),
            'odontologo_b' => $this->makeUser('odontologo'),
            'implantologo_a' => $this->makeUser('implantologo'),
            'implantologo_b' => $this->makeUser('implantologo'),
            'tecnico_a' => $this->makeUser('tecnico_dental'),
            'asistente_a' => $this->makeUser('asistente'),
            'admin_a' => $this->makeUser('administrador'),
        ];
    }

    private function responseIds(User $admin, string $query): array
    {
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/users{$query}");

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);

        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame(
            count($response->json('data')),
            $response->json('meta.total'),
            'meta.total must equal the number of rows in data.'
        );

        return $ids;
    }

    private function expectedIdsByRoles(array $roles): array
    {
        return User::all()
            ->filter(fn (User $user) => in_array($user->role, $roles, true))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    public function test_index_with_comma_separated_role_list_returns_exactly_those_roles(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $ids = $this->responseIds($admin, '?role=odontologo,implantologo');

        $this->assertSame($this->expectedIdsByRoles(['odontologo', 'implantologo']), $ids);
    }

    public function test_index_with_single_role_keeps_backward_compatible_behavior(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $ids = $this->responseIds($admin, '?role=odontologo');

        $this->assertSame($this->expectedIdsByRoles(['odontologo']), $ids);
    }

    public function test_index_without_role_returns_all_users(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $ids = $this->responseIds($admin, '');

        $this->assertSame(User::pluck('id')->sort()->values()->all(), $ids);
    }

    public function test_unknown_role_returns_empty_data_and_zero_total(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users?role=rol_inexistente');

        $response->assertOk()
            ->assertJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    public function test_response_envelope_stays_data_and_meta_total(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users?role=odontologo,implantologo');

        $response->assertOk();

        $this->assertSame(['data', 'meta'], array_keys($response->json()));
        $this->assertSame(['total'], array_keys($response->json('meta')));
    }

    public function test_role_list_with_spaces_and_empty_entries_is_tolerated(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $ids = $this->responseIds($admin, '?role=odontologo,%20implantologo%20,,%20');

        $this->assertSame($this->expectedIdsByRoles(['odontologo', 'implantologo']), $ids);
    }

    public function test_known_roles_alongside_unknown_ones_still_filter(): void
    {
        $admin = $this->admin();
        $this->makeTeamFixtures();

        $ids = $this->responseIds($admin, '?role=odontologo,rol_inexistente');

        $this->assertSame($this->expectedIdsByRoles(['odontologo']), $ids);
    }
}
