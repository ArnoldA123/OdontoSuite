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
 * ## Why the pattern does not flag its own source
 *
 * The patterns below cannot match the text that contains them. After `/home/`
 * the POSIX branch requires a path segment of `[A-Za-z0-9._-]`, and this file
 * writes `/home/<usuario>/`; `<` is not in that class, so the match dies at the
 * first character. The Windows branch requires `Users` followed by `/` or `\`
 * and then a segment, and the source writes `C:/Users/<usuario>/`, which dies in
 * the same place.
 *
 * What would break that, and is therefore forbidden here: writing a plausible
 * example such as `/home/` plus a real user name, or relaxing either segment
 * class to accept `<`, `>` or a quote. The positive control below composes its
 * seeded path at runtime for exactly this reason — a literal one would be the
 * leak the guard is looking for.
 *
 * ## Exit codes
 *
 * `git grep` answers 1 when nothing matched, 0 when something did, and 2 or more
 * when it could not run (128 outside a repository, for instance). Only 1 is a
 * pass: an unreadable tree means the guard proved nothing, and reporting it as a
 * leak would teach the reader to ignore the message.
 */
class PersonalPathLeakGuardTest extends TestCase
{
    private const PLACEHOLDER = '<ruta-local>';

    /**
     * A POSIX home directory with a user segment, and the Windows equivalent.
     */
    private const PATTERN = '(/home/[A-Za-z0-9._-]+/|[A-Za-z]:[\\\\/]Users[\\\\/][A-Za-z0-9._-]+)';

    private const THIS_FILE = 'tests/Unit/Tooling/PersonalPathLeakGuardTest.php';

    private static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3));
    }

    /**
     * Runs one git command and keeps both streams apart.
     *
     * The command goes in as an argument vector, so no shell re-splits the
     * pattern or the paths, and stderr never becomes part of the report: a
     * warning on it used to read as a leaked path (#359, R4-2).
     *
     * @param  array<int, string>                                                         $arguments
     * @return array{status: int, stdout: array<int, string>, stderr: array<int, string>}
     */
    private static function git(array $arguments, ?string $cwd = null): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            array_merge(['git', '-C', $cwd ?? self::root()], $arguments),
            $descriptors,
            $pipes
        );

        if (! is_resource($process)) {
            return ['status' => -1, 'stdout' => [], 'stderr' => ['proc_open refused to start git']];
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        return [
            'status' => $status,
            'stdout' => array_values(array_filter(explode("\n", $stdout), fn (string $line): bool => $line !== '')),
            'stderr' => array_values(array_filter(explode("\n", $stderr), fn (string $line): bool => $line !== '')),
        ];
    }

    /**
     * @return array{status: int, stdout: array<int, string>, stderr: array<int, string>}
     */
    private static function scan(string $cwd): array
    {
        return self::git(['grep', '-nI', '-E', self::PATTERN, '--', '.'], $cwd);
    }

    /**
     * The guard's verdict as the message a reader would see, or null when the
     * command answered "no match" (exit 1).
     *
     * @param array{status: int, stdout: array<int, string>, stderr: array<int, string>} $result
     */
    private static function failure(array $result): ?string
    {
        if ($result['status'] === 1) {
            return null;
        }

        if ($result['status'] === 0) {
            return "A tracked file publishes a personal absolute path:\n"
                .implode("\n", $result['stdout'])
                ."\nReplace it with the `".self::PLACEHOLDER.'` placeholder.';
        }

        return 'The guard could not run: `git grep` exited '
            .$result['status'].' instead of 1 (no match) or 0 (matches found), so the tree was never read.'
            .($result['stderr'] === [] ? '' : "\n".implode("\n", $result['stderr']));
    }

    #[Test]
    public function tracked_files_publish_no_personal_absolute_path(): void
    {
        $result = self::scan(self::root());

        $this->assertNull(
            self::failure($result),
            (string) self::failure($result)
        );
    }

    #[Test]
    public function the_guard_reads_the_tracked_tree(): void
    {
        $result = self::git(['ls-files']);

        $this->assertSame(
            0,
            $result['status'],
            'The guard must run inside the repository: '.implode("\n", $result['stderr'])
        );

        $this->assertContains(
            self::THIS_FILE,
            $result['stdout'],
            'The guard must see its own file, or the self-exclusion contract above is untested.'
        );

        $this->assertGreaterThan(
            100,
            count($result['stdout']),
            'The guard must see the tracked tree; an empty listing would pass vacuously.'
        );
    }

    /**
     * The positive control. Without it the pass above could be a pattern that
     * matches nothing, which is indistinguishable from a clean tree.
     */
    #[Test]
    public function the_guard_detects_a_seeded_path(): void
    {
        $name = 'leak-probe-'.uniqid('', true).'.md';
        $relative = 'tests/Unit/Tooling/'.$name;
        $probe = self::root().'/'.$relative;
        // Composed at runtime: written as a literal, this file would be the leak.
        $seeded = '/'.'home'.'/'.'probeuser'.'/informe.md';

        file_put_contents($probe, "# Probe\n\n".$seeded."\n");
        $staged = false;

        try {
            $add = self::git(['add', '--intent-to-add', '--', $relative]);
            $this->assertSame(
                0,
                $add['status'],
                'The control needs the probe in the index: '.implode("\n", $add['stderr'])
            );
            $staged = true;

            $failure = self::failure(self::scan(self::root()));

            $this->assertNotNull($failure, 'The guard no longer detects a seeded personal path.');
            $this->assertStringContainsString($relative, $failure);
            $this->assertStringContainsString(self::PLACEHOLDER, $failure);
        } finally {
            if ($staged) {
                self::git(['reset', '-q', '--', $relative]);
            }
            if (file_exists($probe)) {
                unlink($probe);
            }
        }

        $this->assertFileDoesNotExist($probe, 'The control must leave no file behind.');
        $this->assertSame(
            [],
            self::git(['ls-files', '--', $relative])['stdout'],
            'The control must leave no index entry behind.'
        );
    }

    #[Test]
    public function a_tree_the_guard_cannot_read_is_not_reported_as_a_leak(): void
    {
        $directory = sys_get_temp_dir().'/personal-path-guard-'.uniqid('', true);
        mkdir($directory);

        try {
            $result = self::scan($directory);
            $failure = self::failure($result);

            $this->assertGreaterThan(1, $result['status'], 'A directory outside a repository must not read as clean.');
            $this->assertNotNull($failure);
            $this->assertStringContainsString('could not run', $failure);
            $this->assertStringNotContainsString('publishes a personal absolute path', $failure);
        } finally {
            rmdir($directory);
        }
    }
}
