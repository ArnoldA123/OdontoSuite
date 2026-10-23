<?php

namespace Tests\Feature\Permissions;

use App\Authorization\Permissions;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * #280 — the SPA derives its flags from this payload, so it must carry the
 * effective permission list for the authenticated role.
 */
class AuthPermissionsPayloadTest extends TestCase
{
    #[Test]
    public function me_returns_the_effective_permissions_of_the_role(): void
    {
        Sanctum::actingAs(new User(['name' => 'Recepción', 'role' => 'recepcionista']));

        $response = $this->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.permissions', Permissions::forRole('recepcionista'));
        $response->assertJsonMissingPath('data.permissions.0.administrador');
    }

    #[Test]
    public function reception_payload_does_not_include_the_consultation_close(): void
    {
        Sanctum::actingAs(new User(['name' => 'Recepción', 'role' => 'recepcionista']));

        $permissions = $this->getJson('/api/auth/me')->json('data.permissions');

        $this->assertNotContains(Permissions::COMPLETE_APPOINTMENT, $permissions);
        $this->assertContains(Permissions::CHECK_IN_APPOINTMENT, $permissions);
        $this->assertContains(Permissions::VIEW_PATIENT, $permissions);
    }

    #[Test]
    public function unknown_role_receives_an_empty_permission_list(): void
    {
        Sanctum::actingAs(new User(['name' => 'Legacy', 'role' => 'rol_inexistente']));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.permissions', []);
    }

    #[Test]
    public function me_publishes_the_branch_affiliation(): void
    {
        // #305: the branch dimension travels with the session. The SPA tells a
        // sede administrator from gerencia by this field, which is also the
        // sede it excludes from the transfer destinations.
        Sanctum::actingAs(new User(['name' => 'Sede', 'role' => 'administrador', 'branch_id' => 4]));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.branch_id', 4);
    }

    #[Test]
    public function group_scope_is_published_as_a_null_branch(): void
    {
        Sanctum::actingAs(new User(['name' => 'Gerencia', 'role' => 'administrador', 'branch_id' => null]));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.branch_id', null);
    }
}
