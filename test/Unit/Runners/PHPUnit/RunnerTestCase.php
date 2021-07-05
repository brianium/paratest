<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\BaseRunner;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use ParaTest\Tests\TestBase;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Process\Process;

use function array_merge;
use function array_reverse;
use function defined;
use function file_get_contents;
use function posix_mkfifo;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function sprintf;
use function str_replace;
use function substr_count;
use function uniqid;

abstract class RunnerTestCase extends TestBase
{
    public const PASSTHRU_PHPUNIT_CUSTOM = 'PASSTHRU_PHPUNIT_CUSTOM';
    public const PASSTHRU_PHP_CUSTOM     = 'PASSTHRU_PHP_CUSTOM';

    final public function testResultsAreCorrect(): void
    {
        $this->bareOptions['--path']            = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--coverage-php']    = TMP_DIR . DS . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('passing_tests' . DS . 'GroupsTest.php');

        $this->assertTestsPassed($this->runRunner());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        static::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    final public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['--path']      = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--processes'] = '10';

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testRunningMoreTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['--path']      = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--processes'] = '1';

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testExitCodes(): void
    {
        $this->bareOptions['--path'] = $this->fixture('wrapper_runner_exit_code_tests' . DS . 'ErrorTest.php');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper_runner_exit_code_tests' . DS . 'FailureTest.php');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper_runner_exit_code_tests' . DS . 'SuccessTest.php');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('OK (1 test, 1 assertion)', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::SUCCESS_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper_runner_exit_code_tests');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('Tests: 3', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    final public function testParallelSuiteOption(): void
    {
        $this->bareOptions = array_merge($this->bareOptions, [
            '--configuration' => $this->fixture('phpunit-parallel-suite.xml'),
            '--parallel-suite' => true,
            '--processes' => '2',
            '--verbose' => 1,
            '--coverage-filter' => $this->fixture('parallel_suite'),
        ]);

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testRaiseExceptionWhenATestCallsExitSilentlyWithCoverage(): void
    {
        $this->bareOptions['--path']            = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsSilentlyTest.php');
        $this->bareOptions['--coverage-php']    = TMP_DIR . DS . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitLoudlyWithCoverage(): void
    {
        $this->bareOptions['--path']            = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsLoudlyTest.php');
        $this->bareOptions['--coverage-php']    = TMP_DIR . DS . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsLoudlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitSilentlyWithoutCoverage(): void
    {
        $this->bareOptions['--path'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitLoudlyWithoutCoverage(): void
    {
        $this->bareOptions['--path'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsLoudlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitWithoutCoverageSingleProcess(): void
    {
        $this->bareOptions['--path']      = $this->fixture('exit_tests');
        $this->bareOptions['--processes'] = '1';

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExits(Silently|Loudly)Test/');

        $this->runRunner();
    }

    final public function testPassthrus(): void
    {
        $this->bareOptions['--path'] = $this->fixture('passthru_tests' . DS . 'PassthruTest.php');

        $runnerResult = $this->runRunner();
        static::assertSame(BaseRunner::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--passthru']     = sprintf("'-d' 'highlight.string=%s'", self::PASSTHRU_PHPUNIT_CUSTOM);
        $this->bareOptions['--passthru-php'] = sprintf("'-d' 'highlight.comment=%s'", self::PASSTHRU_PHP_CUSTOM);
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->bareOptions['--passthru']     = str_replace('\'', '"', (string) $this->bareOptions['--passthru']);
            $this->bareOptions['--passthru-php'] = str_replace('\'', '"', (string) $this->bareOptions['--passthru-php']);
        }

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testGroupAndExcludeGroupArePassedToPhpunitEvenForNonFunctionTests(): void
    {
        $this->bareOptions['--path']          = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--group']         = 'group1,group2';
        $this->bareOptions['--exclude-group'] = 'group3';

        $this->assertTestsPassed($this->runRunner(), '3', '3');
    }

    final public function testTestsWithWarningsResultInFailure(): void
    {
        $this->bareOptions['--path']          = $this->fixture('warning_tests' . DS . 'HasWarningsTest.php');
        $this->bareOptions['--configuration'] = $this->fixture('warning_tests' . DS . 'phpunit.xml.dist');

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Warnings', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::FAILURE_EXIT, $runnerResult->getExitCode());
    }

    final public function testTestsWithOtherWarningsResultInFailure(): void
    {
        $this->bareOptions['--path']          = $this->fixture('warning_tests' . DS . 'HasOtherWarningsTest.php');
        $this->bareOptions['--configuration'] = $this->fixture('warning_tests' . DS . 'phpunit.xml.dist');

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Warnings', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    final public function testParatestEnvironmentVariable(): void
    {
        $this->bareOptions['--path'] = $this->fixture('paratest_only_tests' . DS . 'EnvironmentTest.php');

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens(): void
    {
        $this->bareOptions['--path']           = $this->fixture('paratest_only_tests' . DS . 'EnvironmentTest.php');
        $this->bareOptions['--no-test-tokens'] = true;

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::FAILURE_EXIT, $runnerResult->getExitCode());
    }

    final public function testSkippedInDefaultMode(): void
    {
        $this->bareOptions['--path'] = $this->fixture('skipped_tests' . DS . 'SkippedTest.php');

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 1, Assertions: 0, Skipped: 1.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(1, $runnerResult->getOutput());
    }

    final public function testIncompleteInDefaultMode(): void
    {
        $this->bareOptions['--path'] = $this->fixture('skipped_tests' . DS . 'IncompleteTest.php');

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 1, Assertions: 0, Skipped: 1.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(1, $runnerResult->getOutput());
    }

    final public function testDataProviderWithSkippedInDefaultMode(): void
    {
        $this->bareOptions['--path'] = $this->fixture('skipped_tests' . DS . 'SkippedAndIncompleteDataProviderTest.php');

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 100, Assertions: 33, Skipped: 67.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(67, $runnerResult->getOutput());
    }

    final protected function assertContainsNSkippedTests(int $n, string $output): void
    {
        preg_match('/\n\n([\.ISEF].*)\n\nTime/s', $output, $matches);
        static::assertCount(2, $matches);
        $numberOfS = substr_count($matches[1], 'S');
        static::assertEquals(
            $n,
            $numberOfS,
            "The test should have skipped {$n} tests, instead it skipped {$numberOfS}, {$matches[1]}"
        );
    }

    final public function testStopOnFailureEndsRunBeforeWholeTestSuite(): void
    {
        $this->bareOptions['--processes'] = '1';
        $this->bareOptions['--path']      = $this->fixture('failing_tests');
        $runnerResult                     = $this->runRunner();

        $regexp = '/Tests: \d+, Assertions: \d+, Errors: \d+, Failures: \d+, Warnings: \d+, Skipped: \d+\./';
        $output = $runnerResult->getOutput();
        static::assertMatchesRegularExpression($regexp, $output);
        static::assertSame(1, preg_match($regexp, $output, $matchesOnFullRun));

        $this->bareOptions['--stop-on-failure'] = true;
        $runnerResult                           = $this->runRunner();

        $regexp = '/Tests: \d+, Assertions: \d+, Failures: \d+\./';
        $output = $runnerResult->getOutput();
        static::assertMatchesRegularExpression($regexp, $output);
        static::assertSame(1, preg_match($regexp, $output, $matchesOnPartialRun));

        static::assertNotEquals($matchesOnFullRun[0], $matchesOnPartialRun[0]);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testReadPhpunitConfigPhpSectionBeforeLoadingTheSuite(): void
    {
        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH420'));
        $this->assertTestsPassed($runnerResult);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testReadPhpunitConfigPhpSectionBeforeLoadingTheSuiteManualBootstrap(): void
    {
        $this->bareOptions['--bootstrap'] = $this->fixture('github' . DS . 'GH420bis' . DS . 'bootstrap.php');

        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH420bis'));
        $this->assertTestsPassed($runnerResult);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testFilterOutTestWithoutGroupWhenGroupIsSpecified(): void
    {
        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH432'));
        $this->assertTestsPassed($runnerResult);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testTokensAreAbsentWhenNoTestTokensIsSpecified(): void
    {
        $this->bareOptions['--no-test-tokens'] = true;
        $this->bareOptions['--processes']      = '1';

        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH505'));
        $this->assertTestsPassed($runnerResult);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testChildProcessPipeOverflow(): void
    {
        $this->bareOptions['--processes'] = '1';

        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH431'));
        $this->assertTestsPassed($runnerResult);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testTokensArePresentByDefault(): void
    {
        $this->bareOptions['--processes'] = '1';

        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH505tokens'));
        $this->assertTestsPassed($runnerResult);
    }

    /**
     * @group github
     * @coversNothing
     */
    final public function testErrorsInDataProviderAreHandled(): void
    {
        $runnerResult = $this->runRunner($this->fixture('github' . DS . 'GH565'));

        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Skipped: 2', $runnerResult->getOutput());
        static::assertEquals(BaseRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    final public function testTeamcityLog(): void
    {
        $outputPath = TMP_DIR . DS . 'test-output.teamcity';

        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--log-teamcity' => $outputPath,
        ];

        $this->runRunner();

        static::assertFileExists($outputPath);
        $content = file_get_contents($outputPath);
        static::assertNotFalse($content);

        self::assertSame(66, preg_match_all('/^##teamcity/m', $content));
    }

    /**
     * @requires OSFAMILY Linux
     */
    final public function testTeamcityLogHandlesFifoFiles(): void
    {
        $outputPath = TMP_DIR . DS . 'test-output.teamcity';

        posix_mkfifo($outputPath, 0600);
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--log-teamcity' => $outputPath,
        ];

        $fifoReader = new Process(['cat', $outputPath]);
        $fifoReader->start();

        $this->runRunner();

        self::assertSame(0, $fifoReader->wait());
        self::assertSame(66, preg_match_all('/^##teamcity/m', $fifoReader->getOutput()));
    }

    final public function testRunnerSort(): void
    {
        $this->bareOptions = [
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => '123',
            '--configuration' => $this->fixture('phpunit-passing.xml'),
        ];

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Random order seed 123', $runnerResult->getOutput());
    }

    final public function testRunnerSortNoSeedProvided(): void
    {
        $this->bareOptions = [
            '--order-by' => Options::ORDER_RANDOM,
            '--configuration' => $this->fixture('phpunit-passing.xml'),
        ];

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Random order seed ', $runnerResult->getOutput());
    }

    final public function testRunnerSortTestEqualBySeed(): void
    {
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => '123',
            '--verbose' => 2,
        ];

        $runnerResultFirst  = $this->runRunner();
        $runnerResultSecond = $this->runRunner();

        $firstOutput  = $this->prepareOutputForTestOrderCheck($runnerResultFirst->getOutput());
        $secondOutput = $this->prepareOutputForTestOrderCheck($runnerResultSecond->getOutput());
        static::assertSame($firstOutput, $secondOutput);

        $this->bareOptions['--random-order-seed'] = '321';

        $runnerResultThird = $this->runRunner();

        $thirdOutput = $this->prepareOutputForTestOrderCheck($runnerResultThird->getOutput());

        static::assertNotSame($thirdOutput, $firstOutput);
    }

    /**
     * A change in '--random-order-seed' must be reflected too in:
     *
     * @see \ParaTest\Tests\fixtures\deterministic_random\MtRandTest::testMtRandIsDeterministic
     */
    final public function testRandomnessIsDeterministic(): void
    {
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-deterministic-random.xml'),
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => '123',
        ];

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testRunnerReversed(): void
    {
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--verbose' => 2,
        ];

        $runnerResult = $this->runRunner();
        $defaultOrder = $this->prepareOutputForTestOrderCheck($runnerResult->getOutput());

        $this->bareOptions['--order-by'] = Options::ORDER_REVERSE;

        $runnerResult = $this->runRunner();
        $reverseOrder = $this->prepareOutputForTestOrderCheck($runnerResult->getOutput());

        $reverseOrderReversed = array_reverse($reverseOrder);

        static::assertSame($defaultOrder, $reverseOrderReversed);
    }

    /**
     * @return string[]
     */
    private function prepareOutputForTestOrderCheck(string $output): array
    {
        $matchesCount = preg_match_all(
            sprintf(
                '/%s%s(?<filename>\S+\.php)/',
                preg_quote(FIXTURES, '/'),
                preg_quote(DS, '/')
            ),
            $output,
            $matches
        );

        self::assertGreaterThan(0, $matchesCount);

        return $matches['filename'];
    }
}
