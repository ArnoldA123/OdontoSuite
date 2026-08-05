<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * fix-composables-response-unwrap-2026-08 (NEW-005): CI guard for the
 * flat-envelope response shape contract.
 *
 * useApi.handleResponse() at resources/js/composables/useApi.js:74
 * returns the flat JSON envelope of the shape { data, meta } verbatim.
 * Consumers MUST treat the resolved value as that envelope and read at
 * ONE level only: response.data, response?.data, response.meta.
 *
 * Forbidden on the success path (any of):
 *   - response.data.data
 *   - response.data?.data
 *   - response.data.meta
 *   - response.data?.meta
 *
 * Error-path access such as err.response.data.message or
 * err.response.data.errors is unaffected. That envelope is constructed
 * inside useApi.js itself and never reaches this regex (which is
 * anchored on the literal response. prefix).
 *
 * Canonical reference: the one-level pattern at
 * resources/js/modules/environments/EnvironmentsPage.vue:359.
 *
 * Scans three glob roots (composable files plus all Vue pages):
 *   - resources/js/composables/*.js
 *   - resources/js/modules/ all .vue and .js under that tree
 *   - resources/js/components/ all .vue and .js under that tree
 *
 * Failure indicates a composable or Vue page is over-drilling the flat
 * envelope. Rewrite the read as response.data or response?.data.
 *
 * @see openspec/changes/fix-composables-response-unwrap-2026-08/
 */
class SddCheckJsComposablesTest extends TestCase
{
    /**
     * Pure filesystem scan. No DB connection, no Laravel container.
     * Runs cleanly under the SQLite in-memory phpunit.xml pinning.
     *
     * Mirrors the stripPatterns recipe from SddCheckMigrationsTest
     * lines 247-252: strip single quotes, double quotes, line comments,
     * and block comments before matching.
     *
     * @test
     */
    public function no_composable_double_unwraps_response(): void
    {
        $violations = [];

        $stripPatterns = [
            '/\/\/.*$/m',
            '/\/\*.*?\*\//s',
            "/'(?:\\\\.|[^'\\\\])*'/s",
            '/"(?:\\\\.|[^"\\\\])*"/s',
        ];

        // Anchored on literal response. so constructed error envelopes
        // such as { response: { data: { message: ... } } } never match.
        $forbidden = '/\bresponse\.data\??\.(?:data|meta)\b/';

        // PHP glob() does not support recursive ** or brace expansion, so we
        // glob only the top-level composables directory and then use a
        // RecursiveDirectoryIterator for the .vue/.js trees.
        $paths = array_merge(
            glob(__DIR__ . '/../../resources/js/composables/*.js') ?: [],
            self::collectJsFiles(__DIR__ . '/../../resources/js/modules'),
            self::collectJsFiles(__DIR__ . '/../../resources/js/components'),
        );

        foreach ($paths as $file) {
            $name = basename($file);
            $source = (string) file_get_contents($file);
            if ($source === '') {
                continue;
            }

            // Pre-pass: blank every line that falls inside a multi-line
            // block comment so the per-line stripPatterns below never
            // sees them. We walk the raw source tracking /* */ state
            // instead of relying on the dotall regex (which works on the
            // whole file but loses per-line number alignment).
            $rawLines = explode("\n", $source);
            $sanitized = [];
            $inBlock = false;
            foreach ($rawLines as $raw) {
                $scrubbed = self::scrubBlockComment($raw, $inBlock);
                $sanitized[] = $scrubbed[0];
                $inBlock = $scrubbed[1];
            }

            foreach ($sanitized as $i => $line) {
                $stripped = preg_replace($stripPatterns, '', $line) ?? $line;
                if (preg_match($forbidden, $stripped)) {
                    $violations[] = "{$name}:" . ($i + 1) . ' double-unwraps response';
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "No composable may drill response.data.data or response.data.meta - useApi returns a flat envelope (NEW-005). Offenders:\n"
                . implode("\n", $violations)
        );
    }

    /**
     * Blank out any portion of a line that lies inside a /* block comment *.
     *
     * Returns a two-element array: the scrubbed line content, and the
     * boolean flag for whether we are now inside a block comment after
     * processing this line. Preserves the original line length so column
     * offsets are not required, but we only need line numbers here.
     *
     * @return array{0: string, 1: bool}
     */
    private static function scrubBlockComment(string $line, bool $inBlock): array
    {
        $out = '';
        $i = 0;
        $len = strlen($line);
        while ($i < $len) {
            if ($inBlock) {
                $end = strpos($line, '*/', $i);
                if ($end === false) {
                    $i = $len;
                } else {
                    $i = $end + 2;
                    $inBlock = false;
                }
                continue;
            }
            $start = strpos($line, '/*', $i);
            if ($start === false) {
                $out .= substr($line, $i);
                break;
            }
            $out .= substr($line, $i, $start - $i);
            $end = strpos($line, '*/', $start + 2);
            if ($end === false) {
                $inBlock = true;
                $i = $len;
            } else {
                $i = $end + 2;
            }
        }
        return [$out, $inBlock];
    }

    /**
     * Recursively collect .vue and .js files under the given root.
     * PHP's glob() does not support recursive ** so we walk the tree
     * manually with RecursiveDirectoryIterator.
     *
     * @return string[]
     */
    private static function collectJsFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            )
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
}