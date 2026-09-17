<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * The data-loss guard in scripts/audit/baseline.sh.
 *
 * Plan #12 §6 forbids the audit from running `migrate:fresh` against the
 * application database, and the MySQL suite does exactly that to
 * `odontosuite_test`. The guard therefore has to answer one question: is the
 * database the suite will wipe also the database the application lives in?
 *
 * A native review found the first version of that guard failing open
 * (`R1-data-loss-guard-fail-open`): it grepped for `^DB_DATABASE=` and cut on
 * `=`, so a quoted value, a CRLF line ending, spaces around the separator or an
 * absent key all slipped past it, and `DB_DATABASE="odontosuite_test"` named a
 * database the suite would then destroy. An independent verification reached the
 * same conclusion by code inspection.
 *
 * These tests drive the decision through the script's own diagnostic mode, so
 * the guard is exercised rather than read. Every variant below is one the
 * framework accepts: phpdotenv tolerates the quoting and the spacing, which is
 * why the guard has to parse the file the way the framework does.
 */
class AuditBaselineGuardTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider envFileVariants
     */
    public function database_guard_decides_the_way_the_framework_reads_the_file(
        string $contents,
        string $expectedDecision
    ): void {
        $decision = $this->guardDecision($contents);

        $this->assertSame(
            $expectedDecision,
            $decision['fallback_path'] ?? '(no decision reported)',
            'The guard must refuse whenever the application database cannot be proven different '
                .'from the database the suite wipes'
        );
    }

    /** @test */
    public function database_guard_refuses_when_there_is_no_env_file_to_read(): void
    {
        $decision = $this->guardDecision(null);

        $this->assertSame(
            'refuse:cannot-verify-application-database',
            $decision['fallback_path'] ?? '(no decision reported)',
            'Without an env file the application database cannot be proven different, and the '
                .'suite runs migrate:fresh: the guard fails closed'
        );
    }

    /** @test */
    public function guard_proceeds_only_for_a_database_the_suite_will_not_wipe(): void
    {
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n");

        $this->assertSame('odontosuite', $decision['app_database'] ?? '(absent)');
        $this->assertSame('proceed', $decision['fallback_path'] ?? '(no decision reported)');
        $this->assertSame(
            'odontosuite_test',
            $decision['test_database'] ?? '(absent)',
            'The decision is only meaningful next to the database the suite targets'
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function envFileVariants(): array
    {
        $refuse = 'refuse:application-database-is-test-database';

        return [
            'plain value, different database' => ["DB_DATABASE=odontosuite\n", 'proceed'],
            'plain value, same database' => ["DB_DATABASE=odontosuite_test\n", $refuse],
            'double quoted, same database' => ["DB_DATABASE=\"odontosuite_test\"\n", $refuse],
            'single quoted, same database' => ["DB_DATABASE='odontosuite_test'\n", $refuse],
            'CRLF line ending, same database' => ["DB_DATABASE=odontosuite_test\r\n", $refuse],
            'spaces around the separator, same database' => ["DB_DATABASE = odontosuite_test\n", $refuse],
            'leading spaces, same database' => ["   DB_DATABASE=odontosuite_test\n", $refuse],
            'trailing spaces, same database' => ["DB_DATABASE=odontosuite_test   \n", $refuse],
            'double quoted, different database' => ["DB_DATABASE=\"odontosuite\"\n", 'proceed'],
            'key absent' => ["APP_NAME=OdontoSuite\n", 'refuse:cannot-verify-application-database'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function guardDecision(?string $envContents): array
    {
        $path = sys_get_temp_dir().'/baseline-guard-'.uniqid('', true).'.env';
        if ($envContents !== null) {
            file_put_contents($path, $envContents);
        }

        try {
            return $this->runScript(['--explain-database-guard', $path]);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  list<string>          $arguments
     * @return array<string, string>
     */
    private function runScript(array $arguments): array
    {
        $script = str_replace('\\', '/', dirname(__DIR__, 3).'/scripts/audit/baseline.sh');
        $command = 'bash '.escapeshellarg($script);
        foreach ($arguments as $argument) {
            $command .= ' '.escapeshellarg($argument);
        }

        $lines = [];
        $status = 1;
        exec($command.' 2>&1', $lines, $status);

        $this->assertSame(
            0,
            $status,
            'baseline.sh --explain-database-guard must answer without running anything: '
                .implode(' / ', $lines)
        );

        $parsed = [];
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }
}
