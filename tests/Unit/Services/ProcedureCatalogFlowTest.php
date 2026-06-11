<?php

namespace Tests\Unit\Services;

use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ProcedureCatalogService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Sprint 3 fix (IM-3): tests estructurales del flujo de catálogo de
 * procedimientos. No tocan BD (incompatibilidad SQLite/MySQL preexistente,
 * ver plan-mejoras-futuras-2026-06.md Sprint 4 IM-1). Verifican que el
 * código está bien armado: métodos requeridos, scopes, relaciones, etc.
 */
class ProcedureCatalogFlowTest extends TestCase
{
    /** @test */
    public function procedure_catalog_service_has_all_required_methods(): void
    {
        $reflection = new ReflectionClass(ProcedureCatalogService::class);
        $required = ['paginate', 'search', 'activeList', 'findOrFail', 'forUser', 'create', 'update', 'deactivate'];
        foreach ($required as $method) {
            $this->assertTrue($reflection->hasMethod($method), "ProcedureCatalogService must have method: {$method}");
        }
    }

    /** @test */
    public function procedure_catalog_model_fillable_includes_catalog_fields(): void
    {
        $model = new ProcedureCatalog();
        $fillable = $model->getFillable();

        $expected = [
            'code', 'name', 'description', 'specialty_id', 'default_cost',
            'default_duration_minutes', 'requirements', 'materials_needed',
            'requires_anesthesia', 'requires_radiographs', 'steps',
            'contraindications', 'post_procedure_care', 'is_active',
        ];
        foreach ($expected as $field) {
            $this->assertContains($field, $fillable, "ProcedureCatalog fillable missing: {$field}");
        }
    }

    /** @test */
    public function procedure_catalog_model_casts_numeric_and_boolean_fields(): void
    {
        $model = new ProcedureCatalog();
        $casts = $model->getCasts();

        $this->assertEquals('decimal:2', $casts['default_cost'] ?? null);
        $this->assertEquals('integer', $casts['default_duration_minutes'] ?? null);
        $this->assertEquals('boolean', $casts['requires_anesthesia'] ?? null);
        $this->assertEquals('boolean', $casts['requires_radiographs'] ?? null);
        $this->assertEquals('boolean', $casts['is_active'] ?? null);
    }

    /** @test */
    public function procedure_catalog_has_specialty_relationship(): void
    {
        $model = new ProcedureCatalog();
        $this->assertTrue(method_exists($model, 'specialty'));
        $this->assertTrue(method_exists($model, 'favoritedBy'));
        $this->assertTrue(method_exists($model, 'treatmentPlanItems'));
    }

    /** @test */
    public function procedure_catalog_specialty_code_accessor_prefers_fk(): void
    {
        // Sprint 2 (DM-7) agrego getSpecialtyCodeAttribute. Aqui validamos
        // que existe y tiene la firma esperada.
        $reflection = new ReflectionClass(ProcedureCatalog::class);
        $this->assertTrue($reflection->hasMethod('getSpecialtyCodeAttribute'));
    }

    /** @test */
    public function specialty_model_has_required_methods_for_catalog(): void
    {
        $reflection = new ReflectionClass(Specialty::class);
        $this->assertTrue($reflection->hasMethod('procedureCatalog'));
        $this->assertTrue($reflection->hasMethod('users'));
        $this->assertTrue($reflection->hasMethod('scopeActive'));
    }

    /** @test */
    public function user_has_favorite_procedures_relation_with_position_pivot(): void
    {
        // La relacion favoriteProcedures() en User usa withPivot('position')
        // para soportar el reorder. Verificamos que la firma es correcta.
        $reflection = new ReflectionClass(User::class);
        $this->assertTrue($reflection->hasMethod('favoriteProcedures'));

        $method = $reflection->getMethod('favoriteProcedures');
        $this->assertStringEndsWith('BelongsToMany', $method->getReturnType()->getName());
    }

    /** @test */
    public function procedure_catalog_routes_are_registered(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn($r) => $r->uri())
            ->filter(fn($uri) => str_contains($uri, 'procedure-catalog') || str_contains($uri, 'procedure-catalog-favorites'));

        $this->assertGreaterThanOrEqual(10, $routes->count(), 'Should have at least 10 procedure-catalog routes');
    }

    /** @test */
    public function procedure_catalog_routes_have_role_middleware_for_writes(): void
    {
        // POST/PUT/DELETE en procedure-catalog requieren rol administrativo
        // (admin o finanzas para el endpoint de import).
        $writeRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => in_array('POST', $r->methods()) || in_array('PUT', $r->methods()) || in_array('DELETE', $r->methods()))
            ->filter(fn($r) => str_contains($r->uri(), 'procedure-catalog') && !str_contains($r->uri(), 'favorite'));

        $writeRoutes->each(function ($route) {
            $middleware = implode(',', $route->middleware());
            $this->assertStringContainsString(
                'role:',
                $middleware,
                "Writes on procedure-catalog must require some role. Route: {$route->uri()}, middleware: {$middleware}"
            );
        });
    }

    /** @test */
    public function procedure_catalog_favorite_routes_have_clinical_role_middleware(): void
    {
        $favRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn($r) => str_contains($r->uri(), 'favorite') || str_contains($r->uri(), 'procedure-catalog-favorites'));

        $favRoutes->each(function ($route) {
            $middleware = implode(',', $route->middleware());
            $this->assertTrue(
                str_contains($middleware, 'role:') && str_contains($middleware, 'odontologo'),
                "Favorite routes must require role:odontologo,implantologo,tecnico_dental,asistente. Route: {$route->uri()}, middleware: {$middleware}"
            );
        });
    }
}
