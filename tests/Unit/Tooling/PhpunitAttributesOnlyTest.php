<?php

namespace Tests\Unit\Tooling;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the PHPUnit metadata migration behind issue #237.
 *
 * PHPUnit 11 deprecates metadata declared in doc-comments and PHPUnit 12 stops
 * reading it, so `@test` methods vanish from the suite and doc-comment data
 * providers stop resolving. The failure is silent in one more case: a method
 * that carries any attribute makes PHPUnit 11 ignore the doc-comment provider
 * entirely, and its data set cases disappear from the run without an error.
 *
 * The suite therefore declares every piece of metadata with an attribute. This
 * guard fails when a doc-comment annotation comes back, and points at the file
 * and line that reintroduced it.
 */
class PhpunitAttributesOnlyTest extends TestCase
{
    /**
     * Doc-comment metadata PHPUnit 11 still parses and deprecates, plus the
     * annotations PHPUnit 12 removes outright.
     */
    private const DOC_METADATA_PATTERN = '/^[ \t]*(?:\/\*\*|\*)[ \t]*@(test|testWith|testWithJson|dataProvider|dataProviderExternal|depends|dependsExternal|dependsOnMethod|dependsOnClass|group|covers|coversDefaultClass|coversFunction|coversMethod|coversClass|coversNothing|uses|requires|backupGlobals|runInSeparateProcess|preserveGlobalState|small|medium|large|ticket|internal|testdox|expectedException|expectedExceptionMessage|expectedExceptionCode)\b/';

    #[Test]
    public function phpunit_metadata_is_declared_with_attributes_only(): void
    {
        $offenders = [];

        foreach (self::suite_file_paths() as $path) {
            foreach (file($path) ?: [] as $number => $line) {
                if (preg_match(self::DOC_METADATA_PATTERN, $line) !== 1) {
                    continue;
                }

                $offenders[] = sprintf(
                    '%s:%d %s',
                    self::relativePath($path),
                    $number + 1,
                    trim($line)
                );
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "PHPUnit 12 ignores metadata declared in doc-comments, and PHPUnit 11 already drops a doc-comment data provider on any method that carries an attribute, so the affected cases disappear without a failure.\n"
            ."Declare the metadata with attributes instead (#237):\n"
            .implode("\n", $offenders)
        );
    }

    /**
     * @return list<string>
     */
    private static function suite_file_paths(): array
    {
        $root = dirname(__DIR__, 3).'/tests';
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(dirname(__DIR__, 3)) + 1));
    }
}
