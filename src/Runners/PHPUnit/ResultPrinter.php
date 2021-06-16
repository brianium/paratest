<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\LogInterpreter;
use PHPUnit\Util\Color;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function assert;
use function count;
use function fclose;
use function file_get_contents;
use function filesize;
use function floor;
use function fopen;
use function fwrite;
use function implode;
use function is_array;
use function max;
use function preg_split;
use function rtrim;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

/**
 * Used for outputting ParaTest results
 *
 * @internal
 */
final class ResultPrinter
{
    /**
     * A collection of ExecutableTest objects.
     *
     * @var ExecutableTest[]
     */
    private $suites = [];

    /** @var LogInterpreter */
    private $results;

    /**
     * The number of tests results currently printed.
     * Used to determine when to tally current results
     * and start a new row.
     *
     * @var int
     */
    private $numTestsWidth = 0;

    /**
     * Used for formatting results to a given width.
     *
     * @var int
     */
    private $maxColumn = 0;

    /**
     * The total number of cases to be run.
     *
     * @var int
     */
    private $totalCases = 0;

    /**
     * The current column being printed to.
     *
     * @var int
     */
    private $column = 0;

    /**
     * The total number of cases printed so far.
     *
     * @var int
     */
    private $casesProcessed = 0;

    /**
     * Number of columns.
     *
     * @var int
     */
    private $numberOfColumns = 80;

    /**
     * Number of skipped or incomplete tests.
     *
     * @var int
     */
    private $totalSkippedOrIncomplete = 0;

    /**
     * Do we need to try to process skipped/incompleted tests.
     *
     * @var bool
     */
    private $processSkipped = false;

    /** @var OutputInterface */
    private $output;
    /** @var Options */
    private $options;
    /** @var resource|null */
    private $teamcityLogFileHandle;

    public function __construct(LogInterpreter $results, OutputInterface $output, Options $options)
    {
        $this->results = $results;
        $this->output  = $output;
        $this->options = $options;

        if (($teamcityLogFile = $this->options->logTeamcity()) === null) {
            return;
        }

        $teamcityLogFileHandle = fopen($teamcityLogFile, 'ab+');
        assert($teamcityLogFileHandle !== false);
        $this->teamcityLogFileHandle = $teamcityLogFileHandle;
    }

    /**
     * Adds an ExecutableTest to the tracked results.
     */
    public function addTest(ExecutableTest $suite): void
    {
        $this->suites[]    = $suite;
        $this->totalCases += $suite->getTestCount();
    }

    /**
     * Initializes printing constraints, prints header
     * information and starts the test timer.
     */
    public function start(): void
    {
        $this->numTestsWidth = strlen((string) $this->totalCases);
        $this->maxColumn     = $this->numberOfColumns
                         + (DIRECTORY_SEPARATOR === '\\' ? -1 : 0) // fix windows blank lines
                         - strlen($this->getProgress());
        $this->output->write(sprintf(
            "Running phpunit in %d process%s with %s%s\n\n",
            $this->options->processes(),
            $this->options->processes() > 1 ? 'es' : '',
            $this->options->phpunit(),
            $this->options->functional() ? '. Functional mode is ON.' : ''
        ));
        if (($configuration = $this->options->configuration()) !== null) {
            $this->output->write(sprintf(
                "Configuration read from %s\n\n",
                $configuration->filename()
            ));
        }

        if ($this->options->orderBy() === Options::ORDER_RANDOM) {
            $this->output->write(sprintf(
                "Random order seed %d\n\n",
                $this->options->randomOrderSeed()
            ));
        }

        if ($this->options->orderBy() === Options::ORDER_REVERSE) {
            $this->output->write("Reversed tests order\n\n");
        }

        $this->processSkipped = $this->isSkippedIncompleTestCanBeTracked($this->options);
    }

    public function println(string $string = ''): void
    {
        $this->column = 0;
        $this->output->write($string . "\n");
    }

    /**
     * Prints all results and removes any log files
     * used for aggregating results.
     */
    public function printResults(): void
    {
        $toFilter = [
            $this->getErrors(),
            $this->getWarnings(),
            $this->getFailures(),
            $this->getRisky(),
        ];
        if ($this->options->verbosity() >= Options::VERBOSITY_VERBOSE) {
            $toFilter[] = $this->getSkipped();
        }

        $failures = array_filter($toFilter);

        $this->output->write($this->getHeader());
        $this->output->write(implode("---\n\n", $failures));
        $this->output->write($this->getFooter());

        if ($this->teamcityLogFileHandle === null) {
            return;
        }

        $resource                    = $this->teamcityLogFileHandle;
        $this->teamcityLogFileHandle = null;
        fclose($resource);
    }

    /**
     * Prints the individual "quick" feedback for run
     * tests, that is the ".EF" items.
     */
    public function printFeedback(ExecutableTest $test): Reader
    {
        try {
            $reader = new Reader($test->getTempFile());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new EmptyLogFileException(sprintf(
                "%s\n" .
                "The process: %s\n" .
                "This means a PHPUnit process was unable to run \"%s\"\n",
                $invalidArgumentException->getMessage(),
                $test->getLastCommand(),
                $test->getPath()
            ), 0, $invalidArgumentException);
        }

        if ($this->teamcityLogFileHandle !== null) {
            $log_file = $test->getTeamcityTempFile();

            if (filesize($log_file) === 0) {
                throw new EmptyLogFileException(sprintf(
                    "Teamcity format file is empty.\n" .
                    "The process: %s\n" .
                    "This means a PHPUnit process was unable to run \"%s\"\n",
                    $test->getLastCommand(),
                    $test->getPath()
                ));
            }

            $result = file_get_contents($log_file);
            assert($result !== false);
            fwrite($this->teamcityLogFileHandle, $result);
        }

        $this->results->addReader($reader);
        $this->processReaderFeedback($reader, $test->getTestCount());

        return $reader;
    }

    /**
     * Returns the header containing resource usage.
     */
    public function getHeader(): string
    {
        $resourceUsage = (new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest();

        return "\n" . $resourceUsage . "\n\n";
    }

    /**
     * Return the footer information reporting success
     * or failure.
     */
    public function getFooter(): string
    {
        if ($this->results->isSuccessful()) {
            if ($this->results->getTotalWarnings() === 0) {
                $footer = $this->getSuccessFooter();
            } else {
                $footer = $this->getWarningFooter();
            }
        } else {
            $footer = $this->getFailedFooter();
        }

        return "{$footer}\n";
    }

    /**
     * Returns error messages.
     */
    public function getErrors(): string
    {
        $errors = $this->results->getErrors();

        return $this->getDefects($errors, 'error');
    }

    /**
     * Returns warning messages as a string.
     */
    public function getWarnings(): string
    {
        $warnings = $this->results->getWarnings();

        return $this->getDefects($warnings, 'warning');
    }

    /**
     * Returns the failure messages.
     */
    public function getFailures(): string
    {
        $failures = $this->results->getFailures();

        return $this->getDefects($failures, 'failure');
    }

    /**
     * Returns the risky messages.
     */
    public function getRisky(): string
    {
        $risky = $this->results->getRisky();

        return $this->getDefects($risky, 'risky');
    }

    /**
     * Returns the skipped messages.
     */
    public function getSkipped(): string
    {
        $risky = $this->results->getSkipped();

        return $this->getDefects($risky, 'skipped');
    }

    /**
     * Returns the total cases being printed.
     */
    public function getTotalCases(): int
    {
        return $this->totalCases;
    }

    /**
     * Process reader feedback and print it.
     */
    private function processReaderFeedback(Reader $reader, int $expectedTestCount): void
    {
        $feedbackItems = $reader->getFeedback();

        $actualTestCount = count($feedbackItems);

        $this->processTestOverhead($actualTestCount, $expectedTestCount);

        foreach ($feedbackItems as $item) {
            $this->printFeedbackItem($item);
            if ($item !== 'S') {
                continue;
            }

            ++$this->totalSkippedOrIncomplete;
        }

        if (! $this->processSkipped) {
            return;
        }

        $this->printSkippedAndIncomplete($actualTestCount, $expectedTestCount);
    }

    /**
     * Is skipped/incomplete amount can be properly processed.
     *
     * @todo Skipped/Incomplete test tracking available only in functional mode for now
     *       or in regular mode but without group/exclude-group filters.
     */
    private function isSkippedIncompleTestCanBeTracked(Options $options): bool
    {
        return $options->functional()
            || (count($options->group()) === 0 && count($options->excludeGroup()) === 0);
    }

    /**
     * Process test overhead.
     *
     * In some situations phpunit can return more tests then we expect and in that case
     * this method correct total amount of tests so paratest progress will be auto corrected.
     *
     * @todo May be we need to throw Exception here instead of silent correction.
     */
    private function processTestOverhead(int $actualTestCount, int $expectedTestCount): void
    {
        $overhead = $actualTestCount - $expectedTestCount;
        if ($this->processSkipped) {
            if ($overhead > 0) {
                $this->totalCases += $overhead;
            } else {
                $this->totalSkippedOrIncomplete += -$overhead;
            }
        } else {
            $this->totalCases += $overhead;
        }
    }

    /**
     * Prints S for skipped and incomplete tests.
     *
     * If for some reason process return less tests than expected then we threat all remaining
     * as skipped or incomplete and print them as skipped (S letter)
     */
    private function printSkippedAndIncomplete(int $actualTestCount, int $expectedTestCount): void
    {
        $overhead = $expectedTestCount - $actualTestCount;
        if ($overhead <= 0) {
            return;
        }

        for ($i = 0; $i < $overhead; ++$i) {
            $this->printFeedbackItem('S');
        }
    }

    /**
     * Prints a single "quick" feedback item and increments
     * the total number of processed cases and the column
     * position.
     */
    private function printFeedbackItem(string $item): void
    {
        $this->printFeedbackItemColor($item);
        ++$this->column;
        ++$this->casesProcessed;
        if ($this->column !== $this->maxColumn && $this->casesProcessed < $this->totalCases) {
            return;
        }

        if (
            $this->casesProcessed > 0
            && $this->casesProcessed === $this->totalCases
            && ($pad = $this->maxColumn - $this->column) > 0
        ) {
            $this->output->write(str_repeat(' ', $pad));
        }

        $this->output->write($this->getProgress());
        $this->println();
    }

    private function printFeedbackItemColor(string $item): void
    {
        $buffer = $item;
        switch ($item) {
            case 'E':
                $buffer = $this->colorizeTextBox('fg-red, bold', $item);

                break;

            case 'F':
                $buffer = $this->colorizeTextBox('bg-red, fg-white', $item);

                break;

            case 'W':
            case 'I':
            case 'R':
                $buffer = $this->colorizeTextBox('fg-yellow, bold', $item);

                break;

            case 'S':
                $buffer = $this->colorizeTextBox('fg-cyan, bold', $item);

                break;
        }

        $this->output->write($buffer);
    }

    /**
     * Method that returns a formatted string
     * for a collection of errors or failures.
     *
     * @param string[] $defects
     */
    private function getDefects(array $defects, string $type): string
    {
        $count = count($defects);
        if ($count === 0) {
            return '';
        }

        $output = sprintf(
            "There %s %d %s%s:\n",
            $count === 1 ? 'was' : 'were',
            $count,
            $type,
            $count === 1 ? '' : 's'
        );

        for ($i = 1; $i <= count($defects); ++$i) {
            $output .= sprintf("\n%d) %s\n", $i, $defects[$i - 1]);
        }

        $output .= "\n";

        return $output;
    }

    /**
     * Prints progress for large test collections.
     */
    private function getProgress(): string
    {
        return sprintf(
            ' %' . $this->numTestsWidth . 'd / %' . $this->numTestsWidth . 'd (%3s%%)',
            $this->casesProcessed,
            $this->totalCases,
            floor(($this->totalCases > 0 ? $this->casesProcessed / $this->totalCases : 0) * 100)
        );
    }

    /**
     * Get the footer for a test collection that had tests with
     * failures or errors.
     */
    private function getFailedFooter(): string
    {
        $formatString = "FAILURES!\n%s";

        return $this->colorizeTextBox(
            'fg-white, bg-red',
            sprintf(
                $formatString,
                $this->getFooterCounts()
            )
        );
    }

    /**
     * Get the footer for a test collection containing all successful
     * tests.
     */
    private function getSuccessFooter(): string
    {
        if ($this->totalSkippedOrIncomplete === 0) {
            $tests   = $this->totalCases;
            $asserts = $this->results->getTotalAssertions();

            return $this->colorizeTextBox(
                'fg-black, bg-green',
                sprintf(
                    'OK (%d test%s, %d assertion%s)',
                    $tests,
                    $tests === 1 ? '' : 's',
                    $asserts,
                    $asserts === 1 ? '' : 's'
                )
            );
        }

        return $this->colorizeTextBox(
            'fg-black, bg-yellow',
            sprintf(
                "OK, but incomplete, skipped, or risky tests!\n"
                . '%s',
                $this->getFooterCounts()
            )
        );
    }

    private function getWarningFooter(): string
    {
        $formatString = "WARNINGS!\n%s";

        return $this->colorizeTextBox(
            'fg-black, bg-yellow',
            sprintf(
                $formatString,
                $this->getFooterCounts()
            )
        );
    }

    private function getFooterCounts(): string
    {
        $counts = [
            'Tests' => $this->results->getTotalTests(),
            'Assertions' => $this->results->getTotalAssertions(),
        ] + array_filter([
            'Errors' => $this->results->getTotalErrors(),
            'Failures' => $this->results->getTotalFailures(),
            'Warnings' => $this->results->getTotalWarnings(),
            'Skipped' => $this->results->getTotalSkipped(),
        ]);

        $output = '';
        foreach ($counts as $label => $count) {
            $output .= sprintf('%s: %s, ', $label, $count);
        }

        return rtrim($output, ', ') . '.';
    }

    /**
     * @see \PHPUnit\TextUI\DefaultResultPrinter::colorizeTextBox
     */
    private function colorizeTextBox(string $color, string $buffer): string
    {
        if (! $this->options->colors()) {
            return $buffer;
        }

        $lines = preg_split('/\r\n|\r|\n/', $buffer);
        assert(is_array($lines));
        $padding = max(array_map('\\strlen', $lines));
        assert($padding !== false);

        $styledLines = [];
        foreach ($lines as $line) {
            $styledLines[] = Color::colorize($color, str_pad($line, $padding));
        }

        return implode(PHP_EOL, $styledLines);
    }
}
