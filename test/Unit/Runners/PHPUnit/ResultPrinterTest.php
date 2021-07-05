<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\Unit\ResultTester;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

use function defined;
use function file_put_contents;
use function sprintf;
use function str_repeat;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\ResultPrinter
 */
final class ResultPrinterTest extends ResultTester
{
    private ResultPrinter $printer;
    private BufferedOutput $output;
    private LogInterpreter $interpreter;
    private Suite $passingSuiteWithWrongTestCountEstimation;
    private Options $options;

    protected function setUpInterpreter(): void
    {
        $this->interpreter = new LogInterpreter();
        $this->output      = new BufferedOutput();
        $this->options     = $this->createOptionsFromArgv([], __DIR__);
        $this->printer     = new ResultPrinter($this->interpreter, $this->output, $this->options);

        $this->passingSuiteWithWrongTestCountEstimation = $this->getSuiteWithResult('single-passing.xml', 1);
    }

    public function testConstructor(): void
    {
        static::assertSame([], $this->getObjectValue($this->printer, 'suites'));
        static::assertInstanceOf(
            LogInterpreter::class,
            $this->getObjectValue($this->printer, 'results')
        );
    }

    public function testAddTestShouldAddTest(): void
    {
        $suite = new Suite('/path/to/ResultSuite.php', [], false, false, TMP_DIR);

        $this->printer->addTest($suite);

        static::assertSame([$suite], $this->getObjectValue($this->printer, 'suites'));
    }

    public function testStartPrintsOptionInfo(): void
    {
        $contents = $this->getStartOutput();
        $expected = sprintf(
            "Running phpunit in %s processes with %s\n\n",
            PROCESSES_FOR_TESTS,
            $this->options->phpunit()
        );
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartSetsWidthAndMaxColumn(): void
    {
        $funcs = [];
        for ($i = 0; $i < 120; ++$i) {
            $funcs[] = new TestMethod((string) $i, [], false, false, TMP_DIR);
        }

        $suite = new Suite('/path', $funcs, false, false, TMP_DIR);
        $this->printer->addTest($suite);
        $this->getStartOutput();
        $numTestsWidth = $this->getObjectValue($this->printer, 'numTestsWidth');
        static::assertSame(3, $numTestsWidth);
        $maxExpectedColumun = 63;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $maxExpectedColumun -= 1;
        }

        $maxColumn = $this->getObjectValue($this->printer, 'maxColumn');
        static::assertSame($maxExpectedColumun, $maxColumn);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent(): void
    {
        $pathToConfig = TMP_DIR . DS . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<root />');
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--configuration' => $pathToConfig]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf(
            "Running phpunit in %s processes with %s\n\nConfiguration read from %s\n\n",
            PROCESSES_FOR_TESTS,
            $this->options->phpunit(),
            $pathToConfig
        );
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithRandom(): void
    {
        $pathToConfig = TMP_DIR . DS . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<root />');
        $random_seed   = 1234;
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
            '--configuration' => $pathToConfig,
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => (string) $random_seed,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf(
            "Running phpunit in %s processes with %s\n\nConfiguration read from %s\n\nRandom order seed %s\n\n",
            PROCESSES_FOR_TESTS,
            $this->options->phpunit(),
            $pathToConfig,
            $random_seed
        );
        static::assertEquals($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithReverseOrder(): void
    {
        $pathToConfig = TMP_DIR . DS . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<root />');
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
            '--configuration' => $pathToConfig,
            '--order-by' => Options::ORDER_REVERSE,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf(
            "Running phpunit in %s processes with %s\n\nConfiguration read from %s\n\nReversed tests order\n\n",
            PROCESSES_FOR_TESTS,
            $this->options->phpunit(),
            $pathToConfig
        );
        static::assertEquals($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithFunctionalMode(): void
    {
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--functional' => true]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf(
            "Running phpunit in %s processes with %s. Functional mode is ON.\n\n",
            PROCESSES_FOR_TESTS,
            $this->options->phpunit()
        );
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess(): void
    {
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--processes' => '1']));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("Running phpunit in 1 process with %s\n\n", $this->options->phpunit());
        static::assertStringStartsWith($expected, $contents);
    }

    public function testAddSuiteAddsFunctionCountToTotalTestCases(): void
    {
        $suite = new Suite('/path', [
            new TestMethod('funcOne', [], false, false, TMP_DIR),
            new TestMethod('funcTwo', [], false, false, TMP_DIR),
        ], false, false, TMP_DIR);
        $this->printer->addTest($suite);
        static::assertSame(2, $this->printer->getTotalCases());
    }

    public function testAddTestMethodIncrementsCountByOne(): void
    {
        $method = new TestMethod('/path', ['testThisMethod'], false, false, TMP_DIR);
        $this->printer->addTest($method);
        static::assertSame(1, $this->printer->getTotalCases());
    }

    public function testGetHeader(): void
    {
        $this->printer->addTest($this->errorSuite);
        $this->printer->addTest($this->failureSuite);

        $this->prepareReaders();

        $header = $this->printer->getHeader();

        static::assertMatchesRegularExpression(
            "/\nTime: ([.:]?[0-9]{1,3})+ ?" .
            '(minute|minutes|second|seconds|ms|)?,' .
            " Memory:[\\s][0-9]+([.][0-9]{1,2})? ?M[Bb]\n\n/",
            $header
        );
    }

    public function testGetErrorsSingleError(): void
    {
        $this->printer->addTest($this->errorSuite);
        $this->printer->addTest($this->failureSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq  = "There was 1 error:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n\n";

        static::assertSame($eq, $errors);
    }

    public function testGetErrorsMultipleErrors(): void
    {
        $this->printer->addTest($this->errorSuite);
        $this->printer->addTest($this->otherErrorSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq  = "There were 2 errors:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";
        $eq .= "\n2) UnitTestWithOtherErrorTest::testSomeCase\n";
        $eq .= "Exception: Another Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithOtherErrorTest.php:12\n\n";

        static::assertSame($eq, $errors);
    }

    public function testGetFailures(): void
    {
        $this->printer->addTest($this->mixedSuite);

        $this->prepareReaders();

        $failures = $this->printer->getFailures();

        $eq  = "There were 3 failures:\n\n";
        $eq .= "1) Fixtures\\Tests\\UnitTestWithClassAnnotationTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32\n\n";
        $eq .= "2) UnitTestWithErrorTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:20\n\n";
        $eq .= "3) UnitTestWithMethodAnnotationsTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:20\n\n";

        static::assertSame($eq, $failures);
    }

    public function testGetRisky(): void
    {
        $this->printer->addTest($this->mixedSuite);

        $this->prepareReaders();

        $failures = $this->printer->getRisky();

        $eq  = "There were 2 riskys:\n\n";
        $eq .= "1) Risky Test\n\n";
        $eq .= "2) Risky Test\n\n";

        static::assertSame($eq, $failures);
    }

    public function testGetSkipped(): void
    {
        $this->printer->addTest($this->skipped);

        $this->prepareReaders();

        $failures = $this->printer->getSkipped();

        $eq  = "There was 1 skipped:\n\n";
        $eq .= "1) UnitTestWithMethodAnnotationsTest::testIncomplete\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:51\n\n";

        static::assertSame($eq, $failures);
    }

    public function testGetFooterWithFailures(): void
    {
        $this->printer->addTest($this->errorSuite);
        $this->printer->addTest($this->mixedSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq  = "FAILURES!\n";
        $eq .= "Tests: 20, Assertions: 10, Errors: 4, Failures: 3, Warnings: 2, Skipped: 4.\n";

        static::assertSame($eq, $footer);
    }

    public function testGetFooterWithWarnings(): void
    {
        $this->printer->addTest($this->warningSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq  = "WARNINGS!\n";
        $eq .= "Tests: 1, Assertions: 0, Warnings: 1.\n";

        static::assertSame($eq, $footer);
    }

    public function testGetFooterWithSuccess(): void
    {
        $this->printer->addTest($this->passingSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        static::assertSame($eq, $footer);
    }

    public function testPrintFeedbackForMixed(): void
    {
        $this->printer->addTest($this->mixedSuite);
        $this->printer->printFeedback($this->mixedSuite);
        $contents = $this->output->fetch();
        static::assertSame(".F..E.F.WSSR.F.WSSR 19 / 19 (100%)\n", $contents);
    }

    public function testPrintFeedbackForMoreThan100Suites(): void
    {
        //add tests
        for ($i = 0; $i < 40; ++$i) {
            $this->printer->addTest($this->passingSuite);
        }

        $this->printer->start();
        $this->output->fetch();

        for ($i = 0; $i < 40; ++$i) {
            $this->printer->printFeedback($this->passingSuite);
        }

        $feedback = $this->output->fetch();

        $firstRowColumns  = 63;
        $secondRowColumns = 57;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $firstRowColumns  -= 1;
            $secondRowColumns += 1;
        }

        //assert it is as expected
        $expected = '';
        for ($i = 0; $i < $firstRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf("  %s / 120 ( %s%%)\n", $firstRowColumns, (int) ($firstRowColumns / 120 * 100));
        for ($i = 0; $i < $secondRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf("%s 120 / 120 (100%%)\n", str_repeat(' ', $firstRowColumns - $secondRowColumns));

        static::assertSame($expected, $feedback);
    }

    public function testResultPrinterAdjustsTotalCountForDataProviders(): void
    {
        //add tests
        for ($i = 0; $i < 22; ++$i) {
            $this->printer->addTest($this->passingSuiteWithWrongTestCountEstimation);
        }

        $this->printer->start();
        $this->output->fetch();

        for ($i = 0; $i < 22; ++$i) {
            $this->printer->printFeedback($this->passingSuiteWithWrongTestCountEstimation);
        }

        $feedback = $this->output->fetch();

        $firstRowColumns  = 65;
        $secondRowColumns = 1;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $firstRowColumns  -= 1;
            $secondRowColumns += 1;
        }

        //assert it is as expected
        $expected = '';
        for ($i = 0; $i < $firstRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf(" %s / 66 ( %s%%)\n", $firstRowColumns, (int) ($firstRowColumns / 66 * 100));
        for ($i = 0; $i < $secondRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf("%s 66 / 66 (100%%)\n", str_repeat(' ', $firstRowColumns - $secondRowColumns));

        static::assertSame($expected, $feedback);
    }

    public function testColorsForFailing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->mixedSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->mixedSuite);
        $this->printer->printResults();

        static::assertStringContainsString('FAILURES', $this->output->fetch());
    }

    public function testColorsForWarning(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->warningSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->warningSuite);
        $this->printer->printResults();

        static::assertStringContainsString('WARNING', $this->output->fetch());
    }

    public function testColorsForSkipped(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->skipped);

        $this->printer->start();
        $this->printer->printFeedback($this->skipped);
        $this->printer->printResults();

        $output = $this->output->fetch();
        static::assertStringContainsString('OK', $output);
        static::assertStringNotContainsString('UnitTestWithMethodAnnotationsTest', $output);
    }

    public function testSkippedOutpusMessagesWithVerbose(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true, '--verbose' => 1]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->skipped);

        $this->printer->start();
        $this->printer->printFeedback($this->skipped);
        $this->printer->printResults();

        $output = $this->output->fetch();
        static::assertStringContainsString('OK', $output);
        static::assertStringContainsString('UnitTestWithMethodAnnotationsTest', $output);
    }

    public function testColorsForPassing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->passingSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->passingSuite);
        $this->printer->printResults();

        static::assertStringContainsString('OK', $this->output->fetch());
    }

    /**
     * This test ensure Code Coverage over printSkippedAndIncomplete
     * but the real case for this test case is missing at the time of writing
     *
     * @see \ParaTest\Runners\PHPUnit\ResultPrinter::printSkippedAndIncomplete
     */
    public function testParallelSuiteProgressOverhead(): void
    {
        $suite = $this->getSuiteWithResult('mixed-results.xml', 100);
        $this->printer->addTest($suite);

        $this->printer->start();
        $this->printer->printFeedback($suite);
        $this->printer->printResults();

        static::assertStringContainsString('FAILURES', $this->output->fetch());
    }

    public function testEmptyLogFileRaiseExceptionWithLastCommand(): void
    {
        $test = new ExecutableTestChild(uniqid(), false, false, TMP_DIR);
        $test->setLastCommand(uniqid());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', $test->getLastCommand()));

        $this->printer->printFeedback($test);
    }

    public function testTeamcityEmptyLogFileRaiseExceptionWithLastCommand(): void
    {
        $teamcityLog = TMP_DIR . DS . 'teamcity.log';

        $this->options = $this->createOptionsFromArgv(['--log-teamcity' => $teamcityLog]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);

        $test = $this->getSuiteWithResult('single-passing.xml', 1);
        $test->setLastCommand(uniqid());

        file_put_contents($test->getTeamcityTempFile(), '');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(sprintf('/Teamcity.+%s/s', $test->getLastCommand()));

        $this->printer->printFeedback($test);
    }

    public function testTeamcityFeedback(): void
    {
        $teamcityLog = TMP_DIR . DS . 'teamcity2.log';

        $this->options = $this->createOptionsFromArgv(['--log-teamcity' => $teamcityLog]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->passingSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->passingSuite);
        $this->printer->printResults();

        static::assertStringContainsString('OK', $this->output->fetch());
        static::assertFileExists($teamcityLog);
    }

    private function getStartOutput(): string
    {
        $this->printer->start();

        return $this->output->fetch();
    }

    private function prepareReaders(): void
    {
        $suites = $this->getObjectValue($this->printer, 'suites');
        foreach ($suites as $suite) {
            $this->printer->printFeedback($suite);
        }

        $this->output->fetch();
    }
}
