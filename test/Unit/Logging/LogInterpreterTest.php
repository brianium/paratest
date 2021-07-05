<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Tests\Unit\ResultTester;

use function array_pop;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\LogInterpreter
 */
final class LogInterpreterTest extends ResultTester
{
    private LogInterpreter $interpreter;

    protected function setUpInterpreter(): void
    {
        $this->interpreter = new LogInterpreter();
        $this->interpreter->addReader(new Reader($this->mixedSuite->getTempFile()));
        $this->interpreter->addReader(new Reader($this->passingSuite->getTempFile()));
    }

    public function testConstructor(): void
    {
        $interpreter = new LogInterpreter();
        static::assertSame([], $this->getObjectValue($interpreter, 'readers'));
    }

    public function testAddReaderIncrementsReaders(): void
    {
        static::assertCount(2, $this->getObjectValue($this->interpreter, 'readers'));
        $this->interpreter->addReader(new Reader($this->failureSuite->getTempFile()));
        static::assertCount(3, $this->getObjectValue($this->interpreter, 'readers'));
    }

    public function testGetReaders(): void
    {
        $reader = new Reader($this->failureSuite->getTempFile());
        $this->interpreter->addReader($reader);
        $readers = $this->interpreter->getReaders();
        static::assertCount(3, $readers);
        $last = array_pop($readers);
        static::assertSame($reader, $last);
    }

    public function testGetTotals(): void
    {
        static::assertSame(22, $this->interpreter->getTotalTests());
        static::assertSame(13, $this->interpreter->getTotalAssertions());
        static::assertSame(3, $this->interpreter->getTotalErrors());
        static::assertSame(3, $this->interpreter->getTotalFailures());
        static::assertSame(2, $this->interpreter->getTotalWarnings());
        static::assertSame(4, $this->interpreter->getTotalSkipped());
        static::assertSame(0.006784, $this->interpreter->getTotalTime());
    }

    public function testIsSuccessfulReturnsFalseIfFailuresPresentAndNoErrors(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->failureSuite->getTempFile()));
        static::assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsPresentAndNoFailures(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->errorSuite->getTempFile()));
        static::assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsAndFailuresPresent(): void
    {
        static::assertFalse($this->interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueIfNoErrorsOrFailures(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->passingSuite->getTempFile()));
        static::assertTrue($interpreter->isSuccessful());
    }

    public function testGetErrorsReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n/home/brian/Projects/parallel-phpunit/" .
            'test/fixtures/failing_tests/UnitTestWithErrorTest.php:17',
        ];
        static::assertSame($errors, $this->interpreter->getErrors());
    }

    public function testGetWarningsReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            "UnitTestWithErrorTest::testWarning\nFunction 1 deprecated",
            "UnitTestWithMethodAnnotationsTest::testWarning\nFunction 2 deprecated",
        ];
        static::assertSame($errors, $this->interpreter->getWarnings());
    }

    public function testGetFailuresReturnsArrayOfFailureMessages(): void
    {
        $failures = [
            "Fixtures\\Tests\\UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n/" .
                'home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            "UnitTestWithErrorTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:20',
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:20',
        ];

        static::assertSame($failures, $this->interpreter->getFailures());
    }

    public function testGetRiskyReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            'Risky Test',
            'Risky Test',
        ];
        static::assertSame($errors, $this->interpreter->getRisky());
    }

    public function testGetSkippedReturnsArrayOfTestNames(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->skipped->getTempFile()));
        $skipped = [
            "UnitTestWithMethodAnnotationsTest::testIncomplete\n\n" .
            '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:51',
        ];
        static::assertSame($skipped, $interpreter->getSkipped());
    }

    public function testGetCasesReturnsAllCases(): void
    {
        $cases = $this->interpreter->getCases();
        static::assertCount(22, $cases);
    }

    public function testGetCasesExtendEmptyCasesFromSuites(): void
    {
        $interpreter        = new LogInterpreter();
        $dataProviderReader = new Reader($this->dataProviderSuite->getTempFile());
        $interpreter->addReader($dataProviderReader);
        $cases = $interpreter->getCases();
        static::assertCount(10, $cases);
        foreach ($cases as $name => $case) {
            if ($case->name === 'testNumericDataProvider5 with data set #3') {
                static::assertSame($case->class, 'DataProviderTest1');
            } elseif ($case->name === 'testNamedDataProvider5 with data set #3') {
                static::assertSame($case->class, 'DataProviderTest2');
            } else {
                static::assertSame($case->class, 'DataProviderTest');
            }

            if ($case->name === 'testNumericDataProvider5 with data set #4') {
                static::assertSame(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider_tests/DataProviderTest1.php'
                );
            } elseif ($case->name === 'testNamedDataProvider5 with data set #4') {
                static::assertSame(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider_tests/DataProviderTest2.php'
                );
            } else {
                static::assertSame(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider_tests/DataProviderTest.php'
                );
            }
        }
    }

    /**
     * @return TestSuite[]
     */
    public function testFlattenCasesReturnsCorrectNumberOfSuites(): array
    {
        $suites = $this->interpreter->flattenCases();
        static::assertCount(4, $suites);

        return $suites;
    }

    /**
     * @param TestSuite[] $suites
     *
     * @depends testFlattenCasesReturnsCorrectNumberOfSuites
     */
    public function testFlattenedSuiteHasCorrectTotals(array $suites): void
    {
        $first = $suites[0];
        static::assertSame('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $first->name);
        static::assertSame(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertSame(4, $first->tests);
        static::assertSame(4, $first->assertions);
        static::assertSame(1, $first->failures);
        static::assertSame(0, $first->warnings);
        static::assertSame(0, $first->skipped);
        static::assertSame(0, $first->errors);
        static::assertSame(0.000357, $first->time);
    }
}
