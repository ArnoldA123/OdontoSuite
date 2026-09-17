<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * PR1 / Sub-phase 1.3 — pure state-machine + timing tests for the
 * `useFieldValidation` composable.
 *
 * The composable lives at `resources/js/composables/useFieldValidation.js`
 * and depends on Vue's `reactive` and `watch`. To make the state
 * machine testable from PHPUnit (precedent: `UseSpringMathTest`,
 * `MagneticHoverTest`), the pure logic is extracted to
 * `resources/js/composables/fieldValidationMath.js` (mirrors the
 * `useSpringMath.js` + `magneticHoverMath.js` pattern).
 *
 * Test surface:
 *   - `validateField(field)` runs rules immediately (spec V1, V5).
 *   - The debounce timer resets on rapid input (spec V1, V4).
 *   - Success is immediate, no debounce (spec V2).
 *   - `clearField(field)` zeroes both `errors` and `successes`.
 *   - `validateAll()` returns false if any field has an error.
 *   - Debounce window is ~250ms ± 50ms tolerance (spec V1).
 */
class FieldValidationTest extends TestCase
{
    /** Project root absolute path. */
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    /** Pure-math module path (Node-loadable, no Vue dependency). */
    private const MATH_REL = '/resources/js/composables/fieldValidationMath.js';

    private static function mathPath(): string
    {
        return self::projectRootPath() . self::MATH_REL;
    }

    /**
     * Run a node script that loads the pure-math module, calls the supplied
     * expression, and returns its result as a JSON object.
     *
     * @param string $body  ESM body that ends with `process.stdout.write(JSON.stringify(result))`
     * @return mixed
     */
    private static function runMath(string $body): mixed
    {
        $mathPath = self::mathPath();
        if (!is_file($mathPath)) {
            self::fail("Math module missing: {$mathPath} — implement fieldValidationMath.js before this test (RED → GREEN)");
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

        $tmp = tempnam(sys_get_temp_dir(), 'field_val_');
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
     * Helper: build a `attachValidator(errors, successes, timers, form, rules)`
     * call via the math module and run an arbitrary body against the
     * exposed `v` handle.
     *
     * @param array $formJson     e.g. ['username' => '']
     * @param string $rulesJson   JSON.stringify of the rules map
     * @param string $body        node body that uses `globalThis.__v`
     * @param int    $idleMs
     * @return mixed
     */
    private static function runValidator(array $formJson, string $rulesJson, string $body, int $idleMs = 250): mixed
    {
        $formJsonStr = json_encode((object) $formJson, JSON_UNESCAPED_SLASHES);
        $scriptBody = <<<JS
const { attachValidator } = globalThis.__exports;
const errors = {};
const successes = {};
const timers = {};
const form = JSON.parse(FORM_JSON);
const rulesObj = JSON.parse(RULES_JSON);
// Translate the JSON rule shape [{v: 'fn-string'}, ...] into actual functions.
const rules = {};
for (const [k, arr] of Object.entries(rulesObj)) {
  rules[k] = arr.map(({ v }) => (0, eval)('(' + v + ')'));
}
const v = attachValidator(errors, successes, timers, form, rules, { idleMs: IDLE_MS });
globalThis.__v = v;
globalThis.__errors = errors;
globalThis.__successes = successes;
globalThis.__timers = timers;
globalThis.__form = form;
SCRIPT_BODY_PLACEHOLDER
JS;
        $scriptBody = str_replace(
            ['FORM_JSON', 'RULES_JSON', 'IDLE_MS', 'SCRIPT_BODY_PLACEHOLDER'],
            [
                var_export($formJsonStr, true),
                var_export($rulesJson, true),
                (string) $idleMs,
                $body,
            ],
            $scriptBody
        );
        return self::runMath($scriptBody);
    }

    /**
     * First test in the TDD cycle: validateField MUST run rules
     * immediately (no debounce). Given an empty username and a
     * "non-empty" rule, the call to `validateField('username')` MUST
     * set `errors.username = 'required'` synchronously.
     *
     * @test
     */
    public function validate_field_runs_immediately(): void
    {
        $body = <<<'JS'
globalThis.__v.validateField('username');
process.stdout.write(JSON.stringify({
  error: globalThis.__errors.username,
  success: globalThis.__successes.username
}));
JS;
        $rulesJson = json_encode([
            'username' => [
                ['v' => '(v) => v ? null : "required"']
            ]
        ]);
        $r = self::runValidator(['username' => ''], $rulesJson, $body);
        $this->assertSame('required', $r['error'], 'empty username must set errors.username = "required"');
        $this->assertFalse($r['success'], 'empty username must set successes.username = false');
    }

    /**
     * TRIANGULATE: debounce timer MUST reset on rapid input changes.
     * Two form changes within 100ms → only one validate fires, at the
     * end of the second timer window (250ms after the last change).
     *
     * @test
     */
    public function debounce_resets_on_rapid_input(): void
    {
        $body = <<<'JS'
// Schedule two validations in quick succession. The first timer is
// replaced by the second; only one validation runs at the end.
globalThis.__v.scheduleValidation('username');
globalThis.__timers['username'] !== null ? 'first scheduled' : 'not scheduled';
// Wait 100ms (within the 250ms window).
await new Promise(resolve => setTimeout(resolve, 100));
// Schedule again — this resets the timer.
globalThis.__v.scheduleValidation('username');
// After 200ms total (100ms after the second schedule), the FIRST timer
// would have fired by now if it wasn't cancelled. We must NOT see the
// error yet because the second timer hasn't fired.
await new Promise(resolve => setTimeout(resolve, 100));
const errorBeforeFire = globalThis.__errors.username || '';
// Wait another 200ms — now the second timer (250ms after its schedule)
// should have fired.
await new Promise(resolve => setTimeout(resolve, 200));
const errorAfterFire = globalThis.__errors.username || '';
process.stdout.write(JSON.stringify({
  errorBeforeFire,
  errorAfterFire
}));
JS;
        $rulesJson = json_encode([
            'username' => [
                ['v' => '(v) => v ? null : "required"']
            ]
        ]);
        $r = self::runValidator(['username' => ''], $rulesJson, $body);
        $this->assertSame('', $r['errorBeforeFire'], 'before debounce fires: errors.username must still be empty');
        $this->assertSame('required', $r['errorAfterFire'], 'after debounce fires: errors.username must be "required"');
    }

    /**
     * TRIANGULATE: when a rule returns null (passes), `successes[field]`
     * MUST be `true` IMMEDIATELY (spec V2). No debounce on success.
     *
     * @test
     */
    public function success_immediate_when_rule_passes(): void
    {
        $body = <<<'JS'
globalThis.__v.validateField('username');
process.stdout.write(JSON.stringify({
  error: globalThis.__errors.username,
  success: globalThis.__successes.username
}));
JS;
        // Rule returns null when v is truthy (i.e. always passes).
        $rulesJson = json_encode([
            'username' => [
                ['v' => '(v) => v ? null : "required"']
            ]
        ]);
        $r = self::runValidator(['username' => 'admin'], $rulesJson, $body);
        $this->assertSame('', $r['error'], 'passing rule: errors.username must be empty');
        $this->assertTrue($r['success'], 'passing rule: successes.username must be true on the same call');
    }

    /**
     * TRIANGULATE: clearField MUST reset both `errors[field]` to ''
     * and `successes[field]` to false (spec V5).
     *
     * @test
     */
    public function clear_field_resets_both_states(): void
    {
        $body = <<<'JS'
// First, set a passing rule so success is true.
globalThis.__form.username = 'admin';
globalThis.__v.validateField('username');
const beforeClear = {
  error: globalThis.__errors.username,
  success: globalThis.__successes.username
};
// Now clear.
globalThis.__v.clearField('username');
const afterClear = {
  error: globalThis.__errors.username,
  success: globalThis.__successes.username
};
process.stdout.write(JSON.stringify({ beforeClear, afterClear }));
JS;
        $rulesJson = json_encode([
            'username' => [
                ['v' => '(v) => v ? null : "required"']
            ]
        ]);
        $r = self::runValidator(['username' => ''], $rulesJson, $body);
        $this->assertSame('', $r['beforeClear']['error'], 'precondition: errors.username must be empty after pass');
        $this->assertTrue($r['beforeClear']['success'], 'precondition: successes.username must be true after pass');
        $this->assertSame('', $r['afterClear']['error'], 'after clearField: errors.username must be ""');
        $this->assertFalse($r['afterClear']['success'], 'after clearField: successes.username must be false');
    }

    /**
     * TRIANGULATE: validateAll MUST return false if ANY field has an
     * error, true only if ALL pass (spec V5).
     *
     * @test
     */
    public function validate_all_returns_false_when_any_field_errors(): void
    {
        $body = <<<'JS'
// username is set (passes), password is empty (fails).
globalThis.__form.username = 'admin';
const result = globalThis.__v.validateAll();
process.stdout.write(JSON.stringify({
  result,
  usernameError: globalThis.__errors.username,
  passwordError: globalThis.__errors.password
}));
JS;
        $rulesJson = json_encode([
            'username' => [
                ['v' => '(v) => v ? null : "required"']
            ],
            'password' => [
                ['v' => '(v) => v ? null : "required"']
            ]
        ]);
        $r = self::runValidator(
            ['username' => 'admin', 'password' => ''],
            $rulesJson,
            $body
        );
        $this->assertFalse($r['result'], 'validateAll must return false when at least one field errors');
        $this->assertSame('', $r['usernameError'], 'username passes: errors.username must be empty');
        $this->assertSame('required', $r['passwordError'], 'password empty: errors.password must be "required"');
    }

    /**
     * TRIANGULATE (timing): the debounce window MUST be ~250ms with
     * ±50ms tolerance for CI jitter (spec V1). Wall-clock delta between
     * schedule and execution must fall in [200ms, 300ms].
     *
     * @test
     */
    public function debounce_is_250ms_within_50ms_tolerance(): void
    {
        $body = <<<'JS'
const start = Date.now();
globalThis.__v.scheduleValidation('username');
// Poll every 10ms until errors.username becomes non-empty (i.e. the
// debounce timer has fired).
let elapsed = 0;
while (globalThis.__errors.username === undefined && elapsed < 1000) {
  await new Promise(resolve => setTimeout(resolve, 10));
  elapsed = Date.now() - start;
}
const firedAt = elapsed;
process.stdout.write(JSON.stringify({ firedAt }));
JS;
        $rulesJson = json_encode([
            'username' => [
                ['v' => '(v) => v ? null : "required"']
            ]
        ]);
        $r = self::runValidator(['username' => ''], $rulesJson, $body);
        $firedAt = (int) $r['firedAt'];
        $this->assertGreaterThanOrEqual(200, $firedAt, 'debounce must be at least 200ms (got ' . $firedAt . 'ms)');
        $this->assertLessThanOrEqual(300, $firedAt, 'debounce must be at most 300ms (got ' . $firedAt . 'ms)');
    }
}
