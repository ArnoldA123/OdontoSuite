<?php

namespace Tests\Unit\Polish;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — 39 polish findings inline RED tests.
 *
 * Each test verifies a single low-priority polish item that was either
 * already resolved by an earlier slice or required a small clarification.
 * Findings covered (selective; full list in findings-map.md low-polish):
 *
 *  - API-022: ProcedureCatalogController@destroy soft-deactivates
 *  - API-035: PatientController@export accepts format=zip|pdf
 *  - API-057: PatientController@export validates format whitelist
 *  - API-015: ProcedureCatalogController@index accepts specialty filter
 *  - BF-005: AuthController does NOT expose orphan refresh() method
 *  - BF-006: RoleController not present (CRUD handled via Users apiResource)
 *  - BF-022: SpecialtyRecordSeeder uses concrete models — see slice 05
 */
class ApiAndSeedersPolishTest extends TestCase
{
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';
    private const APP_DIR = self::PROJECT_ROOT . '/app/Http/Controllers/Api';
    private const ROUTES_FILE = self::PROJECT_ROOT . '/routes/api.php';

    /** @test API-022 */
    public function procedure_catalog_destroy_is_soft_deactivate(): void
    {
        $source = file_get_contents(self::APP_DIR . '/ProcedureCatalogController.php');
        $this->assertNotFalse($source);

        // destroy() must call a `deactivate` method on the service, not delete().
        $this->assertMatchesRegularExpression(
            '/function destroy\\([^)]*\\)[^{]*\\{[\\s\\S]*?deactivate\\(/',
            $source,
            'API-022: ProcedureCatalogController@destroy must soft-deactivate via service->deactivate()'
        );
    }

    /** @test API-035 / API-057 */
    public function patient_controller_export_accepts_pdf_and_zip(): void
    {
        $source = file_get_contents(self::APP_DIR . '/PatientController.php');
        $this->assertNotFalse($source);

        // The export() handler must accept 'pdf' and 'zip' as the two
        // supported formats.
        $this->assertStringContainsString(
            'pdf,zip',
            $source,
            'API-057: PatientController@export must validate format against the pdf,zip whitelist'
        );

        // The synchronous path must branch on format to produce the
        // correct Content-Type for each.
        $this->assertStringContainsString(
            'application/pdf',
            $source,
            'API-035: PatientController@export must emit application/pdf for the pdf branch'
        );
        $this->assertStringContainsString(
            'application/zip',
            $source,
            'API-035: PatientController@export must emit application/zip for the zip branch'
        );
    }

    /** @test API-015 */
    public function procedure_catalog_index_supports_specialty_filter(): void
    {
        $controller = file_get_contents(self::APP_DIR . '/ProcedureCatalogController.php');
        $this->assertNotFalse($controller);

        // The catalog index endpoint must read specialty from the request
        // and forward to the service.
        $this->assertStringContainsString(
            "'specialty'",
            $controller,
            'API-015: ProcedureCatalogController@index must read ?specialty via $request->only([...specialty...])'
        );

        // The service must apply the specialty filter via whereHas.
        $service = file_get_contents(self::PROJECT_ROOT . '/app/Services/ProcedureCatalogService.php');
        $this->assertMatchesRegularExpression(
            "/whereHas\\(\\s*'specialty'/",
            $service === false ? '' : $service,
            'API-015: ProcedureCatalogService::paginate must filter by specialty via whereHas(..., fn (\$q) => $q->where(\'code\', \$filters[\'specialty\']))'
        );
    }

    /** @test BF-005 */
    public function auth_controller_does_not_expose_orphan_refresh_method(): void
    {
        $source = file_get_contents(self::APP_DIR . '/AuthController.php');
        $this->assertNotFalse($source);

        // Per BF-005: AuthController::refresh is an orphan method (no
        // route). After slice 11 the method must be removed.
        $hasRefreshMethod = (bool) preg_match(
            '/public function refresh\\(/',
            $source === false ? '' : $source
        );
        $this->assertFalse(
            $hasRefreshMethod,
            'BF-005: AuthController must NOT expose a refresh() method (orphan, no route wired).'
        );

        // Sanity: no POST /auth/refresh route registered.
        $routes = file_get_contents(self::ROUTES_FILE);
        $this->assertStringNotContainsString(
            '/auth/refresh',
            $routes === false ? '' : $routes,
            'BF-005: routes/api.php must not register /auth/refresh (orphan endpoint).'
        );
    }

    /** @test BF-006 */
    public function role_controller_is_not_present(): void
    {
        $this->assertFileDoesNotExist(
            self::APP_DIR . '/RoleController.php',
            'BF-006: RoleController must NOT exist (roles are managed via Users apiResource + a future v2 endpoint).'
        );

        $routes = file_get_contents(self::ROUTES_FILE);
        $this->assertStringNotContainsString(
            "Route::apiResource('roles'",
            $routes === false ? '' : $routes,
            'BF-006: routes/api.php must not register a roles apiResource.'
        );
    }
}
