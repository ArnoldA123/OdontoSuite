<?php

namespace Tests\Feature\Api;

use App\Http\Resources\UserResource;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\RoleBasedUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bugfix-2026-08 slice 05 — DB-bound assertions about the User specialty
 * source-of-truth.
 *
 * These tests require a working database. On SQLite local they fail with
 * the documented `transactions.type` dropColumn baseline tech debt
 * (AGENTS.md §6) — the same pattern as slices 01-04 Feature tests. They
 * pass on CI MySQL.
 *
 * Acceptance:
 *   - `users` table no longer has a `specialties` JSON column.
 *   - User model relationship returns the pivot Specialty models.
 *   - UserResource exposes `specialties` via the pivot (not the dropped JSON).
 *   - Legacy `users.specialty` string column is RETAINED (ADR-0007).
 */
class UserSpecialtySourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function users_table_does_not_have_a_specialties_json_column(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');
        $this->assertNotContains(
            'specialties',
            $columns,
            'The legacy specialties JSON column must be removed from users table.'
        );
    }

    /** @test */
    public function user_model_relationship_returns_pivot_specialties(): void
    {
        $seed = new RoleBasedUsersSeeder();
        $seed->setCommand($this->createMock(\Illuminate\Console\Command::class));
        $seed->run();

        $specialty = Specialty::firstOrCreate(
            ['code' => 'ORT'],
            ['name' => 'Ortodoncia', 'is_active' => true]
        );

        $user = User::where('is_active', true)->first();
        $this->assertNotNull($user, 'Test fixture should produce at least one active user');

        $user->specialties()->attach($specialty->id, ['is_primary' => true]);

        $this->assertCount(1, $user->fresh()->specialties);
        $this->assertSame('Ortodoncia', $user->fresh()->specialties->first()->name);
        $this->assertTrue($user->fresh()->specialties->first()->pivot->is_primary);
    }

    /** @test */
    public function user_resource_exposes_specialties_via_pivot(): void
    {
        $seed = new RoleBasedUsersSeeder();
        $seed->setCommand($this->createMock(\Illuminate\Console\Command::class));
        $seed->run();

        $specialty = Specialty::firstOrCreate(
            ['code' => 'END'],
            ['name' => 'Endodoncia', 'is_active' => true]
        );

        $user = User::where('is_active', true)->first();
        $user->specialties()->attach($specialty->id, ['is_primary' => true]);

        $resource = UserResource::make($user->fresh()->load('specialties'));
        $payload = $resource->resolve();

        $this->assertArrayHasKey('specialties', $payload);
        $this->assertCount(1, $payload['specialties']);
        $this->assertSame('Endodoncia', $payload['specialties'][0]['name']);
        $this->assertTrue($payload['specialties'][0]['is_primary']);
    }

    /** @test */
    public function specialty_legacy_string_remains_on_users_table(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');
        $this->assertContains('specialty', $columns);
    }

    /** @test */
    public function user_specialties_pivot_table_has_expected_columns(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('user_specialties');
        $this->assertContains('user_id', $columns);
        $this->assertContains('specialty_id', $columns);
        $this->assertContains('is_primary', $columns);
    }
}