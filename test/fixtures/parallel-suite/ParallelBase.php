<?php

declare(strict_types=1);

namespace ParallelSuite;

use PHPUnit\Framework\TestCase;

use ReflectionClass;
use function sizeof;

abstract class ParallelBase extends TestCase
{
    final public function testToken(): void
    {
        $refClass = new ReflectionClass(static::class);
        $file = TMP_DIR . DS . 'token_' . str_replace(['\\', '/'], '_', $refClass->getNamespaceName());

        $token = getenv('TEST_TOKEN');
        static::assertIsString($token);

        if (! is_file($file)) {
            file_put_contents($file, $token);
        }

        static::assertStringEqualsFile($file, $token);
    }
}
