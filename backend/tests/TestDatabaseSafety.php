<?php

namespace Tests;

use LogicException;
use Illuminate\Foundation\Application;

class TestDatabaseSafety
{
    public static function assertSafe(Application $app): void
    {
        $environment = $app->environment();

        if ($environment !== 'testing') {
            throw new LogicException(
                "Refusing to run destructive tests: application environment is '{$environment}', expected 'testing'."
            );
        }

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (preg_match('/(?:_test|_testing)$/', $database) !== 1) {
            throw new LogicException(
                "Refusing to run destructive tests against database '{$database}'. Test databases must end with _test or _testing."
            );
        }
    }
}
