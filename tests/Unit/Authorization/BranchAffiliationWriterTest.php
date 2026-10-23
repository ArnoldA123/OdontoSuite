<?php

namespace Tests\Unit\Authorization;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * #305 (S3) — «una sola pluma» is a claim about the codebase, so it is pinned
 * like one.
 *
 * The affiliation (`users.branch_id`, ADR-0010) has exactly two writers: the
 * administrative edit in `UserController` and the approval of a transfer
 * request in `BranchTransferService`. Both leave `user_branch_assigned` in
 * `audit_logs`; no other file assigns the column or writes that action. A new
 * writer would move a user between sedes without the decision or without the
 * trail, and this guard fails with the file and line.
 *
 * Static by design: it reads `app/` and needs neither the framework nor the
 * database.
 */
class BranchAffiliationWriterTest extends TestCase
{
    private const ADMINISTRATIVE_EDIT = 'app/Http/Controllers/Api/UserController.php';

    private const TRANSFER_APPROVAL = 'app/Services/BranchTransferService.php';

    /**
     * @return list<string> repository-relative paths, sorted
     */
    private static function phpFilesUnderApp(): array
    {
        $root = dirname(__DIR__, 3).'/app';
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = substr($file->getPathname(), strlen(dirname(__DIR__, 3)) + 1);
        }

        sort($files);

        return $files;
    }

    /**
     * @param  callable(string): bool $matchesLine
     * @return list<string>
     */
    private static function filesMatching(callable $matchesLine): array
    {
        $root = dirname(__DIR__, 3);
        $hits = [];

        foreach (self::phpFilesUnderApp() as $relative) {
            foreach (file($root.'/'.$relative, FILE_IGNORE_NEW_LINES) as $line) {
                if ($matchesLine($line)) {
                    $hits[] = $relative;

                    break;
                }
            }
        }

        return array_values(array_unique($hits));
    }

    #[Test]
    public function only_the_transfer_approval_assigns_the_affiliation_column(): void
    {
        // A single `=` assignment on the column; `===`, `==` and `=>` are not
        // writes and stay out of the guard.
        $assignments = self::filesMatching(
            static fn (string $line): bool => (bool) preg_match('/\$[A-Za-z_][A-Za-z0-9_]*->branch_id\s*=\s*[^=]/', $line),
        );

        $this->assertSame(
            [self::TRANSFER_APPROVAL],
            $assignments,
            'Assigning users.branch_id outside the approved transfer breaks S3 of #305.',
        );
    }

    #[Test]
    public function only_the_administrative_edit_and_the_approval_audit_the_change(): void
    {
        $writers = self::filesMatching(
            static fn (string $line): bool => str_contains($line, 'user_branch_assigned'),
        );

        $this->assertSame(
            [self::ADMINISTRATIVE_EDIT, self::TRANSFER_APPROVAL],
            $writers,
            'A branch reassignment without user_branch_assigned loses the trail (S3 of #305).',
        );
    }
}
