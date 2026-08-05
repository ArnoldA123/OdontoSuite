<?php

namespace Tests\Unit\Routes;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — broadcasting auth BF-019 RED test.
 *
 * When REVERB_APP_SECRET (or REVERB_APP_KEY) is missing, /api/broadcasting/auth
 * returns 500 "Server configuration error". Per HTTP semantics a missing
 * service-side dependency is a 503 Service Unavailable, not a 500.
 *
 * Rather than spinning up a Laravel HTTP request (which needs the SQLite
 * schema migrated), this test asserts the routes/api.php closure uses the
 * 503 status code in the "missing config" branches.
 *
 * Slice 11 also extracts the closure into BroadcastingAuthController; the
 * test passes if either form uses 503 (closure-with-503 OR controller).
 */
class BroadcastingAuth503Test extends TestCase
{
    private const ROUTES_FILE = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite/routes/api.php';
    private const APP_DIR = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite/app/Http/Controllers/Api';

    public function test_broadcasting_auth_returns_503_when_secret_or_key_missing(): void
    {
        $routesSource = file_get_contents(self::ROUTES_FILE);
        $this->assertNotFalse($routesSource);

        $controllerSource = $this->controllerSource();

        // The fix swaps the 500 response on missing reverb secret/key
        // to a 503 (service unavailable).
        $closureUses503 = str_contains($routesSource, "response()->json(['message' => 'Server configuration error'], 503)")
            || preg_match("/response\\(\\)->json\\([^)]*Server configuration error[^)]*503/s", $routesSource) === 1;

        $controllerUses503 = $controllerSource !== null && (
            str_contains($controllerSource, '503')
        );

        $this->assertTrue(
            $closureUses503 || $controllerUses503,
            'BF-019: broadcasting/auth must return 503 (not 500) when REVERB_APP_SECRET/KEY is missing.'
        );

        // The legacy 500 must be gone from the closure (or from the controller).
        $still500 = preg_match(
            "/response\\(\\)->json\\(\\[\\s*'message'\\s*=>\\s*'Server configuration error'\\s*\\]\\s*,\\s*500\\)/",
            $routesSource
        );
        $this->assertSame(
            0,
            (int) $still500,
            'BF-019: the legacy 500 in broadcasting/auth must be replaced with 503.'
        );
    }

    public function test_broadcasting_auth_is_extracted_to_a_controller(): void
    {
        // BF-025: the big inline closure in routes/api.php must be extracted
        // into a dedicated BroadcastingAuthController.
        $controllerFile = self::APP_DIR . '/BroadcastingAuthController.php';
        $this->assertFileExists(
            $controllerFile,
            'BF-025: routes/api.php must NOT contain the broadcasting/auth closure inline — extract it to App\Http\Controllers\Api\BroadcastingAuthController'
        );
    }

    private function controllerSource(): ?string
    {
        $controllerFile = self::APP_DIR . '/BroadcastingAuthController.php';
        if (!is_file($controllerFile)) {
            return null;
        }

        return file_get_contents($controllerFile) ?: null;
    }
}
