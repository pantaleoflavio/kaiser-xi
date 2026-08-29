<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseRuntimeTest extends TestCase
{
    public function test_runtime_uses_the_dedicated_postgresql_test_database(): void
    {
        $this->assertSame('testing', $this->app->environment());
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertSame('fantasy_football_testing', config('database.connections.pgsql.database'));
        $this->assertSame(
            'fantasy_football_testing',
            DB::connection()->selectOne('select current_database() as database')->database,
        );
    }
}
