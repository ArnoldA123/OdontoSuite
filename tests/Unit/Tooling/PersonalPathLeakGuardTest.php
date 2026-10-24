<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Personal absolute paths are sensitive metadata in a public repository: they
 * publish the workstation user, the operating system layout and where the
 * project lives, and they age badly as soon as the project moves.
 *
 * `gitleaks` does not look for them, so the repository keeps its own guard.
 * Tracked notes and specs use the `<ruta-local>` placeholder instead, the same
 * marker the ODD notes adopted when the local database credential was redacted.
 *
 * The patterns below are written as regular expressions that cannot match their
 * own source text: after `/home/` the pattern expects a path segment and after
 * the drive letter it expects `Users`, so this file does not flag itself.
 */
class PersonalPathLeakGuardTest extends TestCase
{
    /**
     * A POSIX home directory with a user segment, and the Windows equivalent.
     */
    private const PATTERN = '(/home/[A-Za-z0-9._-]+/|[A-Za-z]:[\\\\/]Users[\\\\/][A-Za-z0-9._-]+)';

    private static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3));
    }

    #[Test]
    public function tracked_files_publish_no_personal_absolute_path(): void
    {
        $lines = [];
        $status = 1;

        exec(
            'git -C '.escapeshellarg(self::root())
            .' grep -nI -E '.escapeshellarg(self::PATTERN).' -- . 2>&1',
            $lines,
            $status
        );

        // `git grep` exits 1 when nothing matches, and that is the pass.
        $this->assertSame(
            1,
            $status,
            "A tracked file publishes a personal absolute path:\n".implode("\n", $lines)
            ."\nReplace it with the `<ruta-local>` placeholder."
        );
    }

    #[Test]
    public function the_guard_reads_the_tracked_tree(): void
    {
        $lines = [];
        $status = 1;

        exec('git -C '.escapeshellarg(self::root()).' ls-files 2>&1', $lines, $status);

        $this->assertSame(0, $status, 'The guard must run inside the repository.');
        $this->assertGreaterThan(
            100,
            count($lines),
            'The guard must see the tracked tree; an empty listing would pass vacuously.'
        );
    }
}
