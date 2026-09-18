<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Shared ripgrep wrapper for source-grep assertions.
 *
 * Replaces the five copy-pasted `grepCount()` helpers that silently
 * returned 0 when `rg` was missing or `shell_exec` was disabled. Every
 * failure mode now fails loudly instead of producing a misleading count.
 */
final class SourceGrep
{
    private static ?bool $available = null;

    private static function ensureBinaryAvailable(): void
    {
        if (self::$available === true) {
            return;
        }

        if (! is_callable('shell_exec') || ! is_callable('exec')) {
            Assert::fail(
                'ripgrep (rg) wrapper requires shell_exec/exec, but PHP disables them here. '
                . 'Enable program execution or rewrite the assertion in pure PHP.'
            );
        }

        $output = [];
        $code = 0;
        @exec('rg --version 2>&1', $output, $code);
        $joined = implode("\n", $output);

        if ($code !== 0 || stripos($joined, 'ripgrep') === false) {
            Assert::fail(
                'ripgrep (rg) is required for source-grep assertions but was not found in PATH. '
                . 'Install ripgrep (apt-get install ripgrep / brew install ripgrep / choco install ripgrep) '
                . 'instead of asserting on a silent count=0. Got: ' . trim($joined)
            );
        }

        self::$available = true;
    }

    public static function count(string $pattern, string ...$paths): int
    {
        self::ensureBinaryAvailable();

        foreach ($paths as $path) {
            if (! file_exists($path)) {
                Assert::fail("grepCount target does not exist: {$path}");
            }
        }

        $pathsPart = implode(' ', array_map('escapeshellarg', $paths));
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            $pathsPart
        );

        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);

        if ($code >= 2) {
            Assert::fail(
                "ripgrep failed (exit {$code}) for pattern '{$pattern}'. "
                . 'Output: ' . implode("\n", $output)
            );
        }

        if ($code === 1 || $output === []) {
            return 0;
        }

        $total = 0;
        foreach ($output as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $count = (int) (str_contains($line, ':') ? substr($line, strrpos($line, ':') + 1) : $line);
            $total += $count;
        }

        return $total;
    }

    public static function lines(string $pattern, string ...$paths): array
    {
        self::ensureBinaryAvailable();

        foreach ($paths as $path) {
            if (! file_exists($path)) {
                Assert::fail("grepLines target does not exist: {$path}");
            }
        }

        $pathsPart = implode(' ', array_map('escapeshellarg', $paths));
        $cmd = sprintf(
            'rg --no-heading --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            $pathsPart
        );

        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);

        if ($code >= 2) {
            Assert::fail(
                "ripgrep failed (exit {$code}) for pattern '{$pattern}'. "
                . 'Output: ' . implode("\n", $output)
            );
        }

        if ($code === 1 || $output === []) {
            return [];
        }

        return array_values(array_filter($output, fn ($l) => trim((string) $l) !== ''));
    }
}
