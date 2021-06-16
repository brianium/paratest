<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DataProviderParameterTest extends TestCase
{
    /**
     * @dataProvider dataProviderWithParameter
     */
    public function testWithDataProvider(bool $expected): void
    {
        $this->assertTrue($expected);
    }

    /**
     * @return array<int, array<string, bool>>
     */
    public function dataProviderWithParameter(): array
    {
        return [
            ['expected' => true],
        ];
    }
}
