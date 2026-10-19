<?php

namespace Tests\Feature\Factories;

use App\Models\ProcedureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F12 (2026-10-19 audit): the shared factory still inserted a `category`
 * column that procedure_catalog no longer has, so every test using it errored
 * before touching its own fixture. The guard keeps the factory honest against
 * the real schema.
 */
class ProcedureCatalogFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_procedure_catalog_factory_creates_a_valid_row(): void
    {
        $procedure = ProcedureCatalog::factory()->create(['code' => 'PROC-9001']);

        $this->assertDatabaseHas('procedure_catalog', [
            'id' => $procedure->id,
            'code' => 'PROC-9001',
            'default_duration_minutes' => 30,
            'default_cost' => 100.00,
            'is_active' => true,
        ]);
    }
}
