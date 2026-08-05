<?php

namespace Tests\Unit\Seeders;

use App\Models\EndodonticsRecord;
use App\Models\ImplantologyRecord;
use App\Models\OralSurgeryRecord;
use App\Models\OrthodonticsRecord;
use App\Models\RehabilitationRecord;
use PHPUnit\Framework\TestCase;

/**
 * bugfix-2026-08 slice 05 — pure file/class assertions about
 * SpecialtyRecordSeeder that do NOT require a database connection.
 *
 * These pass locally on SQLite without triggering the documented
 * `transactions.type` dropColumn baseline tech debt (AGENTS.md §6).
 *
 * DB-bound execution assertions live in `tests/Feature/Api/SpecialtyRecordSeederTest.php`.
 */
class SpecialtyRecordSeederSourceTest extends TestCase
{
    /** @test */
    public function seeder_references_five_concrete_models_in_source(): void
    {
        $source = file_get_contents(
            realpath(__DIR__ . '/../../../database/seeders/SpecialtyRecordSeeder.php')
        );

        $expectedModels = [
            'ImplantologyRecord',
            'OrthodonticsRecord',
            'EndodonticsRecord',
            'RehabilitationRecord',
            'OralSurgeryRecord',
        ];

        foreach ($expectedModels as $model) {
            $this->assertStringContainsString(
                $model,
                $source,
                "SpecialtyRecordSeeder must reference {$model} (BF-022 fix)."
            );
        }

        // Must NOT reference the legacy unified SpecialtyRecord model.
        $this->assertStringNotContainsString(
            'use App\Models\SpecialtyRecord;',
            $source,
            'SpecialtyRecordSeeder must not reference the legacy unified SpecialtyRecord model.'
        );
    }

    /** @test */
    public function five_specialty_record_model_classes_exist(): void
    {
        $expected = [
            ImplantologyRecord::class,
            OrthodonticsRecord::class,
            EndodonticsRecord::class,
            RehabilitationRecord::class,
            OralSurgeryRecord::class,
        ];
        foreach ($expected as $class) {
            $this->assertTrue(
                class_exists($class),
                "Expected concrete specialty record model {$class} to exist."
            );
        }
    }
}