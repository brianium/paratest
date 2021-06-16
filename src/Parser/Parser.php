<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Exception;
use PHPUnit\Runner\TestSuiteLoader;
use PHPUnit\Util\Test as TestUtil;
use ReflectionClass;
use ReflectionMethod;

use function assert;
use function is_file;
use function realpath;

/**
 * @internal
 */
final class Parser
{
    /** @var ReflectionClass<TestCase> */
    private $refl;

    /** @var ReflectionClass<TestCase>[] */
    private static $alreadyLoadedSources = [];

    public function __construct(string $srcPath)
    {
        if (! is_file($srcPath)) {
            throw new InvalidArgumentException('file not found: ' . $srcPath);
        }

        $srcPath = realpath($srcPath);
        assert($srcPath !== false);

        if (! isset(self::$alreadyLoadedSources[$srcPath])) {
            try {
                self::$alreadyLoadedSources[$srcPath] = (new TestSuiteLoader())->load($srcPath);
            } catch (Exception $exception) {
                throw new NoClassInFileException('', 0, $exception);
            }
        }

        $this->refl = self::$alreadyLoadedSources[$srcPath];
    }

    /**
     * Returns the fully constructed class
     * with methods or null if the class is abstract.
     */
    public function getClass(): ParsedClass
    {
        $parentsCount = 0;
        $class        = $this->refl;
        while (($parent = $class->getParentClass()) !== false) {
            ++$parentsCount;
            $class = $parent;
        }

        return new ParsedClass(
            $this->refl->getName(),
            $this->getMethods(),
            $parentsCount
        );
    }

    /**
     * Return all test methods present in the file.
     *
     * @return ReflectionMethod[]
     */
    private function getMethods(): array
    {
        $methods = [];
        // @see \PHPUnit\Framework\TestSuite::__construct
        foreach ($this->refl->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === Assert::class) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() === TestCase::class) {
                continue;
            }

            if (! TestUtil::isTestMethod($method)) {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }
}
