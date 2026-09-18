<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * The `--check` guard in scripts/audit/baseline.sh (issue #40).
 *
 * `AuditBaselineGuardTest` covers `--explain-database-guard`; everything here
 * covers the other half the reviews flagged: a `--check` that could pass
 * without measuring. Each case drives the script itself: `--mask-secrets` and
 * `--read-counters` are the diagnostic modes, and `BASELINE_CHECK_STUB` points
 * `--check` at a ready-made counters.txt so the diff logic is exercised without
 * running every gate (the same seam idea as `BASELINE_ENV_FILE` for the guard).
 *
 * Fixed by this candidate:
 * - `mask_secrets()` masked to the first space, so a password with a space
 *   leaked its tail (`DB_PASSWORD=*** bar`). The secret now runs to the next
 *   `KEY=` assignment or to the end of the line.
 * - `read_fenced_counters()` took the first ```json block, so a document with
 *   an unrelated block on top was compared as a foreign object. The counters
 *   block (the one holding `counters`) wins now; a single block without the
 *   wrapper still reads, and a document with no usable block exits 3.
 * - An empty counter means a gate never measured it, so an empty side never
 *   matches: two runs that both failed to measure now mismatch instead of
 *   passing on two empty strings.
 *
 * Accepted without a code change, with the reason in the test name:
 * - Tree-state counters (`changed_paths`, `untracked_paths`) stay ignored by
 *   `--check`: they describe the working tree at capture time, so comparing
 *   them would fail every later run for a reason that is not a claim about
 *   the project.
 * - `mysql_engine` / `mysql_status` stay strings outside `NUMERIC_KEYS`: they
 *   are engine labels (`MySQL 8.0 (...)`, `refused-...`), never numbers.
 * - `--check` on a missing file already exits 2; covered here, not changed.
 */
class AuditBaselineCheckTest extends TestCase
{
    /** @test */
    public function check_refuses_a_missing_file_with_exit_2(): void
    {
        $missing = sys_get_temp_dir().'/baseline-missing-'.uniqid('', true).'.md';

        $result = $this->runBaseline(['--check', $missing]);

        $this->assertSame(2, $result['status']);
        $this->assertStringContainsString('no such file', implode("\n", $result['lines']));
    }

    /** @test */
    public function check_ignores_tree_state_counters(): void
    {
        // Accepted: these describe the tree at capture time, so a later run
        // can never match them and comparing them would make the guard fail
        // for a reason that is not a claim about the project.
        $target = $this->claimedDocument(['models' => 7, 'changed_paths' => 3, 'untracked_paths' => 1]);
        $stub = $this->countersStub([
            'models' => '7',
            'changed_paths' => '99',
            'untracked_paths' => '99',
        ]);

        try {
            $result = $this->runBaseline(['--check', $target], ['BASELINE_CHECK_STUB' => $stub]);

            $this->assertSame(0, $result['status'], implode(' / ', $result['lines']));
            $this->assertStringContainsString('OK', implode("\n", $result['lines']));
        } finally {
            $this->cleanup($target, $stub);
        }
    }

    /** @test */
    public function check_reports_a_counter_that_differs(): void
    {
        $target = $this->claimedDocument(['models' => 7]);
        $stub = $this->countersStub(['models' => '8']);

        try {
            $result = $this->runBaseline(['--check', $target], ['BASELINE_CHECK_STUB' => $stub]);

            $this->assertSame(1, $result['status'], implode(' / ', $result['lines']));
            $this->assertStringContainsString('MISMATCH', implode("\n", $result['lines']));
        } finally {
            $this->cleanup($target, $stub);
        }
    }

    /** @test */
    public function check_fails_closed_when_both_sides_are_unmeasured(): void
    {
        // A gate that fails leaves its record empty. Two runs that both
        // failed to measure proved nothing, so empty never matches empty.
        $target = $this->claimedDocument(['eslint_errors' => '', 'models' => 7]);
        $stub = $this->countersStub(['eslint_errors' => '', 'models' => '7']);

        try {
            $result = $this->runBaseline(['--check', $target], ['BASELINE_CHECK_STUB' => $stub]);
            $output = implode("\n", $result['lines']);

            $this->assertSame(1, $result['status'], $output);
            $this->assertStringContainsString('eslint_errors', $output);
            $this->assertStringContainsString('(unmeasured)', $output);
        } finally {
            $this->cleanup($target, $stub);
        }
    }

    /** @test */
    public function check_fails_when_only_the_new_measurement_is_unmeasured(): void
    {
        $target = $this->claimedDocument(['eslint_errors' => 5]);
        $stub = $this->countersStub(['eslint_errors' => '']);

        try {
            $result = $this->runBaseline(['--check', $target], ['BASELINE_CHECK_STUB' => $stub]);

            $this->assertSame(1, $result['status'], implode(' / ', $result['lines']));
        } finally {
            $this->cleanup($target, $stub);
        }
    }

    /** @test */
    public function read_counters_prefers_the_counters_block_over_an_unrelated_one(): void
    {
        $this->requireNode();

        $path = sys_get_temp_dir().'/baseline-decoy-'.uniqid('', true).'.md';
        file_put_contents(
            $path,
            "# Doc\n\n```json\n{\"other\": 1}\n```\n\ntext\n\n```json\n"
                .'{"counters": {"models": 7}, "commands": {"models": "ls"}}'."\n```\n"
        );

        try {
            $result = $this->runBaseline(['--read-counters', $path]);

            $this->assertSame(0, $result['status'], implode(' / ', $result['lines']));
            $parsed = json_decode(implode("\n", $result['lines']), true);
            $this->assertSame(7, $parsed['counters']['models'] ?? null);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /** @test */
    public function read_counters_still_reads_a_single_bare_block(): void
    {
        $this->requireNode();

        $path = sys_get_temp_dir().'/baseline-bare-'.uniqid('', true).'.md';
        file_put_contents($path, "# Doc\n\n```json\n{\"models\": 7}\n```\n");

        try {
            $result = $this->runBaseline(['--read-counters', $path]);

            $this->assertSame(0, $result['status'], implode(' / ', $result['lines']));
            $this->assertSame(['models' => 7], json_decode(implode("\n", $result['lines']), true));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /** @test */
    public function read_counters_refuses_a_document_without_a_usable_block(): void
    {
        $path = sys_get_temp_dir().'/baseline-noblock-'.uniqid('', true).'.md';
        file_put_contents($path, "# Empty\n\nno blocks here\n");

        try {
            $result = $this->runBaseline(['--read-counters', $path]);

            $this->assertSame(3, $result['status'], implode(' / ', $result['lines']));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @test
     *
     * @dataProvider maskSecretsCases
     */
    public function mask_secrets_masks_the_whole_password(string $input, string $expected): void
    {
        $path = sys_get_temp_dir().'/baseline-mask-'.uniqid('', true).'.txt';
        file_put_contents($path, $input."\n");

        try {
            $script = $this->script();
            $lines = [];
            $status = 1;
            exec('bash '.escapeshellarg($script).' --mask-secrets < '.escapeshellarg($path).' 2>&1', $lines, $status);

            $this->assertSame(0, $status, implode(' / ', $lines));
            $this->assertSame($expected, implode("\n", $lines));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function maskSecretsCases(): array
    {
        return [
            'simple value' => [
                'env DB_PASSWORD=secret123 DB_DATABASE=t',
                'env DB_PASSWORD=*** DB_DATABASE=t',
            ],
            // The finding: masking to the first space printed `bar`.
            'password with a space' => [
                'env DB_HOST=127.0.0.1 DB_PASSWORD=foo bar DB_URL= DB_DATABASE=t php artisan test',
                'env DB_HOST=127.0.0.1 DB_PASSWORD=*** DB_URL= DB_DATABASE=t php artisan test',
            ],
            'empty password' => [
                'env DB_PASSWORD= DB_URL=x',
                'env DB_PASSWORD=*** DB_URL=x',
            ],
            'equals inside the password' => [
                'env DB_PASSWORD=p@ss=w0rd DB_URL=x',
                'env DB_PASSWORD=*** DB_URL=x',
            ],
            'password at end of line' => [
                'env DB_URL=x DB_PASSWORD=trailing secret',
                'env DB_URL=x DB_PASSWORD=***',
            ],
            'line without a password is untouched' => [
                'env DB_URL=x DB_DATABASE=t php artisan test',
                'env DB_URL=x DB_DATABASE=t php artisan test',
            ],
        ];
    }

    /**
     * @param  array<string, int|string>  $counters
     */
    private function claimedDocument(array $counters): string
    {
        $path = sys_get_temp_dir().'/baseline-claimed-'.uniqid('', true).'.md';
        file_put_contents(
            $path,
            '# Claimed'."\n\n```json\n".json_encode(['counters' => $counters, 'commands' => new \stdClass])."\n```\n"
        );

        return $path;
    }

    /**
     * @param  array<string, string>  $rows  key => value ('' means unmeasured)
     */
    private function countersStub(array $rows): string
    {
        $path = sys_get_temp_dir().'/baseline-stub-'.uniqid('', true).'.txt';
        $lines = [];
        foreach ($rows as $key => $value) {
            $lines[] = $key."\t".$value."\tstub-command";
        }
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }

    /**
     * @param  list<string>            $arguments
     * @param  array<string, string>   $env
     * @return array{status: int, lines: list<string>}
     */
    private function runBaseline(array $arguments, array $env = []): array
    {
        $this->requireNode();

        $command = 'env ';
        foreach ($env as $key => $value) {
            $command .= $key.'='.escapeshellarg($value).' ';
        }
        $command .= 'bash '.escapeshellarg($this->script());
        foreach ($arguments as $argument) {
            $command .= ' '.escapeshellarg($argument);
        }

        $lines = [];
        $status = 1;
        exec($command.' 2>&1', $lines, $status);

        return ['status' => $status, 'lines' => $lines];
    }

    private function script(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3).'/scripts/audit/baseline.sh');
    }

    private function cleanup(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        // --check leaves its stub-measured directory behind; it is gitignored,
        // but the test still removes what it created. The split is on '/' only
        // to mirror `basename | tr` in the script (PHP's basename also splits
        // on '\', which names a different directory on Windows).
        foreach ($paths as $path) {
            if (str_contains($path, 'baseline-claimed-')) {
                $base = preg_replace('#^.*/#', '', $path);
                // The script pipes basename into tr, so basename's trailing
                // newline becomes a trailing '_' in the directory name.
                $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '_', $base).'_';
                // The raw parent carries a timestamp this test cannot know, so
                // locate it through the check directory it holds.
                $audit = dirname(__DIR__, 3).'/.atl/qa-evidence/audit';
                foreach (glob($audit.'/raw-'.date('Y-m-d').'-*/check-'.$sanitized, GLOB_ONLYDIR) ?: [] as $dir) {
                    array_map('unlink', glob($dir.'/*') ?: []);
                    rmdir($dir);
                    // Remove the timestamped parent when this test's directory
                    // was its only content.
                    if (count(glob(dirname($dir).'/*') ?: []) === 0) {
                        rmdir(dirname($dir));
                    }
                }
            }
        }
    }

    private function requireNode(): void
    {
        $lines = [];
        $status = 1;
        exec('node --version 2>&1', $lines, $status);
        if ($status !== 0) {
            $this->markTestSkipped('node is not on PATH, and baseline.sh --check is a node script');
        }
    }
}
