<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use PHPUnit\TextUI\XmlConfiguration\Configuration;
use Symfony\Component\Process\Process;

use function array_map;
use function array_merge;
use function assert;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

abstract class ExecutableTest
{
    /**
     * The path to the test to run.
     *
     * @var string
     */
    protected $path;

    /**
     * A path to the temp file created
     * for this test.
     *
     * @var string|null
     */
    protected $temp;

    /**
     * Path where the coveragereport is stored.
     *
     * @var string|null
     */
    protected $coverageFileName;

    /**
     * Last executed process command.
     *
     * @var string
     */
    protected $lastCommand = '';

    /** @var bool */
    private $needsCoverage;

    public function __construct(string $path, bool $needsCoverage)
    {
        $this->path          = $path;
        $this->needsCoverage = $needsCoverage;
    }

    /**
     * Get the expected count of tests to be executed.
     */
    abstract public function getTestCount(): int;

    /**
     * Get the path to the test being executed.
     */
    final public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the path to this test's temp file.
     * If the temp file does not exist, it will be
     * created.
     */
    final public function getTempFile(): string
    {
        if ($this->temp === null) {
            $temp = tempnam(sys_get_temp_dir(), 'PT_');
            assert($temp !== false);

            $this->temp = $temp;
        }

        return $this->temp;
    }

    /**
     * Removes the test file.
     */
    final public function deleteFile(): void
    {
        $outputFile = $this->getTempFile();
        unlink($outputFile);
    }

    /**
     * Return the last process command.
     */
    final public function getLastCommand(): string
    {
        return $this->lastCommand;
    }

    /**
     * Set the last process command.
     */
    final public function setLastCommand(string $command): void
    {
        $this->lastCommand = $command;
    }

    /**
     * Generate command line arguments with passed options suitable to handle through paratest.
     *
     * @param string                $binary   executable binary name
     * @param array<string, string> $options  command line options
     * @param string[]|null         $passthru
     *
     * @return string[] command line arguments
     */
    final public function commandArguments(string $binary, array $options = [], ?array $passthru = null): array
    {
        $options = array_merge($this->prepareOptions($options), ['log-junit' => $this->getTempFile()]);
        if ($this->needsCoverage) {
            $options['coverage-php'] = $this->getCoverageFileName();
        }

        $arguments = [$binary];
        if ($passthru !== null) {
            $arguments = array_merge($arguments, $passthru);
        }

        foreach ($options as $key => $value) {
            $arguments[] = "--$key";
            if ($value === null) {
                continue;
            }

            $arguments[] = $value;
        }

        $arguments[] = $this->getPath();
        $arguments   = array_map('strval', $arguments);

        return $arguments;
    }

    /**
     * Generate command line with passed options suitable to handle through paratest.
     *
     * @param string                $binary   executable binary name
     * @param array<string, string> $options  command line options
     * @param string[]|null         $passthru
     *
     * @return string command line
     */
    final public function command(string $binary, array $options = [], ?array $passthru = null): string
    {
        return (new Process($this->commandArguments($binary, $options, $passthru)))->getCommandLine();
    }

    /**
     * Get coverage filename.
     */
    final public function getCoverageFileName(): string
    {
        if ($this->coverageFileName === null) {
            $coverageFileName = tempnam(sys_get_temp_dir(), 'CV_');
            assert($coverageFileName !== false);

            $this->coverageFileName = $coverageFileName;
        }

        return $this->coverageFileName;
    }

    /**
     * Set process temporary filename.
     */
    final public function setTempFile(string $temp): void
    {
        $this->temp = $temp;
    }

    /**
     * A template method that can be overridden to add necessary options for a test.
     *
     * @param array<string, (string|bool|int|Configuration|string[]|null)> $options
     *
     * @return array<string, (string|bool|int|Configuration|string[]|null)>
     */
    abstract protected function prepareOptions(array $options): array;
}
