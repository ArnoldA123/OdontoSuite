<?php

namespace Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * Issue #79 — `migrate:fresh` is MySQL-only by decision. These tests pin the
 * declaration: the SQLite limitation is observed (not merely described), and
 * AGENTS.md §6 documents it with the supported test path.
 */
class SqliteMigrateFreshDeclarationTest extends TestCase
{
    /** @test */
    public function sqlite_migrate_fresh_still_fails_on_the_indexed_column_drop(): void
    {
        // Issue #79: el esquema no se construye en SQLite. Este test fija la
        // limitación observada: si algún día pasa, la declaración de §6 debe
        // retirarse en el mismo cambio (el test lo exige fallando).
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite not available in this environment');
        }
        $db = sys_get_temp_dir().'/osa-sqlite-79-'.uniqid('', true).'.sqlite';
        touch($db);
        $root = str_replace('\\', '/', dirname(__DIR__, 3));
        $command = 'cd '.escapeshellarg($root).' && DB_CONNECTION=sqlite DB_DATABASE='.escapeshellarg($db).' php artisan migrate:fresh --force 2>&1';
        $lines = [];
        $status = 0;
        exec($command, $lines, $status);
        if (file_exists($db)) { unlink($db); }
        $output = implode("\n", $lines);
        $this->assertNotSame(0, $status, 'migrate:fresh on SQLite must fail (known limitation, issue #79)');
        $this->assertStringContainsString('idx_transactions_patient_type_status', $output);
        $this->assertStringContainsString('no such column: type', $output);
    }

    /** @test */
    public function agents_md_declares_sqlite_migrate_fresh_mysql_only(): void
    {
        $docs = (string) file_get_contents(str_replace('\\', '/', dirname(__DIR__, 3)).'/AGENTS.md');
        $this->assertStringContainsString('solo-MySQL', $docs);
        $this->assertStringContainsString('phpunit.mysql.xml', $docs);
        $this->assertStringContainsString('SqliteMigrateFreshDeclarationTest', $docs);
    }
}
