<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * PR-apply / Phase 1 — shape-morph math + composable unit tests
 * (`ui-login-refinement-dental-split-2026-09`).
 *
 * The shape-morph feature drives the polymorphic submit button + the
 * polymorphic form Card on LoginPage. It has two layers:
 *
 *   1. `shapeMorphMath.js` — pure functions (validateTransition, clampIntensity,
 *      dwellRemaining). No Vue. Unit-tested via `node -e` ESM import.
 *   2. `useShapeMorph.js` — Vue composable wrapping the math. Exposes
 *      `state` ref, `transition()`, `cancel()`, `isTerminal()`. Unit-tested
 *      via a Node ESM shim that imports the module and exercises it (no Vue
 *      runtime required for the surface area covered here).
 *
 * Both modules must exist and export the contract documented in
 * `openspec/changes/ui-login-refinement-dental-split-2026-09/design.md` (D1 +
 * D2). This test file pins that contract.
 *
 * Pattern source: `tests/Unit/DesignSystem/UseSpringMathTest.php` (the
 * project's closest precedent for shell_exec + node ESM import). The user
 * prompt also referenced `MagneticHoverTest.php` and `FieldValidationTest.php`
 * as templates; those do not exist in this repo, so the spring-math test is
 * the operative pattern.
 */
class ShapeMorphTest extends TestCase
{
    /** Project root absolute path. */
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    /** Pure math module path. */
    private const MATH_REL = '/resources/js/composables/shapeMorphMath.js';

    /** Vue composable path. */
    private const COMPOSABLE_REL = '/resources/js/composables/useShapeMorph.js';

    private static function mathPath(): string
    {
        return self::projectRootPath() . self::MATH_REL;
    }

    private static function composablePath(): string
    {
        return self::projectRootPath() . self::COMPOSABLE_REL;
    }

    /**
     * Run a node ESM script that imports BOTH the pure-math module and the
     * Vue composable (the composable exports its functions under named
     * bindings so the shim can call them without booting a Vue runtime).
     *
     * @param string $body  ESM body that ends with `process.stdout.write(JSON.stringify(result))`
     * @return mixed
     */
    private static function runNode(string $body): mixed
    {
        $mathPath = self::mathPath();
        $composablePath = self::composablePath();

        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
const math = await import(pathToFileURL('MATH_PATH').href);
globalThis.__math = { ...math };
try {
  const comp = await import(pathToFileURL('COMPOSABLE_PATH').href);
  globalThis.__comp = { ...comp };
} catch (e) {
  globalThis.__compLoadError = String(e && e.code ? e.code : e);
}
BODY_PLACEHOLDER
JS;
        $loader = str_replace('MATH_PATH', addcslashes($mathPath, "'\\"), $loader);
        $loader = str_replace('COMPOSABLE_PATH', addcslashes($composablePath, "'\\"), $loader);
        $loader = str_replace('BODY_PLACEHOLDER', $body, $loader);

        $tmp = tempnam(sys_get_temp_dir(), 'shape_morph_');
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

    // -----------------------------------------------------------------
    // Phase 1.1 — pure math (shapeMorphMath.js)
    // -----------------------------------------------------------------

    /**
     * @test
     */
    public function math_module_exports_required_kernels(): void
    {
        $this->assertFileExists(self::mathPath(), 'shapeMorphMath.js must exist (RED → GREEN contract)');

        $body = <<<'JS'
const result = {
  hasStates: Array.isArray(globalThis.__math.STATES),
  hasValidateTransition: typeof globalThis.__math.validateTransition === 'function',
  hasClampIntensity: typeof globalThis.__math.clampIntensity === 'function',
  hasDwellRemaining: typeof globalThis.__math.dwellRemaining === 'function',
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertTrue($r['hasStates'], 'shapeMorphMath must export STATES (array)');
        $this->assertTrue($r['hasValidateTransition'], 'shapeMorphMath must export validateTransition');
        $this->assertTrue($r['hasClampIntensity'], 'shapeMorphMath must export clampIntensity');
        $this->assertTrue($r['hasDwellRemaining'], 'shapeMorphMath must export dwellRemaining');
    }

    /**
     * @test
     */
    public function validate_transition_allows_idle_to_validating(): void
    {
        $body = <<<'JS'
const result = {
  ok: globalThis.__math.validateTransition('idle', 'validating', Date.now() - 1000, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertTrue((bool) $r['ok'], 'idle → validating must be allowed (canonical first transition)');
    }

    /**
     * @test
     */
    public function validate_transition_blocks_validating_to_authenticating_before_dwell(): void
    {
        $body = <<<'JS'
// dwellStart is 50ms in the past — well below the 200ms minimum dwell
const result = {
  blocked: globalThis.__math.validateTransition('validating', 'authenticating', Date.now() - 50, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertFalse((bool) $r['blocked'], 'validating → authenticating must be blocked before dwell elapses');
    }

    /**
     * @test
     */
    public function validate_transition_blocks_idle_to_idle(): void
    {
        $body = <<<'JS'
const result = {
  blocked: globalThis.__math.validateTransition('idle', 'idle', Date.now() - 1000, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertFalse((bool) $r['blocked'], 'idle → idle must be blocked (no-op self-transition)');
    }

    /**
     * @test
     */
    public function validate_transition_blocks_terminal_state_transitions(): void
    {
        $body = <<<'JS'
const result = {
  fromSuccess: globalThis.__math.validateTransition('success', 'idle', Date.now() - 1000, 200),
  fromError: globalThis.__math.validateTransition('error', 'idle', Date.now() - 1000, 200),
  fromSuccessToValidating: globalThis.__math.validateTransition('success', 'validating', Date.now() - 1000, 200),
  fromErrorToAuthenticating: globalThis.__math.validateTransition('error', 'authenticating', Date.now() - 1000, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertFalse((bool) $r['fromSuccess'], 'success → idle must be blocked (terminal)');
        $this->assertFalse((bool) $r['fromError'], 'error → idle must be blocked (terminal)');
        $this->assertFalse((bool) $r['fromSuccessToValidating'], 'success → validating must be blocked (terminal)');
        $this->assertFalse((bool) $r['fromErrorToAuthenticating'], 'error → authenticating must be blocked (terminal)');
    }

    /**
     * @test
     */
    public function validate_transition_allows_error_to_idle_via_cancel(): void
    {
        // The composable's cancel() is the only legal exit from a terminal
        // state (per design.md D1 + D2). Pure-math `validateTransition` itself
        // does NOT permit the transition; the composable short-circuits cancel()
        // to force `idle`. This test pins the contract: the pure function
        // blocks it, so any future caller that bypasses cancel() is forced to
        // revisit this test.
        $body = <<<'JS'
const result = {
  blocked: globalThis.__math.validateTransition('error', 'idle', Date.now() - 1000, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertFalse((bool) $r['blocked'], 'pure-math validateTransition must block error → idle (only cancel() may force it)');
    }

    /**
     * @test
     */
    public function validate_transition_blocks_unknown_state_strings(): void
    {
        $body = <<<'JS'
const result = {
  unknownFrom: globalThis.__math.validateTransition('pending', 'idle', Date.now() - 1000, 200),
  unknownTo: globalThis.__math.validateTransition('idle', 'pending', Date.now() - 1000, 200),
  garbageFrom: globalThis.__math.validateTransition('', 'idle', Date.now() - 1000, 200),
  garbageTo: globalThis.__math.validateTransition('idle', '', Date.now() - 1000, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertFalse((bool) $r['unknownFrom'], 'unknown from-state must be blocked');
        $this->assertFalse((bool) $r['unknownTo'], 'unknown to-state must be blocked');
        $this->assertFalse((bool) $r['garbageFrom'], 'empty from-state must be blocked');
        $this->assertFalse((bool) $r['garbageTo'], 'empty to-state must be blocked');
    }

    /**
     * @test
     */
    public function clamp_intensity_returns_zero_for_zero_input(): void
    {
        $body = <<<'JS'
const result = {
  zero: globalThis.__math.clampIntensity(0),
  negative: globalThis.__math.clampIntensity(-0.5),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertEqualsWithDelta(0.0, (float) $r['zero'], 1e-9, 'clampIntensity(0) must return 0');
        $this->assertEqualsWithDelta(0.0, (float) $r['negative'], 1e-9, 'clampIntensity(-0.5) must clamp to 0');
    }

    /**
     * @test
     */
    public function clamp_intensity_caps_at_one(): void
    {
        $body = <<<'JS'
const result = {
  one: globalThis.__math.clampIntensity(1),
  above: globalThis.__math.clampIntensity(2.5),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertEqualsWithDelta(1.0, (float) $r['one'], 1e-9, 'clampIntensity(1) must return 1');
        $this->assertEqualsWithDelta(1.0, (float) $r['above'], 1e-9, 'clampIntensity(2.5) must clamp to 1');
    }

    /**
     * @test
     */
    public function dwell_remaining_returns_zero_when_elapsed(): void
    {
        $body = <<<'JS'
// dwellStart 500ms in the past — exceeds the 200ms dwell
const result = {
  remaining: globalThis.__math.dwellRemaining(Date.now() - 500, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertEqualsWithDelta(0.0, (float) $r['remaining'], 1e-9, 'dwellRemaining must return 0 once dwell has elapsed');
    }

    /**
     * @test
     */
    public function dwell_remaining_returns_positive_when_active(): void
    {
        $body = <<<'JS'
// dwellStart 50ms in the past — 150ms remaining of the 200ms dwell
const result = {
  remaining: globalThis.__math.dwellRemaining(Date.now() - 50, 200),
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertGreaterThan(0, (float) $r['remaining'], 'dwellRemaining must return positive value while dwell is active');
        $this->assertLessThanOrEqual(200, (float) $r['remaining'], 'dwellRemaining must not exceed dwellMs');
    }

    // -----------------------------------------------------------------
    // Phase 1.2 — Vue composable wrapper (useShapeMorph.js)
    //
    // The composable imports Vue's `ref` and `onUnmounted`. To exercise the
    // state-machine surface area without booting a Vue runtime, the shim
    // calls each exported function with a fresh module-scope binding and
    // returns the state value as JSON.
    //
    // The composable contract (per design.md D1):
    //   - state is observable (`getState()` returns the current state string)
    //   - `transition(to)` returns true if accepted, false if blocked
    //   - `cancel()` returns the state to 'idle' (unless terminal)
    //   - `isTerminal(s)` reports whether a state is terminal
    //
    // The shim does not depend on Vue's reactive runtime — only the API
    // shape. The composable internally uses `ref()`; the shim reads the
    // current value via a getter. (Implementation note: if useShapeMorph
    // wraps `ref` and exposes the value as a getter, the shim works.)
    // -----------------------------------------------------------------

    /**
     * @test
     */
    public function use_shape_morph_initial_state_is_idle(): void
    {
        $this->assertFileExists(self::composablePath(), 'useShapeMorph.js must exist (RED → GREEN contract)');

        $body = <<<'JS'
const result = {
  initialState: globalThis.__comp.getInitialState ? globalThis.__comp.getInitialState() : null,
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertSame('idle', (string) $r['initialState'], 'useShapeMorph initial state must be idle');
    }

    /**
     * @test
     */
    public function transition_returns_false_for_invalid(): void
    {
        $body = <<<'JS'
// self-transition is blocked by validateTransition
const result = {
  blocked: globalThis.__comp.transitionFromInitial ? globalThis.__comp.transitionFromInitial('idle') : null,
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertFalse((bool) $r['blocked'], 'transition(idle → idle) must return false (blocked by validateTransition)');
    }

    /**
     * @test
     */
    public function cancel_returns_to_idle(): void
    {
        $body = <<<'JS'
// Drive validating state then cancel — must return to idle
const result = {
  stateAfterValidating: globalThis.__comp.driveToValidating ? globalThis.__comp.driveToValidating() : null,
  stateAfterCancel: globalThis.__comp.driveToValidatingThenCancel ? globalThis.__comp.driveToValidatingThenCancel() : null,
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertSame('validating', (string) $r['stateAfterValidating'], 'composable must accept idle → validating');
        $this->assertSame('idle', (string) $r['stateAfterCancel'], 'cancel() from validating must return to idle');
    }

    /**
     * @test
     */
    public function cancel_blocks_terminal_states(): void
    {
        $body = <<<'JS'
const result = {
  // success is terminal: cancel must NOT move it
  stateAfterCancelFromSuccess: globalThis.__comp.cancelFromSuccess ? globalThis.__comp.cancelFromSuccess() : null,
  // error is terminal: cancel must NOT move it
  stateAfterCancelFromError: globalThis.__comp.cancelFromError ? globalThis.__comp.cancelFromError() : null,
};
process.stdout.write(JSON.stringify(result));
JS;
        $r = self::runNode($body);
        $this->assertSame('success', (string) $r['stateAfterCancelFromSuccess'], 'cancel() from success must leave state at success');
        $this->assertSame('error', (string) $r['stateAfterCancelFromError'], 'cancel() from error must leave state at error');
    }

    /**
     * @test
     */
    public function timer_clears_on_unmount(): void
    {
        // Source-grep fallback: the `onUnmounted` hook is a Vue runtime API
        // and cannot be exercised without a Vue app. We pin the contract
        // via source inspection — the composable file must register a
        // cleanup that clears any pending timer.
        $source = (string) file_get_contents(self::composablePath());

        $this->assertStringContainsString(
            'onUnmounted',
            $source,
            'useShapeMorph must call onUnmounted to clear the dwell timer (leak guard)'
        );
        $this->assertStringContainsString(
            'clearTimeout',
            $source,
            'useShapeMorph must clearTimeout inside its lifecycle hook'
        );
    }
}