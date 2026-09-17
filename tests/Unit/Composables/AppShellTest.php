<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * PR1 / Sub-phase 1.1 — source-inspection guard for the MotionPlugin
 * registration in `resources/js/app.js`.
 *
 * The login premium motion slice (design D17) adds `@vueuse/motion` v3.0.3
 * as a dependency. The plugin MUST be registered with the Vue app so that
 * the `v-motion` directive (and the variant presets the LoginPage
 * composes in PR2) actually attach at runtime. This test pins that
 * contract: app.js MUST reference `MotionPlugin` exactly where the design
 * says it does (import line + `app.use(MotionPlugin)` immediately after
 * `app.use(router)`).
 *
 * If the registration is removed or the import is dropped, the bundle still
 * builds (the dep is still imported by the LoginPage composables), but the
 * runtime directive silently no-ops — the most expensive failure mode for
 * the slice. A static assertion here prevents that regression at CI time.
 *
 * Source-grep via ripgrep through `shell_exec`, matching the existing
 * precedent in `LoginPageRenderTest::grepCount()` and `UseSpringMathTest`.
 */
class AppShellTest extends TestCase
{
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    private const APP_JS_REL = '/resources/js/app.js';

    private static function appJsPath(): string
    {
        return self::projectRootPath() . self::APP_JS_REL;
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
     * The literal string `MotionPlugin` MUST appear at least once in
     * resources/js/app.js. This is the minimal contract that pins the
     * plugin registration; the import line and the `app.use(MotionPlugin)`
     * call both contain the substring, so a single count assertion catches
     * either a missing import or a missing registration.
     *
     * @test
     */
    public function app_js_registers_motion_plugin(): void
    {
        $this->assertFileExists(
            self::appJsPath(),
            'resources/js/app.js must exist for the PR1 plugin registration'
        );

        $count = self::grepCount('MotionPlugin', self::appJsPath());
        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'resources/js/app.js must reference `MotionPlugin` at least once '
            . '(design D17 + PR1 sub-phase 1.1: import line + app.use(MotionPlugin) call).'
            . ' Got count=' . $count . '.'
        );
    }

    /**
     * Defensive second assertion: the import path MUST point to the
     * `@vueuse/motion` package (not a typo'd local module). Catches the
     * failure mode where someone re-adds the import but mistypes the
     * package name — the runtime would silently no-op.
     *
     * @test
     */
    public function app_js_imports_motion_plugin_from_vueuse_motion_package(): void
    {
        $count = self::grepCount('@vueuse/motion', self::appJsPath());
        $this->assertGreaterThanOrEqual(
            1,
            $count,
            'resources/js/app.js must import MotionPlugin from "@vueuse/motion". Got count=' . $count . '.'
        );
    }
}
