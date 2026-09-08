<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * PR1 / Sub-phase 1.2 — pure-math + state-machine tests for the
 * `useMagneticHover` composable.
 *
 * The composable lives at `resources/js/composables/useMagneticHover.js`
 * and exposes two testable surfaces:
 *   - `computeOffset(mx, my)` — a pure clamp helper that returns the
 *     clamped `(dx, dy)` offset given a synthetic cursor position and
 *     element bounds. Exported as a named export for unit-test access.
 *   - The composable itself, which gates `onEnter` on a `prefers-reduced-motion`
 *     matchMedia mock.
 *
 * Test pattern: shells out to `node -e` (via a temp .mjs loader) to import
 * the ESM module and call the helper. Precedent:
 * `tests/Unit/DesignSystem/UseSpringMathTest.php`.
 *
 * Source-inspection tests pin the wiring contract: Button.vue MUST consume
 * both the `data-magnetic` selector and the `var(--spring-magnet-x)`
 * custom property so the magnetic effect actually renders at runtime.
 */
class MagneticHoverTest extends TestCase
{
    /** Project root absolute path. */
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    /** Composable module path. */
    private const COMPOSABLE_REL = '/resources/js/composables/useMagneticHover.js';
    /** Pure-math module path (Node-loadable, no Vue dependency). */
    private const MATH_REL = '/resources/js/composables/magneticHoverMath.js';
    private const BUTTON_REL = '/resources/js/components/ui/Button.vue';

    private static function composablePath(): string
    {
        return self::projectRootPath() . self::COMPOSABLE_REL;
    }

    private static function mathPath(): string
    {
        return self::projectRootPath() . self::MATH_REL;
    }

    private static function buttonPath(): string
    {
        return self::projectRootPath() . self::BUTTON_REL;
    }

    /**
     * Sum ripgrep --count-matches results across one or more paths.
     *
     * @param string $pattern
     * @param string ...$paths
     * @return int
     */
    private static function grepCount(string $pattern, string ...$paths): int
    {
        $args = array_map('escapeshellarg', $paths);
        $pathsPart = implode(' ', $args);
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            $pathsPart
        );
        $output = (string) shell_exec($cmd);
        if ($output === '') {
            return 0;
        }
        $total = 0;
        foreach (preg_split('/\r?\n/', $output) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $count = (int) (str_contains($line, ':') ? substr($line, strrpos($line, ':') + 1) : $line);
            $total += $count;
        }
        return $total;
    }

    /**
     * Run a node script that loads the pure-math module (Node-loadable, no
     * Vue dependency), calls the supplied expression, and returns its
     * result as a JSON object. Mirrors `UseSpringMathTest::runMath`.
     *
     * @param string $body  ESM body that ends with `process.stdout.write(JSON.stringify(result))`
     * @return mixed
     */
    private static function runMath(string $body): mixed
    {
        $mathPath = self::mathPath();
        if (!is_file($mathPath)) {
            self::fail("Math module missing: {$mathPath} — implement magneticHoverMath.js before this test (RED → GREEN)");
        }

        $escapedPath = addcslashes($mathPath, "'\\");
        $loader = <<<JS
import { pathToFileURL } from 'node:url';
const mod = await import(pathToFileURL('TARGET_PATH').href);
globalThis.__exports = { ...mod };
BODY_PLACEHOLDER
JS;
        $loader = str_replace('TARGET_PATH', $escapedPath, $loader);
        $loader = str_replace('BODY_PLACEHOLDER', $body, $loader);

        $tmp = tempnam(sys_get_temp_dir(), 'mag_hover_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $loader);
        @unlink($tmp);

        $cmd = 'node "' . $loaderFile . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        if ($output === null || $output === '') {
            self::fail('node -e produced no output. body: ' . $body);
        }

        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            self::fail('node output had no JSON object: ' . $output);
        }
        $decoded = json_decode(substr($output, $jsonStart), true);
        return $decoded;
    }

    /**
     * @deprecated kept for compatibility with the prior signature;
     *             delegates to runMath. The pure-math module is the
     *             authoritative source of truth for the helper.
     */
    private static function runComposable(string $body): mixed
    {
        return self::runMath($body);
    }

    /**
     * Helper: synthesise bounds and a cursor, return the helper's
     * `(dx, dy)` result. The helper is the pure `computeOffset(mx, my)`
     * exported from the composable; we pass it the bounds via a small
     * indirect: the helper expects `bounds` to be in scope. So instead,
     * the test invokes the composable's exported helper via a wrapper
     * that closes over the bounds.
     *
     * Strategy: the test calls computeOffset via a stub. The composable
     * exports a function `(mx, my) => ({dx, dy})`. To make this testable
     * without a DOM, we expose a `computeOffsetForBounds` helper via the
     * ESM loader that closes over the bounds literal:
     *
     *   globalThis.__compute = (mx, my) =>
     *     mod.computeOffset(mx, my, { left, top, width, height, maxDistanceFactor });
     *
     * If the composable's `computeOffset` signature is `(mx, my)` with the
     * bounds captured via a closure (the design doc's signature), the test
     * binds bounds through a wrapper function that calls the helper.
     *
     * @param array{bx:int,by:int,bw:int,bh:int,max:float} $bounds
     * @param int $mx
     * @param int $my
     * @return array{dx:float,dy:float}
     */
    private static function callOffset(array $bounds, int $mx, int $my): array
    {
        $body = <<<'JS'
const __compute = globalThis.__exports.computeOffset;
if (typeof __compute !== 'function') {
  process.stdout.write(JSON.stringify({ error: 'no computeOffset named export' }));
} else {
  const result = __compute(
    { left: BOUNDS.bx, top: BOUNDS.by, width: BOUNDS.bw, height: BOUNDS.bh },
    MX,
    MY,
    BOUNDS.max
  );
  process.stdout.write(JSON.stringify(result));
}
JS;
        $body = str_replace(
            ['BOUNDS.bx', 'BOUNDS.by', 'BOUNDS.bw', 'BOUNDS.bh', 'BOUNDS.max', 'MX', 'MY'],
            [
                var_export($bounds['bx'], true),
                var_export($bounds['by'], true),
                var_export($bounds['bw'], true),
                var_export($bounds['bh'], true),
                var_export($bounds['max'], true),
                (string) $mx,
                (string) $my,
            ],
            $body
        );
        return self::runMath($body);
    }

    /**
     * The first test in the TDD cycle: the pure clamp helper MUST return
     * `(0, 0)` when the cursor is at the centre of the bounds (zero
     * offset, below the max-distance threshold). This pins the
     * mathematical contract: the magnetic composable does not "snap" to
     * zero when the cursor is over the centre — it returns the raw zero
     * vector, which the spring then uses as a target.
     *
     * Spec M1 + M2 — offset is clamped to 40% of the smaller dimension.
     * When the cursor is exactly centred, mag = 0 < M, so no clamping
     * applies and dx/dy are both exactly 0.
     *
     * @test
     */
    public function clamp_returns_zero_when_inside_max_distance(): void
    {
        // Bounds 100x100 anchored at (0,0). Cursor at (50,50) → exact centre.
        $r = self::callOffset(
            ['bx' => 0, 'by' => 0, 'bw' => 100, 'bh' => 100, 'max' => 0.4],
            50,
            50
        );
        $this->assertSame(0.0, (float) $r['dx'], 'dx must be exactly 0 when cursor is at centre');
        $this->assertSame(0.0, (float) $r['dy'], 'dy must be exactly 0 when cursor is at centre');
    }

    /**
     * TRIANGULATE: a cursor FAR outside the bounds (200,200 on a 100x100
     * box anchored at 0,0) produces raw dx=150, dy=150. Magnitude
     * ≈ 212.13, which exceeds the 40% cap of M=40. The helper MUST clamp
     * the offset to (40 * 150/212.13, 40 * 150/212.13) ≈ (28.28, 28.28),
     * preserving the direction.
     *
     * @test
     */
    public function clamp_caps_magnitude_at_max_distance_factor(): void
    {
        // Bounds 100x100 anchored at (0,0); cursor at (200,200) — well outside.
        $r = self::callOffset(
            ['bx' => 0, 'by' => 0, 'bw' => 100, 'bh' => 100, 'max' => 0.4],
            200,
            200
        );

        // raw dx = 150, dy = 150 → mag = sqrt(150^2 + 150^2) ≈ 212.13.
        // M = min(100, 100) * 0.4 = 40.
        // dx_clamped = (150 / 212.13) * 40 ≈ 28.284.
        $this->assertEqualsWithDelta(
            28.284,
            (float) $r['dx'],
            0.5,
            'dx must be clamped to ~28.28 when raw magnitude exceeds M (got dx=' . $r['dx'] . ')'
        );
        $this->assertEqualsWithDelta(
            28.284,
            (float) $r['dy'],
            0.5,
            'dy must be clamped to ~28.28 when raw magnitude exceeds M (got dy=' . $r['dy'] . ')'
        );

        // And the clamped magnitude must be exactly M (the cap).
        $clampedMag = sqrt((float) $r['dx'] ** 2 + (float) $r['dy'] ** 2);
        $this->assertEqualsWithDelta(40.0, $clampedMag, 0.01, 'clamped magnitude must equal M=40');
    }

    /**
     * TRIANGULATE: zero-bounds edge case. If the button has not yet
     * laid out (width=0, height=0) — e.g. the user is on a slow
     * connection and the button flashes for one frame — the magnetic
     * helper MUST short-circuit and return `(0, 0)` rather than dividing
     * by zero.
     *
     * @test
     */
    public function clamp_handles_zero_bounds(): void
    {
        $r = self::callOffset(
            ['bx' => 0, 'by' => 0, 'bw' => 0, 'bh' => 0, 'max' => 0.4],
            50,
            50
        );
        $this->assertSame(0.0, (float) $r['dx'], 'zero-bounds dx must be 0');
        $this->assertSame(0.0, (float) $r['dy'], 'zero-bounds dy must be 0');
    }

    /**
     * TRIANGULATE: under `prefers-reduced-motion: reduce`, the
     * composable's `onEnter` callback MUST NOT activate the spring or
     * write any offset — it short-circuits. The composable file lives
     * behind a Vue import (not Node-loadable), so we pin the contract
     * by source-inspecting the composable for the `matchMedia` probe
     * with the literal `(prefers-reduced-motion: reduce)` query string.
     * If someone deletes the probe, the bypass silently no-ops at
     * runtime — the most expensive regression.
     *
     * @test
     */
    public function reduced_motion_bypass_returns_inert(): void
    {
        $this->assertFileExists(
            self::composablePath(),
            'resources/js/composables/useMagneticHover.js must exist for the bypass probe to be wired'
        );

        // Probe 1 — the reduced-motion query string MUST appear in the
        // composable source so the bypass is wired.
        $rmCount = self::grepCount('prefers-reduced-motion: reduce', self::composablePath());
        $this->assertGreaterThanOrEqual(
            1,
            $rmCount,
            'useMagneticHover.js must probe matchMedia for (prefers-reduced-motion: reduce) — bypass contract (spec M4)'
        );

        // Probe 2 — the coarse-pointer query string MUST also appear so
        // touch devices get the inert fallback (spec M5).
        $cpCount = self::grepCount('pointer: coarse', self::composablePath());
        $this->assertGreaterThanOrEqual(
            1,
            $cpCount,
            'useMagneticHover.js must probe matchMedia for (pointer: coarse) — touch bypass contract (spec M5)'
        );

        // Probe 3 — the composable file must re-export computeOffset so
        // the unit-test surface stays accessible.
        $coCount = self::grepCount('computeOffset', self::composablePath());
        $this->assertGreaterThanOrEqual(
            1,
            $coCount,
            'useMagneticHover.js must reference computeOffset (re-export the pure helper)'
        );
    }

    /**
     * Source-inspection contract: Button.vue MUST consume the
     * `data-magnetic` selector AND the `var(--spring-magnet-x)`
     * custom property. Without BOTH, the magnetic effect renders no
     * transform at runtime even though the composable fires.
     *
     * @test
     */
    public function button_vue_consumes_spring_magnet_vars(): void
    {
        $this->assertFileExists(
            self::buttonPath(),
            'resources/js/components/ui/Button.vue must exist for the additive magnetic hook'
        );

        // Pin the selector. The design calls for `button[data-magnetic='true']`.
        // Either the bracketed form (attribute selector) or the bare class
        // name is acceptable; assert the literal string is present.
        $this->assertGreaterThanOrEqual(
            1,
            self::grepCount("data-magnetic", self::buttonPath()),
            'Button.vue must reference the data-magnetic selector'
        );

        // Pin the CSS-var consumption. The composable writes
        // --spring-magnet-x and --spring-magnet-y to the element; Button.vue
        // must consume them in its transform.
        $this->assertGreaterThanOrEqual(
            1,
            self::grepCount("spring-magnet-x", self::buttonPath()),
            'Button.vue must consume var(--spring-magnet-x) so the magnetic effect renders'
        );
    }
}
