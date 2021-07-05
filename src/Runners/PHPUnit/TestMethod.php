<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function array_reduce;
use function count;
use function implode;
use function preg_quote;
use function strpos;

/**
 * Represents a set of tests grouped in batch which can be passed to a single phpunit process.
 * Batch limited to run tests only from one php test case file.
 * Used for running ParaTest in functional mode.
 *
 * @internal
 *
 * @todo Rename to Batch
 */
final class TestMethod extends ExecutableTest
{
    /**
     * A set of filters for test, they are merged into phpunit's --filter option.
     *
     * @var string[]
     */
    private array $filters;

    /**
     * Passed filters must be unescaped and must represent test name, optionally including
     * dataset name (numeric or named).
     *
     * @param string   $testPath path to phpunit test case file
     * @param string[] $filters  array of filters or single filter
     */
    public function __construct(string $testPath, array $filters, bool $needsCoverage, bool $needsTeamcity, string $tmpDir)
    {
        parent::__construct($testPath, $needsCoverage, $needsTeamcity, $tmpDir);
        // for compatibility with other code (tests), which can pass string (one filter)
        // instead of array of filters
        $this->filters = $filters;
    }

    /**
     * Returns the test method's name.
     *
     * This method will join all filters via pipe character and return as string.
     */
    public function getName(): string
    {
        return implode('|', $this->filters);
    }

    /**
     * Additional processing for options being passed to PHPUnit.
     *
     * This sets up the --filter switch used to run a single PHPUnit test method.
     * This method also provide escaping for method name to be used as filter regexp.
     *
     * @param array<string, string|null> $options
     *
     * @return array<string, string|null>
     */
    protected function prepareOptions(array $options): array
    {
        $re = array_reduce($this->filters, static function (?string $r, string $v): string {
            $isDataSet = strpos($v, ' with data set ') !== false;

            return ($r !== null ? $r . '|' : '') . preg_quote($v, '/') . ($isDataSet ? '$' : '(?:\s|$)');
        });

        $options['filter'] = '/' . $re . '/';

        return $options;
    }

    /**
     * Get the expected count of tests to be executed.
     */
    public function getTestCount(): int
    {
        return count($this->filters);
    }
}
