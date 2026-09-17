<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * The data-loss guard in scripts/audit/baseline.sh.
 *
 * Plan #12 §6 forbids the audit from running `migrate:fresh` against the
 * application database, and the MySQL suite does exactly that to
 * `odontosuite_test`. The guard therefore answers one question: is the database
 * the suite will wipe also the database the application lives in?
 *
 * A native review found the first version of that guard failing open
 * (`R1-data-loss-guard-fail-open`): it grepped for `^DB_DATABASE=` and cut on
 * `=`, so a quoted value, a CRLF line ending, spaces around the separator or an
 * absent key all slipped past it, and `DB_DATABASE="odontosuite_test"` named a
 * database the suite would then destroy. An independent verification reached the
 * same conclusion by code inspection, and added a second: an exported
 * `DB_DATABASE` beats the file inside the framework, so a guard that reads only
 * the file can still miss the database the application actually uses.
 *
 * These tests drive the decision through the script's own diagnostic mode, so
 * the guard is exercised rather than read. Every env-file variant below is one
 * the framework accepts: phpdotenv tolerates the quoting and the spacing, which
 * is why the guard has to parse the file the way the framework does. The child
 * process gets `env -u DB_DATABASE` unless a test says otherwise, because
 * PHPUnit exports its own `DB_DATABASE` from phpunit.xml and an inherited value
 * would decide these cases instead of the file under test.
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
            $decision['decision'] ?? '(no decision reported)',
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
            $decision['decision'] ?? '(no decision reported)',
            'Without a name from either source the application database cannot be proven different, '
                .'and the suite runs migrate:fresh: the guard fails closed'
        );
    }

    /** @test */
    public function guard_proceeds_only_for_a_database_the_suite_will_not_wipe(): void
    {
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n");

        $this->assertSame('odontosuite', $decision['app_database'] ?? '(absent)');
        $this->assertSame('proceed', $decision['decision'] ?? '(no decision reported)');
        $this->assertSame(
            'odontosuite_test',
            $decision['test_database'] ?? '(absent)',
            'The decision is only meaningful next to the database the suite targets'
        );
    }

    /** @test */
    public function database_guard_refuses_when_an_exported_value_names_the_test_database(): void
    {
        // The residual fail-open the second verification found: the file says the
        // application lives elsewhere, the environment says otherwise, and the
        // environment is what the framework obeys.
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n", 'odontosuite_test');

        $this->assertSame('odontosuite', $decision['app_database_env_file'] ?? '(absent)');
        $this->assertSame('odontosuite_test', $decision['app_database_exported'] ?? '(absent)');
        $this->assertSame(
            'refuse:application-database-is-test-database',
            $decision['decision'] ?? '(no decision reported)',
            'An exported DB_DATABASE wins over the file inside the framework, so naming the test '
                .'database there must refuse even when the file does not'
        );
    }

    /** @test */
    public function database_guard_refuses_when_the_sources_disagree_and_either_names_the_test_database(): void
    {
        $decision = $this->guardDecision("DB_DATABASE=odontosuite_test\n", 'odontosuite');

        $this->assertSame('odontosuite_test', $decision['app_database_env_file'] ?? '(absent)');
        $this->assertSame('odontosuite', $decision['app_database_exported'] ?? '(absent)');
        $this->assertSame(
            'refuse:application-database-is-test-database',
            $decision['decision'] ?? '(no decision reported)',
            'Data may sit in either database when the two sources disagree, so either naming the '
                .'test database refuses'
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
    private function guardDecision(?string $envContents, ?string $exportedDatabase = null): array
    {
        $path = sys_get_temp_dir().'/baseline-guard-'.uniqid('', true).'.env';
        if ($envContents !== null) {
            file_put_contents($path, $envContents);
        }

        try {
            return $this->runScript(['--explain-database-guard', $path], $exportedDatabase);
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
    private function runScript(array $arguments, ?string $exportedDatabase = null): array
    {
        $script = str_replace('\\', '/', dirname(__DIR__, 3).'/scripts/audit/baseline.sh');

        // `env -u` when no export is under test: PHPUnit exports its own
        // DB_DATABASE, and an inherited value would decide these cases.
        $command = $exportedDatabase === null
            ? 'env -u DB_DATABASE '
            : 'env DB_DATABASE='.escapeshellarg($exportedDatabase).' ';
        $command .= 'bash '.escapeshellarg($script);
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
