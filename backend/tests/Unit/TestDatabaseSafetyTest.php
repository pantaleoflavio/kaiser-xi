<?php

namespace Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TestDatabaseSafety;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_isolated_test_databases_are_allowed(): void
    {
        TestDatabaseSafety::assertSafe([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ]);

        TestDatabaseSafety::assertSafe([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'kaiser_xi_test',
            'DB_URL' => '',
        ]);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_database_configuration_is_rejected(array $configuration): void
    {
        $this->expectException(LogicException::class);

        TestDatabaseSafety::assertSafe($configuration);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'development environment' => [[
                'APP_ENV' => 'local',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'kaiser_xi_test',
                'DB_URL' => '',
            ]],
            'development database' => [[
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'kaiser_xi',
                'DB_URL' => '',
            ]],
            'production URL override' => [[
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'kaiser_xi_test',
                'DB_URL' => 'postgres://production/kaiser_xi',
            ]],
        ];
    }
}
