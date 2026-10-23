<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #320 (T1, S8) — the authorizer lookup of the refund modal (S1).
 *
 * `GET /api/users/active` used to answer only the clinical roles, so the SPA had
 * no way to offer the administrator who authorizes a refund without inventing a
 * permission or a second route. Naming `administrador` narrows the same lookup
 * to the active administrators the actor can see; any other value — including
 * the absent one — keeps the clinical list untouched.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class UserAuthorizersLookupTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private User $administrator;

    private User $inactiveAdministrator;

    private User $foreignAdministrator;

    private User $dentist;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->otherBranch = Branch::factory()->create();

        $this->administrator = $this->user('administrador', $this->branch, true);
        $this->inactiveAdministrator = $this->user('administrador', $this->branch, false);
        $this->foreignAdministrator = $this->user('administrador', $this->otherBranch, true);
        $this->dentist = $this->user('odontologo', $this->branch, true);
        $this->receptionist = $this->user('recepcionista', $this->branch, true);
    }

    private function user(string $role, Branch $branch, bool $active): User
    {
        return User::factory()->create([
            'role' => $role,
            'branch_id' => $branch->id,
            'is_active' => $active,
        ]);
    }

    public function test_a_receptionist_sees_the_active_administrators_of_its_branch(): void
    {
        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/users/active?role=administrador');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->administrator->id)
            ->assertJsonPath('data.0.name', $this->administrator->name)
            ->assertJsonPath('data.0.role', 'administrador')
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(
            ['id', 'name', 'role', 'specialty'],
            collect($response->json('data.0'))->keys()->sort()->values()->all(),
            'The authorizer lookup keeps the shape of the previous clinical list.'
        );
    }

    public function test_the_administrator_lookup_never_offers_clinical_staff(): void
    {
        $roles = collect(
            $this->actingAs($this->receptionist, 'sanctum')
                ->getJson('/api/users/active?role=administrador')
                ->assertOk()
                ->json('data')
        )->pluck('role')->unique()->values()->all();

        $this->assertSame(['administrador'], $roles);
    }

    public function test_the_unfiltered_lookup_keeps_the_clinical_list(): void
    {
        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/users/active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->dentist->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(
            ['odontologo'],
            collect($response->json('data'))->pluck('role')->unique()->values()->all(),
        );
    }

    public function test_any_other_role_value_keeps_the_clinical_list(): void
    {
        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/users/active?role=recepcionista');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->dentist->id);

        $this->assertSame(
            ['odontologo'],
            collect($response->json('data'))->pluck('role')->unique()->values()->all(),
            'Only `administrador` narrows the lookup; every other value is the clinical list.'
        );
    }
}
