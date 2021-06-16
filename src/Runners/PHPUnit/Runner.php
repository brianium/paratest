<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Exception;
use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;

use function array_shift;
use function assert;
use function count;
use function max;
use function range;
use function usleep;

/**
 * @internal
 */
final class Runner extends BaseRunner
{
    /**
     * A collection of ExecutableTest objects that have processes
     * currently running.
     *
     * @var RunnerWorker[]
     */
    private $running = [];

    /**
     * The money maker. Runs all ExecutableTest objects in separate processes.
     */
    protected function doRun(): void
    {
        $availableTokens = range(1, $this->options->processes());
        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);
            usleep(self::CYCLE_SLEEP);

            $availableTokens = [];
            foreach ($this->running as $token => $test) {
                if ($this->testIsStillRunning($test)) {
                    continue;
                }

                unset($this->running[$token]);
                $availableTokens[] = $token;
            }
        }
    }

    /**
     * This method removes ExecutableTest objects from the pending collection
     * and adds them to the running collection. It is also in charge of recycling and
     * acquiring available test tokens for use.
     *
     * @param int[] $availableTokens
     */
    private function fillRunQueue(array $availableTokens): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $executableTest = array_shift($this->pending);

            $this->running[$token] = new RunnerWorker($executableTest, $this->options, $token);
            $this->running[$token]->run();

            if ($this->options->verbosity() < Options::VERBOSITY_VERY_VERBOSE) {
                continue;
            }

            $cmd = $this->running[$token];
            $this->output->write("\nExecuting test via: {$cmd->getExecutableTest()->getLastCommand()}\n");
        }
    }

    /**
     * Returns whether or not a test has finished being
     * executed. If it has, this method also halts a test process - optionally
     * throwing an exception if a fatal error has occurred -
     * prints feedback, and updates the overall exit code.
     *
     * @throws Exception
     */
    private function testIsStillRunning(RunnerWorker $worker): bool
    {
        if ($worker->isRunning()) {
            return true;
        }

        $this->exitcode = max($this->exitcode, (int) $worker->stop());
        if ($this->options->stopOnFailure() && $this->exitcode > 0) {
            $this->pending = [];
        }

        if (
            $this->exitcode > 0
            && $this->exitcode !== self::FAILURE_EXIT
            && $this->exitcode !== self::EXCEPTION_EXIT
        ) {
            throw $worker->getWorkerCrashedException();
        }

        $executableTest = $worker->getExecutableTest();
        try {
            $this->printer->printFeedback($executableTest);
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $worker->getWorkerCrashedException($emptyLogFileException);
        }

        if ($this->hasCoverage()) {
            $coverageMerger = $this->getCoverage();
            assert($coverageMerger !== null);
            $coverageMerger->addCoverageFromFile($executableTest->getCoverageFileName());
        }

        return false;
    }

    protected function beforeLoadChecks(): void
    {
    }
}
