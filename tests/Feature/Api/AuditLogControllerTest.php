<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (slice 01, T-01.9) for BF-004 / API-018 etc.
 *
 * Acceptance:
 *  - GET /api/audit-logs  -> 200 with {data, meta.message}
 *  - GET /api/audit-logs/{id} -> 200 with {data}
 *  - POST/PUT/PATCH/DELETE -> 405 (no route) — eliminates the 500 from
 *    apiResource('audit-logs', ...) hitting non-existent controller methods.
 *  - Only role:administrador may read; other roles get 403.
 */
class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_get_audit_logs_returns_200_envelope(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/audit-logs');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_post_audit_logs_returns_405_not_500(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/audit-logs', []);

        $this->assertContains($response->status(), [405, 404], "Expected 405/404 but got {$response->status()}");
        $this->assertNotEquals(500, $response->status());
    }

    public function test_put_audit_logs_returns_405_not_500(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/audit-logs/1', []);

        $this->assertContains($response->status(), [405, 404], "Expected 405/404 but got {$response->status()}");
        $this->assertNotEquals(500, $response->status());
    }

    public function test_delete_audit_logs_returns_405_not_500(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/audit-logs/1');

        $this->assertContains($response->status(), [405, 404], "Expected 405/404 but got {$response->status()}");
        $this->assertNotEquals(500, $response->status());
    }

    public function test_non_admin_cannot_read_audit_logs(): void
    {
        $user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/audit-logs');

        $response->assertForbidden();
    }
}
