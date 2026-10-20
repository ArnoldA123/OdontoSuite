<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MysqlRunner;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (method_exists($this, 'withoutVite')) {
            $this->withoutVite();
        }
    }

    /**
     * Skip database-backed tests on a runner that cannot build the schema.
     *
     * Runs before the trait hooks themselves: `RefreshDatabase` would try
     * `migrate:fresh` on SQLite and fail with a driver error, which reads as a
     * regression when it is only decision #79 (see Tests\Support\MysqlRunner).
     */
    protected function setUpTraits()
    {
        $driver = DB::getDriverName();

        if (MysqlRunner::requiresMysqlSchema(static::class) && ! MysqlRunner::isActive($driver)) {
            $this->markTestSkipped(MysqlRunner::skipMessage($driver));
        }

        return parent::setUpTraits();
    }
}
