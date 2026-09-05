<?php

namespace Tests\Unit;

use LogicException;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseSafety;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_dedicated_test_database_is_allowed(): void
    {
        TestDatabaseSafety::assertSafe($this->application('testing', 'fantasy_football_testing'));

        $this->addToAssertionCount(1);
    }

    public function test_development_database_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("database 'fantasy_football'");
        TestDatabaseSafety::assertSafe($this->application('testing', 'fantasy_football'));
    }

    public function test_non_testing_application_environment_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("environment is 'local'");

        TestDatabaseSafety::assertSafe($this->application('local', 'fantasy_football_testing'));
    }

    private function application(string $environment, string $database): Application
    {
        $app = new Application;
        $app->instance('env', $environment);
        $app->instance('config', new Repository([
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => ['database' => $database],
                ],
            ],
        ]));

        return $app;
    }
}
