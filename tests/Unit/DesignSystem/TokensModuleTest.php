<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Sprint 6 / bugfix-2026-08 slice 06 — design tokens source-of-truth guard.
 *
 * Verifies that `resources/js/design-system/tokens.js` exists, exports the
 * canonical token surface (colors, spacing, radius, typography, shadow,
 * breakpoint), and stays in parity with `tailwind.config.js` palette values.
 *
 * The test shells out to `node` via shell_exec to import the ESM module
 * from a temp loader script. No vitest install required.
 *
 * Failure indicates the design-system foundation regressed: a missing export,
 * a missing palette step, or a value drift between tokens.js and tailwind.
 */
class TokensModuleTest extends TestCase
{
    /** Tokens module absolute path. */
    private const TOKENS_REL_PATH = '/resources/js/design-system/tokens.js';

    /** Tailwind config absolute path. */
    private const TAILWIND_REL_PATH = '/tailwind.config.js';

    /** Project root absolute path. */
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    private static function tokensPath(): string
    {
        return self::projectRootPath() . self::TOKENS_REL_PATH;
    }

    private static function tailwindPath(): string
    {
        return self::projectRootPath() . self::TAILWIND_REL_PATH;
    }

    /**
     * Import tokens.js via Node and return its module.exports as an array.
     * Returns null when the file is missing or import fails.
     *
     * @return array<string, mixed>|null
     */
    private static function loadTokens(): ?array
    {
        $tokensPath = self::tokensPath();
        if (!is_file($tokensPath)) {
            return null;
        }

        // Escape the Windows path for embedding in a JS string literal.
        $escapedPath = addcslashes($tokensPath, "'\\");

        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
const url = pathToFileURL('TARGET_PATH').href;
const mod = await import(url);
const tokens = mod.default;
const out = {
  colors: tokens.colors,
  spacing: tokens.spacing,
  radius: tokens.radius,
  typography: tokens.typography,
  shadow: tokens.shadow,
  breakpoint: tokens.breakpoint,
  motion: tokens.motion
};
process.stdout.write(JSON.stringify(out));
JS;
        $loader = str_replace('TARGET_PATH', $escapedPath, $loader);

        // Write to a .mjs file so node ESM loader is happy without flags.
        $tmp = tempnam(sys_get_temp_dir(), 'tokens_loader_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $loader);
        @unlink($tmp);

        // Run via shell_exec — works around Node 24 on Windows crashing in
        // proc_open with ncrypto::CSPRNG when stdin pipe closes early.
        $cmd = 'node "' . $loaderFile . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        if ($output === null || $output === '') {
            return null;
        }

        // Strip any stderr noise that may precede JSON on 2>&1 capture.
        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            return null;
        }
        $jsonStr = substr($output, $jsonStart);
        $decoded = json_decode($jsonStr, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @test */
    public function tokens_module_file_exists(): void
    {
        $this->assertFileExists(
            self::tokensPath(),
            'resources/js/design-system/tokens.js must exist (FF-004 / AGENTS.md §2 reference)'
        );
    }

    /** @test */
    public function tokens_module_exports_canonical_keys(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');

        $required = ['colors', 'spacing', 'radius', 'typography', 'shadow', 'breakpoint'];
        foreach ($required as $key) {
            $this->assertArrayHasKey(
                $key,
                $tokens,
                "tokens.{$key} must be exported"
            );
            $this->assertIsArray(
                $tokens[$key],
                "tokens.{$key} must be an object/array"
            );
            $this->assertNotEmpty(
                $tokens[$key],
                "tokens.{$key} must not be empty"
            );
        }
    }

    /** @test */
    public function tokens_colors_include_semantic_states(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $colors = $tokens['colors'];

        // FF-004 / UXT-001 — semantic palette must exist with all documented states.
        // PR2: `info` was folded into `clinicalTeal`; `primary` kept as deprecated
        // alias for the 17 un-migrated modules until PR3 retires them.
        $required = ['primary', 'neutral', 'success', 'warning', 'error'];
        foreach ($required as $state) {
            $this->assertArrayHasKey($state, $colors, "tokens.colors.{$state} must exist");
        }

        // Success palette must include 50/100/500/600/700.
        $requiredSteps = ['50', '100', '500', '600', '700'];
        foreach ($requiredSteps as $step) {
            $this->assertArrayHasKey(
                $step,
                $colors['success'],
                "tokens.colors.success.{$step} must exist"
            );
            $this->assertArrayHasKey(
                $step,
                $colors['warning'],
                "tokens.colors.warning.{$step} must exist"
            );
            $this->assertArrayHasKey(
                $step,
                $colors['error'],
                "tokens.colors.error.{$step} must exist"
            );
        }

        // All hex values must match the format #RRGGBB.
        foreach (['success', 'warning', 'error'] as $state) {
            foreach ($colors[$state] as $step => $value) {
                $this->assertMatchesRegularExpression(
                    '/^#[0-9A-Fa-f]{6}$/',
                    (string) $value,
                    "tokens.colors.{$state}.{$step} must be a 6-digit hex value, got: {$value}"
                );
            }
        }
    }

    /** @test */
    public function tokens_spacing_and_radius_publish_canonical_steps(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');

        $this->assertArrayHasKey('4', $tokens['spacing']);
        $this->assertArrayHasKey('8', $tokens['spacing']);
        $this->assertArrayHasKey('16', $tokens['spacing']);

        $this->assertArrayHasKey('sm', $tokens['radius']);
        $this->assertArrayHasKey('md', $tokens['radius']);
        $this->assertArrayHasKey('ios', $tokens['radius']);
        $this->assertArrayHasKey('modal', $tokens['radius']);
        $this->assertArrayHasKey('full', $tokens['radius']);
    }

    /** @test */
    public function tokens_colors_stay_in_parity_with_tailwind_config_palette(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $tailwind = self::loadTailwindPalette();

        // Compare key semantic palette values that components consume.
        foreach (['success', 'warning', 'error', 'info', 'primary'] as $state) {
            foreach ($tailwind[$state] ?? [] as $step => $value) {
                $tokenValue = $tokens['colors'][$state][$step] ?? null;
                $this->assertNotNull(
                    $tokenValue,
                    "tokens.colors.{$state}.{$step} missing (tailwind has {$value})"
                );
                $this->assertSame(
                    strtolower((string) $value),
                    strtolower((string) $tokenValue),
                    "tokens.colors.{$state}.{$step} drift: tokens={$tokenValue} vs tailwind={$value}"
                );
            }
        }
    }

    /**
     * Shell out to ripgrep against `resources/` and return the number of
     * matching lines. `rg` is preferred over PowerShell `Select-String`
     * because the latter parses `|` as a pipeline token even inside quoted
     * regex patterns.
     */
    private static function grepResourceCount(string $pattern): int
    {
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s/resources 2>&1',
            escapeshellarg($pattern),
            escapeshellarg(self::projectRootPath())
        );
        $output = (string) shell_exec($cmd);
        if ($output === '') {
            return 0;
        }
        // rg --count-matches emits one line per file as `path:count` (or just
        // the count when stdin / single file). Strip the file prefix and sum.
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
     * Assert a grep over `resources/` returns exactly zero matches. Fails
     * loudly with the pattern + actual count so the next operator can see
     * which file still ships the dead code.
     */
    private static function assertResourceGrepReturnsZero(string $pattern, string $message): void
    {
        $count = self::grepResourceCount($pattern);
        self::assertSame(
            0,
            $count,
            $message . " (pattern: {$pattern}, count: {$count})"
        );
    }

    /**
     * Task 1.1.1 — anti-requirement guard. After PR1 the dark-mode
     * machinery is fully gone: `useTheme`, the `setTheme` /
     * `getThemeOptions` exports, the `ThemeSelector` UI, the stale
     * `design-system.js` duplicate, and the dead `MobileNavigation`
     * component must all be absent from `resources/`.
     *
     * @test
     */
    public function theme_machinery_removed(): void
    {
        self::assertResourceGrepReturnsZero(
            'useTheme|setTheme|getThemeOptions|ThemeSelector|design-system.js|MobileNavigation',
            'PR1 must remove every dark-mode / dead-component reference from resources/'
        );
    }

    /**
     * Task 1.1.2 — anti-requirement guard. No `@media
     * (prefers-color-scheme: dark)` block may remain anywhere under
     * `resources/` once the design system is light-only.
     *
     * @test
     */
    public function no_dark_mode_blocks_in_resources(): void
    {
        self::assertResourceGrepReturnsZero(
            'prefers-color-scheme: dark',
            'PR1 must delete every prefers-color-scheme: dark block from resources/'
        );
    }

    /**
     * Task 1.1.3 — guard that no code writes a `theme` key into
     * `localStorage`. Per orchestrator correction (2026-08-10): the
     * pre-existing `useTheme.js` wrote `odontosuite-theme` but the
     * spec key is the bare `'theme'` — once `useTheme.js` is deleted,
     * no theme key (any flavor) may be written. A stale `theme` key in
     * a user's browser is intentionally inert on next visit; no
     * read-once bootstrap is required.
     *
     * @test
     */
    public function app_bootstrap_ignores_stale_theme_localstorage_key(): void
    {
        self::assertResourceGrepReturnsZero(
            "setItem\\('theme'",
            'PR1 must delete every localStorage theme write; stale keys become inert naturally'
        );
    }

    /**
     * Task 1.1.4 — guard that `Avatar.vue` no longer carries its own
     * `@media (prefers-color-scheme: dark)` block. The Avatar was the
     * only file under `resources/js/components/ui/` that had one.
     *
     * @test
     */
    public function avatar_dark_mode_blocks_removed(): void
    {
        self::assertResourceGrepReturnsZero(
            'prefers-color-scheme: dark',
            'Avatar.vue must drop its prefers-color-scheme: dark block'
        );
    }

    /**
     * Task 2.1.1 — DEPRECATED: previous design's terracotta/cream/ink/clinicalTeal
     * ramps are replaced by iOS 13+ system color ramps + background/label/separator/fill
     * ramps + deprecated alias keys. The new ramps are asserted in
     * tokens_module_exposes_ios_system_color_ramps (Task 1.1.1) and
     * tokens_module_deprecated_aliases_resolve (Task 1.1.9).
     *
     * @test
     */
    public function tokens_module_exposes_ios_ramps_with_aliases(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $colors = $tokens['colors'];

        // New primary ramps (iOS system colors).
        foreach ([
            'systemBlue', 'systemRed', 'systemOrange', 'systemYellow',
            'systemGreen', 'systemIndigo', 'systemPurple', 'systemPink', 'systemGray'
        ] as $ramp) {
            $this->assertArrayHasKey(
                $ramp,
                $colors,
                "tokens.colors.{$ramp} must exist (iOS 13+ system colors)"
            );
        }

        // iOS background ramp.
        $this->assertArrayHasKey('background', $colors, 'tokens.colors.background must exist');
        foreach (['systemBackground', 'secondaryBackground', 'tertiaryBackground', 'groupedBackground'] as $k) {
            $this->assertArrayHasKey(
                $k,
                $colors['background'],
                "tokens.colors.background.{$k} must exist"
            );
        }

        // iOS label ramp.
        $this->assertArrayHasKey('label', $colors, 'tokens.colors.label must exist');
        foreach (['label', 'secondaryLabel', 'tertiaryLabel', 'quaternaryLabel'] as $k) {
            $this->assertArrayHasKey(
                $k,
                $colors['label'],
                "tokens.colors.label.{$k} must exist"
            );
        }

        // iOS separator and fill ramps.
        $this->assertArrayHasKey('separator', $colors, 'tokens.colors.separator must exist');
        $this->assertArrayHasKey('separator', $colors['separator'], 'separator.separator must exist');
        $this->assertArrayHasKey('fill', $colors, 'tokens.colors.fill must exist');
        foreach (['systemFill', 'secondarySystemFill', 'tertiarySystemFill'] as $k) {
            $this->assertArrayHasKey(
                $k,
                $colors['fill'],
                "tokens.colors.fill.{$k} must exist"
            );
        }

        // Deprecated alias keys must exist (preserve 17 un-migrated modules).
        foreach (['cream', 'terracotta', 'clinicalTeal', 'info'] as $alias) {
            $this->assertArrayHasKey(
                $alias,
                $colors,
                "tokens.colors.{$alias} deprecated alias must exist"
            );
        }
    }

    /**
     * Task 2.1.2 — anti-requirement guard. The `info` ramp survives as a
     * single-step deprecated alias (info.500 -> systemBlue.500). The
     * `terracotta`/`cream`/`clinicalTeal` aliases carry only the steps the
     * 17 un-migrated modules actually consume. No ramp key ends in a
     * dark-suffix anywhere.
     *
     * @test
     */
    public function tokens_module_aliases_shape_and_no_dark_suffixes(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $colors = $tokens['colors'];

        // info ramp survives ONLY as a single-step alias (500).
        $this->assertArrayHasKey('info', $colors, 'tokens.colors.info alias must exist');
        $this->assertSame(
            [500],
            array_keys($colors['info']),
            'tokens.colors.info must contain only the [500] step (single-step alias)'
        );

        // No key in colors ends in -dark, Dark, or _dark.
        foreach (array_keys($colors) as $rampName) {
            $this->assertDoesNotMatchRegularExpression(
                '/(^|_|-)(dark|Dark|DARK)$/',
                (string) $rampName,
                "tokens.colors.{$rampName} ends with a dark-suffix and must be removed"
            );
        }

        foreach ($colors as $rampName => $steps) {
            if (!is_array($steps)) {
                continue;
            }
            foreach (array_keys($steps) as $stepName) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(^|_|-)(dark|Dark|DARK)$/',
                    (string) $stepName,
                    "tokens.colors.{$rampName}.{$stepName} ends with a dark-suffix and must be removed"
                );
            }
        }
    }

    /**
     * Task 1.1.1 — token surface must expose the iOS 13+ system color ramps
     * at steps {50, 100, 500, 600, 700} plus background / label / separator /
     * fill ramps. Each ramp step is a 6-digit lowercase hex literal.
     *
     * @test
     */
    public function tokens_module_exposes_ios_system_color_ramps(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $colors = $tokens['colors'];

        foreach ([
            'systemBlue', 'systemRed', 'systemOrange', 'systemYellow',
            'systemGreen', 'systemIndigo', 'systemPurple', 'systemPink', 'systemGray'
        ] as $ramp) {
            $this->assertArrayHasKey(
                $ramp,
                $colors,
                "tokens.colors.{$ramp} must exist (iOS 13+ system color ramp)"
            );
            foreach (['50', '100', '500', '600', '700'] as $step) {
                $this->assertArrayHasKey(
                    $step,
                    $colors[$ramp],
                    "tokens.colors.{$ramp}.{$step} must exist"
                );
                $this->assertMatchesRegularExpression(
                    '/^#[0-9A-Fa-f]{6}$/',
                    (string) $colors[$ramp][$step],
                    "tokens.colors.{$ramp}.{$step} must be a 6-digit hex literal"
                );
            }
        }
    }

    /**
     * Task 1.1.2 — literal hex checks: every iOS system color hex matches
     * the canonical iOS 13+ reference value, plus background/label/separator.
     *
     * @test
     */
    public function tokens_module_hex_literals_match_ios_palette(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $colors = $tokens['colors'];

        $cases = [
            ['systemBlue', '500', '#007AFF'],
            ['systemRed', '500', '#FF3B30'],
            ['systemOrange', '500', '#FF9500'],
            ['systemYellow', '500', '#FFCC00'],
            ['systemGreen', '500', '#34C759'],
            ['systemIndigo', '500', '#5856D6'],
            ['systemPurple', '500', '#AF52DE'],
            ['systemPink', '500', '#FF2D55'],
        ];
        foreach ($cases as [$ramp, $step, $hex]) {
            $this->assertSame(
                $hex,
                strtoupper((string) $colors[$ramp][$step]),
                "tokens.colors.{$ramp}.{$step} must equal {$hex}"
            );
        }

        $this->assertSame(
            '#FFFFFF',
            strtoupper((string) $colors['background']['systemBackground']),
            'tokens.colors.background.systemBackground must be #FFFFFF'
        );
        $this->assertSame(
            '#000000',
            strtoupper((string) $colors['label']['label']),
            'tokens.colors.label.label must be #000000'
        );
        $this->assertSame(
            '#C6C6C8',
            strtoupper((string) $colors['separator']['separator']),
            'tokens.colors.separator.separator must be #C6C6C8'
        );
    }

    /**
     * Task 1.1.3 — radius scale: `ios` (10 px) for cards/buttons/chips,
     * `modal` (14 px) for Modal/Sheet. `lg/2xl/3xl` are removed.
     *
     * @test
     */
    public function tokens_module_radius_ios_and_modal(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');

        $this->assertSame('10px', $tokens['radius']['ios'], 'tokens.radius.ios must be 10px');
        $this->assertSame('14px', $tokens['radius']['modal'], 'tokens.radius.modal must be 14px');
        $this->assertSame('4px', $tokens['radius']['sm'], 'tokens.radius.sm must be 4px');
        $this->assertSame('9999px', $tokens['radius']['full'], 'tokens.radius.full must be 9999px');

        foreach (['lg', '2xl', '3xl'] as $legacy) {
            $this->assertArrayNotHasKey(
                $legacy,
                $tokens['radius'],
                "tokens.radius.{$legacy} must be removed (replaced by radius.ios/modal)"
            );
        }
    }

    /**
     * Task 1.1.4 — typography.fontFamily.serif is removed. fontFamily.sans
     * remains as a non-empty array starting with -apple-system.
     *
     * @test
     */
    public function tokens_module_font_family_sans_only(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');

        $fontFamily = $tokens['typography']['fontFamily'];
        $this->assertIsArray($fontFamily, 'tokens.typography.fontFamily must be an object');
        $this->assertArrayHasKey('sans', $fontFamily, 'tokens.typography.fontFamily.sans must exist');
        $this->assertIsArray($fontFamily['sans'], 'tokens.typography.fontFamily.sans must be an array');
        $this->assertNotEmpty($fontFamily['sans'], 'tokens.typography.fontFamily.sans must not be empty');
        $this->assertSame(
            '-apple-system',
            (string) $fontFamily['sans'][0],
            'tokens.typography.fontFamily.sans[0] must be -apple-system'
        );
        $this->assertArrayNotHasKey(
            'serif',
            $fontFamily,
            'tokens.typography.fontFamily.serif must be removed (Newsreader retired)'
        );
    }

    /**
     * Task 1.1.5 — letterSpacing table tuned for SF/system: xs/sm/base/lg = 0,
     * xl = -0.01em, 2xl = -0.015em, 3xl = -0.02em, 4xl/display/hero = -0.022em.
     * No fontSize step sets font-optical-sizing (system font has no opsz axis).
     *
     * @test
     */
    public function tokens_module_letter_spacing_table(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');

        $fontSize = $tokens['typography']['fontSize'];
        $expected = [
            'xs' => '0',
            'sm' => '0',
            'base' => '0',
            'lg' => '0',
            'xl' => '-0.01em',
            '2xl' => '-0.015em',
            '3xl' => '-0.02em',
            '4xl' => '-0.022em',
            'display' => '-0.022em',
            'hero' => '-0.022em',
        ];
        foreach ($expected as $step => $ls) {
            $this->assertArrayHasKey($step, $fontSize, "tokens.typography.fontSize.{$step} must exist");
            $opts = $fontSize[$step][1] ?? [];
            $this->assertSame(
                $ls,
                (string) ($opts['letterSpacing'] ?? null),
                "tokens.typography.fontSize.{$step}.letterSpacing must equal {$ls}"
            );
            $this->assertArrayNotHasKey(
                'font-optical-sizing',
                $opts,
                "tokens.typography.fontSize.{$step} must not set font-optical-sizing"
            );
        }
    }

    /**
     * Read `resources/css/tokens.generated.css` and return its raw bytes.
     */
    private static function loadGeneratedCss(): ?string
    {
        $path = self::projectRootPath() . '/resources/css/tokens.generated.css';
        if (!is_file($path)) {
            return null;
        }
        return (string) file_get_contents($path);
    }

    /**
     * Run `git ls-files <path>` and return true when the file is tracked.
     */
    private static function gitLsFilesTracked(string $relPath): bool
    {
        $cmd = sprintf(
            'git ls-files --error-unmatch -- %s 1>nul 2>nul',
            escapeshellarg($relPath)
        );
        $rc = 0;
        $output = [];
        $exe = self::projectRootPath() . DIRECTORY_SEPARATOR;
        // chdir to project root so git picks up the right repo.
        $prev = getcwd();
        if ($prev !== false) {
            chdir($exe);
        }
        exec('git ls-files --error-unmatch ' . escapeshellarg($relPath) . ' 1>nul 2>nul', $output, $rc);
        if ($prev !== false) {
            chdir($prev);
        }
        return $rc === 0;
    }

    /**
     * Task 1.1.6 — Newsreader binary + useFontsLoaded composable absent.
     * No `Newsreader`/`useFontsLoaded`/`var(--font-serif)` references remain
     * under `resources/`.
     *
     * @test
     */
    public function tokens_module_no_newsreader_no_use_fonts_loaded(): void
    {
        // File absence.
        $this->assertFalse(
            self::gitLsFilesTracked('public/fonts/newsreader-latin.woff2'),
            'public/fonts/newsreader-latin.woff2 must be deleted'
        );
        $this->assertFalse(
            self::gitLsFilesTracked('resources/js/composables/useFontsLoaded.js'),
            'resources/js/composables/useFontsLoaded.js must be deleted'
        );

        // Source-level grep absence.
        self::assertResourceGrepReturnsZero(
            'Newsreader',
            'No `Newsreader` references may remain under resources/'
        );
        self::assertResourceGrepReturnsZero(
            'useFontsLoaded',
            'No `useFontsLoaded` references may remain under resources/'
        );
        self::assertResourceGrepReturnsZero(
            'var\(--font-serif\)',
            'No `var(--font-serif)` references may remain under resources/'
        );
    }

    /**
     * Task 1.1.7 — forbidden cream / terracotta / clinicalTeal hex literals
     * must not appear anywhere in `resources/` outside the SoT files
     * (tokens.js + tokens.generated.css).
     *
     * @test
     */
    public function tokens_module_no_cream_terracotta_clinical_teal_literals(): void
    {
        // Strip tokens.js + tokens.generated.css (allowed SoT files) from the
        // project root and grep the rest of resources/.
        $root = self::projectRootPath();
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s 2>&1',
            escapeshellarg('#FAF9F7|#F2EFE9|#E8E3D8|#C96442|#B05432|#2C7A7B'),
            escapeshellarg($root . '/resources')
        );
        $output = (string) shell_exec($cmd);
        $total = 0;
        foreach (preg_split('/\r?\n/', $output) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, 'tokens.js') || str_contains($line, 'tokens.generated.css')) {
                continue;
            }
            $count = (int) (str_contains($line, ':') ? substr($line, strrpos($line, ':') + 1) : $line);
            $total += $count;
        }
        $this->assertSame(
            0,
            $total,
            'Forbidden cream/terracotta/clinicalTeal hex literals must be absent outside tokens.js + tokens.generated.css'
        );
    }

    /**
     * Task 1.1.8 — anti-requirement guard. No `prefers-color-scheme: dark`
     * block may exist under `resources/` (light-only design system).
     *
     * @test
     */
    public function tokens_module_no_dark_mode_blocks(): void
    {
        self::assertResourceGrepReturnsZero(
            'prefers-color-scheme: dark',
            'PR1+ must remove every prefers-color-scheme: dark block from resources/'
        );
    }

    /**
     * Task 1.1.9 — deprecated alias keys resolve to iOS system colors. The
     * 17 un-migrated modules' Tailwind classes keep rendering without churn.
     *
     * @test
     */
    public function tokens_module_deprecated_aliases_resolve(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $colors = $tokens['colors'];

        // cream -> systemGray family.
        $this->assertSame(
            '#F2F2F7',
            strtoupper((string) $colors['cream']['50']),
            'tokens.colors.cream.50 must alias systemGray-50 (#F2F2F7)'
        );
        $this->assertSame(
            '#E5E5EA',
            strtoupper((string) $colors['cream']['100']),
            'tokens.colors.cream.100 must alias systemGray-100 (#E5E5EA)'
        );
        $this->assertSame(
            '#D1D1D6',
            strtoupper((string) $colors['cream']['200']),
            'tokens.colors.cream.200 must alias systemGray-200 (#D1D1D6)'
        );

        // terracotta -> systemBlue.
        $this->assertSame(
            '#007AFF',
            strtoupper((string) $colors['terracotta']['500']),
            'tokens.colors.terracotta.500 must alias systemBlue-500 (#007AFF)'
        );
        $this->assertSame(
            '#0062CC',
            strtoupper((string) $colors['terracotta']['600']),
            'tokens.colors.terracotta.600 must alias systemBlue-600 (#0062CC)'
        );

        // clinicalTeal -> systemBlue.
        $this->assertSame(
            '#E5F1FF',
            strtoupper((string) $colors['clinicalTeal']['50']),
            'tokens.colors.clinicalTeal.50 must alias systemBlue-50 (#E5F1FF)'
        );
        $this->assertSame(
            '#007AFF',
            strtoupper((string) $colors['clinicalTeal']['500']),
            'tokens.colors.clinicalTeal.500 must alias systemBlue-500 (#007AFF)'
        );

        // info -> systemBlue-500 (iOS convention: blue = info).
        $this->assertSame(
            '#007AFF',
            strtoupper((string) $colors['info']['500']),
            'tokens.colors.info.500 must alias systemBlue-500 (#007AFF)'
        );
    }

    /**
     * Task 1.1.10 — generated CSS contains no `@font-face` block, no
     * `--font-serif` declaration, and no `newsreader` reference anywhere.
     *
     * @test
     */
    public function generated_css_has_no_font_face_no_font_serif(): void
    {
        $css = self::loadGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist (run `pnpm tokens:build`)');

        $this->assertDoesNotMatchRegularExpression(
            '/@font-face/i',
            (string) $css,
            'tokens.generated.css must not contain any @font-face block'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/--font-serif\s*:/i',
            (string) $css,
            'tokens.generated.css must not contain --font-serif declaration'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/newsreader/i',
            (string) $css,
            'tokens.generated.css must not reference newsreader'
        );
    }

    /**
     * Task 1.1.11 — generated CSS `.surface-glass` rgba is white-on-white
     * (rgb(255 255 255 / ...)). Shadow ramp uses rgba(0, 0, 0, ...).
     *
     * @test
     */
    public function generated_css_surface_glass_uses_white_on_white_and_pure_black_shadow(): void
    {
        $css = self::loadGeneratedCss();
        $this->assertNotNull($css, 'tokens.generated.css must exist');

        // .surface-glass background must match white-on-white rgba.
        $this->assertMatchesRegularExpression(
            '/\.surface-glass\s*\{[^}]*rgb\(\s*255\s+255\s+255\s*\/\s*0\.78\s*\)/i',
            (string) $css,
            '.surface-glass background must be rgb(255 255 255 / 0.78)'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.surface-glass\s*\{[^}]*rgb\(\s*250\s+249\s+247/i',
            (string) $css,
            '.surface-glass must not carry cream-on-cream rgba'
        );

        // No warm-black rgba(20, 17, 14, ...) anywhere.
        $this->assertDoesNotMatchRegularExpression(
            '/rgba\(\s*20\s*,\s*17\s*,\s*14\b/i',
            (string) $css,
            'tokens.generated.css must not use warm-black rgba(20, 17, 14, ...) shadows'
        );

        // Shadow ramp uses rgba(0, 0, 0, ...).
        $this->assertMatchesRegularExpression(
            '/--shadow-(sm|md|lg|xl)\s*:\s*[^;]*rgba\(\s*0\s*,\s*0\s*,\s*0\s*,/i',
            (string) $css,
            'tokens.generated.css shadow ramp must use rgba(0, 0, 0, ...) (pure black)'
        );
    }

    /**
     * Task 2.1.4 — motion tokens section remains intact (consumed by
     * useSpring / useSpring2D / generator). The values are unchanged from
     * the previous design; this test guards against accidental removal.
     *
     * @test
     */
    public function tokens_module_motion_section_present(): void
    {
        $tokens = self::loadTokens();
        $this->assertNotNull($tokens, 'loadTokens() must succeed');
        $this->assertArrayHasKey('motion', $tokens, 'tokens.motion section must exist');
        $motion = $tokens['motion'];
        $this->assertIsArray($motion, 'tokens.motion must be an object');
        $this->assertArrayHasKey('response', $motion, 'tokens.motion.response must exist');
        $this->assertArrayHasKey('damping', $motion, 'tokens.motion.damping must exist');
        $this->assertSame(
            0.35,
            (float) $motion['response'],
            'tokens.motion.response must be 0.35'
        );
        $this->assertSame(
            1.0,
            (float) $motion['damping'],
            'tokens.motion.damping must be 1.0'
        );
    }

    /**
     * Parse tailwind.config.js palette blocks by string extraction. This is a
     * brittle but acceptable check since the file format is hand-curated and
     * under our control.
     *
     * @return array<string, array<string, string>>
     */
    private static function loadTailwindPalette(): array
    {
        $source = file_get_contents(self::tailwindPath());
        $palette = [];

        // Match each palette entry `name: { ... }` at 8-space indent. The
        // palette closing `}` sits at 8 spaces too.
        preg_match_all('/^        (\w+):\s*\{(.*?)^\s{8}\}/sm', $source, $entries, PREG_SET_ORDER);
        foreach ($entries as $entry) {
            $name = $entry[1];
            $palette[$name] = [];
            preg_match_all("/(\d+):\s*'(#[0-9A-Fa-f]{6})'/", $entry[2], $steps, PREG_SET_ORDER);
            foreach ($steps as $step) {
                $palette[$name][$step[1]] = $step[2];
            }
        }
        return $palette;
    }
}
