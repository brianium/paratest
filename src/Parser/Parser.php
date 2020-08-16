<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

use function array_diff;
use function array_values;
use function assert;
use function file_exists;
use function get_declared_classes;
use function preg_match;
use function realpath;
use function str_replace;
use function strpos;

final class Parser
{
    /**
     * The path to the source file to parse.
     *
     * @var string
     */
    private $path;

    /** @var ReflectionClass<TestCase> */
    private $refl;

    /**
     * Matches a test method beginning with the conventional "test"
     * word.
     *
     * @var string
     */
    private static $testName = '/^test/';

    /**
     * A pattern for matching test methods that use the @test annotation.
     *
     * @var string
     */
    private static $testAnnotation = '/@test\b/';

    public function __construct(string $srcPath)
    {
        if (! file_exists($srcPath)) {
            throw new InvalidArgumentException('file not found: ' . $srcPath);
        }

        $srcPath = realpath($srcPath);
        assert($srcPath !== false);

        $this->path      = $srcPath;
        $declaredClasses = get_declared_classes();
        require_once $this->path;
        $class = $this->getClassName($this->path, $declaredClasses);
        if ($class === null) {
            throw new NoClassInFileException();
        }

        try {
            $this->refl = new ReflectionClass($class);
        } catch (ReflectionException $reflectionException) {
            throw new InvalidArgumentException(
                'Unable to instantiate ReflectionClass. ' . $class . ' not found in: ' . $srcPath,
                0,
                $reflectionException
            );
        }
    }

    /**
     * Returns the fully constructed class
     * with methods or null if the class is abstract.
     */
    public function getClass(): ?ParsedClass
    {
        return $this->refl->isAbstract()
            ? null
            : new ParsedClass(
                (string) $this->refl->getDocComment(),
                $this->getCleanReflectionName(),
                $this->refl->getNamespaceName(),
                $this->getMethods()
            );
    }

    /**
     * Return reflection name with null bytes stripped.
     */
    private function getCleanReflectionName(): string
    {
        return str_replace("\x00", '', $this->refl->getName());
    }

    /**
     * Return all test methods present in the file.
     *
     * @return ParsedFunction[]
     */
    private function getMethods(): array
    {
        $tests   = [];
        $methods = $this->refl->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $hasTestName       = preg_match(self::$testName, $method->getName()) > 0;
            $docComment        = $method->getDocComment();
            $hasTestAnnotation = $docComment !== false && preg_match(self::$testAnnotation, $docComment) > 0;
            $isTestMethod      = $hasTestName || $hasTestAnnotation;
            if (! $isTestMethod) {
                continue;
            }

            $tests[] = new ParsedFunction((string) $method->getDocComment(), $method->getName());
        }

        return $tests;
    }

    /**
     * Return the class name of the class contained
     * in the file.
     *
     * @param class-string[] $previousDeclaredClasses
     *
     * @return class-string<TestCase>|null
     */
    private function getClassName(string $filename, array $previousDeclaredClasses): ?string
    {
        $classes    = get_declared_classes();
        $newClasses = array_values(array_diff($classes, $previousDeclaredClasses));

        $className = $this->searchForUnitTestClass($newClasses, $filename);
        if (isset($className)) {
            return $className;
        }

        $className = $this->searchForUnitTestClass($classes, $filename);
        if (isset($className)) {
            return $className;
        }

        return null;
    }

    /**
     * Search for the name of the unit test.
     *
     * @param class-string[] $classes
     *
     * @return class-string<TestCase>|null
     */
    private function searchForUnitTestClass(array $classes, string $filename): ?string
    {
        // TODO: After merging this PR or other PR for phpunit 6 support, keep only the applicable subclass name
        $matchingClassName = null;
        foreach ($classes as $className) {
            $class = new ReflectionClass($className);
            if ($class->getFileName() !== $filename) {
                continue;
            }

            if (! $class->isSubclassOf(TestCase::class)) {
                continue;
            }

            if ($this->classNameMatchesFileName($filename, $className)) {
                /** @var class-string<TestCase> $foundClassName  */
                $foundClassName = $className;

                return $foundClassName;
            }

            if ($matchingClassName !== null) {
                continue;
            }

            /** @var class-string<TestCase> $matchingClassName */
            $matchingClassName = $className;
        }

        return $matchingClassName;
    }

    private function classNameMatchesFileName(string $filename, string $className): bool
    {
        return strpos($filename, $className) !== false
            || strpos($filename, $this->invertSlashes($className)) !== false;
    }

    private function invertSlashes(string $className): string
    {
        return str_replace('\\', '/', $className);
    }
}
