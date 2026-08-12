<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR0 (ui-rollout-all-modules-2026-08) — pins the `canvasRoutes` array
 * literal in `resources/js/components/layout/AppLayout.vue`.
 *
 * Source-grep test: the array is the contract the entire rollout rides on.
 * Removing a route surfaces as a test failure (scenario CANVAS-ROUTE-001).
 *
 * The extractor tolerates single-line `//` comments, block `/* ... *\/`
 * comments, whitespace, line-breaks, and trailing commas so this is a
 * "the array contains every expected route" assertion, not a literal-string
 * pin (per design.md §4.1 + archive-report.md "test pins rule, not literal"
 * lesson).
 */
class AppLayoutCanvasRoutesTest extends TestCase
{
    private const APP_LAYOUT_PATH = '/resources/js/components/layout/AppLayout.vue';

    /**
     * The 21 polished module routes (foundation-primitives spec APP-CORE-001
     * + design.md §3.1). Order is preserved from the array literal; this list
     * is the rule, not the comment.
     */
    private const EXPECTED_ROUTES = [
        // Already polished (vertical slice)
        '/dashboard',
        '/login',
        '/404',
        // Pagos
        '/cash-register',
        '/cash-register/ready-to-bill',
        '/quotations',
        // Catálogo
        '/procedure-catalog',
        '/procedure-stats',
        // Admin
        '/professionals',
        '/environments',
        '/appointment-types',
        // Operación
        '/calendar',
        // Clínico
        '/patients',
        '/medical-records',
        '/specialty-records',
        '/treatment-plans',
        // Catálogo tail
        '/my-procedures',
        '/reception-procedures',
        // Análisis
        '/ai-analysis',
        // BI
        '/business-intelligence',
        // Settings (canvas surface only)
        '/settings/branches',
        '/settings/payment-methods',
    ];

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function appLayoutPath(): string
    {
        return self::projectRoot() . self::APP_LAYOUT_PATH;
    }

    /**
     * Extract the body of the `const canvasRoutes = [ ... ]` literal,
     * tolerating comments, whitespace, line-breaks, and trailing commas.
     * Returns null when no such literal exists in the source.
     */
    private static function extractCanvasRoutesBody(string $src): ?string
    {
        if (!preg_match('/const\s+canvasRoutes\s*=\s*\[/', $src, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $matches[0][1] + strlen($matches[0][0]);
        $depth = 0;
        $len = strlen($src);
        for ($i = $start; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                if ($depth === 0) {
                    return substr($src, $start, $i - $start);
                }
                $depth--;
            }
        }

        return null;
    }

    /**
     * Enumerate every single-quoted route string in the array body,
     * stripping out comment characters so they do not contribute matches.
     *
     * @return array<int, string>
     */
    private static function listRoutesInBody(string $body): array
    {
        // Strip line comments (// ...) — keep newlines to preserve offsets
        $cleaned = preg_replace('#//[^\n]*#', '', $body) ?? $body;
        // Strip block comments (/* ... */)
        $cleaned = preg_replace('#/\*.*?\*/#s', '', $cleaned) ?? $cleaned;

        if (preg_match_all("/'([^']+)'/", $cleaned, $matches) === false) {
            return [];
        }

        return $matches[1];
    }

    public static function expectedRouteProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_ROUTES as $route) {
            $cases[$route] = [$route];
        }

        return $cases;
    }

    public function test_canvas_routes_file_exists(): void
    {
        $this->assertFileExists(
            self::appLayoutPath(),
            'AppLayout.vue must exist for the canvasRoutes invariant test to run.'
        );
    }

    public function test_canvas_routes_array_present(): void
    {
        $src = file_get_contents(self::appLayoutPath());
        $this->assertIsString($src, 'AppLayout.vue must be readable.');
        $this->assertNotNull(
            self::extractCanvasRoutesBody($src),
            'AppLayout.vue must contain a `const canvasRoutes = [...]` literal.'
        );
    }

    /**
     * @dataProvider expectedRouteProvider
     */
    public function test_each_expected_route_is_in_canvas_routes(string $route): void
    {
        $src = file_get_contents(self::appLayoutPath());
        $this->assertIsString($src, 'AppLayout.vue must be readable.');

        $body = self::extractCanvasRoutesBody($src);
        $this->assertNotNull(
            $body,
            'AppLayout.vue must contain a `const canvasRoutes = [...]` literal.'
        );

        $routes = self::listRoutesInBody($body);
        $this->assertContains(
            $route,
            $routes,
            sprintf(
                'canvasRoutes must contain the route `%s`. Found routes: [%s].',
                $route,
                implode(', ', $routes)
            )
        );
    }

    /**
     * SENTINEL — catches a sneaky regression where someone narrows the array
     * back to the 3-route vertical-slice set. The vertical-slice routes
     * (`/dashboard`, `/login`, `/404`) are still legal; what this test
     * forbids is "only the vertical-slice routes and nothing else".
     */
    public function test_no_legacy_narrowing_to_vertical_slice_routes(): void
    {
        $src = file_get_contents(self::appLayoutPath());
        $this->assertIsString($src, 'AppLayout.vue must be readable.');

        $body = self::extractCanvasRoutesBody($src);
        $this->assertNotNull(
            $body,
            'AppLayout.vue must contain a `const canvasRoutes = [...]` literal.'
        );

        $routes = self::listRoutesInBody($body);
        $moduleRoutes = array_values(array_filter(
            $routes,
            static fn (string $r): bool => !in_array($r, ['/dashboard', '/login', '/404'], true)
        ));

        $this->assertNotEmpty(
            $moduleRoutes,
            'canvasRoutes was narrowed back to the vertical-slice set; PR0 extension is required. '
            . 'At least one non-vertical-slice route must be present. Found: [' . implode(', ', $routes) . '].'
        );
    }
}
