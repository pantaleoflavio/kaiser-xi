<?php

namespace Tests;

use LogicException;

class TestDatabaseSafety
{
    /** @param array<string, mixed> $environment */
    public static function assertSafe(array $environment): void
    {
        $appEnvironment = (string) ($environment['APP_ENV'] ?? '');
        $connection = (string) ($environment['DB_CONNECTION'] ?? '');
        $database = (string) ($environment['DB_DATABASE'] ?? '');
        $url = (string) ($environment['DB_URL'] ?? '');

        $isolatedMemoryDatabase = $connection === 'sqlite' && $database === ':memory:' && $url === '';
        $dedicatedNamedDatabase = $url === ''
            && in_array($connection, ['pgsql', 'mysql', 'mariadb'], true)
            && preg_match('/(?:_test|_testing)$/', $database) === 1;

        if ($appEnvironment !== 'testing' || (! $isolatedMemoryDatabase && ! $dedicatedNamedDatabase)) {
            throw new LogicException(
                'Refusing to run destructive tests: configure an isolated in-memory database or a database named with a _test/_testing suffix.'
            );
        }
    }
}
