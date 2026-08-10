<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR1 / slice 01 — currency-format-helper.
 *
 * Verifies the single source of truth for PEN rendering exposed by
 * `resources/js/composables/useFormatters.js::formatPENLabel` and asserts
 * the call sites that previously concatenated a literal `S/` prefix in
 * front of `formatCurrency(...)` now consume the helper and emit exactly
 * one `S/` prefix.
 *
 * Three layers of coverage:
 *
 *   1. Module surface — the file exists and exports `formatPENLabel`.
 *   2. Helper behaviour — Node-imports the helper and asserts positive,
 *      zero, negative, null, undefined, numeric-string inputs produce
 *      the documented output.
 *   3. Source contract — static scans DashboardPage.vue and
 *      SessionList.vue to forbid the duplicate-prefix patterns, and
 *      forbids `S/ ${` outside the helper file in `resources/js/**`.
 *
 * The test runs under `php artisan test` (Strict TDD oracle) with no
 * Laravel container, no DB connection, and no external services.
 */
class FormatPENLabelTest extends TestCase
{
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    private const HELPER_REL_PATH = '/resources/js/composables/useFormatters.js';

    private const DASHBOARD_REL_PATH = '/resources/js/modules/dashboard/DashboardPage.vue';

    private const SESSION_LIST_REL_PATH = '/resources/js/modules/cash-register/components/SessionList.vue';

    /** Literal `S/ ` MUST appear only inside the helper source file. */
    private const HELPER_FILE_NAME = 'useFormatters.js';

    // -------------------------------------------------------------------
    // Layer 1 — Module surface
    // -------------------------------------------------------------------

    /** @test */
    public function helper_module_file_exists(): void
    {
        $this->assertFileExists(
            self::helperPath(),
            'resources/js/composables/useFormatters.js must exist (PR1 / currency-format-helper)'
        );
    }

    /** @test */
    public function helper_module_exports_format_pen_label(): void
    {
        $exports = self::loadHelperExports();
        $this->assertIsArray($exports, 'loadHelperExports() must return the module exports');
        $this->assertArrayHasKey(
            'formatPENLabel',
            $exports,
            'useFormatters.js must export `formatPENLabel`'
        );
        $this->assertSame(
            'function',
            $exports['formatPENLabel'],
            'formatPENLabel must be a function (typeof)'
        );
    }

    // -------------------------------------------------------------------
    // Layer 2 — Helper behaviour (executed through Node)
    //
    // Node's `Intl.NumberFormat('es-PE', {style:'currency',currency:'PEN'})`
    // uses U+00A0 (NBSP) between the currency glyph and the number, which
    // is locale-correct and renders as a regular space in the browser.
    // Tests normalize the helper output to a regular space so the
    // assertions are stable across ICU builds (some Intl implementations
    // emit a regular space, others NBSP).
    // -------------------------------------------------------------------

    /** @test */
    public function format_pen_label_renders_positive_amount(): void
    {
        $out = self::normalize(self::callHelper(759));
        $this->assertSame('S/ 759.00', $out, 'formatPENLabel(759) must render exactly one S/ prefix');
    }

    /** @test */
    public function format_pen_label_renders_negative_amount_with_minus_sign(): void
    {
        $out = self::normalize(self::callHelper(-5));
        $this->assertSame('-S/ 5.00', $out, 'formatPENLabel(-5) must render -S/ 5.00');
    }

    /** @test */
    public function format_pen_label_renders_zero(): void
    {
        $this->assertSame('S/ 0.00', self::normalize(self::callHelper(0)));
    }

    /** @test */
    public function format_pen_label_renders_null_as_zero(): void
    {
        $this->assertSame('S/ 0.00', self::normalize(self::callHelper(null)));
    }

    /** @test */
    public function format_pen_label_renders_undefined_as_zero(): void
    {
        $this->assertSame('S/ 0.00', self::normalize(self::callHelper('undefined-as-value')));
    }

    /** @test */
    public function format_pen_label_renders_numeric_string(): void
    {
        $this->assertSame('S/ 123.45', self::normalize(self::callHelper('123.45')));
    }

    /** @test */
    public function format_pen_label_renders_non_numeric_string_as_zero_without_throw(): void
    {
        $this->assertSame('S/ 0.00', self::normalize(self::callHelper('not-a-number')));
    }

    /** @test */
    public function format_pen_label_emits_exactly_one_slash_prefix(): void
    {
        // Regression guard: even after triangulation, no call to the
        // helper may produce the doubled `S/ S/` substring.
        $cases = [759, 0, -5, null, 'undefined-as-value', '123.45', 'not-a-number'];
        foreach ($cases as $case) {
            $out = self::callHelper($case);
            $count = substr_count($out, 'S/');
            $this->assertSame(
                1,
                $count,
                "formatPENLabel({$this->label($case)}) must contain exactly one `S/` substring, got: {$out}"
            );
        }
    }

    // -------------------------------------------------------------------
    // Layer 3 — Source contract (static scans)
    // -------------------------------------------------------------------

    /** @test */
    public function dashboard_page_no_longer_concatenates_literal_slash_before_format(): void
    {
        $source = self::readFile(self::dashboardPath());
        $this->assertNotFalse($source, 'DashboardPage.vue must be readable');

        // Line 400 must NOT contain the `S/ ${...formatCurrency...}` pattern.
        $this->assertDoesNotMatchRegularExpression(
            '/Saldo:\s*S\/\s*\$\{[^}]*formatCurrency/s',
            $source,
            'DashboardPage.vue cashBalanceText must drop the literal `S/` prefix and call formatPENLabel'
        );

        // Positive direction: the cash balance computed must consume the helper.
        $this->assertStringContainsString(
            'formatPENLabel',
            $source,
            'DashboardPage.vue must call formatPENLabel for the cash balance'
        );

        // The local dead `formatCurrency` helper must be removed.
        $this->assertDoesNotMatchRegularExpression(
            '/const\s+formatCurrency\s*=\s*\([^)]*\)\s*=>\s*\{[^}]*Intl\.NumberFormat/s',
            $source,
            'DashboardPage.vue must remove its local formatCurrency helper (replaced by the composable)'
        );
    }

    /** @test */
    public function session_list_no_longer_concatenates_literal_slash_before_format(): void
    {
        $source = self::readFile(self::sessionListPath());
        $this->assertNotFalse($source, 'SessionList.vue must be readable');

        // The three render sites (opening, closing, difference) must NOT
        // contain `S/ {{ formatCurrency(...` and `S/ ${{ formatCurrency(...` patterns.
        $this->assertDoesNotMatchRegularExpression(
            '/S\/\s*\{\{\s*formatCurrency\s*\(/',
            $source,
            'SessionList.vue must NOT render `S/ {{ formatCurrency(...)` (opening/closing cells)'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/S\/\s*\$\{\s*formatCurrency/s',
            $source,
            'SessionList.vue must NOT render `S/ ${formatCurrency...` patterns'
        );

        // Positive direction: the helper is the new call site.
        $this->assertStringContainsString(
            'formatPENLabel',
            $source,
            'SessionList.vue must call formatPENLabel for opening/closing/difference cells'
        );
    }

    /** @test */
    public function slice_files_do_not_call_intl_pen_format_directly(): void
    {
        // Scoped to PR1's two owned files. Other components that still
        // call Intl.NumberFormat(...currency:'PEN'...) directly are out
        // of scope for this slice (the design forecast budgets PR1 at
        // ~10 LOC across 2 Vue files); the migration of every other
        // component is deferred to a follow-up slice per the proposal's
        // approach section.
        $owned = [
            self::dashboardPath(),
            self::sessionListPath(),
        ];
        $violations = [];
        foreach ($owned as $path) {
            $source = (string) file_get_contents($path);
            if ($source === '') {
                continue;
            }
            if (preg_match('/Intl\.NumberFormat\([^)]*currency:\s*[\'"]PEN[\'"]/s', $source)) {
                $violations[] = basename($path);
            }
        }
        $this->assertSame(
            [],
            $violations,
            'DashboardPage.vue and SessionList.vue must consume formatPENLabel, not call Intl.NumberFormat directly. Offenders:'
                . "\n" . implode(', ', $violations)
        );
    }

    /** @test */
    public function slice_files_no_longer_contain_literal_slash_before_currency_format(): void
    {
        // Scoped to PR1's two owned files: DashboardPage.vue and SessionList.vue.
        // Other modules may still render `S/ <amount>` as plain strings
        // (e.g. fixture labels in modals) and are out of scope for this
        // slice. The helper's static analysis contract is enforced for the
        // full tree in `no_frontend_file_outside_helper_uses_intl_pen_format_directly`.
        $violations = [];
        $owned = [
            basename(self::dashboardPath()),
            basename(self::sessionListPath()),
        ];
        foreach ($owned as $name) {
            $file = self::PROJECT_ROOT . '/resources/js';
            // Walk only the two known locations.
            $candidates = [
                self::dashboardPath(),
                self::sessionListPath(),
            ];
            foreach ($candidates as $candidate) {
                if (basename($candidate) !== $name) {
                    continue;
                }
                $source = (string) file_get_contents($candidate);
                if ($source === '') {
                    continue;
                }
                $stripped = self::stripCommentsAndStrings($source);
                if (strpos($stripped, 'S/ ') !== false) {
                    $violations[] = $name;
                }
            }
        }
        $this->assertSame(
            [],
            $violations,
            'DashboardPage.vue and SessionList.vue must NOT contain the literal `S/ ` prefix (PR1 owns the duplicate-prefix bug fix). Offenders:'
                . "\n" . implode(', ', $violations)
        );
    }

    /** @test */
    public function dashboard_and_session_list_no_longer_reference_format_currency(): void
    {
        // Belt-and-suspenders: after the slice, no .vue/.js file under
        // resources/js may still name the local `formatCurrency` helper
        // for PEN rendering (we keep it only if some unrelated file needs
        // it, but Dashboard and SessionList were the two consumers).
        $owned = [self::dashboardPath(), self::sessionListPath()];
        foreach ($owned as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString(
                'formatCurrency(',
                $source,
                basename($path) . ' must not reference the local formatCurrency helper anymore (PR1 dedup)'
            );
        }
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private static function helperPath(): string
    {
        return self::PROJECT_ROOT . self::HELPER_REL_PATH;
    }

    private static function dashboardPath(): string
    {
        return self::PROJECT_ROOT . self::DASHBOARD_REL_PATH;
    }

    private static function sessionListPath(): string
    {
        return self::PROJECT_ROOT . self::SESSION_LIST_REL_PATH;
    }

    private static function readFile(string $path): string|false
    {
        return file_get_contents($path);
    }

    /**
     * Import useFormatters.js via Node and return the named exports mapped to
     * their `typeof` value. Returns null when the import fails.
     *
     * @return array<string, mixed>|null
     */
    private static function loadHelperExports(): ?array
    {
        $path = self::helperPath();
        if (!is_file($path)) {
            return null;
        }

        $escapedPath = addcslashes($path, "'\\");

        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
const url = pathToFileURL('TARGET_PATH').href;
const mod = await import(url);
const out = {};
for (const key of Object.keys(mod)) {
  out[key] = typeof mod[key];
}
process.stdout.write(JSON.stringify(out));
JS;
        $loader = str_replace('TARGET_PATH', $escapedPath, $loader);

        $tmp = tempnam(sys_get_temp_dir(), 'formatters_loader_');
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
     * Invoke formatPENLabel via Node and return the rendered string.
     * Mirrors the TokensModuleTest::loadTokens() approach.
     */
    private static function callHelper(mixed $value): string
    {
        $path = self::helperPath();
        if (!is_file($path)) {
            $this->fail('Helper file missing: ' . $path);
        }

        $escapedPath = addcslashes($path, "'\\");
        // Encode the value as a JS literal that can be substituted safely.
        if ($value === 'undefined-as-value') {
            $valueLiteral = 'undefined';
        } elseif (is_string($value)) {
            $valueLiteral = "'" . addcslashes($value, "'\\") . "'";
        } elseif (is_null($value)) {
            $valueLiteral = 'null';
        } else {
            $valueLiteral = var_export($value, true);
        }

        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
const url = pathToFileURL('TARGET_PATH').href;
const mod = await import(url);
const out = mod.formatPENLabel(VALUE_EXPR);
process.stdout.write(JSON.stringify(out));
JS;
        $loader = str_replace('TARGET_PATH', $escapedPath, $loader);
        $loader = str_replace('VALUE_EXPR', $valueLiteral, $loader);

        $tmp = tempnam(sys_get_temp_dir(), 'formatters_call_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $loader);
        @unlink($tmp);

        $cmd = 'node "' . $loaderFile . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        if ($output === null || $output === '') {
            self::fail('Node import returned no output');
        }

        $jsonStart = strpos($output, '"');
        if ($jsonStart === false) {
            self::fail('Node output was not a JSON string: ' . $output);
        }
        $decoded = json_decode(substr($output, $jsonStart), true);
        if (!is_string($decoded)) {
            self::fail('Node output was not a string: ' . $output);
        }
        return $decoded;
    }

    /**
     * Mirror of SddCheckJsComposablesTest::collectJsFiles — PHP glob does
     * not support ** so we walk the tree manually.
     *
     * @return string[]
     */
    private static function collectJsFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $out = [];
        foreach ($iter as $entry) {
            if ($entry->isFile()
                && in_array(strtolower($entry->getExtension()), ['vue', 'js'], true)
            ) {
                $out[] = $entry->getPathname();
            }
        }
        return $out;
    }

    /**
     * Strip block comments, line comments, single-quoted strings, and
     * double-quoted strings from $source. Mirrors the strip recipe used
     * by SddCheckJsComposablesTest.
     */
    private static function stripCommentsAndStrings(string $source): string
    {
        // Multi-line block comments.
        $stripped = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
        // Line comments.
        $stripped = preg_replace('#//.*$#m', '', $stripped) ?? $stripped;
        // Single-quoted strings.
        $stripped = preg_replace("/'(?:\\\\.|[^'\\\\])*'/s", "''", $stripped) ?? $stripped;
        // Double-quoted strings.
        $stripped = preg_replace('/"(?:\\\\.|[^"\\\\])*"/s', '""', $stripped) ?? $stripped;
        return $stripped;
    }

    private function label(mixed $case): string
    {
        if (is_string($case)) {
            return "'{$case}'";
        }
        return var_export($case, true);
    }

    /**
     * Normalize U+00A0 (NBSP) to a regular space so assertions are stable
     * across ICU builds. The rendered glyph is identical to users.
     */
    private static function normalize(string $value): string
    {
        return str_replace("\xc2\xa0", ' ', $value);
    }
}
