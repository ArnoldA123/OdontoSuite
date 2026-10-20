<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Runner gate for database-backed tests.
 *
 * Decision #79 (AGENTS.md §6): `migrate:fresh` is MySQL-only, so every test
 * that owns or joins the schema fails on SQLite before asserting anything.
 * Those tests must be reported as skipped, never as failures, so a local
 * `php artisan test` run keeps showing only real regressions (issue #170).
 */
final class MysqlRunner
{
    /**
     * Traits that require a migrated schema on the active connection.
     *
     * @var array<int, class-string>
     */
    private const SCHEMA_TRAITS = [
        RefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
        DatabaseTransactions::class,
    ];

    /**
     * Whether the given — or currently configured — driver can build the schema.
     */
    public static function isActive(?string $driver = null): bool
    {
        return ($driver ?? DB::getDriverName()) === 'mysql';
    }

    /**
     * Whether the test class owns or joins the database schema.
     */
    public static function requiresMysqlSchema(string $testClass): bool
    {
        $traits = array_flip(class_uses_recursive($testClass));

        foreach (self::SCHEMA_TRAITS as $trait) {
            if (isset($traits[$trait])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Skip reason reported by PHPUnit, naming the decision behind the gate.
     */
    public static function skipMessage(string $driver): string
    {
        return sprintf(
            'Database-backed test: migrate:fresh is MySQL-only (issue #79, AGENTS.md §6). '
            .'Active driver is "%s"; run it with php artisan test --configuration=phpunit.mysql.xml.',
            $driver
        );
    }
}
