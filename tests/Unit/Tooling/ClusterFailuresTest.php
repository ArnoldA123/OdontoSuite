<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * The A2 failure sweep is a command, not a recipe.
 *
 * scripts/audit/cluster-failures.mjs groups the raw MySQL-runner output by
 * root-cause signature (odd/tasks/audit-axis-a2-mysql-failures.md §Method,
 * issue #44). These tests drive the script through its --json mode over a
 * synthetic runner output, so the grouping rules are exercised rather than
 * read: one ANSI escape proves the stripping, two Field 'X' columns prove
 * they stay separate signatures, two duplicate keys prove the index — not
 * the value — is the signature, and the ordering proves most-failures-first
 * with ties in first-seen order.
 */
class ClusterFailuresTest extends TestCase
{
    /** @test */
    public function it_groups_a_synthetic_mysql_output_by_root_cause_signature(): void
    {
        $report = $this->cluster($this->syntheticOutput());

        $this->assertSame(8, $report['failures']);
        $this->assertCount(6, $report['signatures']);

        $signatures = array_column($report['signatures'], 'signature');

        $this->assertSame(
            [
                "SQLSTATE[HY000] 1364 Field 'first_name' doesn't have a default value",
                'Expected #/# but got #',
                "SQLSTATE[23000] 1062 Duplicate entry for key 'users_username_unique'",
                'Call to a member function info() on null',
                'Too few arguments to function App\\Services\\AppointmentService::__construct()',
                "SQLSTATE[HY000] 1364 Field 'city' doesn't have a default value",
            ],
            $signatures,
            'Most failures first, ties in first-seen order, digits collapsed, Field columns kept apart'
        );

        $counts = array_column($report['signatures'], 'count');
        $this->assertSame([2, 2, 1, 1, 1, 1], $counts);

        $duplicates = $report['signatures'][2];
        $this->assertSame('Tests\\Feature\\Modules\\RoundTripTest > post then get r…', $duplicates['example']);
        $this->assertCount(1, $duplicates['tests']);
    }

    /** @test */
    public function it_takes_the_test_location_from_the_first_tests_frame(): void
    {
        $report = $this->cluster($this->syntheticOutput());

        $audit = $report['signatures'][1];
        $this->assertSame(
            'tests/Feature/Api/AuditLogControllerTest.php:50',
            $audit['tests'][0]['location']
        );
    }

    /** @test */
    public function it_falls_back_to_the_header_class_when_the_trace_has_no_tests_frame(): void
    {
        $report = $this->cluster($this->syntheticOutput());

        $duplicates = $report['signatures'][2];
        $this->assertSame(
            'tests/Feature/Modules/RoundTripTest.php',
            $duplicates['tests'][0]['location']
        );
    }

    /** @test */
    public function a_green_run_reports_zero_failures_and_zero_signatures(): void
    {
        $report = $this->cluster("   PASS  Tests\\Unit\\ExampleTest\n  ✓ it works\n\n  Tests:    1 passed\n");

        $this->assertSame(0, $report['failures']);
        $this->assertSame([], $report['signatures']);
    }

    /**
     * @return array{failures: int, signatures: list<array{signature: string, count: int, example: string, tests: list<array{test: string, location: string}>}>}
     */
    private function cluster(string $raw): array
    {
        $node = $this->node();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH, and the classifier under test is a node script');
        }

        $path = sys_get_temp_dir().'/cluster-failures-'.uniqid('', true).'.txt';
        file_put_contents($path, $raw);

        try {
            $script = str_replace('\\', '/', dirname(__DIR__, 3).'/scripts/audit/cluster-failures.mjs');
            $lines = [];
            $status = 1;
            exec(
                escapeshellarg($node).' '.escapeshellarg($script).' '.escapeshellarg($path).' --json 2>&1',
                $lines,
                $status
            );

            $this->assertSame(0, $status, 'cluster-failures.mjs must exit 0: '.implode(' / ', $lines));

            $report = json_decode(implode("\n", $lines), true);
            $this->assertIsArray($report, 'cluster-failures.mjs --json must print JSON');

            return $report;
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function node(): ?string
    {
        $candidates = ['node', 'node.exe'];
        foreach ($candidates as $candidate) {
            $lines = [];
            $status = 1;
            exec(escapeshellarg($candidate).' --version 2>&1', $lines, $status);
            if ($status === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function syntheticOutput(): string
    {
        // One ANSI escape rides before the first header: the sweep must strip
        // it before splitting, or the first block never becomes a failure.
        return "\e[32m".<<<'RAW'
   FAILED  Tests\Unit\Models\AppointmentTest > it can create an appointment                            QueryException
  SQLSTATE[HY000]: General error: 1364 Field 'first_name' doesn't have a default value (Connection: mysql, SQL: insert into `patients` (`is_active`) values (1))

  at vendor\laravel\framework\src\Illuminate\Database\Connection.php:824

   FAILED  Tests\Feature\Api\AuditLogControllerTest > post audit logs returns 405 not 500
  Expected 405/404 but got 500
Failed asserting that an array contains 500.

  at tests\Feature\Api\AuditLogControllerTest.php:50

   FAILED  Tests\Feature\Modules\RoundTripTest > post then get r…  UniqueConstraintViolationException
  SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'implantologo_test' for key 'users_username_unique' (Connection: mysql, SQL: insert into `users` (`username`) values (implantologo_test))

  at vendor\laravel\framework\src\Illuminate\Database\Connection.php:819

   FAILED  Tests\Feature\Api\SeederTest > seeder creates records across all five concrete mode…  Error
  Call to a member function info() on null

  at database\seeders\SpecialtyRecordSeeder.php:43

   FAILED  Tests\Unit\Services\AppointmentServiceTest > creates appointment successfully           ArgumentCountError
  Too few arguments to function App\Services\AppointmentService::__construct(), 0 passed in E:\repo\tests\Unit\Services\AppointmentServiceTest.php on line 25 and exactly 1 expected

  1   app\Services\AppointmentService.php:25
  2   tests\Unit\Services\AppointmentServiceTest.php:25

   FAILED  Tests\Unit\Models\AppointmentTest > it belongs to a patient                            QueryException
  SQLSTATE[HY000]: General error: 1364 Field 'first_name' doesn't have a default value (Connection: mysql, SQL: insert into `patients` (`is_active`) values (1))

  at vendor\laravel\framework\src\Illuminate\Database\Connection.php:824

   FAILED  Tests\Feature\Api\AuditLogControllerTest > put audit logs returns 405 not 500
  Expected 405/404 but got 500
Failed asserting that an array contains 500.

  at tests\Feature\Api\AuditLogControllerTest.php:61

   FAILED  Tests\Feature\Api\CashTest > administrador can post cash movement         QueryException
  SQLSTATE[HY000]: General error: 1364 Field 'city' doesn't have a default value (Connection: mysql, SQL: insert into `branches` (`name`) values (Centro))

  at vendor\laravel\framework\src\Illuminate\Database\Connection.php:824

  Tests:    8 failed
RAW;
    }
}
