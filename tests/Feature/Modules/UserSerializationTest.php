<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T6 — professionals-functionality-audit (odd/tasks/professionals-functionality-audit.md).
 *
 * Polishing contract (S3):
 *   - `GET /api/users/{id}` is shaped by UserResource, so the payload carries
 *     the `specialties` pivot (ADR-0007 source of truth) instead of the raw
 *     model dump
 *   - `GET /api/users/active` only offers roles the application authorizes
 *     (`dentista` is not one of them)
 */
class UserSerializationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador']);
    }

    public function test_show_shapes_the_payload_through_the_user_resource(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->create([
            'role' => 'implantologo',
            'specialty' => 'implantology',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/users/{$professional->id}");

        $response->assertOk();

        $data = $response->json('data');

        $this->assertArrayHasKey('specialties', $data);
        $this->assertSame([], $data['specialties']);
        $this->assertSame('implantologo', $data['role']);
        $this->assertTrue($data['is_active']);
    }

    public function test_active_professionals_endpoint_excludes_retired_roles(): void
    {
        $admin = $this->admin();
        $retired = User::factory()->create(['role' => 'dentista', 'is_active' => true]);
        $clinical = User::factory()->create(['role' => 'asistente', 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users/active');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($clinical->id, $ids);
        $this->assertNotContains($retired->id, $ids);
    }
}
