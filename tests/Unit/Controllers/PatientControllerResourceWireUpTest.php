<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR2 follow-up / Phase 2b — controller wire-up.
 *
 * RED test (source-contract) for the bounded follow-up identified by
 * verify-report #337: PatientController::index / show / store / update
 * returned raw Eloquent models and never invoked PatientResource, so the
 * `age` key never reached the JSON contract consumed by PatientsPage and
 * PatientSelector.
 *
 * This test runs as a pure source check (no DB, no app boot) and is the
 * only level at which RED is observable locally under SQLite — the
 * RefreshDatabase Feature test (PatientControllerAgeTest) is a stronger
 * contract that will run green on CI MySQL but is blocked by the documented
 * pre-existing `transactions.type` dropColumn SQLite tech debt.
 *
 * Mirrors the recipe used by AppointmentControllerInjectionStyleTest and
 * SpecialtyRecordSeederSourceTest: parse the controller source, locate
 * each return statement, assert the data path is wrapped in PatientResource.
 *
 * Rollback boundary: delete this file + revert the four PatientController
 * methods (index, show, store, update) to return raw `$patient` and
 * `$patients->items()`. No data impact, no migration, no model change.
 */
class PatientControllerResourceWireUpTest extends TestCase
{
    private const CONTROLLER_FILE = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite/app/Http/Controllers/Api/PatientController.php';

    private function controllerSource(): string
    {
        $source = file_get_contents(self::CONTROLLER_FILE);
        $this->assertNotFalse($source, 'PatientController source MUST be readable.');
        return $source;
    }

    /**
     * Extract the body of a public method by name. Walks the braces
     * starting from the first `{` after `function NAME(` and returns
     * the substring up to the matching `}`. Defeats non-recursive
     * regex pitfalls with nested closures.
     */
    private function extractMethodBody(string $source, string $methodName): ?string
    {
        if (!preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*:[^{]*\{/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $openPos = $matches[0][1] + strlen($matches[0][0]) - 1;
        $depth = 0;
        $len = strlen($source);
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $source[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $openPos + 1, $i - $openPos - 1);
                }
            }
        }
        return null;
    }

    // -------------------------------------------------------------------
    // The bug surface — every public CRUD method MUST wire PatientResource
    // -------------------------------------------------------------------

    public function test_index_wraps_paginated_items_in_patient_resource(): void
    {
        $source = $this->controllerSource();

        // Slice out the body of the `index` method, then assert that the
        // body references PatientResource. The body's nested blocks (query
        // builder closures) defeat a single non-recursive regex, so we
        // locate the opening `function index` brace, walk to the matching
        // close brace, and search the slice.
        $body = $this->extractMethodBody($source, 'index');
        $this->assertNotNull(
            $body,
            'Could not locate the `index` method body in PatientController.'
        );

        $this->assertStringContainsString(
            'PatientResource',
            $body,
            'PatientController::index() MUST reference PatientResource (collection / make / new) '
                . 'so the `age` key reaches the JSON contract. '
                . 'Currently the controller returns raw $patients->items() and the SPA renders — / N/A años.'
        );
    }

    public function test_show_wraps_patient_in_patient_resource(): void
    {
        $source = $this->controllerSource();

        $body = $this->extractMethodBody($source, 'show');
        $this->assertNotNull($body, 'Could not locate the `show` method body.');

        $this->assertStringContainsString(
            'PatientResource',
            $body,
            'PatientController::show() MUST reference PatientResource so the `age` key reaches the '
                . 'JSON contract. Currently the controller returns the raw Eloquent model.'
        );
    }

    public function test_store_wraps_created_patient_in_patient_resource(): void
    {
        $source = $this->controllerSource();

        $body = $this->extractMethodBody($source, 'store');
        $this->assertNotNull($body, 'Could not locate the `store` method body.');

        $this->assertStringContainsString(
            'PatientResource',
            $body,
            'PatientController::store() MUST reference PatientResource so the 201 response includes the `age` key.'
        );
    }

    public function test_update_wraps_refreshed_patient_in_patient_resource(): void
    {
        $source = $this->controllerSource();

        $body = $this->extractMethodBody($source, 'update');
        $this->assertNotNull($body, 'Could not locate the `update` method body.');

        $this->assertStringContainsString(
            'PatientResource',
            $body,
            'PatientController::update() MUST reference PatientResource so the 200 response includes the `age` key.'
        );
    }

    public function test_controller_uses_patient_resource_in_at_least_four_methods(): void
    {
        $source = $this->controllerSource();

        // Sanity count: the controller must reference PatientResource at
        // least 4 times (index, show, store, update). `destroy` returns null
        // and `search` computes `age` inline; neither needs PatientResource.
        $count = preg_match_all('/PatientResource\b/', $source);

        $this->assertGreaterThanOrEqual(
            4,
            $count,
            'PatientController MUST reference PatientResource at least 4 times (index, show, store, update). '
                . "Currently found {$count} reference(s)."
        );
    }
}
