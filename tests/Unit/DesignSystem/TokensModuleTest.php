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
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    private static function tokensPath(): string
    {
        return self::PROJECT_ROOT . self::TOKENS_REL_PATH;
    }

    private static function tailwindPath(): string
    {
        return self::PROJECT_ROOT . self::TAILWIND_REL_PATH;
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
  breakpoint: tokens.breakpoint
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
        $required = ['primary', 'neutral', 'success', 'warning', 'error', 'info'];
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
        $this->assertArrayHasKey('lg', $tokens['radius']);
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
            escapeshellarg(self::PROJECT_ROOT)
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
