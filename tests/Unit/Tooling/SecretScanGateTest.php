<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The secret-scanning gate is only real while it is wired: a job that is
 * skipped, made advisory or pointed at the working tree instead of the
 * published commits protects nothing, and a scanner without the project rules
 * misses the two shapes this repository actually leaked (a database password
 * written as a value and the XAMPP `root/<password>` shorthand).
 *
 * These tests pin the wiring, not the scanner. The scan itself is proven by
 * the job turning red on a fixture and green on a clean range in the pull
 * request that added the gate.
 */
class SecretScanGateTest extends TestCase
{
    private static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3));
    }

    #[Test]
    public function ci_defines_a_blocking_secret_scan_job(): void
    {
        $workflowPath = self::root().'/.github/workflows/ci.yml';
        $this->assertFileExists($workflowPath);

        $workflow = Yaml::parseFile($workflowPath);
        $job = $workflow['jobs']['security'] ?? null;

        $this->assertIsArray($job, 'CI MUST define a `security` job: the config alone scans nothing.');
        $this->assertSame('Secret scan', $job['name'] ?? null);
        $this->assertNotTrue(
            $job['continue-on-error'] ?? false,
            'The secret scan MUST be blocking; an advisory scan never fails a merge.'
        );

        $source = (string) file_get_contents($workflowPath);
        $this->assertMatchesRegularExpression(
            "/GITLEAKS_VERSION:\\s*'?\\d+\\.\\d+\\.\\d+'?/",
            $source,
            'The gitleaks version MUST be pinned so the gate cannot change under us.'
        );
        $this->assertStringContainsString(
            'sha256sum -c -',
            $source,
            'The downloaded scanner MUST be verified against the published checksum.'
        );
        $this->assertStringContainsString('gitleaks git', $source);
        $this->assertStringContainsString('--config .gitleaks.toml', $source);
        $this->assertStringContainsString(
            '--log-opts',
            $source,
            'The gate MUST scan a commit range, not the working tree.'
        );
        $this->assertStringNotContainsString(
            '--log-opts=--all',
            $source,
            'A full-history scan would red on the leak SECURITY.md records.'
        );

        $checkout = null;
        foreach ($job['steps'] ?? [] as $step) {
            if (($step['uses'] ?? '') === 'actions/checkout@v4') {
                $checkout = $step;

                break;
            }
        }
        $this->assertIsArray($checkout, 'The job MUST check out the repository.');
        $this->assertSame(
            0,
            (int) ($checkout['with']['fetch-depth'] ?? 1),
            'The range scan needs the commit history: fetch-depth 0.'
        );
    }

    #[Test]
    public function the_config_keeps_the_project_rules_and_the_safe_placeholders(): void
    {
        $configPath = self::root().'/.gitleaks.toml';
        $this->assertFileExists($configPath);
        $config = (string) file_get_contents($configPath);

        $this->assertStringContainsString(
            'useDefault = true',
            $config,
            'The gate MUST keep the default rule set.'
        );
        $this->assertStringContainsString('id = "project-db-password"', $config);
        $this->assertStringContainsString('id = "project-xampp-root-password"', $config);
        $this->assertStringContainsString('secretGroup = 1', $config);
        $this->assertStringContainsString('[allowlist]', $config);

        foreach (['secret', 'root', 'password123', 'testing-app-', 'local-key'] as $placeholder) {
            $this->assertStringContainsString(
                $placeholder,
                $config,
                sprintf(
                    'The documented placeholder `%s` MUST stay allowlisted, or the gate reds on safe files.',
                    $placeholder
                )
            );
        }
    }

    #[Test]
    public function the_gate_runs_on_pull_requests(): void
    {
        $source = (string) file_get_contents(self::root().'/.github/workflows/ci.yml');

        $this->assertMatchesRegularExpression(
            '/^\s{2}pull_request:/m',
            $source,
            'The gate MUST run on pull requests, which is where it can block a merge.'
        );
    }
}
