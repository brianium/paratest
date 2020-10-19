<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Tests\TestBase;
use Symfony\Component\Process\Process;

use function array_reverse;
use function assert;
use function count;
use function file_get_contents;
use function glob;
use function posix_mkfifo;
use function preg_match_all;
use function preg_quote;
use function simplexml_load_file;
use function sprintf;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\BaseRunner
 */
final class BaseRunnerTest extends TestBase
{
    protected function setUpTest(): void
    {
        static::skipIfCodeCoverageNotEnabled();

        $this->bareOptions = [
            '--path' => $this->fixture('failing_tests'),
            '--coverage-clover' => TMP_DIR . DS . 'coverage.clover',
            '--coverage-crap4j' => TMP_DIR . DS . 'coverage.crap4j',
            '--coverage-html' => TMP_DIR . DS . 'coverage.html',
            '--coverage-php' => TMP_DIR . DS . 'coverage.php',
            '--coverage-text' => true,
            '--coverage-xml' => TMP_DIR . DS . 'coverage.xml',
            '--bootstrap' => BOOTSTRAP,
            '--whitelist' => $this->fixture('failing_tests'),
        ];
    }

    /**
     * @return string[]
     */
    private function globTempDir(string $pattern): array
    {
        $glob = glob(TMP_DIR . DS . $pattern);
        assert($glob !== false);

        return $glob;
    }

    public function testGeneratesCoverageTypes(): void
    {
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-clover']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-crap4j']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-html']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-php']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-xml']);

        $this->bareOptions['--configuration'] = $this->fixture('phpunit-fully-configured.xml');
        $runnerResult                         = $this->runRunner();

        static::assertFileExists((string) $this->bareOptions['--coverage-clover']);
        static::assertFileExists((string) $this->bareOptions['--coverage-crap4j']);
        static::assertFileExists((string) $this->bareOptions['--coverage-html']);
        static::assertFileExists((string) $this->bareOptions['--coverage-php']);
        static::assertFileExists((string) $this->bareOptions['--coverage-xml']);

        static::assertStringContainsString('Code Coverage Report:', $runnerResult->getOutput());
        static::assertStringContainsString('Generating code coverage', $runnerResult->getOutput());
    }

    public function testRunningTestsShouldLeaveNoTempFiles(): void
    {
        // Needed for one line coverage on early exit CS Fix :\
        unset($this->bareOptions['--coverage-php']);
        $this->bareOptions['--log-teamcity'] = TMP_DIR . DS . 'test-output.teamcity';

        $countBefore         = count($this->globTempDir('PT_*'));
        $countCoverageBefore = count($this->globTempDir('CV_*'));
        $countTeamcityBefore = count($this->globTempDir('TF_*'));

        $this->runRunner();

        $countAfter         = count($this->globTempDir('PT_*'));
        $countCoverageAfter = count($this->globTempDir('CV_*'));
        $countTeamcityAfter = count($this->globTempDir('CF_*'));

        static::assertSame(
            $countAfter,
            $countBefore,
            "Test Runner failed to clean up the 'PT_*' file in " . TMP_DIR
        );
        static::assertSame(
            $countCoverageAfter,
            $countCoverageBefore,
            "Test Runner failed to clean up the 'CV_*' file in " . TMP_DIR
        );
        static::assertSame(
            $countTeamcityAfter,
            $countTeamcityBefore,
            "Test Runner failed to clean up the 'TF_*' file in " . TMP_DIR
        );
    }

    public function testLogJUnitCreatesXmlFile(): void
    {
        $outputPath = TMP_DIR . DS . 'test-output.xml';

        $this->bareOptions['--log-junit'] = $outputPath;

        $this->runRunner();

        static::assertFileExists($outputPath);
        $this->assertJunitXmlIsCorrect($outputPath);
    }

    private function assertJunitXmlIsCorrect(string $path): void
    {
        $doc = simplexml_load_file($path);
        static::assertNotFalse($doc);
        $suites   = $doc->xpath('//testsuite');
        $cases    = $doc->xpath('//testcase');
        $failures = $doc->xpath('//failure');
        $warnings = $doc->xpath('//warning');
        $skipped  = $doc->xpath('//skipped');
        $errors   = $doc->xpath('//error');

        // these numbers represent the tests in fixtures/failing_tests
        // so will need to be updated when tests are added or removed
        static::assertNotFalse($suites);
        static::assertCount(6, $suites);
        static::assertNotFalse($cases);
        static::assertCount(24, $cases);
        static::assertNotFalse($failures);
        static::assertCount(6, $failures);
        static::assertNotFalse($warnings);
        static::assertCount(2, $warnings);
        static::assertNotFalse($skipped);
        static::assertCount(4, $skipped);
        static::assertNotFalse($errors);
        static::assertCount(3, $errors);
    }

    public function testWritesLogWithEmptyNameWhenPathIsNotProvided(): void
    {
        $outputPath = TMP_DIR . DS . 'test-output.xml';

        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--log-junit' => $outputPath,
        ];

        $this->runRunner();

        static::assertFileExists($outputPath);
        $doc = simplexml_load_file($outputPath);
        static::assertNotFalse($doc);
        $suites = (array) $doc->children();
        static::assertArrayHasKey('testsuite', $suites);
        $attribues = (array) $suites['testsuite']->attributes();
        static::assertArrayHasKey('@attributes', $attribues);
        static::assertIsArray($attribues['@attributes']);
        static::assertArrayHasKey('name', $attribues['@attributes']);
        static::assertSame('', $attribues['@attributes']['name']);
    }

    public function testTeamcityLog(): void
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
    public function testTeamcityLogHandlesFifoFiles(): void
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

    public function testRunnerSort(): void
    {
        $this->bareOptions = [
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => 123,
            '--configuration' => $this->fixture('phpunit-passing.xml'),
        ];

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Random order seed 123', $runnerResult->getOutput());
    }

    public function testRunnerSortNoSeedProvided(): void
    {
        $this->bareOptions = [
            '--order-by' => Options::ORDER_RANDOM,
            '--configuration' => $this->fixture('phpunit-passing.xml'),
        ];

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Random order seed ', $runnerResult->getOutput());
    }

    public function testRunnerSortTestEqualBySeed(): void
    {
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => 123,
            '--verbose' => 1,
        ];

        $runnerResultFirst  = $this->runRunner();
        $runnerResultSecond = $this->runRunner();

        $firstOutput  = $this->prepareOutputForTestOrderCheck($runnerResultFirst->getOutput());
        $secondOutput = $this->prepareOutputForTestOrderCheck($runnerResultSecond->getOutput());
        static::assertSame($firstOutput, $secondOutput);

        $this->bareOptions['--random-order-seed'] = 321;

        $runnerResultThird = $this->runRunner();

        $thirdOutput = $this->prepareOutputForTestOrderCheck($runnerResultThird->getOutput());

        static::assertNotSame($thirdOutput, $firstOutput);
    }

    public function testRunnerReversed(): void
    {
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--verbose' => 1,
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
