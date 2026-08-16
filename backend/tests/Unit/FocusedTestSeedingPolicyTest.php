<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FocusedTestSeedingPolicyTest extends TestCase
{
    #[DataProvider('formulaOneTestFiles')]
    public function test_formula_one_tests_do_not_load_full_demo_data(string $testFile): void
    {
        $contents = file_get_contents($testFile);

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('$this->seed();', $contents);
        $this->assertStringNotContainsString('DemoEnvironmentSeeder', $contents);
        $this->assertStringNotContainsString('DemoClassicChampionshipSeeder', $contents);
        $this->assertStringNotContainsString('DemoHeadToHeadResultsSeeder', $contents);
    }

    /** @return iterable<string, array{string}> */
    public static function formulaOneTestFiles(): iterable
    {
        $tests = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__))
        );

        foreach ($tests as $test) {
            if (! $test instanceof SplFileInfo || ! $test->isFile()) {
                continue;
            }

            if (preg_match('/FormulaOne.*Test\.php$/', $test->getFilename()) !== 1) {
                continue;
            }

            yield $test->getPathname() => [$test->getPathname()];
        }
    }
}
