<?php

namespace Tests\Feature\Modules;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T1 — professionals-functionality-audit (odd/tasks/professionals-functionality-audit.md).
 *
 * Contract for `POST /api/users` and `PUT /api/users/{id}`:
 *   - every role the application actually uses can be created (S4: "role
 *     whitelist = 7 roles reales")
 *   - role names from the retired vocabulary are rejected (`admin`, `recepcion`,
 *     `dentista` never existed in `routes/api.php` role lists)
 *   - professional_license, branch_id, commission_rate and is_active are
 *     persisted instead of being silently dropped by `validate()` (B4)
 *   - updating a professional whose role is clinical keeps working (B2)
 */
class UserProfileMutationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador']);
    }

    private function payload(array $overrides = []): array
    {
        $suffix = uniqid();

        return array_merge([
            'name' => "Profesional {$suffix}",
            'username' => "prof_{$suffix}",
            'email' => "prof_{$suffix}@example.com",
            'phone' => '+51 987 654 000',
            'password' => 'password123',
            'role' => 'odontologo',
        ], $overrides);
    }

    public function test_every_application_role_can_be_created(): void
    {
        $admin = $this->admin();

        $roles = [
            'administrador',
            'recepcionista',
            'finanzas',
            'odontologo',
            'implantologo',
            'tecnico_dental',
            'asistente',
        ];

        foreach ($roles as $role) {
            $response = $this->actingAs($admin, 'sanctum')->postJson('/api/users', $this->payload([
                'role' => $role,
                'username' => "nuevo_{$role}",
                'email' => "nuevo_{$role}@example.com",
            ]));

            $response->assertCreated();
            $this->assertSame($role, $response->json('data.role'), "Role {$role} was not persisted.");
        }
    }

    public function test_role_names_outside_the_application_vocabulary_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (['admin', 'recepcion', 'dentista'] as $retired) {
            $response = $this->actingAs($admin, 'sanctum')->postJson('/api/users', $this->payload([
                'role' => $retired,
                'username' => "retired_{$retired}",
                'email' => "retired_{$retired}@example.com",
            ]));

            $response->assertStatus(422);
            $this->assertArrayHasKey('role', $response->json('errors'));
            $this->assertDatabaseMissing('users', ['username' => "retired_{$retired}"]);
        }
    }

    public function test_creating_a_professional_persists_licence_branch_commission_and_status(): void
    {
        $admin = $this->admin();
        $branch = Branch::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/users', $this->payload([
            'professional_license' => 'COP-12345',
            'branch_id' => $branch->id,
            'commission_rate' => 15,
            'is_active' => false,
        ]));

        $response->assertCreated();

        $created = $response->json('data');

        $this->assertSame('COP-12345', $created['professional_license']);
        $this->assertSame($branch->id, $created['branch_id']);
        $this->assertSame('15.00', $created['commission_rate']);
        $this->assertFalse($created['is_active']);
    }

    public function test_updating_a_professional_keeps_its_clinical_role(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->create([
            'role' => 'implantologo',
            'specialty' => 'implantology',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$professional->id}", [
            'name' => 'Nombre Corregido',
            'username' => $professional->username,
            'email' => $professional->email,
            'phone' => $professional->phone,
            'specialty' => 'implantology',
            'role' => 'implantologo',
        ]);

        $response->assertOk();
        $this->assertSame('implantologo', $response->json('data.role'));
        $this->assertSame('Nombre Corregido', $professional->fresh()->name);
    }

    public function test_updating_persists_licence_branch_commission_and_status(): void
    {
        $admin = $this->admin();
        $branch = Branch::factory()->create();
        $professional = User::factory()->create(['role' => 'odontologo']);

        $update = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$professional->id}", [
            'professional_license' => 'COP-98765',
            'branch_id' => $branch->id,
            'commission_rate' => 12.5,
            'is_active' => false,
        ]);

        $update->assertOk();

        $reread = $this->actingAs($admin, 'sanctum')->getJson("/api/users/{$professional->id}");
        $reread->assertOk();

        $this->assertSame('COP-98765', $reread->json('data.professional_license'));
        $this->assertSame($branch->id, $reread->json('data.branch_id'));
        $this->assertSame('12.50', $reread->json('data.commission_rate'));
        $this->assertFalse((bool) $reread->json('data.is_active'));
    }

    public function test_update_rejects_a_branch_that_does_not_exist(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->create(['role' => 'odontologo']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$professional->id}", [
            'branch_id' => 999999,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('branch_id', $response->json('errors'));
        $this->assertNull($professional->fresh()->branch_id);
    }

    public function test_update_rejects_a_commission_above_one_hundred(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->create(['role' => 'odontologo']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$professional->id}", [
            'commission_rate' => 150,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('commission_rate', $response->json('errors'));
        $this->assertSame('0.00', $professional->fresh()->commission_rate);
    }
}
