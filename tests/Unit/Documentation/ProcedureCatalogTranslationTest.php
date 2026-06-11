<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Sprint 4 fix (IM-8): valida la estructura del sistema de multi-idioma
 * del catalogo de procedimientos.
 *
 * Tests puramente estructurales (no tocan BD). Verifican que el modelo,
 * el accessor translate(), la relacion translations(), la migracion
 * y los campos correctos existen.
 */
class ProcedureCatalogTranslationTest extends TestCase
{
    private static function projectRoot(): string
    {
        return realpath(__DIR__ . '/../../..');
    }

    /** @test */
    public function translation_model_exists(): void
    {
        $path = self::projectRoot() . '/app/Models/ProcedureCatalogTranslation.php';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('class ProcedureCatalogTranslation', $content);
        $this->assertStringContainsString('extends Model', $content);
    }

    /** @test */
    public function translation_model_has_correct_fillable(): void
    {
        $content = file_get_contents(self::projectRoot() . '/app/Models/ProcedureCatalogTranslation.php');
        $required = ['procedure_catalog_id', 'locale', 'name', 'description', 'requirements', 'materials_needed', 'contraindications', 'post_procedure_care'];
        foreach ($required as $field) {
            $this->assertStringContainsString("'{$field}'", $content, "ProcedureCatalogTranslation must have fillable: {$field}");
        }
    }

    /** @test */
    public function migration_exists_for_translations_table(): void
    {
        $path = self::projectRoot() . '/database/migrations/2026_06_11_003000_create_procedure_catalog_translations_table.php';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('procedure_catalog_translations', $content);
        $this->assertStringContainsString("'locale'", $content);
        $this->assertStringContainsString("'name'", $content);
    }

    /** @test */
    public function migration_has_unique_constraint_on_locale_per_procedure(): void
    {
        $content = file_get_contents(self::projectRoot() . '/database/migrations/2026_06_11_003000_create_procedure_catalog_translations_table.php');
        $this->assertStringContainsString('unique', $content, 'Migration must have unique constraint on [procedure_catalog_id, locale]');
        $this->assertStringContainsString('pc_trans_unique', $content, 'Unique constraint should have explicit index name');
    }

    /** @test */
    public function procedure_catalog_model_has_translations_relationship(): void
    {
        $reflection = new \ReflectionClass(\App\Models\ProcedureCatalog::class);
        $this->assertTrue($reflection->hasMethod('translations'), 'ProcedureCatalog must have translations() HasMany relationship');
        $method = $reflection->getMethod('translations');
        $this->assertStringEndsWith('HasMany', $method->getReturnType()->getName());
    }

    /** @test */
    public function procedure_catalog_model_has_translate_method(): void
    {
        $reflection = new \ReflectionClass(\App\Models\ProcedureCatalog::class);
        $this->assertTrue($reflection->hasMethod('translate'), 'ProcedureCatalog must have translate(locale, field) method');
        $method = $reflection->getMethod('translate');
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('locale', $params[0]->getName());
        $this->assertEquals('field', $params[1]->getName());
    }

    /** @test */
    public function translate_method_requires_locale_param(): void
    {
        $reflection = new \ReflectionClass(\App\Models\ProcedureCatalog::class);
        $method = $reflection->getMethod('translate');
        $params = $method->getParameters();
        $this->assertStringContainsString('string', $params[0]->getType()->getName());
        $this->assertFalse($params[0]->isOptional(), 'locale parameter should be required');
    }

    /** @test */
    public function ProcedureCatalogTranslation_model_table_name_is_correct(): void
    {
        $path = self::projectRoot() . '/app/Models/ProcedureCatalogTranslation.php';
        $content = file_get_contents($path);
        $this->assertStringContainsString("protected \$table = 'procedure_catalog_translations'", $content);
    }
}
