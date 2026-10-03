<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

use Tests\Support\SourceGrep;

/**
 * Source-inspection guard for the MotionPlugin retirement.
 *
 * Design D17 once pinned `@vueuse/motion` v3.0.3 registration in
 * `resources/js/app.js`, but the `v-motion` consumers it was meant to
 * serve never materialized (the directive accepted only objects/strings,
 * so every function-valued usage was inert). The plugin and the
 * dependency were retired together. This test pins the retirement:
 * neither `MotionPlugin` nor `@vueuse/motion` may return to the JS
 * source or the manifest without a real directive consumer.
 *
 * Source-grep via ripgrep through `shell_exec`, matching the existing
 * precedent in `LoginPageRenderTest::grepCount()` and `UseSpringMathTest`.
 */
class AppShellTest extends TestCase
{
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    private const APP_JS_REL = '/resources/js/app.js';

    private const PACKAGE_JSON_REL = '/package.json';

    private static function appJsPath(): string
    {
        return self::projectRootPath() . self::APP_JS_REL;
    }

    private static function packageJsonPath(): string
    {
        return self::projectRootPath() . self::PACKAGE_JSON_REL;
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
        return SourceGrep::count($pattern, ...$paths);
    }

    /**
     * The literal string `MotionPlugin` MUST NOT appear in
     * resources/js/app.js: the plugin was retired with its dependency.
     *
     * @test
     */
    public function app_js_does_not_register_motion_plugin(): void
    {
        $this->assertFileExists(
            self::appJsPath(),
            'resources/js/app.js must exist'
        );

        $count = self::grepCount('MotionPlugin', self::appJsPath());
        $this->assertSame(
            0,
            $count,
            'resources/js/app.js must not reference `MotionPlugin` '
                . '(retired with @vueuse/motion: no real v-motion consumer).'
                . ' Got count=' . $count . '.'
        );
    }

    /**
     * Neither app.js nor package.json MAY reference `@vueuse/motion`:
     * the dependency was removed alongside the plugin registration.
     *
     * @test
     */
    public function vueuse_motion_dependency_is_retired(): void
    {
        $this->assertFileExists(
            self::packageJsonPath(),
            'package.json must exist'
        );

        foreach ([self::appJsPath(), self::packageJsonPath()] as $path) {
            $count = self::grepCount('@vueuse/motion', $path);
            $this->assertSame(
                0,
                $count,
                basename($path) . ' must not reference "@vueuse/motion" '
                    . '(dependency retired with the plugin registration).'
                    . ' Got count=' . $count . '.'
            );
        }
    }
}
