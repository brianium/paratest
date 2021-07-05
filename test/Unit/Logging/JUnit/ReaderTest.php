<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Tests\TestBase;
use PHPUnit\Framework\ExpectationFailedException;
use stdClass;

use function file_get_contents;
use function file_put_contents;
use function implode;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\JUnit\Reader
 * @covers \ParaTest\Logging\JUnit\TestCase
 * @covers \ParaTest\Logging\JUnit\TestSuite
 */
final class ReaderTest extends TestBase
{
    private string $mixedPath;
    private Reader $mixed;
    private Reader $single;
    private Reader $empty;
    private Reader $multi_errors;

    public function setUpTest(): void
    {
        $this->mixedPath    = FIXTURES . DS . 'results' . DS . 'mixed-results.xml';
        $this->mixed        = new Reader($this->mixedPath);
        $single             = FIXTURES . DS . 'results' . DS . 'single-wfailure.xml';
        $this->single       = new Reader($single);
        $empty              = FIXTURES . DS . 'results' . DS . 'empty-test-suite.xml';
        $this->empty        = new Reader($empty);
        $multi_errors       = FIXTURES . DS . 'results' . DS . 'multiple-errors-with-system-out.xml';
        $this->multi_errors = new Reader($multi_errors);
    }

    public function testInvalidPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $reader = new Reader('/path/to/nowhere');
    }

    public function testFileCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $reader = new Reader(FIXTURES . DS . 'results' . DS . 'empty.xml');
    }

    public function testIsSingleSuiteReturnsTrueForSingleSuite(): void
    {
        static::assertTrue($this->single->isSingleSuite());
    }

    public function testIsSingleSuiteReturnsFalseForMultipleSuites(): void
    {
        static::assertFalse($this->mixed->isSingleSuite());
    }

    public function testMixedSuiteShouldConstructRootSuite(): TestSuite
    {
        $suites = $this->mixed->getSuites();
        static::assertCount(1, $suites);
        static::assertSame('test/fixtures/tests/', $suites[0]->name);
        static::assertSame(19, $suites[0]->tests);
        static::assertSame(10, $suites[0]->assertions);
        static::assertSame(3, $suites[0]->failures);
        static::assertSame(3, $suites[0]->errors);
        static::assertSame(0.001489, $suites[0]->time);

        return $suites[0];
    }

    /**
     * @depends testMixedSuiteShouldConstructRootSuite
     */
    public function testMixedSuiteConstructsChildSuites(TestSuite $suite): TestSuite
    {
        static::assertCount(3, $suite->suites);
        $first = $suite->suites[0];
        static::assertSame('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $first->name);
        static::assertSame(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertSame(4, $first->tests);
        static::assertSame(4, $first->assertions);
        static::assertSame(1, $first->failures);
        static::assertSame(0, $first->errors);
        static::assertSame(0.000357, $first->time);

        return $first;
    }

    /**
     * @depends testMixedSuiteConstructsChildSuites
     */
    public function testMixedSuiteConstructsTestCases(TestSuite $suite): void
    {
        static::assertCount(4, $suite->cases);
        $first = $suite->cases[0];
        static::assertSame('testTruth', $first->name);
        static::assertSame('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $first->class);
        static::assertSame(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertSame(21, $first->line);
        static::assertSame(1, $first->assertions);
        static::assertSame(0.000042, $first->time);
    }

    public function testMixedSuiteCasesLoadFailures(): void
    {
        $suites = $this->mixed->getSuites();
        $case   = $suites[0]->suites[0]->cases[1];
        static::assertCount(1, $case->failures);
        $failure = $case->failures[0];
        static::assertSame(ExpectationFailedException::class, $failure['type']);
        static::assertSame(
            "Fixtures\\Tests\\UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n" .
            '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            $failure['text']
        );
    }

    public function testMixedSuiteCasesLoadErrors(): void
    {
        $suites = $this->mixed->getSuites();
        $case   = $suites[0]->suites[1]->cases[0];
        static::assertCount(1, $case->errors);
        $error = $case->errors[0];
        static::assertSame('Exception', $error['type']);
        static::assertSame(
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithErrorTest.php:17',
            $error['text']
        );
    }

    public function testSingleSuiteShouldConstructRootSuite(): TestSuite
    {
        $suites = $this->single->getSuites();
        static::assertCount(1, $suites);
        static::assertSame('UnitTestWithMethodAnnotationsTest', $suites[0]->name);
        static::assertSame(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php',
            $suites[0]->file
        );
        static::assertSame(3, $suites[0]->tests);
        static::assertSame(3, $suites[0]->assertions);
        static::assertSame(1, $suites[0]->failures);
        static::assertSame(0, $suites[0]->errors);
        static::assertSame(0.005895, $suites[0]->time);

        return $suites[0];
    }

    /**
     * @param mixed $suite
     *
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteShouldHaveNoChildSuites($suite): void
    {
        static::assertCount(0, $suite->suites);
    }

    /**
     * @param mixed $suite
     *
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteConstructsTestCases($suite): void
    {
        static::assertCount(3, $suite->cases);
        $first = $suite->cases[0];
        static::assertSame('testTruth', $first->name);
        static::assertSame('UnitTestWithMethodAnnotationsTest', $first->class);
        static::assertSame(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php',
            $first->file
        );
        static::assertSame(7, $first->line);
        static::assertSame(1, $first->assertions);
        static::assertSame(0.001632, $first->time);
    }

    public function testSingleSuiteCasesLoadFailures(): void
    {
        $suites = $this->single->getSuites();
        $case   = $suites[0]->cases[1];
        static::assertCount(1, $case->failures);
        $failure = $case->failures[0];
        static::assertSame(ExpectationFailedException::class, $failure['type']);
        static::assertSame(
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18',
            $failure['text']
        );
    }

    public function testEmptySuiteConstructsTestCase(): void
    {
        $suites = $this->empty->getSuites();
        static::assertCount(1, $suites);

        $suite = $suites[0];
        static::assertSame('', $suite->name);
        static::assertSame('', $suite->file);
        static::assertSame(0, $suite->tests);
        static::assertSame(0, $suite->assertions);
        static::assertSame(0, $suite->failures);
        static::assertSame(0, $suite->errors);
        static::assertSame(0.0, $suite->time);
    }

    public function testMixedGetTotals(): void
    {
        static::assertSame(19, $this->mixed->getTotalTests());
        static::assertSame(10, $this->mixed->getTotalAssertions());
        static::assertSame(3, $this->mixed->getTotalErrors());
        static::assertSame(3, $this->mixed->getTotalFailures());
        static::assertSame(2, $this->mixed->getTotalWarnings());
        static::assertSame(4, $this->mixed->getTotalSkipped());
        static::assertSame(0.001489, $this->mixed->getTotalTime());
    }

    public function testSingleGetTotals(): void
    {
        static::assertSame(3, $this->single->getTotalTests());
        static::assertSame(3, $this->single->getTotalAssertions());
        static::assertSame(0, $this->single->getTotalErrors());
        static::assertSame(1, $this->single->getTotalFailures());
        static::assertSame(0, $this->single->getTotalWarnings());
        static::assertSame(4, $this->mixed->getTotalSkipped());
        static::assertSame(0.005895, $this->single->getTotalTime());
    }

    public function testMixedGetFailureMessages(): void
    {
        $failures = $this->mixed->getFailures();
        static::assertCount(3, $failures);
        static::assertSame(
            "Fixtures\\Tests\\UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            $failures[0]
        );
        static::assertSame(
            "UnitTestWithErrorTest::testFalsehood\nFailed asserting that true is false." .
                "\n\n/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest." .
                'php:20',
            $failures[1]
        );
    }

    public function testMixedGetErrorMessages(): void
    {
        $errors = $this->mixed->getErrors();
        static::assertCount(1, $errors);
        static::assertSame(
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithErrorTest.php:17',
            $errors[0]
        );
    }

    public function testMixedGetWarningMessages(): void
    {
        $warnings = $this->mixed->getWarnings();
        static::assertCount(2, $warnings);
        static::assertSame(
            "UnitTestWithErrorTest::testWarning\n" .
                'Function 1 deprecated',
            $warnings[0]
        );
    }

    public function testSingleGetMessages(): void
    {
        $failures = $this->single->getFailures();
        static::assertCount(1, $failures);
        static::assertSame(
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18',
            $failures[0]
        );
    }

    /**
     * https://github.com/paratestphp/paratest/issues/352
     */
    public function testGetMultiErrorsMessages(): void
    {
        $errors = $this->multi_errors->getRisky();
        static::assertCount(2, $errors);
        static::assertSame(
            "Risky Test\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:200\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:159\n" .
            'Custom error log on result test with multiple errors!',
            $errors[0]
        );
        static::assertSame(
            "Risky Test\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:200\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:159\n" .
            'Custom error log on result test with multiple errors!',
            $errors[1]
        );
    }

    public function testGetSkippedMessagesForSkippingTest(): void
    {
        $reader  = new Reader(FIXTURES . DS . 'results' . DS . 'single-skipped.xml');
        $skipped = $reader->getSkipped();
        static::assertCount(1, $skipped);
        static::assertSame(
            "UnitTestWithMethodAnnotationsTest::testIncomplete\n\n" .
            '/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:51',
            $skipped[0]
        );
    }

    public function testGetSkippedMessagesForSkippingDataProviders(): void
    {
        $reader  = new Reader(FIXTURES . DS . 'results' . DS . 'data-provider-errors.xml');
        $skipped = $reader->getSkipped();
        static::assertCount(2, $skipped);
        static::assertSame(
            'ParaTest\Tests\fixtures\github\GH565\IssueTest::testIncompleteByDataProvider',
            $skipped[0]
        );
        static::assertSame(
            'ParaTest\Tests\fixtures\github\GH565\IssueTest::testSkippedByDataProvider',
            $skipped[1]
        );
    }

    public function testMixedGetFeedback(): void
    {
        $feedback = $this->mixed->getFeedback();
        static::assertSame('.F..E.F.WSSR.F.WSSR', implode('', $feedback));
    }

    public function testRemoveLog(): void
    {
        $contents = file_get_contents($this->mixedPath);
        $tmp      = FIXTURES . DS . 'results' . DS . 'dummy.xml';
        file_put_contents($tmp, $contents);
        $reader = new Reader($tmp);
        $reader->removeLog();
        static::assertFileDoesNotExist($tmp);
    }

    /**
     * Extraction of log from xml file to use in test of validation "SystemOut" result.
     *
     * @return stdClass $log
     */
    public static function extractLog(): stdClass
    {
        $log          = new stdClass();
        $result       = FIXTURES . DS . 'results' . DS . 'mixed-results-with-system-out.xml';
        $node         = new Reader($result);
        $log->failure = $node->getSuites()[0]->suites[0]->cases[1]->failures[0]['text'];
        $log->error   = $node->getSuites()[0]->suites[1]->cases[0]->errors[0]['text'];

        return $log;
    }

    public function testResultWithSystemOut(): void
    {
        $customLog   = "\nCustom error log on result test with ";
        $result      = FIXTURES . DS . 'results' . DS . 'mixed-results-with-system-out.xml';
        $failLog     = self::extractLog()->failure . $customLog . 'failure!';
        $errorLog    = self::extractLog()->error . $customLog . 'error!';
        $node        = new Reader($result);
        $resultFail  = $node->getSuites()[0]->suites[2]->cases[1]->failures[0]['text'];
        $resultError = $node->getSuites()[0]->suites[1]->cases[1]->errors[0]['text'];

        static::assertSame($failLog, $resultFail);
        static::assertSame($errorLog, $resultError);
    }
}
