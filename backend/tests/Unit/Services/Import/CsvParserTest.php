<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\CsvParser;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CsvParserTest extends TestCase
{
    public function test_it_parses_bom_crlf_quotes_blank_lines_and_physical_rows(): void
    {
        $csv = "\xEF\xBB\xBFcode,name\r\nfoo,\"Foo, \"\"United\"\"\"\r\n\r\nbar,Bar\r\n";
        $result = app(CsvParser::class)->parse($csv, ['code', 'name'], ['code']);

        $this->assertSame(['code', 'name'], $result['header']);
        $this->assertSame('Foo, "United"', $result['rows'][0]['data']['name']);
        $this->assertSame([2, 4], array_column($result['rows'], 'row_number'));
    }

    public function test_it_rejects_unknown_and_duplicate_headers(): void
    {
        foreach (["code,nope\nfoo,x\n", "code,code\nfoo,x\n"] as $csv) {
            try {
                app(CsvParser::class)->parse($csv, ['code'], ['code']);
                $this->fail('Expected invalid header.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
