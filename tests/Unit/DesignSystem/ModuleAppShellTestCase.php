<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR0 (ui-rollout-all-modules-2026-08) — abstract base class for per-module
 * structure tests.
 *
 * Every per-module `<Module>AppShellTest` extends this class and provides the
 * module's `.vue` files via `polishedFiles(): array`. The base class enforces
 * the **rule** (NOT the literal string) per the vertical-slice archive-report
 * lesson at lines 47–57: "a test that pins an example instead of the rule"
 * caused 3 defects. The base class's assertions check that the file
 * references a TOKEN, not that it contains a literal-class string.
 *
 * Subclass extension pattern (used from PR1+):
 *
 *     class ProcedureStatsAppShellTest extends ModuleAppShellTestCase
 *     {
 *         protected function polishedFiles(): array
 *         {
 *             return [
 *                 $this->projectRoot() . '/resources/js/modules/procedure-catalog/ProcedureStatsPage.vue',
 *             ];
 *         }
 *     }
 *
 * Rule assertions (each fires against every file in `polishedFiles()`):
 *
 *   - DLR-R-001  canvas surface:    file references `bg-canvas` OR `var(--color-canvas)` OR `rgb(242, 242, 247)`.
 *   - DLR-R-002  hairline:          file does NOT contain `border-theme` literal (modifier variants
 *                                   like `border-theme-light` are excluded from PR0 — they will be
 *                                   added per-category as AppLayout/Card/Sidebar/Topbar migrate).
 *   - DLR-R-004  focus ring (pos):  if `:focus` or `:focus-visible` is present, the file must consume
 *                                   `var(--focus-ring-default)`.
 *   - DLR-R-004  focus ring (neg):  file does NOT contain `focus:ring-primary-500` or `focus:border-accent`.
 *   - DLR-R-021  no `<style scoped>`: file does NOT contain a `<style scoped>` block.
 *
 * Standing guard for the 6 files with existing `<style scoped>` blocks
 * (TreatmentPlansPage, CashRegisterPage, AiAnalysisPage, AnalyzingModal,
 * CreatePatientInline, TreatmentPlanModal) during their respective PRs.
 */
abstract class ModuleAppShellTestCase extends TestCase
{
    /**
     * Subclasses MUST return absolute paths to the module's polished `.vue`
     * files. The data provider enumerates each file as a separate test row
     * so a failure pinpoints exactly which file regressed.
     *
     * Static because PHPUnit data providers must be static, and we want the
     * provider to be able to call this without instantiating the class.
     *
     * @return array<int, string>
     */
    abstract protected static function polishedFiles(): array;

    /**
     * Subclasses MAY return absolute paths to components embedded inside the
     * module (buttons, badges, tiles). Embedded components obey the
     * style-discipline rules of `test_embedded_component_style_discipline()`
     * but NOT DLR-R-001: a button never paints the page canvas.
     *
     * @return array<int, string>
     */
    protected static function embeddedFiles(): array
    {
        return [];
    }

    protected function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function polishedFileProvider(): array
    {
        $cases = [];
        foreach (static::polishedFiles() as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function embeddedFileProvider(): array
    {
        $cases = [];
        foreach (static::embeddedFiles() as $path) {
            $cases[$path] = [$path];
        }

        // PHPUnit rejects an empty data set. The placeholder keeps every
        // subclass that declares no embedded components on a passing no-op row.
        return $cases === [] ? ['no embedded components declared' => [null]] : $cases;
    }

    private static function readFile(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);

        return $src === false ? null : $src;
    }

    /**
     * Strip JS/TS string + comment noise so the regex patterns match
     * against actual class strings, not pattern examples embedded in
     * comments. Reused by LegacyAliasForbiddenTest (which copies the helper
     * to keep coupling off the base class).
     *
     * Scope: only inside `<script>...</script>` blocks. Stripping HTML
     * attribute values would erase legitimate Tailwind class names in
     * `class="..."` and break the rules. We only want to silence noise
     * that a defender might embed inside JS string literals (e.g. a
     * developer writing a CSS string in JS would not falsely trigger
     * the legacy-alias rule).
     */
    private static function stripStringsAndComments(string $src): string
    {
        // Replace the contents of each <script>...</script> block with the
        // stripped form (comments + strings removed). The surrounding
        // template and style regions are preserved verbatim.
        return preg_replace_callback(
            '#<script\b[^>]*>(.*?)</script>#s',
            static function (array $m): string {
                $script = $m[1];
                $stripped = preg_replace('#/\*.*?\*/#s', '', $script) ?? $script;
                $stripped = preg_replace('#//[^\n]*#', '', $stripped) ?? $stripped;
                $stripped = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "''", $stripped) ?? $stripped;
                $stripped = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $stripped) ?? $stripped;
                $stripped = preg_replace('/`(?:\\\\.|[^`\\\\])*`/', '``', $stripped) ?? $stripped;

                return '<script>'.$stripped.'</script>';
            },
            $src
        ) ?? $src;
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_page_references_canvas_token(string $path): void
    {
        $src = self::readFile($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripStringsAndComments($src);
        $pattern = '/(?:bg-canvas|var\(--color-canvas\)|rgb\(\s*242\s*,\s*242\s*,\s*247\s*\))/';
        $this->assertMatchesRegularExpression(
            $pattern,
            $cleaned,
            sprintf(
                '%s must reference the canvas token (bg-canvas, var(--color-canvas), or rgb(242, 242, 247)) per DLR-R-001.',
                $path
            )
        );
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_border_theme_literal(string $path): void
    {
        $src = self::readFile($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripStringsAndComments($src);
        // Negative lookbehind + lookahead; modifier variants (border-theme-light
        // etc.) contain `-light`/`-dark` after `border-theme`, which match
        // [\w-] in the lookahead, so the regex correctly excludes them.
        $pattern = '/(?<![\w-])border-theme(?![\w-])/';
        $this->assertDoesNotMatchRegularExpression(
            $pattern,
            $cleaned,
            sprintf(
                '%s must not contain the legacy `border-theme` literal (DLR-R-002). '
                .'Use the `border-hairline` / `--color-hairline` token instead.',
                $path
            )
        );
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_focus_ring_consumes_token(string $path): void
    {
        $src = self::readFile($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripStringsAndComments($src);
        // Conditional: if `:focus` or `:focus-visible` appears, the file must
        // also reference `var(--focus-ring-default)`. When the focus
        // selector is absent, the always-true branch keeps PHPUnit from
        // flagging the test as risky.
        if (preg_match('/:focus(?:-visible)?/', $cleaned) === 1) {
            $this->assertMatchesRegularExpression(
                '/var\(--focus-ring-default\)/',
                $cleaned,
                sprintf(
                    '%s contains a `:focus` selector and must therefore consume the `var(--focus-ring-default)` token (DLR-R-004).',
                    $path
                )
            );
        } else {
            $this->assertTrue(true, sprintf('%s has no focus selectors — nothing to assert.', $path));
        }
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_focus_ring_alias(string $path): void
    {
        $src = self::readFile($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripStringsAndComments($src);
        $pattern = '/(?<![\w-])focus:ring-primary-500(?![\w-])|(?<![\w-])focus:border-accent(?![\w-])/';
        $this->assertDoesNotMatchRegularExpression(
            $pattern,
            $cleaned,
            sprintf(
                '%s must not contain legacy focus-ring aliases (`focus:ring-primary-500` or `focus:border-accent`) per DLR-R-004.',
                $path
            )
        );
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_no_style_scoped(string $path): void
    {
        $src = self::readFile($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripStringsAndComments($src);
        $this->assertDoesNotMatchRegularExpression(
            '/<style\s+scoped\s*>/',
            $cleaned,
            sprintf(
                '%s must not contain a `<style scoped>` block (DLR-R-021). '
                .'Tailwind utility classes + scoped transitions (focus ring + reduced-motion fallback) live in the global token CSS.',
                $path
            )
        );
    }

    /**
     * Embedded components (buttons, badges, tiles) share the style-discipline
     * rules of the page shell, minus the canvas-surface rule: a component
     * embedded in a surface never paints the page canvas.
     *
     * @dataProvider embeddedFileProvider
     */
    public function test_embedded_component_style_discipline(?string $path): void
    {
        if ($path === null) {
            $this->assertTrue(true, 'No embedded components declared — nothing to assert.');

            return;
        }

        $src = self::readFile($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripStringsAndComments($src);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $cleaned,
            sprintf(
                '%s must not contain the legacy `border-theme` literal (DLR-R-002). '
                .'Use the `border-hairline` / `--color-hairline` token instead.',
                $path
            )
        );

        if (preg_match('/:focus(?:-visible)?/', $cleaned) === 1) {
            $this->assertMatchesRegularExpression(
                '/var\(--focus-ring-default\)/',
                $cleaned,
                sprintf(
                    '%s contains a `:focus` selector and must therefore consume the `var(--focus-ring-default)` token (DLR-R-004).',
                    $path
                )
            );
        } else {
            $this->assertTrue(true, sprintf('%s has no focus selectors — nothing to assert.', $path));
        }

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])focus:ring-primary-500(?![\w-])|(?<![\w-])focus:border-accent(?![\w-])/',
            $cleaned,
            sprintf(
                '%s must not contain legacy focus-ring aliases (`focus:ring-primary-500` or `focus:border-accent`) per DLR-R-004.',
                $path
            )
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<style\s+scoped\s*>/',
            $cleaned,
            sprintf(
                '%s must not contain a `<style scoped>` block (DLR-R-021). '
                .'Embedded components consume Tailwind utilities and the global token CSS.',
                $path
            )
        );
    }
}
