<?php

namespace Tests\Feature\Modules;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Branches UI redesign (2026-10-19).
 *
 * The redesigned form exposes the optional address/country/timezone columns,
 * whose table definition is NOT NULL. Clearing one of them arrives as null
 * after ConvertEmptyStringsToNull, so the update path must normalize only the
 * columns the client actually sent instead of failing at the database.
 */
class BranchUpdateNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $suffix = uniqid();

        return User::create([
            'name' => "Admin {$suffix}",
            'email' => "branch-update.{$suffix}@example.com",
            'username' => "branch_update_{$suffix}",
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_clearing_an_optional_not_null_column_keeps_the_branch_updatable(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->putJson("/api/branches/{$branch->id}", [
            'name' => 'Sede sin direccion',
            'city' => 'Lima',
            'address' => '',
            'country' => '',
            'timezone' => '',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'address' => '',
            'country' => 'Perú',
            'timezone' => 'America/Lima',
        ]);
    }

    public function test_a_partial_update_does_not_overwrite_omitted_columns(): void
    {
        $branch = Branch::factory()->create([
            'address' => 'Av. Original 1',
            'country' => 'Perú',
            'timezone' => 'America/Lima',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/branches/{$branch->id}", [
                'name' => 'Sede parcial',
                'city' => 'Lima',
            ])
            ->assertOk();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'address' => 'Av. Original 1',
            'country' => 'Perú',
            'timezone' => 'America/Lima',
        ]);
    }
}
