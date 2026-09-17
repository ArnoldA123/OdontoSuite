<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * Contract between the MySQL runner (phpunit.mysql.xml) and the engine that
 * provisions it (docker-compose.yml), the local mirror of the CI service
 * container.
 *
 * Why this guard exists: the two files are edited independently, and each
 * failure mode below is silent. A `--` inside an XML comment makes PHPUnit
 * refuse the whole configuration, so the MySQL suite reports nothing while the
 * command looks like it ran. A port or credential changed on one side only
 * sends the runner to a different engine, or to no engine, without failing the
 * file that was actually edited. Both were hit by hand during the change that
 * introduced this test.
 */
class MySQLTestEngineContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function compose(): string
    {
        $path = $this->root().'/docker-compose.yml';
        $this->assertFileExists($path, 'docker-compose.yml provisions the MySQL test engine');

        return file_get_contents($path);
    }

    private function runnerPath(): string
    {
        return $this->root().'/phpunit.mysql.xml';
    }

    /** @test */
    public function mysql_runner_is_well_formed_xml(): void
    {
        $path = $this->runnerPath();
        $this->assertFileExists($path);

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $loaded = $document->load($path);
        $errors = array_map(
            static fn (\LibXMLError $error): string => trim($error->message),
            libxml_get_errors()
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // A stray `--` inside a comment is legal-looking text and an illegal
        // XML comment. PHPUnit then exits before running a single test.
        $this->assertTrue(
            $loaded,
            'phpunit.mysql.xml must be well-formed XML: '.implode('; ', $errors)
        );
    }

    /** @test */
    public function runner_and_compose_agree_on_the_engine(): void
    {
        $runner = file_get_contents($this->runnerPath());
        $compose = $this->compose();

        $this->assertMatchesRegularExpression(
            '/<env\s+name="DB_CONNECTION"\s+value="mysql"\s*\/>/',
            $runner,
            'The MySQL runner must select the mysql connection'
        );

        $pairs = [
            'database' => ['DB_DATABASE', 'MYSQL_DATABASE'],
            'username' => ['DB_USERNAME', 'MYSQL_USER'],
            'password' => ['DB_PASSWORD', 'MYSQL_PASSWORD'],
        ];

        foreach ($pairs as $label => [$runnerKey, $composeKey]) {
            $runnerValue = $this->xmlEnv($runner, $runnerKey);
            $composeValue = $this->composeValue($compose, $composeKey);

            $this->assertNotNull($runnerValue, "phpunit.mysql.xml must pin {$runnerKey}");
            $this->assertNotNull($composeValue, "docker-compose.yml must declare {$composeKey}");
            $this->assertSame(
                $composeValue,
                $runnerValue,
                "The MySQL {$label} differs between the runner and the engine it provisions"
            );
        }

        // The published host port defaults to something other than 3306: a
        // local MySQL/MariaDB commonly owns 3306, and a container bound to it
        // cannot start. The runner must follow the same default.
        $this->assertMatchesRegularExpression(
            '/ports:\s*\n\s*-\s*[\'"]?\$\{MYSQL_PORT:-(\d+)\}:\d+[\'"]?/',
            $compose,
            'docker-compose.yml must publish the container port through MYSQL_PORT'
        );
        preg_match('/ports:\s*\n\s*-\s*[\'"]?\$\{MYSQL_PORT:-(\d+)\}/', $compose, $matches);
        $published = $matches[1];

        $this->assertNotSame(
            '3306',
            $published,
            'The default published port must not collide with a local MySQL/MariaDB on 3306'
        );
        $this->assertSame(
            $published,
            $this->xmlEnv($runner, 'DB_PORT'),
            'The runner must target the port the compose file publishes'
        );
    }

    /** @test */
    public function compose_declares_the_health_gate_the_acceptance_criterion_uses(): void
    {
        $compose = $this->compose();

        $this->assertStringContainsString(
            'mysql:8.0',
            $compose,
            'The local engine must be the image CI provisions'
        );
        $this->assertMatchesRegularExpression(
            '/healthcheck:/',
            $compose,
            'The acceptance criterion reads `docker compose ps` for a healthy service, '
                .'which requires a healthcheck'
        );
        $this->assertMatchesRegularExpression(
            '/mysqladmin\s*[\'"]?,\s*[\'"]?ping/',
            $compose,
            'The healthcheck must ping the server, as the CI service does'
        );
    }

    private function xmlEnv(string $xml, string $key): ?string
    {
        $pattern = '/<env\s+name="'.preg_quote($key, '/').'"\s+value="([^"]*)"\s*\/>/';

        return preg_match($pattern, $xml, $matches) === 1 ? $matches[1] : null;
    }

    private function composeValue(string $compose, string $key): ?string
    {
        $pattern = '/^\s*'.preg_quote($key, '/').':\s*(\S+)\s*$/m';

        return preg_match($pattern, $compose, $matches) === 1
            ? trim($matches[1], "\"'")
            : null;
    }
}
