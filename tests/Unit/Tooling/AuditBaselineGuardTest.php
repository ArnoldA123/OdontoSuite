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
 * the file can still miss the database the application actually uses. The
 * provider's refuter then corroborated a third (`R1-data-loss-guard-inline-comment`):
 * phpdotenv drops an inline comment, so `DB_DATABASE=odontosuite_test # test`
 * read as the whole line, compared unequal to the test database name, and the
 * guard answered proceed for the exact wipe it exists to prevent. A later review
 * corroborated a fourth (`R3-001`): the engine compares database identifiers
 * without case on Windows, so a comparison that is case-sensitive misses the same
 * database spelled differently. A fifth (`R1-001`) closed the source class that
 * remained: a connection URL beats the explicit keys inside the framework, so a
 * guard reading only `DB_DATABASE` answered proceed while the resolved connection
 * pointed at the test database. `DB_URL` is a source now, and the suite run
 * empties it so the pinned database governs.
 *
 * These tests drive the decision through the script's own diagnostic mode, so
 * the guard is exercised rather than read. Every env-file variant below is one
 * the framework accepts: phpdotenv tolerates the quoting and the spacing, which
 * is why the guard has to parse the file the way the framework does. The child
 * process gets `env -u DB_DATABASE` unless a test says otherwise, because
 * PHPUnit exports its own `DB_DATABASE` from phpunit.xml and an inherited value
 * would decide these cases instead of the file under test.
 *
 * The framework rule (issue #41) is exercised through `BASELINE_FRAMEWORK_PROBE`,
 * the script's seam for the probe command: the tests prove the decision follows
 * the framework's answer — including when it contradicts every static source,
 * and when the framework cannot answer at all — without booting it per case.
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

    /** @test */
    public function database_guard_refuses_when_an_exported_value_differs_only_in_case(): void
    {
        // The provider's refuter corroborated this as critical and
        // candidate-caused (R3-001): MySQL and MariaDB on Windows compare
        // database identifiers case-insensitively, measured as
        // lower_case_table_names=1 on the engine this project develops against,
        // so a differently capitalised export names the same database the suite
        // wipes while a case-sensitive comparison sees two different names.
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n", 'ODONTOSUITE_TEST');

        $this->assertSame(
            'refuse:application-database-is-test-database',
            $decision['decision'] ?? '(no decision reported)',
            'A difference of case is not a difference to the engine'
        );
    }

    /** @test */
    public function database_guard_refuses_when_a_connection_url_names_the_test_database(): void
    {
        // The source the review found missing: `config/database.php` maps
        // `url` => env('DB_URL') for every connection, and the URL wins over the
        // explicit keys. Measured on this project before the fix: with DB_URL
        // selecting odontosuite_test and DB_DATABASE naming odontosuite,
        // `DB::connection('mysql')->getConfig('database')` was odontosuite_test.
        $decision = $this->guardDecision(
            "DB_DATABASE=odontosuite\nDB_URL=mysql://root@127.0.0.1:3306/odontosuite_test\n"
        );

        $this->assertSame('odontosuite_test', $decision['app_database_url_env_file'] ?? '(absent)');
        $this->assertSame(
            'refuse:application-database-is-test-database',
            $decision['decision'] ?? '(no decision reported)',
            'A URL is a connection-defining source: it must be read, not only DB_DATABASE'
        );
    }

    /** @test */
    public function database_guard_refuses_when_an_exported_url_names_the_test_database(): void
    {
        $decision = $this->guardDecision(
            "DB_DATABASE=odontosuite\n",
            null,
            'mysql://root@127.0.0.1:3306/odontosuite_test'
        );

        $this->assertSame('odontosuite_test', $decision['app_database_url_exported'] ?? '(absent)');
        $this->assertSame(
            'refuse:application-database-is-test-database',
            $decision['decision'] ?? '(no decision reported)',
            'An exported URL is inherited by the suite, exactly like an exported DB_DATABASE'
        );
    }

    /** @test */
    public function database_guard_refuses_when_a_url_carries_no_readable_database(): void
    {
        // The remedy the review asked for: refuse when the URL source is present
        // and cannot be read, rather than assume it decides nothing.
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\nDB_URL=mysql://root@127.0.0.1:3306\n");

        $this->assertSame('(unset)', $decision['app_database_url_env_file'] ?? '(absent)');
        $this->assertSame(
            'refuse:unreadable-connection-url',
            $decision['decision'] ?? '(no decision reported)',
            'A URL present but unreadable cannot be proven harmless'
        );
    }

    /**
     * @test
     */
    public function database_guard_follows_the_framework_resolution_not_the_static_sources(): void
    {
        // Issue #41. The env file names a database the suite will not wipe and no
        // static source names the test database, so every string comparison in
        // the script answers proceed. The framework resolves the test database,
        // and the guard must refuse: with the framework rule removed this test
        // answers proceed, which is what makes it a falsification.
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n", null, null, 'echo odontosuite_test');

        $this->assertSame('odontosuite_test', $decision['app_database_framework'] ?? '(absent)');
        $this->assertSame(
            'refuse:application-database-is-test-database',
            $decision['decision'] ?? '(no decision reported)',
            'The decision must come from what the framework resolves, not from comparing the env file strings'
        );
    }

    /**
     * @test
     */
    public function database_guard_proceeds_only_when_the_framework_resolves_another_database(): void
    {
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n", null, null, 'echo odontosuite');

        $this->assertSame('odontosuite', $decision['app_database_framework'] ?? '(absent)');
        $this->assertSame('proceed', $decision['decision'] ?? '(no decision reported)');
    }

    /**
     * @test
     */
    public function database_guard_refuses_when_the_framework_cannot_answer(): void
    {
        // A guard that cannot see the resolution has proven nothing, and the
        // suite runs migrate:fresh: it fails closed instead of assuming.
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n", null, null, 'exit 1');

        $this->assertSame('unavailable', $decision['app_database_framework'] ?? '(absent)');
        $this->assertSame(
            'refuse:framework-resolution-unavailable',
            $decision['decision'] ?? '(no decision reported)',
            'An unanswered framework resolution is not a licence to run the suite'
        );
    }

    /**
     * @test
     */
    public function database_guard_hands_the_probe_the_run_environment(): void
    {
        // The probe reads the connection-defining keys back, so this pins the
        // wiring (what the run would see) rather than the answer: an exported
        // value wins over the file, exactly as it does inside the framework.
        $decision = $this->guardDecision("DB_DATABASE=odontosuite\n", 'odontosuite_test', null, 'echo "$DB_DATABASE"');

        $this->assertSame('odontosuite_test', $decision['app_database_framework'] ?? '(absent)');
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
            // The refuter's finding: phpdotenv drops an inline comment, and a
            // guard that kept it compared unequal to the test database name and
            // answered proceed. Every form below is one the framework accepts.
            'inline comment after a space, same database' => ["DB_DATABASE=odontosuite_test # test base\n", $refuse],
            'inline comment with no space, same database' => ["DB_DATABASE=odontosuite_test#test\n", $refuse],
            'inline comment after a tab, same database' => ["DB_DATABASE=odontosuite_test\t# test base\n", $refuse],
            'double quoted then a comment, same database' => ["DB_DATABASE=\"odontosuite_test\" # test base\n", $refuse],
            'single quoted then a comment, same database' => ["DB_DATABASE='odontosuite_test' # test base\n", $refuse],
            'two hashes, same database' => ["DB_DATABASE=odontosuite_test##two\n", $refuse],
            'inline comment, different database' => ["DB_DATABASE=odontosuite # dev\n", 'proceed'],
            // The engine compares identifiers without case on Windows, so these
            // three name the same schema as the test database or a different one.
            'uppercase, same database' => ["DB_DATABASE=ODONTOSUITE_TEST\n", $refuse],
            'mixed case, same database' => ["DB_DATABASE=Odontosuite_Test\n", $refuse],
            'uppercase, different database' => ["DB_DATABASE=ODONTOSUITE\n", 'proceed'],
            // Controls. Inside quotes the hash is literal, so this name is not
            // the test database and proceeding is the right answer; a value that
            // is only a comment reads as empty, which is stricter than the
            // framework and refuses rather than risks.
            'quoted hash is literal, different database' => ["DB_DATABASE=\"odontosuite_test # inside\"\n", 'proceed'],
            'value is only a comment' => ["DB_DATABASE= # nothing\n", 'refuse:cannot-verify-application-database'],
            // The other connection-defining key in the stock config, and the one
            // that wins over DB_DATABASE inside the framework.
            'url names the test database' => ["DB_DATABASE=odontosuite\nDB_URL=mysql://root@127.0.0.1:3306/odontosuite_test\n", $refuse],
            'url with a query names the test database' => ["DB_URL=mysql://root@127.0.0.1:3306/odontosuite_test?charset=utf8mb4\n", $refuse],
            'url names another database' => ["DB_DATABASE=odontosuite\nDB_URL=mysql://root@127.0.0.1:3306/odontosuite\n", 'proceed'],
            'empty url is treated as absent' => ["DB_DATABASE=odontosuite\nDB_URL=\n", 'proceed'],
            'key absent' => ["APP_NAME=OdontoSuite\n", 'refuse:cannot-verify-application-database'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function guardDecision(
        ?string $envContents,
        ?string $exportedDatabase = null,
        ?string $exportedUrl = null,
        ?string $frameworkProbe = null
    ): array {
        $path = sys_get_temp_dir().'/baseline-guard-'.uniqid('', true).'.env';
        if ($envContents !== null) {
            file_put_contents($path, $envContents);
        }

        try {
            return $this->runScript(['--explain-database-guard', $path], $exportedDatabase, $exportedUrl, $frameworkProbe);
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
    private function runScript(
        array $arguments,
        ?string $exportedDatabase = null,
        ?string $exportedUrl = null,
        ?string $frameworkProbe = null
    ): array {
        $script = str_replace('\\', '/', dirname(__DIR__, 3).'/scripts/audit/baseline.sh');

        // Both connection-defining keys are cleared, and only the ones under test
        // are set: PHPUnit exports its own DB_DATABASE from phpunit.xml, and an
        // inherited value would decide these cases instead of the file.
        $command = 'env -u DB_DATABASE -u DB_URL ';
        if ($exportedDatabase !== null) {
            $command .= 'DB_DATABASE='.escapeshellarg($exportedDatabase).' ';
        }
        if ($exportedUrl !== null) {
            $command .= 'DB_URL='.escapeshellarg($exportedUrl).' ';
        }
        if ($frameworkProbe !== null) {
            $command .= 'BASELINE_FRAMEWORK_PROBE='.escapeshellarg($frameworkProbe).' ';
        }
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
