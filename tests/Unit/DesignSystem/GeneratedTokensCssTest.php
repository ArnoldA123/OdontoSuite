<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

use Tests\Support\SourceGrep;

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
 *
 * PR1 (ui-premium-microdetail-2026-08) — the `test_*` methods at the end of
 * this class pin the emission contract settled by reconciliation rulings
 * R1-R12. Every custom-property name and value below is a pinned invariant,
 * so re-read the ruling before editing an expectation. Several are
 * anti-requirements (a value that must NOT be emitted); each of those guards
 * a defect the generator previously shipped. The task ID on each method maps
 * to that change's tasks.md.
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
        return SourceGrep::count($pattern, $rootPath);
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
        $candidates = [
            $root . '/resources/css/themes.css',
            $root . '/resources/css/tokens.generated.css',
            $root . '/resources/css/utilities.css',
        ];
        $existing = array_values(array_filter($candidates, 'is_file'));
        $this->assertNotEmpty($existing, 'At least one CSS surface must exist for the universal-selector guard');
        $matches = SourceGrep::lines('^\s*\*\s*\{', ...$existing);
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
     * Import tokens.js via Node and return the `colors` subtree.
     *
     * @return array<string, mixed>|null
     */
    private static function loadTokensColors(): ?array
    {
        return self::loadTokensSubtree('colors');
    }

    /**
     * Import tokens.js via Node and return one top-level subtree.
     * Same loader pattern as TokensModuleTest, but smaller output.
     *
     * @return array<string, mixed>|null
     */
    private static function loadTokensSubtree(string $key): ?array
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
const root = mod.default ?? mod;
process.stdout.write(JSON.stringify(root[SUBTREE] ?? null));
JS;
        $loader = str_replace(
            ['TARGET_PATH', 'SUBTREE'],
            [$escapedPath, "'" . $key . "'"],
            $loader
        );

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

    /** Task 1.2.1 — motion.duration emitted as --motion-duration-fast|normal|slow. */
    public function test_generated_css_emits_motion_duration_ramp(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        foreach (['fast' => '120ms', 'normal' => '200ms', 'slow' => '320ms'] as $step => $value) {
            $this->assertMatchesRegularExpression(
                '/--motion-duration-' . $step . '\s*:\s*' . $value . '\s*;/',
                (string) $css,
                "tokens.generated.css must declare --motion-duration-{$step}: {$value};"
            );
        }
    }

    /**
     * Task 1.2.3 — focus-ring parts plus the composed --focus-ring-default
     * shorthand.
     *
     * The colour is read from `tokens.js` and asserted as a RELATIONSHIP:
     * the generated CSS must AGREE WITH the source of truth. A palette
     * migration therefore costs zero edits here — the assertion follows the
     * token instead of pinning the retired hex.
     */
    public function test_generated_css_emits_focus_ring_parts_and_composed(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $colors = self::loadTokensColors();
        $this->assertNotNull($colors, 'tokens.js must expose its colors subtree');
        $this->assertArrayHasKey('accent', $colors, 'tokens.colors.accent (the canonical accent ramp) must exist');

        $accentHex = (string) $colors['accent']['500'];
        $this->assertMatchesRegularExpression(
            '/^#[0-9A-Fa-f]{6}$/',
            $accentHex,
            'the accent 500 step must be a 6-digit hex literal'
        );
        [$r, $g, $b] = self::hexToRgbParts($accentHex);

        $this->assertMatchesRegularExpression('/--focus-ring-width\s*:\s*3px\s*;/', (string) $css);
        $this->assertMatchesRegularExpression(
            '/--focus-ring-color\s*:\s*' . preg_quote($accentHex, '/') . '\s*[;)]/i',
            (string) $css,
            "tokens.generated.css must declare --focus-ring-color as the accent from tokens.js ({$accentHex}, hex case-insensitive)"
        );
        $this->assertMatchesRegularExpression('/--focus-ring-alpha\s*:\s*0\.2(?:0)?\s*;/', (string) $css);
        $this->assertMatchesRegularExpression('/--focus-ring-offset\s*:\s*2px\s*;/', (string) $css);
        $this->assertMatchesRegularExpression(
            '/--focus-ring-default\s*:\s*0\s+0\s+0\s+var\(--focus-ring-width\)\s+rgba\(\s*'
                . $r . '\s*,\s*' . $g . '\s*,\s*' . $b
                . '\s*,\s*var\(--focus-ring-alpha\)\)\s*;/',
            (string) $css,
            'tokens.generated.css must declare the composed --focus-ring-default shorthand built from the accent'
        );
    }

    /**
     * Split a 6-digit hex literal into its decimal r/g/b parts so the
     * generated CSS can be compared against the source of truth without
     * pinning a colour value.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgbParts(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Task 1.2.5 — tabular numerals emit the CSS value, never the Tailwind utility name. */
    public function test_generated_css_emits_font_features_tabular_nums(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $this->assertMatchesRegularExpression(
            '/--font-features-tabular-nums\s*:\s*"tnum"\s+1,\s+"lnum"\s+1\s*;/',
            (string) $css,
            'tokens.generated.css must declare --font-features-tabular-nums with the valid CSS value'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/--font-features-tabular-nums\s*:\s*tabular-nums\s*;/',
            (string) $css,
            'tokens.generated.css must NOT emit the literal Tailwind utility name "tabular-nums" as a value'
        );
    }

    /** Task 1.2.7 — --elevation-0 is none and rungs 1..4 are all emitted. */
    public function test_generated_css_emits_elevation_ramp(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $this->assertMatchesRegularExpression(
            '/--elevation-0\s*:\s*none\s*;/',
            (string) $css,
            'tokens.generated.css must declare --elevation-0: none;'
        );

        for ($rung = 1; $rung <= 4; $rung++) {
            $this->assertMatchesRegularExpression(
                '/--elevation-' . $rung . '\s*:/',
                (string) $css,
                "tokens.generated.css must declare --elevation-{$rung}"
            );
        }
    }

    /** Task 1.2.9 — the colors loop must not double the prefix on colors.border.hairline. */
    public function test_generated_css_does_not_emit_color_hairline_hairline(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $this->assertDoesNotMatchRegularExpression(
            '/--color-hairline-hairline\s*:/',
            (string) $css,
            'tokens.generated.css must NOT emit --color-hairline-hairline (would be a double-prefix bug)'
        );
    }

    /** Task 1.2.11 — the semantic alias --color-canvas ships alongside the ramp property. */
    public function test_generated_css_emits_color_canvas_semantic_alias(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $this->assertMatchesRegularExpression(
            '/--color-canvas\s*:/',
            (string) $css,
            'tokens.generated.css must declare the semantic alias --color-canvas'
        );
        $this->assertMatchesRegularExpression(
            '/--color-background-canvas\s*:/',
            (string) $css,
            'tokens.generated.css must declare the ramp --color-background-canvas (emitted via the colors loop)'
        );
    }

    /**
     * Task 1.2 — the hairline value reaches the generated CSS under the
     * `--color-hairline` name.
     *
     * Asserted as a RELATIONSHIP against `tokens.colors.border.hairline`, so a
     * palette migration never edits this line.
     */
    public function test_generated_css_emits_color_hairline(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $colors = self::loadTokensColors();
        $this->assertNotNull($colors, 'tokens.js must expose its colors subtree');
        $hairline = (string) ($colors['border']['hairline'] ?? '');
        $this->assertNotSame('', $hairline, 'tokens.colors.border.hairline must exist');

        $this->assertMatchesRegularExpression(
            '/--color-hairline\s*:\s*' . preg_quote($hairline, '/') . '\s*;/',
            (string) $css,
            "tokens.generated.css must declare --color-hairline: {$hairline}; (mirroring tokens.js)"
        );
    }

    /**
     * R3/R4 convergence (review-c5ea2472df6659cd) — the generator's hairline
     * guard, proven by BEHAVIOUR rather than by source inspection.
     *
     * Two lenses independently flagged `build-tokens-css.mjs`'s guard:
     * `review-resilience` (R4-001, WARNING) read it as a degradation risk and
     * `review-reliability` (R3, SUGGESTION) as a coverage gap. Both were
     * right, and neither could be satisfied by asserting on the script's
     * text: that is the example-pinning defect this change set out to remove.
     *
     * Both tests run the REAL generator against a throwaway project root
     * holding a transformed copy of the REAL `tokens.js`. Using the real file
     * (not a hand-written stub) keeps the fixture honest as the token surface
     * grows — a stub would rot the moment a new required key appears.
     */
    public function test_generator_fails_loud_when_hairline_token_is_missing(): void
    {
        $fixture = self::makeGeneratorFixture(static function (string $tokensSrc): string {
            // Drop the whole border ramp, not just the key.
            return (string) preg_replace('/^\s*border:\s*\{[^}]*\}/ms', 'border: {}', $tokensSrc, 1);
        });

        try {
            [$exitCode, $output] = self::runGenerator($fixture);

            $this->assertNotSame(
                0,
                $exitCode,
                'The generator must exit non-zero when tokens.colors.border.hairline is missing. Output: ' . $output
            );
            $this->assertStringContainsString(
                'refusing to emit a hardcoded fallback',
                $output,
                'The generator must say WHY it refused; a bare failure is a silent fallback by another name.'
            );
            $this->assertFileDoesNotExist(
                $fixture . '/resources/css/tokens.generated.css',
                'A refused run must not leave a partial stylesheet behind: that is the silent-drift failure mode.'
            );
        } finally {
            self::removeDirectory($fixture);
        }
    }

    /**
     * The anti-drift property itself: change the token, the emitted CSS
     * follows. If this fails, the generator has gone back to authoring the
     * value instead of emitting it — the exact regression the guard exists
     * to prevent, and the one that let `tokens.js` and the generated CSS
     * diverge silently before this change.
     */
    public function test_generator_emits_the_hairline_it_reads_from_tokens(): void
    {
        $sentinel = 'rgba(9, 9, 9, 0.99)';

        $fixture = self::makeGeneratorFixture(
            static fn (string $tokensSrc): string => (string) preg_replace(
                "/hairline:\s*'[^']*'/",
                "hairline: '{$sentinel}'",
                $tokensSrc,
                1
            )
        );

        try {
            [$exitCode, $output] = self::runGenerator($fixture);
            $this->assertSame(0, $exitCode, 'The generator must succeed on a valid fixture. Output: ' . $output);

            $cssPath = $fixture . '/resources/css/tokens.generated.css';
            $this->assertFileExists($cssPath, 'A successful run must write the stylesheet.');

            $this->assertStringContainsString(
                '--color-hairline: ' . $sentinel . ';',
                (string) file_get_contents($cssPath),
                'The emitted --color-hairline must echo the token value read from tokens.js.'
            );
        } finally {
            self::removeDirectory($fixture);
        }
    }

    /**
     * Materialise a throwaway project root holding the real generator plus a
     * transformed copy of the real tokens.js, mirroring the layout the
     * generator resolves from its own location (`<root>/scripts/..`).
     */
    private static function makeGeneratorFixture(callable $transformTokens): string
    {
        $projectRoot = self::projectRoot();
        $root = rtrim(sys_get_temp_dir(), '/\\') . '/odonto-tokens-gen-' . bin2hex(random_bytes(6));

        foreach (['/scripts', '/resources/js/design-system', '/resources/css'] as $sub) {
            $dir = $root . $sub;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            self::assertDirectoryExists($dir, "Could not create fixture directory {$dir}");
        }

        copy($projectRoot . '/scripts/build-tokens-css.mjs', $root . '/scripts/build-tokens-css.mjs');

        $tokensSrc = (string) file_get_contents($projectRoot . '/resources/js/design-system/tokens.js');
        $transformed = $transformTokens($tokensSrc);
        self::assertNotSame(
            $tokensSrc,
            $transformed,
            'Fixture transform changed nothing — the test would pass vacuously.'
        );
        file_put_contents($root . '/resources/js/design-system/tokens.js', $transformed);

        return $root;
    }

    /**
     * @return array{0: int, 1: string} exit code and combined output
     */
    private static function runGenerator(string $fixtureRoot): array
    {
        $output = [];
        $exitCode = 0;
        exec('node ' . escapeshellarg($fixtureRoot . '/scripts/build-tokens-css.mjs') . ' 2>&1', $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    /**
     * Task 1.2 / Slice A3 — every nested radius token reaches the generated CSS.
     *
     * The expected values are read from tokens.js instead of pinned as
     * literals: a literal pin only reports "a value changed" — it never
     * checks that the generator still carries the source of truth.
     */
    public function test_generated_css_emits_the_nested_radius_ramp(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        $radius = self::loadTokensSubtree('radius');
        $this->assertIsArray($radius, 'tokens.js radius subtree must be loadable');

        foreach (['cardLg', 'panel', 'shell', 'control'] as $key) {
            $this->assertArrayHasKey($key, $radius, "tokens.radius.{$key} must exist");

            // Mirror of the generator's toKebab() (scripts/build-tokens-css.mjs).
            $cssName = '--radius-' . strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $key));
            $value = preg_quote((string) $radius[$key], '/');

            $this->assertMatchesRegularExpression(
                '/' . preg_quote($cssName, '/') . '\s*:\s*' . $value . '\s*;/',
                (string) $css,
                "tokens.generated.css must declare {$cssName}: {$radius[$key]};"
            );
        }
    }

    /** Task 1.2 — no elevation rung may fall back to the pure-black shadow being retired. */
    public function test_generated_css_no_elevation_uses_pure_black_rgba(): void
    {
        $css = self::readGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        if (preg_match_all('/--elevation-[0-9]+\s*:\s*([^;]+);/', (string) $css, $matches) > 0) {
            foreach ($matches[1] as $value) {
                self::assertDoesNotMatchRegularExpression(
                    '/rgba\(\s*0\s*,\s*0\s*,\s*0\s*,/',
                    (string) $value,
                    'No elevation rung may use rgba(0, 0, 0, ...) — shadows come from the label hue family'
                );
            }
        } else {
            self::fail('No elevation rungs found in tokens.generated.css — generator regressed');
        }
    }

    /**
     * Vue component styles must not reference a retired colour token.
     *
     * The sibling `generated_css_has_no_dangling_var_references` test only
     * scans tokens.generated.css, so a `var(--color-ink-500)` left behind in
     * a component's <style scoped> block resolved to `unset` at runtime and
     * silently fell back to the browser default. That is exactly how the
     * login subtitle ended up rendering pure black instead of the secondary
     * label tone, collapsing the hierarchy against the headline. A green
     * suite proved nothing because no test looked at component styles.
     */
    public function test_vue_components_have_no_dangling_color_var_references(): void
    {
        $root = self::projectRoot();

        $defined = [];
        foreach (glob($root . '/resources/css/*.css') ?: [] as $cssFile) {
            preg_match_all('/(--color-[a-z0-9-]+)\s*:/i', (string) file_get_contents($cssFile), $m);
            foreach ($m[1] as $name) {
                $defined[$name] = true;
            }
        }
        $this->assertNotEmpty($defined, 'No --color-* definitions found under resources/css/');

        $dangling = [];
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/resources/js', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }
            preg_match_all('/var\(\s*(--color-[a-z0-9-]+)/i', (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $name) {
                if (!isset($defined[$name])) {
                    $dangling[$name][] = basename($file->getPathname());
                }
            }
        }

        $report = '';
        foreach ($dangling as $name => $files) {
            $report .= sprintf("\n  %s  <- %s", $name, implode(', ', array_unique($files)));
        }

        $this->assertSame(
            [],
            $dangling,
            'Vue components reference colour custom properties that no stylesheet defines. '
                . 'They resolve to `unset` at runtime and fall back to the browser default:' . $report
        );
    }
}
