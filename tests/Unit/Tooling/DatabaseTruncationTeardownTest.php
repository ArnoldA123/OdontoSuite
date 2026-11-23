<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * #519 — `DatabaseTruncation` cleans on entry, not on exit.
 *
 * A class that commits rows (the slot-race tests need a second connection and
 * cannot be wrapped in a transaction) leaves them behind for the rest of the
 * suite, which runs under `RefreshDatabase` and expects an empty database. The
 * next red was nineteen unrelated failures; both classes were fixed by hand,
 * but nothing stopped the next one from repeating it.
 *
 * This guard is static on purpose: the failure has to be named before the
 * suite runs, not diagnosed from a residue.
 */
class DatabaseTruncationTeardownTest extends TestCase
{
    public function test_every_class_using_database_truncation_sweeps_on_teardown(): void
    {
        $root = dirname(__DIR__, 3);
        $failures = [];
        $classes = 0;

        foreach ($this->phpFiles($root.'/tests') as $path) {
            $source = file_get_contents($path);

            if (! preg_match('/^\s*use\s+DatabaseTruncation\s*;/m', $source, $traitUse, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $classes++;
            $relative = str_replace($root.'/', '', $path);
            $line = substr_count(substr($source, 0, $traitUse[0][1]), "\n") + 1;

            if (! preg_match(
                '/function\s+tearDown\s*\([^)]*\)\s*(?::\s*void)?\s*\{(.*?)\n\s{4}\}/s',
                $source,
                $tearDown
            )) {
                $failures[] = "{$relative}:{$line} uses DatabaseTruncation without a tearDown() that calls truncateDatabaseTables().";

                continue;
            }

            if (! str_contains($tearDown[1], 'truncateDatabaseTables()')) {
                $failures[] = "{$relative}:{$line} uses DatabaseTruncation but its tearDown() does not call truncateDatabaseTables().";
            }
        }

        $this->assertGreaterThan(
            0,
            $classes,
            'The guard found no DatabaseTruncation class; the scan is broken, not the suite.'
        );
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
