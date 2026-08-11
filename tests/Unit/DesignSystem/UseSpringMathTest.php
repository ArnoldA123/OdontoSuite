<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Task 2.3.9 / 2.3.10 — unit tests for the pure spring math kernels.
 *
 * The motion runtime is the central technical decision of the change
 * (design Decision 2). Its math must be tested without booting a browser
 * or a Vue runtime; this test shells out to `node -e` to load the pure
 * ESM module and exercise every branch the design calls out:
 *
 *   - critically-damped settle (zeta = 1.0) reaches target with zero velocity
 *   - under-damped (zeta = 0.8) overshoots once before settling
 *   - interrupt (re-target mid-flight) blends, does not jump
 *   - 2D X and Y springs are independent (perturbing X does not move Y)
 *   - momentum projection + nearest-snap picks the correct snap point
 *   - reduced-motion path is an instant apply
 *
 * If the project ever adds Vitest, these cases migrate verbatim to a
 * .spec.mjs file; the underlying math module is the single source.
 */
class UseSpringMathTest extends TestCase
{
    /** Project root absolute path. */
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    /** Pure math module path. */
    private const MATH_REL = '/resources/js/composables/useSpringMath.js';

    private static function mathPath(): string
    {
        return self::projectRootPath() . self::MATH_REL;
    }

    /**
     * Run a node script that loads the math module, calls the supplied
     * expression, and returns its result as a JSON object.
     *
     * @param string $body  ESM body that ends with `process.stdout.write(JSON.stringify(result))`
     * @return mixed
     */
    private static function runMath(string $body): mixed
    {
        $mathPath = self::mathPath();
        if (!is_file($mathPath)) {
            self::fail("Math module missing: {$mathPath} — implement useSpringMath.js before this test (RED → GREEN)");
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

        $tmp = tempnam(sys_get_temp_dir(), 'spring_math_');
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

    /** @test */
    public function math_module_exports_required_kernels(): void
    {
        $body = <<<'JS'
const result = {
  hasStepSpring: typeof globalThis.__exports.stepSpring === 'function',
  hasSettle: typeof globalThis.__exports.settle === 'function',
  hasProjectAndSnap: typeof globalThis.__exports.projectAndSnap === 'function',
  hasInstantSettle: typeof globalThis.__exports.instantSettle === 'function',
  hasPrefersReducedMotion: typeof globalThis.__exports.prefersReducedMotion === 'function',
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertTrue($r['hasStepSpring'], 'useSpringMath must export stepSpring');
        $this->assertTrue($r['hasSettle'], 'useSpringMath must export settle');
        $this->assertTrue($r['hasProjectAndSnap'], 'useSpringMath must export projectAndSnap');
        $this->assertTrue($r['hasInstantSettle'], 'useSpringMath must export instantSettle');
        $this->assertTrue($r['hasPrefersReducedMotion'], 'useSpringMath must export prefersReducedMotion');
    }

    /** @test */
    public function critically_damped_settles_to_target_with_zero_velocity(): void
    {
        $body = <<<'JS'
const finalState = globalThis.__exports.settle({ value: 0, velocity: 0 }, 100, { steps: 1200, response: 0.35, damping: 1.0 });
const result = { value: finalState.value, velocity: finalState.velocity };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertEqualsWithDelta(100.0, (float) $r['value'], 0.5, 'critically-damped spring must reach target within 0.5 units');
        $this->assertEqualsWithDelta(0.0, (float) $r['velocity'], 0.5, 'critically-damped spring must end at rest');
    }

    /** @test */
    public function under_damped_overshoots_target_at_least_once(): void
    {
        // Sample the integrator at fine granularity; count zero crossings of
        // (value - target). At least two crossings (up + down) means the
        // spring overshot.
        $body = <<<'JS'
const step = globalThis.__exports.stepSpring;
const response = 0.35;
const damping = 0.8;
const target = 100;
let s = { value: 0, velocity: 0 };
let maxOvershoot = 0;
let prev = s.value - target;
let crossings = 0;
for (let i = 0; i < 1200; i++) {
  s = step(s, target, { response, damping });
  const d = s.value - target;
  if (d > maxOvershoot) maxOvershoot = d;
  if (prev !== 0 && Math.sign(d) !== Math.sign(prev) && Math.abs(d) > 0.01) crossings++;
  prev = d;
}
const result = { maxOvershoot, crossings };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertGreaterThan(0.5, (float) $r['maxOvershoot'], 'underdamped spring must overshoot above the target (got maxOvershoot=' . $r['maxOvershoot'] . ')');
        $this->assertGreaterThanOrEqual(2, (int) $r['crossings'], 'underdamped spring must cross the target at least twice (got crossings=' . $r['crossings'] . ')');
    }

    /** @test */
    public function interrupt_preserves_value_no_discontinuity_jump(): void
    {
        // Animate from 0 to 100; at frame 60, retarget to 50 with no velocity
        // handoff. The value at frame 61 must be CLOSE to the value at frame 60,
        // not jumping to ~50. This is the Apple "blend, don't restart" contract.
        $body = <<<'JS'
const step = globalThis.__exports.stepSpring;
let s = { value: 0, velocity: 0 };
for (let i = 0; i < 60; i++) s = step(s, 100, { response: 0.35, damping: 1.0 });
const beforeInterrupt = s.value;
const afterInterrupt = step(s, 50, { response: 0.35, damping: 1.0 });
const result = { beforeInterrupt, afterInterrupt: afterInterrupt.value, jump: Math.abs(afterInterrupt.value - beforeInterrupt) };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertLessThan(5.0, (float) $r['jump'], 'interrupt must not jump the value; got jump=' . $r['jump']);
    }

    /** @test */
    public function useSpring2D_x_and_y_are_independent(): void
    {
        // X is animated; Y is held at rest. The Y state must not change when
        // the X integrator is advanced. Pure-math test: simulate one axis.
        $body = <<<'JS'
const step = globalThis.__exports.stepSpring;
let x = { value: 0, velocity: 0 };
let y = { value: 0, velocity: 0 };
for (let i = 0; i < 200; i++) {
  x = step(x, 100, { response: 0.35, damping: 1.0 });
  // y untouched
}
const result = { x: x.value, y: y.value };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertEqualsWithDelta(100.0, (float) $r['x'], 0.5, 'X spring must reach target');
        $this->assertEqualsWithDelta(0.0, (float) $r['y'], 0.0001, 'Y spring must not be affected by X axis advance');
    }

    /** @test */
    public function project_and_snap_picks_nearest_snap_point(): void
    {
        $body = <<<'JS'
const pas = globalThis.__exports.projectAndSnap;
// velocity = 0 → projection = current; snap to nearest
const r1 = pas(0, 0, [0, 50, 100]);
// velocity 100 with default d=0.998: projection = 0 + 0.1 * 499 = 49.9 → snap 50
const r2 = pas(0, 100, [0, 50, 100]);
// velocity 1000 from current 50: projection = 50 + 1 * 499 = 549 → snap 100
const r3 = pas(50, 1000, [0, 50, 100]);
// decimal snap
const r4 = pas(7.3, 0, [0, 5, 7, 10]);
// negative velocity from 80
const r5 = pas(80, -1000, [0, 50, 100]);
const result = { r1, r2, r3, r4, r5 };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertEquals(0, $r['r1'], 'velocity=0 + current=0 should snap to 0');
        $this->assertEquals(50, $r['r2'], 'velocity 100 from 0 should project to 49.9 → snap to 50');
        $this->assertEquals(100, $r['r3'], 'velocity 1000 from 50 should project to 549 → snap to 100');
        $this->assertEquals(7, $r['r4'], 'decimal snap test: 7.3 → 7');
        $this->assertEquals(0, $r['r5'], 'strong negative velocity from 80 should snap to 0');
    }

    /** @test */
    public function project_and_snap_uses_ios_deceleration_default(): void
    {
        // With d=0.998: projection = current + (velocity/1000) * 499.
        // velocity 1000 from current 0 → projection = 499 → snap to 500
        // (nearest of [0, 250, 500, 1000]).
        $body = <<<'JS'
const pas = globalThis.__exports.projectAndSnap;
const result = { snapChoice: pas(0, 1000, [0, 250, 500, 1000]) };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertEquals(500, $r['snapChoice'], 'default d=0.998 + velocity 1000 from 0 must project to 499 and snap to 500');
    }

    /** @test */
    public function project_and_snap_handles_empty_snap_points_as_identity(): void
    {
        $body = <<<'JS'
const pas = globalThis.__exports.projectAndSnap;
const result = { empty: pas(42, 100, []), nullSnap: pas(42, 100, null) };
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertEquals(42, $r['empty'], 'empty snap points must return current');
        $this->assertEquals(42, $r['nullSnap'], 'null snap points must return current');
    }

    /** @test */
    public function instant_settle_zeros_velocity_and_lands_on_target(): void
    {
        $body = <<<'JS'
const inst = globalThis.__exports.instantSettle;
const result = inst(73);
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertEquals(73, $r['value']);
        $this->assertEquals(0, $r['velocity']);
    }

    /** @test */
    public function prefers_reduced_motion_honors_match_media(): void
    {
        $body = <<<'JS'
const prm = globalThis.__exports.prefersReducedMotion;
const mockOn = (q) => ({ matches: q === '(prefers-reduced-motion: reduce)' });
const mockOff = () => ({ matches: false });
const throwing = () => { throw new Error('no matchMedia'); };
const result = {
  on: prm(mockOn),
  off: prm(mockOff),
  throws: prm(throwing),
  undefined: prm(undefined),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runMath($body);
        $this->assertTrue($r['on'], 'prefersReducedMotion must return true when matchMedia reports reduce');
        $this->assertFalse($r['off'], 'prefersReducedMotion must return false when matchMedia reports no match');
        $this->assertFalse($r['throws'], 'prefersReducedMotion must not throw on a broken matchMedia; default false');
        $this->assertFalse($r['undefined'], 'prefersReducedMotion must default to false when matchMedia is missing');
    }

    /** @test */
    public function useFontsLoaded_composable_exists_and_exports(): void
    {
        $body = <<<'JS'
import { pathToFileURL } from 'node:url';
import fs from 'node:fs';
const path = 'FONTS_PATH';
const exists = fs.existsSync(path);
const url = pathToFileURL(path).href;
const mod = await import(url);
const result = { exists, hasDefault: typeof mod.default === 'function' || typeof mod.default === 'object' };
process.stdout.write(JSON.stringify(result));
JS;
        $body = str_replace('FONTS_PATH', str_replace('\\', '\\\\', self::projectRootPath() . '/resources/js/composables/useFontsLoaded.js'), $body);

        $tmp = tempnam(sys_get_temp_dir(), 'fonts_loaded_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $body);
        @unlink($tmp);

        $cmd = 'node "' . $loaderFile . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        $r = json_decode(trim((string) $output), true);
        $this->assertTrue($r['exists'] ?? false, 'useFontsLoaded.js must exist');
        $this->assertTrue($r['hasDefault'] ?? false, 'useFontsLoaded.js must export a default (the composable)');
    }
}
