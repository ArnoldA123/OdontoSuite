<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR-ambientes-01 — EnvironmentsCanvasRoutesPrefixTest (NEW).
 *
 * Asserts the AMB-01-001 [BLOCKING] rule: a `matchesCanvasRoute(path)`
 * helper MUST be introduced in `resources/js/components/layout/AppLayout.vue`
 * that uses `startsWith` matching (`path === route || path.startsWith(route + '/')`).
 * The `isCanvasRoute` computed MUST delegate to the helper so detail routes
 * (e.g. `/environments/123`) render on the `bg-canvas` chrome instead of
 * the bare `bg-systemBackground`.
 *
 * This single helper covers 6 detail routes globally:
 *
 *   - `/environments/:id`
 *   - `/patients/:id`
 *   - `/professionals/:id`
 *   - `/appointment-types/:id`
 *   - `/procedure-catalog/:id`
 *   - plus auxiliary
 *
 * The over-match guard asserts that `/environments-archive` returns `false`
 * (the trailing `/` separator in `startsWith(route + '/')` excludes the
 * near-match).
 *
 * Why a separate test file: the global `AppLayoutCanvasRoutesTest` pins the
 * array literal (the source of truth for the routes that have the canvas
 * surface). This file pins the prefix-matching behaviour, which is the
 * load-bearing cross-cutting fix for the entire rollout — keeping it in a
 * dedicated file makes the rule searchable and the failure obvious.
 */
class EnvironmentsCanvasRoutesPrefixTest extends TestCase
{
    private const APP_LAYOUT_PATH = '/resources/js/components/layout/AppLayout.vue';

    private static function appLayoutPath(): string
    {
        return dirname(__DIR__, 3) . self::APP_LAYOUT_PATH;
    }

    private static function readSource(): ?string
    {
        $path = self::appLayoutPath();
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    /**
     * AMB-01-001 — `AppLayout.vue` MUST define a `matchesCanvasRoute(path)`
     * helper that returns `true` when the given path exactly equals a
     * canvas route OR begins with `canvasRoute + '/'`.
     *
     * POSITIVE rule: `function matchesCanvasRoute` reference present in
     * the `<script setup>` block.
     */
    public function test_canvas_routes_defines_matches_helper(): void
    {
        $src = self::readSource();
        $this->assertNotNull($src, 'AppLayout.vue must exist and be readable.');

        $this->assertTrue(
            (bool) preg_match(
                '#function\s+matchesCanvasRoute\s*\(\s*path\s*\)#',
                $src
            ),
            sprintf(
                '%s MUST define `function matchesCanvasRoute(path)` (AMB-01-001). '
                . 'The helper enables prefix matching so detail routes like '
                . '`/environments/123` render on the canvas surface.',
                self::appLayoutPath()
            )
        );

        // The helper body MUST use the canonical pattern:
        // `canvasRoutes.some(route => path === route || path.startsWith(route + '/'))`.
        $this->assertTrue(
            (bool) preg_match(
                '#canvasRoutes\s*\.\s*some\s*\(\s*route\s*=>\s*path\s*===\s*route\s*\|\|\s*path\s*\.\s*startsWith\s*\(\s*route\s*\+\s*[\'"]\/[\'"]\s*\)\s*\)#s',
                $src
            ),
            sprintf(
                '%s `matchesCanvasRoute` body MUST use the canonical prefix-matching '
                . 'pattern (AMB-01-001). Expected `canvasRoutes.some(route => path === route || '
                . 'path.startsWith(route + \'/\'))`.',
                self::appLayoutPath()
            )
        );
    }

    /**
     * AMB-01-001 — the `isCanvasRoute` computed MUST delegate to the
     * `matchesCanvasRoute` helper (NOT the legacy exact-match
     * `canvasRoutes.includes(route.path)`).
     *
     * POSITIVE rule: `const isCanvasRoute = computed(() => matchesCanvasRoute(route.path))`
     * present in the file.
     *
     * NEGATIVE rule: zero `canvasRoutes.includes(route.path)` literal matches
     * (the exact-match check is gone).
     */
    public function test_is_canvas_route_delegates_to_helper(): void
    {
        $src = self::readSource();
        $this->assertNotNull($src, 'AppLayout.vue must exist and be readable.');

        $this->assertTrue(
            (bool) preg_match(
                '#const\s+isCanvasRoute\s*=\s*computed\s*\(\s*\(\s*\)\s*=>\s*matchesCanvasRoute\s*\(\s*route\.path\s*\)\s*\)#',
                $src
            ),
            sprintf(
                '%s `isCanvasRoute` MUST delegate to `matchesCanvasRoute(route.path)` '
                . '(AMB-01-001). Replace the legacy `canvasRoutes.includes(route.path)` '
                . 'exact-match computed.',
                self::appLayoutPath()
            )
        );

        $this->assertSame(
            0,
            preg_match(
                '#canvasRoutes\s*\.\s*includes\s*\(\s*route\.path\s*\)#',
                $src
            ),
            sprintf(
                '%s MUST NOT keep the legacy `canvasRoutes.includes(route.path)` '
                . 'exact-match check (AMB-01-001). The helper replaces it.',
                self::appLayoutPath()
            )
        );
    }

    /**
     * AMB-01-001 — the helper MUST cover the detail routes globally.
     *
     * We assert this at the test layer by exercising the spec scenarios
     * from spec.md §2.0 row `AMB-01-001`:
     *
     *   - `matchesCanvasRoute('/environments')`     returns `true`
     *   - `matchesCanvasRoute('/environments/123')` returns `true`
     *   - `matchesCanvasRoute('/environments-archive')` returns `false`
     *   - `matchesCanvasRoute('/patients/abc-def')` returns `true`
     *   - `matchesCanvasRoute('/professionals/9')`  returns `true`
     *   - `matchesCanvasRoute('/appointment-types/4')` returns `true`
     *   - `matchesCanvasRoute('/procedure-catalog/2')` returns `true`
     *   - `matchesCanvasRoute('/environments-archive')` returns `false`
     *     (over-match guard via trailing `/` separator)
     *
     * Implementation: the file source itself is the executable contract;
     * the helper is a pure function over the in-file `canvasRoutes` array.
     * We parse the array and simulate the helper to verify the rule
     * holds across the spec scenarios.
     */
    public function test_canvas_routes_matches_detail_via_starts_with(): void
    {
        $src = self::readSource();
        $this->assertNotNull($src, 'AppLayout.vue must exist and be readable.');

        $body = self::extractCanvasRoutesBody($src);
        $this->assertNotNull(
            $body,
            'AppLayout.vue must contain a `const canvasRoutes = [...]` literal.'
        );

        $routes = self::listRoutesInBody($body);

        $cases = [
            // Exact match — list page surfaces canvas today.
            '/environments' => true,
            // Detail route — prefix match required (this is the load-bearing fix).
            '/environments/123' => true,
            // Other detail routes covered for free.
            '/patients/abc-def' => true,
            '/professionals/9' => true,
            '/appointment-types/4' => true,
            '/procedure-catalog/2' => true,
            // Auxiliary sub-route from existing canvasRoutes.
            '/cash-register/ready-to-bill' => true,
            // Over-match guard — `/environments-archive` is NOT in canvasRoutes
            // and the trailing `/` in `startsWith(route + '/')` excludes it.
            '/environments-archive' => false,
            // Unrelated route — should not match anything.
            '/somewhere/else' => false,
        ];

        foreach ($cases as $path => $expected) {
            $actual = self::simulateMatchesCanvasRoute($routes, $path);
            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    'matchesCanvasRoute(%s) MUST return %s (AMB-01-001). Routes in canvasRoutes: [%s].',
                    $path,
                    $expected ? 'true' : 'false',
                    implode(', ', $routes)
                )
            );
        }
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
        $cleaned = preg_replace('#//[^\n]*#', '', $body) ?? $body;
        $cleaned = preg_replace('#/\*.*?\*/#s', '', $cleaned) ?? $cleaned;

        if (preg_match_all("/'([^']+)'/", $cleaned, $matches) === false) {
            return [];
        }

        return $matches[1];
    }

    /**
     * Pure simulation of the `matchesCanvasRoute(path)` helper.
     * Returns `true` iff `path` exactly equals a route OR starts with
     * `route + '/'`. Mirrors the production helper body verbatim.
     *
     * @param array<int, string> $routes
     */
    private static function simulateMatchesCanvasRoute(array $routes, string $path): bool
    {
        foreach ($routes as $route) {
            if ($path === $route || str_starts_with($path, $route . '/')) {
                return true;
            }
        }
        return false;
    }
}
