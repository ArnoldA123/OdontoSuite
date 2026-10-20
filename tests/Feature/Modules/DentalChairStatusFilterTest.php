<?php

namespace Tests\Feature\Modules;

use App\Models\DentalChair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * environments-ui-redesign / T1: `status` query filter on GET /api/dental-chairs.
 *
 * Contract:
 * - `status` is optional. When present it MUST be one of
 *   `active|inactive|maintenance`; any other present value (including an empty
 *   one) returns HTTP 422 with the standard Laravel validation error body.
 * - When `status` is present and valid, only rows with that `status` are listed.
 * - Without `status`, the default listing is unchanged: every `is_active = true`
 *   row across all statuses. The `is_active = true` constraint ALWAYS applies,
 *   so soft-archived rows are never listed, not even when their status matches
 *   the filter.
 *
 * Assertions are membership-based on explicitly coded fixtures so seeded rows
 * from sibling suites cannot make the contract pass or fail by accident.
 *
 * Runs against the MySQL harness (RefreshDatabase rebuilds every table):
 *   DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_USERNAME=root DB_PASSWORD=<from .env> \
 *     php artisan test --configuration=phpunit.mysql.xml --filter=DentalChair
 */
class DentalChairStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // Direct create: User::factory() is blocked against MySQL because the
        // default factory does not emit `username` (MySQL users.username is
        // NOT NULL without default).
        return User::create([
            'name' => 'Test Admin',
            'email' => 'admin.dentalchair.test@example.com',
            'username' => 'admin_dentalchair',
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function makeChair(string $code, string $status, bool $isActive = true): DentalChair
    {
        return DentalChair::create([
            'name' => 'Chair '.$code,
            'code' => $code,
            'description' => 'T1 status filter fixture',
            'equipment' => 'Fixture equipment',
            'status' => $status,
            'is_active' => $isActive,
        ]);
    }

    private function seedOneRowPerStatus(): void
    {
        $this->makeChair('AMB-T1-ACT', 'active');
        $this->makeChair('AMB-T1-INA', 'inactive');
        $this->makeChair('AMB-T1-MNT', 'maintenance');
        $this->makeChair('AMB-T1-ARC', 'active', false);
    }

    private function listedCodes($response): array
    {
        return collect($response->json('data'))->pluck('code')->all();
    }

    public function test_filter_by_active_status_returns_only_active_status_rows(): void
    {
        $this->seedOneRowPerStatus();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?status=active');

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $codes = $this->listedCodes($response);
        $this->assertContains('AMB-T1-ACT', $codes);
        $this->assertNotContains('AMB-T1-INA', $codes, 'status=active MUST exclude inactive rows.');
        $this->assertNotContains('AMB-T1-MNT', $codes, 'status=active MUST exclude maintenance rows.');
        $this->assertNotContains('AMB-T1-ARC', $codes, 'status=active MUST exclude soft-archived rows.');
    }

    public function test_filter_by_inactive_status_returns_only_inactive_status_rows(): void
    {
        $this->seedOneRowPerStatus();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?status=inactive');

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $codes = $this->listedCodes($response);
        $this->assertContains('AMB-T1-INA', $codes);
        $this->assertNotContains('AMB-T1-ACT', $codes, 'status=inactive MUST exclude active rows.');
        $this->assertNotContains('AMB-T1-MNT', $codes, 'status=inactive MUST exclude maintenance rows.');
    }

    public function test_filter_by_maintenance_status_returns_only_maintenance_status_rows(): void
    {
        $this->seedOneRowPerStatus();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?status=maintenance');

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $codes = $this->listedCodes($response);
        $this->assertContains('AMB-T1-MNT', $codes);
        $this->assertNotContains('AMB-T1-ACT', $codes, 'status=maintenance MUST exclude active rows.');
        $this->assertNotContains('AMB-T1-INA', $codes, 'status=maintenance MUST exclude inactive rows.');
    }

    public function test_invalid_status_returns_422_validation_error(): void
    {
        $this->makeChair('AMB-T1-ACT', 'active');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?status=bogus');

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_blank_status_value_returns_422_validation_error(): void
    {
        $this->makeChair('AMB-T1-ACT', 'active');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?status=');

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_without_status_returns_active_flagged_rows_of_every_status(): void
    {
        $this->seedOneRowPerStatus();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs');

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $codes = $this->listedCodes($response);
        $this->assertContains('AMB-T1-ACT', $codes, 'Default listing MUST include active rows.');
        $this->assertContains('AMB-T1-INA', $codes, 'Default listing MUST include inactive rows.');
        $this->assertContains('AMB-T1-MNT', $codes, 'Default listing MUST include maintenance rows.');
        $this->assertNotContains('AMB-T1-ARC', $codes, 'Default listing MUST exclude soft-archived rows.');
    }

    public function test_soft_archived_row_is_never_listed_even_when_its_status_matches(): void
    {
        $this->makeChair('AMB-T1-MNT-LIVE', 'maintenance');
        $this->makeChair('AMB-T1-MNT-ARC', 'maintenance', false);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?status=maintenance');

        $response->assertOk();
        $codes = $this->listedCodes($response);
        $this->assertContains('AMB-T1-MNT-LIVE', $codes);
        $this->assertNotContains('AMB-T1-MNT-ARC', $codes, 'is_active = true MUST always apply, even under a status filter.');
    }
}
