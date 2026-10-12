<?php

namespace Tests\Feature\Api;

use App\Models\AppointmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (P1) for the explicit `status` filter on GET /api/appointment-types.
 *
 * Acceptance:
 *  - absent/invalid `status` -> only active types (backwards compatible default)
 *  - `status=inactive`       -> only inactive types
 *  - `status=all`            -> active + inactive
 *  - unknown value never 500s; it falls back to `active`
 */
class AppointmentTypeIndexStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    /**
     * Two active types and one inactive type.
     *
     * @return array{active: array<int, int>, inactive: array<int, int>}
     */
    private function fixture(): array
    {
        $active = AppointmentType::factory()->count(2)->create(['is_active' => true]);
        $inactive = AppointmentType::factory()->count(1)->create(['is_active' => false]);

        return [
            'active' => $active->pluck('id')->all(),
            'inactive' => $inactive->pluck('id')->all(),
        ];
    }

    public function test_index_without_status_returns_only_active_types(): void
    {
        $admin = $this->admin();
        $fixture = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/appointment-types');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);

        $this->assertEqualsCanonicalizing(
            $fixture['active'],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertEquals(count($fixture['active']), $response->json('meta.total'));
    }

    public function test_index_with_status_inactive_returns_only_inactive_types(): void
    {
        $admin = $this->admin();
        $fixture = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/appointment-types?status=inactive');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);

        $this->assertEqualsCanonicalizing(
            $fixture['inactive'],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertEquals(count($fixture['inactive']), $response->json('meta.total'));
    }

    public function test_index_with_status_all_returns_active_and_inactive_types(): void
    {
        $admin = $this->admin();
        $fixture = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/appointment-types?status=all');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);

        $this->assertEqualsCanonicalizing(
            array_merge($fixture['active'], $fixture['inactive']),
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertEquals(
            count($fixture['active']) + count($fixture['inactive']),
            $response->json('meta.total')
        );
    }

    public function test_index_with_invalid_status_falls_back_to_active_without_error(): void
    {
        $admin = $this->admin();
        $fixture = $this->fixture();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/appointment-types?status=bogus');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);

        $this->assertEqualsCanonicalizing(
            $fixture['active'],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertEquals(count($fixture['active']), $response->json('meta.total'));
    }
}
