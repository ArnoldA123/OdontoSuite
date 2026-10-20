<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\Feature\Api\WaitingListCancellationTest;
use Tests\Feature\Modules\ProcedureCatalogSummaryVersionsTest;
use Tests\Support\MysqlRunner;
use Tests\TestCase as ProjectTestCase;

/**
 * Issue #170 — database-backed tests must report `skipped` on a runner that
 * cannot build the schema (decision #79), and the guard must stay visible: a
 * removed gate has to fail here instead of quietly returning to a wall of
 * driver errors.
 */
class MysqlRunnerGateTest extends TestCase
{
    /** @test */
    public function only_the_mysql_driver_satisfies_the_runner_gate(): void
    {
        $this->assertTrue(MysqlRunner::isActive('mysql'));

        foreach (['sqlite', 'pgsql', 'sqlsrv', ''] as $driver) {
            $this->assertFalse(
                MysqlRunner::isActive($driver),
                "The \"{$driver}\" driver must not satisfy the MySQL runner gate."
            );
        }
    }

    /** @test */
    public function every_schema_owning_test_trait_is_detected(): void
    {
        $this->assertTrue(
            MysqlRunner::requiresMysqlSchema(WaitingListCancellationTest::class),
            'RefreshDatabase users must be gated.'
        );
        $this->assertTrue(
            MysqlRunner::requiresMysqlSchema(ProcedureCatalogSummaryVersionsTest::class),
            'DatabaseTransactions users must be gated: the schema they join is MySQL-only too.'
        );
        $this->assertFalse(
            MysqlRunner::requiresMysqlSchema(self::class),
            'A test without a schema trait must keep running on any driver.'
        );
    }

    /** @test */
    public function the_project_test_case_applies_the_gate_before_the_trait_hooks(): void
    {
        $method = new ReflectionMethod(ProjectTestCase::class, 'setUpTraits');

        $this->assertSame(
            ProjectTestCase::class,
            $method->getDeclaringClass()->getName(),
            'Tests\TestCase must override setUpTraits() so the skip runs before RefreshDatabase touches SQLite.'
        );
    }

    /** @test */
    public function a_database_test_on_sqlite_is_reported_as_skipped_not_failed(): void
    {
        [$status, $output] = $this->runOnSqlite(
            'test_cancel_appointment_completes_and_persists_the_cancellation',
            'tests/Feature/Api/WaitingListCancellationTest.php'
        );

        $this->assertSame(0, $status, "A gated database test must not fail on SQLite. Output:\n{$output}");
        $this->assertStringContainsString('Skipped: 1', $output);
        $this->assertStringContainsString('migrate:fresh is MySQL-only', $output);
        $this->assertStringNotContainsString('Failures:', $output);
        $this->assertStringNotContainsString('Errors:', $output);
    }

    /**
     * Run one test file with the SQLite runner, whatever the parent run uses.
     *
     * The environment goes through Process, not a shell prefix, and PHP runs
     * the vendor proxy itself: `VAR=value cmd` and a shebang script are POSIX
     * assumptions that break on the Windows environment AGENTS.md §8 documents.
     *
     * @return array{0: int, 1: string}
     */
    private function runOnSqlite(string $filter, string $path): array
    {
        $process = new Process(
            [PHP_BINARY, 'vendor/bin/phpunit', '--configuration=phpunit.xml', '--display-skipped', '--filter', $filter, $path],
            dirname(__DIR__, 3),
            ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'],
            null,
            120
        );

        $process->run();

        // A process that never finished normally reports null; failing the
        // assertion beats letting the cast turn it into a passing 0.
        return [$process->getExitCode() ?? -1, $process->getOutput().$process->getErrorOutput()];
    }
}
