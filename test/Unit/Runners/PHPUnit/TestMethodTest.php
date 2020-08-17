<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\TestBase;

/**
 * @coversNothing
 */
final class TestMethodTest extends TestBase
{
    public function testConstructor(): void
    {
        $testMethod = new TestMethod('pathToFile', ['methodName'], false);
        static::assertEquals('pathToFile', $this->getObjectValue($testMethod, 'path'));
    }
}
