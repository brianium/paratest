<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Tests\TestBase;
use ReflectionMethod;

/**
 * @internal
 *
 * @covers \ParaTest\Parser\ParsedClass
 */
final class ParsedClassTest extends TestBase
{
    private ParsedClass $class;
    /** @var ReflectionMethod[]  */
    private array $methods;

    public function setUpTest(): void
    {
        $this->methods = [
            new ReflectionMethod(self::class, 'testGetters'),
        ];
        $this->class   = new ParsedClass(self::class, $this->methods, 4);
    }

    public function testGetters(): void
    {
        static::assertSame(self::class, $this->class->getName());
        static::assertSame($this->methods, $this->class->getMethods());
        static::assertSame(4, $this->class->getParentsCount());
    }
}
