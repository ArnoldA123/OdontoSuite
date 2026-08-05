<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — BF-014 SpecialtyRecordController show() query
 * reduction RED test.
 *
 * SpecialtyRecordController::show(int $id) runs 5 sequential findOrFail
 * queries (Implantology, Orthodontics, Endodontics, Rehabilitation,
 * OralSurgery) — O(N) per call. The fix iterates the model classes in a
 * loop (or uses morphMap) so there is a single foreach + find loop with
 * at most one DB roundtrip per table until the row is found.
 *
 * This test parses the source and asserts the controller has been refactored
 * from 5 `findOrFail` / `find` calls into a single foreach loop.
 */
class SpecialtyRecordControllerShowQueriesTest extends TestCase
{
    private const CONTROLLER_FILE = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite/app/Http/Controllers/Api/SpecialtyRecordController.php';

    public function test_specialty_record_show_iterates_models_in_a_loop(): void
    {
        $source = file_get_contents(self::CONTROLLER_FILE);
        $this->assertNotFalse($source);

        // Count occurrences of the canonical model::find pattern inside
        // the show() method. Before the fix there were 5; after there
        // must be exactly 1 (the loop body).
        preg_match_all('/::with\([^)]+\)\s*->find\(/', $source, $matches);
        $findCount = count($matches[0]);

        $this->assertLessThanOrEqual(
            1,
            $findCount,
            'BF-014: SpecialtyRecordController@show must collapse the 5 sequential model::find calls into a single loop (≤1 inline occurrence)'
        );

        // The loop must exist (foreach over the models array).
        $this->assertStringContainsString(
            'foreach',
            $source,
            'BF-014: SpecialtyRecordController@show must iterate over the model classes with foreach'
        );

        // The model classes must be enumerated as a list (any of: array,
        // const, or explicit foreach body). The simplest implementation
        // declares an array of class strings.
        $hasModelList = (bool) preg_match(
            "/\[\\s*['\\\"]?Implantology['\\\"]?\\s*=>|\\bImplantologyRecord::class\\b.*\\bOrthodonticsRecord::class\\b/s",
            $source
        );
        $this->assertTrue(
            $hasModelList,
            'BF-014: SpecialtyRecordController must enumerate all 5 specialty model classes (ImplantologyRecord, OrthodonticsRecord, EndodonticsRecord, RehabilitationRecord, OralSurgeryRecord) for the show() lookup loop'
        );
    }
}
