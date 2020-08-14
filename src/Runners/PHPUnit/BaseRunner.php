<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use Symfony\Component\Console\Output\OutputInterface;

use function array_merge;

abstract class BaseRunner implements RunnerInterface
{
    /** @var Options */
    protected $options;

    /** @var LogInterpreter */
    protected $interpreter;

    /** @var ResultPrinter */
    protected $printer;

    /**
     * A collection of pending ExecutableTest objects that have
     * yet to run.
     *
     * @var array<int|string, ExecutableTest>
     */
    protected $pending = [];

    /**
     * A tallied exit code that returns the highest exit
     * code returned out of the entire collection of tests.
     *
     * @var int
     */
    protected $exitcode = -1;

    /**
     * CoverageMerger to hold track of the accumulated coverage.
     *
     * @var CoverageMerger
     */
    protected $coverage = null;

    /** @var OutputInterface */
    protected $output;

    public function __construct(Options $opts, OutputInterface $output)
    {
        $this->options     = $opts;
        $this->interpreter = new LogInterpreter();
        $this->printer     = new ResultPrinter($this->interpreter, $output);
        $this->output      = $output;
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. If functional mode is enabled $this->pending will
     * contain a collection of TestMethod objects instead of Suite
     * objects.
     */
    private function load(SuiteLoader $loader): void
    {
        $this->beforeLoadChecks();
        $loader->load($this->options->path());
        $executables   = $this->options->functional() ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach ($this->pending as $pending) {
            $this->printer->addTest($pending);
        }
    }

    abstract protected function beforeLoadChecks(): void;

    /**
     * Returns the highest exit code encountered
     * throughout the course of test execution.
     */
    final public function getExitCode(): int
    {
        return $this->exitcode;
    }

    /**
     * Write output to JUnit format if requested.
     */
    final protected function log(): void
    {
        if ($this->options->logJunit() === null) {
            return;
        }

        $output = $this->options->logJunit();
        $writer = new Writer($this->interpreter, $this->options->path());
        $writer->write($output);
    }

    /**
     * Write coverage to file if requested.
     */
    final protected function logCoverage(): void
    {
        if (! $this->hasCoverage()) {
            return;
        }

        $reporter = $this->getCoverage()->getReporter();

        if ($this->options->coverageClover() !== null) {
            $reporter->clover($this->options->coverageClover());
        }

        if ($this->options->coverageCrap4j() !== null) {
            $reporter->crap4j($this->options->coverageCrap4j());
        }

        if ($this->options->coverageHtml() !== null) {
            $reporter->html($this->options->coverageHtml());
        }

        if ($this->options->coverageText()) {
            $this->output->write($reporter->text());
        }

        if ($this->options->coverageXml() !== null) {
            $reporter->xml($this->options->coverageXml());
        }

        $reporter->php($this->options->coveragePhp());
    }

    private function initCoverage(): void
    {
        if (! $this->options->hasCoverage()) {
            return;
        }

        $this->coverage = new CoverageMerger($this->options->coverageTestLimit());
    }

    final protected function hasCoverage(): bool
    {
        return $this->options->hasCoverage();
    }

    final protected function getCoverage(): ?CoverageMerger
    {
        return $this->coverage;
    }

    final protected function initialize(): void
    {
        $this->initCoverage();
        $this->load(new SuiteLoader($this->options));
        $this->printer->start($this->options);
    }
}
