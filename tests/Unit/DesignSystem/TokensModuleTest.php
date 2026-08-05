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
