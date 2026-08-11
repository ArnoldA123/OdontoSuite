<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR2 / Phase 2.1 — generated tokens CSS anti-requirement guards.
 *
 * Validates `resources/css/tokens.generated.css` (emitted by
 * `scripts/build-tokens-css.mjs` from `resources/js/design-system/tokens.js`).
 * The generator is the only durable answer to token drift; these tests are
 * the parity check that catches iCloud-blue drift and stale color references
 * before they reach a browser.
 *
 * Coverage:
 *  - 2.1.5  generated_css_single_root_block
 *  - 2.1.6  generated_css_has_no_external_font_request
 *  - 2.1.7  generated_css_has_font_face_swap
 *  - 2.1.8  generated_css_surface_glass_class_emitted_exactly_once
 *  - 2.1.9  card_variant_glass_has_no_backdrop_filter
 *  - 2.1.10 primitives_have_no_backdrop_filter_outside_chrome
 *  - 2.1.11 no_universal_transition_selector_in_css
 *  - 2.1.12 generated_css_only_contains_token_hex_literals
 */
class GeneratedTokensCssTest extends TestCase
{
    /**
     * Project root, derived from this file's location.
     *
     * Never hardcode an absolute path here: it passes only on the machine that
     * wrote it and fails on every other checkout and in CI. This class does not
     * boot the framework, so `base_path()` is unavailable — walk up from __DIR__
     * instead (tests/Unit/DesignSystem -> project root is three levels up).
     */
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Generated CSS path (emitted by build-tokens-css.mjs). */
    private const GENERATED_CSS_REL = '/resources/css/tokens.generated.css';

    /** Font asset path used by the @font-face src declaration. */
    private const FONT_REL = '/public/fonts/newsreader-latin.woff2';

    private static function generatedCssPath(): string
    {
        return self::projectRoot() . self::GENERATED_CSS_REL;
    }

    private static function fontPath(): string
    {
        return self::projectRoot() . self::FONT_REL;
    }

    /**
     * Read the generated CSS as a string, or null if the file does not exist
     * yet (RED state before 2.2.4 runs the generator).
     */
    private static function readGeneratedCss(): ?string
    {
        $path = self::generatedCssPath();
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    /**
     * Shell out to ripgrep and return the number of matching lines, summed
     * across files. Same helper as TokensModuleTest::grepResourceCount, but
     * scoped to a path argument so we can grep generated CSS only.
     */
    private static function grepCount(string $pattern, string $rootPath): int
    {
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            escapeshellarg($rootPath)
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
     * Task 2.1.5 — generated CSS must contain exactly one top-level `:root`
     * block. The generator's contract: a single emission point for the entire
     * token surface. Nested `:root` selectors inside media queries are
     * allowed (e.g. for prefers-contrast overrides) but the top-level count
     * must be exactly one.
     *
     * @test
     */
    public function generated_css_single_root_block(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist (run scripts/build-tokens-css.mjs)');

        // Top-level only: `:root` at column 0 (no leading whitespace).
        $count = preg_match_all('/^:root\s*\{/m', $css);
        $this->assertSame(
            1,
            (int) $count,
            'tokens.generated.css must contain exactly 1 top-level :root block, got ' . var_export($count, true)
        );
    }

    /**
     * Task 2.1.6 — anti-requirement guard. No Google Fonts CDN reference may
     * exist anywhere under resources/css, resources/js, or resources/views.
     * The font is self-hosted in public/fonts/.
     *
     * @test
     */
    public function generated_css_has_no_external_font_request(): void
    {
        $root = self::projectRoot();
        $count = self::grepCount('fonts\.googleapis|fonts\.gstatic', $root . '/resources/css')
            + self::grepCount('fonts\.googleapis|fonts\.gstatic', $root . '/resources/js')
            + self::grepCount('fonts\.googleapis|fonts\.gstatic', $root . '/resources/views');

        $this->assertSame(
            0,
            $count,
            'No Google Fonts CDN references may exist under resources/{css,js,views}'
        );
    }

    /**
     * Anti-requirement (ui-refresh-apple-clinical-2026-08, design Decision 6):
     * the @font-face block is GONE. The system font has no FOUT risk; no
     * replacement woff2 binary ships; no replacement composable ships.
     *
     * @test
     */
    public function generated_css_has_no_font_face_block(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $this->assertDoesNotMatchRegularExpression(
            '/@font-face\s*\{/',
            (string) $css,
            'tokens.generated.css must not contain any @font-face block (Newsreader retired per Decision 6)'
        );
    }

    /**
     * Task 2.1.8 — the chrome-only `.surface-glass` class must be emitted
     * exactly once at the top level of the generated CSS. Duplicate top-level
     * emissions indicate the generator ran twice or someone hand-edited the
     * file. Nested references inside media queries (e.g. for
     * prefers-reduced-transparency) are allowed and counted separately.
     *
     * @test
     */
    public function generated_css_surface_glass_class_emitted_exactly_once(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        // Top-level only: `.surface-glass` at column 0 (no leading whitespace).
        $count = preg_match_all('/^\.surface-glass\s*\{/m', $css);
        $this->assertSame(
            1,
            (int) $count,
            '.surface-glass class must be emitted exactly once at top level in tokens.generated.css, got ' . var_export($count, true)
        );
    }

    /**
     * Task 2.1.9 — data-card `variant="glass"` is opaque by construction.
     * No `backdrop-filter` declaration may exist in Card.vue.
     *
     * @test
     */
    public function card_variant_glass_has_no_backdrop_filter(): void
    {
        $cardPath = self::projectRoot() . '/resources/js/components/ui/Card.vue';
        $this->assertFileExists($cardPath, 'Card.vue must exist');

        $count = self::grepCount('backdrop-filter', $cardPath);
        $this->assertSame(
            0,
            $count,
            'Card.vue must not declare backdrop-filter (data-card variant="glass" is opaque; use .surface-glass for chrome blur)'
        );
    }

    /**
     * Task 2.1.10 — only Card.vue was historically the data-card surface;
     * no other primitive component may declare its own blur. After PR2 the
     * chrome blur lives only in `.surface-glass` inside the generated CSS.
     *
     * @test
     */
    public function primitives_have_no_backdrop_filter_outside_chrome(): void
    {
        $uiDir = self::projectRoot() . '/resources/js/components/ui';
        $total = self::grepCount('backdrop-filter', $uiDir);
        // Card.vue is excluded (covered separately by 2.1.9). After PR2 no
        // other primitive carries a backdrop-filter; chrome lives in
        // tokens.generated.css.
        $this->assertSame(
            0,
            $total,
            'No primitive component under resources/js/components/ui/ may declare backdrop-filter (chrome blur lives in .surface-glass only)'
        );
    }

    /**
     * Task 2.1.11 — design Testing Strategy #1 anti-requirement. No
     * universal selector with a transition declaration may exist across the
     * three CSS surfaces (themes, tokens, utilities). The generator must
     * never emit one; hand-edits must not introduce one.
     *
     * @test
     */
    public function no_universal_transition_selector_in_css(): void
    {
        $root = self::projectRoot();
        $cmd = sprintf(
            'rg --no-heading -n "^\s*\*\s*\{" %s/resources/css/themes.css %s/resources/css/tokens.generated.css %s/resources/css/utilities.css 2>&1',
            escapeshellarg($root),
            escapeshellarg($root),
            escapeshellarg($root)
        );
        $output = (string) shell_exec($cmd);
        $matches = array_filter(
            preg_split('/\r?\n/', $output) ?: [],
            static fn($line) => trim((string) $line) !== ''
        );
        $transitions = 0;
        foreach ($matches as $line) {
            // Each ripgrep result is `path:line:content`; check content for "transition".
            if (stripos((string) $line, 'transition') !== false) {
                $transitions++;
            }
        }
        $this->assertSame(
            0,
            $transitions,
            'No universal selector with a transition may exist across themes/tokens/utilities CSS (design Testing Strategy #1)'
        );
    }

    /**
     * Task 2.1.12 — the set of 6-digit hex literals in tokens.generated.css
     * must equal the union of all hex literals declared in tokens.colors.
     * Catches iCloud-blue drift (a hand-edited hex value that the JS module
     * does not know about) the unit test on tokens.js alone cannot.
     *
     * @test
     */
    public function generated_css_only_contains_token_hex_literals(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        // Collect hex values from tokens.js via Node.
        $tokens = self::loadTokensColors();
        $this->assertNotNull($tokens, 'loadTokensColors() must succeed');
        $expected = [];
        foreach ($tokens as $ramp) {
            if (!is_array($ramp)) {
                continue;
            }
            foreach ($ramp as $step => $value) {
                $hex = strtolower((string) $value);
                if (preg_match('/^#[0-9a-f]{6}$/', $hex) === 1) {
                    $expected[$hex] = true;
                }
            }
        }

        // Collect hex values from the generated CSS (case-insensitive).
        preg_match_all('/#[0-9A-Fa-f]{6}/', $css, $cssMatches);
        $actual = [];
        foreach ($cssMatches[0] as $hex) {
            $actual[strtolower((string) $hex)] = true;
        }

        $missing = array_diff_key($expected, $actual);
        $extra = array_diff_key($actual, $expected);

        $this->assertSame(
            [],
            $missing,
            'tokens.generated.css is missing hex values declared in tokens.js: ' . implode(', ', array_keys($missing))
        );
        $this->assertSame(
            [],
            $extra,
            'tokens.generated.css contains hex values NOT declared in tokens.js (drift detected): ' . implode(', ', array_keys($extra))
        );
    }

    /**
     * Import tokens.js via Node and return only the `colors` subtree.
     * Same loader pattern as TokensModuleTest, but smaller output.
     *
     * @return array<string, mixed>|null
     */
    private static function loadTokensColors(): ?array
    {
        $tokensPath = self::projectRoot() . '/resources/js/design-system/tokens.js';
        if (!is_file($tokensPath)) {
            return null;
        }
        $escapedPath = addcslashes($tokensPath, "'\\");
        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
const url = pathToFileURL('TARGET_PATH').href;
const mod = await import(url);
process.stdout.write(JSON.stringify(mod.default.colors ?? mod.colors ?? null));
JS;
        $loader = str_replace('TARGET_PATH', $escapedPath, $loader);

        $tmp = tempnam(sys_get_temp_dir(), 'tokens_colors_loader_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $loader);
        @unlink($tmp);

        $cmd = 'node "' . $loaderFile . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        if ($output === null || $output === '') {
            return null;
        }
        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            return null;
        }
        $decoded = json_decode(substr($output, $jsonStart), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Every var() reference in the generated CSS must point at a property the
     * same file defines.
     *
     * This exists because it already failed once: the ramps were emitted
     * camelCase (`--color-clinicalTeal-500`) while the semantic aliases
     * referenced kebab-case (`--color-clinical-teal-500`). CSS does not error
     * on an undefined custom property — it resolves to nothing — so
     * `--color-info` silently became empty and the WebSocket status indicator
     * lost its colour with no failing test and no console warning.
     */
    public function test_generated_css_has_no_dangling_var_references(): void
    {
        $css = file_get_contents(self::generatedCssPath());

        preg_match_all('/^\s*(--[a-zA-Z0-9-]+)\s*:/m', $css, $definedMatches);
        $defined = array_flip($definedMatches[1]);

        preg_match_all('/var\(\s*(--[a-zA-Z0-9-]+)/', $css, $usedMatches);
        $used = array_unique($usedMatches[1]);

        $dangling = array_values(array_filter(
            $used,
            static fn (string $name): bool => ! isset($defined[$name])
        ));

        self::assertSame(
            [],
            $dangling,
            'Generated CSS references custom properties it never defines: '
                . implode(', ', $dangling)
        );
    }

    /**
     * Custom properties are kebab-case even when the JS token key is camelCase.
     */
    public function test_generated_css_uses_kebab_case_property_names(): void
    {
        $css = file_get_contents(self::generatedCssPath());

        preg_match_all('/^\s*(--[a-zA-Z0-9-]+)\s*:/m', $css, $matches);

        $camelCased = array_values(array_filter(
            array_unique($matches[1]),
            static function (string $name): bool {
                // `DEFAULT` is Tailwind's own key for the unsuffixed value in a
                // scale (`radius.DEFAULT` -> `rounded`). It is a borrowed
                // convention, not a casing slip, so it is the one allowed
                // uppercase segment.
                $withoutTailwindDefault = str_replace('-DEFAULT', '', $name);

                return (bool) preg_match('/[A-Z]/', $withoutTailwindDefault);
            }
        ));

        self::assertSame(
            [],
            $camelCased,
            'Custom properties must be kebab-case; found: ' . implode(', ', $camelCased)
        );
    }
}
